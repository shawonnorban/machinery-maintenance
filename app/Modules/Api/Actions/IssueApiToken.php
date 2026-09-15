<?php

declare(strict_types=1);

namespace App\Modules\Api\Actions;

use App\Modules\Api\Models\ApiClient;
use App\Modules\Api\Models\ApiToken;
use App\Modules\Identity\Models\User;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Scopes\TenantScope;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Minting a bearer token (API 3).
 *
 * Two callers, one mechanism no longer, mid-migration to Sanctum: a person's
 * token is now a Sanctum personal access token (`forUser`), while a
 * machine's stays on the legacy `ApiToken` scheme (`forClient`) — Sanctum has
 * no client-credentials equivalent for a caller with no user behind it.
 * `AuthenticateApiToken` resolves either kind of bearer token back to the
 * same `TokenHandle` interface, so nothing downstream of minting needs to
 * know which scheme issued a given token.
 *
 * The plain token is returned here and nowhere else. Nothing stores it, no
 * screen reads it back, and there is no endpoint that will show it again — a
 * secret a support session can retrieve is a secret that leaks through support
 * sessions.
 */
class IssueApiToken
{
    /**
     * @param  list<string>|null  $abilities  a subset of what the user can do, or null for all of it
     * @return array{token: PersonalAccessToken, plain: string}
     */
    public function forUser(
        User $user,
        string $companyId,
        string $name,
        ?array $abilities = null,
        ?int $days = null,
        ?string $impersonatedBy = null,
    ): array {
        if (! $user->belongsToCompany($companyId)) {
            // Refused rather than silently re-pointed at a company they do
            // belong to. A token for the wrong factory is worse than no token.
            throw ApiException::of(ErrorCode::TENANT_ACCESS_DENIED);
        }

        $expiresAt = Carbon::now()->addDays($this->lifetimeDays($days));

        // Sanctum's own default is ['*'] for "everything the guard allows";
        // null here means the same thing as it did for the legacy scheme.
        $minted = $user->createToken($name, $abilities ?? ['*'], $expiresAt);

        // Neither company_id/last_four nor impersonated_by is part of
        // Sanctum's own schema — see the migrations that added them to
        // personal_access_tokens. company_id carries the same invariant the
        // legacy ApiToken table enforced: a token is minted for exactly one
        // company. last_four is what lets the account screen tell tokens
        // apart without ever showing one in full again. impersonated_by is
        // set only when this token is a support-access session (Platform
        // API §6) entered on somebody else's behalf; AuditRecorder reads it
        // back onto every row the session writes, the same fact the web
        // flow carries in `session('impersonated_by')`.
        $minted->accessToken->forceFill([
            'company_id' => $companyId,
            'last_four' => substr($minted->plainTextToken, -4),
            'impersonated_by' => $impersonatedBy,
        ])->save();

        return ['token' => $minted->accessToken, 'plain' => $minted->plainTextToken];
    }

    /**
     * A token for platform staff (Platform API §1), who belong to no
     * company at all (SRS 5) — `personal_access_tokens.company_id` is
     * nullable for exactly this case. Refused to anyone whose
     * `is_platform_admin` flag is not set, so this method itself is the one
     * place that invariant has to hold rather than every caller of it.
     * `AuthenticateApiToken` never sees this token: platform routes verify
     * it and resolve the caller through a separate, tenant-context-free
     * middleware, the same way the web platform area never resolves a
     * tenant either.
     *
     * @return array{token: PersonalAccessToken, plain: string}
     */
    public function forPlatformAdmin(User $user, string $name, ?int $days = null): array
    {
        if (! $user->is_platform_admin) {
            throw ApiException::of(ErrorCode::FORBIDDEN);
        }

        $expiresAt = Carbon::now()->addDays($this->lifetimeDays($days));

        $minted = $user->createToken($name, ['*'], $expiresAt);

        $minted->accessToken->forceFill([
            'last_four' => substr($minted->plainTextToken, -4),
        ])->save();

        return ['token' => $minted->accessToken, 'plain' => $minted->plainTextToken];
    }

    /**
     * @return array{token: ApiToken, plain: string}
     */
    public function forClient(ApiClient $client, ?int $days = null): array
    {
        return $this->create([
            'company_id' => $client->company_id,
            'api_client_id' => $client->id,
            'name' => $client->name,
            // Never null for a machine. The scope list on the client is the
            // control; a token that inherited "everything" would defeat it.
            'abilities_json' => $client->scopes(),
        ], $days);
    }

    /**
     * Revoke every token a person holds for one company.
     *
     * Used when somebody is removed from a company or their password changes:
     * the tokens are the part of an account that survives a password change
     * unless something goes looking for them. Both schemes, since a person's
     * tokens may span the Sanctum cutover — a legacy token minted before it,
     * still live, must go just as surely as a Sanctum one minted after.
     */
    public function revokeAllFor(User $user, ?string $companyId = null): int
    {
        $legacy = ApiToken::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('user_id', $user->id)
            ->whereNull('revoked_at');

        $sanctum = $user->tokens();

        if ($companyId !== null) {
            $legacy->where('company_id', $companyId);
            $sanctum->where('company_id', $companyId);
        }

        $revoked = $legacy->update(['revoked_at' => Carbon::now()]);

        return $revoked + $sanctum->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{token: ApiToken, plain: string}
     */
    private function create(array $attributes, ?int $days): array
    {
        [$plain, $hash] = ApiToken::mint();

        $token = ApiToken::create($attributes + [
            'token_hash' => $hash,
            'last_four' => substr($plain, -4),
            'expires_at' => Carbon::now()->addDays($this->lifetimeDays($days)),
        ]);

        return ['token' => $token, 'plain' => $plain];
    }

    private function lifetimeDays(?int $days): int
    {
        return min(max($days ?? ApiToken::DEFAULT_LIFETIME_DAYS, 1), ApiToken::MAX_LIFETIME_DAYS);
    }
}
