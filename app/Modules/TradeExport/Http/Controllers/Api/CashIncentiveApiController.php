<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Http\Controllers\Api;

use App\Modules\TradeExport\Models\TxCashIncentive;
use App\Modules\TradeExport\Models\TxExportLc;
use App\Modules\TradeExport\Support\ExportBook;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Trade & Export → Cash Incentive (AnnotechERP 22/05): the claims on an
 * export L/C, each followed from application through documents, the audit
 * certificate and the bank's collection; the L/C searched by number brings
 * its invoices, realisation and PRC, and the days left to claim. Its PRC is
 * kept on the L/C ("Manage PRC").
 */
class CashIncentiveApiController extends ApiController
{
    public const TYPES = ['REGULAR' => 4, 'SPECIAL' => 1];

    /** Days after the latest realisation in which a claim is to be submitted. */
    public const CLAIM_WINDOW_DAYS = 180;

    private ?ExportBook $book = null;

    public function index(): JsonResponse
    {
        $this->allow('tradeexport.export.view');
        $rows = TxCashIncentive::query()->orderByDesc('application_date')->orderByDesc('claim_sl_no')->get();
        $lcs = TxExportLc::query()->whereIn('id', $rows->pluck('lc_id')->unique())->pluck('lc_no', 'id');

        return ApiResponse::ok($rows->map(fn (TxCashIncentive $r) => $this->claimRow($r) + ['lc_no' => $lcs[$r->lc_id] ?? '—'])->values()->all());
    }

    /** The L/C or contract found by its number, and every claim on it. */
    public function credit(Request $request): JsonResponse
    {
        $this->allow('tradeexport.export.view');
        $q = trim((string) $request->query('lc', ''));
        $l = $q === '' ? null : (TxExportLc::query()->whereRaw('lower(lc_no) = ?', [mb_strtolower($q)])->first()
            ?? TxExportLc::query()->whereRaw('lower(lc_no) like ?', ['%'.mb_strtolower($q).'%'])->orderBy('lc_no')->first());

        return ApiResponse::ok($l ? $this->creditBody($l) : null);
    }

    public function save(Request $request, ?string $id = null): JsonResponse
    {
        $this->allow('tradeexport.export.manage');
        $c = $this->caller()->companyId;
        $r = $id ? TxCashIncentive::query()->findOrFail($id) : null;
        if ($r?->final) {
            throw ValidationException::withMessages(['claim_sl_no' => __('tradeop.v.locked', ['no' => $r->claim_sl_no])]);
        }
        $lcId = $r?->lc_id ?? (string) $request->input('lc_id');
        $d = $request->validate([
            'lc_id' => [$r ? 'nullable' : 'required', Rule::exists('tx_export_lcs', 'id')->where('company_id', $c)],
            'cia_type' => ['required', Rule::in(array_keys(self::TYPES))],
            'claim_part_no' => ['required', 'integer', 'between:1,4', Rule::unique('tx_cash_incentives')->where('lc_id', $lcId)->where('cia_type', (string) $request->input('cia_type'))->ignore($r?->id)],
            'claim_sl_no' => ['required', 'string', 'max:40', Rule::unique('tx_cash_incentives')->where('company_id', $c)->ignore($r?->id)],
            'application_date' => ['required', 'date'],
            'doc_sub_date' => ['nullable', 'date', 'after_or_equal:application_date'],
            'claim_amount' => ['nullable', 'numeric', 'min:0'],
            'claim_currency' => ['nullable', 'string', 'size:3'],
            'certificate_no' => ['nullable', 'string', 'max:60'],
            'audit_currency' => ['nullable', 'string', 'size:3'],
            'audit_cert_date' => ['nullable', 'date', 'after_or_equal:application_date'],
            'audit_amount' => ['nullable', 'numeric', 'min:0'],
            'collection_date' => ['nullable', 'date', 'after_or_equal:application_date'],
            'remarks' => ['nullable', 'string', 'max:600'],
            'final' => ['boolean'],
        ], [], ExportBook::attrs());
        if (! empty($d['final']) && empty($d['collection_date'])) {
            throw ValidationException::withMessages(['collection_date' => __('tradeop.v.ci_collect')]);
        }
        $body = ['lc_id' => $lcId, 'claim_amount' => (float) ($d['claim_amount'] ?? 0), 'audit_amount' => (float) ($d['audit_amount'] ?? 0), 'claim_currency' => $d['claim_currency'] ?? 'USD', 'audit_currency' => $d['audit_currency'] ?? 'BDT', 'final' => (bool) ($d['final'] ?? false)] + $d + ['company_id' => $c];
        if ($r) {
            $r->update($body);
        } else {
            $r = TxCashIncentive::query()->create($body + ['created_by' => $this->caller()->auditUserId()]);
        }
        $l = TxExportLc::query()->findOrFail($r->lc_id);

        return $id ? ApiResponse::ok($this->creditBody($l)) : ApiResponse::created($this->creditBody($l));
    }

    /* ── Manage PRC (on the L/C) ───────────────────────────────────── */

    public function savePrc(Request $request, string $lc): JsonResponse
    {
        $this->allow('tradeexport.export.manage');
        $l = TxExportLc::query()->findOrFail($lc);
        $d = $request->validate(['prc_no' => ['nullable', 'string', 'max:60'], 'prc_date' => ['nullable', 'date', 'required_with:prc_no']], [], ExportBook::attrs());
        $no = $d['prc_no'] ?? null;
        $l->update(['prc_no' => $no, 'prc_date' => $no ? $d['prc_date'] : null, 'prc_entry_date' => $no ? ($l->prc_entry_date ?? CarbonImmutable::now('Asia/Dhaka')->toDateString()) : null]);

        return ApiResponse::ok($this->prcBody($l->fresh()));
    }

    public function prcAttach(Request $request, StoreFileAttachment $files, string $lc): JsonResponse
    {
        $this->allow('tradeexport.export.manage');
        $l = TxExportLc::query()->findOrFail($lc);
        if (! $l->prc_no) {
            throw ValidationException::withMessages(['prc_no' => __('tradeop.v.prc_first')]);
        }
        $request->validate(['upload' => ['required', 'file', 'max:10240']]);
        $upload = $request->file('upload');
        $file = $files->handle($upload, 'tx_export_lc_prc', $l->id, $this->caller()->auditUserId());
        $l->update(['prc_file_id' => $file->id, 'prc_file_name' => mb_substr($upload->getClientOriginalName(), 0, 200)]);

        return ApiResponse::ok($this->prcBody($l->fresh()));
    }

    /* ── Bodies ────────────────────────────────────────────────────── */

    /** @return array<string, mixed> */
    private function creditBody(TxExportLc $l): array
    {
        $b = $this->book();
        $invoiced = (float) DB::table('tx_commercial_invoices')->where('lc_id', $l->id)->sum('invoiced_amount');
        $real = collect($b->realised(DB::table('tx_bills_of_exchange')->where('lc_id', $l->id)->pluck('id')->all()));
        $realized = (float) $real->sum('invoice_amount');
        $latest = $real->max('realized_date');
        $countdown = null;
        if ($latest) {
            $left = (int) CarbonImmutable::now('Asia/Dhaka')->startOfDay()->diffInDays(CarbonImmutable::parse($latest)->addDays(self::CLAIM_WINDOW_DAYS), false);
            $countdown = $left >= 0 ? __('tradeop.ci_days_left', ['n' => $left]) : __('tradeop.ci_days_over', ['n' => -$left]);
        }
        $bank = $b->branches()[$l->advising_branch_id] ?? '';
        $claims = TxCashIncentive::query()->where('lc_id', $l->id)->orderBy('application_date')->orderBy('claim_part_no')->get();

        return ['id' => $l->id, 'lc_no' => $l->lc_no, 'beneficiary' => $b->company(), 'destination' => $l->port_of_discharge, 'issue_date' => $l->issue_date->toDateString(), 'applicant' => $b->buyers()[$l->buyer_id] ?? '—', 'lc_value' => $l->lc_value, 'bank' => explode(' — ', $bank)[0],
            'currency' => $l->currency, 'invoice_total' => $invoiced, 'min_rate' => $real->count() ? (float) $real->min('rate_ods') : null, 'realized' => $realized, 'realized_pct' => $invoiced > 0 ? round($realized / $invoiced * 100, 2) : null, 'latest_realized' => $latest, 'countdown' => $countdown]
            + $this->prcBody($l)
            + ['claims' => $claims->map(fn (TxCashIncentive $r) => $this->claimRow($r))->values()->all(), 'claim_total' => (float) $claims->sum('claim_amount'), 'audit_total' => (float) $claims->sum('audit_amount')];
    }

    /** @return array<string, mixed> */
    private function prcBody(TxExportLc $l): array
    {
        return ['lc_id' => $l->id, 'prc_no' => $l->prc_no, 'prc_date' => $l->prc_date?->toDateString(), 'prc_entry_date' => $l->prc_entry_date?->toDateString(), 'prc_file_id' => $l->prc_file_id, 'prc_file_name' => $l->prc_file_name];
    }

    /** @return array<string, mixed> */
    private function claimRow(TxCashIncentive $r): array
    {
        $d = fn ($v) => $v?->toDateString();

        return ['id' => $r->id, 'lc_id' => $r->lc_id, 'cia_type' => $r->cia_type, 'cia_rate' => self::TYPES[$r->cia_type] ?? null, 'claim_part_no' => $r->claim_part_no, 'claim_sl_no' => $r->claim_sl_no, 'application_date' => $d($r->application_date), 'doc_sub_date' => $d($r->doc_sub_date), 'claim_amount' => $r->claim_amount, 'claim_currency' => $r->claim_currency,
            'certificate_no' => $r->certificate_no, 'audit_currency' => $r->audit_currency, 'audit_cert_date' => $d($r->audit_cert_date), 'audit_amount' => $r->audit_amount, 'collection_date' => $d($r->collection_date), 'remarks' => $r->remarks, 'final' => $r->final, 'locked' => $r->final];
    }

    private function book(): ExportBook
    {
        return $this->book ??= new ExportBook($this->caller()->companyId);
    }
}
