<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Check Roll Parameter Setup: the GSM, width, shade and shrinkage tolerances a check roll is measured against, for a buyer and fabric type (blank: every one). Dye House → Production Board (AnnotechERP 17/07).
 */
class DyeCheckRollParam extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_check_roll_params';

    protected $fillable = ['company_id', 'buyer_id', 'fabric_type_id', 'gsm_tol_pct', 'width_tol_pct', 'shade_delta_e', 'shrink_length_pct', 'shrink_width_pct', 'remarks', 'active'];

    protected function casts(): array
    {
        return ['gsm_tol_pct' => 'float', 'width_tol_pct' => 'float', 'shade_delta_e' => 'float', 'shrink_length_pct' => 'float', 'shrink_width_pct' => 'float', 'active' => 'boolean'];
    }
}
