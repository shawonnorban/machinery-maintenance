<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A subcontract party's quoted rates for one rate group (Dye Sub-Contract Party Rate). Dye House → Master Data (AnnotechERP 17/01).
 */
class DyeScQuotation extends BaseModel
{
    use BelongsToTenant;

    public const GROUPS = ['KNITTING', 'YARN_DYEING', 'DYEING', 'DYEING_FINISHING', 'AOP', 'YARN_TWIST'];

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED', 'CANCELLED'];

    /** OUT: the rates a subcontract party charges us (17/01); IN: the rates we charge a party for in-house work (17/10). */
    public const SIDES = ['OUT', 'IN'];

    protected $table = 'dye_sc_quotations';

    protected $fillable = ['company_id', 'quotation_no', 'rate_group', 'side', 'supplier_id', 'quotation_date', 'currency', 'status', 'remarks', 'documents', 'created_by'];

    protected function casts(): array
    {
        return ['quotation_date' => 'date', 'documents' => 'array'];
    }
}
