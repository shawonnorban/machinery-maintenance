<?php

declare(strict_types=1);

namespace App\Modules\Api\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A claim on one operation (API 32, ERD 26).
 *
 * The row is written *before* the work runs, not after. That ordering is the
 * whole mechanism: the unique index on (company, key, endpoint) means two
 * concurrent retries race to insert and exactly one wins, and the loser is
 * told the operation is already in flight rather than being allowed to run it
 * a second time.
 */
class IdempotencyKey extends Model
{
    use BelongsToTenant;
    use HasUlids;

    public const UPDATED_AT = null;

    /** How long a key answers for a replay before it becomes a new request. */
    public const TTL_HOURS = 24;

    protected $fillable = [
        'company_id', 'user_id', 'api_client_id', 'key', 'endpoint',
        'request_hash', 'status', 'response_status', 'response_body_json',
        'resource_type', 'resource_id', 'locked_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'response_body_json' => 'array',
            'locked_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function isComplete(): bool
    {
        return $this->status === 'COMPLETED';
    }

    public function hasExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
