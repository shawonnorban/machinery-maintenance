<?php

declare(strict_types=1);

namespace App\Modules\Api\Actions;

use App\Modules\Api\Models\ApiClient;
use App\Modules\Api\Models\ApiToken;
use App\Modules\Identity\Models\Permission;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Minting and withdrawing a machine's credentials (API 4.2).
 *
 * The whole design rests on one idea: a credential is narrower than the person
 * who created it. An administrator who can do everything still has to say,
 * explicitly, which handful of things the ERP may do — and if they say nothing,
 * the answer is nothing rather than everything.
 */
class ManageApiClient
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  list<string>  $scopes
     * @return array{client: ApiClient, secret: string}
     */
    public function create(string $name, array $scopes, ?string $expiresAt, string $userId): array
    {
        $scopes = $this->validateScopes($scopes);

        [$secret, $hash] = ApiClient::mintSecret();

        $client = ApiClient::create([
            'company_id' => $this->context->companyId(),
            'name' => $name,
            'client_id' => ApiClient::mintClientId(),
            'secret_hash' => $hash,
            'scopes_json' => $scopes,
            'status' => 'ACTIVE',
            'expires_at' => $expiresAt,
            'created_by' => $userId,
        ]);

        return ['client' => $client, 'secret' => $secret];
    }

    /**
     * @param  list<string>  $scopes
     */
    public function updateScopes(ApiClient $client, array $scopes): ApiClient
    {
        $scopes = $this->validateScopes($scopes);

        $client->forceFill(['scopes_json' => $scopes])->save();

        // Tokens already issued carry the scope list they were minted with, so
        // narrowing a client here would leave the wider tokens running. They
        // are revoked instead: the next call re-mints against the new list.
        $this->revokeTokens($client);

        return $client;
    }

    /**
     * A new secret. The old one stops working immediately, which is the point
     * — rotation exists for the day somebody pasted the old one into a ticket.
     *
     * @return array{client: ApiClient, secret: string}
     */
    public function rotateSecret(ApiClient $client): array
    {
        [$secret, $hash] = ApiClient::mintSecret();

        $client->forceFill([
            'secret_hash' => $hash,
            'secret_rotated_at' => Carbon::now(),
        ])->save();

        $this->revokeTokens($client);

        return ['client' => $client, 'secret' => $secret];
    }

    /**
     * Withdraw the credential.
     *
     * Revoked rather than deleted, and the row stays: months later somebody
     * will ask what posted a reading last March, and a deleted client makes
     * that question unanswerable.
     */
    public function revoke(ApiClient $client): ApiClient
    {
        $client->forceFill([
            'status' => 'REVOKED',
            'revoked_at' => Carbon::now(),
        ])->save();

        $this->revokeTokens($client);

        return $client;
    }

    /**
     * Every scope must be a real permission code.
     *
     * A typo would otherwise become a scope that grants nothing and refuses
     * everything, and the integration would fail with a 403 nobody can explain.
     *
     * @param  list<string>  $scopes
     * @return list<string>
     */
    private function validateScopes(array $scopes): array
    {
        $scopes = array_values(array_unique(array_filter(array_map('trim', $scopes))));

        if ($scopes === []) {
            throw ValidationException::withMessages([
                'scopes' => __('api.scopes_required'),
            ]);
        }

        $known = Permission::query()->pluck('code')->all();
        $unknown = array_diff($scopes, $known);

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'scopes' => __('api.scope_unknown'),
            ]);
        }

        return $scopes;
    }

    private function revokeTokens(ApiClient $client): void
    {
        ApiToken::withoutGlobalScope(TenantScope::class)
            ->where('api_client_id', $client->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => Carbon::now()]);
    }
}
