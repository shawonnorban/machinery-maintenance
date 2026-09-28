<?php

declare(strict_types=1);

namespace App\Modules\Vendor\Models;

use App\Modules\Finance\Models\Bank;
use App\Modules\Finance\Models\PaymentType;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A supplier or service provider (SRS 26, ERD Section 15).
 *
 * Archived, never deleted. A vendor named on a five-year-old cost entry or on
 * a warranty that is still being claimed against has to stay resolvable, and a
 * report that renders "unknown vendor" for half its rows is a report nobody
 * uses (ADR-057).
 */
class Vendor extends BaseModel
{
    use BelongsToTenant;
    use SoftDeletes;

    /**
     * What the vendor is to this factory. A parts supplier and a service
     * contractor are chosen in different places, and mixing them in one list
     * makes both harder to pick from.
     */
    public const TYPES = ['SUPPLIER', 'SERVICE', 'BOTH'];

    public const STATUSES = ['ACTIVE', 'INACTIVE', 'BLACKLISTED'];

    /**
     * What the vendor supplies — a different axis from `vendor_type`, which
     * says what kind of relationship it is (parts supplier, service, both).
     */
    public const SUPPLY_CATEGORIES = ['FABRIC', 'YARN', 'TRIMS', 'CHEMICAL', 'SPARE_PARTS', 'SERVICE'];

    /** Vetted or not. Separate from `status`, which is whether the vendor is in use. */
    public const APPROVAL_STATUSES = ['PENDING', 'APPROVED'];

    protected $table = 'vendors';

    /** Mirrors the column default, so a freshly created row reads as un-vetted without a round trip. */
    protected $attributes = ['approval_status' => 'PENDING'];

    protected $fillable = [
        'company_id', 'name', 'code', 'vendor_type', 'contact_name', 'phone',
        'email', 'address', 'country_of_origin', 'tax_reference', 'bin', 'trade_license_no',
        'bank_id', 'bank_branch', 'bank_account_no', 'bank_swift', 'supply_categories_json',
        'status', 'approval_status', 'default_currency', 'default_payment_type_id', 'default_payment_terms',
        'rating', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'supply_categories_json' => 'array',
            'rating' => 'decimal:2',
        ];
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function defaultPaymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class, 'default_payment_type_id');
    }

    public function warranties(): HasMany
    {
        return $this->hasMany(Warranty::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(ServiceContract::class);
    }

    public function supplies(): bool
    {
        return in_array($this->vendor_type, ['SUPPLIER', 'BOTH'], true);
    }

    public function services(): bool
    {
        return in_array($this->vendor_type, ['SERVICE', 'BOTH'], true);
    }

    /**
     * Blacklisted is not the same as inactive: one is a decision about the
     * vendor, the other is housekeeping. Both stop new work being given to
     * them, and neither touches what they were given before.
     */
    public function isSelectable(): bool
    {
        return $this->status === 'ACTIVE';
    }
}
