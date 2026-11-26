<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Dyes and chemicals coming into a store on a challan — bought (MRR-), borrowed (MRR-DC-) or a loan coming back from a party (RTN-LP-) — counted in once the store finalises it. Dye House → Chemical Store (AnnotechERP 17/06).
 */
class DyeChemReceipt extends BaseModel
{
    use BelongsToTenant;

    public const KINDS = ['PURCHASE', 'LOAN_IN', 'LOAN_BACK'];

    public const STATUSES = ['DRAFT', 'RECEIVED', 'VERIFIED', 'CANCELLED'];

    /** The standings whose lines are in stock. */
    public const POSTED = ['RECEIVED', 'VERIFIED'];

    public const SOURCES = ['LOCAL', 'IMPORT'];

    public const RETURN_TYPES = ['FULL', 'PARTIAL'];

    protected $table = 'dye_chem_receipts';

    protected $fillable = ['company_id', 'kind', 'doc_no', 'doc_date', 'party_id', 'store_id', 'supplier_id', 'source', 'challan_no', 'lc_no', 'pr_no', 'po_no', 'req_type', 'pay_method', 'return_type', 'received_by', 'remarks', 'status', 'finalized_at', 'finalized_by', 'verified_at', 'verified_by', 'created_by'];

    protected function casts(): array
    {
        return ['doc_date' => 'date', 'finalized_at' => 'datetime', 'verified_at' => 'datetime'];
    }
}
