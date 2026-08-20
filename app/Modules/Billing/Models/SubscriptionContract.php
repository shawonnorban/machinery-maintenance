<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Tenancy\Models\Company;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a customer agreed to (SRS 40, ADR-028).
 *
 * There are no fixed packages: a garment group negotiates on factories, assets
 * or users, so the contract carries its own pricing model and its own limits.
 *
 * The status is the single fact that decides whether the product still accepts
 * writes, which is why the transitions are a state machine rather than an
 * editable field.
 */
class SubscriptionContract extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = [
        'DRAFT', 'TRIAL', 'ACTIVE', 'PAST_DUE', 'GRACE', 'READ_ONLY', 'ARCHIVED', 'CANCELLED',
    ];

    /** Access narrows here, and nothing may be written (ADR-029). */
    public const RESTRICTED_STATUSES = ['READ_ONLY', 'ARCHIVED'];

    public const BILLING_CYCLES = ['MONTHLY', 'QUARTERLY', 'YEARLY'];

    public const OVERAGE_POLICIES = ['BLOCK', 'ALLOW_AND_BILL', 'WARN_ONLY'];

    protected $table = 'subscription_contracts';

    protected $fillable = [
        'company_id', 'contract_number', 'status', 'start_date', 'end_date',
        'billing_cycle', 'amount', 'currency', 'trial_end', 'grace_period_days',
        'auto_renew', 'read_only_at', 'archived_at', 'cancelled_at',
        'cancellation_reason', 'pricing_model_json', 'included_factories',
        'included_assets', 'included_users', 'overage_policy', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'trial_end' => 'immutable_date',
            'read_only_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'pricing_model_json' => 'array',
            'auto_renew' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    /** Writes are refused in these states; reads and exports never are. */
    public function isReadOnly(): bool
    {
        return in_array($this->status, self::RESTRICTED_STATUSES, true);
    }

    public function isUsable(): bool
    {
        return in_array($this->status, ['TRIAL', 'ACTIVE', 'PAST_DUE', 'GRACE'], true);
    }

    /**
     * The limit for one metric, or null where the contract sets none.
     *
     * Null is not zero. A contract that names no asset limit is unlimited, and
     * treating the absence of a number as a limit of nothing would lock a
     * customer out of their own system.
     */
    public function limitFor(string $metric): ?int
    {
        return match ($metric) {
            'ACTIVE_FACTORIES' => $this->included_factories,
            'ACTIVE_ASSETS' => $this->included_assets,
            'ACTIVE_USERS' => $this->included_users,
            default => null,
        };
    }

    public function graceEndsAt(): ?CarbonImmutable
    {
        $from = $this->end_date ?? $this->start_date;

        return $from?->addDays($this->grace_period_days);
    }
}
