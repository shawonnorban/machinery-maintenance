<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Api;

use App\Modules\Finance\Actions\SeedGlDefaults;
use App\Modules\Finance\Models\GlAccount;
use App\Modules\Finance\Models\GlEventMapping;
use App\Modules\Finance\Models\GlJournalEntry;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The event → account mapping, and what it has posted (Shared Systems §2).
 *
 * This screen is the whole point of the design: when finance signs off on
 * a chart of accounts, they change these rows and nothing else. There is
 * no code path that writes an account code.
 */
class GlMappingApiController extends ApiController
{
    public const VIEW = 'finance.gl.view_any';

    public const MANAGE = 'finance.gl.manage';

    public function __construct(
        private readonly TenantContext $context,
        private readonly SeedGlDefaults $defaults,
    ) {}

    public function index(): JsonResponse
    {
        $this->allow(self::VIEW);

        // A company that has never posted sees the placeholder mapping
        // rather than an empty screen it cannot act on.
        if (! GlEventMapping::query()->exists()) {
            $this->defaults->forCompany($this->context->companyId());
        }

        $mappings = GlEventMapping::query()
            ->with(['debitAccount:id,code,name', 'creditAccount:id,code,name', 'secondaryCreditAccount:id,code,name'])
            ->get()
            ->keyBy('event_type');

        $rows = [];

        foreach (array_keys(GlEventMapping::EVENTS) as $event) {
            $mapping = $mappings->get($event);

            $rows[] = [
                'event_type' => $event,
                'id' => $mapping?->id,
                'active' => (bool) ($mapping?->active ?? false),
                'debit_account' => $this->account($mapping?->debitAccount),
                'credit_account' => $this->account($mapping?->creditAccount),
                'secondary_credit_account' => $this->account($mapping?->secondaryCreditAccount),
                'notes' => $mapping?->notes,
                'postable' => $mapping?->isPostable() ?? false,
                'entries' => GlJournalEntry::where('event_type', $event)->count(),
            ];
        }

        return ApiResponse::ok([
            'mappings' => $rows,
            'accounts' => GlAccount::query()->where('active', true)->orderBy('code')
                ->get(['id', 'code', 'name', 'account_category'])->all(),
            'can_manage' => $this->caller()->can(self::MANAGE),
        ]);
    }

    public function update(Request $request, string $eventType): JsonResponse
    {
        $this->allow(self::MANAGE);

        if (! array_key_exists($eventType, GlEventMapping::EVENTS)) {
            abort(404);
        }

        $data = $request->validate([
            'debit_account_id' => ['nullable', 'string', 'size:26', Rule::exists('gl_accounts', 'id')],
            'credit_account_id' => ['nullable', 'string', 'size:26', Rule::exists('gl_accounts', 'id')],
            'secondary_credit_account_id' => ['nullable', 'string', 'size:26', Rule::exists('gl_accounts', 'id')],
            'active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $mapping = GlEventMapping::firstOrNew([
            'event_type' => $eventType,
            'company_id' => $this->context->companyId(),
        ]);

        $mapping->fill(array_filter($data, static fn (string $key): bool => array_key_exists($key, $data), ARRAY_FILTER_USE_KEY));
        $mapping->company_id = $this->context->companyId();
        $mapping->event_type = $eventType;
        $mapping->save();

        return ApiResponse::ok([
            'event_type' => $eventType,
            'id' => $mapping->id,
            'active' => (bool) $mapping->active,
            'postable' => $mapping->isPostable(),
        ]);
    }

    /** What the books have recorded, newest first. */
    public function entries(Request $request): JsonResponse
    {
        $this->allow(self::VIEW);

        $query = GlJournalEntry::query()->with(['lines.account:id,code,name']);

        if (is_string($search = $request->query('search')) && $search !== '') {
            $query->whereRaw('LOWER(COALESCE(source_no, \'\')) LIKE ?', ['%'.mb_strtolower($search).'%']);
        }

        $query = $this->applyFilters($query, $request, ['event_type', 'factory_id', 'source_type']);
        $query = $this->applySort($query, $request, ['entry_date', 'amount', 'event_type'], 'entry_date');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (GlJournalEntry $entry): array => [
                'id' => $entry->id,
                'event_type' => $entry->event_type,
                'entry_date' => $entry->entry_date->toDateString(),
                'amount' => (string) $entry->amount,
                'currency' => $entry->currency,
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
                'source_no' => $entry->source_no,
                'memo' => $entry->memo,
                'balanced' => $entry->isBalanced(),
                'lines' => $entry->lines->map(fn ($line): array => [
                    'side' => $line->side,
                    'amount' => (string) $line->amount,
                    'account' => $this->account($line->account),
                ])->all(),
            ],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function account(?GlAccount $account): ?array
    {
        return $account === null ? null : [
            'id' => $account->id,
            'code' => $account->code,
            'name' => $account->name,
        ];
    }
}
