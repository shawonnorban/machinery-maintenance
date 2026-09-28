<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money given back (ERD Section 19).
 *
 * Against a payment rather than against an invoice, because that is what a
 * refund physically is: the reversal of one receipt. An invoice paid in three
 * instalments and refunded once has to say which instalment came back.
 */
class Refund extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['ISSUED', 'SETTLED', 'CANCELLED'];

    protected $table = 'refunds';

    protected $fillable = [
        'company_id', 'payment_id', 'amount', 'currency', 'reason',
        'status', 'issued_at', 'issued_by',
    ];

    protected function casts(): array
    {
        return ['issued_at' => 'immutable_datetime'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPayment::class, 'payment_id');
    }
}
