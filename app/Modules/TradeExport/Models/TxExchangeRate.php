<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * The local rate a currency is converted at, for a purpose, from a day (Currency Exchange Rates). Trade & Export → Master Data (AnnotechERP 22/01).
 */
class TxExchangeRate extends BaseModel
{
    use BelongsToTenant;

    public const PURPOSES = ['EXPORT_REALIZATION', 'IMPORT_PAYMENT', 'LOCAL_SALES', 'CASH_INCENTIVE'];

    protected $table = 'tx_exchange_rates';

    protected $fillable = ['company_id', 'rate_for', 'currency', 'rate', 'effective_date', 'remarks'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:6', 'effective_date' => 'date'];
    }
}
