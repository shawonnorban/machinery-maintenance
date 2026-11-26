<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Http\Controllers\Api;

use App\Modules\TradeExport\Models\TxBillOfExchange;
use App\Modules\TradeExport\Models\TxCmReceipt;
use App\Modules\TradeExport\Models\TxExportLc;
use App\Modules\TradeExport\Support\ExportBook;
use App\Modules\TradeExport\Support\ImportBook;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Trade & Export → CM Payment Receive (AnnotechERP 22/04): the CM payment
 * received against a final bill of exchange (never past its amount, every
 * receipt of the bill in the grid below), the bill's submit and payment
 * dates at the bank (Bill Adjustment's "Manage Bill Submit and Payment
 * Date"), and the Margin Closing list of the advances raised for margin,
 * commission, interest or bank charge.
 */
class CmPaymentApiController extends ApiController
{
    public const MARGIN_TYPES = ['MARGIN', 'COMMISSION', 'INTEREST', 'BANK_CHARGE'];

    private ?ExportBook $book = null;

    /* ── CM Payment Receipts ───────────────────────────────────────── */

    public function receipts(): JsonResponse
    {
        $this->allow('tradeexport.export.view');
        $b = $this->book();
        $rows = TxCmReceipt::query()->orderByDesc('receive_date')->orderByDesc('receipt_no')->get();
        $boes = TxBillOfExchange::query()->whereIn('id', $rows->pluck('boe_id')->unique())->get()->keyBy('id');
        $lcs = TxExportLc::query()->whereIn('id', $boes->pluck('lc_id')->filter()->unique())->get()->keyBy('id');

        return ApiResponse::ok($rows->map(function (TxCmReceipt $r) use ($b, $boes, $lcs) {
            $e = $boes[$r->boe_id] ?? null;
            $lc = $e?->lc_id ? ($lcs[$e->lc_id] ?? null) : null;

            return ['id' => $r->id, 'receipt_no' => $r->receipt_no, 'final' => $r->final, 'bill_no' => $e?->bill_no, 'bank_bill_type' => $r->bank_bill_type, 'bank_bill_ref_no' => $e?->bank_bill_ref_no, 'bill_date' => $e?->bill_date->toDateString(), 'bill_amount' => $e?->bill_amount, 'buyer' => $e ? ($b->buyers()[$e->buyer_id] ?? '—') : '—',
                'lc_no' => $lc?->lc_no, 'lc_value' => $lc?->lc_value, 'submit_date' => ($e?->bank_submit_date ?? $e?->ref_date)?->toDateString(), 'receive_date' => $r->receive_date->toDateString(), 'amount' => $r->amount, 'locked' => $r->final];
        })->values()->all());
    }

    /** The final bills a receipt can be raised on. */
    public function bills(): JsonResponse
    {
        $this->allow('tradeexport.export.view');

        return ApiResponse::ok(TxBillOfExchange::query()->where('final', true)->orderByDesc('bill_date')->get()->map(fn (TxBillOfExchange $e) => ['value' => $e->id, 'label' => $e->bill_no.($e->bank_bill_ref_no ? ' · '.$e->bank_bill_ref_no : '')])->values()->all());
    }

    /** What a bill brings to a receipt, and every receipt already on it. */
    public function source(Request $request, string $boe): JsonResponse
    {
        $this->allow('tradeexport.export.view');

        return ApiResponse::ok($this->billBody(TxBillOfExchange::query()->findOrFail($boe)));
    }

    public function receipt(string $id): JsonResponse
    {
        $this->allow('tradeexport.export.view');
        $r = TxCmReceipt::query()->findOrFail($id);

        return ApiResponse::ok($this->receiptBody($r));
    }

    public function save(Request $request, ?string $id = null): JsonResponse
    {
        $this->allow('tradeexport.export.manage');
        $c = $this->caller()->companyId;
        $r = $id ? TxCmReceipt::query()->findOrFail($id) : null;
        if ($r?->final) {
            throw ValidationException::withMessages(['boe_id' => __('tradeop.v.locked', ['no' => $r->receipt_no])]);
        }
        $d = $request->validate([
            'boe_id' => ['required', Rule::exists('tx_bills_of_exchange', 'id')->where('company_id', $c)], 'bank_bill_type' => ['required', Rule::in(ExportBook::BANK_BILL_TYPES)], 'amount' => ['required', 'numeric', 'gt:0'], 'receive_date' => ['required', 'date'], 'exchange_rate' => ['nullable', 'numeric', 'min:0'], 'dfc_margin' => ['nullable', 'numeric', 'min:0'],
        ], [], ExportBook::attrs());
        $e = TxBillOfExchange::query()->findOrFail($d['boe_id']);
        if (! $e->final) {
            throw ValidationException::withMessages(['boe_id' => __('tradeop.v.real_boe', ['no' => $e->bill_no])]);
        }
        $other = (float) TxCmReceipt::query()->where('boe_id', $e->id)->when($r, fn ($q) => $q->where('id', '!=', $r->id))->sum('amount');
        if ($other + (float) $d['amount'] > $e->bill_amount + 0.005) {
            throw ValidationException::withMessages(['amount' => __('tradeop.v.cm_amount', ['no' => $e->bill_no, 'left' => $this->book()->num(max(0.0, $e->bill_amount - $other))])]);
        }
        $body = $d + ['company_id' => $c, 'dfc_margin' => (float) ($d['dfc_margin'] ?? 0)];
        if ($r) {
            $r->update($body);
        } else {
            $r = TxCmReceipt::query()->create($body + ['receipt_no' => (new ImportBook($c))->nextNo('CMR', 'tx_cm_receipts', 'receipt_no', $d['receive_date']), 'created_by' => $this->caller()->auditUserId()]);
        }

        return $id ? ApiResponse::ok($this->receiptBody($r->fresh())) : ApiResponse::created($this->receiptBody($r->fresh()));
    }

    public function final(string $id): JsonResponse
    {
        $this->allow('tradeexport.export.manage');
        $r = TxCmReceipt::query()->findOrFail($id);
        $r->update(['final' => true]);

        return ApiResponse::ok(['id' => $r->id, 'final' => true]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->allow('tradeexport.export.manage');
        $r = TxCmReceipt::query()->findOrFail($id);
        if ($r->final) {
            throw ValidationException::withMessages(['boe_id' => __('tradeop.v.locked', ['no' => $r->receipt_no])]);
        }
        $r->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    /* ── Bill Adjustment → Manage Bill Submit and Payment Date ─────── */

    public function billDates(string $lc): JsonResponse
    {
        $this->allow('tradeexport.export.view');
        TxExportLc::query()->findOrFail($lc);

        return ApiResponse::ok(TxBillOfExchange::query()->where('lc_id', $lc)->orderBy('bill_date')->get()->map(fn (TxBillOfExchange $e) => ['id' => $e->id, 'bill_no' => $e->bill_no, 'bill_date' => $e->bill_date->toDateString(), 'bill_amount' => $e->bill_amount, 'bank_submit_date' => $e->bank_submit_date?->toDateString(), 'payment_recv_date' => $e->payment_recv_date?->toDateString(), 'bank_bill_ref_no' => $e->bank_bill_ref_no, 'final' => $e->final])->values()->all());
    }

    public function saveBillDates(Request $request): JsonResponse
    {
        $this->allow('tradeexport.export.manage');
        $c = $this->caller()->companyId;
        $d = $request->validate(['rows' => ['required', 'array', 'min:1'], 'rows.*.id' => ['required', Rule::exists('tx_bills_of_exchange', 'id')->where('company_id', $c)], 'rows.*.bank_submit_date' => ['nullable', 'date'], 'rows.*.payment_recv_date' => ['nullable', 'date']]);
        foreach ($d['rows'] as $n => $row) {
            $e = TxBillOfExchange::query()->findOrFail($row['id']);
            $submit = $row['bank_submit_date'] ?? null;
            $paid = $row['payment_recv_date'] ?? null;
            if ($submit && $submit < $e->bill_date->toDateString()) {
                throw ValidationException::withMessages(["rows.{$n}.bank_submit_date" => __('tradeop.v.bd_submit', ['no' => $e->bill_no])]);
            }
            if ($paid && $submit && $paid < $submit) {
                throw ValidationException::withMessages(["rows.{$n}.payment_recv_date" => __('tradeop.v.bd_paid', ['no' => $e->bill_no])]);
            }
            $e->update(['bank_submit_date' => $submit, 'payment_recv_date' => $paid]);
        }

        return ApiResponse::ok(['saved' => count($d['rows'])]);
    }

    /* ── Margin Closing ────────────────────────────────────────────── */

    public function margins(): JsonResponse
    {
        $this->allow('tradeexport.export.view');
        $b = $this->book();

        return ApiResponse::ok(TxBillOfExchange::query()->whereNotNull('margin_type')->orderByDesc('created_at')->get()->map(fn (TxBillOfExchange $e) => ['id' => $e->id, 'bill_no' => $e->bill_no, 'bank_id' => $b->branchBank($e->receiving_branch_id), 'bank' => explode(' — ', $b->branches()[$e->receiving_branch_id] ?? '—')[0], 'margin_type' => $e->margin_type, 'amount' => $e->bill_amount, 'created_on' => $e->created_at?->timezone('Asia/Dhaka')->toDateString(), 'final' => $e->final, 'locked' => $e->final])->values()->all());
    }

    /* ── Bodies ────────────────────────────────────────────────────── */

    /** @return array<string, mixed> */
    private function billBody(TxBillOfExchange $e): array
    {
        $b = $this->book();
        $lc = $e->lc_id ? TxExportLc::query()->find($e->lc_id) : null;
        $invoices = DB::table('tx_boe_invoices as x')->join('tx_commercial_invoices as i', 'i.id', '=', 'x.invoice_id')->where('x.boe_id', $e->id)->pluck('i.invoice_no');
        $rows = TxCmReceipt::query()->where('boe_id', $e->id)->orderBy('receive_date')->get();

        return ['boe_id' => $e->id, 'bill_no' => $e->bill_no, 'lc_no' => $lc?->lc_no, 'buyer' => $b->buyers()[$e->buyer_id] ?? '—', 'lc_value' => $lc?->lc_value, 'bill_date' => $e->bill_date->toDateString(), 'invoices' => $invoices->implode(', '), 'bank_bill_type' => $e->bank_bill_type, 'submit_date' => ($e->bank_submit_date ?? $e->ref_date)?->toDateString(), 'bill_amount' => $e->bill_amount, 'bank_bill_ref_no' => $e->bank_bill_ref_no, 'rate' => $e->local_rate,
            'receipts' => $rows->map(fn (TxCmReceipt $r) => ['id' => $r->id, 'receipt_no' => $r->receipt_no, 'receive_date' => $r->receive_date->toDateString(), 'amount' => $r->amount, 'dfc_margin' => $r->dfc_margin, 'final' => $r->final])->values()->all(),
            'received' => (float) $rows->sum('amount'), 'dfc' => (float) $rows->sum('dfc_margin')];
    }

    /** @return array<string, mixed> */
    private function receiptBody(TxCmReceipt $r): array
    {
        return array_merge($r->only($r->getFillable()), ['id' => $r->id, 'receive_date' => $r->receive_date->toDateString(), 'locked' => $r->final]);
    }

    private function book(): ExportBook
    {
        return $this->book ??= new ExportBook($this->caller()->companyId);
    }
}
