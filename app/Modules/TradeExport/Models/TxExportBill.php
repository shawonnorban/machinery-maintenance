<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * The customs export bill lodged for a set of invoices. Trade & Export → Operations (AnnotechERP 22/02).
 */
class TxExportBill extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'tx_export_bills';

    protected $fillable = ['company_id', 'bill_no', 'bill_date', 'charge', 'pass_book_page', 'remarks', 'file_id', 'file_name', 'final', 'created_by'];

    protected function casts(): array
    {
        return ['bill_date' => 'date', 'charge' => 'float', 'final' => 'boolean'];
    }
}
