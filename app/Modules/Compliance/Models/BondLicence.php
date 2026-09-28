<?php

declare(strict_types=1);

namespace App\Modules\Compliance\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customs bond licence (Shared Systems spec §3.1).
 *
 * The licence is what lets a factory import fabric and trims without
 * paying duty, against the promise that they leave again as garments.
 * Everything else in this module hangs off it.
 *
 * Expiry is read from the calendar rather than stored as a status, for
 * the same reason as a rate contract: a stored `EXPIRED` is only as true
 * as the last sweep that ran, and being wrong here means importing
 * against a licence that lapsed last month.
 */
class BondLicence extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['ACTIVE', 'SUSPENDED', 'CANCELLED'];

    /**
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'ACTIVE' => ['SUSPENDED', 'CANCELLED'],
        // A suspension is lifted, not undone: customs can restore it.
        'SUSPENDED' => ['ACTIVE', 'CANCELLED'],
        'CANCELLED' => [],
    ];

    protected $table = 'bond_licences';

    protected $fillable = [
        'company_id', 'factory_id', 'licence_no', 'issuing_office', 'issued_date',
        'valid_from', 'valid_to', 'status', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_date' => 'date',
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(BondEntitlement::class);
    }

    public function declarations(): HasMany
    {
        return $this->hasMany(UtilisationDeclaration::class);
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

    /** Active, and today falls inside its period. */
    public function isLive(?CarbonImmutable $on = null): bool
    {
        if ($this->status !== 'ACTIVE') {
            return false;
        }

        $date = ($on ?? CarbonImmutable::today())->startOfDay();

        return $date->greaterThanOrEqualTo($this->valid_from->startOfDay())
            && $date->lessThanOrEqualTo($this->valid_to->startOfDay());
    }

    public function hasExpired(?CarbonImmutable $on = null): bool
    {
        return ($on ?? CarbonImmutable::today())->startOfDay()->greaterThan($this->valid_to->startOfDay());
    }

    public function daysToExpiry(?CarbonImmutable $on = null): int
    {
        return (int) ($on ?? CarbonImmutable::today())->startOfDay()
            ->diffInDays($this->valid_to->startOfDay(), false);
    }
}
