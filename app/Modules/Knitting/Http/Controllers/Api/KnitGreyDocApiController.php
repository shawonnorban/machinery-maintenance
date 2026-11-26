<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\Knitting\Models\KnitGreyAdjustment;
use App\Modules\Knitting\Models\KnitGreyAdjustmentLine;
use App\Modules\Knitting\Models\KnitGreyLot;
use App\Modules\Knitting\Support\GreyStore;
use App\Modules\Knitting\Support\YarnStore;
use App\Modules\Merchandise\Models\Order;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Knit Production → Fabric Store (AnnotechERP 15/05), the grey store's
 * correction paper (the movements between stock lines are
 * KnitGreyMoveApiController's), as Brickland works it:
 *
 * - Grey Fabric Stock Correction (GFA-…): physical-count corrections, one
 *   line per stock line (order, shade and fabric — and the roll, when there
 *   is one); positive puts fabric on the shelf, negative takes it off.
 *   Saving moves nothing; verified and then approved, it posts.
 */
class KnitGreyDocApiController extends ApiController
{
    /* ---------------- Grey Fabric Stock Correction ---------------- */

    public function adjustments(): JsonResponse
    {
        $this->allow('knitting.fstore.view');
        $rows = KnitGreyAdjustment::query()->orderByDesc('adj_date')->orderByDesc('adj_no')->limit(500)->get();
        $lines = KnitGreyAdjustmentLine::query()->whereIn('adjustment_id', $rows->pluck('id'))->get()->groupBy('adjustment_id');
        $stores = DB::table('stores')->whereIn('id', $rows->pluck('store_id')->unique())->pluck('name', 'id');
        $company = GreyStore::companyName();

        return ApiResponse::ok($rows->map(function (KnitGreyAdjustment $a) use ($lines, $stores, $company) {
            $mine = $lines[$a->id] ?? collect();

            return [
                'adj_no' => $a->adj_no, 'adj_date' => $a->adj_date->toDateString(), 'company' => $company, 'store_id' => $a->store_id, 'store' => $stores[$a->store_id] ?? null,
                'lines' => $mine->count(), 'qty' => round((float) $mine->sum('qty'), 3), 'value' => round((float) $mine->sum(fn ($l) => $l->qty * (float) ($l->rate ?? 0)), 2),
                'reason' => $a->reason, 'status' => $a->status, 'activity' => $a->activity,
            ];
        })->values()->all());
    }

    public function showAdjustment(string $no): JsonResponse
    {
        $this->allow('knitting.fstore.view');

        return ApiResponse::ok($this->presentAdjustment($this->find(KnitGreyAdjustment::class, 'adj_no', $no)));
    }

    public function saveAdjustment(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.fstore.manage');
        $row = $no ? $this->find(KnitGreyAdjustment::class, 'adj_no', $no) : null;
        if ($row && ($row->status !== 'DRAFT' || $row->activity !== 'ACTIVE')) {
            throw ValidationException::withMessages(['status' => $this->cannotText($row, 'adj_no', 'save', 'adj_status')]);
        }
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'adj_date' => ['required', 'date'], 'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $company)],
            'reason' => ['nullable', 'string', 'max:255'], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:200'],
            'lines.*.order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $company)],
            'lines.*.colour_id' => ['nullable', Rule::exists('colours', 'id')->where('company_id', $company)], 'lines.*.colour_name' => ['nullable', 'string', 'max:80'],
            'lines.*.fabric' => ['required', 'string', 'max:160'], 'lines.*.roll_no' => ['nullable', 'string', 'max:40'], 'lines.*.part' => ['nullable', 'string', 'max:40'],
            'lines.*.qty' => ['required', 'numeric', 'not_in:0', 'between:-99999999,99999999'], 'lines.*.uom' => ['required', 'string', 'max:16', UomCodes::rule($company)],
            'lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:99999999'], 'lines.*.reason' => ['nullable', 'string', 'max:255'],
        ], [
            'adj_date.required' => __('knitting.fs.v.adj_date'), 'store_id.required' => __('knitting.fs.v.store'), 'lines.*.fabric.required' => __('knitting.fs.v.line_fabric'),
            'lines.*.qty.required' => __('knitting.fs.v.adj_qty'), 'lines.*.qty.not_in' => __('knitting.fs.v.adj_qty'), 'lines.*.uom.required' => __('knitting.fs.v.uom'),
        ]);
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('knitting.fs.v.no_adj_lines')]);
        }
        $row = DB::transaction(function () use ($row, $data, $lines): KnitGreyAdjustment {
            $head = ['adj_date' => $data['adj_date'], 'store_id' => $data['store_id'], 'reason' => ($data['reason'] ?? '') ?: null, 'remarks' => ($data['remarks'] ?? '') ?: null];
            $row ??= KnitGreyAdjustment::query()->create($head + [
                'adj_no' => GreyStore::nextNumber('GFA', 'knit_grey_adjustments', 'adj_no', $data['adj_date']), 'created_by' => $this->caller()->auditUserId(),
            ]);
            $row->update($head);
            KnitGreyAdjustmentLine::query()->where('adjustment_id', $row->id)->delete();
            foreach ($lines as $i => $l) {
                KnitGreyAdjustmentLine::query()->create(['company_id' => $row->company_id, 'adjustment_id' => $row->id, 'sort_order' => $i + 1, 'qty' => (float) $l['qty'],
                    'uom' => UomCodes::normalize($l['uom']), 'fabric' => trim($l['fabric'])]
                    + collect($l)->only(['order_id', 'colour_id', 'colour_name', 'roll_no', 'part', 'rate', 'reason'])->map(fn ($v) => $v === '' ? null : $v)->all());
            }

            return $row;
        });

        return $no ? ApiResponse::ok($this->presentAdjustment($row->refresh())) : ApiResponse::created($this->presentAdjustment($row->refresh()));
    }

    public function adjustmentTransition(string $no, string $action): JsonResponse
    {
        $this->allow('knitting.fstore.manage');
        $row = $this->find(KnitGreyAdjustment::class, 'adj_no', $no);
        if ($row->activity !== 'ACTIVE') {
            throw ValidationException::withMessages(['status' => __('knitting.fs.v.adj_cancelled', ['no' => $row->adj_no])]);
        }
        [$from, $to] = match ($action) {
            'verify' => [['DRAFT'], 'VERIFIED'],
            'approve' => [['VERIFIED'], 'APPROVED'],
            'cancel' => [['DRAFT', 'VERIFIED', 'APPROVED'], null],
            default => abort(404),
        };
        $this->assertStatus($row, 'adj_no', $from, $action, 'adj_status');
        DB::transaction(function () use ($row, $action, $to): void {
            $date = $row->adj_date->toDateString();
            if ($action === 'approve') {
                GreyStore::assertOpen($date, 'adj_date');
                $entries = [];
                foreach (KnitGreyAdjustmentLine::query()->where('adjustment_id', $row->id)->orderBy('sort_order')->get() as $i => $l) {
                    $lot = $this->adjustedLot($row, $l, $date);
                    if ($l->qty < 0 && GreyStore::balance($lot->id) + 0.00005 < -$l->qty) {
                        throw ValidationException::withMessages(['lines' => __('knitting.fs.v.adj_short', ['n' => $i + 1, 'have' => round(GreyStore::balance($lot->id), 3)])]);
                    }
                    $l->update(['lot_id' => $lot->id]);
                    $entries[] = ['lot_id' => $lot->id, 'qty' => (float) $l->qty, 'rolls' => $lot->roll_id ? ($l->qty < 0 && GreyStore::balance($lot->id) + $l->qty < 0.00005 ? -1 : 0) : 0,
                        'value' => (float) $l->qty * (float) ($l->rate ?? $lot->rate), 'doc_line_id' => $l->id];
                }
                GreyStore::post('ADJUST', $row->adj_no, $date, $entries);
            }
            if ($action === 'cancel') {
                if ($row->status === 'APPROVED') {
                    GreyStore::assertOpen($date, 'adj_date');
                    GreyStore::unpost('ADJUST', $row->adj_no);
                }
                $row->update(['activity' => 'CANCELLED']);

                return;
            }
            $row->update(['status' => $to]);
        });

        return ApiResponse::ok($this->presentAdjustment($row->refresh()));
    }

    /* ---------------- helpers ---------------- */

    private function find(string $model, string $column, string $no): Model
    {
        return $model::query()->where($column, $no)->firstOrFail();
    }

    private function cannotText(Model $row, string $column, string $action, string $group): string
    {
        return __('knitting.fs.v.cannot', ['no' => $row->{$column}, 'status' => __("knitting.fs.{$group}.{$row->status}"), 'action' => __("knitting.fs.action.{$action}")]);
    }

    private function assertStatus(Model $row, string $column, array $from, string $action, string $group): void
    {
        if (! in_array($row->status, $from, true)) {
            throw ValidationException::withMessages(['status' => $this->cannotText($row, $column, $action, $group)]);
        }
    }

    /** The stock line a correction is struck on: a roll's own, or the order, shade and fabric's. */
    private function adjustedLot(KnitGreyAdjustment $a, KnitGreyAdjustmentLine $l, string $date): KnitGreyLot
    {
        if ($l->roll_no) {
            $roll = KnitGreyLot::query()->where('store_id', $a->store_id)->whereNotNull('roll_id')
                ->whereIn('roll_id', DB::table('knit_rolls')->whereRaw('upper(roll_code) = ?', [mb_strtoupper($l->roll_no)])->pluck('id'))->first();
            if ($roll) {
                return $roll;
            }
        }

        // The order, the shade and the fabric are what a balance is struck on: the line of the store already holding them, the fullest first.
        $low = fn ($v) => mb_strtolower(trim((string) ($v ?? '')));
        $match = KnitGreyLot::query()->where('store_id', $a->store_id)->whereNull('roll_id')
            ->when($l->order_id, fn ($q) => $q->where('order_id', $l->order_id), fn ($q) => $q->whereNull('order_id'))
            ->whereRaw("lower(coalesce(colour_name, '')) = ?", [$low($l->colour_name)])->whereRaw('lower(fabric) = ?', [$low($l->fabric)])
            ->whereRaw("upper(coalesce(roll_no, '')) = ?", [mb_strtoupper(trim((string) ($l->roll_no ?? '')))])
            ->whereRaw("lower(coalesce(part, '')) = ?", [$low($l->part)])->get()
            ->sortByDesc(fn (KnitGreyLot $x) => GreyStore::balance($x->id))->first();
        if ($match) {
            return $match;
        }

        return GreyStore::lotFor([
            'store_id' => $a->store_id, 'kind' => 'IN_HOUSE', 'order_id' => $l->order_id, 'buyer_id' => $l->order_id ? Order::query()->whereKey($l->order_id)->value('buyer_id') : null,
            'colour_id' => $l->colour_id, 'colour_name' => $l->colour_name, 'fabric' => $l->fabric, 'roll_no' => $l->roll_no, 'part' => $l->part,
        ], $date, (float) ($l->rate ?? 0));
    }

    /** @return array<string, mixed> */
    private function presentAdjustment(KnitGreyAdjustment $a): array
    {
        $lines = KnitGreyAdjustmentLine::query()->where('adjustment_id', $a->id)->orderBy('sort_order')->get();
        $orders = Order::query()->whereIn('id', $lines->pluck('order_id')->filter()->unique())->get(['id', 'order_no', 'style_no', 'work_style_no'])->keyBy('id');

        return [
            'id' => $a->id, 'adj_no' => $a->adj_no, 'adj_date' => $a->adj_date->toDateString(), 'store_id' => $a->store_id,
            'store' => DB::table('stores')->where('id', $a->store_id)->value('name'), 'reason' => $a->reason, 'remarks' => $a->remarks, 'status' => $a->status,
            'activity' => $a->activity, 'company' => GreyStore::companyName(),
            'lines' => $lines->map(fn (KnitGreyAdjustmentLine $l) => collect($l->only(['order_id', 'colour_id', 'colour_name', 'fabric', 'roll_no', 'part', 'qty', 'uom', 'rate', 'reason']))->all() + [
                'order' => isset($orders[$l->order_id]) ? trim(($orders[$l->order_id]->work_style_no ?: $orders[$l->order_id]->style_no).' · '.$orders[$l->order_id]->order_no, ' ·') : null,
                'value' => round($l->qty * (float) ($l->rate ?? 0), 2),
            ])->values()->all(),
        ];
    }
}
