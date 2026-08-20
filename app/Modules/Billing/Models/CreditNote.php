<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A correction to an issued invoice (ERD Section 19).
 *
 * This exists so an issued invoice never has to be edited. A customer who was
 * overcharged gets a document saying so, which is what their accounts
 * department needs; lowering the original total instead leaves the two sides
 * holding different copies of the same invoice number.
 */
class CreditNote extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['ISSUED', 'APPLIED', 'CANCELLED'];

    protected $table = 'credit_notes';

    protected $fillable = [
        'company_id', 'invoice_id', 'credit_note_number', 'amount', 'currency',
        'reason', 'status', 'issued_at', 'issued_by',
    ];

    protected function casts(): array
    {
        return ['issued_at' => 'immutable_datetime'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SubscriptionInvoice::class, 'invoice_id');
    }
}
