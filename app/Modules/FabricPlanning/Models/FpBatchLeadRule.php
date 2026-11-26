<?php

declare(strict_types=1);

namespace App\Modules\FabricPlanning\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * How many days before shipment or PCD a dye batch is planned, for a colour, finish and wash and an order lead time and volume band (Batch Lead Days Before PCD). Fabric Requirement → Master Data (AnnotechERP 13/01).
 */
class FpBatchLeadRule extends BaseModel
{
    use BelongsToTenant;

    public const COLOUR_TYPES = ['SOLID', 'MELANGE', 'YARN_DYED', 'AOP'];

    public const FINISH_TYPES = ['NORMAL', 'PEACH', 'BRUSH', 'SILICONE'];

    public const WASH_TYPES = ['NO_WASH', 'ENZYME', 'STONE', 'GARMENT'];

    public const BASES = ['SHIPMENT', 'PCD'];

    protected $table = 'fp_batch_lead_rules';

    protected $fillable = ['company_id', 'colour_type', 'finish_type', 'wash_type', 'lead_from', 'lead_to', 'volume_from', 'volume_to', 'production_lead_time', 'basis', 'days_before_shipment', 'days_before_pcd', 'is_embellishment'];

    protected function casts(): array
    {
        return ['lead_from' => 'integer', 'lead_to' => 'integer', 'volume_from' => 'integer', 'volume_to' => 'integer', 'production_lead_time' => 'integer', 'days_before_shipment' => 'integer', 'days_before_pcd' => 'integer', 'is_embellishment' => 'boolean'];
    }
}
