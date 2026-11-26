<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Support;

use App\Modules\Knitting\Models\KnitCard;
use App\Modules\Knitting\Models\KnitGreyLot;
use App\Modules\Knitting\Models\KnitGreyMonthClose;
use App\Modules\Knitting\Models\KnitGreyMove;
use App\Modules\Knitting\Models\KnitProdOrder;
use App\Modules\Knitting\Models\KnitRoll;
use App\Modules\Merchandise\Models\Order;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The grey fabric ledger Knit Production → Fabric Store (AnnotechERP 15/05)
 * keeps: a stock line per store, opening kind, order, party, shade, fabric,
 * dia, GSM and roll (a weighed roll is a line of its own); every document
 * posts signed weights (and roll counts) into it; a struck month takes no
 * more movement.
 */
final class GreyStore
{
    /** The stock line these particulars name, made the first time they are met. */
    public static function lotFor(array $a, string $date, float $rate = 0.0): KnitGreyLot
    {
        $low = fn ($v) => mb_strtolower(trim((string) ($v ?? '')));
        $parts = [$a['store_id'], $a['kind'] ?? 'IN_HOUSE', $a['order_id'] ?? '', $a['party_id'] ?? '', $a['roll_id'] ?? '', mb_strtoupper(trim((string) ($a['roll_no'] ?? ''))),
            $a['colour_id'] ?? '', $low($a['colour_name'] ?? null), $low($a['fabric']), $low($a['finish_dia'] ?? null), isset($a['gsm']) && $a['gsm'] !== '' ? round((float) $a['gsm'], 2) : '', $low($a['part'] ?? null)];
        $key = implode('|', $parts);
        // A line held on a dye batch is its own (older keys, without one, stay as they were).
        $batch = mb_strtoupper(trim((string) ($a['batch_no'] ?? '')));
        if ($batch !== '') {
            $key .= '|B:'.$batch;
        }
        $lot = KnitGreyLot::query()->where('lot_key', $key)->first();
        if ($lot) {
            $patch = array_filter(['bin_id' => $a['bin_id'] ?? null, 'rate' => $rate > 0 ? $rate : null]);
            if ($patch) {
                $lot->update($patch);
            }

            return $lot;
        }

        return KnitGreyLot::query()->create([
            'lot_key' => $key, 'store_id' => $a['store_id'], 'kind' => $a['kind'] ?? 'IN_HOUSE', 'prod_type' => $a['prod_type'] ?? null, 'buyer_id' => $a['buyer_id'] ?? null,
            'order_id' => $a['order_id'] ?? null, 'party_id' => $a['party_id'] ?? null, 'roll_id' => $a['roll_id'] ?? null,
            'roll_no' => isset($a['roll_no']) && trim((string) $a['roll_no']) !== '' ? mb_strtoupper(trim((string) $a['roll_no'])) : null,
            'fabric_type_id' => $a['fabric_type_id'] ?? null, 'fabric' => trim((string) $a['fabric']), 'colour_id' => $a['colour_id'] ?? null,
            'colour_name' => ($a['colour_name'] ?? null) ?: null, 'finish_dia' => ($a['finish_dia'] ?? null) ?: null, 'gsm' => isset($a['gsm']) && $a['gsm'] !== '' ? $a['gsm'] : null,
            'part' => ($a['part'] ?? null) ?: null, 'batch_no' => $batch !== '' ? $batch : null, 'bin_id' => $a['bin_id'] ?? null, 'rate' => $rate, 'first_date' => $date,
        ]);
    }

    /** The same fabric's line somewhere else — another store, another order, a batch (a movement's landing line). */
    public static function lotIn(KnitGreyLot $from, string $storeId, string $date, array $overrides = []): KnitGreyLot
    {
        return self::lotFor(array_merge(collect($from->only(['kind', 'prod_type', 'buyer_id', 'order_id', 'party_id', 'roll_id', 'roll_no', 'fabric_type_id', 'fabric', 'colour_id',
            'colour_name', 'finish_dia', 'gsm', 'part', 'batch_no']))->all(), $overrides, ['store_id' => $storeId]), $date, (float) $from->rate);
    }

    /**
     * Post a document's movement: what it posted before under this kind is replaced.
     *
     * @param  list<array{lot_id: string, qty: float, rolls?: int, value?: float, doc_line_id?: ?string}>  $entries
     */
    public static function post(string $kind, string $docNo, string $date, array $entries): void
    {
        self::unpost($kind, $docNo);
        foreach ($entries as $e) {
            self::move($kind, $docNo, $date, $e);
        }
    }

    /** One more entry under a document (a roll scanned in on its own). */
    public static function move(string $kind, string $docNo, string $date, array $e): void
    {
        if (abs((float) $e['qty']) < 0.00005 && empty($e['rolls'])) {
            return;
        }
        $lot = KnitGreyLot::query()->findOrFail($e['lot_id']);
        KnitGreyMove::query()->create([
            'lot_id' => $lot->id, 'store_id' => $lot->store_id, 'move_date' => $date, 'kind' => $kind, 'doc_no' => $docNo, 'doc_line_id' => $e['doc_line_id'] ?? null,
            'qty' => round((float) $e['qty'], 4), 'rolls' => (int) ($e['rolls'] ?? 0), 'value' => round((float) ($e['value'] ?? 0), 4),
        ]);
    }

    public static function unpost(string $kind, string $docNo, ?string $docLineId = null): void
    {
        KnitGreyMove::query()->where('kind', $kind)->where('doc_no', $docNo)->when($docLineId, fn ($q) => $q->where('doc_line_id', $docLineId))->delete();
    }

    /** A struck month takes no more movement. */
    public static function assertOpen(string $date, string $field = 'date'): void
    {
        $d = CarbonImmutable::parse($date);
        if (KnitGreyMonthClose::query()->where('period', $d->format('Y-m'))->exists()) {
            throw ValidationException::withMessages([$field => __('knitting.fs.v.month_closed', ['period' => $d->format('F Y')])]);
        }
    }

    public static function balance(string $lotId): float
    {
        return round((float) KnitGreyMove::query()->where('lot_id', $lotId)->sum('qty'), 4);
    }

    /**
     * Stock lines with their balance and rolls (a store's, those holding stock), named for pickers.
     *
     * @return list<array<string, mixed>>
     */
    public static function lots(?string $storeId = null, bool $holding = true, ?string $orderId = null): array
    {
        $lots = KnitGreyLot::query()->when($storeId, fn ($q) => $q->where('store_id', $storeId))->when($orderId, fn ($q) => $q->where('order_id', $orderId))
            ->orderBy('first_date')->get();
        $sums = KnitGreyMove::query()->whereIn('lot_id', $lots->pluck('id'))->groupBy('lot_id')->selectRaw('lot_id, sum(qty) as q, sum(rolls) as r')->get()->keyBy('lot_id');
        $names = self::names($lots);
        $out = [];
        foreach ($lots as $l) {
            $qty = round((float) ($sums[$l->id]->q ?? 0), 4);
            if ($holding && $qty <= 0.00005) {
                continue;
            }
            $out[] = self::describe($l, $names) + ['balance' => $qty, 'rolls' => (int) ($sums[$l->id]->r ?? 0)];
        }

        return $out;
    }

    /** The names a set of stock lines is shown with. */
    public static function names(Collection $lots): array
    {
        $orders = Order::query()->whereIn('id', $lots->pluck('order_id')->filter()->unique())->get(['id', 'order_no', 'style_no', 'work_style_no', 'job_no'])->keyBy('id');

        return [
            'orders' => $orders,
            'buyers' => DB::table('buyers')->whereIn('id', $lots->pluck('buyer_id')->filter()->unique())->get(['id', 'name', 'short_name'])->keyBy('id'),
            'parties' => DB::table('mp_suppliers')->whereIn('id', $lots->pluck('party_id')->filter()->unique())->pluck('name', 'id'),
            'stores' => DB::table('stores')->whereIn('id', $lots->pluck('store_id')->unique())->pluck('name', 'id'),
            'bins' => DB::table('bins')->whereIn('id', $lots->pluck('bin_id')->filter()->unique())->pluck('name', 'id'),
            'types' => DB::table('fabric_types')->whereIn('id', $lots->pluck('fabric_type_id')->filter()->unique())->pluck('name', 'id'),
            'rolls' => KnitRoll::query()->whereIn('id', $lots->pluck('roll_id')->filter())->get(['id', 'roll_code', 'quality'])->keyBy('id'),
        ];
    }

    /** @return array<string, mixed> */
    public static function describe(KnitGreyLot $l, ?array $names = null): array
    {
        $names ??= self::names(collect([$l]));
        $o = $names['orders'][$l->order_id] ?? null;
        $b = $names['buyers'][$l->buyer_id] ?? null;
        $roll = $names['rolls'][$l->roll_id] ?? null;
        $rollNo = $roll?->roll_code ?? $l->roll_no;
        $shade = $l->colour_name;

        return [
            'lot_id' => $l->id, 'store_id' => $l->store_id, 'store' => $names['stores'][$l->store_id] ?? null, 'kind' => $l->kind, 'prod_type' => $l->prod_type,
            'buyer_id' => $l->buyer_id, 'buyer' => $b ? ($b->short_name ?: $b->name) : null, 'party_id' => $l->party_id, 'party' => $names['parties'][$l->party_id] ?? null,
            'order_id' => $l->order_id, 'order_no' => $o?->order_no, 'style' => $o ? ($o->work_style_no ?: $o->style_no ?: $o->job_no) : null,
            'roll_id' => $l->roll_id, 'roll_no' => $rollNo, 'quality' => $roll?->quality, 'fabric' => $l->fabric, 'fabric_type' => $names['types'][$l->fabric_type_id] ?? null,
            'colour_id' => $l->colour_id, 'colour' => $shade, 'finish_dia' => $l->finish_dia, 'gsm' => $l->gsm, 'part' => $l->part,
            'batch_no' => $l->batch_no, 'bin_id' => $l->bin_id, 'rack' => $names['bins'][$l->bin_id] ?? null, 'rate' => (float) $l->rate, 'first_date' => $l->first_date->toDateString(),
            'label' => implode(' · ', array_filter([$rollNo, $l->fabric, $shade, $o?->order_no])),
        ];
    }

    /**
     * What a knitted roll's stock line is struck on: its production order's
     * kind, buyer, order and fabric, the card's shade and dia.
     *
     * @return array<string, mixed>
     */
    public static function rollFacts(KnitRoll $roll): array
    {
        $card = KnitCard::query()->findOrFail($roll->card_id);
        $po = KnitProdOrder::query()->findOrFail($card->prod_order_id);

        return [
            'kind' => 'IN_HOUSE', 'prod_type' => $po->order_kind, 'buyer_id' => $po->buyer_id, 'order_id' => $po->order_id, 'roll_id' => $roll->id,
            'fabric_type_id' => $po->fabric_type_id, 'fabric' => $po->fabric_desc, 'colour_id' => $card->colour_name ? null : $po->colour_id,
            'colour_name' => $card->colour_name ?: $po->colour_name, 'finish_dia' => $card->finish_dia ?: $po->finish_dia, 'gsm' => $po->gsm,
        ];
    }

    public static function nextNumber(string $prefix, string $table, string $column, string $date): string
    {
        return KnitProduction::nextNumber($prefix, $table, $column, $date);
    }

    public static function companyName(): ?string
    {
        return DB::table('companies')->where('id', app(TenantContext::class)->companyId())->value('name');
    }
}
