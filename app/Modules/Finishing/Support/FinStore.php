<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Support;

use App\Modules\Finishing\Models\FinStoreLot;
use App\Modules\Finishing\Models\FinStoreMove;
use App\Shared\Fabric\FabricLength;
use Illuminate\Support\Facades\DB;

/**
 * The finished fabric store's ledger (AnnotechERP 18/05): a lot's balance is
 * the sum of its moves, read afresh every time — nothing typed, nothing kept.
 */
final class FinStore
{
    /**
     * Kilos, metres and rolls on hand per lot (one document's own moves left out if asked).
     * Fabric is counted by length (2026-11-16); a lot received before lengths were kept has none.
     *
     * @param  list<string>  $lotIds
     * @return array<string, array{kg: float, m: float, rolls: int}>
     */
    public static function balances(array $lotIds, ?string $exceptDocId = null): array
    {
        if (! $lotIds) {
            return [];
        }

        return DB::table('fin_store_moves')->whereIn('lot_id', $lotIds)->when($exceptDocId, fn ($q) => $q->where('doc_id', '!=', $exceptDocId))
            ->groupBy('lot_id')->selectRaw('lot_id, sum(qty_kg) as kg, sum(coalesce(qty_m, 0)) as m, sum(rolls) as rolls')->get()
            ->mapWithKeys(fn ($m) => [$m->lot_id => ['kg' => round((float) $m->kg, 3), 'm' => round((float) $m->m, 3), 'rolls' => (int) $m->rolls]])->all();
    }

    public static function book(FinStoreLot $lot, string $kind, float $kg, int $rolls, string $date, string $docType, string $docId, string $docNo, ?string $userId, ?float $metres = null): void
    {
        FinStoreMove::query()->create(['lot_id' => $lot->id, 'kind' => $kind, 'qty_kg' => round($kg, 3), 'qty_m' => $metres === null ? null : round($metres, 3), 'rolls' => $rolls, 'move_date' => $date, 'doc_type' => $docType, 'doc_id' => $docId, 'doc_no' => $docNo, 'created_by' => $userId]);
    }

    public static function unbook(string $docType, string $docId): void
    {
        FinStoreMove::query()->where('doc_type', $docType)->where('doc_id', $docId)->delete();
    }

    /**
     * The store's lots as the forms pick them: "FSL-2026110001 · LOT-B11 · Black · 98.5 kg".
     *
     * @return list<array<string, mixed>>
     */
    public static function lots(?string $storeId = null): array
    {
        $lots = FinStoreLot::query()->when($storeId, fn ($q) => $q->where('store_id', $storeId))->orderByDesc('received_on')->orderByDesc('lot_code')->get();
        $bal = self::balances($lots->pluck('id')->all());
        $orders = DB::table('merch_orders')->whereIn('id', $lots->pluck('order_id')->filter())->pluck('order_no', 'id');
        $batches = FinFloor::batches(DB::table('fin_batch_receipts')->whereIn('id', $lots->pluck('receipt_id'))->pluck('batch_id')->all());
        $batchOf = DB::table('fin_batch_receipts')->whereIn('id', $lots->pluck('receipt_id'))->pluck('batch_id', 'id');

        return $lots->map(function (FinStoreLot $l) use ($bal, $orders, $batches, $batchOf) {
            $b = $bal[$l->id] ?? ['kg' => 0.0, 'm' => 0.0, 'rolls' => 0];
            $batch = $batches[$batchOf[$l->receipt_id] ?? ''] ?? null;

            $len = $l->received_m === null ? '' : ' · '.rtrim(rtrim(number_format((float) FabricLength::fromMetres($b['m']), 3, '.', ''), '0'), '.').' '.strtolower(FabricLength::unit());

            return ['value' => $l->id, 'label' => trim("{$l->lot_code} · ".($l->lot_no ?: '—').' · '.($l->colour ?: '—').$len.' · '.rtrim(rtrim(number_format($b['kg'], 3, '.', ''), '0'), '.').' kg'),
                'lot_code' => $l->lot_code, 'store_id' => $l->store_id, 'lot_no' => $l->lot_no, 'colour' => $l->colour, 'style' => $l->style, 'buyer_id' => $l->buyer_id, 'order_no' => $orders[$l->order_id] ?? null,
                'batch_no' => $batch?->batch_no, 'description' => trim(($l->fabric_type ?? '').' '.($l->fabric_details ?? '')), 'received_kg' => round((float) $l->received_kg, 3), 'balance_kg' => $b['kg'], 'balance_rolls' => $b['rolls'],
                'received_len' => FabricLength::fromMetres($l->received_m), 'balance_len' => $l->received_m === null ? null : FabricLength::fromMetres($b['m']), 'len_unit' => FabricLength::unit(),
                'kg_per_m' => $l->received_m ? round((float) $l->received_kg / (float) $l->received_m, 5) : null, 'fin_dia' => $l->fin_dia === null ? null : (float) $l->fin_dia,
                'shade_no' => $l->shade_no, 'dia_type' => $l->dia_type, 'fin_gsm' => $l->fin_gsm === null ? null : (float) $l->fin_gsm, 'received_on' => $l->received_on->toDateString()];
        })->values()->all();
    }
}
