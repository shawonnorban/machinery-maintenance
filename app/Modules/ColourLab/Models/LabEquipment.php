<?php

declare(strict_types=1);

namespace App\Modules\ColourLab\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A piece of lab equipment a certificate may cover (a spectrophotometer, its calibration). Colour Lab → Operations (AnnotechERP 16/01).
 */
class LabEquipment extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'lab_equipment';

    protected $fillable = ['company_id', 'code', 'name', 'manufacturer', 'serial_no', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
