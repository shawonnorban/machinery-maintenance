<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One priced item on an invoice (ERD Section 19).
 *
 * The reason invoices have lines at all: a contract priced per factory or per
 * asset cannot be itemized without them, and a customer looking at a single
 * total has no way to check it.
 */
class SubscriptionInvoiceLine extends BaseModel
{
    use BelongsToTenant;

    public const METRICS = ['FACTORIES', 'ASSETS', 'USERS', 'FLAT'];

    public $timestamps = false;

    protected $table = 'subscription_invoice_lines';

    protected $fillable = [
        'company_id', 'subscription_invoice_id', 'description', 'metric',
        'quantity', 'unit_price', 'amount', 'tax_rate', 'tax_amount',
        'period_start', 'period_end', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'immutable_date',
            'period_end' => 'immutable_date',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SubscriptionInvoice::class, 'subscription_invoice_id');
    }
}
