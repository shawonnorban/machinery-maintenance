<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Resources;

/** Pre-Budget, Master Data: Wash Type: the garment wash a requisition line names. */
class WashTypeResource extends PbListResource
{
    public function key(): string
    {
        return 'pb-wash-types';
    }

    public function list(): string
    {
        return 'WASH_TYPE';
    }
}
