<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * The customs entry of goods received against a B2B L/C. Trade & Export → Import (AnnotechERP 22/03).
 */
class TxBillOfEntry extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'tx_bills_of_entry';

    protected $fillable = ['company_id', 'be_no', 'be_date', 'lc_id', 'entry_by', 'custom_code', 'custom_name', 'bill_amount', 'currency', 'exchange_rate', 'local_currency', 'equivalent_amount', 'remarks', 'file_id', 'file_name', 'final', 'created_by'];

    protected function casts(): array
    {
        return ['be_date' => 'date', 'final' => 'boolean', 'bill_amount' => 'float', 'exchange_rate' => 'float', 'equivalent_amount' => 'float'];
    }
}
