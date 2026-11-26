<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Http\Controllers\Api;

use App\Modules\TradeExport\Models\TxBillOfEntry;
use App\Modules\TradeExport\Models\TxImportAcceptance;
use App\Modules\TradeExport\Models\TxImportLc;
use App\Modules\TradeExport\Models\TxUd;
use App\Modules\TradeExport\Models\TxUdTransfer;
use App\Modules\TradeExport\Support\ExportBook;
use App\Modules\TradeExport\Support\ImportBook;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Trade & Export → Import (AnnotechERP 22/03): the Customs Bill of Entry (the
 * goods received against the L/C's PIs, each MRR line entered once) and the
 * Import Bill Acceptance (a numbered bill of exchange inside the L/C and its
 * tolerance); and → Operations (22/02): the Utilisation Declaration over B2B
 * L/Cs and the transfer of entitlement between two declarations.
 */
class ImportBillApiController extends ApiController
{
    private ?ExportBook $export = null;

    private ?ImportBook $import = null;

    /* ── Customs Bill of Entry ─────────────────────────────────────── */

    public function entries(): JsonResponse
    {
        $this->allow('tradeexport.import.view');
        $rows = TxBillOfEntry::query()->orderByDesc('be_date')->orderByDesc('be_no')->get();
        $lcs = $this->lcMap($rows->pluck('lc_id')->all());
        $users = DB::table('users')->whereIn('id', $rows->pluck('entry_by')->filter())->pluck('name', 'id');

        return ApiResponse::ok($rows->map(fn (TxBillOfEntry $e) => ['id' => $e->id, 'be_no' => $e->be_no, 'final' => $e->final, 'be_date' => $e->be_date->toDateString(), 'bill_amount' => $e->bill_amount, 'custom_name' => $e->custom_name, 'lc_id' => $e->lc_id, 'lc_no' => $lcs[$e->lc_id] ?? null, 'entry_by' => $e->entry_by, 'entry_by_name' => $users[$e->entry_by] ?? null, 'remarks' => $e->remarks, 'file_id' => $e->file_id, 'file_name' => $e->file_name, 'locked' => $e->final])->values()->all());
    }

    public function entrySource(Request $request, string $lc): JsonResponse
    {
        $this->allow('tradeexport.import.view');
        $l = TxImportLc::query()->findOrFail($lc);

        return ApiResponse::ok($this->lcHead($l) + ['lines' => $this->import()->receipts($this->piLines($l->id), $request->query('except'))]);
    }

    public function entry(string $id): JsonResponse
    {
        $this->allow('tradeexport.import.view');

        return ApiResponse::ok($this->entryBody(TxBillOfEntry::query()->findOrFail($id)));
    }

    public function saveEntry(Request $request, ?string $id = null): JsonResponse
    {
        $this->allow('tradeexport.import.manage');
        $c = $this->caller()->companyId;
        $e = $id ? TxBillOfEntry::query()->findOrFail($id) : null;
        if ($e?->final) {
            throw ValidationException::withMessages(['be_no' => __('tradeop.v.locked', ['no' => $e->be_no])]);
        }
        $d = $request->validate([
            'lc_id' => ['required', Rule::exists('tx_import_lcs', 'id')->where('company_id', $c)], 'be_no' => ['required', 'string', 'max:60', Rule::unique('tx_bills_of_entry', 'be_no')->where('company_id', $c)->ignore($e?->id)], 'be_date' => ['required', 'date'], 'entry_by' => ['nullable', Rule::exists('users', 'id')], 'custom_code' => ['nullable', Rule::in(ImportBook::CUSTOMS)], 'custom_name' => ['nullable', 'string', 'max:120'],
            'bill_amount' => ['required', 'numeric', 'gt:0'], 'currency' => ['required', Rule::exists('currencies', 'code')->where('company_id', $c)], 'exchange_rate' => ['required', 'numeric', 'gt:0'], 'local_currency' => ['nullable', 'string', 'max:3'], 'remarks' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'], 'lines.*.pi_line_id' => ['required', 'string'], 'lines.*.mrr_no' => ['required', 'string', 'max:40'], 'lines.*.receive_qty' => ['required', 'numeric', 'gt:0'],
        ], ['lines.required' => __('tradeop.v.be_lines')], ExportBook::attrs());
        $l = TxImportLc::query()->findOrFail($d['lc_id']);
        $source = collect($this->import()->receipts($this->piLines($l->id), $e?->id))->keyBy('key');
        foreach ($d['lines'] as $line) {
            $row = $source[$line['pi_line_id'].'|'.$line['mrr_no']] ?? null;
            if (! $row) {
                throw ValidationException::withMessages(['lines' => __('tradeop.v.be_line', ['mrr' => $line['mrr_no']])]);
            }
            if ((float) $line['receive_qty'] > $row['balance_qty'] + 0.0005) {
                throw ValidationException::withMessages(['lines' => __('tradeop.v.be_qty', ['mrr' => $line['mrr_no'], 'left' => $this->export()->num($row['balance_qty'])])]);
            }
        }
        $saved = DB::transaction(function () use ($d, $e, $c, $source) {
            $body = collect($d)->except(['lines'])->all() + ['company_id' => $c, 'equivalent_amount' => round($d['bill_amount'] * $d['exchange_rate'], 4)];
            $body['local_currency'] = $d['local_currency'] ?? 'BDT';
            $body['entry_by'] = $d['entry_by'] ?? $this->caller()->auditUserId();
            $e ? $e->update($body) : $e = TxBillOfEntry::query()->create($body + ['created_by' => $this->caller()->auditUserId()]);
            DB::table('tx_bill_of_entry_lines')->where('be_id', $e->id)->delete();
            foreach (array_values($d['lines']) as $i => $line) {
                $row = $source[$line['pi_line_id'].'|'.$line['mrr_no']];
                DB::table('tx_bill_of_entry_lines')->insert(['id' => (string) Str::ulid(), 'company_id' => $c, 'be_id' => $e->id, 'pi_line_id' => $line['pi_line_id'], 'mrr_no' => $line['mrr_no'], 'challan_no' => $row['challan_no'], 'receive_qty' => $line['receive_qty'], 'receive_amount' => round($line['receive_qty'] * $row['unit_price'], 4), 'sort_order' => $i, 'created_at' => now(), 'updated_at' => now()]);
            }

            return $e;
        });

        return $id ? ApiResponse::ok($this->entryBody($saved->fresh())) : ApiResponse::created($this->entryBody($saved->fresh()));
    }

    public function entryAttach(Request $request, StoreFileAttachment $files, string $id): JsonResponse
    {
        $this->allow('tradeexport.import.manage');
        $e = TxBillOfEntry::query()->findOrFail($id);
        if ($e->final) {
            throw ValidationException::withMessages(['be_no' => __('tradeop.v.locked', ['no' => $e->be_no])]);
        }
        $request->validate(['upload' => ['required', 'file', 'max:10240']]);
        $upload = $request->file('upload');
        $file = $files->handle($upload, 'tx_bill_of_entry', $e->id, $this->caller()->auditUserId());
        $e->update(['file_id' => $file->id, 'file_name' => mb_substr($upload->getClientOriginalName(), 0, 200)]);

        return ApiResponse::ok(['id' => $e->id, 'file_id' => $file->id, 'attachment' => $e->file_name]);
    }

    public function entryFinal(string $id): JsonResponse
    {
        $this->allow('tradeexport.import.manage');
        $e = TxBillOfEntry::query()->findOrFail($id);
        $e->update(['final' => true]);

        return ApiResponse::ok(['id' => $e->id, 'final' => true]);
    }

    public function entryDestroy(string $id): JsonResponse
    {
        $this->allow('tradeexport.import.manage');
        $e = TxBillOfEntry::query()->findOrFail($id);
        if ($e->final) {
            throw ValidationException::withMessages(['be_no' => __('tradeop.v.locked', ['no' => $e->be_no])]);
        }
        $e->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    /* ── Import Bill Acceptance ────────────────────────────────────── */

    public function acceptances(): JsonResponse
    {
        $this->allow('tradeexport.import.view');
        $b = $this->export();
        $rows = TxImportAcceptance::query()->orderByDesc('acceptance_date')->orderByDesc('boe_no')->get();
        $lcs = $this->lcMap($rows->pluck('lc_id')->all());
        $banks = TxImportLc::query()->whereIn('id', $rows->pluck('lc_id')->unique())->pluck('sender_branch_id', 'id');

        return ApiResponse::ok($rows->map(fn (TxImportAcceptance $a) => ['id' => $a->id, 'boe_no' => $a->boe_no, 'final' => $a->final, 'lc_no' => $lcs[$a->lc_id] ?? null, 'accounting_done' => $a->accounting_done, 'payment_type' => $a->payment_type, 'invoiced_amount' => $a->invoiced_amount, 'ifdbc_no' => $a->ifdbc_no, 'maturity_date' => $a->maturity_date->toDateString(), 'ext_maturity_date' => $a->ext_maturity_date?->toDateString(), 'ifdbc_amount' => $a->ifdbc_amount, 'paid_amount' => $a->paid_amount, 'open_date' => $a->open_date->toDateString(), 'paid_date' => $a->paid_date?->toDateString(), 'b2b_qty' => $a->b2b_qty, 'acceptance_date' => $a->acceptance_date->toDateString(), 'is_pending' => $a->is_pending, 'bank_id' => $b->branchBank($banks[$a->lc_id] ?? null), 'locked' => $a->final])->values()->all());
    }

    public function acceptanceSource(Request $request, string $lc): JsonResponse
    {
        $this->allow('tradeexport.import.view');

        return ApiResponse::ok($this->acceptanceSourceBody(TxImportLc::query()->findOrFail($lc)));
    }

    public function acceptance(string $id): JsonResponse
    {
        $this->allow('tradeexport.import.view');

        return ApiResponse::ok($this->acceptanceBody(TxImportAcceptance::query()->findOrFail($id)));
    }

    public function saveAcceptance(Request $request, ?string $id = null): JsonResponse
    {
        $this->allow('tradeexport.import.manage');
        $c = $this->caller()->companyId;
        $a = $id ? TxImportAcceptance::query()->findOrFail($id) : null;
        if ($a?->final) {
            throw ValidationException::withMessages(['boe_no' => __('tradeop.v.locked', ['no' => $a->boe_no])]);
        }
        $pending = $request->boolean('is_pending');
        $d = $request->validate([
            'lc_id' => ['required', Rule::exists('tx_import_lcs', 'id')->where('company_id', $c)], 'invoiced_amount' => ['required', 'numeric', 'gt:0'], 'currency' => ['required', Rule::exists('currencies', 'code')->where('company_id', $c)], 'acceptance_date' => ['required', 'date'], 'ifdbc_no' => ['required', 'string', 'max:60'], 'ifdbc_amount' => [$pending ? 'nullable' : 'required', 'numeric', 'min:0'], 'is_pending' => ['boolean'], 'open_date' => ['required', 'date'], 'maturity_date' => ['required', 'date', 'after_or_equal:open_date'], 'ext_maturity_date' => ['nullable', 'date', 'after_or_equal:maturity_date'], 'b2b_qty' => ['nullable', 'numeric', 'min:0'], 'attached_document' => ['nullable', 'string', 'max:200'], 'payment_type' => ['required', Rule::in(ImportBook::PAYMENT_TYPES)], 'exchange_rate' => ['nullable', 'numeric', 'min:0'], 'local_currency' => ['nullable', 'string', 'max:3'], 'remarks' => ['nullable', 'string'], 'final' => ['boolean'],
        ], ['maturity_date.after_or_equal' => __('tradeop.v.ac_maturity')], ExportBook::attrs());
        $l = TxImportLc::query()->findOrFail($d['lc_id']);
        if ($l->status === 'DRAFT') {
            throw ValidationException::withMessages(['lc_id' => __('tradeop.v.inv_lc_draft', ['no' => $l->ref_no])]);
        }
        $other = (float) TxImportAcceptance::query()->where('lc_id', $l->id)->when($a, fn ($q) => $q->where('id', '!=', $a->id))->sum('invoiced_amount');
        $cap = round($l->lc_value * (1 + $l->credit_tolerance_pct / 100), 4);
        if ($other + (float) $d['invoiced_amount'] > $cap + 0.005) {
            throw ValidationException::withMessages(['invoiced_amount' => __('tradeop.v.ac_lc', ['lc' => $l->ref_no, 'left' => $this->export()->num(max(0.0, $cap - $other))])]);
        }
        $body = $d + ['company_id' => $c];
        foreach (['ifdbc_amount', 'b2b_qty'] as $k) {
            $body[$k] = (float) ($d[$k] ?? 0);
        }
        $body['is_pending'] = $pending;
        $body['final'] = (bool) ($d['final'] ?? false);
        $body['local_currency'] = $d['local_currency'] ?? 'BDT';
        if ($a) {
            $a->update($body);
        } else {
            $a = TxImportAcceptance::query()->create($body + ['boe_no' => $this->import()->nextNo('IBE', 'tx_import_acceptances', 'boe_no', $d['acceptance_date']), 'created_by' => $this->caller()->auditUserId()]);
        }

        return $id ? ApiResponse::ok($this->acceptanceBody($a->fresh())) : ApiResponse::created($this->acceptanceBody($a->fresh()));
    }

    public function acceptanceDestroy(string $id): JsonResponse
    {
        $this->allow('tradeexport.import.manage');
        $a = TxImportAcceptance::query()->findOrFail($id);
        if ($a->final) {
            throw ValidationException::withMessages(['boe_no' => __('tradeop.v.locked', ['no' => $a->boe_no])]);
        }
        $a->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    /* ── Utilisation Declaration ───────────────────────────────────── */

    public function uds(): JsonResponse
    {
        $this->allow('tradeexport.export.view');
        $rows = TxUd::query()->orderByDesc('ud_date')->orderByDesc('ud_no')->get();
        $lcs = DB::table('tx_ud_lcs as x')->join('tx_import_lcs as l', 'l.id', '=', 'x.import_lc_id')->leftJoin('tx_export_lcs as e', 'e.id', '=', 'l.export_lc_id')->whereIn('x.ud_id', $rows->pluck('id'))->get(['x.ud_id', 'e.lc_no as export', 'l.ref_no as b2b'])->groupBy('ud_id');

        return ApiResponse::ok($rows->map(fn (TxUd $u) => ['id' => $u->id, 'ud_no' => $u->ud_no, 'final' => $u->final, 'export_lc' => $lcs->get($u->id, collect())->pluck('export')->filter()->unique()->implode(', '), 'b2b' => $lcs->get($u->id, collect())->pluck('b2b')->implode(', '), 'ud_date' => $u->ud_date->toDateString(), 'authority' => $u->authority, 'amendment_no' => $u->amendment_no, 'locked' => $u->final])->values()->all());
    }

    /** B2B L/Cs not on another declaration, as the UD grid shows them. */
    public function udLcs(Request $request): JsonResponse
    {
        $this->allow('tradeexport.export.view');
        $taken = DB::table('tx_ud_lcs')->when($request->query('except'), fn ($q, $v) => $q->where('ud_id', '!=', $v))->pluck('import_lc_id');

        return ApiResponse::ok($this->udRows(TxImportLc::query()->where('status', '!=', 'DRAFT')->whereNotIn('id', $taken)->pluck('id')->all()));
    }

    public function ud(string $id): JsonResponse
    {
        $this->allow('tradeexport.export.view');

        return ApiResponse::ok($this->udBody(TxUd::query()->findOrFail($id)));
    }

    public function saveUd(Request $request, ?string $id = null): JsonResponse
    {
        $this->allow('tradeexport.export.manage');
        $c = $this->caller()->companyId;
        $u = $id ? TxUd::query()->findOrFail($id) : null;
        if ($u?->final) {
            throw ValidationException::withMessages(['ud_no' => __('tradeop.v.locked', ['no' => $u->ud_no])]);
        }
        $d = $request->validate(['ud_no' => ['required', 'string', 'max:60', Rule::unique('tx_uds', 'ud_no')->where('company_id', $c)->ignore($u?->id)], 'ud_date' => ['required', 'date'], 'authority' => ['required', Rule::in(ImportBook::AUTHORITIES)], 'lcs' => ['required', 'array', 'min:1'], 'lcs.*' => ['required', Rule::exists('tx_import_lcs', 'id')->where('company_id', $c)]], ['lcs.required' => __('tradeop.v.ud_lcs')], ExportBook::attrs());
        $ids = array_values(array_unique($d['lcs']));
        $taken = DB::table('tx_ud_lcs as x')->join('tx_uds as o', 'o.id', '=', 'x.ud_id')->join('tx_import_lcs as l', 'l.id', '=', 'x.import_lc_id')->whereIn('x.import_lc_id', $ids)->when($u, fn ($q) => $q->where('x.ud_id', '!=', $u->id))->first(['l.ref_no', 'o.ud_no']);
        if ($taken) {
            throw ValidationException::withMessages(['lcs' => __('tradeop.v.ud_taken', ['lc' => $taken->ref_no, 'ud' => $taken->ud_no])]);
        }
        $saved = DB::transaction(function () use ($d, $u, $c, $ids) {
            $body = collect($d)->except(['lcs'])->all() + ['company_id' => $c];
            $u ? $u->update($body) : $u = TxUd::query()->create($body + ['created_by' => $this->caller()->auditUserId()]);
            DB::table('tx_ud_lcs')->where('ud_id', $u->id)->delete();
            foreach ($ids as $lc) {
                DB::table('tx_ud_lcs')->insert(['id' => (string) Str::ulid(), 'company_id' => $c, 'ud_id' => $u->id, 'import_lc_id' => $lc, 'created_at' => now(), 'updated_at' => now()]);
            }

            return $u;
        });

        return $id ? ApiResponse::ok($this->udBody($saved->fresh())) : ApiResponse::created($this->udBody($saved->fresh()));
    }

    public function udFinal(string $id): JsonResponse
    {
        $this->allow('tradeexport.export.manage');
        $u = TxUd::query()->findOrFail($id);
        $u->update(['final' => true]);

        return ApiResponse::ok(['id' => $u->id, 'final' => true]);
    }

    public function udDestroy(string $id): JsonResponse
    {
        $this->allow('tradeexport.export.manage');
        $u = TxUd::query()->findOrFail($id);
        if ($u->final || DB::table('tx_ud_transfers')->where('from_ud_id', $u->id)->orWhere('to_ud_id', $u->id)->exists()) {
            throw ValidationException::withMessages(['ud_no' => __('tradeop.v.delete_draft', ['no' => $u->ud_no])]);
        }
        $u->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    /* ── Utilisation Declaration Transfer ──────────────────────────── */

    public function transfers(): JsonResponse
    {
        $this->allow('tradeexport.export.view');
        $rows = TxUdTransfer::query()->orderByDesc('created_at')->orderByDesc('id')->get();
        $uds = DB::table('tx_uds')->whereIn('id', $rows->pluck('from_ud_id')->merge($rows->pluck('to_ud_id'))->unique())->pluck('ud_no', 'id');

        return ApiResponse::ok($rows->map(fn (TxUdTransfer $t) => ['id' => $t->id, 'ref' => ($uds[$t->from_ud_id] ?? '—').' → '.($uds[$t->to_ud_id] ?? '—').' · '.$t->material, 'from_ud' => $uds[$t->from_ud_id] ?? '—', 'to_ud' => $uds[$t->to_ud_id] ?? '—', 'material' => $t->material, 'transfer_qty' => $t->transfer_qty, 'status' => $t->status, 'created_on' => $t->created_at?->timezone('Asia/Dhaka')->toDateString(), 'locked' => $t->status === 'APPROVED'])->values()->all());
    }

    /** What the From declaration holds of a commodity. */
    public function transferSource(Request $request): JsonResponse
    {
        $this->allow('tradeexport.export.view');
        $d = $request->validate(['material' => ['required', Rule::in(array_keys(ImportBook::MATERIALS))], 'from' => ['required', 'string']]);

        return ApiResponse::ok($this->entitlement($d['material'], $d['from'], $request->query('except')));
    }

    public function transfer(string $id): JsonResponse
    {
        $this->allow('tradeexport.export.view');

        return ApiResponse::ok($this->transferBody(TxUdTransfer::query()->findOrFail($id)));
    }

    public function saveTransfer(Request $request, ?string $id = null): JsonResponse
    {
        $this->allow('tradeexport.export.manage');
        $c = $this->caller()->companyId;
        $t = $id ? TxUdTransfer::query()->findOrFail($id) : null;
        if ($t?->status === 'APPROVED') {
            throw ValidationException::withMessages(['from_ud_id' => __('tradeop.v.ud_approved')]);
        }
        $d = $request->validate(['material' => ['required', Rule::in(array_keys(ImportBook::MATERIALS))], 'from_ud_id' => ['required', Rule::exists('tx_uds', 'id')->where('company_id', $c)], 'to_ud_id' => ['required', 'different:from_ud_id', Rule::exists('tx_uds', 'id')->where('company_id', $c)], 'export_qty' => ['nullable', 'numeric', 'min:0'], 'loss_pct' => ['nullable', 'numeric', 'min:0', 'max:100'], 'transfer_qty' => ['required', 'numeric', 'gt:0'], 'status' => ['required', Rule::in(['DRAFT', 'APPROVED'])]], ['to_ud_id.different' => __('tradeop.v.ud_same')], ExportBook::attrs());
        $has = $this->entitlement($d['material'], $d['from_ud_id'], $t?->id);
        $export = (float) ($d['export_qty'] ?? 0);
        $pct = (float) ($d['loss_pct'] ?? 0);
        $loss = round($export * $pct / 100, 4);
        $balance = round($has['receive_qty'] - $has['transferred'] - $export - $loss, 4);
        if ((float) $d['transfer_qty'] > $balance + 0.0005) {
            throw ValidationException::withMessages(['transfer_qty' => __('tradeop.v.ud_balance', ['left' => $this->export()->num(max(0.0, $balance))])]);
        }
        $body = $d + ['company_id' => $c, 'receive_qty' => $has['receive_qty'], 'export_qty' => $export, 'loss_pct' => $pct, 'loss_qty' => $loss, 'balance_qty' => $balance];
        if ($t) {
            $t->update($body);
        } else {
            $t = TxUdTransfer::query()->create($body + ['created_by' => $this->caller()->auditUserId()]);
        }

        return $id ? ApiResponse::ok($this->transferBody($t->fresh())) : ApiResponse::created($this->transferBody($t->fresh()));
    }

    public function transferDestroy(string $id): JsonResponse
    {
        $this->allow('tradeexport.export.manage');
        $t = TxUdTransfer::query()->findOrFail($id);
        if ($t->status === 'APPROVED') {
            throw ValidationException::withMessages(['from_ud_id' => __('tradeop.v.ud_approved')]);
        }
        $t->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    /* ── Shared ────────────────────────────────────────────────────── */

    /** @return array{receive_qty: float, transferred: float} */
    private function entitlement(string $material, string $udId, ?string $except): array
    {
        $lcs = DB::table('tx_ud_lcs as x')->join('tx_import_lcs as l', 'l.id', '=', 'x.import_lc_id')->where('x.ud_id', $udId)->where('l.commodity_type', ImportBook::MATERIALS[$material])->pluck('l.id');
        $received = (float) DB::table('tx_bill_of_entry_lines as y')->join('tx_bills_of_entry as e', 'e.id', '=', 'y.be_id')->whereIn('e.lc_id', $lcs)->sum('y.receive_qty');
        $out = (float) TxUdTransfer::query()->where('from_ud_id', $udId)->where('material', $material)->when($except, fn ($q) => $q->where('id', '!=', $except))->sum('transfer_qty');

        return ['receive_qty' => $received, 'transferred' => $out];
    }

    /** @return array<string, string> L/C id => its number as the papers print it. */
    private function lcMap(array $ids): array
    {
        return TxImportLc::query()->whereIn('id', $ids)->get()->mapWithKeys(fn (TxImportLc $l) => [$l->id => $this->lcNo($l)])->all();
    }

    private function lcNo(TxImportLc $l): string
    {
        return implode('', array_filter([$l->lc_bank_code, $l->lc_year, $l->lc_code, $l->lc_sl_no], fn ($v) => $v !== null && $v !== '')) ?: $l->ref_no;
    }

    /** @return array<string, mixed> */
    private function lcHead(TxImportLc $l): array
    {
        $b = $this->export();

        return ['lc_id' => $l->id, 'lc_no' => $this->lcNo($l), 'supplier' => DB::table('mp_suppliers')->where('id', $l->supplier_id)->value('name'), 'company' => $b->company(), 'amount' => $l->lc_value, 'currency' => $l->currency, 'issue_date' => $l->issue_date->toDateString(), 'export_lc' => $l->export_lc_id ? DB::table('tx_export_lcs')->where('id', $l->export_lc_id)->value('lc_no') : null];
    }

    private function piLines(string $lcId)
    {
        return DB::table('tx_import_pi_lines as l')->join('tx_import_pis as p', 'p.id', '=', 'l.pi_id')->join('tx_import_lc_pis as x', 'x.pi_id', '=', 'p.id')->where('x.lc_id', $lcId)->orderBy('p.pi_no')->orderBy('l.sort_order')->get(['l.id', 'l.po_kind', 'l.po_line_id', 'l.po_no', 'l.item', 'l.uom', 'l.qty', 'l.unit_price', 'l.amount', 'p.pi_no']);
    }

    /** @return array<string, mixed> */
    private function entryBody(TxBillOfEntry $e): array
    {
        $pi = $this->piLines($e->lc_id)->keyBy('id');
        $lines = DB::table('tx_bill_of_entry_lines')->where('be_id', $e->id)->orderBy('sort_order')->get()->map(function ($r) use ($pi) {
            $p = $pi[$r->pi_line_id] ?? null;

            return ['key' => $r->pi_line_id.'|'.$r->mrr_no, 'pi_line_id' => $r->pi_line_id, 'pi_no' => $p?->pi_no, 'po_no' => $p?->po_no, 'mrr_no' => $r->mrr_no, 'challan_no' => $r->challan_no, 'item' => $p?->item, 'hs_code' => null, 'uom' => $p?->uom, 'receive_qty' => (float) $r->receive_qty];
        })->all();

        return array_merge($e->only($e->getFillable()), ['id' => $e->id, 'be_date' => $e->be_date->toDateString(), 'locked' => $e->final, 'lines' => $lines]);
    }

    /** @return array<string, mixed> */
    private function acceptanceSourceBody(TxImportLc $l): array
    {
        $b = $this->export();
        $rows = TxImportAcceptance::query()->where('lc_id', $l->id)->orderBy('acceptance_date')->get();
        $total = (float) $rows->sum('invoiced_amount');
        $pis = DB::table('tx_import_lc_pis as x')->join('tx_import_pis as p', 'p.id', '=', 'x.pi_id')->where('x.lc_id', $l->id)->get(['p.id', 'p.pi_no', 'p.pi_date', 'p.net_amount']);
        $qty = DB::table('tx_import_pi_lines')->whereIn('pi_id', $pis->pluck('id'))->groupBy('pi_id')->selectRaw('pi_id, sum(qty) as q')->pluck('q', 'pi_id');

        return $this->lcHead($l) + ['lc_type' => $b->reference('LC_TYPE', $l->lc_type), 'tolerance_amount' => round($l->lc_value * $l->credit_tolerance_pct / 100, 4), 'tenor' => trim(($b->reference('PAYMENT_TERM', $l->payment_term) ?? '').($l->tenor_days ? ' / '.$l->tenor_days : '')), 'is_deferred' => $l->tenor_days > 0, 'commodity' => $l->commodity_type, 'sender_bank' => $b->branches()[$l->sender_branch_id] ?? null, 'lean_bank' => $b->branches()[$l->lean_branch_id] ?? null,
            'pis' => $pis->map(fn ($p) => ['pi_no' => $p->pi_no, 'qty' => (float) ($qty[$p->id] ?? 0), 'amount' => (float) $p->net_amount, 'pi_date' => $p->pi_date])->all(),
            'bills' => $rows->map(fn (TxImportAcceptance $a) => ['id' => $a->id, 'boe_no' => $a->boe_no, 'acceptance_date' => $a->acceptance_date->toDateString(), 'ifdbc_no' => $a->ifdbc_no, 'open_date' => $a->open_date->toDateString(), 'maturity_date' => $a->maturity_date->toDateString(), 'ifdbc_amount' => $a->ifdbc_amount, 'paid_amount' => $a->paid_amount, 'paid_date' => $a->paid_date?->toDateString(), 'final' => $a->final])->values()->all(), 'total_bill' => $total, 'lc_due' => round($l->lc_value - $total, 4)];
    }

    /** @return array<string, mixed> */
    private function acceptanceBody(TxImportAcceptance $a): array
    {
        return array_merge($a->only($a->getFillable()), ['id' => $a->id, 'acceptance_date' => $a->acceptance_date->toDateString(), 'open_date' => $a->open_date->toDateString(), 'maturity_date' => $a->maturity_date->toDateString(), 'ext_maturity_date' => $a->ext_maturity_date?->toDateString(), 'paid_date' => $a->paid_date?->toDateString(), 'locked' => $a->final]);
    }

    /** @return list<array<string, mixed>> */
    private function udRows(array $ids): array
    {
        $b = $this->export();
        $rows = TxImportLc::query()->whereIn('id', $ids)->orderBy('issue_date')->get();
        $exports = DB::table('tx_export_lcs')->whereIn('id', $rows->pluck('export_lc_id')->filter())->get(['id', 'lc_no', 'issue_date', 'lc_value'])->keyBy('id');

        return $rows->map(function (TxImportLc $l) use ($b, $exports) {
            $e = $exports[$l->export_lc_id] ?? null;

            return ['id' => $l->id, 'company' => $b->company(), 'buyer' => $b->buyers()[$l->buyer_id] ?? '—', 'export_lc' => $e?->lc_no, 'export_date' => $e?->issue_date, 'export_value' => $e ? (float) $e->lc_value : null, 'b2b' => $this->lcNo($l), 'b2b_date' => $l->issue_date->toDateString(), 'b2b_value' => $l->lc_value];
        })->values()->all();
    }

    /** @return array<string, mixed> */
    private function udBody(TxUd $u): array
    {
        $ids = DB::table('tx_ud_lcs')->where('ud_id', $u->id)->pluck('import_lc_id')->all();

        return array_merge($u->only($u->getFillable()), ['id' => $u->id, 'ud_date' => $u->ud_date->toDateString(), 'locked' => $u->final, 'lc_rows' => $this->udRows($ids)]);
    }

    /** @return array<string, mixed> */
    private function transferBody(TxUdTransfer $t): array
    {
        $uds = DB::table('tx_uds')->whereIn('id', [$t->from_ud_id, $t->to_ud_id])->pluck('ud_no', 'id');

        return array_merge($t->only($t->getFillable()), ['id' => $t->id, 'ref' => ($uds[$t->from_ud_id] ?? '—').' → '.($uds[$t->to_ud_id] ?? '—').' · '.$t->material, 'locked' => $t->status === 'APPROVED']);
    }

    private function export(): ExportBook
    {
        return $this->export ??= new ExportBook($this->caller()->companyId);
    }

    private function import(): ImportBook
    {
        return $this->import ??= new ImportBook($this->caller()->companyId);
    }
}
