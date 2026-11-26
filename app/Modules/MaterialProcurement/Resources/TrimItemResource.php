<?php

declare(strict_types=1);

namespace App\Modules\MaterialProcurement\Resources;

/**
 * Trims & Accessories Master: trims and accessories a requisition may ask
 * for — category, size, specification, colour and UOM. Material Procurement →
 * Master Data (AnnotechERP 08/01).
 */
class TrimItemResource extends MaterialItemResource
{
    public function key(): string
    {
        return 'material-trims';
    }

    protected function kind(): string
    {
        return 'TRIMS';
    }

    protected function ownFields(): array
    {
        return ['size'];
    }
}
