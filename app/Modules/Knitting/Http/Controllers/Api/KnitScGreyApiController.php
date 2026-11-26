<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\Knitting\Models\KnitGreyLot;
use App\Modules\Knitting\Models\KnitRoll;
use App\Modules\Knitting\Models\KnitScDelivery;
use App\Modules\Knitting\Models\KnitScDeliveryLine;
use App\Modules\Knitting\Models\KnitScGreyReceipt;
use App\Modules\Knitting\Models\KnitScGreyReceiptLine;
use App\Modules\Knitting\Models\KnitScGreyReturn;
use App\Modules\Knitting\Models\KnitScGreyReturnLine;
use App\Modules\Knitting\Models\KnitScReceipt;
use App\Modules\Knitting\Models\YarnLot;
use App\Modules\Knitting\Support\GreyStore;
use App\Modules\Knitting\Support\KnitProduction;
use App\Modules\Knitting\Support\YarnProcurement;
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
 * Knit Production → Fabric Store (AnnotechERP 15/05), the sub-contract
 * challans on the grey ledger, as Brickland works them:
 *
 * - Out-House Grey Receipt Challan (OGR-…): grey fabric (kilograms), collar
 *   and cuff (pieces) in from an outside knitter, against the party's own
 *   challan. Receive needs the store; it books every line onto the ledger
 *   and puts the challan on the QC worklist, where Out-House Collar & Cuff
 *   QC Receipt passes, holds or rejects the challans carrying collar / cuff.
 * - Grey Return Challan, out-house (OGT-…) and in-house (IGT-…): grey going
 *   back to a party, against the receive it answers. A line naming a roll or
 *   stock line leaves the store on Issue (a roll whole); a line without one
 *   is a quantity that moves nothing. Received Back is the party's word.
 * - In-House Grey Delivery Challan (IGD-…): one lorry — fabric rolls, the
 *   collar / cuff going with them and the party's leftover yarn going back.
 *   Issue takes the rolls off the grey ledger (the roll is delivered) and the
 *   yarn off the yarn ledger; Cancel before or after puts them back.
 */
class KnitScGreyApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allowEither('knitting.fstore.view', 'knitting.production.view');
        $o = YarnProcurement::options();
        $p = KnitProduction::options();
        $suppliers = collect($o['suppliers'])->pluck('label', 'value');

        return ApiResponse::ok(collect($o)->only(['company', 'stores', 'orders', 'suppliers', 'uoms'])->all() + [
            'colours' => $p['colours'], 'fabric_types' => $p['fabric_types'], 'types' => KnitScGreyReceipt::TYPES,
            'receipt_statuses' => KnitScGreyReceipt::STATUSES, 'return_statuses' => KnitScGreyReturn::STATUSES, 'delivery_statuses' => KnitScDelivery::STATUSES,
            // What a return answers: an outside knitter's receive challan, or an in-house party's programme receipt.
            'receipts' => KnitScGreyReceipt::query()->whereNotIn('status', ['DRAFT', 'CANCELLED'])->orderByDesc('rcv_no')->limit(500)->get(['rcv_no', 'party_id', 'challan_no'])
                ->map(fn ($r) => ['value' => $r->rcv_no, 'label' => "{$r->rcv_no} · {$r->challan_no}", 'party_id' => $r->party_id])->values()->all(),
            'sc_receipts' => KnitScReceipt::query()->whereNotIn('status', ['DRAFT', 'CANCELLED'])->orderByDesc('program_no')->limit(500)->get(['program_no', 'party_id'])
                ->map(fn ($r) => ['value' => $r->program_no, 'label' => $r->program_no.' — '.($suppliers[$r->party_id] ?? ''), 'party_id' => $r->party_id])->values()->all(),
        ]);
    }

    /** A party's yarn a store holds — a delivery's Yarn Return picker. */
    public function partyYarn(Request $request): JsonResponse
    {
        $this->allowEither('knitting.fstore.view', 'knitting.production.view');
        $f = $request->validate(['store' => ['nullable', 'string'], 'party' => ['required', 'string']]);

        return ApiResponse::ok(collect(YarnStore::lots($f['store'] ?? null))->where('supplier_id', $f['party'])->values()->all());
    }

    /* ---------------- Out-House Grey Receipt Challan ---------------- */

    public function receipts(Request $request): JsonResponse
    {
        $this->allowEither('knitting.fstore.view', 'knitting.production.view');
        $qc = $request->boolean('qc');
        $rows = KnitScGreyReceipt::query()
            ->when($qc, fn ($q) => $q->whereNotIn('status', ['DRAFT', 'CANCELLED'])
                ->whereIn('id', KnitScGreyReceiptLine::query()->whereIn('type', ['COLLAR', 'CUFF'])->select('receipt_id')))
            ->orderByDesc('rcv_date')->orderByDesc('rcv_no')->limit(500)->get();
        $lines = KnitScGreyReceiptLine::query()->whereIn('receipt_id', $rows->pluck('id'))->get()->groupBy('receipt_id');
        $names = $this->names($rows->pluck('party_id'), $rows->pluck('store_id'), $rows->pluck('order_id'));

        return ApiResponse::ok($rows->map(function (KnitScGreyReceipt $r) use ($lines, $names) {
            $mine = $lines[$r->id] ?? collect();

            return [
                'rcv_no' => $r->rcv_no, 'rcv_date' => $r->rcv_date->toDateString(), 'party_id' => $r->party_id, 'party' => $names['parties'][$r->party_id] ?? null,
                'store' => $names['stores'][$r->store_id] ?? null, 'challan_no' => $r->challan_no, 'challan_date' => $r->challan_date->toDateString(),
                'order' => $names['order']($r->order_id), 'lines' => $mine->count(), 'rolls' => (int) $mine->sum('r_qty'),
                'fabric' => round((float) $mine->where('type', 'FABRIC')->sum('qty'), 3), 'pieces' => round((float) $mine->whereIn('type', ['COLLAR', 'CUFF'])->sum('qty'), 3),
                'gate_pass_no' => $r->gate_pass_no, 'status' => $r->status,
            ];
        })->values()->all());
    }

    public function showReceipt(string $no): JsonResponse
    {
        $this->allowEither('knitting.fstore.view', 'knitting.production.view');

        return ApiResponse::ok($this->presentReceipt($this->find(KnitScGreyReceipt::class, 'rcv_no', $no)));
    }

    public function saveReceipt(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.fstore.manage');
        $row = $no ? $this->find(KnitScGreyReceipt::class, 'rcv_no', $no) : null;
        if ($row) {
            $this->assertStatus($row, 'rcv_no', ['DRAFT'], 'save', 'receipt_status');
        }
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'rcv_date' => ['required', 'date'], 'party_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'store_id' => ['nullable', Rule::exists('stores', 'id')->where('company_id', $company)], 'challan_no' => ['required', 'string', 'max:40'],
            'challan_date' => ['required', 'date'], 'order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $company)],
            'gate_pass_no' => ['nullable', 'string', 'max:40'], 'vehicle_no' => ['nullable', 'string', 'max:40'], 'note' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:300'], 'lines.*.type' => ['required', Rule::in(KnitScGreyReceipt::TYPES)],
            'lines.*.fabric_type_id' => ['nullable', Rule::exists('fabric_types', 'id')->where('company_id', $company)],
            'lines.*.description' => ['required', 'string', 'max:160'], 'lines.*.colour_id' => ['nullable', Rule::exists('colours', 'id')->where('company_id', $company)],
            'lines.*.colour_name' => ['nullable', 'string', 'max:80'], 'lines.*.shade' => ['nullable', 'string', 'max:40'], 'lines.*.roll_no' => ['nullable', 'string', 'max:40'],
            'lines.*.finish_dia' => ['nullable', 'string', 'max:24'], 'lines.*.gsm' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'lines.*.r_qty' => ['nullable', 'integer', 'min:0', 'max:999999'], 'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'lines.*.uom' => ['required', 'string', 'max:16', UomCodes::rule($company)], 'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'rcv_date.required' => __('knitting.scg.v.rcv_date'), 'party_id.required' => __('knitting.scg.v.party'), 'challan_no.required' => __('knitting.scg.v.challan_no'),
            'challan_date.required' => __('knitting.scg.v.challan_date'), 'lines.*.description.required' => __('knitting.scg.v.description'),
            'lines.*.qty.required' => __('knitting.scg.v.qty'), 'lines.*.qty.gt' => __('knitting.scg.v.qty'), 'lines.*.uom.required' => __('knitting.scg.v.uom'),
        ]);
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('knitting.scg.v.no_receipt_lines')]);
        }
        if (KnitScGreyReceipt::query()->where('party_id', $data['party_id'])->where('challan_no', $data['challan_no'])->where('status', '!=', 'CANCELLED')
            ->when($row, fn ($q) => $q->whereKeyNot($row->id))->exists()) {
            throw ValidationException::withMessages(['challan_no' => __('knitting.scg.v.challan_taken', ['no' => $data['challan_no']])]);
        }
        $row = DB::transaction(function () use ($row, $data, $lines): KnitScGreyReceipt {
            $head = collect($data)->only(['rcv_date', 'party_id', 'store_id', 'challan_no', 'challan_date', 'order_id', 'gate_pass_no', 'vehicle_no', 'note'])
                ->map(fn ($v) => $v === '' ? null : $v)->all() + ['store_id' => null, 'order_id' => null, 'gate_pass_no' => null, 'vehicle_no' => null, 'note' => null];
            $row ??= KnitScGreyReceipt::query()->create($head + [
                'rcv_no' => GreyStore::nextNumber('OGR', 'knit_sc_grey_receipts', 'rcv_no', $data['rcv_date']), 'created_by' => $this->caller()->auditUserId(),
            ]);
            $row->update($head);
            KnitScGreyReceiptLine::query()->where('receipt_id', $row->id)->delete();
            foreach ($lines as $i => $l) {
                KnitScGreyReceiptLine::query()->create(['company_id' => $row->company_id, 'receipt_id' => $row->id, 'sort_order' => $i + 1, 'qty' => (float) $l['qty'],
                    'uom' => UomCodes::normalize($l['uom']), 'description' => trim($l['description'])]
                    + collect($l)->only(['type', 'fabric_type_id', 'colour_id', 'colour_name', 'shade', 'roll_no', 'finish_dia', 'gsm', 'r_qty', 'remarks'])->map(fn ($v) => $v === '' ? null : $v)->all());
            }

            return $row;
        });

        return $no ? ApiResponse::ok($this->presentReceipt($row->refresh())) : ApiResponse::created($this->presentReceipt($row->refresh()));
    }

    public function receiptTransition(string $no, string $action): JsonResponse
    {
        $this->allow('knitting.fstore.manage');
        $row = $this->find(KnitScGreyReceipt::class, 'rcv_no', $no);
        [$from, $to] = match ($action) {
            'receive' => [['DRAFT'], 'RECEIVED'],
            'qc-pass' => [['RECEIVED', 'QC_HOLD'], 'QC_PASSED'],
            'qc-hold' => [['RECEIVED'], 'QC_HOLD'],
            'qc-reject' => [['RECEIVED', 'QC_HOLD'], 'QC_REJECTED'],
            'cancel' => [['DRAFT', 'RECEIVED'], 'CANCELLED'],
            default => abort(404),
        };
        $this->assertStatus($row, 'rcv_no', $from, $action, 'receipt_status');
        DB::transaction(function () use ($row, $action, $to): void {
            $date = $row->rcv_date->toDateString();
            $lines = KnitScGreyReceiptLine::query()->where('receipt_id', $row->id)->orderBy('sort_order')->get();
            if ($action === 'receive') {
                if (! $row->store_id) {
                    throw ValidationException::withMessages(['store_id' => __('knitting.scg.v.store_first')]);
                }
                GreyStore::assertOpen($date, 'rcv_date');
                $buyer = $row->order_id ? Order::query()->whereKey($row->order_id)->value('buyer_id') : null;
                $entries = [];
                foreach ($lines as $l) {
                    $lot = GreyStore::lotFor([
                        'store_id' => $row->store_id, 'kind' => $l->type === 'FABRIC' ? 'IN_HOUSE' : 'COLLAR_CUFF', 'order_id' => $row->order_id, 'buyer_id' => $buyer,
                        'party_id' => $row->party_id, 'roll_no' => $l->roll_no, 'fabric_type_id' => $l->fabric_type_id, 'fabric' => $l->description,
                        'colour_id' => $l->colour_id, 'colour_name' => $l->colour_name, 'finish_dia' => $l->finish_dia, 'gsm' => $l->gsm,
                        'part' => $l->type === 'FABRIC' ? null : __("knitting.scg.type.{$l->type}"),
                    ], $date);
                    $l->update(['lot_id' => $lot->id]);
                    $entries[] = ['lot_id' => $lot->id, 'qty' => (float) $l->qty, 'rolls' => (int) ($l->r_qty ?? 0), 'doc_line_id' => $l->id];
                }
                GreyStore::post('SC_RECEIVE', $row->rcv_no, $date, $entries);
            }
            if ($action === 'cancel' && $row->status === 'RECEIVED') {
                GreyStore::assertOpen($date, 'rcv_date');
                foreach ($lines->groupBy('lot_id') as $lotId => $mine) {
                    if ($lotId && GreyStore::balance($lotId) + 0.00005 < (float) $mine->sum('qty')) {
                        throw ValidationException::withMessages(['status' => __('knitting.scg.v.receipt_used', ['no' => $row->rcv_no])]);
                    }
                }
                GreyStore::unpost('SC_RECEIVE', $row->rcv_no);
            }
            $row->update(['status' => $to]);
        });

        return ApiResponse::ok($this->presentReceipt($row->refresh()));
    }

    /* ---------------- Grey Return Challan (out-house / in-house) ---------------- */

    public function returns(string $kind): JsonResponse
    {
        $this->allowEither('knitting.fstore.view', 'knitting.production.view');
        $rows = KnitScGreyReturn::query()->where('kind', $this->kind($kind))->orderByDesc('challan_date')->orderByDesc('challan_no')->limit(500)->get();
        $lines = KnitScGreyReturnLine::query()->whereIn('return_id', $rows->pluck('id'))->get()->groupBy('return_id');
        $names = $this->names($rows->pluck('party_id'), $rows->pluck('store_id'), $rows->pluck('order_id'));
        $rcv = KnitScGreyReceipt::query()->whereIn('id', $rows->pluck('receipt_id')->filter())->pluck('rcv_no', 'id');
        $scr = KnitScReceipt::query()->whereIn('id', $rows->pluck('sc_receipt_id')->filter())->pluck('program_no', 'id');

        return ApiResponse::ok($rows->map(function (KnitScGreyReturn $r) use ($lines, $names, $rcv, $scr) {
            $mine = $lines[$r->id] ?? collect();

            return [
                'challan_no' => $r->challan_no, 'challan_date' => $r->challan_date->toDateString(), 'party_id' => $r->party_id, 'party' => $names['parties'][$r->party_id] ?? null,
                'store' => $names['stores'][$r->store_id] ?? null, 'order' => $names['order']($r->order_id), 'against' => $rcv[$r->receipt_id] ?? $scr[$r->sc_receipt_id] ?? null,
                'reason' => $r->reason, 'lines' => $mine->count(), 'rolls' => (int) $mine->sum('r_qty'), 'qty' => round((float) $mine->sum('qty'), 3), 'status' => $r->status,
            ];
        })->values()->all());
    }

    public function showReturn(string $kind, string $no): JsonResponse
    {
        $this->allowEither('knitting.fstore.view', 'knitting.production.view');

        return ApiResponse::ok($this->presentReturn($this->ret($kind, $no)));
    }

    public function saveReturn(Request $request, string $kind, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.fstore.manage');
        $row = $no ? $this->ret($kind, $no) : null;
        $kind = $this->kind($kind);
        if ($row) {
            $this->assertStatus($row, 'challan_no', ['DRAFT'], 'save', 'return_status');
        }
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'challan_date' => ['required', 'date'], 'party_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'store_id' => ['nullable', Rule::exists('stores', 'id')->where('company_id', $company)],
            'order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $company)], 'against' => ['nullable', 'string'],
            'reason' => ['nullable', 'string', 'max:255'], 'gate_pass_no' => ['nullable', 'string', 'max:40'], 'vehicle_no' => ['nullable', 'string', 'max:40'],
            'driver' => ['nullable', 'string', 'max:80'], 'note' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:300'], 'lines.*.type' => ['required', Rule::in(KnitScGreyReceipt::TYPES)], 'lines.*.lot_id' => ['nullable', 'string'],
            'lines.*.description' => ['nullable', 'string', 'max:160'], 'lines.*.colour_id' => ['nullable', Rule::exists('colours', 'id')->where('company_id', $company)],
            'lines.*.colour_name' => ['nullable', 'string', 'max:80'], 'lines.*.shade' => ['nullable', 'string', 'max:40'], 'lines.*.roll_no' => ['nullable', 'string', 'max:40'],
            'lines.*.r_qty' => ['nullable', 'integer', 'min:0', 'max:999999'], 'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'lines.*.uom' => ['required', 'string', 'max:16', UomCodes::rule($company)], 'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'challan_date.required' => __('knitting.scg.v.challan_date'), 'party_id.required' => __('knitting.scg.v.party'),
            'lines.*.qty.required' => __('knitting.scg.v.qty'), 'lines.*.qty.gt' => __('knitting.scg.v.qty'), 'lines.*.uom.required' => __('knitting.scg.v.uom'),
        ]);
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('knitting.scg.v.no_return_lines')]);
        }
        [$receiptId, $scReceiptId] = [null, null];
        if (! empty($data['against'])) {
            $against = $kind === 'OUT_HOUSE'
                ? KnitScGreyReceipt::query()->where('rcv_no', $data['against'])->whereNotIn('status', ['DRAFT', 'CANCELLED'])->first()
                : KnitScReceipt::query()->where('program_no', $data['against'])->first();
            if (! $against || $against->party_id !== $data['party_id']) {
                throw ValidationException::withMessages(['against' => __('knitting.scg.v.against_party')]);
            }
            $kind === 'OUT_HOUSE' ? $receiptId = $against->id : $scReceiptId = $against->id;
        }
        $out = [];
        $seen = [];
        foreach ($lines as $i => $l) {
            $n = $i + 1;
            $lot = ! empty($l['lot_id']) ? KnitGreyLot::query()->find($l['lot_id']) : null;
            $qty = (float) $l['qty'];
            $desc = trim((string) ($l['description'] ?? ''));
            if (! empty($l['lot_id'])) {
                if (! $lot || ! $data['store_id'] || $lot->store_id !== $data['store_id']) {
                    throw ValidationException::withMessages(["lines.{$i}.lot_id" => __('knitting.scg.v.line_store', ['n' => $n])]);
                }
                if (isset($seen[$lot->id]) && $lot->roll_id) {
                    throw ValidationException::withMessages(["lines.{$i}.lot_id" => __('knitting.scg.v.roll_twice', ['n' => $n])]);
                }
                $seen[$lot->id] = true;
                $have = GreyStore::balance($lot->id);
                $qty = $lot->roll_id ? $have : $qty;
                if ($qty > $have + 0.00005 || $qty <= 0) {
                    throw ValidationException::withMessages(["lines.{$i}.qty" => __('knitting.scg.v.line_over', ['n' => $n, 'have' => round($have, 3)])]);
                }
                $desc = $desc ?: $lot->fabric;
            }
            if ($desc === '') {
                throw ValidationException::withMessages(["lines.{$i}.description" => __('knitting.scg.v.description')]);
            }
            $out[] = ['type' => $l['type'], 'lot_id' => $lot?->id, 'description' => $desc, 'colour_id' => ($l['colour_id'] ?? null) ?: $lot?->colour_id,
                'colour_name' => (($l['colour_name'] ?? '') ?: $lot?->colour_name) ?: null, 'shade' => ($l['shade'] ?? '') ?: null,
                'roll_no' => (($l['roll_no'] ?? '') ?: ($lot ? GreyStore::describe($lot)['roll_no'] : null)) ?: null, 'r_qty' => $lot?->roll_id ? 1 : ($l['r_qty'] ?? null),
                'qty' => round($qty, 4), 'uom' => UomCodes::normalize($l['uom']), 'remarks' => ($l['remarks'] ?? '') ?: null];
        }
        $row = DB::transaction(function () use ($row, $data, $out, $kind, $receiptId, $scReceiptId): KnitScGreyReturn {
            $head = collect($data)->only(['challan_date', 'party_id', 'store_id', 'order_id', 'reason', 'gate_pass_no', 'vehicle_no', 'driver', 'note'])
                ->map(fn ($v) => $v === '' ? null : $v)->all()
                + ['store_id' => null, 'order_id' => null, 'reason' => null, 'gate_pass_no' => null, 'vehicle_no' => null, 'driver' => null, 'note' => null]
                + ['receipt_id' => $receiptId, 'sc_receipt_id' => $scReceiptId];
            $row ??= KnitScGreyReturn::query()->create($head + [
                'kind' => $kind, 'challan_no' => GreyStore::nextNumber($kind === 'OUT_HOUSE' ? 'OGT' : 'IGT', 'knit_sc_grey_returns', 'challan_no', $data['challan_date']),
                'created_by' => $this->caller()->auditUserId(),
            ]);
            $row->update($head);
            KnitScGreyReturnLine::query()->where('return_id', $row->id)->delete();
            foreach ($out as $i => $l) {
                KnitScGreyReturnLine::query()->create($l + ['company_id' => $row->company_id, 'return_id' => $row->id, 'sort_order' => $i + 1]);
            }

            return $row;
        });

        return $no ? ApiResponse::ok($this->presentReturn($row->refresh())) : ApiResponse::created($this->presentReturn($row->refresh()));
    }

    public function returnTransition(string $kind, string $no, string $action): JsonResponse
    {
        $this->allow('knitting.fstore.manage');
        $row = $this->ret($kind, $no);
        [$from, $to] = match ($action) {
            'issue' => [['DRAFT'], 'ISSUED'],
            'received-back' => [['ISSUED'], 'RECEIVED_BACK'],
            'cancel' => [['DRAFT', 'ISSUED'], 'CANCELLED'],
            default => abort(404),
        };
        $this->assertStatus($row, 'challan_no', $from, $action, 'return_status');
        DB::transaction(function () use ($row, $action, $to): void {
            $date = $row->challan_date->toDateString();
            if ($action === 'issue') {
                $lines = KnitScGreyReturnLine::query()->where('return_id', $row->id)->whereNotNull('lot_id')->get();
                GreyStore::assertOpen($date, 'challan_date');
                GreyStore::post('SC_RETURN', $row->challan_no, $date, $this->outEntries($lines));
            }
            if ($action === 'cancel' && $row->status === 'ISSUED') {
                GreyStore::assertOpen($date, 'challan_date');
                GreyStore::unpost('SC_RETURN', $row->challan_no);
            }
            $row->update(['status' => $to]);
        });

        return ApiResponse::ok($this->presentReturn($row->refresh()));
    }

    /* ---------------- In-House Grey Delivery Challan ---------------- */

    public function deliveries(): JsonResponse
    {
        $this->allowEither('knitting.fstore.view', 'knitting.production.view');
        $rows = KnitScDelivery::query()->orderByDesc('challan_date')->orderByDesc('challan_no')->limit(500)->get();
        $lines = KnitScDeliveryLine::query()->whereIn('delivery_id', $rows->pluck('id'))->get()->groupBy('delivery_id');
        $names = $this->names($rows->pluck('party_id'), $rows->pluck('store_id'), collect());

        return ApiResponse::ok($rows->map(function (KnitScDelivery $d) use ($lines, $names) {
            $mine = $lines[$d->id] ?? collect();

            return [
                'challan_no' => $d->challan_no, 'challan_date' => $d->challan_date->toDateString(), 'party_id' => $d->party_id, 'party' => $names['parties'][$d->party_id] ?? null,
                'store' => $names['stores'][$d->store_id] ?? null, 'rolls' => (int) $mine->where('section', 'FABRIC')->sum('r_qty'),
                'fabric' => round((float) $mine->where('section', 'FABRIC')->sum('qty'), 3), 'pieces' => round((float) $mine->where('section', 'COLLAR_CUFF')->sum('qty'), 3),
                'yarn' => round((float) $mine->where('section', 'YARN')->sum('qty'), 3), 'receiver' => $d->receiver, 'gate_pass_no' => $d->gate_pass_no, 'status' => $d->status,
            ];
        })->values()->all());
    }

    public function showDelivery(string $no): JsonResponse
    {
        $this->allowEither('knitting.fstore.view', 'knitting.production.view');

        return ApiResponse::ok($this->presentDelivery($this->find(KnitScDelivery::class, 'challan_no', $no)));
    }

    public function saveDelivery(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.fstore.manage');
        $row = $no ? $this->find(KnitScDelivery::class, 'challan_no', $no) : null;
        if ($row) {
            $this->assertStatus($row, 'challan_no', ['DRAFT'], 'save', 'delivery_status');
        }
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'challan_date' => ['required', 'date'], 'party_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $company)], 'gate_pass_no' => ['nullable', 'string', 'max:40'],
            'vehicle_no' => ['nullable', 'string', 'max:40'], 'driver' => ['nullable', 'string', 'max:80'], 'receiver' => ['nullable', 'string', 'max:80'],
            'receiver_mobile' => ['nullable', 'string', 'max:24'], 'note' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:400'], 'lines.*.section' => ['required', Rule::in(KnitScDeliveryLine::SECTIONS)],
            'lines.*.part' => ['nullable', Rule::in(['COLLAR', 'CUFF'])], 'lines.*.lot_id' => ['nullable', 'string'], 'lines.*.yarn_lot_id' => ['nullable', 'string'],
            'lines.*.shade' => ['nullable', 'string', 'max:40'], 'lines.*.mc_gauge' => ['nullable', 'string', 'max:24'], 'lines.*.bags' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'lines.*.uom' => ['required', 'string', 'max:16', UomCodes::rule($company)],
            'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'challan_date.required' => __('knitting.scg.v.challan_date'), 'party_id.required' => __('knitting.scg.v.party'), 'store_id.required' => __('knitting.scg.v.store'),
            'lines.*.qty.required' => __('knitting.scg.v.qty'), 'lines.*.qty.gt' => __('knitting.scg.v.qty'), 'lines.*.uom.required' => __('knitting.scg.v.uom'),
        ]);
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('knitting.scg.v.no_delivery_lines')]);
        }
        $names = YarnProcurement::names();
        $out = [];
        $seen = [];
        foreach ($lines as $i => $l) {
            $n = $i + 1;
            $qty = (float) $l['qty'];
            if ($l['section'] === 'YARN') {
                $yarn = ! empty($l['yarn_lot_id']) ? YarnLot::query()->whereKey($l['yarn_lot_id'])->where('store_id', $data['store_id'])->first() : null;
                if (! $yarn) {
                    throw ValidationException::withMessages(["lines.{$i}.yarn_lot_id" => __('knitting.scg.v.yarn_lot', ['n' => $n])]);
                }
                if ($yarn->supplier_id !== $data['party_id']) {
                    throw ValidationException::withMessages(["lines.{$i}.yarn_lot_id" => __('knitting.scg.v.yarn_party', ['n' => $n])]);
                }
                $have = YarnStore::balance($yarn->id);
                if ($qty > $have + 0.00005) {
                    throw ValidationException::withMessages(["lines.{$i}.qty" => __('knitting.scg.v.line_over', ['n' => $n, 'have' => round($have, 3)])]);
                }
                $out[] = ['section' => 'YARN', 'part' => null, 'lot_id' => null, 'yarn_lot_id' => $yarn->id, 'description' => YarnStore::describe($yarn, $names)['yarn'] ?: null,
                    'colour_name' => $yarn->colour, 'shade' => ($l['shade'] ?? '') ?: null, 'lot_no' => $yarn->lot_no, 'bags' => $l['bags'] ?? null, 'qty' => round($qty, 4),
                    'uom' => UomCodes::normalize($l['uom']), 'remarks' => ($l['remarks'] ?? '') ?: null];

                continue;
            }
            $lot = ! empty($l['lot_id']) ? KnitGreyLot::query()->whereKey($l['lot_id'])->where('store_id', $data['store_id'])->first() : null;
            if (! $lot) {
                throw ValidationException::withMessages(["lines.{$i}.lot_id" => __('knitting.scg.v.line_store', ['n' => $n])]);
            }
            if (isset($seen[$lot->id]) && $lot->roll_id) {
                throw ValidationException::withMessages(["lines.{$i}.lot_id" => __('knitting.scg.v.roll_twice', ['n' => $n])]);
            }
            $seen[$lot->id] = true;
            $have = GreyStore::balance($lot->id);
            if ($qty > $have + 0.00005) {
                throw ValidationException::withMessages(["lines.{$i}.qty" => __('knitting.scg.v.line_over', ['n' => $n, 'have' => round($have, 3)])]);
            }
            $d = GreyStore::describe($lot);
            $card = $lot->roll_id ? DB::table('knit_rolls')->join('knit_cards', 'knit_cards.id', '=', 'knit_rolls.card_id')->where('knit_rolls.id', $lot->roll_id)->value('knit_cards.gauge') : null;
            $out[] = ['section' => $l['section'], 'part' => $l['section'] === 'COLLAR_CUFF' ? (($l['part'] ?? '') ?: 'COLLAR') : null, 'lot_id' => $lot->id, 'yarn_lot_id' => null,
                'description' => $lot->fabric, 'colour_id' => $lot->colour_id, 'colour_name' => $lot->colour_name, 'shade' => ($l['shade'] ?? '') ?: null, 'roll_no' => $d['roll_no'],
                'finish_dia' => $lot->finish_dia, 'mc_gauge' => (($l['mc_gauge'] ?? '') ?: $card) ?: null, 'r_qty' => $lot->roll_id ? 1 : null, 'qty' => round($qty, 4),
                'uom' => UomCodes::normalize($l['uom']), 'remarks' => ($l['remarks'] ?? '') ?: null];
        }
        $row = DB::transaction(function () use ($row, $data, $out): KnitScDelivery {
            $head = collect($data)->only(['challan_date', 'party_id', 'store_id', 'gate_pass_no', 'vehicle_no', 'driver', 'receiver', 'receiver_mobile', 'note'])
                ->map(fn ($v) => $v === '' ? null : $v)->all() + ['gate_pass_no' => null, 'vehicle_no' => null, 'driver' => null, 'receiver' => null, 'receiver_mobile' => null, 'note' => null];
            $row ??= KnitScDelivery::query()->create($head + [
                'challan_no' => GreyStore::nextNumber('IGD', 'knit_sc_deliveries', 'challan_no', $data['challan_date']), 'created_by' => $this->caller()->auditUserId(),
            ]);
            $row->update($head);
            KnitScDeliveryLine::query()->where('delivery_id', $row->id)->delete();
            foreach ($out as $i => $l) {
                KnitScDeliveryLine::query()->create($l + ['company_id' => $row->company_id, 'delivery_id' => $row->id, 'sort_order' => $i + 1]);
            }

            return $row;
        });

        return $no ? ApiResponse::ok($this->presentDelivery($row->refresh())) : ApiResponse::created($this->presentDelivery($row->refresh()));
    }

    public function deliveryTransition(string $no, string $action): JsonResponse
    {
        $this->allow('knitting.fstore.manage');
        $row = $this->find(KnitScDelivery::class, 'challan_no', $no);
        [$from, $to] = match ($action) {
            'issue' => [['DRAFT'], 'ISSUED'],
            'cancel' => [['DRAFT', 'ISSUED'], 'CANCELLED'],
            default => abort(404),
        };
        $this->assertStatus($row, 'challan_no', $from, $action, 'delivery_status');
        DB::transaction(function () use ($row, $action, $to): void {
            $date = $row->challan_date->toDateString();
            $lines = KnitScDeliveryLine::query()->where('delivery_id', $row->id)->orderBy('sort_order')->get();
            $grey = $lines->whereNotNull('lot_id');
            $yarn = $lines->whereNotNull('yarn_lot_id');
            $rolls = KnitGreyLot::query()->whereIn('id', $grey->pluck('lot_id'))->whereNotNull('roll_id')->pluck('roll_id');
            if ($action === 'issue') {
                GreyStore::assertOpen($date, 'challan_date');
                YarnStore::assertOpen($date, 'challan_date');
                foreach ($yarn->groupBy('yarn_lot_id') as $lotId => $mine) {
                    if (YarnStore::balance($lotId) + 0.00005 < (float) $mine->sum('qty')) {
                        throw ValidationException::withMessages(['lines' => __('knitting.scg.v.yarn_short', ['lot' => $mine->first()->lot_no ?? '—'])]);
                    }
                }
                GreyStore::post('SC_DELIVERY', $row->challan_no, $date, $this->outEntries($grey));
                YarnStore::post('SC_YARN_RETURN', $row->challan_no, $date, $yarn->map(fn ($l) => [
                    'lot_id' => $l->yarn_lot_id, 'qty' => -(float) $l->qty, 'value' => -(float) $l->qty * (float) YarnLot::query()->whereKey($l->yarn_lot_id)->value('rate'), 'doc_line_id' => $l->id,
                ])->values()->all());
                KnitRoll::query()->whereIn('id', $rolls)->update(['movement' => 'DELIVERED']);
            }
            if ($action === 'cancel' && $row->status === 'ISSUED') {
                GreyStore::assertOpen($date, 'challan_date');
                YarnStore::assertOpen($date, 'challan_date');
                GreyStore::unpost('SC_DELIVERY', $row->challan_no);
                YarnStore::unpost('SC_YARN_RETURN', $row->challan_no);
                KnitRoll::query()->whereIn('id', $rolls)->update(['movement' => 'GREY_STORE']);
            }
            $row->update(['status' => $to]);
        });

        return ApiResponse::ok($this->presentDelivery($row->refresh()));
    }

    /* ---------------- helpers ---------------- */

    private function find(string $model, string $column, string $no): Model
    {
        return $model::query()->where($column, $no)->firstOrFail();
    }

    private function kind(string $slug): string
    {
        return match ($slug) {
            'out-house' => 'OUT_HOUSE',
            'in-house' => 'IN_HOUSE',
            default => abort(404),
        };
    }

    private function ret(string $slug, string $no): KnitScGreyReturn
    {
        return KnitScGreyReturn::query()->where('kind', $this->kind($slug))->where('challan_no', $no)->firstOrFail();
    }

    private function assertStatus(Model $row, string $column, array $from, string $action, string $group): void
    {
        if (! in_array($row->status, $from, true)) {
            throw ValidationException::withMessages(['status' => __('knitting.scg.v.cannot', [
                'no' => $row->{$column}, 'status' => __("knitting.scg.{$group}.{$row->status}"), 'action' => __("knitting.scg.action.{$action}"),
            ])]);
        }
    }

    /**
     * Lines leaving the grey ledger: a roll whole, a balance what the line says — never more than is there.
     *
     * @return list<array<string, mixed>>
     */
    private function outEntries($lines): array
    {
        $entries = [];
        foreach ($lines->groupBy('lot_id') as $lotId => $mine) {
            $lot = KnitGreyLot::query()->findOrFail($lotId);
            $have = GreyStore::balance($lotId);
            if ($have + 0.00005 < (float) $mine->sum('qty') || $have <= 0) {
                throw ValidationException::withMessages(['lines' => __('knitting.fs.v.short', ['line' => GreyStore::describe($lot)['label'], 'have' => round($have, 3)])]);
            }
            foreach ($mine as $l) {
                $entries[] = ['lot_id' => $lotId, 'qty' => $lot->roll_id ? -$have : -(float) $l->qty, 'rolls' => $lot->roll_id ? -1 : 0, 'doc_line_id' => $l->id];
            }
        }

        return $entries;
    }

    /** Party, store and order names for list rows. */
    private function names($parties, $stores, $orders): array
    {
        $o = Order::query()->whereIn('id', collect($orders)->filter()->unique()->values())->get(['id', 'order_no', 'style_no', 'work_style_no'])->keyBy('id');

        return [
            'parties' => DB::table('mp_suppliers')->whereIn('id', collect($parties)->filter()->unique()->values())->pluck('name', 'id'),
            'stores' => DB::table('stores')->whereIn('id', collect($stores)->filter()->unique()->values())->pluck('name', 'id'),
            'order' => fn (?string $id) => isset($o[$id]) ? trim(($o[$id]->work_style_no ?: $o[$id]->style_no).' · '.$o[$id]->order_no, ' ·') : null,
        ];
    }

    /** @return array<string, mixed> */
    private function head(Model $r): array
    {
        $party = DB::table('mp_suppliers')->where('id', $r->party_id)->first(['name', 'address']);
        $names = $this->names([], [$r->store_id], [$r->order_id ?? null]);

        return ['party_id' => $r->party_id, 'party' => $party?->name, 'party_address' => $party?->address, 'store_id' => $r->store_id,
            'store' => $r->store_id ? DB::table('stores')->where('id', $r->store_id)->value('name') : null, 'order_id' => $r->order_id ?? null,
            'order' => $names['order']($r->order_id ?? null), 'company' => DB::table('companies')->where('id', $r->company_id)->value('name'), 'status' => $r->status];
    }

    /** @return array<string, mixed> */
    private function presentReceipt(KnitScGreyReceipt $r): array
    {
        return ['id' => $r->id, 'rcv_no' => $r->rcv_no, 'rcv_date' => $r->rcv_date->toDateString(), 'challan_no' => $r->challan_no, 'challan_date' => $r->challan_date->toDateString(),
            'gate_pass_no' => $r->gate_pass_no, 'vehicle_no' => $r->vehicle_no, 'note' => $r->note] + $this->head($r) + [
                'lines' => KnitScGreyReceiptLine::query()->where('receipt_id', $r->id)->orderBy('sort_order')->get()->map(fn ($l) => collect($l->only([
                    'type', 'fabric_type_id', 'description', 'colour_id', 'colour_name', 'shade', 'roll_no', 'finish_dia', 'gsm', 'r_qty', 'qty', 'uom', 'remarks',
                ]))->all())->values()->all(),
            ];
    }

    /** @return array<string, mixed> */
    private function presentReturn(KnitScGreyReturn $r): array
    {
        return ['id' => $r->id, 'challan_no' => $r->challan_no, 'kind' => $r->kind, 'challan_date' => $r->challan_date->toDateString(),
            'against' => $r->receipt_id ? KnitScGreyReceipt::query()->whereKey($r->receipt_id)->value('rcv_no') : ($r->sc_receipt_id ? KnitScReceipt::query()->whereKey($r->sc_receipt_id)->value('program_no') : null),
            'reason' => $r->reason, 'gate_pass_no' => $r->gate_pass_no, 'vehicle_no' => $r->vehicle_no, 'driver' => $r->driver, 'note' => $r->note] + $this->head($r) + [
                'lines' => KnitScGreyReturnLine::query()->where('return_id', $r->id)->orderBy('sort_order')->get()->map(fn ($l) => collect($l->only([
                    'type', 'lot_id', 'description', 'colour_id', 'colour_name', 'shade', 'roll_no', 'r_qty', 'qty', 'uom', 'remarks',
                ]))->all())->values()->all(),
            ];
    }

    /** @return array<string, mixed> */
    private function presentDelivery(KnitScDelivery $d): array
    {
        return ['id' => $d->id, 'challan_no' => $d->challan_no, 'challan_date' => $d->challan_date->toDateString(), 'gate_pass_no' => $d->gate_pass_no,
            'vehicle_no' => $d->vehicle_no, 'driver' => $d->driver, 'receiver' => $d->receiver, 'receiver_mobile' => $d->receiver_mobile, 'note' => $d->note] + $this->head($d) + [
                'lines' => KnitScDeliveryLine::query()->where('delivery_id', $d->id)->orderBy('sort_order')->get()->map(fn ($l) => collect($l->only([
                    'section', 'part', 'lot_id', 'yarn_lot_id', 'description', 'colour_id', 'colour_name', 'shade', 'roll_no', 'lot_no', 'finish_dia', 'mc_gauge', 'r_qty', 'bags', 'qty', 'uom', 'remarks',
                ]))->all())->values()->all(),
            ];
    }
}
