<?php

declare(strict_types=1);

namespace App\Modules\MaterialProcurement\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A supplier's quotation on one requisition line (QT-2026-00001): Pending
 * once sent, Revised when a new price follows; the price review asks for
 * Negotiation, or Approves or Rejects it, after which it is no longer
 * revised. Its prices are its revisions.
 */
class Quotation extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['PENDING', 'REVISED', 'NEGOTIATION', 'APPROVED', 'REJECTED', 'CLOSED'];

    /** Still open to a new price from the supplier. */
    public const REVISABLE = ['PENDING', 'REVISED', 'NEGOTIATION'];

    /** What the supplier's Rejected / closed pill counts. */
    public const ENDED = ['REJECTED', 'CLOSED'];

    public const CURRENCIES = ['USD', 'BDT', 'EUR', 'GBP', 'CNY', 'INR'];

    protected $table = 'mp_quotations';

    protected $fillable = ['company_id', 'qt_no', 'requisition_line_id', 'supplier_id', 'status', 'revisions', 'negotiation_message', 'negotiated_at', 'reject_reason', 'decided_by', 'decided_at'];

    protected function casts(): array
    {
        return ['negotiated_at' => 'datetime', 'decided_at' => 'datetime'];
    }
}
