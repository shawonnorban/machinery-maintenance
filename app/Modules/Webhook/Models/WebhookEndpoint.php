<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Somewhere to send events (ERD Section 22, SRS 43).
 *
 * Disabled automatically after enough consecutive failures. Consecutive
 * matters: an endpoint that fails once a week and recovers is a flaky network,
 * while one that has failed the last six times in a row is gone, and retrying
 * it forever costs the platform a queue worker for nobody's benefit.
 */
class WebhookEndpoint extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['ACTIVE', 'PAUSED', 'DISABLED'];

    /** After this many consecutive failures the endpoint is switched off. */
    public const FAILURE_LIMIT = 6;

    /** How long a rotated secret keeps being honoured. */
    public const ROTATION_WINDOW_HOURS = 24;

    protected $table = 'webhook_endpoints';

    protected $fillable = [
        'company_id', 'url', 'description', 'secret', 'signing_algorithm',
        'previous_secret', 'secret_rotated_at', 'status',
        'consecutive_failure_count', 'disabled_at', 'disabled_reason', 'created_by',
    ];

    /**
     * Never serialised. A secret that can leave through an API response or a
     * log line is a secret that has already leaked.
     */
    protected $hidden = ['secret', 'previous_secret'];

    protected function casts(): array
    {
        return [
            'secret_rotated_at' => 'immutable_datetime',
            'disabled_at' => 'immutable_datetime',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(WebhookSubscription::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function isDeliverable(): bool
    {
        return $this->status === 'ACTIVE';
    }

    /**
     * Whether the old secret is still inside its rotation window.
     */
    public function honoursPreviousSecret(?CarbonImmutable $now = null): bool
    {
        if ($this->previous_secret === null || $this->secret_rotated_at === null) {
            return false;
        }

        $now ??= CarbonImmutable::now();

        return $this->secret_rotated_at->addHours(self::ROTATION_WINDOW_HOURS)->greaterThan($now);
    }

    public function subscribesTo(string $eventType): bool
    {
        return $this->subscriptions->contains('event_type', $eventType);
    }
}
