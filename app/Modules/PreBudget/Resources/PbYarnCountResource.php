<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Resources;

/** Pre-Budget, Master Data: Yarn Count: 30/1 Ne, 28S, 40D, 100D/36F …, Norban's list — a list of Pre-Budget's own, apart from Merchandise / Knitting's masters. */
class PbYarnCountResource extends PbListResource
{
    public function key(): string
    {
        return 'pb-yarn-counts';
    }

    public function list(): string
    {
        return 'YARN_COUNT';
    }
}
