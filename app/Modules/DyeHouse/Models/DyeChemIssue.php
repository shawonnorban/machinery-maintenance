<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A paper that takes dyes and chemicals out of a store — a transfer, a print, wash or batch issue, a sale, a loan out or a loan going back — and what the store has issued against it. Dye House → Chemical Store (AnnotechERP 17/06).
 */
class DyeChemIssue extends BaseModel
{
    use BelongsToTenant;

    public const KINDS = ['TRANSFER', 'PRINT', 'WASH', 'BATCH', 'SALE', 'LOAN_OUT', 'LOAN_RETURN'];

    /** Each kind's number prefix. */
    public const PREFIX = ['TRANSFER' => 'REQ-ST-', 'PRINT' => 'IS-PR-', 'WASH' => 'IS-MW-', 'BATCH' => 'IS-BP-', 'SALE' => 'IS-SL-', 'LOAN_OUT' => 'IS-LN-', 'LOAN_RETURN' => 'IS-LR-'];

    public const STATUSES = ['WAITING_VERIFICATION', 'VERIFIED', 'WAITING', 'PARTIAL', 'ISSUED', 'CANCELLED'];

    /** The standings the store may still issue against. */
    public const ISSUABLE = ['VERIFIED', 'WAITING', 'PARTIAL'];

    protected $table = 'dye_chem_issues';

    protected $fillable = ['company_id', 'kind', 'issue_no', 'issue_date', 'req_type', 'party_id', 'store_id', 'to_store_id', 'gate_pass', 'buyer_id', 'order_id', 'print_type', 'batch_id', 'dye_machine_id', 'process_type', 'is_reprocess', 'supplier_id', 'remarks', 'status', 'verified_at', 'verified_by', 'issued_at', 'issued_by', 'created_by'];

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'is_reprocess' => 'boolean', 'verified_at' => 'datetime', 'issued_at' => 'datetime'];
    }
}
