<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One grey roll off a knit card, weighed and tagged.
 */
class KnitRoll extends BaseModel
{
    use BelongsToTenant;

    public const MOVEMENTS = ['IN_KNITTING', 'GREY_STORE', 'BATCH_STORE', 'LEFT_OVER', 'DELIVERED'];

    public const QUALITIES = ['PENDING', 'PASSED', 'HOLD', 'REJECTED'];

    protected $table = 'knit_rolls';

    protected $fillable = ['company_id', 'sheet_id', 'card_id', 'roll_no', 'roll_code', 'roll_at', 'shift_id', 'operator_id', 'sc_party_id', 'machine_id', 'weight', 'deducted', 'movement', 'quality', 'label_info', 'comments'];

    protected function casts(): array
    {
        return ['roll_at' => 'datetime', 'weight' => 'float', 'deducted' => 'float', 'roll_no' => 'integer'];
    }
}
