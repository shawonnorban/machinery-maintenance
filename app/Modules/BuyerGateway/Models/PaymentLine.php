<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A buyer's billed and received, its terms and next due date. Buyer Gateway → Payment Progress View (AnnotechERP 25/03).
 */
class PaymentLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'bg_payment_lines';

    protected $fillable = ['company_id', 'buyer_id', 'billed', 'received', 'payment_terms', 'next_due'];

    protected function casts(): array
    {
        return ['billed' => 'float', 'received' => 'float', 'next_due' => 'date'];
    }
}
