<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Resources;

/** Pre-Budget, Master Data: Item: the garment items a requisition line picks (Dress, Polo, T-Shirt …), Norban's list — a list of Pre-Budget's own, apart from Merchandise / Knitting's masters. */
class PbItemResource extends PbListResource
{
    public function key(): string
    {
        return 'pb-items';
    }

    public function list(): string
    {
        return 'ITEM';
    }
}
