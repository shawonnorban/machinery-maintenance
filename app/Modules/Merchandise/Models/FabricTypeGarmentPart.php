<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One garment part a fabric type may be cut for (Fabric Type Master → GARM Part List). Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class FabricTypeGarmentPart extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'fabric_type_garment_parts';

    protected $fillable = ['company_id', 'fabric_type_id', 'garment_part_id'];
}
