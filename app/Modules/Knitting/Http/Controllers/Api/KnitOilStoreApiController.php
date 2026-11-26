<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\Knitting\Models\KnitOilConsumption;
use App\Modules\Knitting\Models\KnitOilConsumptionLine;
use App\Modules\Knitting\Models\KnitOilOpening;
use App\Modules\Knitting\Models\KnitOilReceipt;
use App\Modules\Knitting\Models\KnitOilReceiptLine;
use App\Modules\Knitting\Models\KnitOilReq;
use App\Modules\Knitting\Models\KnitOilReqLine;
use App\Modules\Knitting\Models\KnitOilReturn;
use App\Modules\Knitting\Models\KnitOilReturnLine;
use App\Modules\Knitting\Support\KnitProduction;
use App\Modules\Knitting\Support\NeedleStore;
use App\Modules\Knitting\Support\YarnStore;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Knit Production → Oil Store (AnnotechERP 15/10), posted to the knitting
 * store's OIL book, as Brickland keeps it:
 *
 * - Knit Machine Oil Indent (ORQ-…): one store asks another. 13-Submit,
 *   14-Verify & Submit R; once verified only Issue Qty changes — saving it
 *   moves the oil out of the store asked and into the store asking. Issue
 *   in Full, Close, Re-open (undoes what was released), Cancel.
 * - Machine Oil Opening Balance: one line per store, item and date; Cost
 *   Price is Unit Price × Conv. Rate; Finalize (a row, or Finalize All on
 *   the ticked rows) posts it and locks it.
 * - Daily Machine Oil Consumption (OCS-…): one sheet per store, date and
 *   shift; Finalize, once Issue By is named, takes the oil out of the store.
 * - Machine Oil Receipt (OMR-…): Send to Next Desk walks it Draft → QC →
 *   Verification → Accounts → Approved → Complete; Finalize (at
 *   Verification or Accounts) posts it, Accounting Done marks it, and it
 *   leaves Accounts only finalized and Approved only accounted. Cancel
 *   until it is finalized.
 * - Oil Receipt Return: against a finalized receipt, never more than is
 *   still returnable; Issue posts it, Finalize, Re-open, Cancel.
 */
class KnitOilStoreApiController extends ApiController
{
    private const BOOK = 'OIL';

    public function options(): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');
        $company = app(TenantContext::class)->companyId();

        return ApiResponse::ok(NeedleStore::options() + [
            'req_statuses' => KnitOilReq::STATUSES, 'receipt_statuses' => KnitOilReceipt::STATUSES, 'pay_methods' => KnitOilReceipt::PAY_METHODS,
            'return_types' => KnitOilReturn::TYPES, 'return_statuses' => KnitOilReturn::STATUSES,
            'currencies' => DB::table('currencies')->where('company_id', $company)->where('active', true)->orderBy('code')->get(['id', 'code', 'name', 'exchange_rate'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => "{$c->code} — {$c->name}", 'code' => $c->code, 'rate' => (float) $c->exchange_rate])->values()->all(),
        ]);
    }

    /* ---------------- indent ---------------- */

    public function reqs(): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');
        $rows = KnitOilReq::query()->orderByDesc('req_date')->orderByDesc('req_no')->limit(500)->get();
        $sums = KnitOilReqLine::query()->whereIn('req_id', $rows->pluck('id'))->groupBy('req_id')
            ->selectRaw('req_id, sum(required_qty) as r, sum(issue_qty) as i')->get()->keyBy('req_id');
        [, $stores] = NeedleStore::names();

        return ApiResponse::ok($rows->map(function (KnitOilReq $r) use ($sums, $stores) {
            $req = round((float) ($sums[$r->id]->r ?? 0), 3);
            $iss = round((float) ($sums[$r->id]->i ?? 0), 3);

            // Nothing comes back on an oil indent: what is out is all still out.
            return ['req_no' => $r->req_no, 'req_date' => $r->req_date->toDateString(), 'from_store_id' => $r->from_store_id, 'from_store' => $stores[$r->from_store_id] ?? null,
                'to_store_id' => $r->to_store_id, 'to_store' => $stores[$r->to_store_id] ?? null, 'required' => $req, 'issued' => $iss, 'balance' => round($req - $iss, 3),
                'returned' => 0.0, 'outstanding' => $iss, 'status' => $r->status];
        })->values()->all());
    }

    public function showReq(string $no): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');

        return ApiResponse::ok($this->presentReq(KnitOilReq::query()->where('req_no', $no)->firstOrFail()));
    }

    public function saveReq(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.needle.manage');
        $row = $no ? KnitOilReq::query()->where('req_no', $no)->firstOrFail() : null;
        if ($row && in_array($row->status, ['VERIFIED', 'PARTLY_ISSUED', 'ISSUED'], true)) {
            return $this->writeIssueQty($request, $row);
        }
        $row && $this->assertStatus($row->req_no, $row->status, ['DRAFT'], 'save', 'oil_req_status');
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'req_date' => ['required', 'date'], 'req_type' => ['nullable', Rule::in(YarnStore::REQ_TYPES)],
            'office_id' => ['nullable', Rule::exists('factories', 'id')->where('company_id', $company)],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('company_id', $company)],
            'from_store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $company)],
            'to_store_id' => ['required', 'different:from_store_id', Rule::exists('stores', 'id')->where('company_id', $company)],
            'reason' => ['nullable', 'string', 'max:300'], 'remarks' => ['nullable', 'string', 'max:2000'],
        ] + $this->lineRules(['required_qty' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'rate' => ['nullable', 'numeric', 'min:0', 'max:99999999']]), [
            'req_date.required' => __('knitting.nd.v.req_date'), 'from_store_id.required' => __('knitting.nd.v.from_store'),
            'to_store_id.required' => __('knitting.nd.v.oil_to_store'), 'to_store_id.different' => __('knitting.nd.v.same_store'),
            'lines.*.item_id.required' => __('knitting.nd.v.oil_line_item'), 'lines.*.required_qty.required' => __('knitting.nd.v.line_qty'),
            'lines.*.required_qty.gt' => __('knitting.nd.v.line_qty'),
        ]);
        $lines = $this->needLines($data, 'no_oil_lines');
        $row = DB::transaction(function () use ($row, $data, $lines): KnitOilReq {
            $head = collect($data)->except('lines')->map(fn ($v) => $v === '' ? null : $v)->all()
                + ['req_type' => null, 'office_id' => null, 'department_id' => null, 'reason' => null, 'remarks' => null];
            $row ??= KnitOilReq::query()->create($head + ['req_no' => KnitProduction::nextNumber('ORQ', 'knit_oil_reqs', 'req_no', $data['req_date']),
                'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            $row->update($head);
            KnitOilReqLine::query()->where('req_id', $row->id)->delete();
            foreach ($lines as $i => $l) {
                KnitOilReqLine::query()->create(['company_id' => $row->company_id, 'req_id' => $row->id, 'sort_order' => $i + 1, 'item_id' => $l['item_id'],
                    'uom' => UomCodes::normalize($l['uom'] ?? null) ?: null, 'required_qty' => round((float) $l['required_qty'], 4),
                    'rate' => round((float) ($l['rate'] ?? 0), 4), 'remarks' => ($l['remarks'] ?? '') ?: null]);
            }

            return $row;
        });

        return $no ? ApiResponse::ok($this->presentReq($row->refresh())) : ApiResponse::created($this->presentReq($row->refresh()));
    }

    public function reqTransition(string $no, string $action): JsonResponse
    {
        $this->allow('knitting.needle.manage');
        $row = KnitOilReq::query()->where('req_no', $no)->firstOrFail();
        [$from, $to] = match ($action) {
            'submit' => [['DRAFT'], 'WAITING'],
            'submit-verify' => [['DRAFT'], 'VERIFIED'],
            'verify' => [['WAITING'], 'VERIFIED'],
            'issue-full' => [['VERIFIED', 'PARTLY_ISSUED'], 'ISSUED'],
            'reopen' => [['VERIFIED', 'PARTLY_ISSUED', 'ISSUED'], 'DRAFT'],
            'close' => [['PARTLY_ISSUED'], 'CLOSED'],
            'cancel' => [['DRAFT', 'WAITING', 'VERIFIED'], 'CANCELLED'],
            default => abort(404),
        };
        $this->assertStatus($row->req_no, $row->status, $from, $action, 'oil_req_status');
        DB::transaction(function () use ($row, $action, $to): void {
            $lines = KnitOilReqLine::query()->where('req_id', $row->id)->get();
            if (in_array($action, ['submit-verify', 'verify'], true)) {
                $row->update(['verified_at' => now(), 'verified_by' => $this->caller()->auditUserId()]);
            }
            if ($action === 'issue-full') {
                // Every line released for the whole quantity asked.
                foreach ($lines as $l) {
                    $l->issue_qty = $l->required_qty;
                }
                $this->postIssue($row, $lines);
                $lines->each->save();
            }
            if ($action === 'reopen') {
                // Re-opening takes back what was released and starts the paper over.
                $this->postIssue($row, $lines->each(fn ($l) => $l->issue_qty = 0));
                $lines->each->save();
                $row->update(['verified_at' => null, 'verified_by' => null]);
            }
            $row->update(['status' => $to]);
        });

        return ApiResponse::ok($this->presentReq($row->refresh()));
    }

    /* ---------------- opening balance ---------------- */

    public function openings(): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');

        return ApiResponse::ok(KnitOilOpening::query()->orderByDesc('ob_date')->limit(2000)->get()->map(fn (KnitOilOpening $o) => $this->presentOpening($o))->values()->all());
    }

    public function saveOpening(Request $request, ?string $id = null): JsonResponse
    {
        $this->allow('knitting.needle.manage');
        $row = $id ? KnitOilOpening::query()->findOrFail($id) : null;
        if ($row?->finalized) {
            throw ValidationException::withMessages(['ob_date' => __('knitting.nd.v.ob_line_final')]);
        }
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $company)], 'ob_date' => ['required', 'date'],
            'item_id' => ['required', Rule::exists('gs_items', 'id')->where('company_id', $company)], 'uom' => ['nullable', 'string', 'max:16', UomCodes::rule($company)],
            'qty' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'unit_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'currency_id' => ['nullable', Rule::exists('currencies', 'id')->where('company_id', $company)], 'conv_rate' => ['nullable', 'numeric', 'gt:0', 'max:99999999'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ], ['store_id.required' => __('knitting.nd.v.store'), 'ob_date.required' => __('knitting.nd.v.ob_date'), 'item_id.required' => __('knitting.nd.v.oil_item'),
            'qty.required' => __('knitting.nd.v.ob_qty'), 'qty.gt' => __('knitting.nd.v.ob_qty')]);
        $date = CarbonImmutable::parse($data['ob_date'])->toDateString();
        if (KnitOilOpening::query()->where('store_id', $data['store_id'])->whereDate('ob_date', $date)->where('item_id', $data['item_id'])
            ->when($row, fn ($q) => $q->whereKeyNot($row->id))->exists()) {
            throw ValidationException::withMessages(['item_id' => __('knitting.nd.v.ob_line_taken')]);
        }
        NeedleStore::assertOpen($data['store_id'], $date, 'ob_date', self::BOOK);
        $price = round((float) ($data['unit_price'] ?? 0), 4);
        $conv = round((float) ($data['conv_rate'] ?? 1), 4);
        $vals = ['store_id' => $data['store_id'], 'ob_date' => $date, 'item_id' => $data['item_id'], 'uom' => UomCodes::normalize($data['uom'] ?? null) ?: null,
            'qty' => round((float) $data['qty'], 4), 'unit_price' => $price, 'currency_id' => $data['currency_id'] ?? null, 'conv_rate' => $conv,
            'cost_price' => round($price * $conv, 4), 'remarks' => ($data['remarks'] ?? '') ?: null];
        $row ? $row->update($vals) : $row = KnitOilOpening::query()->create($vals + ['created_by' => $this->caller()->auditUserId()]);

        return $id ? ApiResponse::ok($this->presentOpening($row->refresh())) : ApiResponse::created($this->presentOpening($row->refresh()));
    }

    /** Finalize one line (`id`) or every ticked one (`ids`): each posts as the store's opening and stops being editable. */
    public function finalizeOpenings(Request $request, ?string $id = null): JsonResponse
    {
        $this->allow('knitting.needle.manage');
        $ids = $id ? [$id] : $request->validate(['ids' => ['required', 'array', 'min:1', 'max:2000'], 'ids.*' => ['string']],
            ['ids.required' => __('knitting.nd.v.nothing_ticked'), 'ids.min' => __('knitting.nd.v.nothing_ticked')])['ids'];
        $rows = KnitOilOpening::query()->whereIn('id', $ids)->where('finalized', false)->get();
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['ids' => __('knitting.nd.v.nothing_ticked')]);
        }
        DB::transaction(function () use ($rows): void {
            foreach ($rows as $o) {
                NeedleStore::assertOpen($o->store_id, $o->ob_date->toDateString(), 'ids', self::BOOK);
                NeedleStore::post('OPENING', 'OOB-'.$o->id, $o->ob_date->toDateString(), [
                    ['store_id' => $o->store_id, 'item_id' => $o->item_id, 'qty' => $o->qty, 'value' => $o->qty * $o->cost_price, 'doc_line_id' => $o->id],
                ], self::BOOK);
                $o->update(['finalized' => true, 'finalized_at' => now(), 'finalized_by' => $this->caller()->auditUserId()]);
            }
        });

        return ApiResponse::ok($rows->map(fn (KnitOilOpening $o) => $this->presentOpening($o->refresh()))->values()->all());
    }

    /* ---------------- daily consumption ---------------- */

    public function consumptions(): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');
        $rows = KnitOilConsumption::query()->orderByDesc('cons_date')->orderByDesc('cons_no')->limit(500)->get();
        $sums = KnitOilConsumptionLine::query()->whereIn('consumption_id', $rows->pluck('id'))->groupBy('consumption_id')
            ->selectRaw('consumption_id, count(*) as n, sum(qty) as q, sum(running_hrs) as h, sum(qty * cost_price) as v')->get()->keyBy('consumption_id');
        [, $stores] = NeedleStore::names();
        $offices = DB::table('factories')->whereIn('id', $rows->pluck('office_id')->filter())->pluck('name', 'id');
        $shifts = DB::table('hr_shifts')->whereIn('id', $rows->pluck('shift_id'))->pluck('name', 'id');
        $people = DB::table('hr_employees')->whereIn('id', $rows->pluck('issued_by')->filter())->pluck('name', 'id');

        return ApiResponse::ok($rows->map(fn (KnitOilConsumption $r) => [
            'cons_no' => $r->cons_no, 'cons_date' => $r->cons_date->toDateString(), 'shift_id' => $r->shift_id, 'shift' => $shifts[$r->shift_id] ?? null, 'office_id' => $r->office_id, 'office' => $offices[$r->office_id] ?? null,
            'store_id' => $r->store_id, 'store' => $stores[$r->store_id] ?? null, 'issued_by' => $people[$r->issued_by] ?? null,
            'lines' => (int) ($sums[$r->id]->n ?? 0), 'qty' => round((float) ($sums[$r->id]->q ?? 0), 3), 'running_hrs' => round((float) ($sums[$r->id]->h ?? 0), 2),
            'value' => round((float) ($sums[$r->id]->v ?? 0), 2), 'status' => $r->status,
        ])->values()->all());
    }

    public function showConsumption(string $no): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');

        return ApiResponse::ok($this->presentConsumption(KnitOilConsumption::query()->where('cons_no', $no)->firstOrFail()));
    }

    public function saveConsumption(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.needle.manage');
        $row = $no ? KnitOilConsumption::query()->where('cons_no', $no)->firstOrFail() : null;
        $row && $this->assertStatus($row->cons_no, $row->status, ['DRAFT'], 'save', 'cons_status');
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'cons_date' => ['required', 'date'], 'shift_id' => ['required', Rule::exists('hr_shifts', 'id')->where('company_id', $company)],
            'office_id' => ['nullable', Rule::exists('factories', 'id')->where('company_id', $company)],
            'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $company)],
            'issued_by' => ['nullable', Rule::exists('hr_employees', 'id')->where('company_id', $company)], 'remarks' => ['nullable', 'string', 'max:2000'],
        ] + $this->lineRules(['machine_id' => ['required', Rule::exists('knit_machines', 'id')->where('company_id', $company)],
            'qty' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'running_hrs' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:99999999']]), [
                'cons_date.required' => __('knitting.nd.v.date'), 'shift_id.required' => __('knitting.nd.v.shift'), 'store_id.required' => __('knitting.nd.v.store'),
                'lines.*.machine_id.required' => __('knitting.nd.v.line_machine'), 'lines.*.item_id.required' => __('knitting.nd.v.oil_line_item'),
                'lines.*.qty.required' => __('knitting.nd.v.line_qty'), 'lines.*.qty.gt' => __('knitting.nd.v.line_qty'),
            ]);
        $lines = $this->needLines($data, 'no_cons_lines');
        $date = CarbonImmutable::parse($data['cons_date'])->toDateString();
        $taken = KnitOilConsumption::query()->where('store_id', $data['store_id'])->whereDate('cons_date', $date)->where('shift_id', $data['shift_id'])
            ->when($row, fn ($q) => $q->whereKeyNot($row->id))->value('cons_no');
        if ($taken) {
            throw ValidationException::withMessages(['shift_id' => __('knitting.nd.v.cons_taken', ['no' => $taken])]);
        }
        $offices = NeedleStore::machineOffices();
        foreach ($lines as $i => $l) {
            if (! empty($data['office_id']) && ($offices[$l['machine_id']] ?? null) !== $data['office_id']) {
                throw ValidationException::withMessages(["lines.{$i}.machine_id" => __('knitting.nd.v.machine_office', ['n' => $i + 1])]);
            }
        }
        $row = DB::transaction(function () use ($row, $data, $lines, $date): KnitOilConsumption {
            $head = ['cons_date' => $date, 'shift_id' => $data['shift_id'], 'office_id' => ($data['office_id'] ?? '') ?: null, 'store_id' => $data['store_id'],
                'issued_by' => ($data['issued_by'] ?? '') ?: null, 'remarks' => ($data['remarks'] ?? '') ?: null];
            $row ??= KnitOilConsumption::query()->create($head + ['cons_no' => KnitProduction::nextNumber('OCS', 'knit_oil_consumptions', 'cons_no', $date),
                'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            $row->update($head);
            KnitOilConsumptionLine::query()->where('consumption_id', $row->id)->delete();
            foreach ($lines as $i => $l) {
                KnitOilConsumptionLine::query()->create(['company_id' => $row->company_id, 'consumption_id' => $row->id, 'sort_order' => $i + 1, 'machine_id' => $l['machine_id'],
                    'item_id' => $l['item_id'], 'uom' => UomCodes::normalize($l['uom'] ?? null) ?: null, 'qty' => round((float) $l['qty'], 4),
                    'running_hrs' => round((float) ($l['running_hrs'] ?? 0), 2), 'cost_price' => round((float) ($l['cost_price'] ?? 0), 4), 'remarks' => ($l['remarks'] ?? '') ?: null]);
            }

            return $row;
        });

        return $no ? ApiResponse::ok($this->presentConsumption($row->refresh())) : ApiResponse::created($this->presentConsumption($row->refresh()));
    }

    /** Finalize is the supervisor signing for the sheet: the oil leaves the store. */
    public function finalizeConsumption(string $no): JsonResponse
    {
        $this->allow('knitting.needle.manage');
        $row = KnitOilConsumption::query()->where('cons_no', $no)->firstOrFail();
        $this->assertStatus($row->cons_no, $row->status, ['DRAFT'], 'finalize', 'cons_status');
        if (! $row->issued_by) {
            throw ValidationException::withMessages(['issued_by' => __('knitting.nd.v.issued_by_first')]);
        }
        $date = $row->cons_date->toDateString();
        NeedleStore::assertOpen($row->store_id, $date, 'status', self::BOOK);
        $lines = KnitOilConsumptionLine::query()->where('consumption_id', $row->id)->get();
        $this->assertStock($row->store_id, $lines->groupBy('item_id')->map(fn ($m) => (float) $m->sum('qty')), $row->cons_no);
        DB::transaction(function () use ($row, $lines, $date): void {
            NeedleStore::post('CONSUME', $row->cons_no, $date, $lines->map(fn (KnitOilConsumptionLine $l) => [
                'store_id' => $row->store_id, 'item_id' => $l->item_id, 'qty' => -$l->qty, 'value' => -$l->qty * $l->cost_price, 'doc_line_id' => $l->id])->all(), self::BOOK);
            $row->update(['status' => 'FINALIZED', 'finalized_at' => now(), 'finalized_by' => $this->caller()->auditUserId()]);
        });

        return ApiResponse::ok($this->presentConsumption($row->refresh()));
    }

    /* ---------------- receipts ---------------- */

    public function receipts(): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');
        $rows = KnitOilReceipt::query()->orderByDesc('mrr_date')->orderByDesc('mrr_no')->limit(500)->get();
        $lines = KnitOilReceiptLine::query()->whereIn('receipt_id', $rows->pluck('id'))->get();
        $back = $this->returned($lines->pluck('id'));
        [$items, $stores] = NeedleStore::names();
        $parties = DB::table('mp_suppliers')->whereIn('id', $rows->pluck('supplier_id'))->pluck('name', 'id');
        $byRec = $lines->groupBy('receipt_id');

        return ApiResponse::ok($rows->map(function (KnitOilReceipt $r) use ($byRec, $back, $items, $stores, $parties) {
            $mine = $byRec[$r->id] ?? collect();

            return ['mrr_no' => $r->mrr_no, 'mrr_date' => $r->mrr_date->toDateString(), 'store_id' => $r->store_id, 'store' => $stores[$r->store_id] ?? null,
                'supplier_id' => $r->supplier_id, 'supplier' => $parties[$r->supplier_id] ?? null, 'challan_no' => $r->challan_no, 'req_type' => $r->req_type,
                'finalized' => $r->finalized_at !== null, 'accounted' => $r->accounted_at !== null,
                'items' => $mine->map(fn ($l) => $items[$l->item_id] ?? '')->implode(', '),
                'received_qty' => round((float) $mine->sum('received_qty'), 3), 'cond_qty' => round((float) $mine->sum('cond_qty'), 3),
                'returned' => round((float) $mine->sum(fn ($l) => $back[$l->id] ?? 0), 3),
                'returnable' => round((float) $mine->sum(fn ($l) => $l->received_qty - ($back[$l->id] ?? 0)), 3), 'status' => $r->status];
        })->values()->all());
    }

    public function showReceipt(string $no): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');

        return ApiResponse::ok($this->presentReceipt(KnitOilReceipt::query()->where('mrr_no', $no)->firstOrFail()));
    }

    public function saveReceipt(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.needle.manage');
        $row = $no ? KnitOilReceipt::query()->where('mrr_no', $no)->firstOrFail() : null;
        $row && $this->assertStatus($row->mrr_no, $row->status, ['DRAFT'], 'save', 'oil_receipt_status');
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'mrr_date' => ['required', 'date'], 'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $company)],
            'req_type' => ['nullable', Rule::in(YarnStore::REQ_TYPES)], 'supplier_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'challan_no' => ['nullable', 'string', 'max:60'], 'challan_date' => ['nullable', 'date'], 'pay_method' => ['required', Rule::in(KnitOilReceipt::PAY_METHODS)],
            'source' => ['required', 'string', 'max:80'], 'lc_no' => ['nullable', 'string', 'max:60'], 'remarks' => ['nullable', 'string', 'max:2000'],
        ] + $this->lineRules(['challan_qty' => ['nullable', 'numeric', 'min:0', 'max:99999999'], 'received_qty' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'cond_qty' => ['nullable', 'numeric', 'min:0', 'max:99999999'], 'rate' => ['nullable', 'numeric', 'min:0', 'max:99999999']]), [
                'mrr_date.required' => __('knitting.nd.v.mrr_date'), 'store_id.required' => __('knitting.nd.v.store'), 'supplier_id.required' => __('knitting.nd.v.supplier'),
                'pay_method.required' => __('knitting.nd.v.pay_method'), 'source.required' => __('knitting.nd.v.oil_source'),
                'lines.*.item_id.required' => __('knitting.nd.v.oil_line_item'), 'lines.*.received_qty.required' => __('knitting.nd.v.received_qty'),
                'lines.*.received_qty.gt' => __('knitting.nd.v.received_qty'),
            ]);
        if (! empty($data['lc_no']) && $data['pay_method'] !== 'LC') {
            throw ValidationException::withMessages(['lc_no' => __('knitting.nd.v.lc_only')]);
        }
        $lines = $this->needLines($data, 'no_oil_rcv_lines');
        foreach ($lines as $i => $l) {
            if ((float) ($l['cond_qty'] ?? 0) > (float) $l['received_qty'] + 0.00005) {
                throw ValidationException::withMessages(["lines.{$i}.cond_qty" => __('knitting.nd.v.cond_over', ['n' => $i + 1])]);
            }
        }
        $row = DB::transaction(function () use ($row, $data, $lines): KnitOilReceipt {
            $head = collect($data)->except('lines')->map(fn ($v) => $v === '' ? null : $v)->all()
                + ['req_type' => null, 'challan_no' => null, 'challan_date' => null, 'lc_no' => null, 'remarks' => null];
            $row ??= KnitOilReceipt::query()->create($head + ['mrr_no' => KnitProduction::nextNumber('OMR', 'knit_oil_receipts', 'mrr_no', $data['mrr_date']),
                'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            $row->update($head);
            KnitOilReceiptLine::query()->where('receipt_id', $row->id)->delete();
            foreach ($lines as $i => $l) {
                $qty = round((float) $l['received_qty'], 4);
                $rate = round((float) ($l['rate'] ?? 0), 4);
                KnitOilReceiptLine::query()->create(['company_id' => $row->company_id, 'receipt_id' => $row->id, 'sort_order' => $i + 1, 'item_id' => $l['item_id'],
                    'uom' => UomCodes::normalize($l['uom'] ?? null) ?: null, 'challan_qty' => round((float) ($l['challan_qty'] ?? 0), 4), 'received_qty' => $qty,
                    'cond_qty' => round((float) ($l['cond_qty'] ?? 0), 4), 'rate' => $rate, 'amount' => round($qty * $rate, 4), 'remarks' => ($l['remarks'] ?? '') ?: null]);
            }

            return $row;
        });

        return $no ? ApiResponse::ok($this->presentReceipt($row->refresh())) : ApiResponse::created($this->presentReceipt($row->refresh()));
    }

    public function receiptTransition(string $no, string $action): JsonResponse
    {
        $this->allow('knitting.needle.manage');
        $row = KnitOilReceipt::query()->where('mrr_no', $no)->firstOrFail();
        $final = $row->finalized_at !== null;
        if (! in_array($action, ['next', 'finalize', 'accounting', 'cancel'], true)) {
            abort(404);
        }
        // Finalized, it posted: it can no longer be finalized again or cancelled.
        if ($final && in_array($action, ['finalize', 'cancel'], true)) {
            throw ValidationException::withMessages(['status' => __('knitting.nd.v.oil_final', ['no' => $row->mrr_no, 'action' => __("knitting.nd.action.{$action}")])]);
        }
        if ($action === 'accounting' && (! $final || $row->accounted_at)) {
            throw ValidationException::withMessages(['status' => __($final ? 'knitting.nd.v.accounted' : 'knitting.nd.v.accounting_needs_final', ['no' => $row->mrr_no])]);
        }
        $this->assertStatus($row->mrr_no, $row->status, match ($action) {
            'next' => array_keys(KnitOilReceipt::DESKS),
            'finalize' => ['WAITING', 'WAIT_ACCOUNTS'],
            'accounting' => ['WAIT_ACCOUNTS', 'APPROVED'],
            'cancel' => ['DRAFT', 'WAIT_QC', 'WAITING', 'WAIT_ACCOUNTS'],
        }, $action, 'oil_receipt_status');
        if ($action === 'next' && $row->status === 'WAIT_ACCOUNTS' && ! $final) {
            throw ValidationException::withMessages(['status' => __('knitting.nd.v.finalize_first', ['no' => $row->mrr_no])]);
        }
        if ($action === 'next' && $row->status === 'APPROVED' && ! $row->accounted_at) {
            throw ValidationException::withMessages(['status' => __('knitting.nd.v.accounting_first', ['no' => $row->mrr_no])]);
        }
        DB::transaction(function () use ($row, $action): void {
            $me = $this->caller()->auditUserId();
            match ($action) {
                'next' => $row->update(['status' => KnitOilReceipt::DESKS[$row->status]]),
                'accounting' => $row->update(['accounted_at' => now(), 'accounted_by' => $me]),
                'cancel' => $row->update(['status' => 'CANCELLED']),
                'finalize' => (function () use ($row, $me): void {
                    // Finalize is the store saying the lines are right: the receipt posts.
                    NeedleStore::assertOpen($row->store_id, $row->mrr_date->toDateString(), 'status', self::BOOK);
                    NeedleStore::post('RECEIPT', $row->mrr_no, $row->mrr_date->toDateString(), KnitOilReceiptLine::query()->where('receipt_id', $row->id)->get()
                        ->map(fn (KnitOilReceiptLine $l) => ['store_id' => $row->store_id, 'item_id' => $l->item_id, 'qty' => $l->received_qty, 'value' => $l->amount, 'doc_line_id' => $l->id])->all(), self::BOOK);
                    $row->update(['finalized_at' => now(), 'finalized_by' => $me]);
                })(),
            };
        });

        return ApiResponse::ok($this->presentReceipt($row->refresh()));
    }

    /** A finalized receipt's lines with what is still returnable: what a return answers. */
    public function receiptSource(Request $request, string $no): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');
        $r = KnitOilReceipt::query()->where('mrr_no', $no)->whereNotNull('finalized_at')->where('status', '!=', 'CANCELLED')->firstOrFail();
        $except = $request->query('challan') ? KnitOilReturn::query()->where('challan_no', $request->query('challan'))->value('id') : null;
        $lines = KnitOilReceiptLine::query()->where('receipt_id', $r->id)->orderBy('sort_order')->get();
        $back = $this->returned($lines->pluck('id'), $except);
        [$items, $stores] = NeedleStore::names();

        return ApiResponse::ok(['mrr_no' => $r->mrr_no, 'store_id' => $r->store_id, 'store' => $stores[$r->store_id] ?? null, 'supplier_id' => $r->supplier_id,
            'supplier' => DB::table('mp_suppliers')->where('id', $r->supplier_id)->value('name'),
            'lines' => $lines->map(fn (KnitOilReceiptLine $l) => ['receipt_line_id' => $l->id, 'item_id' => $l->item_id, 'item' => $items[$l->item_id] ?? null, 'uom' => $l->uom,
                'received' => round($l->received_qty, 3), 'back' => round((float) ($back[$l->id] ?? 0), 3), 'left' => round($l->received_qty - (float) ($back[$l->id] ?? 0), 3),
                'rate' => round($l->rate, 4)])->values()->all()]);
    }

    /* ---------------- receipt returns ---------------- */

    public function returns(): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');
        $rows = KnitOilReturn::query()->orderByDesc('challan_date')->orderByDesc('challan_no')->limit(500)->get();
        $sums = KnitOilReturnLine::query()->whereIn('return_id', $rows->pluck('id'))->groupBy('return_id')->selectRaw('return_id, sum(qty) as q, sum(amount) as a')->get()->keyBy('return_id');
        $mrr = KnitOilReceipt::query()->whereIn('id', $rows->pluck('receipt_id'))->pluck('mrr_no', 'id');
        [, $stores] = NeedleStore::names();
        $parties = DB::table('mp_suppliers')->whereIn('id', $rows->pluck('supplier_id'))->pluck('name', 'id');

        return ApiResponse::ok($rows->map(fn (KnitOilReturn $r) => [
            'challan_no' => $r->challan_no, 'challan_date' => $r->challan_date->toDateString(), 'mrr_no' => $mrr[$r->receipt_id] ?? null, 'challan_type' => $r->challan_type,
            'store_id' => $r->store_id, 'store' => $stores[$r->store_id] ?? null, 'supplier_id' => $r->supplier_id, 'supplier' => $parties[$r->supplier_id] ?? null,
            'qty' => round((float) ($sums[$r->id]->q ?? 0), 3), 'amount' => round((float) ($sums[$r->id]->a ?? 0), 2), 'finalized' => $r->status === 'FINALIZED', 'status' => $r->status,
        ])->values()->all());
    }

    public function showReturn(string $no): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');

        return ApiResponse::ok($this->presentReturn(KnitOilReturn::query()->where('challan_no', $no)->firstOrFail()));
    }

    public function saveReturn(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.needle.manage');
        $row = $no ? KnitOilReturn::query()->where('challan_no', $no)->firstOrFail() : null;
        $row && $this->assertStatus($row->challan_no, $row->status, ['DRAFT'], 'save', 'return_status');
        $data = $request->validate([
            'mrr_no' => ['required', 'string', 'max:24'], 'challan_no' => ['required', 'string', 'max:32', 'regex:/^[^\\/?#]+$/'], 'challan_date' => ['required', 'date'],
            'challan_type' => ['required', Rule::in(KnitOilReturn::TYPES)], 'gate_pass_no' => ['nullable', 'string', 'max:40'], 'vehicle_no' => ['nullable', 'string', 'max:40'],
            'reason' => ['nullable', 'string', 'max:2000'], 'lines' => ['present', 'array', 'max:300'], 'lines.*.receipt_line_id' => ['nullable', 'string'],
            'lines.*.qty' => ['nullable', 'numeric', 'min:0', 'max:99999999'], 'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], ['mrr_no.required' => __('knitting.nd.v.oil_mrr'), 'challan_no.required' => __('knitting.nd.v.rtn_challan'), 'challan_no.regex' => __('knitting.nd.v.rtn_challan_chars'),
            'challan_date.required' => __('knitting.nd.v.challan_date'), 'challan_type.required' => __('knitting.nd.v.return_type')]);
        $rec = KnitOilReceipt::query()->where('mrr_no', $data['mrr_no'])->whereNotNull('finalized_at')->where('status', '!=', 'CANCELLED')->first();
        if (! $rec || ($row && $row->receipt_id !== $rec->id)) {
            throw ValidationException::withMessages(['mrr_no' => __('knitting.nd.v.oil_mrr')]);
        }
        $src = KnitOilReceiptLine::query()->where('receipt_id', $rec->id)->get()->keyBy('id');
        $back = $this->returned($src->keys(), $row?->id);
        $lines = [];
        foreach (YarnStore::inOrder($data['lines']) as $i => $l) {
            $s = $src[$l['receipt_line_id'] ?? ''] ?? null;
            if (! $s) {
                throw ValidationException::withMessages(["lines.{$i}.receipt_line_id" => __('knitting.nd.v.return_line', ['n' => $i + 1])]);
            }
            $qty = round((float) ($l['qty'] ?? 0), 4);
            if ($qty <= 0) {
                throw ValidationException::withMessages(["lines.{$i}.qty" => __('knitting.nd.v.line_qty')]);
            }
            $left = round($s->received_qty - (float) ($back[$s->id] ?? 0) - collect($lines)->where('receipt_line_id', $s->id)->sum('qty'), 4);
            if ($qty > $left + 0.00005) {
                throw ValidationException::withMessages(["lines.{$i}.qty" => __('knitting.nd.v.oil_return_over', ['n' => $i + 1, 'left' => $left])]);
            }
            $lines[] = ['receipt_line_id' => $s->id, 'item_id' => $s->item_id, 'uom' => $s->uom, 'qty' => $qty, 'rate' => $s->rate, 'amount' => round($qty * $s->rate, 4),
                'remarks' => ($l['remarks'] ?? '') ?: null];
        }
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('knitting.nd.v.no_oil_rtn_lines')]);
        }
        if (KnitOilReturn::query()->where('challan_no', $data['challan_no'])->when($row, fn ($q) => $q->whereKeyNot($row->id))->exists()) {
            throw ValidationException::withMessages(['challan_no' => __('knitting.nd.v.return_taken', ['no' => $data['challan_no']])]);
        }
        $row = DB::transaction(function () use ($row, $data, $rec, $lines): KnitOilReturn {
            $head = ['challan_no' => $data['challan_no'], 'receipt_id' => $rec->id, 'challan_date' => $data['challan_date'], 'challan_type' => $data['challan_type'],
                'store_id' => $rec->store_id, 'supplier_id' => $rec->supplier_id, 'gate_pass_no' => ($data['gate_pass_no'] ?? '') ?: null,
                'vehicle_no' => ($data['vehicle_no'] ?? '') ?: null, 'reason' => ($data['reason'] ?? '') ?: null];
            $row ??= KnitOilReturn::query()->create($head + ['status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            $row->update($head);
            KnitOilReturnLine::query()->where('return_id', $row->id)->delete();
            foreach ($lines as $i => $l) {
                KnitOilReturnLine::query()->create($l + ['company_id' => $row->company_id, 'return_id' => $row->id, 'sort_order' => $i + 1]);
            }

            return $row;
        });

        return $no ? ApiResponse::ok($this->presentReturn($row->refresh())) : ApiResponse::created($this->presentReturn($row->refresh()));
    }

    public function returnTransition(string $no, string $action): JsonResponse
    {
        $this->allow('knitting.needle.manage');
        $row = KnitOilReturn::query()->where('challan_no', $no)->firstOrFail();
        [$from, $to] = match ($action) {
            'issue' => [['DRAFT'], 'ISSUED'],
            'finalize' => [['ISSUED'], 'FINALIZED'],
            // Re-open steps back one: a finalized challan to Issued, an issued one to Draft (its posting undone).
            'reopen' => [['ISSUED', 'FINALIZED'], $row->status === 'FINALIZED' ? 'ISSUED' : 'DRAFT'],
            'cancel' => [['DRAFT'], 'CANCELLED'],
            default => abort(404),
        };
        $this->assertStatus($row->challan_no, $row->status, $from, $action, 'return_status');
        DB::transaction(function () use ($row, $action, $to): void {
            $date = $row->challan_date->toDateString();
            if ($action === 'issue') {
                // Issue is what sends the drums out and takes them off the store.
                NeedleStore::assertOpen($row->store_id, $date, 'status', self::BOOK);
                $lines = KnitOilReturnLine::query()->where('return_id', $row->id)->get();
                $this->assertStock($row->store_id, $lines->groupBy('item_id')->map(fn ($m) => (float) $m->sum('qty')), $row->challan_no);
                NeedleStore::post('RETURN', $row->challan_no, $date, $lines->map(fn (KnitOilReturnLine $l) => [
                    'store_id' => $row->store_id, 'item_id' => $l->item_id, 'qty' => -$l->qty, 'value' => -$l->amount, 'doc_line_id' => $l->id])->all(), self::BOOK);
            }
            if ($action === 'reopen' && $to === 'DRAFT') {
                NeedleStore::assertOpen($row->store_id, $date, 'status', self::BOOK);
                NeedleStore::unpost('RETURN', $row->challan_no);
            }
            $row->update(['status' => $to]);
        });

        return ApiResponse::ok($this->presentReturn($row->refresh()));
    }

    /* ---------------- helpers ---------------- */

    /** Once verified only Issue Qty changes: what is written is released from the store asked into the store asking. */
    private function writeIssueQty(Request $request, KnitOilReq $row): JsonResponse
    {
        $data = $request->validate(['lines' => ['required', 'array', 'max:300'], 'lines.*.id' => ['required', 'string'],
            'lines.*.issue_qty' => ['nullable', 'numeric', 'min:0', 'max:99999999']]);
        $lines = KnitOilReqLine::query()->where('req_id', $row->id)->get()->keyBy('id');
        foreach (YarnStore::inOrder($data['lines']) as $i => $l) {
            $line = $lines[$l['id']] ?? null;
            if (! $line) {
                throw ValidationException::withMessages(["lines.{$i}.id" => __('knitting.nd.v.issue_line', ['n' => $i + 1])]);
            }
            $qty = round((float) ($l['issue_qty'] ?? 0), 4);
            if ($qty > $line->required_qty + 0.00005) {
                throw ValidationException::withMessages(["lines.{$i}.issue_qty" => __('knitting.nd.v.loan_over', ['n' => $i + 1, 'qty' => round($line->required_qty, 3)])]);
            }
            $line->issue_qty = $qty;
        }
        DB::transaction(function () use ($row, $lines): void {
            $this->postIssue($row, $lines->values());
            $lines->each->save();
            $out = (float) $lines->sum('issue_qty');
            $row->update(['status' => $out <= 0 ? 'VERIFIED' : ($out + 0.00005 >= (float) $lines->sum('required_qty') ? 'ISSUED' : 'PARTLY_ISSUED')]);
        });

        return ApiResponse::ok($this->presentReq($row->refresh()));
    }

    /** The indent's release, replacing what it posted before: out of the store asked, into the store asking. */
    private function postIssue(KnitOilReq $row, Collection $lines): void
    {
        $date = $row->req_date->toDateString();
        NeedleStore::assertOpen($row->to_store_id, $date, 'status', self::BOOK);
        NeedleStore::assertOpen($row->from_store_id, $date, 'status', self::BOOK);
        $this->assertStock($row->to_store_id, $lines->groupBy('item_id')->map(fn ($m) => (float) $m->sum('issue_qty')), $row->req_no);
        $entries = fn (string $store, int $sign) => $lines->map(fn (KnitOilReqLine $l) => ['store_id' => $store, 'item_id' => $l->item_id, 'qty' => $sign * $l->issue_qty,
            'value' => $sign * $l->issue_qty * $l->rate, 'doc_line_id' => $l->id])->all();
        NeedleStore::post('ISSUE_OUT', $row->req_no, $date, $entries($row->to_store_id, -1), self::BOOK);
        NeedleStore::post('ISSUE_IN', $row->req_no, $date, $entries($row->from_store_id, 1), self::BOOK);
    }

    /** What a paper takes out of a store, item by item, never more than the store holds (its own earlier moves aside). */
    private function assertStock(string $storeId, Collection $byItem, string $docNo): void
    {
        [$items] = NeedleStore::names();
        foreach ($byItem as $item => $qty) {
            $have = NeedleStore::balance($storeId, $item, null, $docNo);
            if ($qty > $have + 0.00005) {
                throw ValidationException::withMessages(['status' => __('knitting.nd.v.stock_short', ['item' => $items[$item] ?? '', 'have' => $have])]);
            }
        }
    }

    /** @return array<string, mixed> */
    private function lineRules(array $qty): array
    {
        $company = app(TenantContext::class)->companyId();

        return ['lines' => ['present', 'array', 'max:300'], 'lines.*.item_id' => ['required', Rule::exists('gs_items', 'id')->where('company_id', $company)],
            'lines.*.uom' => ['nullable', 'string', 'max:16', UomCodes::rule($company)], 'lines.*.remarks' => ['nullable', 'string', 'max:300']]
            + collect($qty)->mapWithKeys(fn ($r, $k) => ["lines.*.{$k}" => $r])->all();
    }

    private function needLines(array $data, string $msg): array
    {
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __("knitting.nd.v.{$msg}")]);
        }

        return $lines;
    }

    private function assertStatus(string $no, string $status, array $from, string $action, string $group): void
    {
        if (! in_array($status, $from, true)) {
            throw ValidationException::withMessages(['status' => __('knitting.nd.v.cannot', [
                'no' => $no, 'status' => __("knitting.nd.{$group}.{$status}"), 'action' => __("knitting.nd.action.{$action}"),
            ])]);
        }
    }

    /** @return Collection<string, float> */
    private function returned($lineIds, ?string $exceptReturnId = null): Collection
    {
        $live = KnitOilReturn::query()->where('status', '!=', 'CANCELLED')->when($exceptReturnId, fn ($q) => $q->whereKeyNot($exceptReturnId))->select('id');

        return KnitOilReturnLine::query()->whereIn('receipt_line_id', collect($lineIds)->values())->whereIn('return_id', $live)
            ->groupBy('receipt_line_id')->selectRaw('receipt_line_id, sum(qty) as q')->pluck('q', 'receipt_line_id')->map(fn ($q) => round((float) $q, 4));
    }

    /** @return array<string, mixed> */
    private function presentReq(KnitOilReq $r): array
    {
        $lines = KnitOilReqLine::query()->where('req_id', $r->id)->orderBy('sort_order')->get();
        [$items, $stores] = NeedleStore::names();
        $cats = DB::table('gs_items')->whereIn('id', $lines->pluck('item_id'))->pluck('gs_category_id', 'id');

        return collect($r->only(['id', 'req_no', 'req_type', 'office_id', 'department_id', 'from_store_id', 'to_store_id', 'reason', 'remarks', 'status']))->all() + [
            'req_date' => $r->req_date->toDateString(), 'from_store' => $stores[$r->from_store_id] ?? null, 'to_store' => $stores[$r->to_store_id] ?? null,
            'office' => $r->office_id ? DB::table('factories')->where('id', $r->office_id)->value('name') : null,
            'department' => $r->department_id ? DB::table('departments')->where('id', $r->department_id)->value('name') : null,
            'company' => DB::table('companies')->where('id', $r->company_id)->value('name'),
            'lines' => $lines->map(fn (KnitOilReqLine $l) => collect($l->only(['id', 'item_id', 'uom', 'remarks']))->all() + [
                'category_id' => $cats[$l->item_id] ?? null, 'item' => $items[$l->item_id] ?? null, 'required_qty' => round($l->required_qty, 3),
                'issue_qty' => round($l->issue_qty, 3), 'rate' => round($l->rate, 4)])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentOpening(KnitOilOpening $o): array
    {
        $item = DB::table('gs_items')->where('id', $o->item_id)->first(['item_code', 'name', 'gs_category_id']);
        $store = DB::table('stores')->where('id', $o->store_id)->first(['name', 'code']);

        return collect($o->only(['id', 'store_id', 'item_id', 'uom', 'currency_id', 'remarks', 'finalized']))->all() + [
            'ob_date' => $o->ob_date->toDateString(), 'company' => DB::table('companies')->where('id', $o->company_id)->value('name'),
            'store' => $store?->name, 'store_code' => $store?->code, 'item' => $item ? "{$item->item_code} · {$item->name}" : null, 'item_code' => $item?->item_code,
            'category_id' => $item?->gs_category_id, 'currency' => $o->currency_id ? DB::table('currencies')->where('id', $o->currency_id)->value('code') : null,
            'qty' => round($o->qty, 3), 'unit_price' => round($o->unit_price, 4), 'conv_rate' => round($o->conv_rate, 4), 'cost_price' => round($o->cost_price, 4),
            'value' => round($o->qty * $o->cost_price, 2),
        ];
    }

    /** @return array<string, mixed> */
    private function presentConsumption(KnitOilConsumption $r): array
    {
        $lines = KnitOilConsumptionLine::query()->where('consumption_id', $r->id)->orderBy('sort_order')->get();
        [$items, $stores] = NeedleStore::names();
        $cats = DB::table('gs_items')->whereIn('id', $lines->pluck('item_id'))->pluck('gs_category_id', 'id');
        $machines = DB::table('knit_machines')->whereIn('id', $lines->pluck('machine_id'))->pluck('machine_no', 'id');

        return collect($r->only(['id', 'cons_no', 'shift_id', 'office_id', 'store_id', 'issued_by', 'remarks', 'status']))->all() + [
            'cons_date' => $r->cons_date->toDateString(), 'store' => $stores[$r->store_id] ?? null, 'shift' => DB::table('hr_shifts')->where('id', $r->shift_id)->value('name'),
            'office' => $r->office_id ? DB::table('factories')->where('id', $r->office_id)->value('name') : null,
            'issuer' => $r->issued_by ? DB::table('hr_employees')->where('id', $r->issued_by)->value('name') : null,
            'company' => DB::table('companies')->where('id', $r->company_id)->value('name'),
            'lines' => $lines->map(fn (KnitOilConsumptionLine $l) => collect($l->only(['machine_id', 'item_id', 'uom', 'remarks']))->all() + [
                'machine' => $machines[$l->machine_id] ?? null, 'category_id' => $cats[$l->item_id] ?? null, 'item' => $items[$l->item_id] ?? null,
                'qty' => round($l->qty, 3), 'running_hrs' => round($l->running_hrs, 2), 'cost_price' => round($l->cost_price, 4)])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentReceipt(KnitOilReceipt $r): array
    {
        $lines = KnitOilReceiptLine::query()->where('receipt_id', $r->id)->orderBy('sort_order')->get();
        $back = $this->returned($lines->pluck('id'));
        [$items, $stores] = NeedleStore::names();
        $cats = DB::table('gs_items')->whereIn('id', $lines->pluck('item_id'))->pluck('gs_category_id', 'id');
        $party = DB::table('mp_suppliers')->where('id', $r->supplier_id)->first(['name', 'address']);

        return collect($r->only(['id', 'mrr_no', 'store_id', 'req_type', 'supplier_id', 'challan_no', 'source', 'pay_method', 'lc_no', 'remarks', 'status']))->all() + [
            'mrr_date' => $r->mrr_date->toDateString(), 'challan_date' => $r->challan_date?->toDateString(), 'store' => $stores[$r->store_id] ?? null,
            'finalized' => $r->finalized_at !== null, 'accounted' => $r->accounted_at !== null,
            'supplier' => $party?->name, 'supplier_address' => $party?->address, 'company' => DB::table('companies')->where('id', $r->company_id)->value('name'),
            'lines' => $lines->map(fn (KnitOilReceiptLine $l) => collect($l->only(['item_id', 'uom', 'remarks']))->all() + [
                'category_id' => $cats[$l->item_id] ?? null, 'item' => $items[$l->item_id] ?? null, 'challan_qty' => round($l->challan_qty, 3),
                'received_qty' => round($l->received_qty, 3), 'cond_qty' => round($l->cond_qty, 3), 'rate' => round($l->rate, 4), 'amount' => round($l->amount, 2),
                'returned' => round((float) ($back[$l->id] ?? 0), 3)])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentReturn(KnitOilReturn $r): array
    {
        $lines = KnitOilReturnLine::query()->where('return_id', $r->id)->orderBy('sort_order')->get();
        $src = KnitOilReceiptLine::query()->whereIn('id', $lines->pluck('receipt_line_id'))->get()->keyBy('id');
        $back = $this->returned($lines->pluck('receipt_line_id'), $r->id);
        [$items, $stores] = NeedleStore::names();
        $party = DB::table('mp_suppliers')->where('id', $r->supplier_id)->first(['name', 'address']);

        return collect($r->only(['id', 'challan_no', 'challan_type', 'store_id', 'supplier_id', 'gate_pass_no', 'vehicle_no', 'reason', 'status']))->all() + [
            'challan_date' => $r->challan_date->toDateString(), 'mrr_no' => KnitOilReceipt::query()->whereKey($r->receipt_id)->value('mrr_no'), 'store' => $stores[$r->store_id] ?? null,
            'supplier' => $party?->name, 'supplier_address' => $party?->address, 'company' => DB::table('companies')->where('id', $r->company_id)->value('name'),
            'lines' => $lines->map(fn (KnitOilReturnLine $l) => ['receipt_line_id' => $l->receipt_line_id, 'item_id' => $l->item_id, 'item' => $items[$l->item_id] ?? null, 'uom' => $l->uom,
                'received' => round((float) ($src[$l->receipt_line_id]->received_qty ?? 0), 3), 'back' => round((float) ($back[$l->receipt_line_id] ?? 0), 3),
                'qty' => round($l->qty, 3), 'rate' => round($l->rate, 4), 'amount' => round($l->amount, 2), 'remarks' => $l->remarks])->values()->all(),
        ];
    }
}
