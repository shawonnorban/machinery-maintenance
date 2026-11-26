<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One shade an out-house programme asks the party to dye: buyer, order, colour and kilos. Dye House → S/C Dyeing (AnnotechERP 17/09).
 */
class DyeScProgrammeShade extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_sc_programme_shades';

    protected $fillable = ['company_id', 'programme_id', 'buyer_id', 'order_id', 'colour_id', 'req_kg', 'remarks', 'sort_order'];

    protected function casts(): array
    {
        return ['req_kg' => 'float'];
    }
}
