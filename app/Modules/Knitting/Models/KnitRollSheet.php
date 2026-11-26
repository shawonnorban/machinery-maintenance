<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A roll sheet: the rolls weighed against one knit card at one sitting.
 */
class KnitRollSheet extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_roll_sheets';

    protected $fillable = ['company_id', 'sheet_no', 'card_id', 'created_by'];

    protected function casts(): array
    {
        return [];
    }
}
