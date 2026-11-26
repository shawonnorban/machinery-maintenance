<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Resources;

/** Pre-Budget, Master Data: Additional Process: extra fabric or garment processes (Peach Finishing, AOP and so on). */
class AdditionalProcessResource extends PbListResource
{
    public function key(): string
    {
        return 'pb-additional-processes';
    }

    public function list(): string
    {
        return 'ADDITIONAL_PROCESS';
    }
}
