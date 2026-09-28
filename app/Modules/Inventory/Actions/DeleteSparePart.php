<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\InventoryBalance;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Inventory\Models\InventoryTransferItem;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Models\SparePartCompatibility;
use App\Modules\Inventory\Models\SparePartReservation;
use App\Modules\Inventory\Models\WorkOrderPart;
use App\Shared\Scopes\TenantScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Removes a catalogue entry that should never have been created (SRS 22).
 *
 * A part typed in twice, or under the wrong number, has to be removable —
 * retiring it would leave the mistake in every list for ever, labelled as
 * though it were once a real part.
 *
 * But only that case. Once anything points at the row — a receipt, an issue,
 * a reservation, a part fitted to a work order — the row is part of the
 * ledger, and deleting it would leave a valuation that cannot be replayed and
 * a repair whose parts cannot be named. Those are retired instead, and the
 * screen says so rather than failing on a foreign key nobody can read.
 */
class DeleteSparePart
{
    /**
     * Everything that would be orphaned, as model => foreign key.
     *
     * A balance row is included even though it may hold zero: a balance exists
     * because stock was once put in that bin, which means transactions exist.
     *
     * @var array<class-string, list<string>>
     */
    private const REFERENCES = [
        InventoryTransaction::class => ['spare_part_id'],
        InventoryBalance::class => ['spare_part_id'],
        SparePartReservation::class => ['spare_part_id'],
        WorkOrderPart::class => ['spare_part_id', 'substitute_for_spare_part_id'],
        InventoryTransferItem::class => ['spare_part_id'],
        SparePartCompatibility::class => ['spare_part_id', 'substitute_for_part_id'],
    ];

    public function handle(SparePart $part): void
    {
        $used = $this->referenceCount($part);

        if ($used > 0) {
            throw ValidationException::withMessages([
                'part_number' => __('inventory.delete_blocked', ['count' => $used]),
            ])->status(409);
        }

        DB::transaction(fn () => $part->delete());
    }

    /**
     * How many records point at this part, across every table that can.
     */
    public function referenceCount(SparePart $part): int
    {
        $total = 0;

        foreach (self::REFERENCES as $model => $columns) {
            foreach ($columns as $column) {
                // Without the tenant scope: the question is whether anything
                // anywhere references the row, and a scoped count that missed
                // one would let the delete through to fail on the constraint.
                $total += $model::query()
                    ->withoutGlobalScope(TenantScope::class)
                    ->where($column, $part->id)
                    ->count();
            }
        }

        return $total;
    }
}
