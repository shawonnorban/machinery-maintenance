<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Twisted yarn coming back from the party.
 */
class KnitTwistReceive extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_twist_receives';

    protected $fillable = ['company_id', 'challan_no', 'receive_date', 'program_id', 'gate_pass_no', 'supplier_id', 'store_id', 'finalized', 'transferred', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['receive_date' => 'date', 'finalized' => 'boolean', 'transferred' => 'boolean'];
    }
}
