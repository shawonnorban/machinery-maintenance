<?php

declare(strict_types=1);

namespace App\Modules\Api\Models;

use App\Modules\Identity\Models\User;
use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A bearer token (API 3).
 *
 * Minted in one of two ways and checked in one: a person exchanges their
 * password for one, a machine exchanges its client credentials, and every
 * request afterwards presents the same `Authorization: Bearer` header.
 */
class ApiToken extends Model
{
    use BelongsToTenant;
    use HasUlids;

    /** How long a token lasts unless the caller asks for less. */
    public const DEFAULT_LIFETIME_DAYS = 30;

    /** The longest a token may be minted for, whatever was asked. */
    public const MAX_LIFETIME_DAYS = 365;

    protected $fillable = [
        'company_id', 'user_id', 'api_client_id', 'name', 'token_hash',
        'last_four', 'abilities_json', 'last_used_at', 'expires_at', 'revoked_at',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'abilities_json' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class, 'api_client_id');
    }

    /**
     * A fresh token, returned in the clear exactly once.
     *
     * @return array{0: string, 1: string} the plain token, then its hash
     */
    public static function mint(): array
    {
        $plain = 'mmt_'.Str::random(40);

        return [$plain, self::hash($plain)];
    }

    /**
     * SHA-256, not bcrypt.
     *
     * This is looked up by exact match on every request, so it has to be
     * indexable, and the input is 40 characters of CSPRNG output rather than
     * anything a person chose — there is no dictionary to slow down.
     */
    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * Usable right now.
     *
     * Revoked and expired are kept apart because they mean different things to
     * whoever is reading the list: one was a decision, the other was the clock.
     */
    public function isUsable(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /**
     * The permission subset this token carries, or null for "whatever the
     * caller behind it can do".
     *
     * @return list<string>|null
     */
    public function abilities(): ?array
    {
        $abilities = $this->abilities_json;

        if (! is_array($abilities)) {
            return null;
        }

        return array_values(array_filter($abilities, 'is_string'));
    }

    public function permits(string $permission): bool
    {
        $abilities = $this->abilities();

        return $abilities === null || in_array($permission, $abilities, true);
    }

    public function touchUsage(): void
    {
        // Traffic, not an edit. Written quietly so `updated_at` still means
        // "somebody changed this token".
        $this->forceFill(['last_used_at' => Carbon::now()])->saveQuietly();
    }

    public function revoke(): void
    {
        $this->forceFill(['revoked_at' => Carbon::now()])->save();
    }
}
