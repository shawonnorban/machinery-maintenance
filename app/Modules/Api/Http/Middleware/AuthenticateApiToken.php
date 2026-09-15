<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Middleware;

use App\Modules\Api\Models\ApiClient;
use App\Modules\Api\Models\ApiToken;
use App\Modules\Api\Support\ApiCaller;
use App\Modules\Api\Support\LegacyTokenHandle;
use App\Modules\Api\Support\SanctumTokenHandle;
use App\Modules\Api\Support\TokenHandle;
use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\PermissionResolver;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * The front door for machine callers (API 3, 34).
 *
 * This middleware owns tenant resolution for the API, rather than leaving it
 * to the web application's `ResolveTenantContext`. The reason is that a token
 * *is* the company: it was minted for exactly one, and deriving the company
 * from anything else — a header, a default membership — would mean a stolen
 * token could be pointed at data it was never issued for.
 *
 * An `X-Company-Id` that disagrees with the token is refused rather than
 * ignored. Silently overriding a client's explicit instruction is how a caller
 * ends up writing last month's readings into the wrong factory and believing
 * it worked.
 *
 * Two token schemes are live during the Sanctum migration: a bearer token is
 * tried as a Sanctum personal access token first (the scheme person callers
 * are issued going forward), and falls back to the legacy `ApiToken` table
 * (still the only scheme for machine/client-credential callers, and for any
 * person's token minted before the cutover). `TokenHandle` is what lets
 * everything past this point stop caring which scheme answered.
 */
class AuthenticateApiToken
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly PermissionResolver $permissions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->resolveToken($request);

        $caller = $token->apiClientId() !== null
            ? $this->machineCaller($token)
            : $this->personCaller($token);

        $this->assertCompanyUsable($caller->companyId);

        $requested = $request->header('X-Company-Id');

        if (is_string($requested) && $requested !== '' && $requested !== $caller->companyId) {
            $this->recordCrossTenantAttempt($request, $caller, $requested);

            throw ApiException::of(ErrorCode::TENANT_ACCESS_DENIED, __('api.token_company_mismatch'));
        }

        $this->context->set(
            $caller->companyId,
            $caller->user !== null
                ? $this->permissions->accessibleFactoryIds($caller->user, $caller->companyId)
                : $this->allFactoryIds($caller->companyId),
        );

        // Bound rather than passed, because every API controller needs it and
        // threading it through each signature would be noise.
        app()->instance(ApiCaller::class, $caller);

        if ($caller->user !== null) {
            // So audit rows, idempotency claims and anything else that reads
            // `$request->user()` see the person behind the token. This is not
            // a login: no session is started and none is wanted.
            $request->setUserResolver(fn (): User => $caller->user);
        }

        $request->attributes->set('api_client_id', $caller->client?->id);

        $token->touchUsage();

        $response = $next($request);

        if ($token instanceof LegacyTokenHandle) {
            // Not an error — a nudge. The legacy scheme still works, but a
            // caller minting new tokens against /auth/token or /auth/login
            // now receives Sanctum ones; this header is how an integration
            // still holding an old one notices before the scheme is retired.
            $response->headers->set('Deprecation', 'true');
        }

        return $response;
    }

    /**
     * The bearer token, resolved against whichever of the two schemes issued
     * it, or a refusal.
     *
     * Every failure here is the same 401 with the same message. Telling a
     * caller whether a token was unknown, revoked or merely expired hands an
     * attacker a way to sort stolen strings into "worth trying again later".
     */
    private function resolveToken(Request $request): TokenHandle
    {
        $bearer = $request->bearerToken();

        if (! is_string($bearer) || $bearer === '') {
            throw ApiException::of(ErrorCode::UNAUTHENTICATED);
        }

        $sanctum = PersonalAccessToken::findToken($bearer);

        if ($sanctum !== null) {
            if (! $sanctum->tokenable instanceof User) {
                // Sanctum is issued only to User in this application; a token
                // pointed at anything else cannot be a caller this API knows.
                throw ApiException::of(ErrorCode::UNAUTHENTICATED);
            }

            $handle = new SanctumTokenHandle($sanctum);

            if (! $handle->isUsable()) {
                throw ApiException::of(ErrorCode::UNAUTHENTICATED);
            }

            return $handle;
        }

        $legacy = ApiToken::withoutGlobalScope(TenantScope::class)
            ->where('token_hash', ApiToken::hash($bearer))
            ->first();

        if ($legacy === null || ! $legacy->isUsable()) {
            throw ApiException::of(ErrorCode::UNAUTHENTICATED);
        }

        return new LegacyTokenHandle($legacy);
    }

    private function personCaller(TokenHandle $token): ApiCaller
    {
        $userId = $token->userId();
        $user = $userId === null ? null : User::find($userId);

        if ($user === null || ! $user->isActive()) {
            throw ApiException::of(ErrorCode::UNAUTHENTICATED);
        }

        // Membership is re-checked on every request, not trusted from minting
        // time. Somebody removed from a company this morning must stop being
        // able to read it this morning, whatever they are still holding.
        if (! $user->belongsToCompany($token->companyId())) {
            throw ApiException::of(ErrorCode::TENANT_ACCESS_DENIED);
        }

        return ApiCaller::forUser($token, $user);
    }

    private function machineCaller(TokenHandle $token): ApiCaller
    {
        $client = ApiClient::withoutGlobalScope(TenantScope::class)->find($token->apiClientId());

        if ($client === null || ! $client->isUsable()) {
            throw ApiException::of(ErrorCode::UNAUTHENTICATED);
        }

        return ApiCaller::forClient($token, $client);
    }

    /**
     * The web equivalent of this check (`ResolveTenantContext`) runs on
     * every request; this one has to as well, not only at suspend time —
     * a token minted before a suspension keeps working until something
     * checks the company itself, not just the token's own validity.
     *
     * withTrashed(), deliberately: a closed company resolves to null
     * otherwise, and a token surviving its company's closure is exactly
     * the case this exists to catch.
     */
    private function assertCompanyUsable(string $companyId): void
    {
        $company = Company::withTrashed()->find($companyId);

        if ($company === null || $company->trashed()) {
            throw ApiException::of(ErrorCode::TENANT_ACCESS_DENIED);
        }

        if ($company->isSuspended()) {
            throw ApiException::of(ErrorCode::TENANT_SUSPENDED, __('tenancy.suspended_body', [
                'company' => $company->name,
                'reason' => $company->suspension_reason ?? __('tenancy.suspended_no_reason'),
            ]));
        }
    }

    /**
     * A machine client reaches every factory in its company.
     *
     * It has no role assignment to narrow it, and inventing a narrowing would
     * be a guess. Whoever mints the credential narrows it with scopes instead,
     * which is the control that was actually designed for this.
     *
     * @return list<string>
     */
    private function allFactoryIds(string $companyId): array
    {
        return Factory::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $companyId)
            ->pluck('id')
            ->all();
    }

    private function recordCrossTenantAttempt(Request $request, ApiCaller $caller, string $requested): void
    {
        // Either a bug in an integration or somebody trying doors. Both are
        // worth seeing, and neither is visible from an error response alone.
        app(AuditRecorder::class)->event(
            'SECURITY_EVENT',
            [
                'reason' => 'TENANT_ACCESS_DENIED',
                'requested_company_id' => $requested,
                'token_company_id' => $caller->companyId,
                'path' => $request->path(),
            ],
            userId: $caller->auditUserId(),
            label: 'TENANT_ACCESS_DENIED',
        );
    }
}
