<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Support;

use App\Modules\Finishing\Models\FinBatchReceipt;
use App\Modules\Finishing\Models\FinProcessDelivery;
use App\Modules\Finishing\Models\FinProcessType;
use App\Modules\Finishing\Models\FinScChallanLine;
use Illuminate\Support\Facades\DB;

/**
 * Finishing Line → S/C Process (AnnotechERP 18/03): what each challan line
 * has had back — counted from posted receipts, never typed — and moving a
 * batch out to the processor and back onto the floor.
 */
final class FinSc
{
    /**
     * Received and short kilos per challan line, from posted receipts (one receipt left out if asked).
     *
     * @param  list<string>  $lineIds
     * @return array<string, array{recv: float, short: float}>
     */
    public static function returned(array $lineIds, ?string $exceptReceiptId = null): array
    {
        if (! $lineIds) {
            return [];
        }

        return DB::table('fin_sc_receipt_lines as l')->join('fin_sc_receipts as r', 'r.id', '=', 'l.receipt_id')
            ->whereIn('l.challan_line_id', $lineIds)->where('r.status', 'POSTED')
            ->when($exceptReceiptId, fn ($q) => $q->where('r.id', '!=', $exceptReceiptId))
            ->groupBy('l.challan_line_id')->selectRaw('l.challan_line_id, sum(l.recv_kg) as recv, sum(l.short_kg) as short')->get()
            ->mapWithKeys(fn ($x) => [$x->challan_line_id => ['recv' => round((float) $x->recv, 3), 'short' => round((float) $x->short, 3)]])->all();
    }

    /** What a line still has out at the processor. */
    public static function balance(FinScChallanLine $line, array $returned): float
    {
        $r = $returned[$line->id] ?? ['recv' => 0.0, 'short' => 0.0];

        return round((float) $line->qty_kg - $r['recv'] - $r['short'], 3);
    }

    /** Send a card out to the processor. */
    public static function sendOut(FinBatchReceipt $card, string $challanNo, ?string $userId): void
    {
        FinFloor::move($card, 'AT_SUB_CONTRACT', 'SC_OUT', now(), null, $challanNo, $userId);
    }

    /** Bring a card back to the stage it left for the processor from (a challan cancelled). */
    public static function callBack(FinBatchReceipt $card, string $challanNo, ?string $userId): void
    {
        $from = FinProcessDelivery::query()->where('receipt_id', $card->id)->where('source', 'SC_OUT')->orderByDesc('delivered_at')->orderByDesc('id')->value('from_stage') ?? 'AWAITING_LOAD';
        FinFloor::move($card, $from, 'SC_BACK', now(), null, $challanNo, $userId);
    }

    /**
     * Once every posted challan line a card went out on is fully answered, the
     * card comes back onto the floor at the stage of the process done for it.
     */
    public static function bringBackIfDone(FinBatchReceipt $card, string $mrrNo, ?string $userId): void
    {
        if ($card->stage !== 'AT_SUB_CONTRACT') {
            return;
        }
        $lines = FinScChallanLine::query()->where('receipt_id', $card->id)
            ->whereIn('challan_id', DB::table('fin_sc_challans')->where('status', 'POSTED')->select('id'))->orderBy('created_at')->get();
        $returned = self::returned($lines->pluck('id')->all());
        if ($lines->isEmpty() || $lines->contains(fn ($l) => self::balance($l, $returned) > 0.0005)) {
            return;
        }
        $stage = FinProcessType::query()->whereKey($lines->last()->process_id)->value('wip_stage') ?? 'QC_INSPECTION';
        FinFloor::move($card, $stage, 'SC_IN', now(), null, $mrrNo, $userId, ['last_process_id' => $lines->last()->process_id]);
    }
}
