<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A branch of a bank in the directory, with its routing number and contact (Bank Directory). Trade & Export → Master Data (AnnotechERP 22/01).
 */
class TxBankBranch extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'tx_bank_branches';

    protected $fillable = ['company_id', 'bank_id', 'code', 'name', 'address', 'swift_code', 'routing_no', 'contact_person', 'phone', 'email', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
