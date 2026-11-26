<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * The share of a ledger head a compliance voucher may touch (Statutory Ledger Rules). Finance & Ledger → Master Data (AnnotechERP 23/01).
 */
class AccComplianceRule extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'acc_compliance_rules';

    protected $fillable = ['company_id', 'account_id', 'pct'];

    protected function casts(): array
    {
        return ['pct' => 'decimal:2'];
    }
}
