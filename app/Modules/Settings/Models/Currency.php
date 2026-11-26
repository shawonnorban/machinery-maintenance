<?php

declare(strict_types=1);

namespace App\Modules\Settings\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A currency the company trades in, with its rate to the base currency
 * (Settings → Date, Currency & Localization, AnnotechERP 03/03).
 */
class Currency extends BaseModel
{
    use BelongsToTenant;

    /** ISO codes offered when adding a currency. */
    public const CODES = ['BDT', 'USD', 'EUR', 'GBP', 'CNY', 'INR', 'JPY', 'HKD', 'SGD', 'AED', 'CAD', 'AUD', 'TRY', 'VND', 'PKR', 'LKR'];

    protected $table = 'currencies';

    protected $fillable = ['company_id', 'code', 'name', 'symbol', 'decimal_places', 'exchange_rate', 'rate_date', 'active'];

    protected function casts(): array
    {
        return ['decimal_places' => 'integer', 'exchange_rate' => 'decimal:6', 'rate_date' => 'date', 'active' => 'boolean'];
    }
}
