<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One time-boxed permission for one platform administrator to see inside one
 * company (SRS 5.4).
 *
 * No tenant scope, deliberately. Everything else in this schema is scoped so a
 * company can only reach its own rows; this table is read from both sides — by
 * the platform, to see what is open, and by the customer, to see who has been
 * in — so scoping it would break one of those two.
 */
class SupportGrant extends BaseModel
{
    protected $fillable = [
        'company_id', 'granted_to', 'reason',
        'starts_at', 'expires_at', 'ended_at', 'ended_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function holder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_to');
    }

    /**
     * Usable right now.
     *
     * Three ways to stop being usable and they mean different things: handed
     * back, expired, or not yet begun. Only the first is a decision.
     */
    public function isActive(): bool
    {
        if ($this->ended_at !== null) {
            return false;
        }

        $now = Carbon::now();

        return $this->starts_at->lessThanOrEqualTo($now) && $this->expires_at->greaterThan($now);
    }

    public function end(?string $userId = null): void
    {
        if ($this->ended_at !== null) {
            return;
        }

        $this->forceFill(['ended_at' => Carbon::now(), 'ended_by' => $userId])->save();
    }
}
