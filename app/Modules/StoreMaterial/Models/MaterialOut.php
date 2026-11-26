<?php

declare(strict_types=1);

namespace App\Modules\StoreMaterial\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Material Out: material issued to a floor, section and line for a purpose, handed to an employee. Store & Material (AnnotechERP 09).
 */
class MaterialOut extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'sm_material_outs';

    protected $fillable = ['company_id', 'mo_no', 'mo_date', 'stock_id', 'purpose', 'qty', 'floor_id', 'section_id', 'line_id', 'receiver_id', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['mo_date' => 'date', 'qty' => 'decimal:3'];
    }
}
