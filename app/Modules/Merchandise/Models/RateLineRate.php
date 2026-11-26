<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One revision of a rate-line rate for a buyer office, from its effective date. Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class RateLineRate extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'rate_line_rates';

    protected $fillable = ['company_id', 'rate_line_id', 'buyer_office_id', 'rate', 'currency', 'effective_from', 'entered_by'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:4', 'effective_from' => 'date'];
    }
}
