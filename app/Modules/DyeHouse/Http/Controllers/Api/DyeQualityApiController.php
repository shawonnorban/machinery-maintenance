<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Http\Controllers\Api;

use App\Modules\DyeHouse\Models\DyeBatch;
use App\Modules\DyeHouse\Models\DyeBatchCompletion;
use App\Modules\DyeHouse\Models\DyeCheckRollInspection;
use App\Modules\DyeHouse\Models\DyeChemRequisition;
use App\Modules\DyeHouse\Models\DyeProduction;
use App\Modules\DyeHouse\Models\DyeShadeApproval;
use App\Modules\DyeHouse\Support\ChemStore;
use App\Modules\DyeHouse\Support\DyeBatches;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Dye House → Dyeing Q.C (AnnotechERP 17/08): Check Roll Quality Inspection
 * (a roll of a dyed batch received from dyeing — nothing ticked passes it,
 * anything ticked holds it against the department answerable; addressed by
 * its number, CRI-YYYYMM####) and Batch Programme Completion (a batch
 * programme requisition marked finished once every QC gate of its batch has
 * passed: produced, its check rolls passed unless the run by-passed check
 * roll, and no rib or collar shade still pending, on hold or rejected).
 */
class DyeQualityApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('dyehouse.qc.view');
        $c = DyeBatches::company();
        $colours = DB::table('colours')->where('company_id', $c)->pluck('name', 'id');

        return ApiResponse::ok([
            // Cards that have been on a machine, newest first: what the check roll desk receives.
            'batches' => DyeBatch::query()->whereIn('id', DyeProduction::query()->select('batch_id'))->where('status', 'OPEN')->orderByDesc('batch_date')->orderByDesc('batch_no')
                ->get(['id', 'batch_no', 'lot_no', 'colour_id', 'card_qty'])->map(fn ($b) => ['value' => $b->id, 'label' => $b->batch_no, 'batch_no' => $b->batch_no, 'lot_no' => $b->lot_no,
                    'colour' => $colours[$b->colour_id] ?? null, 'qty' => (float) $b->card_qty])->values()->all(),
            'departments' => DB::table('departments')->where('company_id', $c)->orderBy('name')->get(['id', 'name'])->map(fn ($d) => ['value' => $d->id, 'label' => $d->name])->values()->all(),
            'defects' => DyeCheckRollInspection::DEFECTS,
            'buyers' => DB::table('buyers')->where('company_id', $c)->orderBy('name')->get(['id', 'name', 'short_name', 'code'])
                ->map(fn ($b) => ['value' => $b->id, 'label' => ($b->short_name ?: $b->name).($b->code ? " ({$b->code})" : '')])->values()->all(),
            'orders' => DyeBatches::orders(),
            'colours' => DB::table('colours')->where('company_id', $c)->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])
                ->map(fn ($x) => ['value' => $x->id, 'label' => $x->code ? "{$x->name} ({$x->code})" : $x->name])->values()->all(),
        ]);
    }

    /* ── Check Roll Quality Inspection ───────────────────────────────── */

    public function inspections(): JsonResponse
    {
        $this->allow('dyehouse.qc.view');

        return ApiResponse::ok($this->inspectionRows(DyeCheckRollInspection::query()->orderByDesc('inspection_date')->orderByDesc('inspection_no')->get()));
    }

    /** A dyed card's details as the check roll desk reads them off it — and off its latest production — and the rolls already inspected. */
    public function sheet(string $batchNo): JsonResponse
    {
        $this->allow('dyehouse.qc.view');
        $b = DyeBatch::query()->where('batch_no', DyeBatches::batchNo($batchNo))->firstOrFail();
        $n = DyeBatches::names();
        $p = DyeProduction::query()->where('batch_id', $b->id)->orderByDesc('production_date')->first();

        return ApiResponse::ok([
            'batch_id' => $b->id, 'batch_no' => $b->batch_no, 'lot_no' => $b->lot_no, 'colour' => $n['colours'][$b->colour_id] ?? null, 'colour_group' => $n['groups'][$b->colour_group_id] ?? null,
            'buyer' => $n['buyers'][$b->buyer_id] ?? null, 'style' => $b->style_ref, 'order_no' => $b->order_no, 'dyeing_method' => $b->dyeing_method,
            'weight_kg' => $p ? (float) ($p->weight_kg ?? $b->card_qty) : (float) $b->card_qty, 'machine' => $p ? ($n['machines'][$p->dye_machine_id] ?? null) : null,
            'model_combo' => $p?->model_combo, 'lab_dip_ref' => $p?->lab_dip_ref, 'liquor_ratio' => $p?->liquor_ratio, 'bypass_check_roll' => (bool) $p?->bypass_check_roll,
            'inspections' => $this->inspectionRows(DyeCheckRollInspection::query()->where('batch_id', $b->id)->orderBy('roll_no')->get()),
        ]);
    }

    /**
     * Receive a roll: new, or the inspection of `no` rewritten. A roll of a
     * batch is inspected once; anything ticked holds it and names the
     * department answerable.
     */
    public function save(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('dyehouse.qc.manage');
        $row = $no ? DyeCheckRollInspection::query()->where('inspection_no', $no)->firstOrFail() : null;
        $c = DyeBatches::company();
        $data = $request->validate([
            'inspection_date' => ['required', 'date'],
            'batch_id' => [$row ? 'prohibited' : 'required', Rule::exists('dye_batches', 'id')->where('company_id', $c)],
            'roll_no' => ['required', 'string', 'max:40'],
            'defects' => ['nullable', 'array', 'max:30'],
            'defects.*' => [Rule::in(DyeCheckRollInspection::DEFECTS)],
            'department_id' => ['nullable', 'required_with:defects.0', Rule::exists('departments', 'id')->where('company_id', $c)],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ], [
            'inspection_date.required' => __('dyehouse.qc.v.date'), 'batch_id.required' => __('dyehouse.qc.v.batch'), 'roll_no.required' => __('dyehouse.qc.v.roll'),
            'department_id.required_with' => __('dyehouse.qc.v.department'),
        ]);
        $batchId = $row?->batch_id ?? $data['batch_id'];
        $batch = DyeBatch::query()->findOrFail($batchId);
        if (! $row && ! DyeProduction::query()->where('batch_id', $batchId)->exists()) {
            throw ValidationException::withMessages(['batch_id' => __('dyehouse.qc.v.not_dyed', ['no' => $batch->batch_no])]);
        }
        $roll = mb_strtoupper(trim($data['roll_no']));
        if (DyeCheckRollInspection::query()->where('batch_id', $batchId)->where('roll_no', $roll)->when($row, fn ($q) => $q->whereKeyNot($row->id))->exists()) {
            throw ValidationException::withMessages(['roll_no' => __('dyehouse.qc.v.roll_twice', ['roll' => $roll, 'no' => $batch->batch_no])]);
        }
        $defects = array_values(array_intersect(DyeCheckRollInspection::DEFECTS, array_unique($data['defects'] ?? [])));
        $values = ['inspection_date' => $data['inspection_date'], 'roll_no' => $roll, 'defects' => $defects, 'department_id' => $defects ? ($data['department_id'] ?? null) : null,
            'result' => $defects ? 'HELD' : 'PASSED', 'remarks' => ($data['remarks'] ?? '') ?: null];
        $row = DB::transaction(function () use ($row, $values, $batchId, $data): DyeCheckRollInspection {
            if ($row) {
                $row->update($values);

                return $row;
            }

            return DyeCheckRollInspection::query()->create($values + ['batch_id' => $batchId, 'created_by' => $this->caller()->auditUserId(),
                'inspection_no' => DyeBatches::nextNumber('CRI-', 'dye_check_roll_inspections', 'inspection_no', $data['inspection_date'], 'Ym')]);
        });

        return ApiResponse::ok($this->inspectionRows(collect([$row->refresh()]))[0], [], $no ? 200 : 201);
    }

    /**
     * @param  Collection<int, DyeCheckRollInspection>  $rows
     * @return list<array<string, mixed>>
     */
    private function inspectionRows(Collection $rows): array
    {
        $batches = DyeBatch::query()->whereIn('id', $rows->pluck('batch_id'))->get(['id', 'batch_no', 'lot_no', 'colour_id'])->keyBy('id');
        $depts = DB::table('departments')->whereIn('id', $rows->pluck('department_id')->filter())->pluck('name', 'id');
        $colours = DyeBatches::names()['colours'];

        return $rows->map(fn (DyeCheckRollInspection $r) => [
            'inspection_no' => $r->inspection_no, 'inspection_date' => $r->inspection_date->toDateString(), 'batch_id' => $r->batch_id, 'batch_no' => $batches[$r->batch_id]->batch_no ?? null,
            'lot_no' => $batches[$r->batch_id]->lot_no ?? null, 'colour' => $colours[$batches[$r->batch_id]->colour_id ?? ''] ?? null, 'roll_no' => $r->roll_no, 'defects' => $r->defects ?? [],
            'defect_count' => count($r->defects ?? []), 'department_id' => $r->department_id, 'department' => $depts[$r->department_id] ?? null, 'result' => $r->result, 'remarks' => $r->remarks,
        ])->values()->all();
    }

    /* ── Batch Programme Completion ──────────────────────────────────── */

    /**
     * Every batch programme and re-process requisition with its batch, where
     * it has got to (completed or not, each QC gate) and whether a roll of the
     * batch was sent back from dyeing and finishing.
     */
    public function completions(): JsonResponse
    {
        $this->allow('dyehouse.qc.view');
        $n = DyeBatches::names();
        $reqs = DyeChemRequisition::query()->whereIn('req_for', ['BATCH_PROGRAM', 'REPROCESS'])->where('status', '!=', 'CANCELLED')->orderByDesc('req_date')->orderByDesc('req_no')->get();
        $done = DyeBatchCompletion::query()->whereIn('requisition_id', $reqs->pluck('id'))->get()->keyBy('requisition_id');
        $batches = DyeBatch::query()->whereIn('id', $reqs->pluck('batch_id')->filter())->pluck('batch_no', 'id');
        $back = DB::table('dye_check_roll_returns')->whereIn('batch_id', $reqs->pluck('batch_id')->filter())->whereNotNull('sent_back_at')->distinct()->pluck('batch_id')->flip();

        return ApiResponse::ok($reqs->map(function (DyeChemRequisition $r) use ($n, $done, $batches, $back) {
            $gates = $r->batch_id ? $this->gates($r->batch_id) : null;

            return [
                'req_no' => $r->req_no, 'req_date' => $r->req_date->toDateString(), 'req_for' => $r->req_for, 'colour_id' => $r->colour_id, 'colour' => $n['colours'][$r->colour_id] ?? null,
                'process_type' => $r->process_type, 'store_id' => $r->store_id, 'store' => ChemStore::store($r->store_id), 'batch_no' => $batches[$r->batch_id] ?? null,
                'back_from_df' => $r->batch_id !== null && isset($back[$r->batch_id]), 'gates' => $gates, 'ready' => $gates !== null && $gates['ready'],
                'status' => isset($done[$r->id]) ? 'COMPLETED' : 'OPEN', 'completed_at' => ($done[$r->id] ?? null)?->created_at?->toIso8601String(),
            ];
        })->values()->all());
    }

    /** Complete a batch programme: refused, naming the gate, while any of its batch's QC gates has not passed. */
    public function complete(string $reqNo): JsonResponse
    {
        $this->allow('dyehouse.qc.manage');
        $req = DyeChemRequisition::query()->where('req_no', $reqNo)->firstOrFail();
        if (! in_array($req->req_for, ['BATCH_PROGRAM', 'REPROCESS'], true) || $req->status === 'CANCELLED') {
            throw ValidationException::withMessages(['req_no' => __('dyehouse.qc.v.not_programme', ['no' => $req->req_no])]);
        }
        if (! $req->batch_id) {
            throw ValidationException::withMessages(['req_no' => __('dyehouse.qc.v.no_batch', ['no' => $req->req_no])]);
        }
        if (DyeBatchCompletion::query()->where('requisition_id', $req->id)->exists()) {
            throw ValidationException::withMessages(['req_no' => __('dyehouse.qc.v.completed_already', ['no' => $req->req_no])]);
        }
        $g = $this->gates($req->batch_id);
        if (! $g['ready']) {
            throw ValidationException::withMessages(['req_no' => __("dyehouse.qc.v.gate_{$g['failing']}", ['no' => $g['batch_no'], 'rolls' => implode(', ', $g['held_rolls']), 'count' => $g['open_shades']])]);
        }
        DyeBatchCompletion::query()->create(['requisition_id' => $req->id, 'completed_by' => $this->caller()->auditUserId()]);

        return ApiResponse::ok(['req_no' => $req->req_no, 'status' => 'COMPLETED'], [], 201);
    }

    /**
     * A batch's QC gates: produced; check rolls inspected and none held
     * (skipped when its run by-passed check roll); no shade approval pending,
     * on hold or rejected. `failing` names the first that has not passed.
     *
     * @return array<string, mixed>
     */
    private function gates(string $batchId): array
    {
        $no = DyeBatch::query()->whereKey($batchId)->value('batch_no');
        $productions = DyeProduction::query()->where('batch_id', $batchId)->get(['id', 'bypass_check_roll']);
        $bypass = $productions->contains(fn ($p) => (bool) $p->bypass_check_roll);
        $inspections = DyeCheckRollInspection::query()->where('batch_id', $batchId)->get(['roll_no', 'result']);
        $held = $inspections->where('result', 'HELD')->pluck('roll_no')->values()->all();
        $openShades = DyeShadeApproval::query()->where('batch_id', $batchId)->where('status', '!=', 'PASSED')->count();
        $failing = match (true) {
            $productions->isEmpty() => 'not_produced',
            ! $bypass && $inspections->isEmpty() => 'not_inspected',
            ! $bypass && $held !== [] => 'held',
            $openShades > 0 => 'shade',
            default => null,
        };

        return ['batch_no' => $no, 'produced' => $productions->isNotEmpty(), 'bypass' => $bypass, 'rolls' => $inspections->count(), 'held_rolls' => $held,
            'open_shades' => $openShades, 'failing' => $failing, 'ready' => $failing === null];
    }
}
