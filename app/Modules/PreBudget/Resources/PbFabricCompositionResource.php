<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Resources;

/** Pre-Budget, Master Data: Fabric Composition: the fabric's fibre mix as written (95% Cotton 5% Elastane …), Norban's list — a list of Pre-Budget's own, apart from Merchandise / Knitting's masters. */
class PbFabricCompositionResource extends PbListResource
{
    public function key(): string
    {
        return 'pb-fabric-compositions';
    }

    public function list(): string
    {
        return 'FABRIC_COMPOSITION';
    }
}
