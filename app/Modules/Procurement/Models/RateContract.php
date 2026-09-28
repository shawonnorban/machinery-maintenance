<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Vendor\Models\Vendor;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A price fixed with a supplier for a period (Procurement spec §14).
 *
 * Expiry is deliberately not a status. The dates already say whether a
 * contract applies today, and a stored `EXPIRED` would be true only as
 * often as some sweep remembered to run — wrong in between, and wrong in
 * the direction that quietly puts last year's price on this year's order.
 * So the lifecycle is DRAFT → ACTIVE → CANCELLED, and "is it live" is
 * asked of the calendar.
 */
class RateContract extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'ACTIVE', 'CANCELLED'];

    /**
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'DRAFT' => ['ACTIVE', 'CANCELLED'],
        'ACTIVE' => ['CANCELLED'],
        'CANCELLED' => [],
    ];

    protected $table = 'rate_contracts';

    protected $fillable = [
        'company_id', 'factory_id', 'vendor_id', 'contract_no', 'title', 'supplier_reference',
        'valid_from', 'valid_to', 'currency', 'status', 'notes', 'created_by',
        'activated_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_to' => 'date',
            'activated_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RateContractLine::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /** Active, and today falls inside the agreed period. */
    public function isLive(?CarbonImmutable $on = null): bool
    {
        if ($this->status !== 'ACTIVE') {
            return false;
        }

        $date = ($on ?? CarbonImmutable::today())->startOfDay();

        return $date->greaterThanOrEqualTo($this->valid_from->startOfDay())
            && $date->lessThanOrEqualTo($this->valid_to->startOfDay());
    }

    /** Agreed, but its period is behind us. */
    public function hasExpired(?CarbonImmutable $on = null): bool
    {
        return $this->status === 'ACTIVE'
            && ($on ?? CarbonImmutable::today())->startOfDay()->greaterThan($this->valid_to->startOfDay());
    }

    public function daysToExpiry(?CarbonImmutable $on = null): int
    {
        return (int) ($on ?? CarbonImmutable::today())->startOfDay()
            ->diffInDays($this->valid_to->startOfDay(), false);
    }

    /**
     * Contracts that apply on a date — the resolver's own question.
     *
     * @param  Builder<self>  $query
     */
    public function scopeLiveOn(Builder $query, CarbonImmutable $date): Builder
    {
        return $query->where('status', 'ACTIVE')
            ->whereDate('valid_from', '<=', $date->toDateString())
            ->whereDate('valid_to', '>=', $date->toDateString());
    }

    /**
     * A contract with no factory is the whole company's.
     *
     * @param  Builder<self>  $query
     */
    public function scopeForFactory(Builder $query, ?string $factoryId): Builder
    {
        if ($factoryId === null) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->whereNull('factory_id')
            ->orWhere('factory_id', $factoryId));
    }
}
