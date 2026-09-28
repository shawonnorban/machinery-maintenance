<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Identity\Models\User;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Money received against an invoice (SRS 40).
 *
 * Manual methods are first-class here because that is how these customers
 * actually pay — a bank transfer with a reference somebody types in. A gateway
 * is an integration on top, not a precondition for using the product.
 *
 * A payment is never edited or deleted. A mistake is reversed, which leaves
 * both the original receipt and the correction visible.
 */
class SubscriptionPayment extends BaseModel
{
    use BelongsToTenant;

    public const METHODS = ['BANK_TRANSFER', 'CASH', 'CHEQUE', 'CARD', 'MOBILE', 'GATEWAY'];

    public const STATUSES = ['RECEIVED', 'REVERSED'];

    protected $table = 'subscription_payments';

    protected $fillable = [
        'company_id', 'invoice_id', 'payment_reference', 'method', 'amount',
        'currency', 'paid_at', 'status', 'notes', 'recorded_by',
    ];

    protected function casts(): array
    {
        return ['paid_at' => 'immutable_datetime'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SubscriptionInvoice::class, 'invoice_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'payment_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** A reversed payment stays on the record but stops counting. */
    public function counts(): bool
    {
        return $this->status === 'RECEIVED';
    }
}
