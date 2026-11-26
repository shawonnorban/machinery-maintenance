<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A Knit Card (KC) cut off a programme: what a machine is loaded with and every roll is weighed against.
 */
class KnitCard extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['OPEN', 'RUNNING', 'COMPLETED', 'CLOSED', 'CANCELLED'];

    /** The ones rolls can still be weighed against. */
    public const LIVE = ['OPEN', 'RUNNING'];

    protected $table = 'knit_cards';

    protected $fillable = ['company_id', 'kc_no', 'program_id', 'prod_order_id', 'card_date', 'assigned_qty', 'colour_name', 'machine_dia', 'finish_dia', 'gauge', 'floor_id', 'yarn_details', 'machine_id', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['card_date' => 'date', 'assigned_qty' => 'float'];
    }
}
