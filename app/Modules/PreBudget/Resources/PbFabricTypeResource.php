<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Resources;

/** Pre-Budget, Master Data: Fabric Type: Single Jersey, Brush Back Fleece, 2X2 Rib …, Norban's list — a list of Pre-Budget's own, apart from Merchandise / Knitting's masters. */
class PbFabricTypeResource extends PbListResource
{
    public function key(): string
    {
        return 'pb-fabric-types';
    }

    public function list(): string
    {
        return 'FABRIC_TYPE';
    }
}
