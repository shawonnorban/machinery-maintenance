<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

/**
 * Cutting Process Loss Setup: cutting-stage wastage allowances applied to marker consumption. Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class CuttingLossAllowanceResource extends ProcessLossAllowanceResource
{
    public function key(): string
    {
        return 'cutting-loss-allowances';
    }

    protected function stage(): string
    {
        return 'CUTTING';
    }
}
