<?php

declare(strict_types=1);

namespace App\Modules\StoreMaterial\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Material Return: unused material taken back from the floor against its Material Out. Store & Material (AnnotechERP 09).
 */
class MaterialReturn extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'sm_material_returns';

    protected $fillable = ['company_id', 'mr_no', 'mr_date', 'material_out_id', 'roll_id', 'qty', 'reason', 'line_name', 'returned_by', 'received_by', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['mr_date' => 'date', 'qty' => 'decimal:3'];
    }
}
