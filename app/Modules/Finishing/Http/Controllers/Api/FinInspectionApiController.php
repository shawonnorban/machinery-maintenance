<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Http\Controllers\Api;

use App\Modules\DyeHouse\Support\DyeBatches;
use App\Modules\Finishing\Models\FinBatchReceipt;
use App\Modules\Finishing\Models\FinInspection;
use App\Modules\Finishing\Models\FinInspectionRoll;
use App\Modules\Finishing\Models\FinProcessType;
use App\Modules\Finishing\Models\FinProductionSheet;
use App\Modules\Finishing\Models\FinReprocessAdvice;
use App\Modules\Finishing\Support\FinFloor;
use App\Modules\Finishing\Support\FinishingDefaults;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Finishing Line → QC Process → Four-Point Fabric Inspection (AnnotechERP
 * 18/04), as Brickland keeps it: load a batch on the floor, take the lab
 * readings from its cut sample, weigh and grade its rolls (each roll number
 * once on the sheet). Saving keeps the sheet a draft and recounts its three
 * totals — rolls, kilos, points; posting it from the register records the
 * verdict against the batch: a pass sends it on to the store (Ready for
 * Delivery), a hold or a reject stops it on the floor board (On Hold).
 * Also what the QC lists pick from.
 */
class FinInspectionApiController extends ApiController
{
    /** The verdict's stage on the floor board. */
    private const STAGE_OF = ['PASS' => 'READY_FOR_DELIVERY', 'HOLD' => 'ON_HOLD', 'REJECT' => 'ON_HOLD'];

    public function options(): JsonResponse
    {
        $this->allow('finishing.qc.view');
        $c = $this->caller()->companyId;
        FinishingDefaults::ensure($c);
        $cards = FinBatchReceipt::query()->where('status', 'ON_FLOOR')->whereNotIn('stage', FinFloor::DESK_STAGES)->orderBy('receipt_no')->get();
        $batches = FinFloor::batches($cards->pluck('batch_id')->all());
        $advised = FinReprocessAdvice::query()->pluck('inspection_id')->all();
        $rejected = FinInspection::query()->where('status', 'POSTED')->whereNotIn('id', $advised)
            ->where(fn ($q) => $q->where('verdict', 'REJECT')->orWhereIn('id', FinInspectionRoll::query()->where('result', 'REJECT')->select('inspection_id')))->orderByDesc('insp_date')->get();
        $rejBatches = FinFloor::batches(FinBatchReceipt::query()->whereIn('id', $rejected->pluck('receipt_id'))->pluck('batch_id', 'id')->values()->all());
        $rejCards = FinBatchReceipt::query()->whereIn('id', $rejected->pluck('receipt_id'))->pluck('batch_id', 'id');

        return ApiResponse::ok([
            'cards' => $cards->map(function ($card) use ($batches) {
                $b = $batches[$card->batch_id] ?? null;

                return ['value' => $card->id, 'label' => trim(($b->batch_no ?? '—').' · '.($b->colour ?? '—').' · '.$card->receipt_no), 'batch_no' => $b?->batch_no, 'buyer' => $b?->buyer, 'order_no' => $b?->order_no, 'style' => $b?->style,
                    'colour' => $b?->colour, 'lot_no' => $b?->lot_no, 'qty' => (float) $card->recv_qty_kg, 'rolls' => $card->recv_rolls, 'stage' => $card->stage, 'dia_type' => $card->dia_type];
            })->values()->all(),
            'users' => DB::table('company_users')->join('users', 'users.id', '=', 'company_users.user_id')->where('company_users.company_id', $c)->where('company_users.status', 'ACTIVE')
                ->orderBy('users.name')->get(['users.id', 'users.name'])->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])->values()->all(),
            'buyers' => DB::table('buyers')->where('company_id', $c)->orderBy('name')->get(['id', 'short_name', 'name'])->map(fn ($b) => ['value' => $b->id, 'label' => $b->short_name ?: $b->name])->all(),
            'orders' => DB::table('merch_orders')->where('company_id', $c)->orderByDesc('order_no')->get(['id', 'order_no', 'style_no', 'work_style_no'])->map(fn ($o) => ['value' => $o->id, 'label' => "{$o->order_no} [".($o->work_style_no ?: $o->style_no).']'])->all(),
            'machines' => DB::table('fin_machines')->where('company_id', $c)->where('active', true)->orderBy('machine_no')->get(['id', 'machine_no', 'name'])->map(fn ($m) => ['value' => $m->id, 'label' => "{$m->machine_no} — {$m->name}"])->all(),
            'processes' => FinProcessType::query()->where('active', true)->whereIn('group_id', DB::table('fin_process_groups')->where('company_id', $c)->where('code', 'DYEING_FINISHING')->select('id'))
                ->orderBy('display_order')->get(['id', 'name', 'short_name'])->map(fn ($p) => ['value' => $p->id, 'label' => "{$p->name} ({$p->short_name})"])->all(),
            'rejected' => $rejected->map(fn ($i) => ['value' => $i->id, 'label' => "{$i->ref_no} · ".($rejBatches[$rejCards[$i->receipt_id] ?? '']->batch_no ?? '—').' · '.__("finishing.qc.opt.verdict.{$i->verdict}")])->values()->all(),
        ]);
    }

    public function index(): JsonResponse
    {
        $this->allow('finishing.qc.view');
        $rows = FinInspection::query()->orderByDesc('insp_date')->orderByDesc('ref_no')->get();
        $cards = FinBatchReceipt::query()->whereIn('id', $rows->pluck('receipt_id'))->get()->keyBy('id');
        $batches = FinFloor::batches($cards->pluck('batch_id')->all());
        $rolls = FinInspectionRoll::query()->whereIn('inspection_id', $rows->pluck('id'))->get(['inspection_id', 'roll_no'])->groupBy('inspection_id');

        return ApiResponse::ok($rows->map(function (FinInspection $i) use ($cards, $batches, $rolls) {
            $card = $cards[$i->receipt_id] ?? null;
            $b = $card ? ($batches[$card->batch_id] ?? null) : null;

            return ['id' => $i->id, 'ref_no' => $i->ref_no, 'insp_date' => $i->insp_date->toDateString(), 'receipt_no' => $card?->receipt_no, 'batch_no' => $b?->batch_no, 'buyer' => $b?->buyer, 'colour' => $b?->colour,
                'shift' => $i->shift, 'roll_count' => $i->roll_count, 'total_kg' => round((float) $i->total_kg, 3), 'total_points' => $i->total_points, 'verdict' => $i->verdict, 'status' => $i->status,
                'rolls' => ($rolls[$i->id] ?? collect())->pluck('roll_no')->implode(' ')];
        })->all());
    }

    public function show(string $ref): JsonResponse
    {
        $this->allow('finishing.qc.view');

        return ApiResponse::ok($this->present(FinInspection::query()->where('ref_no', $ref)->firstOrFail()));
    }

    public function save(Request $request, ?string $ref = null): JsonResponse
    {
        $this->allow('finishing.qc.manage');
        $insp = $ref ? $this->draft($ref) : null;
        $data = $this->validated($request, $insp);
        $insp = DB::transaction(function () use ($insp, $data) {
            $rolls = $data['rolls'];
            $head = array_diff_key($data, ['rolls' => 1, 'ref_no' => 1]) + [
                'roll_count' => count($rolls), 'total_kg' => round(array_sum(array_map(fn ($r) => (float) $r['weight_kg'], $rolls)), 3), 'total_points' => array_sum(array_map(fn ($r) => (int) ($r['points'] ?? 0), $rolls)),
            ];
            if ($insp) {
                $insp->update($head + (($data['ref_no'] ?? null) ? ['ref_no' => $data['ref_no']] : []));
                $insp->rolls()->delete();
            } else {
                $insp = FinInspection::query()->create($head + ['ref_no' => ($data['ref_no'] ?? null) ?: DyeBatches::nextNumber('FFI-', 'fin_inspections', 'ref_no', $data['insp_date'], 'Ym'), 'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            }
            foreach ($rolls as $i => $r) {
                FinInspectionRoll::query()->create(['inspection_id' => $insp->id, 'sort_order' => $i + 1, 'points' => (int) ($r['points'] ?? 0)] + $r);
            }

            return $insp;
        });

        return $ref ? ApiResponse::ok($this->present($insp->refresh())) : ApiResponse::created($this->present($insp->refresh()));
    }

    /** Post a draft: the verdict is recorded against the batch and moves it on the floor board. */
    public function post(string $ref): JsonResponse
    {
        $this->allow('finishing.qc.manage');
        $insp = $this->draft($ref);
        DB::transaction(function () use ($insp): void {
            $card = FinBatchReceipt::query()->lockForUpdate()->findOrFail($insp->receipt_id);
            if ($card->status !== 'ON_FLOOR' || in_array($card->stage, FinFloor::DESK_STAGES, true)) {
                throw ValidationException::withMessages(['ref_no' => __('finishing.qc.v.card_off_floor', ['batch' => FinFloor::batches([$card->batch_id])[$card->batch_id]->batch_no ?? ''])]);
            }
            $to = self::STAGE_OF[$insp->verdict];
            if ($card->stage !== $to) {
                FinFloor::move($card, $to, 'QC_INSP', now(), null, $insp->ref_no, $this->caller()->auditUserId());
            }
            $insp->update(['status' => 'POSTED', 'posted_at' => now(), 'posted_by' => $this->caller()->auditUserId()]);
        });

        return ApiResponse::ok($this->present($insp->refresh()));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?FinInspection $insp): array
    {
        $c = $this->caller()->companyId;
        $member = Rule::exists('company_users', 'user_id')->where('company_id', $c);
        $data = $request->validate([
            'ref_no' => ['nullable', 'string', 'max:40'],
            'receipt_id' => ['required', Rule::exists('fin_batch_receipts', 'id')->where('company_id', $c)],
            'insp_date' => ['required', 'date'], 'shift' => ['required', Rule::in(FinProductionSheet::SHIFTS)],
            'actual_dia' => ['nullable', 'numeric', 'min:0', 'max:9999'], 'shrinkage_l' => ['nullable', 'numeric', 'min:-100', 'max:100'], 'shrinkage_w' => ['nullable', 'numeric', 'min:-100', 'max:100'], 'spirality' => ['nullable', 'numeric', 'min:-100', 'max:100'],
            'inspector_id' => ['nullable', $member], 'shift_incharge_id' => ['nullable', $member], 'qc_incharge_id' => ['nullable', $member],
            'verdict' => ['required', Rule::in(FinInspection::VERDICTS)], 'shade_note' => ['nullable', 'string', 'max:2000'], 'qc_comment' => ['nullable', 'string', 'max:2000'],
            'rolls' => ['required', 'array', 'min:1', 'max:500'],
            'rolls.*.roll_no' => ['required', 'string', 'max:40'], 'rolls.*.weight_kg' => ['required', 'numeric', 'gt:0', 'max:999999'], 'rolls.*.points' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'rolls.*.grade' => ['nullable', 'string', 'max:8'], 'rolls.*.result' => ['required', Rule::in(FinInspection::VERDICTS)], 'rolls.*.defect' => ['nullable', 'string', 'max:255'],
        ], [
            'receipt_id.required' => __('finishing.qc.v.batch_no'), 'insp_date.required' => __('finishing.qc.v.insp_date'), 'shift.required' => __('finishing.qc.v.shift'), 'verdict.required' => __('finishing.qc.v.verdict'),
            'rolls.required' => __('finishing.qc.v.no_rolls'), 'rolls.min' => __('finishing.qc.v.no_rolls'),
        ]);
        ksort($data['rolls']);
        $data['rolls'] = array_values($data['rolls']);
        if ($data['ref_no'] ?? null) {
            $data['ref_no'] = mb_strtoupper(trim($data['ref_no']));
            if (FinInspection::query()->where('ref_no', $data['ref_no'])->when($insp, fn ($q) => $q->whereKeyNot($insp->id))->exists()) {
                throw ValidationException::withMessages(['ref_no' => __('finishing.qc.v.ref_twice', ['no' => $data['ref_no']])]);
            }
        }
        $card = FinBatchReceipt::query()->findOrFail($data['receipt_id']);
        if ($card->status !== 'ON_FLOOR' || in_array($card->stage, FinFloor::DESK_STAGES, true)) {
            throw ValidationException::withMessages(['receipt_id' => __('finishing.qc.v.card_off_floor', ['batch' => FinFloor::batches([$card->batch_id])[$card->batch_id]->batch_no ?? ''])]);
        }
        if (substr((string) $data['insp_date'], 0, 10) < $card->receive_date->toDateString()) {
            throw ValidationException::withMessages(['insp_date' => __('finishing.qc.v.before_receipt', ['no' => $card->receipt_no])]);
        }
        $seen = [];
        $errors = [];
        foreach ($data['rolls'] as $i => &$r) {
            $r['roll_no'] = mb_strtoupper(trim($r['roll_no']));
            $r['grade'] = ($r['grade'] ?? null) ? mb_strtoupper(trim($r['grade'])) : null;
            if (isset($seen[$r['roll_no']])) {
                $errors["rolls.{$i}.roll_no"] = __('finishing.qc.v.roll_on_sheet', ['n' => $i + 1, 'roll' => $r['roll_no']]);
            }
            $seen[$r['roll_no']] = true;
        }
        unset($r);
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }

    private function draft(string $ref): FinInspection
    {
        $insp = FinInspection::query()->where('ref_no', $ref)->firstOrFail();
        if ($insp->status !== 'DRAFT') {
            throw ValidationException::withMessages(['ref_no' => __('finishing.qc.v.inspection_posted', ['no' => $insp->ref_no])]);
        }

        return $insp;
    }

    /** @return array<string, mixed> */
    private function present(FinInspection $i): array
    {
        $card = FinBatchReceipt::query()->find($i->receipt_id);
        $b = $card ? (FinFloor::batches([$card->batch_id])[$card->batch_id] ?? null) : null;
        $num = fn ($v) => $v === null ? null : (float) $v;

        return [
            'id' => $i->id, 'ref_no' => $i->ref_no, 'receipt_id' => $i->receipt_id, 'batch' => $card ? trim(($b->batch_no ?? '—').' · '.($b->colour ?? '—').' · '.$card->receipt_no) : null,
            'buyer' => $b?->buyer, 'style' => $b?->style, 'colour' => $b?->colour, 'lot_no' => $b?->lot_no, 'order_no' => $b?->order_no, 'weights' => $card ? round((float) $card->recv_qty_kg, 3).' kg / '.$card->recv_rolls : null,
            'insp_date' => $i->insp_date->toDateString(), 'shift' => $i->shift, 'actual_dia' => $num($i->actual_dia), 'shrinkage_l' => $num($i->shrinkage_l), 'shrinkage_w' => $num($i->shrinkage_w), 'spirality' => $num($i->spirality),
            'inspector_id' => $i->inspector_id, 'shift_incharge_id' => $i->shift_incharge_id, 'qc_incharge_id' => $i->qc_incharge_id, 'verdict' => $i->verdict, 'shade_note' => $i->shade_note, 'qc_comment' => $i->qc_comment,
            'roll_count' => $i->roll_count, 'total_kg' => round((float) $i->total_kg, 3), 'total_points' => $i->total_points, 'status' => $i->status,
            'rolls' => $i->rolls()->get()->map(fn (FinInspectionRoll $r) => ['id' => $r->id, 'roll_no' => $r->roll_no, 'weight_kg' => round((float) $r->weight_kg, 3), 'points' => $r->points, 'grade' => $r->grade, 'result' => $r->result, 'defect' => $r->defect])->values()->all(),
        ];
    }
}
