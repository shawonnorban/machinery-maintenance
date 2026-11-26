<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Resources;

/** Pre-Budget, Master Data: GSM: the fabric weights a requisition line picks, a list of Pre-Budget's own (not Order Summary's). */
class GsmResource extends PbListResource
{
    public function key(): string
    {
        return 'pb-gsms';
    }

    public function list(): string
    {
        return 'GSM';
    }
}
