<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Http\Controllers\Api;

use App\Modules\TradeExport\Models\TxBillOfExchange;
use App\Modules\TradeExport\Models\TxBillRealisation;
use App\Modules\TradeExport\Models\TxCommercialInvoice;
use App\Modules\TradeExport\Models\TxExportBill;
use App\Modules\TradeExport\Models\TxExportLc;
use App\Modules\TradeExport\Support\ExportBook;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Trade & Export → Operations (AnnotechERP 22/02), from the shipping invoice
 * to the money: the Export Commercial Invoice (prefix + auto number, against
 * an L/C and its orders, within the L/C's tolerance), the Export Bill
 * Lodgement of final invoices, the Bill of Exchange negotiated at the bank
 * or an advance against export, and the early and final Bank Bill
 * Realisation. Finalised papers are locked; a final realisation marks the
 * bill's invoices collected.
 */
class ExportBillApiController extends ApiController
{
    private ?ExportBook $book = null;

    /* ── Export Commercial Invoice ─────────────────────────────────── */

    public function invoices(): JsonResponse
    {
        $this->allow('tradeexport.export.view');
        $b = $this->book();
        $rows = TxCommercialInvoice::query()->orderByDesc('invoice_date')->orderByDesc('invoice_no')->get();
        $ids = $rows->pluck('id');
        $lines = DB::table('tx_commercial_invoice_lines as l')->join('merch_orders as o', 'o.id', '=', 'l.order_id')->whereIn('l.invoice_id', $ids)->get(['l.invoice_id', 'l.inv_qty', 'l.ctn_qty', 'o.order_no'])->groupBy('invoice_id');
        $lcs = TxExportLc::query()->whereIn('id', $rows->pluck('lc_id')->unique())->get()->keyBy('id');
        $boes = DB::table('tx_boe_invoices as x')->join('tx_bills_of_exchange as e', 'e.id', '=', 'x.boe_id')->whereIn('x.invoice_id', $ids)->get(['x.invoice_id', 'e.bill_no', 'e.id as boe_id'])->keyBy('invoice_id');
        $collected = DB::table('tx_bill_realisations')->where('type', 'FINAL')->where('final', true)->whereIn('boe_id', $boes->pluck('boe_id'))->pluck('boe_id')->flip();

        return ApiResponse::ok($rows->map(function (TxCommercialInvoice $i) use ($b, $lines, $lcs, $boes, $collected) {
            $mine = $lines->get($i->id, collect());
            $lc = $lcs[$i->lc_id] ?? null;
            $boe = $boes[$i->id] ?? null;

            return ['id' => $i->id, 'invoice_no' => $i->invoice_no, 'final' => $i->final, 'bill_collected' => $boe && isset($collected[$boe->boe_id]), 'bl_no' => $i->bl_no, 'on_board_date' => $i->on_board_date?->toDateString(), 'buyer_id' => $lc?->buyer_id, 'buyer' => $lc ? ($b->buyers()[$lc->buyer_id] ?? '—') : '—',
                'lc_no' => $lc?->lc_no, 'order_no' => $mine->pluck('order_no')->unique()->implode(', '), 'exp_no' => $i->exp_no, 'boe_no' => $boe?->bill_no, 'bank_finance' => $i->is_negotiated, 'bank' => $lc ? ($b->branches()[$lc->receiving_branch_id] ?? '—') : '—',
                'ctn' => (int) $mine->sum('ctn_qty'), 'pcs' => (float) $mine->sum('inv_qty'), 'amount' => $i->invoiced_amount, 'net_amount' => $i->net_amount, 'freight' => $i->freight_charges, 'ex_factory_date' => $i->ex_factory_date->toDateString(), 'cfs_date' => $i->handover_date->toDateString(),
                'invoice_date' => $i->invoice_date->toDateString(), 'company' => $b->company(), 'locked' => $i->final];
        })->values()->all());
    }

    /** What an L/C brings to an invoice: its terms, its orders, the invoices already on it. */
    public function invoiceSource(Request $request, string $lcId): JsonResponse
    {
        $this->allow('tradeexport.export.view');
        $l = TxExportLc::query()->findOrFail($lcId);

        return ApiResponse::ok($this->lcSource($l, $request->query('except')));
    }

    public function invoice(string $id): JsonResponse
    {
        $this->allow('tradeexport.export.view');

        return ApiResponse::ok($this->invoiceBody(TxCommercialInvoice::query()->findOrFail($id)));
    }

    public function saveInvoice(Request $request, ?string $id = null): JsonResponse
    {
        $this->allow('tradeexport.export.manage');
        $c = $this->caller()->companyId;
        $b = $this->book();
        $inv = $id ? TxCommercialInvoice::query()->findOrFail($id) : null;
        if ($inv?->final) {
            throw ValidationException::withMessages(['invoice_no' => __('tradeop.v.locked', ['no' => $inv->invoice_no])]);
        }
        $d = $request->validate([
            'lc_id' => ['required', Rule::exists('tx_export_lcs', 'id')->where('company_id', $c)], 'invoice_prefix' => ['required', 'string', 'max:40'], 'invoice_date' => ['required', 'date'], 'currency' => ['required', Rule::exists('currencies', 'code')->where('company_id', $c)], 'loc_exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'exp_no' => ['required', 'string', 'max:60'], 'exp_part_no' => ['required', 'integer', 'between:1,4'], 'exp_date' => ['required', 'date'], 'forwarder' => ['nullable', $b->refRule('FORWARDER')], 'ex_factory_date' => ['required', 'date'], 'on_board_date' => ['nullable', 'date'], 'bl_no' => ['nullable', 'string', 'max:60'], 'bl_date' => ['nullable', 'date'],
            'handover_date' => ['required', 'date'], 'payment_term' => ['required', $b->refRule('PAYMENT_TERM')], 'payment_days' => ['nullable', 'integer', 'min:0'], 'port_of_loading' => ['required', $b->refRule('TRANSIT_PORT')], 'port_of_discharge' => ['required', $b->refRule('TRANSIT_PORT')],
            'delivery_country_id' => ['required', Rule::exists('origin_countries', 'id')->where('company_id', $c)], 'target_collection_date' => ['nullable', 'date'], 'shipment_mode' => ['required', $b->refRule('SHIPMENT_MODE')], 'applicant_id' => ['required', Rule::exists('tx_lc_applicants', 'id')->where('company_id', $c)],
            'penalty_amount' => ['nullable', 'numeric', 'min:0'], 'agency_commission' => ['nullable', 'numeric', 'min:0'], 'sales_discount' => ['nullable', 'numeric', 'min:0'], 'freight_type' => ['nullable', Rule::in(['Collect', 'Prepaid'])], 'freight_charges' => ['nullable', 'numeric', 'min:0'], 'adjust_against_advance' => ['boolean'], 'is_negotiated' => ['boolean'], 'export_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'vessel_no' => ['nullable', 'string', 'max:60'], 'exp_reg_no' => ['nullable', 'string', 'max:60'], 'exp_reg_date' => ['nullable', 'date'], 'marks_and_nos' => ['nullable', 'string', 'max:200'], 'pre_carriage_by' => ['nullable', 'string', 'max:120'], 'container_size' => ['nullable', Rule::in(ExportBook::CONTAINER_SIZES)], 'container_no' => ['nullable', 'string', 'max:200'], 'exp_inv_rex' => ['nullable', 'string', 'max:60'], 'remarks' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'], 'lines.*.order_id' => ['required', Rule::exists('merch_orders', 'id')->where('company_id', $c)], 'lines.*.pattern' => ['nullable', 'string', 'max:80'], 'lines.*.s_all' => ['nullable', 'string', 'max:40'], 'lines.*.hs_code' => ['nullable', 'string', 'max:24'], 'lines.*.hs_category' => ['nullable', 'string', 'max:80'], 'lines.*.description' => ['nullable', 'string', 'max:300'],
            'lines.*.unit' => ['required', 'string', 'max:16'], 'lines.*.inv_qty' => ['required', 'numeric', 'gt:0'], 'lines.*.unit_price' => ['required', 'numeric', 'gte:0'], 'lines.*.ctn_qty' => ['nullable', 'integer', 'min:0'], 'lines.*.net_weight' => ['nullable', 'numeric', 'min:0'], 'lines.*.gross_weight' => ['nullable', 'numeric', 'min:0'], 'lines.*.cbm' => ['nullable', 'numeric', 'min:0'], 'lines.*.ctn_measurement' => ['nullable', 'string', 'max:60'],
            'lines.*.size_breakdown' => ['nullable', 'array'], 'lines.*.size_breakdown.*.colour' => ['required', 'string', 'max:64'], 'lines.*.size_breakdown.*.size' => ['required', 'string', 'max:32'], 'lines.*.size_breakdown.*.qty' => ['required', 'numeric', 'min:0'],
        ], ['lines.required' => __('tradeop.v.inv_lines')], ExportBook::attrs());
        $lc = TxExportLc::query()->findOrFail($d['lc_id']);
        if ($lc->status === 'DRAFT') {
            throw ValidationException::withMessages(['lc_id' => __('tradeop.v.inv_lc_draft', ['no' => $lc->lc_no])]);
        }
        $lines = array_values($d['lines']);
        $tol = 1 + ((float) $lc->credit_tolerance_pct) / 100;
        $orders = DB::table('merch_orders')->whereIn('id', array_column($lines, 'order_id'))->get(['id', 'order_no', 'buyer_id', 'total_qty'])->keyBy('id');
        $done = $this->invoicedByOrder(array_keys($orders->all()), $inv?->id);
        foreach ($lines as $n => $l) {
            $o = $orders[$l['order_id']];
            if ($o->buyer_id !== $lc->buyer_id) {
                throw ValidationException::withMessages(['lines' => __('tradeop.v.lc_order_buyer', ['no' => $o->order_no])]);
            }
            if (! empty($l['size_breakdown'])) {
                $sum = array_sum(array_column($l['size_breakdown'], 'qty'));
                if (abs($sum - (float) $l['inv_qty']) > 0.0005) {
                    throw ValidationException::withMessages(["lines.{$n}.inv_qty" => __('tradeop.v.inv_breakdown', ['no' => $o->order_no, 'sum' => $b->num((float) $sum), 'qty' => $b->num((float) $l['inv_qty'])])]);
                }
            }
        }
        foreach (collect($lines)->groupBy('order_id') as $orderId => $g) {
            $o = $orders[$orderId];
            $cap = round($o->total_qty * $tol, 4);
            if ($g->sum('inv_qty') + (float) ($done[$orderId] ?? 0) > $cap + 0.0005) {
                throw ValidationException::withMessages(['lines' => __('tradeop.v.inv_qty', ['no' => $o->order_no, 'max' => $b->num(max(0.0, $cap - (float) ($done[$orderId] ?? 0)))])]);
            }
        }
        $amount = round(array_sum(array_map(fn ($l) => $l['inv_qty'] * $l['unit_price'], $lines)), 4);
        $other = (float) DB::table('tx_commercial_invoices')->where('lc_id', $lc->id)->when($inv, fn ($q) => $q->where('id', '!=', $inv->id))->sum('invoiced_amount');
        if ($amount + $other > round($lc->lc_value * $tol, 4) + 0.005) {
            throw ValidationException::withMessages(['lines' => __('tradeop.v.inv_lc', ['lc' => $lc->lc_no, 'max' => $b->num(max(0.0, $lc->lc_value * $tol - $other))])]);
        }
        $saved = DB::transaction(function () use ($d, $inv, $c, $lines, $amount) {
            $body = collect($d)->except(['lines'])->all() + ['company_id' => $c];
            foreach (['penalty_amount', 'agency_commission', 'sales_discount', 'freight_charges'] as $k) {
                $body[$k] = (float) ($d[$k] ?? 0);
            }
            $body['adjust_against_advance'] = (bool) ($d['adjust_against_advance'] ?? false);
            $body['is_negotiated'] = (bool) ($d['is_negotiated'] ?? false);
            $body['payment_days'] = (int) ($d['payment_days'] ?? 0);
            $body['invoiced_amount'] = $amount;
            $body['net_amount'] = round($amount - $body['penalty_amount'] - $body['agency_commission'] - $body['sales_discount'], 4);
            if ($inv && $inv->invoice_prefix === $d['invoice_prefix']) {
                $inv->update($body);
            } else {
                $no = $this->nextAuto($d['invoice_prefix']);
                $identity = ['auto_no' => $no, 'invoice_no' => $d['invoice_prefix'].str_pad((string) $no, 4, '0', STR_PAD_LEFT)];
                if ($inv) {
                    $inv->update($body + $identity);
                } else {
                    $inv = TxCommercialInvoice::query()->create($body + $identity + ['created_by' => $this->caller()->auditUserId()]);
                }
            }
            DB::table('tx_commercial_invoice_lines')->where('invoice_id', $inv->id)->delete();
            foreach ($lines as $i => $l) {
                DB::table('tx_commercial_invoice_lines')->insert(['id' => (string) Str::ulid(), 'company_id' => $c, 'invoice_id' => $inv->id, 'order_id' => $l['order_id'], 'pattern' => $l['pattern'] ?? null, 's_all' => $l['s_all'] ?? null, 'hs_code' => $l['hs_code'] ?? null, 'hs_category' => $l['hs_category'] ?? null, 'description' => $l['description'] ?? null, 'unit' => $l['unit'], 'inv_qty' => $l['inv_qty'],
                    'size_breakdown' => empty($l['size_breakdown']) ? null : json_encode(array_values($l['size_breakdown'])), 'unit_price' => $l['unit_price'], 'amount' => round($l['inv_qty'] * $l['unit_price'], 4), 'ctn_qty' => (int) ($l['ctn_qty'] ?? 0), 'net_weight' => (float) ($l['net_weight'] ?? 0), 'gross_weight' => (float) ($l['gross_weight'] ?? 0), 'cbm' => (float) ($l['cbm'] ?? 0), 'ctn_measurement' => $l['ctn_measurement'] ?? null, 'sort_order' => $i, 'created_at' => now(), 'updated_at' => now()]);
            }

            return $inv;
        });

        return $id ? ApiResponse::ok($this->invoiceBody($saved->fresh())) : ApiResponse::created($this->invoiceBody($saved->fresh()));
    }

    public function invoiceFinal(string $id): JsonResponse
    {
        $this->allow('tradeexport.export.manage');
        $i = TxCommercialInvoice::query()->findOrFail($id);
        $i->update(['final' => true]);

        return ApiResponse::ok(['id' => $i->id, 'final' => true]);
    }

    public function invoiceDestroy(string $id): JsonResponse
    {
        $this->allow('tradeexport.export.manage');
        $i = TxCommercialInvoice::query()->findOrFail($id);
        if ($i->final) {
            throw ValidationException::withMessages(['invoice_no' => __('tradeop.v.locked', ['no' => $i->invoice_no])]);
        }
        $i->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    /* ── Export Bill Lodgement ─────────────────────────────────────── */

    public function lodgements(): JsonResponse
    {
        $this->allow('tradeexport.export.view');
        $rows = TxExportBill::query()->orderByDesc('bill_date')->orderByDesc('bill_no')->get();
        $inv = DB::table('tx_export_bill_invoices as x')->join('tx_commercial_invoices as i', 'i.id', '=', 'x.invoice_id')->whereIn('x.bill_id', $rows->pluck('id'))->get(['x.bill_id', 'i.invoice_no', 'i.lc_id'])->groupBy('bill_id');
        $uds = $this->book()->udNos($inv->flatten()->pluck('lc_id')->unique()->all());

        return ApiResponse::ok($rows->map(fn (TxExportBill $e) => ['id' => $e->id, 'bill_no' => $e->bill_no, 'bill_date' => $e->bill_date->toDateString(), 'final' => $e->final, 'invoices' => $inv->get($e->id, collect())->map(fn ($r) => $r->invoice_no.' ['.($uds[$r->lc_id] ?? '—').']')->implode(', '), 'remarks' => $e->remarks, 'charge' => $e->charge, 'file_id' => $e->file_id, 'file_name' => $e->file_name, 'locked' => $e->final])->values()->all());
    }

    /** Final invoices not lodged on another bill. */
    public function lodgeable(Request $request): JsonResponse
    {
        $this->allow('tradeexport.export.view');
        $except = $request->query('except');
        $taken = DB::table('tx_export_bill_invoices')->when($except, fn ($q) => $q->where('bill_id', '!=', $except))->pluck('invoice_id');
        $ids = TxCommercialInvoice::query()->where('final', true)->whereNotIn('id', $taken)->pluck('id')->all();

        return ApiResponse::ok($this->invoiceRows($ids));
    }

    public function lodgement(string $id): JsonResponse
    {
        $this->allow('tradeexport.export.view');

        return ApiResponse::ok($this->lodgementBody(TxExportBill::query()->findOrFail($id)));
    }

    public function saveLodgement(Request $request, ?string $id = null): JsonResponse
    {
        $this->allow('tradeexport.export.manage');
        $c = $this->caller()->companyId;
        $e = $id ? TxExportBill::query()->findOrFail($id) : null;
        if ($e?->final) {
            throw ValidationException::withMessages(['bill_no' => __('tradeop.v.locked', ['no' => $e->bill_no])]);
        }
        $d = $request->validate([
            'bill_no' => ['required', 'string', 'max:60', Rule::unique('tx_export_bills', 'bill_no')->where('company_id', $c)->ignore($e?->id)], 'bill_date' => ['required', 'date'], 'charge' => ['nullable', 'numeric', 'min:0'], 'pass_book_page' => ['nullable', 'string', 'max:40'], 'remarks' => ['nullable', 'string'],
            'invoices' => ['required', 'array', 'min:1'], 'invoices.*' => ['required', Rule::exists('tx_commercial_invoices', 'id')->where('company_id', $c)],
        ], ['invoices.required' => __('tradeop.v.bill_invoices')], ExportBook::attrs());
        $ids = array_values(array_unique($d['invoices']));
        foreach (TxCommercialInvoice::query()->whereIn('id', $ids)->get(['id', 'invoice_no', 'final']) as $i) {
            if (! $i->final) {
                throw ValidationException::withMessages(['invoices' => __('tradeop.v.bill_not_final', ['no' => $i->invoice_no])]);
            }
        }
        $taken = DB::table('tx_export_bill_invoices as x')->join('tx_export_bills as b', 'b.id', '=', 'x.bill_id')->join('tx_commercial_invoices as i', 'i.id', '=', 'x.invoice_id')->whereIn('x.invoice_id', $ids)->when($e, fn ($q) => $q->where('x.bill_id', '!=', $e->id))->first(['i.invoice_no', 'b.bill_no']);
        if ($taken) {
            throw ValidationException::withMessages(['invoices' => __('tradeop.v.bill_taken', ['no' => $taken->invoice_no, 'bill' => $taken->bill_no])]);
        }
        $saved = DB::transaction(function () use ($d, $e, $c, $ids) {
            $body = collect($d)->except(['invoices'])->all() + ['company_id' => $c, 'charge' => (float) ($d['charge'] ?? 0)];
            $e ? $e->update($body) : $e = TxExportBill::query()->create($body + ['created_by' => $this->caller()->auditUserId()]);
            DB::table('tx_export_bill_invoices')->where('bill_id', $e->id)->delete();
            foreach ($ids as $inv) {
                DB::table('tx_export_bill_invoices')->insert(['id' => (string) Str::ulid(), 'company_id' => $c, 'bill_id' => $e->id, 'invoice_id' => $inv, 'created_at' => now(), 'updated_at' => now()]);
            }

            return $e;
        });

        return $id ? ApiResponse::ok($this->lodgementBody($saved->fresh())) : ApiResponse::created($this->lodgementBody($saved->fresh()));
    }

    public function lodgementAttach(Request $request, StoreFileAttachment $files, string $id): JsonResponse
    {
        $this->allow('tradeexport.export.manage');
        $e = TxExportBill::query()->findOrFail($id);
        if ($e->final) {
            throw ValidationException::withMessages(['bill_no' => __('tradeop.v.locked', ['no' => $e->bill_no])]);
        }
        $request->validate(['upload' => ['required', 'file', 'max:10240']]);
        $upload = $request->file('upload');
        $file = $files->handle($upload, 'tx_export_bill', $e->id, $this->caller()->auditUserId());
        $e->update(['file_id' => $file->id, 'file_name' => mb_substr($upload->getClientOriginalName(), 0, 200)]);

        return ApiResponse::ok(['id' => $e->id, 'file_id' => $file->id, 'attachment' => $e->file_name]);
    }

    public function lodgementFinal(string $id): JsonResponse
    {
        $this->allow('tradeexport.export.manage');
        $e = TxExportBill::query()->findOrFail($id);
        $e->update(['final' => true]);

        return ApiResponse::ok(['id' => $e->id, 'final' => true]);
    }

    public function lodgementDestroy(string $id): JsonResponse
    {
        $this->allow('tradeexport.export.manage');
        $e = TxExportBill::query()->findOrFail($id);
        if ($e->final) {
            throw ValidationException::withMessages(['bill_no' => __('tradeop.v.locked', ['no' => $e->bill_no])]);
        }
        $e->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    /* ── Bill of Exchange Draft ────────────────────────────────────── */

    public function boes(): JsonResponse
    {
        $this->allow('tradeexport.export.view');
        $b = $this->book();
        $rows = TxBillOfExchange::query()->orderByDesc('bill_date')->orderByDesc('bill_no')->get();
        $lcs = TxExportLc::query()->whereIn('id', $rows->pluck('lc_id')->filter()->unique())->get()->keyBy('id');
        $invoices = DB::table('tx_boe_invoices as x')->join('tx_commercial_invoices as i', 'i.id', '=', 'x.invoice_id')->whereIn('x.boe_id', $rows->pluck('id'))->get(['x.boe_id', 'i.invoice_no'])->groupBy('boe_id');
        $real = TxBillRealisation::query()->whereIn('boe_id', $rows->pluck('id'))->get()->groupBy('boe_id');
        $cm = DB::table('tx_cm_receipts')->whereIn('boe_id', $rows->pluck('id'))->groupBy('boe_id')->selectRaw('boe_id, max(receive_date) as d')->pluck('d', 'boe_id');

        return ApiResponse::ok($rows->map(function (TxBillOfExchange $e) use ($b, $lcs, $invoices, $real, $cm) {
            $lc = $e->lc_id ? ($lcs[$e->lc_id] ?? null) : null;
            $mine = $real->get($e->id, collect());
            $neg = $this->negotiated($mine);

            return ['id' => $e->id, 'bill_no' => $e->bill_no, 'final' => $e->final, 'is_advance' => $e->is_advance, 'bank_bill_type' => $e->bank_bill_type, 'bill_date' => $e->bill_date->toDateString(), 'submit_date' => ($e->bank_submit_date ?? $e->ref_date)?->toDateString(), 'received_date' => $e->payment_recv_date?->toDateString() ?? (max((string) $mine->max(fn ($r) => $r->realized_date->toDateString()), (string) substr((string) ($cm[$e->id] ?? ''), 0, 10)) ?: null), 'margin_type' => $e->margin_type, 'bank_bill_ref_no' => $e->bank_bill_ref_no, 'lc_no' => $lc?->lc_no, 'lc_value' => $lc?->lc_value,
                'bill_amount' => $e->bill_amount, 'negotiated' => $neg, 'remaining' => max(0.0, round($e->bill_amount - $neg, 4)), 'buyer_id' => $e->buyer_id, 'bank_id' => $b->branchBank($e->receiving_branch_id), 'invoices' => $invoices->get($e->id, collect())->pluck('invoice_no')->implode(', '), 'company' => $b->company(), 'locked' => $e->final];
        })->values()->all());
    }

    /** What is left on an L/C and the final invoices not on another bill. */
    public function boeSource(Request $request, string $lcId): JsonResponse
    {
        $this->allow('tradeexport.export.view');
        $l = TxExportLc::query()->findOrFail($lcId);
        $except = $request->query('except');
        $taken = DB::table('tx_boe_invoices')->when($except, fn ($q) => $q->where('boe_id', '!=', $except))->pluck('invoice_id');
        $ids = TxCommercialInvoice::query()->where('lc_id', $l->id)->where('final', true)->whereNotIn('id', $taken)->pluck('id')->all();
        $used = (float) TxBillOfExchange::query()->where('lc_id', $l->id)->when($except, fn ($q) => $q->where('id', '!=', $except))->sum('bill_amount');

        return ApiResponse::ok(['lc' => $l->only(['id', 'lc_no', 'lc_value', 'currency', 'buyer_id', 'applicant_id', 'payment_term', 'receiving_branch_id', 'advising_branch_id', 'sender_branch_id']), 'balance' => round($l->lc_value - $used, 4), 'invoices' => $this->invoiceRows($ids)]);
    }

    public function boe(string $id): JsonResponse
    {
        $this->allow('tradeexport.export.view');

        return ApiResponse::ok($this->boeBody(TxBillOfExchange::query()->findOrFail($id)));
    }

    public function saveBoe(Request $request, ?string $id = null): JsonResponse
    {
        $this->allow('tradeexport.export.manage');
        $c = $this->caller()->companyId;
        $b = $this->book();
        $e = $id ? TxBillOfExchange::query()->findOrFail($id) : null;
        if ($e?->final) {
            throw ValidationException::withMessages(['bill_no' => __('tradeop.v.locked', ['no' => $e->bill_no])]);
        }
        $advance = $request->boolean('is_advance');
        $branch = fn (bool $req) => [$req ? 'required' : 'nullable', Rule::exists('tx_bank_branches', 'id')->where('company_id', $c)];
        $d = $request->validate([
            'bill_no' => ['required', 'string', 'max:60', Rule::unique('tx_bills_of_exchange', 'bill_no')->where('company_id', $c)->ignore($e?->id)], 'is_advance' => ['boolean'], 'lc_id' => [$advance ? 'nullable' : 'required', Rule::exists('tx_export_lcs', 'id')->where('company_id', $c)], 'buyer_id' => ['required', Rule::exists('buyers', 'id')->where('company_id', $c)], 'applicant_id' => ['required', Rule::exists('tx_lc_applicants', 'id')->where('company_id', $c)],
            'bill_date' => ['required', 'date'], 'bill_amount' => ['required', 'numeric', 'gt:0'], 'currency' => ['required', Rule::exists('currencies', 'code')->where('company_id', $c)], 'local_rate' => ['required', 'numeric', 'gt:0'], 'local_currency' => ['nullable', 'string', 'max:3'],
            'issuing_branch_id' => $branch(true), 'advising_branch_id' => $branch(true), 'doc_credit_branch_id' => $branch(false), 'receiving_branch_id' => $branch(true), 'same_receiver_bank' => ['boolean'], 'payment_term' => ['nullable', $b->refRule('PAYMENT_TERM')], 'remarks' => ['nullable', 'string'],
            'bank_bill_type' => ['required', Rule::in($advance ? ['IP'] : ['FDBP', 'FDBC', 'DA', 'DP'])], 'bank_bill_ref_no' => ['nullable', 'string', 'max:60'], 'remittance_ref_no' => ['nullable', 'string', 'max:60'], 'due_date' => ['required', 'date', 'after_or_equal:bill_date'], 'ref_date' => ['nullable', 'date'],
            'margin_type' => ['nullable', Rule::in(CmPaymentApiController::MARGIN_TYPES)], 'link_type' => [$advance && ! $request->filled('margin_type') ? 'required' : 'nullable', Rule::in(['PI', 'PO'])], 'pi_id' => ['nullable', Rule::exists('tx_export_pis', 'id')->where('company_id', $c)], 'order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $c)],
            'invoices' => ['array'], 'invoices.*' => ['required', Rule::exists('tx_commercial_invoices', 'id')->where('company_id', $c)], ...$this->distributionRules($c),
        ], ['due_date.after_or_equal' => __('tradeop.v.boe_due')], ExportBook::attrs());
        $ids = array_values(array_unique($d['invoices'] ?? []));
        $distribution = array_values($d['distribution'] ?? []);
        if (! $advance) {
            if (! $ids) {
                throw ValidationException::withMessages(['invoices' => __('tradeop.v.bill_invoices')]);
            }
            foreach (TxCommercialInvoice::query()->whereIn('id', $ids)->get(['id', 'invoice_no', 'final', 'lc_id']) as $i) {
                if (! $i->final || $i->lc_id !== $d['lc_id']) {
                    throw ValidationException::withMessages(['invoices' => __('tradeop.v.boe_invoice', ['no' => $i->invoice_no])]);
                }
            }
            $taken = DB::table('tx_boe_invoices as x')->join('tx_bills_of_exchange as o', 'o.id', '=', 'x.boe_id')->join('tx_commercial_invoices as i', 'i.id', '=', 'x.invoice_id')->whereIn('x.invoice_id', $ids)->when($e, fn ($q) => $q->where('x.boe_id', '!=', $e->id))->first(['i.invoice_no', 'o.bill_no']);
            if ($taken) {
                throw ValidationException::withMessages(['invoices' => __('tradeop.v.bill_taken', ['no' => $taken->invoice_no, 'bill' => $taken->bill_no])]);
            }
            $lc = TxExportLc::query()->findOrFail($d['lc_id']);
            $used = (float) TxBillOfExchange::query()->where('lc_id', $lc->id)->when($e, fn ($q) => $q->where('id', '!=', $e->id))->sum('bill_amount');
            if ((float) $d['bill_amount'] > round($lc->lc_value - $used, 4) + 0.005) {
                throw ValidationException::withMessages(['bill_amount' => __('tradeop.v.boe_balance', ['lc' => $lc->lc_no, 'max' => $b->num(max(0.0, $lc->lc_value - $used))])]);
            }
        } elseif (empty($d['margin_type']) || ! empty($d['link_type'])) {
            $key = $d['link_type'] === 'PI' ? 'pi_id' : 'order_id';
            if (empty($d[$key])) {
                throw ValidationException::withMessages([$key => __('tradeop.v.boe_link')]);
            }
            $this->assertBalanced($distribution, 'distribution');
        } else {
            $this->assertBalanced($distribution, 'distribution');
        }
        if (! empty($d['margin_type']) && ! $advance) {
            throw ValidationException::withMessages(['margin_type' => __('tradeop.v.mc_advance')]);
        }
        $saved = DB::transaction(function () use ($d, $e, $c, $ids, $distribution, $advance) {
            $body = collect($d)->except(['invoices', 'distribution'])->all() + ['company_id' => $c];
            $body['is_advance'] = $advance;
            $body['same_receiver_bank'] = (bool) ($d['same_receiver_bank'] ?? false);
            $body['local_currency'] = $d['local_currency'] ?? 'BDT';
            $e ? $e->update($body) : $e = TxBillOfExchange::query()->create($body + ['created_by' => $this->caller()->auditUserId()]);
            DB::table('tx_boe_invoices')->where('boe_id', $e->id)->delete();
            foreach ($ids as $inv) {
                DB::table('tx_boe_invoices')->insert(['id' => (string) Str::ulid(), 'company_id' => $c, 'boe_id' => $e->id, 'invoice_id' => $inv, 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->storeDistribution('BOE', $e->id, $distribution, $c);

            return $e;
        });

        return $id ? ApiResponse::ok($this->boeBody($saved->fresh())) : ApiResponse::created($this->boeBody($saved->fresh()));
    }

    public function boeFinal(string $id): JsonResponse
    {
        $this->allow('tradeexport.export.manage');
        $e = TxBillOfExchange::query()->findOrFail($id);
        $e->update(['final' => true]);

        return ApiResponse::ok(['id' => $e->id, 'final' => true]);
    }

    public function boeDestroy(string $id): JsonResponse
    {
        $this->allow('tradeexport.export.manage');
        $e = TxBillOfExchange::query()->findOrFail($id);
        if ($e->final) {
            throw ValidationException::withMessages(['bill_no' => __('tradeop.v.locked', ['no' => $e->bill_no])]);
        }
        DB::table('tx_bill_distributions')->where('owner_type', 'BOE')->where('owner_id', $e->id)->delete();
        $e->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    /* ── Bank Bill Realisation ─────────────────────────────────────── */

    public function realisations(): JsonResponse
    {
        $this->allow('tradeexport.export.view');
        $b = $this->book();
        $rows = TxBillRealisation::query()->orderByDesc('realized_date')->orderByDesc('realized_no')->get();
        $boes = TxBillOfExchange::query()->whereIn('id', $rows->pluck('boe_id')->unique())->get()->keyBy('id');
        $invoices = DB::table('tx_boe_invoices as x')->join('tx_commercial_invoices as i', 'i.id', '=', 'x.invoice_id')->whereIn('x.boe_id', $boes->keys())->get(['x.boe_id', 'i.invoice_no', 'i.net_amount'])->groupBy('boe_id');

        return ApiResponse::ok($rows->map(function (TxBillRealisation $r) use ($b, $boes, $invoices) {
            $e = $boes[$r->boe_id] ?? null;
            $mine = $invoices->get($r->boe_id, collect());

            return ['id' => $r->id, 'realized_no' => $r->realized_no, 'company' => $b->company(), 'type' => $r->type, 'final' => $r->final, 'realized_date' => $r->realized_date->toDateString(), 'boe_no' => $e?->bill_no, 'bank_bill_no' => $e?->bank_bill_ref_no, 'collect_amount' => $r->type === 'FINAL' ? $r->collection_amount : $r->invoice_amount, 'bank_name' => $r->bank_name, 'bank_id' => $e ? $b->branchBank($e->receiving_branch_id) : null, 'bank_bill_type' => $r->bank_bill_type, 'fbc_amount' => $r->fbc_amount, 'penalty_amount' => $r->penalty_amount,
                'invoices' => $mine->pluck('invoice_no')->implode(', '), 'invoice_amount' => $r->invoice_amount, 'locked' => $r->final];
        })->values()->all());
    }

    /** The final bills a realisation can be raised on (not yet finally realised, unless it is this one). */
    public function realisable(Request $request): JsonResponse
    {
        $this->allow('tradeexport.export.view');
        $except = $request->query('except');
        $done = TxBillRealisation::query()->where('type', 'FINAL')->when($except, fn ($q) => $q->where('id', '!=', $except))->pluck('boe_id');

        return ApiResponse::ok(TxBillOfExchange::query()->where('final', true)->whereNotIn('id', $done)->orderByDesc('bill_date')->get()->map(fn (TxBillOfExchange $e) => $this->boeForRealisation($e))->values()->all());
    }

    public function realisation(string $id): JsonResponse
    {
        $this->allow('tradeexport.export.view');

        return ApiResponse::ok($this->realisationBody(TxBillRealisation::query()->findOrFail($id)));
    }

    public function saveRealisation(Request $request, ?string $id = null): JsonResponse
    {
        $this->allow('tradeexport.export.manage');
        $c = $this->caller()->companyId;
        $r = $id ? TxBillRealisation::query()->findOrFail($id) : null;
        if ($r?->final) {
            throw ValidationException::withMessages(['realized_no' => __('tradeop.v.locked', ['no' => $r->realized_no])]);
        }
        $d = $request->validate([
            'type' => ['required', Rule::in(['EARLY', 'FINAL'])], 'realized_no' => ['required', 'string', 'max:60', Rule::unique('tx_bill_realisations', 'realized_no')->where('company_id', $c)->ignore($r?->id)], 'boe_id' => ['required', Rule::exists('tx_bills_of_exchange', 'id')->where('company_id', $c)], 'realized_date' => ['required', 'date'], 'bank_bill_type' => ['required', Rule::in(ExportBook::BANK_BILL_TYPES)], 'bank_name' => ['required', 'string', 'max:160'],
            'export_currency' => ['required', 'string', 'max:3'], 'local_currency' => ['required', 'string', 'max:3'], 'rate_ods' => ['required', 'numeric', 'gt:0'], 'rate_ttc' => [$request->input('type') === 'FINAL' ? 'required' : 'nullable', 'numeric', 'gt:0'], 'invoice_amount' => ['required', 'numeric', 'gt:0'], 'collection_amount' => [$request->input('type') === 'FINAL' ? 'required' : 'nullable', 'numeric', 'min:0'], 'fbc_amount' => ['nullable', 'numeric', 'min:0'], 'remarks' => ['nullable', 'string'], ...$this->distributionRules($c),
        ], [], ExportBook::attrs());
        $e = TxBillOfExchange::query()->findOrFail($d['boe_id']);
        if (! $e->final) {
            throw ValidationException::withMessages(['boe_id' => __('tradeop.v.real_boe', ['no' => $e->bill_no])]);
        }
        $others = TxBillRealisation::query()->where('boe_id', $e->id)->when($r, fn ($q) => $q->where('id', '!=', $r->id))->get(['type', 'realized_no']);
        if ($d['type'] === 'FINAL' && ($dup = $others->firstWhere('type', 'FINAL'))) {
            throw ValidationException::withMessages(['boe_id' => __('tradeop.v.real_twice', ['no' => $e->bill_no, 'real' => $dup->realized_no])]);
        }
        if ($d['type'] === 'EARLY' && ($dup = $others->first())) {
            throw ValidationException::withMessages(['boe_id' => __('tradeop.v.real_twice', ['no' => $e->bill_no, 'real' => $dup->realized_no])]);
        }
        $distribution = array_values($d['distribution'] ?? []);
        $this->assertBalanced($distribution, 'distribution');
        $penalty = (float) DB::table('tx_boe_invoices as x')->join('tx_commercial_invoices as i', 'i.id', '=', 'x.invoice_id')->where('x.boe_id', $e->id)->sum('i.penalty_amount');
        $saved = DB::transaction(function () use ($d, $r, $c, $distribution, $penalty) {
            $body = collect($d)->except(['distribution'])->all() + ['company_id' => $c];
            $body['invoice_amount_bdt'] = round($d['invoice_amount'] * $d['rate_ods'], 4);
            $body['collection_amount'] = (float) ($d['collection_amount'] ?? 0);
            $body['fbc_amount'] = (float) ($d['fbc_amount'] ?? 0);
            $body['penalty_amount'] = $penalty;
            $r ? $r->update($body) : $r = TxBillRealisation::query()->create($body + ['created_by' => $this->caller()->auditUserId()]);
            $this->storeDistribution('REALISATION', $r->id, $distribution, $c);

            return $r;
        });

        return $id ? ApiResponse::ok($this->realisationBody($saved->fresh())) : ApiResponse::created($this->realisationBody($saved->fresh()));
    }

    public function realisationFinal(string $id): JsonResponse
    {
        $this->allow('tradeexport.export.manage');
        $r = TxBillRealisation::query()->findOrFail($id);
        $r->update(['final' => true]);

        return ApiResponse::ok(['id' => $r->id, 'final' => true]);
    }

    public function realisationDestroy(string $id): JsonResponse
    {
        $this->allow('tradeexport.export.manage');
        $r = TxBillRealisation::query()->findOrFail($id);
        if ($r->final) {
            throw ValidationException::withMessages(['realized_no' => __('tradeop.v.locked', ['no' => $r->realized_no])]);
        }
        DB::table('tx_bill_distributions')->where('owner_type', 'REALISATION')->where('owner_id', $r->id)->delete();
        $r->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    /* ── Shared ────────────────────────────────────────────────────── */

    /** @return array<string, array<int, mixed>> */
    private function distributionRules(string $c): array
    {
        return ['distribution' => ['array'], 'distribution.*.account_id' => ['required', Rule::exists('acc_accounts', 'id')->where('company_id', $c)], 'distribution.*.dr_cr' => ['required', Rule::in(['DR', 'CR'])], 'distribution.*.currency' => ['required', 'string', 'max:3'], 'distribution.*.amount' => ['required', 'numeric', 'gt:0'], 'distribution.*.note' => ['nullable', 'string', 'max:200']];
    }

    private function assertBalanced(array $rows, string $field): void
    {
        if (! $rows) {
            return;
        }
        $sum = fn (string $side) => array_sum(array_map(fn ($r) => $r['dr_cr'] === $side ? (float) $r['amount'] : 0.0, $rows));
        if (abs($sum('DR') - $sum('CR')) > 0.005) {
            throw ValidationException::withMessages([$field => __('tradeop.v.unbalanced', ['dr' => $this->book()->num($sum('DR')), 'cr' => $this->book()->num($sum('CR'))])]);
        }
    }

    private function storeDistribution(string $owner, string $id, array $rows, string $c): void
    {
        DB::table('tx_bill_distributions')->where('owner_type', $owner)->where('owner_id', $id)->delete();
        foreach ($rows as $i => $r) {
            DB::table('tx_bill_distributions')->insert(['id' => (string) Str::ulid(), 'company_id' => $c, 'owner_type' => $owner, 'owner_id' => $id, 'account_id' => $r['account_id'], 'dr_cr' => $r['dr_cr'], 'currency' => $r['currency'], 'amount' => $r['amount'], 'note' => $r['note'] ?? null, 'sort_order' => $i, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function distribution(string $owner, string $id): array
    {
        return DB::table('tx_bill_distributions')->where('owner_type', $owner)->where('owner_id', $id)->orderBy('sort_order')->get(['account_id', 'dr_cr', 'currency', 'amount', 'note'])->map(fn ($r) => ['account_id' => $r->account_id, 'dr_cr' => $r->dr_cr, 'currency' => $r->currency, 'amount' => (float) $r->amount, 'note' => $r->note])->all();
    }

    private function negotiated(Collection $realisations): float
    {
        $final = $realisations->firstWhere('type', 'FINAL');

        return (float) ($final ? $final->collection_amount : $realisations->sum('invoice_amount'));
    }

    private function nextAuto(string $prefix): int
    {
        return (int) TxCommercialInvoice::query()->where('invoice_prefix', $prefix)->max('auto_no') + 1;
    }

    /** @return array<string, float> Quantity invoiced per order, other than on `$except`. */
    private function invoicedByOrder(array $orderIds, ?string $except = null): array
    {
        return DB::table('tx_commercial_invoice_lines')->whereIn('order_id', $orderIds)->when($except, fn ($q) => $q->where('invoice_id', '!=', $except))->groupBy('order_id')->selectRaw('order_id, sum(inv_qty) as q')->pluck('q', 'order_id')->map(fn ($v) => (float) $v)->all();
    }

    /** @return array<string, mixed> */
    private function lcSource(TxExportLc $l, ?string $except): array
    {
        $b = $this->book();
        $orders = DB::table('tx_export_lc_orders as x')->join('merch_orders as o', 'o.id', '=', 'x.order_id')->where('x.lc_id', $l->id)->get(['o.id']);
        $invoices = TxCommercialInvoice::query()->where('lc_id', $l->id)->orderBy('invoice_date')->get();
        $lines = DB::table('tx_commercial_invoice_lines as li')->join('merch_orders as o', 'o.id', '=', 'li.order_id')->whereIn('li.invoice_id', $invoices->pluck('id'))->get(['li.invoice_id', 'li.inv_qty', 'li.ctn_qty', 'o.order_no', 'o.unit'])->groupBy('invoice_id');
        $total = (float) $invoices->sum('invoiced_amount');

        return ['lc' => $l->only(['id', 'lc_no', 'lc_value', 'currency', 'buyer_id', 'applicant_id', 'payment_term', 'payment_days', 'port_of_loading', 'shipment_mode']) + ['buyer' => $b->buyers()[$l->buyer_id] ?? '—', 'beneficiary' => $b->company(), 'receiving_bank' => $b->branches()[$l->receiving_branch_id] ?? '—', 'tolerance' => $l->credit_tolerance_pct],
            'orders' => $this->orderLines($orders->pluck('id')->all(), $except), 'invoices' => $invoices->map(function (TxCommercialInvoice $i) use ($l, $b, $lines) {
                $mine = $lines->get($i->id, collect());

                return ['id' => $i->id, 'invoice_no' => $i->invoice_no, 'invoice_date' => $i->invoice_date->toDateString(), 'amount' => $i->invoiced_amount, 'buyer' => $b->buyers()[$l->buyer_id] ?? '—', 'lc_no' => $l->lc_no, 'lc_value' => $l->lc_value, 'currency' => $i->currency, 'order_no' => $mine->pluck('order_no')->unique()->implode(', '), 'unit' => $mine->pluck('unit')->first(), 'ctn' => (int) $mine->sum('ctn_qty'), 'pcs' => (float) $mine->sum('inv_qty'), 'shipment_mode' => $b->reference('SHIPMENT_MODE', $i->shipment_mode), 'freight' => $i->freight_charges];
            })->values()->all(), 'total_bill' => $total, 'lc_due' => round($l->lc_value - $total, 4)];
    }

    /** @return list<array<string, mixed>> An order's line for an invoice: the PO, what is invoiced, its colour / size grid. */
    private function orderLines(array $orderIds, ?string $except): array
    {
        $done = $this->invoicedByOrder($orderIds, $except);
        $sizes = DB::table('merch_order_sizes')->whereIn('order_id', $orderIds)->orderBy('sort_order')->get(['order_id', 'colour', 'size', 'qty'])->groupBy('order_id');

        return DB::table('merch_orders')->whereIn('id', $orderIds)->orderBy('order_no')->get(['id', 'order_no', 'style_no', 'item', 'unit', 'total_qty', 'unit_price'])
            ->map(fn ($o) => ['order_id' => $o->id, 'order_no' => $o->order_no, 'style_no' => $o->style_no, 'description' => $o->item, 'unit' => $o->unit, 'po_qty' => (float) $o->total_qty, 'done_qty' => (float) ($done[$o->id] ?? 0), 'unit_price' => (float) ($o->unit_price ?? 0), 'sizes' => $sizes->get($o->id, collect())->map(fn ($s) => ['colour' => $s->colour, 'size' => $s->size, 'qty' => (float) $s->qty])->values()->all()])->values()->all();
    }

    /** The line of one order, for an invoice that picks it by hand. */
    public function invoiceOrder(Request $request, string $orderId): JsonResponse
    {
        $this->allow('tradeexport.export.view');
        abort_unless(DB::table('merch_orders')->where('company_id', $this->caller()->companyId)->where('id', $orderId)->exists(), 404);

        return ApiResponse::ok($this->orderLines([$orderId], $request->query('except'))[0]);
    }

    /** @return list<array<string, mixed>> Invoices as the bill grids show them. */
    private function invoiceRows(array $ids): array
    {
        $b = $this->book();
        $rows = TxCommercialInvoice::query()->whereIn('id', $ids)->orderBy('invoice_date')->orderBy('invoice_no')->get();
        $lcs = TxExportLc::query()->whereIn('id', $rows->pluck('lc_id')->unique())->get()->keyBy('id');
        $uds = $b->udNos($rows->pluck('lc_id')->unique()->all());
        $lines = DB::table('tx_commercial_invoice_lines as l')->join('merch_orders as o', 'o.id', '=', 'l.order_id')->whereIn('l.invoice_id', $ids)->get(['l.invoice_id', 'l.inv_qty', 'l.ctn_qty', 'l.net_weight', 'o.order_no', 'o.unit', 'o.total_qty'])->groupBy('invoice_id');

        return $rows->map(function (TxCommercialInvoice $i) use ($b, $lcs, $lines, $uds) {
            $mine = $lines->get($i->id, collect());
            $lc = $lcs[$i->lc_id] ?? null;

            return ['id' => $i->id, 'invoice_no' => $i->invoice_no, 'invoice_date' => $i->invoice_date->toDateString(), 'gross' => $i->invoiced_amount, 'net' => $i->net_amount, 'beneficiary' => $b->company(), 'buyer' => $lc ? ($b->buyers()[$lc->buyer_id] ?? '—') : '—', 'lc_no' => $lc?->lc_no, 'lc_value' => $lc?->lc_value, 'order_no' => $mine->pluck('order_no')->unique()->implode(', '), 'ship_date' => $i->ex_factory_date->toDateString(), 'order_qty' => (float) $mine->sum('total_qty'), 'unit' => $mine->pluck('unit')->first(), 'ctn' => (int) $mine->sum('ctn_qty'), 'pcs' => (float) $mine->sum('inv_qty'), 'net_weight' => (float) $mine->sum('net_weight'), 'exp_no' => $i->exp_no, 'ud' => $uds[$i->lc_id] ?? null];
        })->values()->all();
    }

    /** @return array<string, mixed> */
    private function invoiceBody(TxCommercialInvoice $i): array
    {
        $dates = ['invoice_date', 'exp_date', 'ex_factory_date', 'on_board_date', 'bl_date', 'handover_date', 'target_collection_date', 'exp_reg_date'];
        $rows = DB::table('tx_commercial_invoice_lines as l')->join('merch_orders as o', 'o.id', '=', 'l.order_id')->where('l.invoice_id', $i->id)->orderBy('l.sort_order')->get(['l.*', 'o.order_no', 'o.style_no', 'o.total_qty']);
        $done = $this->invoicedByOrder($rows->pluck('order_id')->all(), $i->id);
        $sizes = DB::table('merch_order_sizes')->whereIn('order_id', $rows->pluck('order_id'))->orderBy('sort_order')->get(['order_id', 'colour', 'size', 'qty'])->groupBy('order_id');

        return array_merge($i->only($i->getFillable()), collect($dates)->mapWithKeys(fn ($k) => [$k => $i->{$k}?->toDateString()])->all(), ['id' => $i->id, 'locked' => $i->final, 'lines' => $rows->map(fn ($r) => ['order_id' => $r->order_id, 'order_no' => $r->order_no, 'style_no' => $r->style_no, 'pattern' => $r->pattern, 's_all' => $r->s_all, 'hs_code' => $r->hs_code, 'hs_category' => $r->hs_category, 'description' => $r->description, 'unit' => $r->unit, 'po_qty' => (float) $r->total_qty, 'done_qty' => (float) ($done[$r->order_id] ?? 0), 'inv_qty' => (float) $r->inv_qty, 'unit_price' => (float) $r->unit_price, 'ctn_qty' => (int) $r->ctn_qty, 'net_weight' => (float) $r->net_weight, 'gross_weight' => (float) $r->gross_weight, 'cbm' => (float) $r->cbm, 'ctn_measurement' => $r->ctn_measurement, 'size_breakdown' => $r->size_breakdown ? json_decode($r->size_breakdown, true) : [], 'sizes' => $sizes->get($r->order_id, collect())->map(fn ($s) => ['colour' => $s->colour, 'size' => $s->size, 'qty' => (float) $s->qty])->values()->all()])->values()->all()]);
    }

    /** @return array<string, mixed> */
    private function lodgementBody(TxExportBill $e): array
    {
        $ids = DB::table('tx_export_bill_invoices')->where('bill_id', $e->id)->pluck('invoice_id')->all();

        return array_merge($e->only($e->getFillable()), ['id' => $e->id, 'bill_date' => $e->bill_date->toDateString(), 'locked' => $e->final, 'invoice_rows' => $this->invoiceRows($ids)]);
    }

    /** @return array<string, mixed> */
    private function boeBody(TxBillOfExchange $e): array
    {
        $ids = DB::table('tx_boe_invoices')->where('boe_id', $e->id)->pluck('invoice_id')->all();

        return array_merge($e->only($e->getFillable()), ['id' => $e->id, 'bill_date' => $e->bill_date->toDateString(), 'due_date' => $e->due_date->toDateString(), 'ref_date' => $e->ref_date?->toDateString(), 'bank_submit_date' => $e->bank_submit_date?->toDateString(), 'payment_recv_date' => $e->payment_recv_date?->toDateString(), 'locked' => $e->final, 'invoice_rows' => $this->invoiceRows($ids), 'distribution' => $this->distribution('BOE', $e->id)]);
    }

    /** @return array<string, mixed> A final bill as a realisation reads it. */
    private function boeForRealisation(TxBillOfExchange $e): array
    {
        $b = $this->book();
        $ids = DB::table('tx_boe_invoices')->where('boe_id', $e->id)->pluck('invoice_id')->all();
        $bank = $b->branches()[$e->receiving_branch_id] ?? '';

        return ['value' => $e->id, 'label' => ($e->bank_bill_ref_no ?: $e->bill_no).' — '.$e->bill_no, 'bill_no' => $e->bill_no, 'beneficiary' => $b->company(), 'bank_name' => explode(' — ', $bank)[0], 'bank_bill_type' => $e->bank_bill_type, 'currency' => $e->currency, 'local_currency' => $e->local_currency, 'rate' => $e->local_rate, 'amount' => $e->bill_amount, 'invoices' => $this->invoiceRows($ids)];
    }

    /** @return array<string, mixed> */
    private function realisationBody(TxBillRealisation $r): array
    {
        $e = TxBillOfExchange::query()->find($r->boe_id);

        return array_merge($r->only($r->getFillable()), ['id' => $r->id, 'realized_date' => $r->realized_date->toDateString(), 'locked' => $r->final, 'boe' => $e ? $this->boeForRealisation($e) : null, 'distribution' => $this->distribution('REALISATION', $r->id)]);
    }

    private function book(): ExportBook
    {
        return $this->book ??= new ExportBook($this->caller()->companyId);
    }
}
