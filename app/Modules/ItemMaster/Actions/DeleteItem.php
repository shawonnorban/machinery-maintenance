<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Actions;

use App\Modules\ItemMaster\Models\Item;
use App\Modules\Procurement\Models\GoodsReceiptLine;
use Illuminate\Validation\ValidationException;

/**
 * Removes a library entry that should never have been created.
 *
 * Narrow on purpose: anything a goods receipt has named stays, retired via
 * `SaveItem::setActive()` instead, so last year's delivery still reads as
 * the item it was. Requisition and purchase order lines join this list as
 * those tables arrive.
 */
class DeleteItem
{
    public function handle(Item $item): void
    {
        $references = GoodsReceiptLine::query()->where('item_id', $item->id)->count();

        if ($references > 0) {
            throw ValidationException::withMessages([
                'item' => __('itemmaster.delete_blocked', ['count' => $references]),
            ])->status(409);
        }

        $item->delete();
    }
}
