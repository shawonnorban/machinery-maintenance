<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to hand an event to somebody else's system (ERD Section 22).
 *
 * Keeps what was sent, what came back and how long it took. "We sent it" and
 * "they received it" are different claims, and an integration argument is only
 * ever settled by the second one.
 */
class WebhookDelivery extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['PENDING', 'DELIVERED', 'FAILED', 'EXHAUSTED'];

    /**
     * Exponential backoff in minutes (ERD Section 22).
     *
     * Long tails on purpose. A receiver that is down is usually down for hours,
     * and retrying every thirty seconds turns their outage into our queue
     * backlog.
     */
    public const BACKOFF_MINUTES = [1, 5, 30, 120, 360, 1440];

    public $timestamps = false;

    protected $table = 'webhook_deliveries';

    protected $fillable = [
        'company_id', 'webhook_endpoint_id', 'event_type', 'event_id',
        'payload_json', 'request_headers_json', 'signature', 'status',
        'attempt_count', 'response_status', 'response_body_excerpt',
        'duration_ms', 'last_attempted_at', 'next_retry_at', 'delivered_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'request_headers_json' => 'array',
            'last_attempted_at' => 'immutable_datetime',
            'next_retry_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    /**
     * One first attempt plus one retry per backoff step (ERD Section 22).
     *
     * Seven attempts in total, not six: the schedule describes the waits
     * between retries, so the delivery that starts the sequence is not one of
     * them.
     */
    public function hasAttemptsLeft(): bool
    {
        return $this->attempt_count <= count(self::BACKOFF_MINUTES);
    }

    /**
     * When the next attempt is due, or null when there are none left.
     *
     * After the first failure the wait is the first step, so the index is one
     * behind the attempt count. Reading them as the same number would skip the
     * one-minute retry entirely and make the shortest step in the schedule
     * dead code.
     */
    public function nextRetryAt(?CarbonImmutable $from = null): ?CarbonImmutable
    {
        if (! $this->hasAttemptsLeft()) {
            return null;
        }

        $minutes = self::BACKOFF_MINUTES[$this->attempt_count - 1] ?? null;

        return $minutes === null ? null : ($from ?? CarbonImmutable::now())->addMinutes($minutes);
    }

    public function succeeded(): bool
    {
        return $this->status === 'DELIVERED';
    }
}
