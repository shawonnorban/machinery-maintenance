<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A knitting programme received from a party: what they want knitted and the yarn they issue against it.
 */
class KnitScReceipt extends BaseModel
{
    use BelongsToTenant;

    /** Draft → Confirmed (the lines fixed, the floor may plan) → In Production → Closed; Cancelled. */
    public const STATUSES = ['DRAFT', 'CONFIRMED', 'IN_PRODUCTION', 'CLOSED', 'CANCELLED'];

    public const YD_TYPES = ['SOLID', 'AUTO_STRIPE', 'FEEDER_STRIPE', 'ENGINEERED_STRIPE', 'MELANGE'];

    public const RATE_TYPES = ['PER_KG', 'PER_LB', 'PER_DZN', 'FIXED'];

    protected $table = 'knit_sc_receipts';

    protected $fillable = ['company_id', 'program_no', 'auto_number', 'prog_date', 'party_id', 'yarn_store_id', 'remarks', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['prog_date' => 'date', 'auto_number' => 'boolean'];
    }
}
