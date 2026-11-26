<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Resources;

/** Pre-Budget, Master Data: Sustainability: the certified fibre programme (BCI, GOTS and so on), a list of Pre-Budget's own. */
class SustainabilityResource extends PbListResource
{
    public function key(): string
    {
        return 'pb-sustainabilities';
    }

    public function list(): string
    {
        return 'SUSTAINABILITY';
    }
}
