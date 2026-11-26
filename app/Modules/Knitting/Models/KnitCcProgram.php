<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Flat-knit parts placed on an outside party at a rate per piece.
 */
class KnitCcProgram extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'APPROVED', 'IN_PRODUCTION', 'CLOSED', 'CANCELLED'];

    /** The ones pieces can still be knitted and received against. */
    public const LIVE = ['APPROVED', 'IN_PRODUCTION'];

    protected $table = 'knit_cc_programs';

    protected $fillable = ['company_id', 'program_no', 'program_date', 'delivery_date', 'party_id', 'cpi', 'buyer_id', 'order_id', 'work_style_no', 'remarks', 'status', 'approved_at', 'approved_by', 'created_by'];

    protected function casts(): array
    {
        return ['program_date' => 'date', 'delivery_date' => 'date', 'approved_at' => 'datetime'];
    }
}
