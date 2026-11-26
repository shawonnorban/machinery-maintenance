<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A knitting programme placed with a sub-contract knitter.
 */
class KnitScProgram extends BaseModel
{
    use BelongsToTenant;

    /** Bulk (Solid), Sample (Solid) and Party (Y/D) — one screen each. */
    public const KINDS = ['BULK_SOLID', 'SAMPLE_SOLID', 'PARTY_YD'];

    /** The Type a programme of each kind can carry. */
    public const TYPES = ['BULK_SOLID' => ['BULK', 'SHORT_BULK'], 'SAMPLE_SOLID' => ['DEVELOP', 'SAMPLE'], 'PARTY_YD' => ['BULK', 'SHORT_BULK']];

    /** Draft Created → Submitted for Approval → Approved or Rejected (back to the desk); Cancelled. */
    public const STATUSES = ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED', 'CANCELLED'];

    protected $table = 'knit_sc_programs';

    protected $fillable = ['company_id', 'program_no', 'kind', 'program_type', 'po_date', 'target_date', 'party_id', 'category_code', 'buyer_id', 'style_ref', 'order_id', 'work_style_no', 'remarks', 'status', 'decision_note', 'decided_at', 'decided_by', 'created_by'];

    protected function casts(): array
    {
        return ['po_date' => 'date', 'target_date' => 'date', 'decided_at' => 'datetime'];
    }
}
