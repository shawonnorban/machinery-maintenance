<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\InventoryBalance;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Inventory\Models\InventoryTransferItem;
use App\Modules\Inventory\Models\SparePartReservation;
use App\Modules\Inventory\Models\WorkOrderPart;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;
use Illuminate\Database\Eloquent\Builder;

/**
 * The shelf a part actually sits on (SRS 23).
 *
 * Every stock movement names a bin, which is why one has to exist before a
 * company can receive anything.
 */
class BinData extends MasterDataType
{
    public function key(): string
    {
        return 'bins';
    }

    public function model(): string
    {
        return Bin::class;
    }

    public function group(): string
    {
        return 'inventory';
    }

    public function sharedWithPlatform(): bool
    {
        return false;
    }

    /**
     * In-transit bins are left out entirely.
     *
     * They are created by a transfer to hold stock that has left one factory
     * and not yet reached the other, and they are emptied when it arrives.
     * Nobody puts anything in one by hand, and a person editing or deleting
     * one would be editing the accounting for stock on a van.
     */
    public function query(): Builder
    {
        return parent::query()->where('is_in_transit', false);
    }

    public function fields(): array
    {
        return [
            Field::reference('store_id', 'stores'),
            Field::text('name'),
            Field::code(32),
            Field::boolean('active'),
        ];
    }

    public function usedBy(): array
    {
        return [
            InventoryBalance::class => 'bin_id',
            InventoryTransaction::class => 'bin_id',
            SparePartReservation::class => 'bin_id',
            WorkOrderPart::class => 'bin_id',
            InventoryTransferItem::class => 'from_bin_id',
        ];
    }
}
