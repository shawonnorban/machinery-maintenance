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
 * Stock nobody has drawn on (Inventory spec §14).
 *
 * "Slow" and "non-moving" are the same question asked with two different
 * numbers, so this is one report with a threshold rather than two reports
 * with a hardcoded one: a trim store measures idleness in weeks and a
 * fabric store in seasons, and neither is wrong.
 *
 * Idleness is measured against **demand** — stock actually drawn for work
 * — rather than against every outbound row. Counting a receipt as movement
 * would hide the buying mistake this report exists to surface; counting a
 * stock-count write-off or a scrap as movement would hide it just as well,
 * since an item somebody wrote off is the opposite of an item that sells.
 * A transfer out does count: the cloth went somewhere it was wanted.
 *
 * Stock that has never moved at all is reported as such rather than
 * silently dropped — it is the worst case, not a missing one.
 */
class SlowMovingItemReport extends Report
{
    use ScopesItemStock;

    private const DEFAULT_DAYS = 90;

    /**
     * Movement that means somebody wanted the stock.
     *
     * Deliberately not `ItemStockTransaction::OUTBOUND`: that list also
     * holds `ADJUSTMENT_OUT` and `SCRAP`, so a line written off by a stock
     * count would be reported as freshly moving on the very day it was
     * written off.
     *
     * @var list<string>
     */
    private const DEMAND = ['ISSUE', 'CONSUME', 'TRANSFER_OUT'];

    public function key(): string
    {
        return 'item_slow_moving';
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
            'last_issue' => ['label' => 'report.columns.last_issue'],
            'days_idle' => ['label' => 'report.columns.days_idle', 'numeric' => true],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        $today = CarbonImmutable::today();
        $threshold = $this->thresholdDays($query);
        $cutoff = $today->subDays($threshold);
        $issues = $this->lastIssues($query);

        foreach ($this->base($query)->lazy() as $balance) {
            $last = $issues[$balance->item_id.'|'.$balance->bin_id.'|'.$balance->lot_no] ?? null;

            if ($last !== null && CarbonImmutable::parse($last)->greaterThan($cutoff)) {
                // Drawn on inside the window: moving, by this definition.
                continue;
            }

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
                // Never issued at all is the worst case, and it says so
                // rather than leaving the cell blank.
                'last_issue' => $last === null
                    ? __('report.never_issued')
                    : CarbonImmutable::parse($last)->toDateString(),
                'days_idle' => $last === null
                    ? null
                    : (int) CarbonImmutable::parse($last)->startOfDay()->diffInDays($today),
            ];
        }
    }

    /**
     * Rows on the shelf, which is the ceiling this can produce. The real
     * answer needs the issue dates, and working those out is the report.
     */
    public function estimatedRows(ReportQuery $query): int
    {
        return $this->base($query)->count();
    }

    private function thresholdDays(ReportQuery $query): int
    {
        $days = (int) $query->get('days', self::DEFAULT_DAYS);

        return $days > 0 ? $days : self::DEFAULT_DAYS;
    }

    /**
     * The most recent outbound movement per (item, bin, lot).
     *
     * @return array<string, string>
     */
    private function lastIssues(ReportQuery $query): array
    {
        $builder = ItemStockTransaction::query()
            ->whereIn('transaction_type', self::DEMAND)
            ->select('item_id', 'bin_id', 'lot_no', DB::raw('MAX(transaction_at) as last_at'))
            ->groupBy('item_id', 'bin_id', 'lot_no');

        $binIds = $this->binIdsForFactory($query);

        if ($binIds !== null) {
            $builder->whereIn('bin_id', $binIds);
        }

        $issues = [];

        foreach ($builder->get() as $row) {
            $issues[$row->item_id.'|'.$row->bin_id.'|'.$row->lot_no] = (string) $row->last_at;
        }

        return $issues;
    }

    private function base(ReportQuery $query): Builder
    {
        $builder = ItemStockBalance::query()
            ->with(['item:id,item_code,name', 'bin:id,code'])
            ->whereHas('item')
            ->where('quantity_on_hand', '>', 0);

        $builder = $this->scopeToFactory($builder, $query);
        $builder = $this->scopeToSearch($builder, $query);

        return $builder->orderBy('item_id')->orderBy('bin_id')->orderBy('lot_no');
    }
}
