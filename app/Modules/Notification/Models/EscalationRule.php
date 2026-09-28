<?php

declare(strict_types=1);

namespace App\Modules\Notification\Models;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\Team;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Who gets told next, and how long the first person has (SRS 28).
 *
 * `delay_minutes` is measured from the original event, never from the previous
 * escalation. Chaining the delays lets a stalled chain drift: two levels each
 * described as "thirty minutes later" silently become ninety when the first
 * escalation itself runs late, and the factory manager hears about a stopped
 * line an hour after they should have.
 */
class EscalationRule extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'escalation_rules';

    protected $fillable = [
        'company_id', 'factory_id', 'event_type', 'severity', 'delay_minutes',
        'escalation_level', 'escalation_role_id', 'escalation_team_id',
        'escalation_user_id', 'channel_overrides_json', 'max_escalations',
        'stop_on_acknowledge', 'active',
    ];

    protected function casts(): array
    {
        return [
            'channel_overrides_json' => 'array',
            'delay_minutes' => 'integer',
            'escalation_level' => 'integer',
            'max_escalations' => 'integer',
            'stop_on_acknowledge' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'escalation_role_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'escalation_team_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalation_user_id');
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    /**
     * Whether this rule covers a given notification.
     *
     * A rule with no severity covers every severity, and a rule with no factory
     * covers every factory. Narrowing is opt-in, so a company-wide rule written
     * once keeps working when a new factory opens.
     */
    public function coversNotification(Notification $notification): bool
    {
        if (! $this->active) {
            return false;
        }

        if ($this->event_type !== $notification->event_type) {
            return false;
        }

        if ($this->severity !== null && $this->severity !== $notification->severity) {
            return false;
        }

        if ($this->factory_id !== null && $this->factory_id !== $notification->factory_id) {
            return false;
        }

        return true;
    }
}
