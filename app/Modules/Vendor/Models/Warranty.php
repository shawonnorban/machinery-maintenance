<?php

declare(strict_types=1);

namespace App\Modules\Vendor\Models;

use App\Modules\Asset\Models\Asset;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cover on one machine, from one vendor, between two dates (SRS 26).
 *
 * Exists so a technician standing at a broken machine can be told the repair is
 * already paid for. That is the whole value: a machine under warranty repaired
 * at the factory's own cost is money thrown away, and it happens because the
 * warranty is in a drawer in the office.
 */
class Warranty extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['MANUFACTURER', 'EXTENDED', 'SERVICE'];

    public const STATUSES = ['ACTIVE', 'EXPIRED', 'VOID'];

    protected $table = 'warranties';

    protected $fillable = [
        'company_id', 'asset_id', 'vendor_id', 'warranty_type', 'reference',
        'start_date', 'end_date', 'coverage', 'exclusions', 'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(WarrantyClaim::class);
    }

    /**
     * Cover in force on a given day.
     *
     * Status alone is not enough: a sweep marks warranties expired once a day,
     * and between midnight and that sweep a lapsed warranty would still read as
     * ACTIVE. The dates are the truth; the status is a convenience for lists.
     */
    public function isActiveOn(?CarbonImmutable $date = null): bool
    {
        $date ??= CarbonImmutable::now();

        return $this->status !== 'VOID'
            && $this->start_date->lessThanOrEqualTo($date)
            && $this->end_date->greaterThanOrEqualTo($date->startOfDay());
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
