<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Middleware;

use App\Modules\Api\Models\ApiToken;
use App\Modules\Api\Support\ApiCaller;
use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\PermissionResolver;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
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

        $caller = $token->api_client_id !== null
            ? $this->machineCaller($token)
            : $this->personCaller($token);

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

        return $next($request);
    }

    /**
     * The bearer token, or a refusal.
     *
     * Every failure here is the same 401 with the same message. Telling a
     * caller whether a token was unknown, revoked or merely expired hands an
     * attacker a way to sort stolen strings into "worth trying again later".
     */
    private function resolveToken(Request $request): ApiToken
    {
        $bearer = $request->bearerToken();

        if (! is_string($bearer) || $bearer === '') {
            throw ApiException::of(ErrorCode::UNAUTHENTICATED);
        }

        $token = ApiToken::withoutGlobalScope(TenantScope::class)
            ->where('token_hash', ApiToken::hash($bearer))
            ->first();

        if ($token === null || ! $token->isUsable()) {
            throw ApiException::of(ErrorCode::UNAUTHENTICATED);
        }

        return $token;
    }

    private function personCaller(ApiToken $token): ApiCaller
    {
        $user = User::find($token->user_id);

        if ($user === null || ! $user->isActive()) {
            throw ApiException::of(ErrorCode::UNAUTHENTICATED);
        }

        // Membership is re-checked on every request, not trusted from minting
        // time. Somebody removed from a company this morning must stop being
        // able to read it this morning, whatever they are still holding.
        if (! $user->belongsToCompany((string) $token->company_id)) {
            throw ApiException::of(ErrorCode::TENANT_ACCESS_DENIED);
        }

        return ApiCaller::forUser($token, $user);
    }

    private function machineCaller(ApiToken $token): ApiCaller
    {
        $client = $token->client()->withoutGlobalScope(TenantScope::class)->first();

        if ($client === null || ! $client->isUsable()) {
            throw ApiException::of(ErrorCode::UNAUTHENTICATED);
        }

        return ApiCaller::forClient($token, $client);
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
