<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A bill (ERD Section 19, SRS 40).
 *
 * An issued invoice is immutable. It is corrected by a credit note, or by being
 * voided and reissued — never by editing. An invoice a customer has already
 * seen, and possibly paid against, is a document; quietly changing its total is
 * how a billing question becomes an accusation.
 */
class SubscriptionInvoice extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = [
        'DRAFT', 'ISSUED', 'PARTIALLY_PAID', 'PAID', 'OVERDUE', 'VOID', 'WRITTEN_OFF',
    ];

    /** Once here, the figures are fixed. */
    public const IMMUTABLE_STATUSES = ['ISSUED', 'PARTIALLY_PAID', 'PAID', 'OVERDUE', 'VOID', 'WRITTEN_OFF'];

    public const OPEN_STATUSES = ['ISSUED', 'PARTIALLY_PAID', 'OVERDUE'];

    protected $table = 'subscription_invoices';

    protected $fillable = [
        'company_id', 'subscription_contract_id', 'invoice_number', 'issue_date',
        'due_date', 'subtotal', 'tax', 'total', 'tax_rate', 'tax_reference',
        'currency', 'status', 'paid_amount', 'balance_due', 'paid_at',
        'voided_at', 'void_reason', 'pdf_file_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'immutable_date',
            'due_date' => 'immutable_date',
            'paid_at' => 'immutable_datetime',
            'voided_at' => 'immutable_datetime',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(SubscriptionContract::class, 'subscription_contract_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SubscriptionInvoiceLine::class, 'subscription_invoice_id')->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class, 'invoice_id');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class, 'invoice_id');
    }

    public function isImmutable(): bool
    {
        return in_array($this->status, self::IMMUTABLE_STATUSES, true);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_date->isPast();
    }
}
