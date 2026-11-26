<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Support;

use App\Modules\Merchandise\Models\AccReceipt;
use App\Modules\Merchandise\Models\AccReturn;
use App\Modules\Merchandise\Models\FabricOpeningBalance;
use App\Modules\Merchandise\Models\FabricReturn;
use App\Modules\Merchandise\Models\PurchasedFabricMrr;
use App\Modules\Merchandise\Models\StockClosing;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * The six month closings of Trims & Accessories (AnnotechERP 06/06): which
 * documents of each kind must be settled before a month closes, and the
 * guard that keeps a closed month's documents from being saved or finalised
 * again. Batch fabric and the two leftovers have no documents yet (their
 * dyeing and order-closing screens are not built), so nothing stands in
 * their way; finished fabric's only document, the opening balance, has no
 * workflow and so is never open.
 */
final class StockPeriods
{
    public const KINDS = ['purchased-fabric', 'finished-fabric', 'trims', 'batch-fabric', 'grey-leftover', 'finished-leftover'];

    /** "Jul/2026" or "2026-07" → "2026-07"; null when it is neither. */
    public static function parse(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $value, $m)) {
            return "{$m[1]}-{$m[2]}";
        }
        if (preg_match('/^([A-Za-z]{3})\/(\d{4})$/', $value, $m)) {
            $month = array_search(ucfirst(strtolower($m[1])), ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'], true);

            return $month === false ? null : sprintf('%s-%02d', $m[2], $month + 1);
        }

        return null;
    }

    /** "2026-07" → "Jul/2026", as Brickland shows a period. */
    public static function label(string $period): string
    {
        return CarbonImmutable::createFromFormat('!Y-m', $period)->format('M/Y');
    }

    /**
     * The documents of a kind dated in the month and not yet settled.
     *
     * @return list<array{document: string, doc_no: string, doc_date: string, status: string}>
     */
    public static function openDocuments(string $kind, string $period): array
    {
        $from = CarbonImmutable::createFromFormat('!Y-m', $period)->startOfMonth()->toDateString();
        $to = CarbonImmutable::createFromFormat('!Y-m', $period)->endOfMonth()->toDateString();
        $rows = [];
        $add = function (string $document, $query, string $no, string $date) use (&$rows, $from, $to): void {
            foreach ($query->whereBetween($date, [$from, $to])->orderBy($date)->orderBy($no)->get() as $r) {
                $rows[] = ['document' => $document, 'doc_no' => $r->{$no}, 'doc_date' => $r->{$date}->toDateString(), 'status' => $r->status];
            }
        };
        if ($kind === 'purchased-fabric') {
            $add('PFOB', FabricOpeningBalance::query()->whereIn('status', ['DRAFT', 'SUBMITTED', 'APPROVED']), 'ob_no', 'ob_date');
            $add('FABRIC_MRR', PurchasedFabricMrr::query()->where('status', 'DRAFT'), 'mrr_no', 'mrr_date');
            $add('FABRIC_RETURN', FabricReturn::query()->where('status', 'DRAFT'), 'challan_no', 'challan_date');
        } elseif ($kind === 'trims') {
            $add('ACC_MRR', AccReceipt::query()->where('status', 'DRAFT'), 'mrr_no', 'mrr_date');
            $add('ACC_RETURN', AccReturn::query()->where('status', 'DRAFT'), 'challan_no', 'challan_date');
        }

        return $rows;
    }

    /** A document dated in a closed month of its kind is refused. */
    public static function assertOpen(string $kind, string|CarbonInterface $date, string $field = 'status'): void
    {
        $period = CarbonImmutable::parse($date)->format('Y-m');
        if (StockClosing::query()->where('kind', $kind)->where('period', $period)->where('status', 'CLOSED')->exists()) {
            throw ValidationException::withMessages([$field => __('merchandise.clo.period_closed', ['period' => self::label($period), 'kind' => __("merchandise.master.clo.kind.{$kind}")])]);
        }
    }
}
