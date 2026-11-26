<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A node of the chart of accounts — a group, or a ledger head that takes postings (Chart of Accounts Master). Finance & Ledger → Master Data (AnnotechERP 23/01).
 */
class AccAccount extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['ASSET', 'LIABILITY', 'EQUITY', 'REVENUE', 'EXPENSE'];

    public const REF_TYPES = ['BUYER', 'SUPPLIER', 'EMPLOYEE', 'BANK', 'BANK_ACCOUNT'];

    protected $table = 'acc_accounts';

    protected $fillable = ['company_id', 'code', 'name', 'parent_id', 'type', 'postable', 'voucher_ref_type', 'voucher_ref_id', 'display_order', 'active'];

    protected function casts(): array
    {
        return ['postable' => 'boolean', 'display_order' => 'integer', 'active' => 'boolean'];
    }
}
