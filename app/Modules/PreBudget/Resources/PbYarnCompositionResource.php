<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Resources;

/** Pre-Budget, Master Data: Yarn Composition: the yarn as Norban writes it (Siro 50/50 [ 50% BCI Cotton-50% Recycled Polyester ] …) — a list of Pre-Budget's own, apart from Merchandise / Knitting's masters. */
class PbYarnCompositionResource extends PbListResource
{
    public function key(): string
    {
        return 'pb-yarn-compositions';
    }

    public function list(): string
    {
        return 'YARN_COMPOSITION';
    }
}
