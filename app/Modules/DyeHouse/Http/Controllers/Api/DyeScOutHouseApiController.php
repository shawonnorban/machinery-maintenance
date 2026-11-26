<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Http\Controllers\Api;

use App\Modules\DyeHouse\Models\DyeScChallan;
use App\Modules\DyeHouse\Models\DyeScChallanProgramme;
use App\Modules\DyeHouse\Models\DyeScProgramme;
use App\Modules\DyeHouse\Models\DyeScProgrammeShade;
use App\Modules\DyeHouse\Models\DyeScReceipt;
use App\Modules\DyeHouse\Models\DyeScReceiptLine;
use App\Modules\DyeHouse\Models\DyeScRequisition;
use App\Modules\DyeHouse\Models\DyeScRequisitionLine;
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
 * Dye House → S/C Dyeing (AnnotechERP 17/09): dyeing placed with an outside
 * dye house. A programme (DF-SC-OH-) names its shades; grey is called off
 * against them on the Dye Batch Programme (DF-SC-RQ-, batch DF-SC-B-) — no
 * more of a shade than it asks — and issued on the Grey Fabric Indent List;
 * the programmes go out on a challan (DF-SC-CH-), one challan each, which
 * marks them despatched; the fabric comes back first to the floor (DF-SC-FR-,
 * its QC No) and then into the store (DF-SC-MRR-), never more than the floor
 * has passed; a programme booked back in full is completed. Each paper is
 * addressed by its number.
 */
class DyeScOutHouseApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('dyehouse.sc.view');
        $c = DyeBatches::company();
        $o = ChemStore::options();

        return ApiResponse::ok([
            'suppliers' => $o['suppliers'], 'stores' => $o['stores'], 'companies' => $o['companies'], 'buyers' => $o['buyers'], 'orders' => DyeBatches::orders(),
            'colours' => DB::table('colours')->where('company_id', $c)->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])
                ->map(fn ($x) => ['value' => $x->id, 'label' => $x->code ? "{$x->name} ({$x->code})" : $x->name])->values()->all(),
            // Live programmes, with what is left of each shade to call off.
            'programmes' => DyeScProgramme::query()->whereIn('status', DyeScProgramme::LIVE)->orderByDesc('issue_date')->orderByDesc('program_no')->get()
                ->map(fn (DyeScProgramme $p) => $this->programmeRow($p, true))->values()->all(),
        ]);
    }

    /* ── Programmes ──────────────────────────────────────────────────── */

    public function programmes(): JsonResponse
    {
        $this->allow('dyehouse.sc.view');

        return ApiResponse::ok(DyeScProgramme::query()->orderByDesc('issue_date')->orderByDesc('program_no')->get()->map(fn (DyeScProgramme $p) => $this->programmeRow($p))->values()->all());
    }

    public function programme(string $no): JsonResponse
    {
        $this->allow('dyehouse.sc.view');

        return ApiResponse::ok($this->programmeRow($this->programmeOf($no), true));
    }

    /**
     * A programme and its shades together: new, or rewritten while not
     * completed or cancelled. A shade already called off stays — its kilos
     * may change, but not below what has been called off.
     */
    public function saveProgramme(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('dyehouse.sc.manage');
        $row = $no ? $this->programmeOf($no) : null;
        if ($row && in_array($row->status, ['COMPLETED', 'CANCELLED'], true)) {
            throw ValidationException::withMessages(['status' => __('dyehouse.sc.v.programme_closed', ['no' => $row->program_no])]);
        }
        $c = DyeBatches::company();
        $data = $request->validate([
            'issue_date' => ['required', 'date'],
            'exp_delivery_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'party_id' => ['nullable', Rule::in([$c])],
            'supplier_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $c)],
            'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $c)],
            'gate_pass_no' => ['nullable', 'string', 'max:40'],
            'status' => ['required', Rule::in(DyeScProgramme::STATUSES)],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'shades' => ['required', 'array', 'min:1', 'max:100'],
            'shades.*.id' => ['nullable', 'string'],
            'shades.*.buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $c)],
            'shades.*.order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $c)],
            'shades.*.colour_id' => ['required', Rule::exists('colours', 'id')->where('company_id', $c)],
            'shades.*.req_kg' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'shades.*.remarks' => ['nullable', 'string', 'max:255'],
        ], [
            'issue_date.required' => __('dyehouse.sc.v.issue_date'), 'supplier_id.required' => __('dyehouse.sc.v.party'), 'store_id.required' => __('dyehouse.sc.v.store'),
            'status.required' => __('dyehouse.sc.v.status'), 'shades.required' => __('dyehouse.sc.v.shades'), 'shades.min' => __('dyehouse.sc.v.shades'),
            'shades.*.colour_id.required' => __('dyehouse.sc.v.shade_colour'), 'shades.*.req_kg.required' => __('dyehouse.sc.v.shade_kg'), 'shades.*.req_kg.gt' => __('dyehouse.sc.v.shade_kg'),
        ]);
        ksort($data['shades']);
        $called = $row ? $this->calledOff(DyeScProgrammeShade::query()->where('programme_id', $row->id)->pluck('id')) : collect();
        $keep = collect($data['shades'])->pluck('id')->filter()->all();
        foreach ($called as $shadeId => $kg) {
            if ($kg > 0 && ! in_array($shadeId, $keep, true)) {
                throw ValidationException::withMessages(['shades' => __('dyehouse.sc.v.shade_called', ['kg' => ChemStore::qty($kg)])]);
            }
        }
        foreach (array_values($data['shades']) as $i => $s) {
            if (! empty($s['id']) && (float) $s['req_kg'] + 0.0005 < ($called[$s['id']] ?? 0)) {
                throw ValidationException::withMessages(["shades.{$i}.req_kg" => __('dyehouse.sc.v.shade_below', ['n' => $i + 1, 'kg' => ChemStore::qty($called[$s['id']])])]);
            }
        }
        $head = collect($data)->only(['issue_date', 'exp_delivery_date', 'party_id', 'supplier_id', 'store_id', 'gate_pass_no', 'status', 'remarks'])
            ->map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v)->all();
        $row = DB::transaction(function () use ($row, $head, $data): DyeScProgramme {
            if ($row) {
                $row->update($head);
            } else {
                $row = DyeScProgramme::query()->create($head + ['created_by' => $this->caller()->auditUserId(),
                    'program_no' => DyeBatches::nextNumber('DF-SC-OH-', 'dye_sc_programmes', 'program_no', $data['issue_date'], 'Ym')]);
            }
            $existing = DyeScProgrammeShade::query()->where('programme_id', $row->id)->get()->keyBy('id');
            $kept = [];
            foreach (array_values($data['shades']) as $i => $s) {
                $values = ['buyer_id' => $s['buyer_id'] ?? null, 'order_id' => $s['order_id'] ?? null, 'colour_id' => $s['colour_id'], 'req_kg' => $s['req_kg'],
                    'remarks' => ($s['remarks'] ?? '') ?: null, 'sort_order' => ($i + 1) * 10];
                $shade = ! empty($s['id']) ? ($existing[$s['id']] ?? null) : null;
                if ($shade) {
                    $shade->update($values);
                } else {
                    $shade = DyeScProgrammeShade::query()->create($values + ['programme_id' => $row->id]);
                }
                $kept[] = $shade->id;
            }
            DyeScProgrammeShade::query()->where('programme_id', $row->id)->whereNotIn('id', $kept)->delete();

            return $row;
        });

        return ApiResponse::ok($this->programmeRow($row->refresh(), true), [], $no ? 200 : 201);
    }

    /* ── Dye Batch Programme and the Grey Fabric Indent List ─────────── */

    public function requisitions(): JsonResponse
    {
        $this->allow('dyehouse.sc.view');

        return ApiResponse::ok($this->requisitionRows(DyeScRequisition::query()->orderByDesc('req_date')->orderByDesc('req_no')->get()));
    }

    public function requisition(string $no): JsonResponse
    {
        $this->allow('dyehouse.sc.view');

        return ApiResponse::ok($this->presentRequisition(DyeScRequisition::query()->where('req_no', $no)->firstOrFail()));
    }

    /**
     * Call off grey against a live programme's shades: new, or rewritten while
     * nothing has been issued. No shade is called off past what it asks.
     */
    public function saveRequisition(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('dyehouse.sc.manage');
        $row = $no ? DyeScRequisition::query()->where('req_no', $no)->firstOrFail() : null;
        if ($row && $row->status !== 'WAITING') {
            throw ValidationException::withMessages(['req_no' => __('dyehouse.sc.v.req_issued', ['no' => $row->req_no])]);
        }
        $c = DyeBatches::company();
        $data = $request->validate([
            'req_date' => ['required', 'date'],
            'program_no' => [$row ? 'prohibited' : 'required', 'string'],
            'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $c)],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.shade_id' => ['required', 'string'],
            'lines.*.req_kg' => ['required', 'numeric', 'gt:0', 'max:99999999'],
        ], [
            'req_date.required' => __('dyehouse.sc.v.req_date'), 'program_no.required' => __('dyehouse.sc.v.program'), 'store_id.required' => __('dyehouse.sc.v.store'),
            'lines.required' => __('dyehouse.sc.v.req_lines'), 'lines.min' => __('dyehouse.sc.v.req_lines'), 'lines.*.req_kg.gt' => __('dyehouse.sc.v.req_kg'), 'lines.*.req_kg.required' => __('dyehouse.sc.v.req_kg'),
        ]);
        ksort($data['lines']);
        $programme = $row ? DyeScProgramme::query()->findOrFail($row->programme_id) : $this->programmeOf($data['program_no']);
        if (! in_array($programme->status, DyeScProgramme::LIVE, true)) {
            throw ValidationException::withMessages(['program_no' => __('dyehouse.sc.v.programme_not_live', ['no' => $programme->program_no])]);
        }
        $shades = DyeScProgrammeShade::query()->where('programme_id', $programme->id)->get()->keyBy('id');
        $called = $this->calledOff($shades->keys(), $row?->id);
        $asked = [];
        foreach (array_values($data['lines']) as $i => $l) {
            $shade = $shades[$l['shade_id']] ?? null;
            if (! $shade) {
                throw ValidationException::withMessages(["lines.{$i}.shade_id" => __('dyehouse.sc.v.not_on_programme', ['no' => $programme->program_no])]);
            }
            $asked[$shade->id] = round(($asked[$shade->id] ?? 0) + (float) $l['req_kg'], 3);
            $left = round((float) $shade->req_kg - ($called[$shade->id] ?? 0), 3);
            if ($asked[$shade->id] > $left + 0.0005) {
                throw ValidationException::withMessages(["lines.{$i}.req_kg" => __('dyehouse.sc.v.req_more', ['n' => $i + 1, 'left' => ChemStore::qty(max(0, $left))])]);
            }
        }
        $row = DB::transaction(function () use ($row, $programme, $data): DyeScRequisition {
            if ($row) {
                $row->update(['req_date' => $data['req_date'], 'store_id' => $data['store_id']]);
                DyeScRequisitionLine::query()->where('requisition_id', $row->id)->delete();
            } else {
                $row = DyeScRequisition::query()->create(['req_date' => $data['req_date'], 'programme_id' => $programme->id, 'store_id' => $data['store_id'], 'status' => 'WAITING',
                    'req_no' => DyeBatches::nextNumber('DF-SC-RQ-', 'dye_sc_requisitions', 'req_no', $data['req_date'], 'Ym'),
                    'batch_no' => DyeBatches::nextNumber('DF-SC-B-', 'dye_sc_requisitions', 'batch_no', $data['req_date']), 'created_by' => $this->caller()->auditUserId()]);
            }
            foreach (array_values($data['lines']) as $i => $l) {
                DyeScRequisitionLine::query()->create(['requisition_id' => $row->id, 'shade_id' => $l['shade_id'], 'req_kg' => $l['req_kg'], 'sort_order' => ($i + 1) * 10]);
            }

            return $row;
        });

        return ApiResponse::ok($this->presentRequisition($row->refresh()), [], $no ? 200 : 201);
    }

    /** The store's issue against a requisition: what it gives now on each line, at most what is still asked. */
    public function issue(Request $request, string $no): JsonResponse
    {
        $this->allow('dyehouse.sc.issue');
        $row = DyeScRequisition::query()->where('req_no', $no)->firstOrFail();
        if ($row->status === 'ISSUED') {
            throw ValidationException::withMessages(['status' => __('dyehouse.sc.v.issued_already', ['no' => $row->req_no])]);
        }
        $data = $request->validate(['lines' => ['required', 'array', 'min:1'], 'lines.*.id' => ['required', 'string'], 'lines.*.qty' => ['required', 'numeric', 'min:0', 'max:99999999']],
            ['lines.required' => __('dyehouse.sc.v.issue_nothing')]);
        $lines = DyeScRequisitionLine::query()->where('requisition_id', $row->id)->get()->keyBy('id');
        $give = [];
        foreach ($data['lines'] as $k => $l) {
            $line = $lines[$l['id']] ?? null;
            if (! $line) {
                throw ValidationException::withMessages(["lines.{$k}.id" => __('dyehouse.sc.v.not_on_req', ['no' => $row->req_no])]);
            }
            $left = round((float) $line->req_kg - (float) $line->issued_kg, 3);
            if ((float) $l['qty'] > $left + 0.0005) {
                throw ValidationException::withMessages(["lines.{$k}.qty" => __('dyehouse.sc.v.issue_more', ['left' => ChemStore::qty(max(0, $left))])]);
            }
            if ((float) $l['qty'] > 0) {
                $give[$line->id] = (float) $l['qty'];
            }
        }
        if (! $give) {
            throw ValidationException::withMessages(['lines' => __('dyehouse.sc.v.issue_nothing')]);
        }
        DB::transaction(function () use ($row, $lines, $give): void {
            foreach ($give as $id => $qty) {
                $lines[$id]->update(['issued_kg' => round((float) $lines[$id]->issued_kg + $qty, 3)]);
            }
            $asked = (float) $lines->sum('req_kg');
            $out = (float) DyeScRequisitionLine::query()->where('requisition_id', $row->id)->sum('issued_kg');
            $row->update(['status' => $out + 0.0005 >= $asked ? 'ISSUED' : 'PARTIAL']);
        });

        return ApiResponse::ok($this->presentRequisition($row->refresh()));
    }

    /* ── Challans ────────────────────────────────────────────────────── */

    public function challans(): JsonResponse
    {
        $this->allow('dyehouse.sc.view');

        return ApiResponse::ok($this->challanRows(DyeScChallan::query()->orderByDesc('challan_date')->orderByDesc('challan_no')->get()));
    }

    public function challan(string $no): JsonResponse
    {
        $this->allow('dyehouse.sc.view');

        return ApiResponse::ok($this->challanRows(collect([DyeScChallan::query()->where('challan_no', $no)->firstOrFail()]))[0]);
    }

    /**
     * A challan and the programmes on it: the party's live programmes not on
     * another challan. They are marked despatched; one taken off goes back to
     * submitted. Nothing on it may have come back yet when it is rewritten.
     */
    public function saveChallan(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('dyehouse.sc.manage');
        $row = $no ? DyeScChallan::query()->where('challan_no', $no)->firstOrFail() : null;
        if ($row && DyeScReceipt::query()->where('challan_id', $row->id)->exists()) {
            throw ValidationException::withMessages(['challan_no' => __('dyehouse.sc.v.challan_received', ['no' => $row->challan_no])]);
        }
        $c = DyeBatches::company();
        $data = $request->validate([
            'challan_date' => ['required', 'date'],
            'party_id' => ['nullable', Rule::in([$c])],
            'supplier_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $c)],
            'store_id' => ['nullable', Rule::exists('stores', 'id')->where('company_id', $c)],
            'gate_pass_no' => ['nullable', 'string', 'max:40'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'programmes' => ['required', 'array', 'min:1', 'max:100'],
            'programmes.*' => ['string', 'distinct'],
        ], [
            'challan_date.required' => __('dyehouse.sc.v.challan_date'), 'supplier_id.required' => __('dyehouse.sc.v.party'),
            'programmes.required' => __('dyehouse.sc.v.challan_programmes'), 'programmes.min' => __('dyehouse.sc.v.challan_programmes'),
        ]);
        $progs = DyeScProgramme::query()->whereIn('program_no', $data['programmes'])->get()->keyBy('program_no');
        foreach ($data['programmes'] as $k => $pno) {
            $p = $progs[$pno] ?? null;
            if (! $p) {
                throw ValidationException::withMessages(["programmes.{$k}" => __('dyehouse.sc.v.no_programme', ['no' => $pno])]);
            }
            if ($p->supplier_id !== $data['supplier_id']) {
                throw ValidationException::withMessages(["programmes.{$k}" => __('dyehouse.sc.v.other_party', ['no' => $pno])]);
            }
            if (! in_array($p->status, DyeScProgramme::LIVE, true)) {
                throw ValidationException::withMessages(["programmes.{$k}" => __('dyehouse.sc.v.programme_not_live', ['no' => $pno])]);
            }
            if (DyeScChallanProgramme::query()->where('programme_id', $p->id)->when($row, fn ($q) => $q->where('challan_id', '!=', $row->id))->exists()) {
                throw ValidationException::withMessages(["programmes.{$k}" => __('dyehouse.sc.v.on_challan', ['no' => $pno])]);
            }
        }
        $row = DB::transaction(function () use ($row, $data, $progs): DyeScChallan {
            $head = collect($data)->only(['challan_date', 'party_id', 'supplier_id', 'store_id', 'gate_pass_no', 'remarks'])->map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v)->all();
            if ($row) {
                $row->update($head);
                $off = DyeScChallanProgramme::query()->where('challan_id', $row->id)->whereNotIn('programme_id', $progs->pluck('id'))->pluck('programme_id');
                DyeScProgramme::query()->whereIn('id', $off)->where('status', 'DESPATCHED')->update(['status' => 'SUBMITTED']);
                DyeScChallanProgramme::query()->where('challan_id', $row->id)->delete();
            } else {
                $row = DyeScChallan::query()->create($head + ['created_by' => $this->caller()->auditUserId(),
                    'challan_no' => DyeBatches::nextNumber('DF-SC-CH-', 'dye_sc_challans', 'challan_no', $data['challan_date'], 'Ym')]);
            }
            foreach (array_values($data['programmes']) as $i => $pno) {
                DyeScChallanProgramme::query()->create(['challan_id' => $row->id, 'programme_id' => $progs[$pno]->id, 'sort_order' => ($i + 1) * 10]);
            }
            DyeScProgramme::query()->whereIn('id', $progs->pluck('id'))->update(['status' => 'DESPATCHED']);

            return $row;
        });

        return ApiResponse::ok($this->challanRows(collect([$row->refresh()]))[0], [], $no ? 200 : 201);
    }

    /* ── Floor and store receipts ────────────────────────────────────── */

    public function receipts(Request $request): JsonResponse
    {
        $this->allow('dyehouse.sc.view');
        $kind = $request->validate(['kind' => ['required', Rule::in(DyeScReceipt::KINDS)]])['kind'];

        return ApiResponse::ok($this->receiptRows(DyeScReceipt::query()->where('kind', $kind)->orderByDesc('issue_date')->orderByDesc('mrr_no')->get()));
    }

    public function receipt(string $no): JsonResponse
    {
        $this->allow('dyehouse.sc.view');

        return ApiResponse::ok($this->receiptRows(collect([DyeScReceipt::query()->where('mrr_no', $no)->firstOrFail()]))[0]);
    }

    /**
     * Fabric coming back on a challan, per programme on it: to the floor
     * first, then into the store — the store books no more of a programme
     * than the floor has received. A programme booked back in full is completed.
     */
    public function saveReceipt(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('dyehouse.sc.manage');
        $row = $no ? DyeScReceipt::query()->where('mrr_no', $no)->firstOrFail() : null;
        $kind = $row?->kind ?? $request->input('kind');
        $c = DyeBatches::company();
        $data = $request->validate([
            'kind' => [$row ? 'prohibited' : 'required', Rule::in(DyeScReceipt::KINDS)],
            'issue_date' => ['required', 'date'],
            'challan_no' => [$row ? 'prohibited' : 'required', 'string'],
            'transfer_party_id' => ['nullable', Rule::exists('mp_suppliers', 'id')->where('company_id', $c)],
            'store_id' => ['nullable', Rule::exists('stores', 'id')->where('company_id', $c)],
            'batch_no' => ['nullable', 'string', 'max:40'],
            'qc_no' => ['nullable', 'string', 'max:40'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.program_no' => ['required', 'string'],
            'lines.*.qty_kg' => ['required', 'numeric', 'gt:0', 'max:99999999'],
        ], [
            'issue_date.required' => __('dyehouse.sc.v.issue_date'), 'challan_no.required' => __('dyehouse.sc.v.challan'),
            'lines.required' => __('dyehouse.sc.v.receipt_lines'), 'lines.min' => __('dyehouse.sc.v.receipt_lines'),
            'lines.*.qty_kg.required' => __('dyehouse.sc.v.receipt_kg'), 'lines.*.qty_kg.gt' => __('dyehouse.sc.v.receipt_kg'),
        ]);
        ksort($data['lines']);
        $challan = $row ? DyeScChallan::query()->findOrFail($row->challan_id) : DyeScChallan::query()->where('challan_no', $data['challan_no'])->first();
        if (! $challan) {
            throw ValidationException::withMessages(['challan_no' => __('dyehouse.sc.v.no_challan', ['no' => $data['challan_no']])]);
        }
        if ($data['issue_date'] < $challan->challan_date->toDateString()) {
            throw ValidationException::withMessages(['issue_date' => __('dyehouse.sc.v.before_challan', ['no' => $challan->challan_no])]);
        }
        $onChallan = DyeScProgramme::query()->whereIn('id', DyeScChallanProgramme::query()->where('challan_id', $challan->id)->select('programme_id'))->get()->keyBy('program_no');
        $asked = [];
        foreach (array_values($data['lines']) as $i => $l) {
            $p = $onChallan[$l['program_no']] ?? null;
            if (! $p) {
                throw ValidationException::withMessages(["lines.{$i}.program_no" => __('dyehouse.sc.v.not_on_challan', ['no' => $l['program_no'], 'challan' => $challan->challan_no])]);
            }
            $asked[$p->id] = round(($asked[$p->id] ?? 0) + (float) $l['qty_kg'], 3);
            if ($kind === 'STORE') {
                $floor = $this->received($p->id, 'FLOOR');
                $store = $this->received($p->id, 'STORE', $row?->id);
                if ($asked[$p->id] > $floor - $store + 0.0005) {
                    throw ValidationException::withMessages(["lines.{$i}.qty_kg" => __('dyehouse.sc.v.store_more', ['no' => $p->program_no, 'left' => ChemStore::qty(max(0, $floor - $store))])]);
                }
            }
        }
        $row = DB::transaction(function () use ($row, $kind, $challan, $data, $onChallan): DyeScReceipt {
            $head = ['issue_date' => $data['issue_date'], 'transfer_party_id' => $data['transfer_party_id'] ?? null, 'store_id' => $data['store_id'] ?? $challan->store_id,
                'batch_no' => $kind === 'STORE' ? (trim((string) ($data['batch_no'] ?? '')) ?: null) : null, 'qc_no' => $kind === 'FLOOR' ? (trim((string) ($data['qc_no'] ?? '')) ?: null) : null,
                'remarks' => ($data['remarks'] ?? '') ?: null];
            if ($row) {
                $row->update($head);
                DyeScReceiptLine::query()->where('receipt_id', $row->id)->delete();
            } else {
                $row = DyeScReceipt::query()->create($head + ['kind' => $kind, 'challan_id' => $challan->id, 'supplier_id' => $challan->supplier_id, 'created_by' => $this->caller()->auditUserId(),
                    'mrr_no' => DyeBatches::nextNumber($kind === 'FLOOR' ? 'DF-SC-FR-' : 'DF-SC-MRR-', 'dye_sc_receipts', 'mrr_no', $data['issue_date'], 'Ym')]);
            }
            foreach (array_values($data['lines']) as $i => $l) {
                DyeScReceiptLine::query()->create(['receipt_id' => $row->id, 'programme_id' => $onChallan[$l['program_no']]->id, 'qty_kg' => $l['qty_kg'], 'sort_order' => ($i + 1) * 10]);
            }
            // A programme booked back into the store in full is completed.
            if ($kind === 'STORE') {
                foreach ($onChallan as $p) {
                    $asked = (float) DyeScProgrammeShade::query()->where('programme_id', $p->id)->sum('req_kg');
                    if ($p->status === 'DESPATCHED' && $this->received($p->id, 'STORE') + 0.0005 >= $asked) {
                        $p->update(['status' => 'COMPLETED']);
                    }
                }
            }

            return $row;
        });

        return ApiResponse::ok($this->receiptRows(collect([$row->refresh()]))[0], [], $no ? 200 : 201);
    }

    /* ── Shared ──────────────────────────────────────────────────────── */

    private function programmeOf(string $no): DyeScProgramme
    {
        $p = DyeScProgramme::query()->where('program_no', $no)->first();
        if (! $p) {
            throw ValidationException::withMessages(['program_no' => __('dyehouse.sc.v.no_programme', ['no' => $no])]);
        }

        return $p;
    }

    /** What has been called off each shade on requisitions (one aside, given). @return Collection<string, float> */
    private function calledOff(Collection $shadeIds, ?string $exceptReqId = null): Collection
    {
        return DyeScRequisitionLine::query()->whereIn('shade_id', $shadeIds)->when($exceptReqId, fn ($q) => $q->where('requisition_id', '!=', $exceptReqId))
            ->groupBy('shade_id')->selectRaw('shade_id, sum(req_kg) as kg')->pluck('kg', 'shade_id')->map(fn ($k) => round((float) $k, 3));
    }

    /** What has come back of a programme on receipts of a kind (one aside, given). */
    private function received(string $programmeId, string $kind, ?string $exceptReceiptId = null): float
    {
        return round((float) DyeScReceiptLine::query()->where('programme_id', $programmeId)
            ->whereIn('receipt_id', DyeScReceipt::query()->where('kind', $kind)->when($exceptReceiptId, fn ($q) => $q->whereKeyNot($exceptReceiptId))->select('id'))->sum('qty_kg'), 3);
    }

    /** @return array<string, mixed> */
    private function programmeRow(DyeScProgramme $p, bool $withShades = false): array
    {
        $n = DyeBatches::names();
        $shades = DyeScProgrammeShade::query()->where('programme_id', $p->id)->orderBy('sort_order')->get();
        $challan = DyeScChallan::query()->whereIn('id', DyeScChallanProgramme::query()->where('programme_id', $p->id)->select('challan_id'))->first(['challan_no', 'challan_date']);
        $out = [
            'program_no' => $p->program_no, 'issue_date' => $p->issue_date->toDateString(), 'exp_delivery_date' => $p->exp_delivery_date?->toDateString(), 'party_id' => $p->party_id,
            'company' => $n['companies'][$p->party_id ?? DyeBatches::company()] ?? null, 'supplier_id' => $p->supplier_id, 'supplier' => $n['suppliers'][$p->supplier_id] ?? null,
            'store_id' => $p->store_id, 'store' => ChemStore::store($p->store_id), 'gate_pass_no' => $p->gate_pass_no, 'status' => $p->status, 'remarks' => $p->remarks,
            'challan_no' => $challan?->challan_no, 'challan_date' => $challan?->challan_date?->toDateString(), 'req_kg' => round((float) $shades->sum('req_kg'), 3),
            'floor_kg' => $this->received($p->id, 'FLOOR'), 'store_kg' => $this->received($p->id, 'STORE'),
        ];
        if ($withShades) {
            $called = $this->calledOff($shades->pluck('id'));
            $out['shades'] = $shades->map(function (DyeScProgrammeShade $s) use ($n, $called) {
                $o = $s->order_id ? ($n['orders'][$s->order_id] ?? null) : null;

                return ['id' => $s->id, 'buyer_id' => $s->buyer_id, 'buyer' => $n['buyers'][$s->buyer_id] ?? null, 'order_id' => $s->order_id,
                    'style' => $o ? ($o->work_style_no ?: $o->style_no) : null, 'order_no' => $o?->order_no, 'colour_id' => $s->colour_id, 'colour' => $n['colours'][$s->colour_id] ?? null,
                    'req_kg' => (float) $s->req_kg, 'called_kg' => $called[$s->id] ?? 0.0, 'left_kg' => round((float) $s->req_kg - ($called[$s->id] ?? 0), 3), 'remarks' => $s->remarks];
            })->values()->all();
        }

        return $out;
    }

    /**
     * @param  Collection<int, DyeScRequisition>  $rows
     * @return list<array<string, mixed>>
     */
    private function requisitionRows(Collection $rows): array
    {
        $n = DyeBatches::names();
        $progs = DyeScProgramme::query()->whereIn('id', $rows->pluck('programme_id'))->get(['id', 'program_no', 'supplier_id', 'issue_date'])->keyBy('id');
        $sums = DyeScRequisitionLine::query()->whereIn('requisition_id', $rows->pluck('id'))->groupBy('requisition_id')->selectRaw('requisition_id, sum(req_kg) as req, sum(issued_kg) as iss')->get()->keyBy('requisition_id');
        $buyers = DB::table('dye_sc_requisition_lines')->join('dye_sc_programme_shades', 'dye_sc_programme_shades.id', '=', 'dye_sc_requisition_lines.shade_id')
            ->whereIn('dye_sc_requisition_lines.requisition_id', $rows->pluck('id'))->get(['dye_sc_requisition_lines.requisition_id', 'dye_sc_programme_shades.buyer_id', 'dye_sc_programme_shades.order_id'])
            ->groupBy('requisition_id');

        return $rows->map(function (DyeScRequisition $r) use ($n, $progs, $sums, $buyers) {
            $p = $progs[$r->programme_id] ?? null;

            return [
                'req_no' => $r->req_no, 'batch_no' => $r->batch_no, 'req_date' => $r->req_date->toDateString(), 'program_no' => $p?->program_no, 'program_date' => $p?->issue_date?->toDateString(),
                'supplier_id' => $p?->supplier_id, 'supplier' => $n['suppliers'][$p?->supplier_id ?? ''] ?? null, 'store_id' => $r->store_id, 'store' => ChemStore::store($r->store_id),
                'req_type' => __('dyehouse.sc.req_type'), 'status' => $r->status, 'req_kg' => round((float) ($sums[$r->id]->req ?? 0), 3), 'issued_kg' => round((float) ($sums[$r->id]->iss ?? 0), 3),
                'buyer_ids' => ($buyers[$r->id] ?? collect())->pluck('buyer_id')->filter()->unique()->values()->all(),
                'order_ids' => ($buyers[$r->id] ?? collect())->pluck('order_id')->filter()->unique()->values()->all(),
            ];
        })->values()->all();
    }

    /** @return array<string, mixed> */
    private function presentRequisition(DyeScRequisition $r): array
    {
        $n = DyeBatches::names();
        $shades = DyeScProgrammeShade::query()->whereIn('id', DyeScRequisitionLine::query()->where('requisition_id', $r->id)->select('shade_id'))->get()->keyBy('id');

        return $this->requisitionRows(collect([$r]))[0] + ['lines' => DyeScRequisitionLine::query()->where('requisition_id', $r->id)->orderBy('sort_order')->get()
            ->map(fn (DyeScRequisitionLine $l) => ['id' => $l->id, 'shade_id' => $l->shade_id, 'shade' => trim(($n['colours'][$shades[$l->shade_id]->colour_id ?? ''] ?? '').' · '.(float) ($shades[$l->shade_id]->req_kg ?? 0).' kg', ' ·'),
                'req_kg' => (float) $l->req_kg, 'issued_kg' => (float) $l->issued_kg, 'left_kg' => round((float) $l->req_kg - (float) $l->issued_kg, 3)])->values()->all()];
    }

    /**
     * @param  Collection<int, DyeScChallan>  $rows
     * @return list<array<string, mixed>>
     */
    private function challanRows(Collection $rows): array
    {
        $n = DyeBatches::names();
        $links = DyeScChallanProgramme::query()->whereIn('challan_id', $rows->pluck('id'))->orderBy('sort_order')->get()->groupBy('challan_id');
        $progs = DyeScProgramme::query()->whereIn('id', $links->flatten()->pluck('programme_id'))->pluck('program_no', 'id');

        return $rows->map(fn (DyeScChallan $c) => [
            'challan_no' => $c->challan_no, 'challan_date' => $c->challan_date->toDateString(), 'party_id' => $c->party_id, 'company' => $n['companies'][$c->party_id ?? DyeBatches::company()] ?? null,
            'supplier_id' => $c->supplier_id, 'supplier' => $n['suppliers'][$c->supplier_id] ?? null, 'store_id' => $c->store_id, 'store' => ChemStore::store($c->store_id),
            'gate_pass_no' => $c->gate_pass_no, 'remarks' => $c->remarks,
            'programmes' => ($links[$c->id] ?? collect())->map(fn ($l) => $progs[$l->programme_id] ?? null)->filter()->values()->all(),
            'received' => DyeScReceipt::query()->where('challan_id', $c->id)->exists(),
        ])->values()->all();
    }

    /**
     * @param  Collection<int, DyeScReceipt>  $rows
     * @return list<array<string, mixed>>
     */
    private function receiptRows(Collection $rows): array
    {
        $n = DyeBatches::names();
        $challans = DyeScChallan::query()->whereIn('id', $rows->pluck('challan_id'))->get(['id', 'challan_no', 'challan_date'])->keyBy('id');
        $lines = DyeScReceiptLine::query()->whereIn('receipt_id', $rows->pluck('id'))->orderBy('sort_order')->get()->groupBy('receipt_id');
        $progs = DyeScProgramme::query()->whereIn('id', $lines->flatten()->pluck('programme_id'))->pluck('program_no', 'id');

        return $rows->map(fn (DyeScReceipt $r) => [
            'kind' => $r->kind, 'mrr_no' => $r->mrr_no, 'issue_date' => $r->issue_date->toDateString(), 'challan_no' => $challans[$r->challan_id]->challan_no ?? null,
            'challan_date' => $challans[$r->challan_id]?->challan_date?->toDateString(), 'supplier_id' => $r->supplier_id, 'supplier' => $n['suppliers'][$r->supplier_id] ?? null,
            'transfer_party_id' => $r->transfer_party_id, 'transfer_party' => $n['suppliers'][$r->transfer_party_id] ?? null, 'store_id' => $r->store_id, 'store' => ChemStore::store($r->store_id),
            'batch_no' => $r->batch_no, 'qc_no' => $r->qc_no, 'remarks' => $r->remarks,
            'lines' => ($lines[$r->id] ?? collect())->map(fn ($l) => ['program_no' => $progs[$l->programme_id] ?? null, 'qty_kg' => (float) $l->qty_kg])->values()->all(),
            'programmes' => ($lines[$r->id] ?? collect())->map(fn ($l) => $progs[$l->programme_id] ?? null)->filter()->unique()->values()->all(),
            'qty_kg' => round((float) ($lines[$r->id] ?? collect())->sum('qty_kg'), 3),
        ])->values()->all();
    }
}
