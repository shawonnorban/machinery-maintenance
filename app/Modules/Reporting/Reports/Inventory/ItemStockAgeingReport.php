<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Inventory;

use App\Modules\ItemMaster\Models\ItemStockBalance;
use App\Modules\ItemMaster\Models\ItemStockTransaction;
use App\Modules\Reporting\Reports\Inventory\Concerns\ScopesItemStock;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * How long the stock has been sitting (Inventory spec §14).
 *
 * Age is measured from the **last time stock of that kind arrived**, not
 * from the first: what is on the shelf today is a pool, and the ledger
 * keeps a weighted average rather than layers, so there is no honest way
 * to say which particular metre came in when. Dating the pool from its
 * most recent arrival is the conservative reading — it reports stock as
 * younger than it might be, which is the direction that does not invent
 * write-offs.
 *
 * That is worth stating plainly on the report, and the brackets say it:
 * a line in "over 180 days" has had nothing arrive for six months, which
 * for a dye or a trim is the question worth asking.
 */
class ItemStockAgeingReport extends Report
{
    use ScopesItemStock;

    /** Upper bound of each bracket, in days; null is the open-ended last one. */
    private const BRACKETS = [30, 60, 90, 180, null];

    public function key(): string
    {
        return 'item_stock_ageing';
    }

    public function group(): string
    {
        return 'inventory';
    }

    public function permission(): string
    {
        return 'inventory.stock.view';
    }

    public function filters(): array
    {
        return ['search', 'factory'];
    }

    public function columns(): array
    {
        return [
            'item_code' => ['label' => 'report.columns.item_code'],
            'item_name' => ['label' => 'report.columns.name'],
            'bin' => ['label' => 'report.columns.bin'],
            'lot_no' => ['label' => 'report.columns.lot_no'],
            'on_hand' => ['label' => 'report.columns.on_hand', 'numeric' => true],
            'value' => ['label' => 'report.columns.stock_value', 'numeric' => true],
            'last_receipt' => ['label' => 'report.columns.last_receipt'],
            'days_held' => ['label' => 'report.columns.days_held', 'numeric' => true],
            'bracket' => ['label' => 'report.columns.age_bracket'],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        $today = CarbonImmutable::today();
        $arrivals = $this->lastArrivals($query);

        foreach ($this->base($query)->lazy() as $balance) {
            $key = $this->key_($balance);
            $last = $arrivals[$key] ?? null;
            $days = $last === null ? null : (int) CarbonImmutable::parse($last)->startOfDay()->diffInDays($today);

            yield [
                'item_code' => $balance->item?->item_code,
                'item_name' => $balance->item?->name,
                'bin' => $balance->bin?->code,
                'lot_no' => $balance->lot_no === '' ? null : $balance->lot_no,
                'on_hand' => (string) $balance->quantity_on_hand,
                'value' => bcmul(
                    (string) $balance->quantity_on_hand,
                    (string) $balance->weighted_average_cost,
                    4,
                ),
                'last_receipt' => $last === null ? null : CarbonImmutable::parse($last)->toDateString(),
                'days_held' => $days,
                'bracket' => $this->bracketFor($days),
            ];
        }
    }

    protected function countable(ReportQuery $query): Builder
    {
        return $this->base($query);
    }

    /**
     * The most recent inbound movement per (item, bin, lot).
     *
     * Read in one grouped query rather than per row: a store with ten
     * thousand balance lines would otherwise mean ten thousand round trips
     * to answer a question about dates.
     *
     * @return array<string, string>
     */
    private function lastArrivals(ReportQuery $query): array
    {
        $builder = ItemStockTransaction::query()
            ->whereIn('transaction_type', ItemStockTransaction::INBOUND)
            ->select('item_id', 'bin_id', 'lot_no', DB::raw('MAX(transaction_at) as last_at'))
            ->groupBy('item_id', 'bin_id', 'lot_no');

        $binIds = $this->binIdsForFactory($query);

        if ($binIds !== null) {
            $builder->whereIn('bin_id', $binIds);
        }

        $arrivals = [];

        foreach ($builder->get() as $row) {
            $arrivals[$row->item_id.'|'.$row->bin_id.'|'.$row->lot_no] = (string) $row->last_at;
        }

        return $arrivals;
    }

    private function key_(ItemStockBalance $balance): string
    {
        return $balance->item_id.'|'.$balance->bin_id.'|'.$balance->lot_no;
    }

    private function bracketFor(?int $days): string
    {
        if ($days === null) {
            // On the shelf with no inbound movement behind it — an opening
            // figure, or stock whose history predates the ledger.
            return __('report.age_brackets.unknown');
        }

        foreach (self::BRACKETS as $limit) {
            if ($limit === null) {
                break;
            }

            if ($days <= $limit) {
                return __('report.age_brackets.upto', ['days' => $limit]);
            }
        }

        return __('report.age_brackets.over', ['days' => 180]);
    }

    private function base(ReportQuery $query): Builder
    {
        $builder = ItemStockBalance::query()
            ->with(['item:id,item_code,name', 'bin:id,code'])
            ->whereHas('item')
            // Nothing on the shelf has no age worth reporting.
            ->where('quantity_on_hand', '>', 0);

        $builder = $this->scopeToFactory($builder, $query);
        $builder = $this->scopeToSearch($builder, $query);

        return $builder->orderBy('item_id')->orderBy('bin_id')->orderBy('lot_no');
    }
}
