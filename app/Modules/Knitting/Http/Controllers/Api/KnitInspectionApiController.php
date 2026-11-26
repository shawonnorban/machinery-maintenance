<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Knitting\Models\KnitCard;
use App\Modules\Knitting\Models\KnitProdOrder;
use App\Modules\Knitting\Models\KnitRoll;
use App\Modules\Knitting\Models\KnitRollInspection;
use App\Modules\Knitting\Models\KnitRollInspectionLine;
use App\Modules\Knitting\Support\KnitProduction;
use App\Modules\Knitting\Support\YarnStore;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Knit Production → Production (AnnotechERP 15/04) → Grey Roll Quality
 * Inspection, as Brickland works it: one four-point sheet per grey roll
 * (QCI-…) — each defect's score copied onto its line, Total Point their
 * sum, Grade Point the points per 100 kg of what is left of the roll after
 * the inspector's own deduction, and the grade they come to unless one is
 * set by hand. A clean roll is a grade A sheet. Pass, Hold and Reject on the
 * register are decisions about the roll and write the roll itself.
 */
class KnitInspectionApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('knitting.production.view');
        $o = KnitProduction::options();
        $rolls = KnitRoll::query()->orderByDesc('roll_at')->orderByDesc('roll_no')->limit(500)->get();

        return ApiResponse::ok(collect($o)->only(['machines', 'shifts', 'employees', 'defects', 'grades'])->all() + [
            'statuses' => KnitRollInspection::STATUSES,
            'rolls' => $this->rollDetails($rolls),
            'sheets' => KnitRollInspection::query()->orderByDesc('qci_no')->limit(300)->get()->map(fn (KnitRollInspection $i) => [
                'value' => $i->qci_no, 'label' => $i->qci_no.' · '.KnitRoll::query()->whereKey($i->roll_id)->value('roll_code').' — '.$i->inspected_on->format('d M Y').' · '.$i->grade.' · '.__("knitting.prod.qc_status.{$i->status}"),
            ])->values()->all(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->allow('knitting.production.view');
        $f = $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'status' => ['nullable', Rule::in(KnitRollInspection::STATUSES)],
            'grade' => ['nullable', Rule::in(KnitProduction::GRADES)], 'machine' => ['nullable', 'string'],
        ]);
        $rows = KnitRollInspection::query()
            ->when(! empty($f['from']), fn ($q) => $q->whereDate('inspected_on', '>=', $f['from']))
            ->when(! empty($f['to']), fn ($q) => $q->whereDate('inspected_on', '<=', $f['to']))
            ->when(! empty($f['status']), fn ($q) => $q->where('status', $f['status']))
            ->when(! empty($f['grade']), fn ($q) => $q->where('grade', $f['grade']))
            ->when(! empty($f['machine']), fn ($q) => $q->whereIn('roll_id', KnitRoll::query()->where('machine_id', $f['machine'])->select('id')))
            ->orderByDesc('inspected_on')->orderByDesc('qci_no')->limit(300)->get();

        return ApiResponse::ok($this->presentRegister($rows));
    }

    public function show(string $qci): JsonResponse
    {
        $this->allow('knitting.production.view');

        return ApiResponse::ok($this->present($this->find($qci)));
    }

    public function save(Request $request, ?string $qci = null): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $row = $qci ? $this->find($qci) : null;
        if ($row && $row->status !== 'DRAFT') {
            throw ValidationException::withMessages(['status' => __('knitting.prod.v.qc_decided', ['no' => $row->qci_no, 'status' => __("knitting.prod.qc_status.{$row->status}")])]);
        }
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'roll_code' => ['required', 'string'], 'inspected_on' => ['required', 'date'], 'shift_id' => ['nullable', Rule::exists('hr_shifts', 'id')->where('company_id', $company)],
            'qc_by' => ['nullable', Rule::exists('hr_employees', 'id')->where('company_id', $company)], 'deduct_weight' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'grade_override' => ['nullable', Rule::in(KnitProduction::GRADES)], 'comments' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:100'], 'lines.*.defect' => ['required', Rule::in(array_keys(KnitProduction::DEFECTS))],
            'lines.*.count' => ['required', 'integer', 'min:1', 'max:99999'], 'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'roll_code.required' => __('knitting.prod.v.qc_roll'), 'inspected_on.required' => __('knitting.prod.v.qc_date'),
            'lines.*.count.required' => __('knitting.prod.v.qc_count'), 'lines.*.count.min' => __('knitting.prod.v.qc_count'),
        ]);
        $roll = KnitRoll::query()->where('roll_code', $data['roll_code'])->first();
        if (! $roll) {
            throw ValidationException::withMessages(['roll_code' => __('knitting.prod.v.qc_roll')]);
        }
        $other = KnitRollInspection::query()->where('roll_id', $roll->id)->when($row, fn ($q) => $q->whereKeyNot($row->id))->value('qci_no');
        if ($other) {
            throw ValidationException::withMessages(['roll_code' => __('knitting.prod.v.qc_twice', ['roll' => $roll->roll_code, 'no' => $other])]);
        }
        $net = (float) $roll->weight - (float) $roll->deducted - (float) ($data['deduct_weight'] ?? 0);
        if ($net <= 0) {
            throw ValidationException::withMessages(['deduct_weight' => __('knitting.prod.v.qc_deduct')]);
        }
        $lines = YarnStore::inOrder($data['lines']);
        $points = array_sum(array_map(fn ($l) => KnitProduction::DEFECTS[$l['defect']][0] * (int) $l['count'], $lines));
        $gradePoint = round($points * 100 / $net, 2);
        $row = DB::transaction(function () use ($row, $data, $roll, $lines, $points, $gradePoint): KnitRollInspection {
            $head = [
                'roll_id' => $roll->id, 'inspected_on' => $data['inspected_on'], 'shift_id' => $data['shift_id'] ?? null, 'qc_by' => $data['qc_by'] ?? null,
                'deduct_weight' => (float) ($data['deduct_weight'] ?? 0), 'grade_override' => $data['grade_override'] ?? null, 'comments' => $data['comments'] ?? null,
                'total_points' => $points, 'grade_point' => $gradePoint, 'grade' => $data['grade_override'] ?? KnitProduction::gradeFor($gradePoint),
            ];
            $row ??= KnitRollInspection::query()->create($head + [
                'qci_no' => KnitProduction::nextNumber('QCI', 'knit_roll_inspections', 'qci_no', $data['inspected_on']), 'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId(),
            ]);
            $row->update($head);
            KnitRollInspectionLine::query()->where('inspection_id', $row->id)->delete();
            foreach ($lines as $i => $l) {
                KnitRollInspectionLine::query()->create([
                    'company_id' => $row->company_id, 'inspection_id' => $row->id, 'sort_order' => $i + 1, 'defect' => $l['defect'],
                    'points_each' => KnitProduction::DEFECTS[$l['defect']][0], 'count' => (int) $l['count'], 'remarks' => $l['remarks'] ?? null,
                ]);
            }

            return $row;
        });

        return $qci ? ApiResponse::ok($this->present($row->refresh())) : ApiResponse::created($this->present($row));
    }

    /** Pass, Hold or Reject: a decision about the roll, written onto the roll itself. */
    public function decide(string $qci, string $action): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $row = $this->find($qci);
        $to = match ($action) {
            'pass' => 'PASSED',
            'hold' => 'HOLD',
            'reject' => 'REJECTED',
            default => abort(404),
        };
        if ($row->status === $to || ($row->status !== 'DRAFT' && $row->status !== 'HOLD')) {
            throw ValidationException::withMessages(['status' => __('knitting.prod.v.qc_decided', ['no' => $row->qci_no, 'status' => __("knitting.prod.qc_status.{$row->status}")])]);
        }
        DB::transaction(function () use ($row, $to): void {
            $row->update(['status' => $to]);
            KnitRoll::query()->whereKey($row->roll_id)->update(['quality' => $to]);
        });

        return ApiResponse::ok($this->presentRegister(collect([$row->refresh()]))[0]);
    }

    /* ---------------- helpers ---------------- */

    private function find(string $qci): KnitRollInspection
    {
        return KnitRollInspection::query()->where('qci_no', $qci)->firstOrFail();
    }

    /**
     * Rolls as the sheet's Roll No picker and Roll List show them, with the
     * details the sheet fills in.
     *
     * @return list<array<string, mixed>>
     */
    private function rollDetails(Collection $rolls): array
    {
        $cards = KnitCard::query()->whereIn('id', $rolls->pluck('card_id'))->get()->keyBy('id');
        $orders = KnitProdOrder::query()->whereIn('id', $cards->pluck('prod_order_id'))->get()->keyBy('id');
        $merch = Order::query()->whereIn('id', $orders->pluck('order_id'))->get()->keyBy('id');
        $buyers = Buyer::query()->whereIn('id', $orders->pluck('buyer_id')->filter())->get()->mapWithKeys(fn (Buyer $b) => [$b->id => $b->short_name ?: $b->name]);
        $machines = DB::table('knit_machines')->whereIn('id', $rolls->pluck('machine_id')->filter())->pluck('machine_no', 'id');
        $people = DB::table('hr_employees')->whereIn('id', $rolls->pluck('operator_id')->filter())->pluck('name', 'id');
        $shifts = DB::table('hr_shifts')->whereIn('id', $rolls->pluck('shift_id')->filter())->pluck('name', 'id');

        return $rolls->map(function (KnitRoll $r) use ($cards, $orders, $merch, $buyers, $machines, $people, $shifts) {
            $c = $cards[$r->card_id];
            $po = $orders[$c->prod_order_id];
            $o = $merch[$po->order_id] ?? null;
            $net = round((float) $r->weight - (float) $r->deducted, 3);

            return [
                'value' => $r->roll_code, 'label' => $r->roll_code.' · '.number_format($net, 3).' Kg', 'weight' => (float) $r->weight, 'net' => $net,
                'kc_no' => $c->kc_no, 'roll_no' => $r->roll_no, 'operator' => $people[$r->operator_id] ?? null, 'buyer' => $buyers[$po->buyer_id] ?? null,
                'style' => $o?->work_style_no ?: $o?->style_no, 'order_no' => $o?->order_no, 'fabric' => $po->fabric_desc, 'colour' => $c->colour_name,
                'finish_dia' => $c->finish_dia ?: $po->finish_dia, 'machine' => $machines[$r->machine_id] ?? null, 'machine_id' => $r->machine_id,
                'dia_gauge' => ($c->machine_dia ?: '—').' x '.($c->gauge ?: '—'), 'gsm' => $po->gsm, 'yarn_details' => $c->yarn_details, 'shift' => $shifts[$r->shift_id] ?? null,
            ];
        })->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function presentRegister(Collection $rows): array
    {
        $rolls = KnitRoll::query()->whereIn('id', $rows->pluck('roll_id'))->get()->keyBy('id');
        $machines = DB::table('knit_machines')->whereIn('id', $rolls->pluck('machine_id')->filter())->pluck('machine_no', 'id');
        $shifts = DB::table('hr_shifts')->whereIn('id', $rows->pluck('shift_id')->filter())->pluck('name', 'id');
        $people = DB::table('hr_employees')->whereIn('id', $rows->pluck('qc_by')->filter())->pluck('name', 'id');
        $defects = KnitRollInspectionLine::query()->whereIn('inspection_id', $rows->pluck('id'))->groupBy('inspection_id')->selectRaw('inspection_id, count(*) as n')->pluck('n', 'inspection_id');

        return $rows->map(function (KnitRollInspection $i) use ($rolls, $machines, $shifts, $people, $defects) {
            $r = $rolls[$i->roll_id];

            return [
                'qci_no' => $i->qci_no, 'roll_code' => $r->roll_code, 'inspected_on' => $i->inspected_on->toDateString(), 'shift' => $shifts[$i->shift_id] ?? null,
                'qc_by' => $people[$i->qc_by] ?? null, 'machine' => $machines[$r->machine_id] ?? null, 'weight' => (float) $r->weight, 'deduct' => (float) $i->deduct_weight,
                'net' => round((float) $r->weight - (float) $r->deducted - (float) $i->deduct_weight, 3), 'defects' => (int) ($defects[$i->id] ?? 0),
                'total_points' => $i->total_points, 'grade_point' => (float) $i->grade_point, 'grade' => $i->grade, 'status' => $i->status,
            ];
        })->values()->all();
    }

    /** @return array<string, mixed> */
    private function present(KnitRollInspection $row): array
    {
        $roll = KnitRoll::query()->findOrFail($row->roll_id);

        return [
            'qci_no' => $row->qci_no, 'roll_code' => $roll->roll_code, 'roll' => $this->rollDetails(collect([$roll]))[0], 'inspected_on' => $row->inspected_on->toDateString(),
            'shift_id' => $row->shift_id, 'qc_by' => $row->qc_by, 'deduct_weight' => (float) $row->deduct_weight, 'grade_override' => $row->grade_override,
            'total_points' => $row->total_points, 'grade_point' => (float) $row->grade_point, 'grade' => $row->grade, 'comments' => $row->comments, 'status' => $row->status,
            'editable' => $row->status === 'DRAFT', 'register' => $this->presentRegister(collect([$row]))[0],
            'lines' => KnitRollInspectionLine::query()->where('inspection_id', $row->id)->orderBy('sort_order')->get()->map(fn ($l) => [
                'defect' => $l->defect, 'points_each' => $l->points_each, 'count' => $l->count, 'remarks' => $l->remarks,
                'group' => KnitProduction::DEFECTS[$l->defect][1] ?? null,
            ])->values()->all(),
        ];
    }
}
