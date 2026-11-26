<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Http\Controllers\Api;

use App\Modules\PreBudget\Models\PbRequisitionDraft;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pre-Budget → Draft Requisition (AnnotechERP 33/34), Norban's way: the Add
 * Requisition page's rows saved as typed — every two minutes while there are
 * changes, on Save Draft and as the page closes — into the draft the page is
 * working on (its number), or the one its own key made, or a new one. A
 * person sees their own drafts; who sees every buyer's requisitions sees
 * every draft. Completing the requisitions drops the draft
 * (RequisitionApiController::store).
 */
class DraftApiController extends ApiController
{
    private const MAX_ROWS = 200;

    public function index(): JsonResponse
    {
        $this->allow('prebudget.create');

        $drafts = $this->visible()->with('user:id,name')->latest('updated_at')->limit(500)->get()->map(function (PbRequisitionDraft $d) {
            $rows = collect($d->rows ?? []);

            return [
                'draft_no' => $d->draft_no, 'row_count' => $d->row_count, 'saved_at' => $d->updated_at?->toIso8601String(), 'owner' => $d->user?->name,
                'types' => $rows->pluck('values.entry_type')->filter()->unique()->values()->all(),
                'buyers' => $rows->pluck('values.buyer')->filter()->unique()->values()->all(),
                'styles' => $rows->pluck('values.style')->filter()->unique()->take(6)->values()->all(),
            ];
        })->all();

        // `all`: this person sees everyone's drafts, so the list says whose each is.
        return ApiResponse::ok(['drafts' => $drafts, 'all' => $this->seesAll()]);
    }

    public function show(int $no): JsonResponse
    {
        $this->allow('prebudget.create');
        $d = $this->visible()->where('draft_no', $no)->first() ?? abort(404);

        return ApiResponse::ok(['draft_no' => $d->draft_no, 'rows' => $d->rows ?? [], 'saved_at' => $d->updated_at?->toIso8601String()]);
    }

    /** Save Draft / auto-save. Body: draft_no (the draft the page is on), client_key (the page's own key), rows[] of {values, artwork_url}. */
    public function save(Request $request): JsonResponse
    {
        $this->allow('prebudget.create');
        $request->validate(['draft_no' => ['nullable', 'integer'], 'client_key' => ['nullable', 'string', 'max:64'], 'rows' => ['present', 'array']]);
        $rows = self::clean((array) $request->input('rows'));
        if ($rows === []) {
            return ApiResponse::error(ErrorCode::VALIDATION_ERROR, __('prebudget.draft.nothing'), ['rows' => [__('prebudget.draft.nothing')]], status: 422);
        }
        $key = $request->input('client_key') ?: null;
        $draft = null;
        if ($request->filled('draft_no')) {
            $draft = $this->visible()->where('draft_no', (int) $request->input('draft_no'))->first();
            // Completed or deleted (in another tab) since: not brought back — the page says so.
            if (! $draft) {
                return ApiResponse::error(ErrorCode::CONFLICT, __('prebudget.draft.gone'), status: 409);
            }
        } elseif ($key) {
            $draft = $this->visible()->where('client_key', $key)->first();
        }
        $data = ['rows' => $rows, 'row_count' => count($rows)];
        try {
            $draft = $draft ? tap($draft, fn ($d) => $d->fill($data)->touch()) : $this->create($key, $data);
        } catch (UniqueConstraintViolationException) {
            // The same page's other save made it a moment ago: this one updates that draft.
            $draft = $this->visible()->where('client_key', $key)->firstOrFail();
            $draft->fill($data)->touch();
        }

        return ApiResponse::ok(['draft_no' => $draft->draft_no, 'saved_at' => $draft->updated_at?->toIso8601String()]);
    }

    public function destroy(int $no): JsonResponse
    {
        $this->allow('prebudget.create');
        $d = $this->visible()->where('draft_no', $no)->first() ?? abort(404);
        $d->forceFill(['client_key' => null])->save();
        $d->delete();

        return ApiResponse::ok(['deleted' => $no]);
    }

    /* ── Helpers ────────────────────────────────────────────────────── */

    /** The drafts this person sees: their own, or every one with View All. */
    private function visible(): Builder
    {
        return PbRequisitionDraft::query()->when(! $this->seesAll(), fn ($q) => $q->where('user_id', $this->caller()->auditUserId()));
    }

    private function seesAll(): bool
    {
        return $this->caller()->auditUserId() === null || $this->caller()->can('prebudget.view_all');
    }

    private function create(?string $key, array $data): PbRequisitionDraft
    {
        return DB::transaction(function () use ($key, $data): PbRequisitionDraft {
            $company = $this->caller()->companyId;
            DB::table('companies')->where('id', $company)->lockForUpdate()->value('id');
            $no = (int) PbRequisitionDraft::withTrashed()->max('draft_no') + 1;

            return PbRequisitionDraft::query()->create(['draft_no' => $no, 'user_id' => $this->caller()->auditUserId(), 'client_key' => $key] + $data);
        });
    }

    /**
     * Rows as typed, nothing checked (a draft may be half done or wrong): rows
     * with nothing typed are dropped, a value is text, a number or a list of
     * text, kept to a sensible length.
     *
     * @return list<array{values: array<string, mixed>, artwork_url: ?string}>
     */
    public static function clean(array $rows): array
    {
        $out = [];
        foreach (array_slice($rows, 0, self::MAX_ROWS) as $row) {
            $values = [];
            foreach ((array) ($row['values'] ?? []) as $k => $v) {
                $k = mb_substr((string) $k, 0, 64);
                if (is_array($v)) {
                    $v = array_values(array_filter(array_map(fn ($x) => is_scalar($x) ? mb_substr((string) $x, 0, 500) : null, $v), fn ($x) => $x !== null && $x !== ''));
                } elseif (is_string($v)) {
                    $v = mb_substr($v, 0, 5000);
                } elseif (! is_int($v) && ! is_float($v) && ! is_bool($v)) {
                    $v = null;
                }
                if ($v !== null && $v !== '' && $v !== []) {
                    $values[$k] = $v;
                }
            }
            if ($values !== []) {
                $url = $row['artwork_url'] ?? null;
                $kept = ['values' => $values, 'artwork_url' => is_string($url) && str_starts_with($url, '/api/files/') ? mb_substr($url, 0, 200) : null];
                // A row made from an order keeps which one (checked again on Complete) and how the picker named it.
                $order = $row['order'] ?? null;
                if (is_array($order) && is_string($order['id'] ?? null) && strlen($order['id']) === 26) {
                    $kept['order'] = ['id' => $order['id']] + array_map(fn ($v) => is_scalar($v) ? mb_substr((string) $v, 0, 200) : null, array_intersect_key($order, array_flip(['ssl', 'style', 'season', 'job_no', 'buyer'])));
                }
                $out[] = $kept;
            }
        }

        return $out;
    }
}
