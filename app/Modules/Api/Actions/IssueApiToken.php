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

/**
 * Minting a bearer token (API 3).
 *
 * Two callers, one mechanism. What differs is what stands behind the token:
 * a person's roles, which change, or a machine client's fixed scope list.
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
     * @return array{token: ApiToken, plain: string}
     */
    public function forUser(
        User $user,
        string $companyId,
        string $name,
        ?array $abilities = null,
        ?int $days = null,
    ): array {
        if (! $user->belongsToCompany($companyId)) {
            // Refused rather than silently re-pointed at a company they do
            // belong to. A token for the wrong factory is worse than no token.
            throw ApiException::of(ErrorCode::TENANT_ACCESS_DENIED);
        }

        return $this->create([
            'company_id' => $companyId,
            'user_id' => $user->id,
            'name' => $name,
            'abilities_json' => $abilities,
        ], $days);
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
     * unless something goes looking for them.
     */
    public function revokeAllFor(User $user, ?string $companyId = null): int
    {
        $query = ApiToken::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('user_id', $user->id)
            ->whereNull('revoked_at');

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        return $query->update(['revoked_at' => Carbon::now()]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{token: ApiToken, plain: string}
     */
    private function create(array $attributes, ?int $days): array
    {
        [$plain, $hash] = ApiToken::mint();

        $lifetime = min(
            max($days ?? ApiToken::DEFAULT_LIFETIME_DAYS, 1),
            ApiToken::MAX_LIFETIME_DAYS,
        );

        $token = ApiToken::create($attributes + [
            'token_hash' => $hash,
            'last_four' => substr($plain, -4),
            'expires_at' => Carbon::now()->addDays($lifetime),
        ]);

        return ['token' => $token, 'plain' => $plain];
    }
}
