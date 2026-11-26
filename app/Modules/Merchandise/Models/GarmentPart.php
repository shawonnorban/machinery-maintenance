<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A part a garment is cut from — body, sleeve, collar … (Garment Part Master). Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class GarmentPart extends BaseModel
{
    use BelongsToTenant;

    public const FABRIC_GROUPS = ['BODY', 'COLLAR', 'LINING', 'RIB'];

    protected $table = 'garment_parts';

    protected $fillable = ['company_id', 'code', 'name', 'fabric_group', 'is_circular', 'is_process_group', 'is_body', 'active'];

    protected function casts(): array
    {
        return ['is_circular' => 'boolean', 'is_process_group' => 'boolean', 'is_body' => 'boolean', 'active' => 'boolean'];
    }
}
