<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One of the company's own accounts at a bank branch (Bank Account Master). Finance & Ledger → Master Data (AnnotechERP 23/01).
 */
class AccBankAccount extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['CURRENT', 'SAVINGS', 'STD', 'SND', 'FC', 'CC', 'OD'];

    protected $table = 'acc_bank_accounts';

    protected $fillable = ['company_id', 'bank_id', 'branch_id', 'account_no', 'account_name', 'account_type', 'currency', 'ledger_id', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
