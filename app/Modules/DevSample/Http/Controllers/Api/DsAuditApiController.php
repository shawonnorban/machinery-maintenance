<?php

declare(strict_types=1);

namespace App\Modules\DevSample\Http\Controllers\Api;

use App\Modules\DevSample\Models\DevSample;
use App\Modules\DevSample\Models\DsChange;
use App\Modules\DevSample\Support\DsSheet;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Dev Sample Status's Audit Log (AnnotechERP 37, phase 5: the change log on
 * the web and in the app, as Order Summary's): every save, one entry per
 * sample per save, with the cells it changed — old and new. Only the samples
 * this caller sees on the sheet: everyone's with devsample.view_all, else
 * their own. Filters: from, to (Dhaka days), kind, user, q (any of the
 * sample's values), page (50 a page).
 */
class DsAuditApiController extends ApiController
{
    public const KINDS = ['CREATE', 'EDIT', 'DELETE', 'RECALC'];

    public function index(Request $request): JsonResponse
    {
        $this->allow('devsample.view');
        $data = $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'kind' => ['nullable', Rule::in(self::KINDS)],
            'user' => ['nullable', 'string', 'size:26'], 'q' => ['nullable', 'string', 'max:120'], 'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $labels = (new DsSheet($this->caller()->companyId, $this->caller()->user))->columns->pluck('label', 'key');
        $users = DB::table('users')->pluck('name', 'id');
        $visible = $this->visible()->withTrashed()->select('id');
        $base = DsChange::query()->whereIn('sample_id', $visible)
            ->when($data['kind'] ?? null, fn ($q, $k) => $q->where('kind', $k))
            ->when($data['user'] ?? null, fn ($q, $u) => $q->where('changed_by', $u))
            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereRaw("(changed_at at time zone 'Asia/Dhaka')::date >= ?", [$d]))
            ->when($data['to'] ?? null, fn ($q, $d) => $q->whereRaw("(changed_at at time zone 'Asia/Dhaka')::date <= ?", [$d]))
            ->when($data['q'] ?? null, fn ($q, $s) => $q->whereIn('sample_id', DevSample::withTrashed()->whereRaw('LOWER("values"::text) LIKE ?', ['%'.mb_strtolower(addcslashes($s, '\\%_')).'%'])->select('id')));
        $batches = (clone $base)->toBase()->selectRaw('batch, sample_id, max(changed_at) as at')->groupBy('batch', 'sample_id')
            ->orderByDesc('at')->paginate(50, ['*'], 'page', (int) ($data['page'] ?? 1));
        $cells = (clone $base)->whereIn('batch', collect($batches->items())->pluck('batch'))->get()->groupBy(fn ($c) => $c->batch.'|'.$c->sample_id);
        $samples = DevSample::withTrashed()->whereKey(collect($batches->items())->pluck('sample_id'))->get()->keyBy('id');

        return ApiResponse::ok([
            'entries' => collect($batches->items())->map(function ($b) use ($cells, $samples, $labels, $users) {
                $g = ($cells[$b->batch.'|'.$b->sample_id] ?? collect())->sortBy('changed_at');
                $s = $samples[$b->sample_id] ?? null;
                $v = $s?->values ?? [];
                $first = $g->first();

                return [
                    'at' => $first?->changed_at?->toIso8601String(), 'kind' => $first?->kind, 'by' => $users[$first?->changed_by] ?? null,
                    'serial_no' => $s?->serial_no,
                    'sample' => trim(implode(' / ', array_filter([(string) ($v['styling'] ?? ''), implode(', ', (array) ($v['job_no'] ?? []))]))),
                    'buyer' => $v['buyer'] ?? null, 'deleted' => (bool) $s?->trashed(),
                    'cells' => $g->whereNotNull('column_key')->map(fn ($c) => ['column' => $labels[$c->column_key] ?? Str::headline($c->column_key), 'old' => $c->old_value, 'new' => $c->new_value])->values(),
                ];
            })->values(),
            'page' => ['current' => $batches->currentPage(), 'last' => $batches->lastPage(), 'total' => $batches->total()],
            'users' => DsChange::query()->whereIn('sample_id', $visible)->distinct()->pluck('changed_by')->filter()
                ->map(fn ($id) => ['value' => $id, 'label' => $users[$id] ?? $id])->sortBy('label')->values(),
        ]);
    }

    /** The samples this caller may read — the sheet's own rule (DevSampleApiController::visible). */
    private function visible(): Builder
    {
        $query = DevSample::query();
        if (! $this->caller()->can('devsample.view_all')) {
            $query->where('created_by', $this->caller()->auditUserId());
        }

        return $query;
    }
}
