<?php

declare(strict_types=1);

namespace App\Modules\StoreMaterial\Support;

use App\Modules\HrPayroll\Models\HrEmployee;
use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\StoreMaterial\Models\Grn;
use App\Modules\StoreMaterial\Models\LedgerEntry;
use App\Modules\StoreMaterial\Models\Roll;
use App\Modules\StoreMaterial\Models\RollMove;
use App\Modules\StoreMaterial\Models\Stock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The store's books: the bucket a material is held in for a buyer, PO, style
 * and order; the ledger every movement is posted to; a bucket's balance; the
 * label a material goes by ("Cotton Jersey (White) — LPP / 927"); the rolls
 * fabric is held in and what each has left; and the employee a receiver's
 * ID or card number names.
 */
final class StoreBook
{
    /** The bucket for a material and whom it is held for, made on first use. */
    public static function bucket(MaterialItem $item, array $for, ?string $orderId = null): Stock
    {
        $keys = ['item_id' => $item->id] + array_map(fn ($v): string => trim((string) $v), array_merge(['buyer' => '', 'po_number' => '', 'style' => '', 'order_no' => ''], array_intersect_key($for, array_flip(['buyer', 'po_number', 'style', 'order_no']))));

        return Stock::query()->firstOrCreate($keys, ['uom' => $item->uom, 'order_id' => $orderId]);
    }

    public static function post(Stock $stock, string $type, string $reference, string $sourceId, string $date, float $in = 0, float $out = 0, float $return = 0, ?string $by = null): void
    {
        LedgerEntry::query()->create([
            'stock_id' => $stock->id, 'entry_date' => $date, 'type' => $type, 'reference' => $reference, 'source_id' => $sourceId,
            'in_qty' => $in, 'out_qty' => $out, 'return_qty' => $return, 'created_by' => $by, 'created_at' => now(),
        ]);
    }

    /** What a bucket holds now: received and returned, less issued. */
    public static function balance(string $stockId): float
    {
        return round((float) LedgerEntry::query()->where('stock_id', $stockId)->sum(DB::raw('in_qty + return_qty - out_qty')), 3);
    }

    /** @return array<string, float> stock id => balance */
    public static function balances(Collection $stockIds): array
    {
        return LedgerEntry::query()->whereIn('stock_id', $stockIds->all())->groupBy('stock_id')
            ->select('stock_id', DB::raw('sum(in_qty + return_qty - out_qty) as b'))->pluck('b', 'stock_id')
            ->map(fn ($b): float => round((float) $b, 3))->all();
    }

    public static function moveRoll(Roll $roll, string $type, string $reference, string $sourceId, string $date, float $in = 0, float $out = 0, float $return = 0, ?string $by = null): void
    {
        RollMove::query()->create([
            'roll_id' => $roll->id, 'entry_date' => $date, 'type' => $type, 'reference' => $reference, 'source_id' => $sourceId,
            'in_qty' => $in, 'out_qty' => $out, 'return_qty' => $return, 'created_by' => $by, 'created_at' => now(),
        ]);
    }

    /** The rolls in these buckets, on GRNs that stand (a reversed GRN's rolls left with it). @return Collection<int, Roll> */
    public static function rolls(Collection $stockIds): Collection
    {
        return Roll::query()->whereIn('stock_id', $stockIds->all())
            ->whereIn('grn_id', Grn::query()->where('status', 'CONFIRMED')->select('id'))->orderBy('roll_no')->get();
    }

    /**
     * Each roll's received, out, returned and available quantity.
     *
     * @return array<string, array{recv: float, out: float, returned: float, available: float}>
     */
    public static function rollTotals(Collection $rollIds): array
    {
        $totals = [];
        foreach (RollMove::query()->whereIn('roll_id', $rollIds->all())->get()->groupBy('roll_id') as $id => $moves) {
            $totals[$id] = [
                'recv' => round((float) $moves->where('type', 'GRN')->sum('in_qty'), 3), 'out' => round((float) $moves->where('type', 'MATERIAL_OUT')->sum('out_qty'), 3),
                'returned' => round((float) $moves->sum('return_qty'), 3),
                'available' => round((float) $moves->sum(fn (RollMove $m) => (float) $m->in_qty + (float) $m->return_qty - (float) $m->out_qty), 3),
            ];
        }

        return $totals;
    }

    /** A roll by its number: the one in stock on a standing GRN, else the latest of that number. */
    public static function rollNamed(string $no): ?Roll
    {
        $no = strtoupper(trim($no));

        return Roll::query()->where('roll_no', $no)->whereIn('grn_id', Grn::query()->where('status', 'CONFIRMED')->select('id'))->first()
            ?? Roll::query()->where('roll_no', $no)->latest()->first();
    }

    /** "Cotton Jersey (White)". */
    public static function material(MaterialItem $item): string
    {
        return $item->name.($item->colour ? " ({$item->colour})" : '');
    }

    /** "Cotton Jersey (White) — LPP / 927", or the material alone for general stock. */
    public static function label(Stock $stock, MaterialItem $item): string
    {
        $for = implode(' / ', array_filter([$stock->buyer, $stock->style]));

        return self::material($item).($for !== '' ? " — {$for}" : '');
    }

    /** The employee an ID or card / proxy number names, or a message as Brickland words it. */
    public static function employee(?string $id, string $field): ?HrEmployee
    {
        $id = trim((string) $id);
        if ($id === '') {
            return null;
        }

        return HrEmployee::query()->where(fn ($q) => $q->where('employee_code', $id)->orWhere('card_no', $id))->first()
            ?? throw ValidationException::withMessages([$field => __('storematerial.no_employee', ['id' => $id])]);
    }

    /** "50001001 (Mohammad Sayem)". */
    public static function person(?HrEmployee $e): ?string
    {
        return $e ? "{$e->card_no} ({$e->name})" : null;
    }

    public static function qty(float $n): string
    {
        return rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.');
    }
}
