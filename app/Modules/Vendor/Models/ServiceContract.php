<?php

declare(strict_types=1);

namespace App\Modules\Vendor\Models;

use App\Modules\Asset\Models\Asset;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * An AMC or service contract (SRS 26, ERD Section 15).
 *
 * Scope is the awkward part and the reason for three columns rather than one.
 * A contract may cover one machine, every machine in a factory, or a named list
 * of machines across factories. Forcing it to one asset would make the contract
 * value meaningless the moment somebody signs an AMC for a whole line.
 *
 * A renewal is a new contract that points at the one it replaced, never an
 * edit. Changing the dates on last year's contract erases what was agreed last
 * year, and the value of an AMC history is exactly that it shows what changed
 * between renewals.
 */
class ServiceContract extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['AMC', 'CALIBRATION', 'INSPECTION', 'SUPPORT'];

    public const STATUSES = ['ACTIVE', 'EXPIRED', 'CANCELLED', 'RENEWED'];

    protected $table = 'service_contracts';

    protected $fillable = [
        'company_id', 'vendor_id', 'asset_id', 'factory_id', 'contract_number',
        'contract_type', 'start_date', 'end_date', 'renewal_date', 'value',
        'currency', 'coverage', 'visits_per_year', 'response_time_hours',
        'status', 'renewed_from_contract_id', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'renewal_date' => 'immutable_date',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewed_from_contract_id');
    }

    public function assets(): BelongsToMany
    {
        // The pivot carries created_at only: an attachment is made or removed,
        // never edited, so there is nothing for an updated_at to record.
        return $this->belongsToMany(Asset::class, 'service_contract_assets')
            ->withPivot(['company_id', 'created_at']);
    }

    public function isActiveOn(?CarbonImmutable $date = null): bool
    {
        $date ??= CarbonImmutable::now();

        return ! in_array($this->status, ['CANCELLED'], true)
            && $this->start_date->lessThanOrEqualTo($date)
            && $this->end_date->greaterThanOrEqualTo($date->startOfDay());
    }

    /**
     * Whether this contract covers a given machine.
     *
     * Three shapes of scope, answered in one place so a screen, a report and an
     * alert cannot disagree about whether a machine is covered.
     */
    public function covers(Asset $asset): bool
    {
        if ($this->asset_id !== null) {
            return $this->asset_id === $asset->id;
        }

        if ($this->assets()->where('assets.id', $asset->id)->exists()) {
            return true;
        }

        // A factory-wide contract covers what is in that factory now, which is
        // also what it will cover after a machine is transferred in.
        return $this->factory_id !== null && $this->factory_id === $asset->current_factory_id;
    }

    public function daysRemaining(?CarbonImmutable $from = null): int
    {
        $from ??= CarbonImmutable::now();

        return (int) $from->startOfDay()->diffInDays($this->end_date, absolute: false);
    }

    public function scopeExpiringWithin(Builder $query, int $days): Builder
    {
        $now = CarbonImmutable::now();

        return $query->where('status', 'ACTIVE')
            ->whereBetween('end_date', [$now->toDateString(), $now->addDays($days)->toDateString()]);
    }
}
