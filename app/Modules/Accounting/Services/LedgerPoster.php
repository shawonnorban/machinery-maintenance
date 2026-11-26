<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Support\LedgerSources;
use App\Modules\Costing\Models\CostEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Puts Machinery's costs on Finance & Ledger's Chart of Account (the user's
 * decision of 2026-11-15) through Access Control → Ledger Integration Setup.
 *
 * Every posted cost becomes a draft journal voucher (JV, source SYSTEM) on the
 * GL source of its kind — Parts, Labour, Vendor & External Service, Other —
 * debiting its first DEBIT ledger and crediting its first CREDIT ledger. The
 * voucher waits on Draft Voucher Entry for Finance to approve and post, so
 * maker-checker still holds.
 *
 * Only the difference is ever posted: a cost that changes (a work order's
 * parts re-priced) or goes (a part line removed) posts what moved since the
 * last voucher, reversed sides for a decrease. Nothing posts while the GL
 * source is inactive, has no debit or no credit ledger, or the date has no
 * open fiscal year; the next change of that cost catches up.
 */
final class LedgerPoster
{
    private const SCALE = 4;

    private const TZ = 'Asia/Dhaka';

    /** Posts what a cost entry has moved since its last voucher; the new voucher's id, if one was raised. */
    public function syncCostEntry(CostEntry $entry, bool $gone = false): ?string
    {
        $companyId = (string) $entry->getAttribute('company_id');
        $kind = $entry->getAttribute('is_reversal') && $entry->getAttribute('reverses_cost_entry_id')
            ? DB::table('cost_entries')->where('id', $entry->getAttribute('reverses_cost_entry_id'))->value('source_type')
            : $entry->getAttribute('source_type');
        $ref = LedgerSources::COST_SOURCE[$kind] ?? null;
        if ($ref === null) {
            return null;
        }

        $posted = (string) (DB::table('acc_ledger_postings')->where('company_id', $companyId)
            ->where('source_type', 'cost_entry')->where('source_id', $entry->getKey())->sum('amount') ?: '0');
        $target = $gone ? '0' : $this->money((string) $entry->getAttribute('base_amount'));
        $delta = bcsub($target, $this->money($posted), self::SCALE);
        if (bccomp($delta, '0', self::SCALE) === 0) {
            return null;
        }

        LedgerSources::ensure($companyId);
        $source = DB::table('acc_gl_sources')->where('company_id', $companyId)->where('link_ref', $ref)->where('active', true)->first();
        if ($source === null) {
            return null;
        }
        $lines = DB::table('acc_gl_source_lines')->where('gl_source_id', $source->id)->orderBy('sort_order')->get();
        $debit = $lines->firstWhere('side', 'DEBIT');
        $credit = $lines->firstWhere('side', 'CREDIT');
        if ($debit === null || $credit === null) {
            return null;
        }

        $date = CarbonImmutable::parse($entry->getAttribute('occurred_at'))->setTimezone(self::TZ)->toDateString();
        $openYear = DB::table('acc_fiscal_years')->where('company_id', $companyId)->where('fy_start', '<=', $date)->where('fy_end', '>=', $date)->where('closed', false)->exists();
        if (! $openYear) {
            return null;
        }

        // A decrease posts the other way round.
        $amount = ltrim($delta, '-');
        [$dr, $cr] = bccomp($delta, '0', self::SCALE) > 0 ? [$debit, $credit] : [$credit, $debit];

        $workOrder = $entry->getAttribute('work_order_id')
            ? DB::table('work_orders')->where('id', $entry->getAttribute('work_order_id'))->first(['work_order_number', 'title'])
            : null;
        $asset = DB::table('assets')->where('id', $entry->getAttribute('asset_id'))->first(['asset_code', 'name']);
        $narration = trim(implode(' — ', array_filter([
            $source->name,
            $workOrder?->work_order_number,
            $asset ? "{$asset->asset_code} {$asset->name}" : null,
            $entry->getAttribute('description'),
            bccomp($delta, '0', self::SCALE) < 0 ? 'reversal' : null,
        ])));
        $user = $entry->getAttribute('posted_by');

        return DB::transaction(function () use ($companyId, $entry, $source, $dr, $cr, $amount, $date, $workOrder, $narration, $user, $delta): string {
            $now = now();
            $voucherId = strtolower((string) Str::ulid());
            DB::table('acc_vouchers')->insert([
                'id' => $voucherId, 'company_id' => $companyId, 'voucher_ref_no' => $this->nextRef($companyId, $date), 'voucher_date' => $date,
                'voucher_type' => 'JV', 'trx_source' => 'SYSTEM', 'bill_no' => $workOrder?->work_order_number, 'voucher_for_type' => 'GENERAL',
                'narration' => mb_substr($narration, 0, 2000), 'action_status' => 'CREATE_VOUCHER', 'posting_status' => 'DRAFT', 'total' => (float) $amount,
                'created_by' => $user, 'updated_by' => $user, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $line = fn (object $l, string $side, int $sort) => [
                'id' => strtolower((string) Str::ulid()), 'company_id' => $companyId, 'voucher_id' => $voucherId, 'account_id' => $l->account_id,
                'cost_center_id' => $l->cost_center_id, 'bill_no' => $workOrder?->work_order_number, 'note' => $l->note, 'ref_no' => null,
                'currency' => 'BDT', 'rate' => 1, 'value' => null, 'debit' => $side === 'DEBIT' ? (float) $amount : 0, 'credit' => $side === 'CREDIT' ? (float) $amount : 0,
                'sort_order' => $sort, 'created_at' => $now, 'updated_at' => $now,
            ];
            $rows = [$line($dr, 'DEBIT', 1), $line($cr, 'CREDIT', 2)];
            DB::table('acc_voucher_lines')->insert($rows);

            $voucher = DB::table('acc_vouchers')->where('id', $voucherId)->first();
            DB::table('acc_voucher_logs')->insert([
                'id' => strtolower((string) Str::ulid()), 'company_id' => $companyId, 'voucher_id' => $voucherId, 'voucher_no' => null,
                'voucher_ref_no' => $voucher->voucher_ref_no, 'action' => 'CREATE', 'posting_status' => 'DRAFT',
                'snapshot' => json_encode(['voucher' => $voucher, 'lines' => $rows], JSON_UNESCAPED_UNICODE), 'user_id' => $user, 'created_at' => $now,
            ]);

            DB::table('acc_ledger_postings')->insert([
                'id' => (string) Str::ulid(), 'company_id' => $companyId, 'source_type' => 'cost_entry', 'source_id' => $entry->getKey(),
                'gl_source_id' => $source->id, 'voucher_id' => $voucherId, 'amount' => $delta, 'created_at' => $now, 'updated_at' => $now,
            ]);

            return $voucherId;
        });
    }

    /** VR-YYYY-000001, the draft reference Voucher Entry gives, per company and year. */
    private function nextRef(string $companyId, string $date): string
    {
        $base = 'VR-'.substr($date, 0, 4).'-';
        $last = DB::table('acc_vouchers')->where('company_id', $companyId)->where('voucher_ref_no', 'like', $base.'%')->orderByDesc('voucher_ref_no')->value('voucher_ref_no');

        return $base.str_pad((string) ((int) substr((string) $last, strlen($base)) + 1), 6, '0', STR_PAD_LEFT);
    }

    private function money(string $value): string
    {
        return bcadd($value === '' ? '0' : $value, '0', self::SCALE);
    }
}
