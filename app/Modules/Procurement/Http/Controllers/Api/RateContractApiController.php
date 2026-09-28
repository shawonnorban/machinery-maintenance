<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Http\Controllers\Api;

use App\Modules\ItemMaster\Models\Item;
use App\Modules\Procurement\Actions\ManageRateContract;
use App\Modules\Procurement\Models\RateContract;
use App\Modules\Procurement\Support\Currencies;
use App\Modules\Procurement\Models\RateContractLine;
use App\Modules\Procurement\Services\RateContractResolver;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Vendor\Models\Vendor;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Rate contracts over the API (Procurement spec §14).
 *
 * `lookup` is the endpoint the order form calls: it answers "what did we
 * agree for this item, with this supplier, at this quantity" so a buyer
 * sees the price before saving rather than discovering it afterwards.
 * The order itself does not depend on it — `ManagePurchaseOrder` asks the
 * same resolver server-side — so a screen that never calls it still
 * produces correctly priced orders.
 */
class RateContractApiController extends ApiController
{
    public const VIEW = 'procurement.contract.view_any';

    public const MANAGE = 'procurement.contract.manage';

    private const FILTERS = ['status', 'vendor_id', 'factory_id'];

    private const SORTS = ['contract_no', 'valid_from', 'valid_to', 'status'];

    public function __construct(
        private readonly TenantContext $context,
        private readonly ManageRateContract $contracts,
        private readonly RateContractResolver $resolver,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow(self::VIEW);

        $query = RateContract::query()
            ->with(['vendor:id,name,code', 'factory:id,name'])
            ->withCount('lines')
            // A company-wide contract belongs to every factory the caller
            // can reach, so it is never filtered out by scope.
            ->where(fn ($q) => $q->whereNull('factory_id')
                ->orWhereIn('factory_id', $this->context->accessibleFactoryIds()));

        if (is_string($search = $request->query('search')) && $search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(contract_no) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(COALESCE(title, \'\')) LIKE ?', [$needle])
                ->orWhereHas('vendor', fn ($v) => $v->whereRaw('LOWER(name) LIKE ?', [$needle])));
        }

        if ($request->boolean('live')) {
            $query->liveOn(CarbonImmutable::today());
        }

        $query = $this->applyFilters($query, $request, self::FILTERS);
        $query = $this->applySort($query, $request, self::SORTS, 'valid_from');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (RateContract $contract): array => $this->summary($contract),
        );
    }

    public function formOptions(): JsonResponse
    {
        $this->allow(self::MANAGE);

        $factoryIds = $this->context->accessibleFactoryIds();

        return ApiResponse::ok([
            'factories' => Factory::whereIn('id', $factoryIds)->orderBy('name')->get(['id', 'name'])->all(),
            'vendors' => Vendor::query()->where('status', 'ACTIVE')
                ->whereIn('vendor_type', ['SUPPLIER', 'BOTH'])
                ->orderBy('name')->get(['id', 'name', 'code', 'default_currency'])->all(),
            'currencies' => Currencies::all(),
        ]);
    }

    public function show(RateContract $contract): JsonResponse
    {
        $this->allow(self::VIEW);
        $this->assertReachable($contract);

        return ApiResponse::ok($this->detail($contract));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow(self::MANAGE);

        $data = $this->validated($request, creating: true);

        if (! $this->context->canAccessFactory($data['factory_id'])) {
            abort(403);
        }

        return ApiResponse::created($this->detail($this->contracts->create(
            Factory::findOrFail($data['factory_id']),
            $data,
            $data['lines'],
            $this->caller()->auditUserId(),
        )));
    }

    public function update(Request $request, RateContract $contract): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachable($contract);

        $data = $this->validated($request, creating: false);

        return ApiResponse::ok($this->detail($this->contracts->update(
            $contract, $data, $data['lines'] ?? null,
        )));
    }

    public function activate(RateContract $contract): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachable($contract);

        return ApiResponse::ok($this->detail($this->contracts->activate($contract)));
    }

    public function cancel(Request $request, RateContract $contract): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachable($contract);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return ApiResponse::ok($this->detail($this->contracts->cancel($contract, $data['reason'] ?? null)));
    }

    /** What the order form shows a buyer before they save. */
    public function lookup(Request $request): JsonResponse
    {
        $this->allow(self::VIEW);

        $data = $request->validate([
            'vendor_id' => ['required', 'string', 'size:26'],
            'item_id' => ['required', 'string', 'size:26'],
            'quantity' => ['nullable', 'numeric', 'gte:0'],
            'factory_id' => ['nullable', 'string', 'size:26'],
            'on' => ['nullable', 'date'],
        ]);

        $agreed = $this->resolver->resolve(
            Vendor::findOrFail($data['vendor_id']),
            Item::findOrFail($data['item_id']),
            (string) ($data['quantity'] ?? '0'),
            $data['factory_id'] ?? null,
            isset($data['on']) ? CarbonImmutable::parse($data['on']) : null,
        );

        if ($agreed === null) {
            return ApiResponse::ok(['rate' => null, 'contract' => null]);
        }

        return ApiResponse::ok([
            'rate' => $agreed['rate'],
            'min_quantity' => (string) $agreed['line']->min_quantity,
            'lead_time_days' => $agreed['line']->lead_time_days,
            'currency' => $agreed['contract']->currency,
            'contract' => [
                'id' => $agreed['contract']->id,
                'contract_no' => $agreed['contract']->contract_no,
                'valid_to' => $agreed['contract']->valid_to?->toDateString(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'factory_id' => [$creating ? 'required' : 'sometimes', 'string', 'size:26'],
            'vendor_id' => [$creating ? 'required' : 'sometimes', 'string', 'size:26'],
            'company_wide' => ['nullable', 'boolean'],
            'title' => ['nullable', 'string', 'max:160'],
            'supplier_reference' => ['nullable', 'string', 'max:64'],
            'valid_from' => [$creating ? 'required' : 'nullable', 'date'],
            'valid_to' => [$creating ? 'required' : 'nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => [$creating ? 'required' : 'nullable', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'string', 'size:26'],
            'lines.*.uom_id' => ['nullable', 'string', 'size:26'],
            'lines.*.rate' => ['required', 'numeric', 'gt:0'],
            'lines.*.min_quantity' => ['nullable', 'numeric', 'gte:0'],
            'lines.*.lead_time_days' => ['nullable', 'integer', 'min:0'],
            'lines.*.notes' => ['nullable', 'string', 'max:500'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(RateContract $contract): array
    {
        return [
            'id' => $contract->id,
            'contract_no' => $contract->contract_no,
            'title' => $contract->title,
            'status' => $contract->status,
            'valid_from' => $contract->valid_from?->toDateString(),
            'valid_to' => $contract->valid_to?->toDateString(),
            // Whether it actually applies today, which the status alone
            // cannot say — expiry is the calendar's business, not a column.
            'is_live' => $contract->isLive(),
            'has_expired' => $contract->hasExpired(),
            'days_to_expiry' => $contract->daysToExpiry(),
            'currency' => $contract->currency,
            'line_count' => $contract->lines_count ?? null,
            'vendor' => $contract->vendor === null ? null : ['id' => $contract->vendor->id, 'name' => $contract->vendor->name, 'code' => $contract->vendor->code],
            // Null means every factory.
            'factory' => $contract->factory === null ? null : ['id' => $contract->factory->id, 'name' => $contract->factory->name],
            'company_wide' => $contract->factory_id === null,
            'created_at' => $contract->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(RateContract $contract): array
    {
        $contract->loadMissing(['vendor:id,name,code', 'factory:id,name', 'creator:id,name', 'lines.uom:id,code,name']);

        $mayManage = $this->caller()->can(self::MANAGE);

        return $this->summary($contract) + [
            'supplier_reference' => $contract->supplier_reference,
            'notes' => $contract->notes,
            'created_by' => $contract->creator === null ? null : ['id' => $contract->creator->id, 'name' => $contract->creator->name],
            'activated_at' => $contract->activated_at?->toIso8601String(),
            'cancelled_at' => $contract->cancelled_at?->toIso8601String(),
            'can_edit' => $contract->status === 'DRAFT' && $mayManage,
            'can_activate' => $contract->canTransitionTo('ACTIVE') && $mayManage,
            'can_cancel' => $contract->canTransitionTo('CANCELLED') && $mayManage,
            'lines' => $contract->lines
                ->sortBy([['item_code', 'asc'], ['min_quantity', 'asc']])
                ->values()
                ->map(fn (RateContractLine $line): array => [
                    'id' => $line->id,
                    'item_id' => $line->item_id,
                    'item_code' => $line->item_code,
                    'item_name' => $line->item_name,
                    'uom' => $line->uom === null ? null : ['code' => $line->uom->code, 'name' => $line->uom->name],
                    'rate' => (string) $line->rate,
                    'min_quantity' => (string) $line->min_quantity,
                    'lead_time_days' => $line->lead_time_days,
                    'notes' => $line->notes,
                ])->all(),
        ];
    }

    private function assertReachable(RateContract $contract): void
    {
        if ($contract->factory_id === null) {
            // Company-wide: reachable by anyone in the company, which the
            // tenant scope has already settled.
            return;
        }

        if (! $this->context->canAccessFactory((string) $contract->factory_id)) {
            abort(404);
        }
    }
}
