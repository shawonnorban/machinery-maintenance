<?php

declare(strict_types=1);

namespace App\Modules\StoreMaterial\Http\Controllers\Api;

use App\Modules\HrPayroll\Models\HrEmployee;
use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\MaterialProcurement\Support\Procurement;
use App\Modules\StoreMaterial\Models\Adjustment;
use App\Modules\StoreMaterial\Models\MaterialOut;
use App\Modules\StoreMaterial\Models\MaterialReturn;
use App\Modules\StoreMaterial\Models\MinLevel;
use App\Modules\StoreMaterial\Models\Roll;
use App\Modules\StoreMaterial\Models\RollMove;
use App\Modules\StoreMaterial\Models\Stock;
use App\Modules\StoreMaterial\Support\StoreBook;
use App\Modules\Tenancy\Models\Floor;
use App\Modules\Tenancy\Models\ProductionLine;
use App\Modules\Tenancy\Models\Section;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Store & Material's movements off the shelf (AnnotechERP 09/01), as Brickland
 * keeps them. Material Out: material to a floor, section and line for a
 * purpose, handed to an employee — never more than is in stock; fabric held
 * in rolls may go out roll by roll, never more than a roll has. Material
 * Return: unused material back against its Material Out, up to what is
 * still out — on the roll it went out on, when it went by roll. Stock Adjustment: a physical count against the system's, which
 * reaches the books only when the Store Manager approves; and the minimum
 * stock each material is kept at.
 */
class IssueApiController extends ApiController
{
    public const PURPOSES = ['PRODUCTION', 'CUTTING', 'SEWING', 'FINISHING', 'PRINTING', 'EMBROIDERY', 'PACKING', 'SAMPLE', 'REPLACEMENT', 'MAINTENANCE', 'OTHER'];

    public const RETURN_REASONS = ['EXCESS', 'COMPLETED', 'WRONG_ISSUE', 'LINE_CANCELLED', 'REPLACEMENT', 'DAMAGED', 'OTHER'];

    public const ADJUST_REASONS = ['COUNT_DIFFERENCE', 'DAMAGED', 'LOST', 'FOUND', 'MEASUREMENT', 'OTHER'];

    /** What Material Out, Material Return and Stock Adjustment pick from. */
    public function options(): JsonResponse
    {
        $this->allow('storematerial.view');
        $today = now()->toDateString();

        return ApiResponse::ok([
            'next' => [
                'mo' => Procurement::previewNumber('MO', 'sm_material_outs', 'mo_no', $today), 'mr' => Procurement::previewNumber('MR', 'sm_material_returns', 'mr_no', $today),
                'sa' => Procurement::previewNumber('SA', 'sm_adjustments', 'sa_no', $today),
            ],
            'stocks' => $this->stockOptions(),
            'items' => MaterialItem::query()->where('active', true)->orderBy('name')->get()->map(fn (MaterialItem $i) => ['value' => $i->id, 'label' => $i->name, 'uom' => $i->uom])->values()->all(),
            'purposes' => self::PURPOSES, 'return_reasons' => self::RETURN_REASONS, 'adjust_reasons' => self::ADJUST_REASONS,
            'floors' => Floor::query()->where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name'])->map(fn ($f) => ['value' => $f->id, 'label' => $f->name])->all(),
            'sections' => Section::query()->where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name', 'floor_id', 'department_id'])->map(fn ($s) => ['value' => $s->id, 'label' => $s->name, 'floor_id' => $s->floor_id])->all(),
            'lines' => ProductionLine::query()->where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name', 'floor_id', 'section_id'])->map(fn ($l) => ['value' => $l->id, 'label' => $l->name, 'floor_id' => $l->floor_id, 'section_id' => $l->section_id])->all(),
            'adjustments' => $this->adjustmentRows(),
            'min_levels' => $this->minLevelRows(),
        ]);
    }

    /** Confirm Material Out. */
    public function storeOut(Request $request): JsonResponse
    {
        $this->allow('storematerial.issue');
        $company = app(TenantContext::class)->companyId();
        $in = fn (string $table) => Rule::exists($table, 'id')->where('company_id', $company);
        $data = $request->validate([
            'stock_id' => ['required', $in('sm_stocks')], 'purpose' => ['required', Rule::in(self::PURPOSES)], 'qty' => ['required', 'numeric', 'gt:0'],
            'floor_id' => ['required', $in('floors')], 'section_id' => ['required', $in('sections')], 'line_id' => ['required', $in('production_lines')],
            'receiver' => ['required', 'string', 'max:40'], 'remarks' => ['nullable', 'string', 'max:2000'],
            'rolls' => ['nullable', 'array', 'max:500'], 'rolls.*.roll_id' => ['required', 'string'], 'rolls.*.qty' => ['nullable', 'numeric', 'min:0'],
        ], [
            'stock_id.required' => __('storematerial.mo.material_required'), 'purpose.required' => __('storematerial.mo.purpose_required'),
            'qty.required' => __('storematerial.mo.qty_required'), 'qty.gt' => __('storematerial.mo.qty_required'),
            'floor_id.required' => __('storematerial.mo.floor_required'), 'section_id.required' => __('storematerial.mo.section_required'),
            'line_id.required' => __('storematerial.mo.line_required'), 'receiver.required' => __('storematerial.mo.receiver_required'),
        ]);
        $receiver = StoreBook::employee($data['receiver'], 'receiver');
        $out = DB::transaction(function () use ($data, $receiver): MaterialOut {
            $stock = Stock::query()->lockForUpdate()->findOrFail($data['stock_id']);
            $available = StoreBook::balance($stock->id);
            $qty = round((float) $data['qty'], 3);
            if ($qty > $available + 0.0005) {
                throw ValidationException::withMessages(['qty' => __('storematerial.mo.over', ['available' => StoreBook::qty($available), 'uom' => $stock->uom])]);
            }
            $taken = $this->takenRolls($stock, $data['rolls'] ?? [], $qty);
            $today = now()->toDateString();
            $out = MaterialOut::query()->create([
                'mo_no' => Procurement::nextNumber('MO', 'sm_material_outs', 'mo_no', $today), 'mo_date' => $today, 'stock_id' => $stock->id, 'purpose' => $data['purpose'],
                'qty' => $qty, 'floor_id' => $data['floor_id'], 'section_id' => $data['section_id'], 'line_id' => $data['line_id'], 'receiver_id' => $receiver->id,
                'remarks' => $data['remarks'] ?? null, 'created_by' => $this->caller()->auditUserId(),
            ]);
            StoreBook::post($stock, 'MATERIAL_OUT', $out->mo_no, $out->id, $today, out: $qty, by: $this->caller()->auditUserId());
            foreach ($taken as [$roll, $q]) {
                StoreBook::moveRoll($roll, 'MATERIAL_OUT', $out->mo_no, $out->id, $today, out: $q, by: $this->caller()->auditUserId());
            }

            return $out;
        });

        return ApiResponse::created($this->outPayload($out->fresh()) + ['stocks' => $this->stockOptions()]);
    }

    /** A Material Out as Material Return reads it: what went out, what came back, what may still. */
    public function showOut(string $mo): JsonResponse
    {
        $this->allow('storematerial.view');

        return ApiResponse::ok($this->outPayload($this->findOut($mo)));
    }

    /** Confirm Return. */
    public function storeReturn(Request $request): JsonResponse
    {
        $this->allow('storematerial.issue');
        $data = $request->validate([
            'mo_no' => ['required', 'string'], 'roll_id' => ['nullable', 'string'], 'qty' => ['required', 'numeric', 'gt:0'], 'reason' => ['required', Rule::in(self::RETURN_REASONS)],
            'line_name' => ['required', 'string', 'max:120'], 'returned_by' => ['nullable', 'string', 'max:40'], 'received_by' => ['nullable', 'string', 'max:40'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ], [
            'mo_no.required' => __('storematerial.mr.mo_required'), 'qty.required' => __('storematerial.mr.qty_required'), 'qty.gt' => __('storematerial.mr.qty_required'),
            'reason.required' => __('storematerial.mr.reason_required'), 'line_name.required' => __('storematerial.mr.line_required'),
        ]);
        $out = $this->findOut($data['mo_no']);
        $returnedBy = StoreBook::employee($data['returned_by'] ?? null, 'returned_by');
        $receivedBy = StoreBook::employee($data['received_by'] ?? null, 'received_by');
        $mr = DB::transaction(function () use ($out, $data, $returnedBy, $receivedBy): MaterialReturn {
            MaterialOut::query()->lockForUpdate()->findOrFail($out->id);
            $eligible = round((float) $out->qty - $this->returnedOf($out->id), 3);
            $qty = round((float) $data['qty'], 3);
            $uom = Stock::query()->whereKey($out->stock_id)->value('uom');
            if ($qty > $eligible + 0.0005) {
                throw ValidationException::withMessages(['qty' => __('storematerial.mr.over', ['eligible' => StoreBook::qty($eligible), 'uom' => $uom])]);
            }
            // What went out by roll comes back on one of those rolls, up to what that roll took.
            $rolls = collect($this->outRolls($out));
            $roll = null;
            if ($rolls->isNotEmpty()) {
                $picked = $rolls->firstWhere('roll_id', $data['roll_id'] ?? null) ?? throw ValidationException::withMessages(['roll_id' => __('storematerial.mr.roll_required')]);
                if ($qty > $picked['eligible'] + 0.0005) {
                    throw ValidationException::withMessages(['qty' => __('storematerial.mr.roll_over', ['roll' => $picked['roll_no'], 'eligible' => StoreBook::qty($picked['eligible']), 'uom' => $uom])]);
                }
                $roll = Roll::query()->findOrFail($picked['roll_id']);
            }
            $today = now()->toDateString();
            $mr = MaterialReturn::query()->create([
                'mr_no' => Procurement::nextNumber('MR', 'sm_material_returns', 'mr_no', $today), 'mr_date' => $today, 'material_out_id' => $out->id, 'roll_id' => $roll?->id, 'qty' => $qty,
                'reason' => $data['reason'], 'line_name' => trim($data['line_name']), 'returned_by' => $returnedBy?->id, 'received_by' => $receivedBy?->id,
                'remarks' => $data['remarks'] ?? null, 'created_by' => $this->caller()->auditUserId(),
            ]);
            StoreBook::post(Stock::query()->findOrFail($out->stock_id), 'MATERIAL_RETURN', $mr->mr_no, $mr->id, $today, return: $qty, by: $this->caller()->auditUserId());
            if ($roll) {
                StoreBook::moveRoll($roll, 'MATERIAL_RETURN', $mr->mr_no, $mr->id, $today, return: $qty, by: $this->caller()->auditUserId());
            }

            return $mr;
        });

        return ApiResponse::created(['mr_no' => $mr->mr_no, 'qty' => (float) $mr->qty, 'material_out' => $this->outPayload($out->fresh())]);
    }

    /** Submit for Approval: a count against the system's. */
    public function storeAdjustment(Request $request): JsonResponse
    {
        $this->allow('storematerial.adjust');
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'stock_id' => ['required', Rule::exists('sm_stocks', 'id')->where('company_id', $company)], 'physical_qty' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', Rule::in(self::ADJUST_REASONS)], 'remarks' => ['nullable', 'string', 'max:2000'],
        ], [
            'stock_id.required' => __('storematerial.sa.material_required'), 'physical_qty.required' => __('storematerial.sa.physical_required'),
            'reason.required' => __('storematerial.sa.reason_required'),
        ]);
        $system = StoreBook::balance($data['stock_id']);
        if (abs(round((float) $data['physical_qty'], 3) - $system) < 0.0005) {
            throw ValidationException::withMessages(['physical_qty' => __('storematerial.sa.no_difference')]);
        }
        $today = now()->toDateString();
        Adjustment::query()->create([
            'sa_no' => Procurement::nextNumber('SA', 'sm_adjustments', 'sa_no', $today), 'sa_date' => $today, 'stock_id' => $data['stock_id'],
            'system_qty' => $system, 'physical_qty' => round((float) $data['physical_qty'], 3), 'reason' => $data['reason'], 'remarks' => $data['remarks'] ?? null,
            'status' => 'PENDING', 'requested_by' => $this->caller()->auditUserId(),
        ]);

        return ApiResponse::created(['adjustments' => $this->adjustmentRows(), 'next' => Procurement::previewNumber('SA', 'sm_adjustments', 'sa_no', $today)]);
    }

    /** The Store Manager approves (the difference reaches the books) or rejects a count. */
    public function decideAdjustment(Request $request, string $adjustment): JsonResponse
    {
        $this->allow('storematerial.manage');
        $data = $request->validate(['decision' => ['required', Rule::in(['APPROVED', 'REJECTED'])]]);
        $row = Adjustment::query()->where('sa_no', $adjustment)->firstOrFail();
        if ($row->status !== 'PENDING') {
            throw ValidationException::withMessages(['adjustment' => __('storematerial.sa.decided', ['no' => $row->sa_no])]);
        }
        DB::transaction(function () use ($row, $data): void {
            if ($data['decision'] === 'APPROVED') {
                $stock = Stock::query()->lockForUpdate()->findOrFail($row->stock_id);
                $diff = round((float) $row->physical_qty - (float) $row->system_qty, 3);
                if ($diff < 0 && StoreBook::balance($stock->id) + $diff < -0.0005) {
                    throw ValidationException::withMessages(['adjustment' => __('storematerial.sa.short', ['no' => $row->sa_no])]);
                }
                StoreBook::post($stock, 'ADJUSTMENT', $row->sa_no, $row->id, now()->toDateString(), in: max(0, $diff), out: max(0, -$diff), by: $this->caller()->auditUserId());
            }
            $row->update(['status' => $data['decision'], 'decided_by' => $this->caller()->auditUserId(), 'decided_at' => now()]);
        });

        return ApiResponse::ok(['adjustments' => $this->adjustmentRows(), 'stocks' => $this->stockOptions()]);
    }

    /** Minimum Stock Level: Save. */
    public function saveMinLevel(Request $request): JsonResponse
    {
        $this->allow('storematerial.manage');
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'item_id' => ['required', Rule::exists('mp_items', 'id')->where('company_id', $company)], 'min_qty' => ['required', 'numeric', 'min:0'],
        ], ['item_id.required' => __('storematerial.sa.material_required'), 'min_qty.required' => __('storematerial.sa.min_required')]);
        MinLevel::query()->updateOrCreate(['item_id' => $data['item_id']], ['min_qty' => round((float) $data['min_qty'], 3)]);

        return ApiResponse::ok(['min_levels' => $this->minLevelRows()]);
    }

    /** Every bucket with stock in it, as the pickers list them. @return list<array<string, mixed>> */
    private function stockOptions(): array
    {
        $stocks = Stock::query()->get();
        $balances = StoreBook::balances($stocks->pluck('id'));
        $items = MaterialItem::query()->whereIn('id', $stocks->pluck('item_id'))->get()->keyBy('id');
        $held = $stocks->filter(fn (Stock $s) => ($balances[$s->id] ?? 0) > 0);
        $rolls = StoreBook::rolls($held->pluck('id'));
        $left = StoreBook::rollTotals($rolls->pluck('id'));
        $byStock = $rolls->filter(fn (Roll $r) => ($left[$r->id]['available'] ?? 0) > 0)->groupBy('stock_id');

        return $held->sortBy(fn (Stock $s) => StoreBook::label($s, $items[$s->item_id]))
            ->values()->map(fn (Stock $s) => [
                'value' => $s->id, 'label' => StoreBook::label($s, $items[$s->item_id]), 'kind' => $items[$s->item_id]->kind, 'uom' => $s->uom,
                'available' => $balances[$s->id] ?? 0, 'buyer' => $s->buyer ?: null, 'style' => $s->style ?: null,
                'rolls' => ($byStock[$s->id] ?? collect())->map(fn (Roll $r) => [
                    'id' => $r->id, 'roll_no' => $r->roll_no, 'dye_lot' => $r->dye_lot, 'gsm' => $r->gsm, 'width' => $r->width, 'available' => $left[$r->id]['available'],
                ])->values()->all(),
            ])->all();
    }

    /**
     * The rolls a Material Out takes, checked: each in this bucket and still
     * holding what is asked of it, and together making the quantity going out.
     *
     * @param  array<int, mixed>  $given
     * @return list<array{0: Roll, 1: float}>
     */
    private function takenRolls(Stock $stock, array $given, float $qty): array
    {
        $want = collect($given)->filter(fn ($r) => is_array($r) && (float) ($r['qty'] ?? 0) > 0)->mapWithKeys(fn (array $r) => [(string) $r['roll_id'] => round((float) $r['qty'], 3)]);
        if ($want->isEmpty()) {
            return [];
        }
        $rolls = StoreBook::rolls(collect([$stock->id]))->whereIn('id', $want->keys()->all());
        if ($rolls->count() !== $want->count()) {
            throw ValidationException::withMessages(['rolls' => __('storematerial.roll.not_here')]);
        }
        Roll::query()->whereIn('id', $rolls->pluck('id'))->lockForUpdate()->get();
        $left = StoreBook::rollTotals($rolls->pluck('id'));
        foreach ($rolls as $r) {
            $has = $left[$r->id]['available'] ?? 0;
            if ($want[$r->id] > $has + 0.0005) {
                throw ValidationException::withMessages(['rolls' => __('storematerial.roll.over', ['roll' => $r->roll_no, 'available' => StoreBook::qty($has), 'uom' => $stock->uom])]);
            }
        }
        $total = round($want->sum(), 3);
        if (abs($total - $qty) > 0.0005) {
            throw ValidationException::withMessages(['rolls' => __('storematerial.roll.out_total', ['total' => StoreBook::qty($total), 'qty' => StoreBook::qty($qty), 'uom' => $stock->uom])]);
        }

        return $rolls->map(fn (Roll $r) => [$r, $want[$r->id]])->values()->all();
    }

    /**
     * The rolls a Material Out took, each with what it took, what has come back
     * on it and what still may.
     *
     * @return list<array{roll_id: string, roll_no: string, dye_lot: ?string, out: float, returned: float, eligible: float}>
     */
    private function outRolls(MaterialOut $out): array
    {
        $moves = RollMove::query()->where('source_id', $out->id)->where('type', 'MATERIAL_OUT')->get();
        $rolls = Roll::query()->whereIn('id', $moves->pluck('roll_id'))->get()->keyBy('id');
        $back = MaterialReturn::query()->where('material_out_id', $out->id)->whereNotNull('roll_id')->get()->groupBy('roll_id')->map(fn ($set) => (float) $set->sum('qty'));

        return $moves->groupBy('roll_id')->map(function ($set, string $id) use ($rolls, $back): array {
            $took = round((float) $set->sum('out_qty'), 3);
            $returned = round($back[$id] ?? 0, 3);

            return ['roll_id' => $id, 'roll_no' => $rolls[$id]->roll_no, 'dye_lot' => $rolls[$id]->dye_lot, 'out' => $took, 'returned' => $returned, 'eligible' => round(max(0, $took - $returned), 3)];
        })->sortBy('roll_no')->values()->all();
    }

    private function findOut(string $no): MaterialOut
    {
        return MaterialOut::query()->where('mo_no', strtoupper(trim($no)))->first()
            ?? throw ValidationException::withMessages(['mo_no' => __('storematerial.mr.mo_missing', ['mo' => strtoupper(trim($no))])]);
    }

    private function returnedOf(string $outId): float
    {
        return round((float) MaterialReturn::query()->where('material_out_id', $outId)->sum('qty'), 3);
    }

    /** @return array<string, mixed> */
    private function outPayload(MaterialOut $out): array
    {
        $stock = Stock::query()->findOrFail($out->stock_id);
        $item = MaterialItem::query()->findOrFail($stock->item_id);
        $returned = $this->returnedOf($out->id);

        return [
            'mo_no' => $out->mo_no, 'mo_date' => $out->mo_date->toDateString(), 'material' => StoreBook::label($stock, $item), 'kind' => $item->kind, 'uom' => $stock->uom,
            'purpose' => $out->purpose, 'qty' => (float) $out->qty, 'returned' => $returned, 'eligible' => round(max(0, (float) $out->qty - $returned), 3),
            'floor' => Floor::query()->whereKey($out->floor_id)->value('name'), 'section' => Section::query()->whereKey($out->section_id)->value('name'),
            'line' => ProductionLine::query()->whereKey($out->line_id)->value('name'), 'receiver' => StoreBook::person(HrEmployee::query()->find($out->receiver_id)),
            'rolls' => $this->outRolls($out),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function adjustmentRows(): array
    {
        $rows = Adjustment::query()->orderByDesc('sa_no')->limit(100)->get();
        $stocks = Stock::query()->whereIn('id', $rows->pluck('stock_id'))->get()->keyBy('id');
        $items = MaterialItem::query()->whereIn('id', $stocks->pluck('item_id'))->get()->keyBy('id');
        $names = Procurement::names([...$rows->pluck('requested_by'), ...$rows->pluck('decided_by')]);

        return $rows->map(fn (Adjustment $a) => [
            'sa_no' => $a->sa_no, 'date' => $a->sa_date->toDateString(), 'material' => StoreBook::material($items[$stocks[$a->stock_id]->item_id]),
            'buyer_style' => implode(' / ', array_filter([$stocks[$a->stock_id]->buyer, $stocks[$a->stock_id]->style])) ?: null, 'uom' => $stocks[$a->stock_id]->uom,
            'system' => (float) $a->system_qty, 'physical' => (float) $a->physical_qty, 'difference' => round((float) $a->physical_qty - (float) $a->system_qty, 3),
            'reason' => $a->reason, 'requested_by' => $names[$a->requested_by] ?? null, 'decided_by' => $names[$a->decided_by] ?? null, 'status' => $a->status,
        ])->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function minLevelRows(): array
    {
        $rows = MinLevel::query()->get();
        $items = MaterialItem::query()->whereIn('id', $rows->pluck('item_id'))->get()->keyBy('id');

        return $rows->sortBy(fn ($m) => $items[$m->item_id]->name)->values()->map(fn (MinLevel $m) => ['item_id' => $m->item_id, 'material' => $items[$m->item_id]->name, 'uom' => $items[$m->item_id]->uom, 'min_qty' => (float) $m->min_qty])->all();
    }
}
