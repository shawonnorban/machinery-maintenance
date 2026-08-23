<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Controllers\Api;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Api\Models\ApiClient;
use App\Modules\Api\Models\ApiToken;
use App\Modules\Api\Support\ApiCaller;
use App\Modules\Identity\Actions\AttemptLogin;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\Permission;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Scopes\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * How a caller gets in (API 3).
 *
 * Two doors, because there are two kinds of caller. A person exchanges their
 * password for a token; a machine exchanges client credentials for one. Both
 * come out holding the same kind of bearer token, which is what lets every
 * other endpoint stop caring which door was used.
 */
class AuthController extends ApiController
{
    /**
     * A person's token.
     *
     * The credential check, the rate limiting and the audit row all come from
     * the same action the login screen uses (ADR-066), so a rule tightened for
     * one entry point is tightened for both. What this does not do is start a
     * session: a bearer token is not a login.
     */
    public function login(Request $request, AttemptLogin $login, IssueApiToken $tokens): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            // Optional. A person in one company never has to say which.
            'company_id' => ['nullable', 'string', 'size:26'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:'.ApiToken::MAX_LIFETIME_DAYS],
        ]);

        $user = $login->verify($data['email'], $data['password'], (string) $request->ip());

        $companyId = $data['company_id'] ?? $this->defaultCompanyId($user->id);

        if ($companyId === null) {
            throw ApiException::of(ErrorCode::TENANT_CONTEXT_REQUIRED, __('auth.no_company_membership'));
        }

        ['token' => $token, 'plain' => $plain] = $tokens->forUser(
            $user,
            $companyId,
            $data['device_name'] ?? __('api.default_token_name'),
            days: $data['expires_in_days'] ?? null,
        );

        // Set here rather than inside the credential check: a token in hand is
        // an arrival, and that is what this column is asked about afterwards.
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return ApiResponse::created([
            'access_token' => $plain,
            'token_type' => 'Bearer',
            'expires_at' => $token->expires_at?->toIso8601String(),
            'company_id' => $companyId,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'locale' => $user->locale,
            ],
        ]);
    }

    /**
     * A machine's token (API 4.2).
     *
     * Client credentials rather than a password, because there is nobody to
     * type one. The failure is deliberately indistinguishable between an
     * unknown client id and a wrong secret: telling them apart lets somebody
     * enumerate which credentials exist.
     */
    public function token(Request $request, IssueApiToken $tokens): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'string', 'max:64'],
            'client_secret' => ['required', 'string', 'max:255'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:'.ApiToken::MAX_LIFETIME_DAYS],
        ]);

        $client = ApiClient::withoutGlobalScope(TenantScope::class)
            ->where('client_id', $data['client_id'])
            ->first();

        if ($client === null || ! $client->verifySecret($data['client_secret']) || ! $client->isUsable()) {
            throw ApiException::of(ErrorCode::UNAUTHENTICATED);
        }

        $client->touchUsage();

        ['token' => $token, 'plain' => $plain] = $tokens->forClient($client, $data['expires_in_days'] ?? null);

        return ApiResponse::created([
            'access_token' => $plain,
            'token_type' => 'Bearer',
            'expires_at' => $token->expires_at?->toIso8601String(),
            'company_id' => $client->company_id,
            'scopes' => $client->scopes(),
        ]);
    }

    /**
     * Who this token belongs to.
     */
    public function me(ApiCaller $caller): JsonResponse
    {
        return ApiResponse::ok([
            'company_id' => $caller->companyId,
            'kind' => $caller->isMachine() ? 'CLIENT' : 'USER',
            'name' => $caller->label(),
            'user' => $caller->user === null ? null : [
                'id' => $caller->user->id,
                'name' => $caller->user->name,
                'email' => $caller->user->email,
                'locale' => $caller->user->locale,
            ],
            'client' => $caller->client === null ? null : [
                'id' => $caller->client->id,
                'client_id' => $caller->client->client_id,
            ],
            'token' => [
                'id' => $caller->token->id,
                'name' => $caller->token->name,
                'expires_at' => $caller->token->expires_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * What this token may do (API 3).
     *
     * Served so a client can hide what it would be refused rather than
     * discovering it one 403 at a time. The list is what the *token* can do,
     * not what the account behind it could: a read-only token minted for a
     * dashboard reports read-only here.
     */
    public function permissions(ApiCaller $caller): JsonResponse
    {
        $all = Permission::query()->orderBy('code')->pluck('code')->all();

        return ApiResponse::ok([
            'permissions' => $caller->permissionCodes($all),
        ]);
    }

    /**
     * Give up this token.
     *
     * Revoked rather than deleted: a token that stops working leaves a
     * question behind, and the row is the answer to it.
     */
    public function logout(ApiCaller $caller): JsonResponse
    {
        $caller->token->revoke();

        return ApiResponse::noContent();
    }

    /**
     * A token for another of this person's companies (API 3).
     *
     * A new token rather than a mutation of this one. A token that can change
     * which company it reads is a token whose reach nobody can state, and the
     * old one keeps working for the company it was minted for — a client
     * syncing two factories legitimately holds both.
     */
    public function switchCompany(Request $request, ApiCaller $caller, IssueApiToken $tokens): JsonResponse
    {
        if ($caller->isMachine()) {
            // A machine's credentials belong to one company by construction.
            throw ApiException::of(ErrorCode::FORBIDDEN, __('api.client_cannot_switch_company'));
        }

        $data = $request->validate([
            'company_id' => ['required', 'string', 'size:26'],
        ]);

        ['token' => $token, 'plain' => $plain] = $tokens->forUser(
            $caller->user,
            $data['company_id'],
            $caller->token->name,
        );

        return ApiResponse::created([
            'access_token' => $plain,
            'token_type' => 'Bearer',
            'expires_at' => $token->expires_at?->toIso8601String(),
            'company_id' => $data['company_id'],
        ]);
    }

    /**
     * The companies this person may mint a token for.
     */
    public function companies(ApiCaller $caller): JsonResponse
    {
        if ($caller->isMachine()) {
            return ApiResponse::ok([]);
        }

        return ApiResponse::ok(
            $caller->user->accessibleCompanies()
                ->map(fn ($company): array => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'code' => $company->code,
                ])
                ->values()
                ->all(),
        );
    }

    private function defaultCompanyId(string $userId): ?string
    {
        return CompanyUser::withoutGlobalScope(TenantScope::class)
            ->where('user_id', $userId)
            ->where('status', 'ACTIVE')
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->value('company_id');
    }
}
