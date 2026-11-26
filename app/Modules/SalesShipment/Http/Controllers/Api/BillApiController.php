<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Http\Controllers\Api;

use App\Modules\SalesShipment\Models\SsBill;
use App\Modules\SalesShipment\Models\SsBillLine;
use App\Modules\SalesShipment\Models\SsChallan;
use App\Modules\SalesShipment\Support\SalesBook;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Sales & Shipment → Order & Delivery → Delivery Bill Verification
 * (AnnotechERP 21/02): the check bill of what a customer has taken — the
 * lines of its delivered challans, never billed beyond what was delivered
 * less what another bill has claimed, at a rate and its BDT rate, less a
 * deduction. Saved as a draft or submitted for approval; verified, approved
 * (finalized: the quantities lock against the challans) and marked paid —
 * or rejected back to the desk.
 */
class BillApiController extends ApiController
{
    private ?SalesBook $book = null;

    public function index(): JsonResponse
    {
        $this->allow('salesshipment.bill.view');
        $b = $this->book();
        $rows = SsBill::query()->orderByDesc('bill_date')->orderByDesc('bill_no')->get();
        $lines = DB::table('ss_bill_lines as bl')->join('ss_challan_lines as cl', 'cl.id', '=', 'bl.challan_line_id')->join('ss_challans as c', 'c.id', '=', 'cl.challan_id')
            ->whereIn('bl.bill_id', $rows->pluck('id'))->get(['bl.bill_id', 'bl.qty', 'bl.rate', 'bl.exchange_rate', 'c.delivery_target_date']);
        $names = $b->customerNames();
        $company = $b->company();

        return ApiResponse::ok($rows->map(function (SsBill $r) use ($lines, $names, $company, $b) {
            $mine = $lines->where('bill_id', $r->id);
            $gross = round((float) $mine->sum(fn ($l) => $l->qty * $l->rate), 2);
            $bdt = round((float) $mine->sum(fn ($l) => $l->qty * $l->rate * $l->exchange_rate), 2);

            return [
                'bill_no' => $r->bill_no, 'customer' => $names[$r->customer_id] ?? '—', 'customer_id' => $r->customer_id, 'company' => $company, 'bill_date' => $r->bill_date->toDateString(),
                'target_date' => $mine->max('delivery_target_date'), 'finalized' => in_array($r->state, SsBill::FINAL, true), 'gross_amount' => $gross, 'deduction' => (float) $r->deduction,
                'net_amount' => round($gross - (float) $r->deduction, 2), 'net_amount_bdt' => round($bdt - (float) $r->deduction * (float) $r->loc_exchange_rate, 2),
                'verified_by' => $b->trail($r->verified_by, $r->verified_at), 'state' => $r->state,
            ];
        })->values()->all());
    }

    public function show(string $no): JsonResponse
    {
        $this->allow('salesshipment.bill.view');

        return ApiResponse::ok($this->present($this->find($no)));
    }

    /** The Receive Challan List on the left: delivered challans with what is still unbilled. */
    public function challans(): JsonResponse
    {
        $this->allow('salesshipment.bill.view');
        $rows = SsChallan::query()->where('state', 'DELIVERED')->orderByDesc('chalan_date')->orderByDesc('chalan_no')->get();
        $lines = DB::table('ss_challan_lines')->whereIn('challan_id', $rows->pluck('id'))->get(['id', 'challan_id']);
        $fig = $this->book()->lineFigures($lines->pluck('id')->all());
        $orders = DB::table('ss_sales_orders')->whereIn('id', $rows->pluck('order_id'))->pluck('so_no', 'id');
        $names = $this->book()->customerNames();

        return ApiResponse::ok($rows->map(fn (SsChallan $c) => [
            'chalan_no' => $c->chalan_no, 'so_no' => $orders[$c->order_id] ?? '—', 'customer' => $names[$c->customer_id] ?? '—', 'customer_id' => $c->customer_id, 'currency_id' => $c->currency_id,
            'unbilled' => max(0.0, round((float) $lines->where('challan_id', $c->id)->sum(fn ($l) => $fig[$l->id]['delivered'] - $fig[$l->id]['billed']), 4)),
        ])->values()->all());
    }

    /** A delivered challan's lines as they drop into a bill. */
    public function source(Request $request, string $chalan): JsonResponse
    {
        $this->allow('salesshipment.bill.view');
        $c = SsChallan::query()->where('chalan_no', $chalan)->firstOrFail();
        $except = $request->query('except') ? SsBill::query()->where('bill_no', $request->query('except'))->value('id') : null;

        return ApiResponse::ok($this->sourceOf([$c->id], $except));
    }

    public function save(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('salesshipment.bill.manage');
        $row = $no ? $this->find($no) : null;
        if ($row && ! in_array($row->state, SsBill::EDITABLE, true)) {
            throw ValidationException::withMessages(['bill_no' => __('salesshipment.v.locked', ['no' => $row->bill_no, 'status' => __('salesshipment.cb.state.'.$row->state)])]);
        }
        $c = $this->caller()->companyId;
        $data = $request->validate([
            'customer_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $c)], 'currency_id' => ['required', Rule::exists('currencies', 'id')->where('company_id', $c)],
            'loc_exchange_rate' => ['required', 'numeric', 'gt:0'], 'bill_date' => ['required', 'date'], 'deduction' => ['nullable', 'numeric', 'min:0'], 'remarks' => ['nullable', 'string', 'max:2000'],
            'action_status' => ['required', Rule::in(['68', '69'])],
            'lines' => ['nullable', 'array'], 'lines.*.challan_line_id' => ['required', 'string', 'max:26', 'distinct'], 'lines.*.qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.rate' => ['nullable', 'numeric', 'min:0'], 'lines.*.exchange_rate' => ['nullable', 'numeric', 'gt:0'],
        ], ['lines.*.challan_line_id.distinct' => __('salesshipment.v.line_twice'), 'action_status.required' => __('salesshipment.v.cb_action')]);
        $this->book()->assertOpen($data['bill_date'], 'bill_date');
        $lines = collect(array_values($data['lines'] ?? []))->filter(fn ($l) => (float) ($l['qty'] ?? 0) > 0)->values();
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('salesshipment.v.cb_lines')]);
        }
        $challanIds = DB::table('ss_challan_lines')->whereIn('id', $lines->pluck('challan_line_id'))->pluck('challan_id')->unique()->values()->all();
        $src = collect($this->sourceOf($challanIds, $row?->id))->keyBy('challan_line_id');
        foreach ($lines as $i => $l) {
            $s = $src[$l['challan_line_id']] ?? null;
            if (! $s || $s['state'] !== 'DELIVERED') {
                throw ValidationException::withMessages(["lines.{$i}.challan_line_id" => __('salesshipment.v.cb_not_delivered')]);
            }
            if ($s['customer_id'] !== $data['customer_id']) {
                throw ValidationException::withMessages(["lines.{$i}.challan_line_id" => __('salesshipment.v.cb_other_customer', ['no' => $s['chalan_no']])]);
            }
            if ((float) $l['qty'] > $s['billable'] + 0.00005) {
                throw ValidationException::withMessages(["lines.{$i}.qty" => __('salesshipment.v.cb_over', ['line' => $s['label'], 'max' => $this->num($s['billable'])])]);
            }
        }
        $gross = $lines->sum(fn ($l) => (float) $l['qty'] * (float) ($l['rate'] ?? $src[$l['challan_line_id']]['rate']));
        $deduction = (float) ($data['deduction'] ?? 0);
        if ($deduction > $gross + 0.005) {
            throw ValidationException::withMessages(['deduction' => __('salesshipment.v.cb_deduction', ['gross' => number_format($gross, 2)])]);
        }
        $submit = $data['action_status'] === '69';
        $by = $this->caller()->auditUserId();
        $row = DB::transaction(function () use ($row, $data, $lines, $src, $deduction, $submit, $by) {
            $head = ['customer_id' => $data['customer_id'], 'currency_id' => $data['currency_id'], 'loc_exchange_rate' => $data['loc_exchange_rate'], 'bill_date' => $data['bill_date'],
                'deduction' => $deduction, 'state' => $submit ? 'WAITING_FOR_APPROVAL' : 'DRAFT', 'reject_reason' => null,
                ...($submit ? ['submitted_at' => now(), 'submitted_by' => $by] : ['submitted_at' => null, 'submitted_by' => null])];
            if ($row) {
                $row->update($head);
                SsBillLine::query()->where('bill_id', $row->id)->delete();
            } else {
                $row = SsBill::query()->create($head + ['bill_no' => $this->book()->nextNo('CB', 'ss_bills', 'bill_no', $data['bill_date']), 'created_by' => $by]);
            }
            foreach ($lines as $i => $l) {
                SsBillLine::query()->create(['bill_id' => $row->id, 'challan_line_id' => $l['challan_line_id'], 'qty' => $l['qty'], 'rate' => $l['rate'] ?? $src[$l['challan_line_id']]['rate'],
                    'exchange_rate' => $l['exchange_rate'] ?? $data['loc_exchange_rate'], 'sort_order' => $i]);
            }
            $sections = $lines->pluck('challan_line_id')->map(fn ($id) => $src[$id]['section'] ?? null)->filter()->unique()->implode(', ');
            $row->update(['remarks' => $data['remarks'] ? strtr($data['remarks'], ['[BILL_NO]' => $row->bill_no, '[BILL_DATE]' => $row->bill_date->format('d-M-Y'), '[SECTION]' => $sections ?: '—']) : null]);

            return $row;
        });

        return ApiResponse::ok($this->present($row->refresh()), [], $no ? 200 : 201);
    }

    /** Verify, Approve (finalize), Mark Paid, Reject. */
    public function act(Request $request, string $no, string $action): JsonResponse
    {
        $from = ['verify' => ['WAITING_FOR_APPROVAL', 'VERIFIED'], 'approve' => ['VERIFIED', 'APPROVED'], 'pay' => ['APPROVED', 'PAID'], 'reject' => [null, 'REJECTED']];
        abort_unless(isset($from[$action]), 404);
        $this->allow('salesshipment.bill.approve');
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:300']]);
        $row = DB::transaction(function () use ($no, $action, $from, $data) {
            $row = SsBill::query()->where('bill_no', $no)->lockForUpdate()->firstOrFail();
            $allowed = $action === 'reject' ? ['WAITING_FOR_APPROVAL', 'VERIFIED'] : [$from[$action][0]];
            if (! in_array($row->state, $allowed, true)) {
                throw ValidationException::withMessages(['status' => __('salesshipment.v.step', ['no' => $row->bill_no, 'status' => __('salesshipment.cb.state.'.$row->state)])]);
            }
            $this->book()->assertOpen($row->bill_date, 'status');
            $col = ['verify' => 'verified', 'approve' => 'approved', 'pay' => 'paid', 'reject' => 'rejected'][$action];
            $row->update(['state' => $from[$action][1], "{$col}_at" => now(), "{$col}_by" => $this->caller()->auditUserId(),
                ...($action === 'reject' ? ['reject_reason' => $data['reason'] ?? null] : [])]);

            return $row;
        });

        return ApiResponse::ok($this->present($row->refresh()));
    }

    public function destroy(string $no): JsonResponse
    {
        $this->allow('salesshipment.bill.manage');
        $row = $this->find($no);
        if (! in_array($row->state, SsBill::EDITABLE, true)) {
            throw ValidationException::withMessages(['status' => __('salesshipment.v.delete_draft', ['no' => $row->bill_no])]);
        }
        $row->delete();

        return ApiResponse::ok(['deleted' => $no]);
    }

    /* ── Helpers ───────────────────────────────────────────────────── */

    private function book(): SalesBook
    {
        return $this->book ??= new SalesBook($this->caller()->companyId);
    }

    private function find(string $no): SsBill
    {
        return SsBill::query()->where('bill_no', $no)->firstOrFail();
    }

    private function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');
    }

    /**
     * The lines of challans as a bill reads them: delivered, billed elsewhere, billable, rate, unit, section.
     *
     * @param  list<string>  $challanIds
     * @return list<array<string, mixed>>
     */
    private function sourceOf(array $challanIds, ?string $exceptBill): array
    {
        $lines = DB::table('ss_challan_lines as cl')->join('ss_challans as c', 'c.id', '=', 'cl.challan_id')->join('ss_sales_orders as o', 'o.id', '=', 'c.order_id')
            ->join('ss_sales_order_sizes as s', 's.id', '=', 'cl.size_id')->join('ss_sales_order_lines as l', 'l.id', '=', 's.line_id')
            ->whereIn('cl.challan_id', $challanIds)->orderBy('c.chalan_no')->orderBy('cl.sort_order')
            ->get(['cl.id', 'cl.qty', 'cl.rate', 'cl.size_id', 'c.chalan_no', 'c.chalan_date', 'c.state', 'c.customer_id', 'o.so_no', 'l.uom', 'l.section_id']);
        $fig = $this->book()->lineFigures($lines->pluck('id')->all(), null, $exceptBill);
        $labels = $this->book()->sizeLabels($lines->pluck('size_id')->all());
        $sections = $this->book()->sectionNames();

        return $lines->map(fn ($l) => [
            'challan_line_id' => $l->id, 'chalan_no' => $l->chalan_no, 'challan_info' => $l->chalan_no.' · '.$l->chalan_date, 'label' => $l->so_no.' · '.($labels[$l->size_id] ?? '—'),
            'unit' => $l->uom, 'delivered' => (float) $l->qty, 'prev' => $fig[$l->id]['billed'], 'billable' => max(0.0, round($l->qty - $fig[$l->id]['billed'], 4)), 'rate' => (float) $l->rate,
            'state' => $l->state, 'customer_id' => $l->customer_id, 'section' => $l->section_id ? ($sections[$l->section_id] ?? null) : null,
        ])->values()->all();
    }

    /** @return array<string, mixed> */
    private function present(SsBill $r): array
    {
        $b = $this->book();
        $lines = SsBillLine::query()->where('bill_id', $r->id)->orderBy('sort_order')->get();
        $challanIds = DB::table('ss_challan_lines')->whereIn('id', $lines->pluck('challan_line_id'))->pluck('challan_id')->unique()->values()->all();
        $src = collect($this->sourceOf($challanIds, $r->id))->keyBy('challan_line_id');
        $gross = round((float) $lines->sum(fn ($l) => $l->qty * $l->rate), 2);
        $bdt = round((float) $lines->sum(fn ($l) => $l->qty * $l->rate * $l->exchange_rate), 2);

        return [
            'bill_no' => $r->bill_no, 'customer_id' => $r->customer_id, 'customer' => $b->customerNames()[$r->customer_id] ?? '—', 'address' => $b->addresses()[$r->customer_id] ?? '',
            'currency_id' => $r->currency_id, 'currency' => $b->currencyCodes()[$r->currency_id] ?? '—', 'loc_exchange_rate' => (float) $r->loc_exchange_rate, 'bill_date' => $r->bill_date->toDateString(),
            'deduction' => (float) $r->deduction, 'remarks' => $r->remarks, 'state' => $r->state, 'reject_reason' => $r->reject_reason, 'finalized' => in_array($r->state, SsBill::FINAL, true),
            'gross' => $gross, 'gross_bdt' => $bdt, 'net' => round($gross - (float) $r->deduction, 2), 'net_bdt' => round($bdt - (float) $r->deduction * (float) $r->loc_exchange_rate, 2),
            'submitted' => $b->trail($r->submitted_by, $r->submitted_at), 'verified' => $b->trail($r->verified_by, $r->verified_at), 'approved' => $b->trail($r->approved_by, $r->approved_at),
            'paid' => $b->trail($r->paid_by, $r->paid_at), 'company' => $b->company(),
            'lines' => $lines->map(fn ($l) => [...($src[$l->challan_line_id] ?? []), 'challan_line_id' => $l->challan_line_id, 'qty' => (float) $l->qty, 'rate' => (float) $l->rate,
                'exchange_rate' => (float) $l->exchange_rate])->values()->all(),
        ];
    }
}
