<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Resources;

/** Pre-Budget, Master Data: Yarn Type: the yarn a knit requisition line asks for (100% Cotton, CVC 60/40 and so on). */
class YarnTypeResource extends PbListResource
{
    public function key(): string
    {
        return 'pb-yarn-types';
    }

    public function list(): string
    {
        return 'YARN_TYPE';
    }
}
