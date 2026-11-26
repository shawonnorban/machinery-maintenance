<?php

declare(strict_types=1);

namespace App\Modules\MaterialProcurement\Resources;

/**
 * Fabric Master: fabric items a requisition may ask for — category, GSM,
 * width, composition, colour and UOM. Material Procurement → Master Data
 * (AnnotechERP 08/01).
 */
class FabricItemResource extends MaterialItemResource
{
    public function key(): string
    {
        return 'material-fabrics';
    }

    protected function kind(): string
    {
        return 'FABRIC';
    }

    protected function ownFields(): array
    {
        return ['gsm', 'width', 'composition'];
    }
}
