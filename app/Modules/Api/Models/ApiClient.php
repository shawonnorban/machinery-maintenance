<?php

declare(strict_types=1);

namespace App\Modules\Api\Models;

use App\Modules\Identity\Models\User;
use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A machine's credentials (API 4.2, SRS 43).
 *
 * Never issued to a browser. This is how somebody else's software — an ERP
 * posting costs, a dye house controller posting meter readings — identifies
 * itself, and it is deliberately weaker than a person's account: it holds an
 * explicit list of permission codes and is refused everywhere else, so a
 * credential minted for meter readings cannot close a work order even though
 * the administrator who created it could.
 */
class ApiClient extends Model
{
    use BelongsToTenant;
    use HasUlids;

    protected $fillable = [
        'company_id', 'name', 'client_id', 'secret_hash', 'scopes_json',
        'status', 'last_used_at', 'expires_at', 'revoked_at', 'created_by',
        'secret_rotated_at',
    ];

    protected $hidden = ['secret_hash'];

    protected function casts(): array
    {
        return [
            'scopes_json' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'secret_rotated_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * A fresh secret, returned in the clear exactly once.
     *
     * The caller is responsible for showing it and then forgetting it. What
     * is stored is the hash, so a leaked database gives an attacker nothing
     * they can present as a credential.
     *
     * @return array{0: string, 1: string} the plain secret, then its hash
     */
    public static function mintSecret(): array
    {
        $secret = 'sk_'.Str::random(48);

        return [$secret, Hash::make($secret)];
    }

    public static function mintClientId(): string
    {
        return 'cid_'.Str::lower((string) Str::ulid());
    }

    public function verifySecret(string $secret): bool
    {
        return Hash::check($secret, $this->secret_hash);
    }

    /**
     * Usable right now.
     *
     * Three separate ways to stop being usable, and they are not the same
     * thing: revoked is deliberate, expired is the clock, and inactive is a
     * pause somebody expects to undo.
     */
    public function isUsable(): bool
    {
        if ($this->status !== 'ACTIVE' || $this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        $scopes = $this->scopes_json;

        return is_array($scopes) ? array_values(array_filter($scopes, 'is_string')) : [];
    }

    /**
     * An empty scope list grants nothing.
     *
     * The tempting reading — "no restrictions listed, so no restrictions" — is
     * how a credential meant for one integration ends up able to do everything.
     */
    public function allows(string $permission): bool
    {
        return in_array($permission, $this->scopes(), true);
    }

    public function touchUsage(): void
    {
        // Written without touching updated_at: last use is traffic, not an
        // edit, and an audit reader should be able to tell them apart.
        $this->forceFill(['last_used_at' => Carbon::now()])->saveQuietly();
    }
}
