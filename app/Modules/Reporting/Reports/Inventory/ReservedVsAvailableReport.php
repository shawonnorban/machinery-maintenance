<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Inventory;

use App\Modules\ItemMaster\Models\ItemReservation;
use App\Modules\ItemMaster\Models\ItemStockBalance;
use App\Modules\Reporting\Reports\Inventory\Concerns\ScopesItemStock;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * What is spoken for, and by whom (Inventory spec §14, §7).
 *
 * The valuation report shows a reserved column; this one answers the
 * question that column provokes — *who* is holding it. A storekeeper
 * looking at "40 reserved" and unable to find out for what will either
 * issue it anyway or leave it untouched for a year, and both are worse
 * than being told.
 *
 * Earmarks are counted separately from holds. A claim on stock that has
 * not arrived yet encumbers nothing on the shelf (`bin_id` is null), so
 * folding the two together would overstate what is unavailable and
 * understate what can still be sold out of the store.
 */
class ReservedVsAvailableReport extends Report
{
    use ScopesItemStock;

    public function key(): string
    {
        return 'item_reserved_vs_available';
    }

    public function group(): string
    {
        return 'inventory';
    }

    public function permission(): string
    {
        return 'itemmaster.reservation.view_any';
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
            'reserved' => ['label' => 'report.columns.reserved', 'numeric' => true],
            'available' => ['label' => 'report.columns.available', 'numeric' => true],
            'holders' => ['label' => 'report.columns.held_for'],
            'pending_arrival' => ['label' => 'report.columns.earmarked', 'numeric' => true],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        $holds = $this->holdsByPlace($query);
        $earmarks = $this->earmarksByItem();

        foreach ($this->base($query)->lazy() as $balance) {
            $place = $balance->item_id.'|'.$balance->bin_id.'|'.$balance->lot_no;

            yield [
                'item_code' => $balance->item?->item_code,
                'item_name' => $balance->item?->name,
                'bin' => $balance->bin?->code,
                'lot_no' => $balance->lot_no === '' ? null : $balance->lot_no,
                'on_hand' => (string) $balance->quantity_on_hand,
                'reserved' => (string) $balance->quantity_reserved,
                'available' => bcsub(
                    (string) $balance->quantity_on_hand,
                    (string) $balance->quantity_reserved,
                    4,
                ),
                'holders' => $holds[$place] ?? null,
                // Claims on stock that is not here yet: they encumber
                // nothing on this shelf, so they are their own column.
                'pending_arrival' => $earmarks[$balance->item_id] ?? null,
            ];
        }
    }

    protected function countable(ReportQuery $query): Builder
    {
        return $this->base($query);
    }

    /**
     * Who holds what, as a readable list per shelf.
     *
     * @return array<string, string>
     */
    private function holdsByPlace(ReportQuery $query): array
    {
        $builder = ItemReservation::query()
            ->whereIn('status', ItemReservation::HOLDING_STATUSES)
            ->whereNotNull('bin_id');

        $binIds = $this->binIdsForFactory($query);

        if ($binIds !== null) {
            $builder->whereIn('bin_id', $binIds);
        }

        $holds = [];

        foreach ($builder->get() as $reservation) {
            $place = $reservation->item_id.'|'.$reservation->bin_id.'|'.$reservation->lot_no;
            $label = __("itemmaster.reservation_types.{$reservation->reservation_type}");

            $holds[$place][] = trim($label.' '.$reservation->outstanding());
        }

        return array_map(
            static fn (array $lines): string => implode(', ', $lines),
            $holds,
        );
    }

    /**
     * Earmarks are per item rather than per shelf: they are waiting for
     * stock to land somewhere, and which bin it lands in is not known yet.
     *
     * @return array<string, string>
     */
    private function earmarksByItem(): array
    {
        $totals = [];

        foreach (ItemReservation::where('status', 'PENDING_ARRIVAL')->get() as $reservation) {
            $totals[$reservation->item_id] = bcadd(
                $totals[$reservation->item_id] ?? '0',
                $reservation->outstanding(),
                4,
            );
        }

        return $totals;
    }

    private function base(ReportQuery $query): Builder
    {
        $builder = ItemStockBalance::query()
            ->with(['item:id,item_code,name', 'bin:id,code'])
            ->whereHas('item')
            // Shelves where nothing is held and nothing is on hand have
            // nothing to say about availability.
            ->where(fn ($q) => $q->where('quantity_on_hand', '>', 0)->orWhere('quantity_reserved', '>', 0));

        $builder = $this->scopeToFactory($builder, $query);
        $builder = $this->scopeToSearch($builder, $query);

        return $builder->orderBy('item_id')->orderBy('bin_id')->orderBy('lot_no');
    }
}
