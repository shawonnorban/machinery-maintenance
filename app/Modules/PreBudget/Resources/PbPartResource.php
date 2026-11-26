<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Resources;

/** Pre-Budget, Master Data: Parts: the garment parts a fabric goes into (Body, Neck Rib, Armhole Binding + Neck …), Norban's list — a list of Pre-Budget's own, apart from Merchandise / Knitting's masters. */
class PbPartResource extends PbListResource
{
    public function key(): string
    {
        return 'pb-parts';
    }

    public function list(): string
    {
        return 'PARTS';
    }
}
