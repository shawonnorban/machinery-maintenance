<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Resources;

/** Pre-Budget, Master Data: Order Type: Confirmed PO, BLOCK, Buying File and so on, a list of Pre-Budget's own. */
class OrderTypeResource extends PbListResource
{
    public function key(): string
    {
        return 'pb-order-types';
    }

    public function list(): string
    {
        return 'ORDER_TYPE';
    }
}
