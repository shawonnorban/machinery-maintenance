<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Trims Dyeing Programme (TDP-): tape, cord and other dyeable trims to dye for one order, in yards and kilos. Dye House → Production Board (AnnotechERP 17/07).
 */
class DyeTrimProgramme extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_trim_programmes';

    protected $fillable = ['company_id', 'programme_no', 'prod_type', 'buyer_id', 'order_id', 'prog_date', 'status', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['prog_date' => 'date'];
    }
}
