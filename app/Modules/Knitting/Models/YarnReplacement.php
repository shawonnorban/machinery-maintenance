<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A consignment going back to a spinner against a replacement claim.
 */
class YarnReplacement extends BaseModel
{
    use BelongsToTenant;

    /** Draft Created → Submitted (on the lorry) → Accepted (the spinner's signature); Cancelled. */
    public const STATUSES = ['DRAFT', 'SUBMITTED', 'ACCEPTED', 'CANCELLED'];

    /** Why the yarn is going back, as Brickland lists it. */
    public const TYPES = ['CLAIM_YARN_TEST', 'CLAIM_PRODUCTION', 'SHORT_SUPPLY', 'WRONG_SUPPLY', 'DAMAGED_IN_TRANSIT'];

    protected $table = 'knit_yarn_replacements';

    protected $fillable = ['company_id', 'challan_no', 'challan_date', 'store_id', 'supplier_id', 'claim_ref', 'return_type', 'lc_no', 'lot_ref', 'remarks', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['challan_date' => 'date'];
    }
}
