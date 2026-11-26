<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Which account type and layer a chart-of-accounts node is classified under (Account Type Configuration). Finance & Ledger → Master Data (AnnotechERP 23/01).
 */
class AccAccountClass extends BaseModel
{
    use BelongsToTenant;

    public const ACCOUNT_TYPES = ['CASH', 'BANK', 'RECEIVABLE', 'PAYABLE', 'INVENTORY', 'GENERAL'];

    public const LAYERS = ['MAIN_CLASS', 'SUB_CLASS', 'CONTROL', 'LEDGER'];

    protected $table = 'acc_account_classes';

    protected $fillable = ['company_id', 'account_type', 'layer', 'account_id'];

    protected function casts(): array
    {
        return [];
    }
}
