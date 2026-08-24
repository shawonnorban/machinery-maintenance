<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\PermissionResolver;
use App\Shared\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Establishes the tenant context for the request.
 *
 * The critical rule (SRS 4, ADR-042 rule 1): the company is derived from
 * authenticated membership and re-validated here. A client-supplied
 * X-Company-Id only *selects among* memberships the user already has; it can
 * never grant access to a company they do not belong to.
 */
class ResolveTenantContext
{
    public const SESSION_KEY = 'active_company_id';

    public const FACTORY_SCOPE_KEY = 'factory_scope_id';

    public function __construct(
        private readonly TenantContext $context,
        private readonly PermissionResolver $permissions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $requested = $this->requestedCompanyId($request);

        if ($requested !== null) {
            // Membership check before anything else. An id naming a company
            // the user does not belong to is refused, never honoured.
            if (! $user->belongsToCompany($requested)) {
                return $this->denyTenantAccess($request);
            }

            $companyId = $requested;
        } else {
            $companyId = $this->defaultCompanyIdFor($user);

            if ($companyId === null) {
                // Platform staff belong to no company by design (SRS 5), and
                // the platform area needs no tenant. Passing through with no
                // context is correct for them; anything under /app that needs
                // a tenant will still refuse, because TenantContext throws
                // rather than quietly returning an unscoped result.
                if ($user->is_platform_admin) {
                    return $next($request);
                }

                // Authenticated but with no active membership. This is not a
                // 403 on a specific resource; the user has no tenant at all.
                return $this->denyNoMembership($request);
            }
        }

        $this->context->set(
            $companyId,
            $this->permissions->accessibleFactoryIds($user, $companyId),
        );

        if ($request->hasSession()) {
            $request->session()->put(self::SESSION_KEY, $companyId);

            $this->context->setFactoryScope(
                $request->session()->get(self::FACTORY_SCOPE_KEY),
            );
        }

        return $next($request);
    }

    /**
     * Session wins for web requests; the header serves API clients and the
     * company switcher. Neither is trusted without the membership check above.
     */
    private function requestedCompanyId(Request $request): ?string
    {
        $header = $request->header('X-Company-Id');

        if (is_string($header) && $header !== '') {
            return $header;
        }

        if ($request->hasSession()) {
            $session = $request->session()->get(self::SESSION_KEY);

            if (is_string($session) && $session !== '') {
                return $session;
            }
        }

        return null;
    }

    private function defaultCompanyIdFor(User $user): ?string
    {
        $membership = $user->memberships()
            ->where('status', 'ACTIVE')
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->first();

        return $membership?->company_id;
    }

    private function denyTenantAccess(Request $request): Response
    {
        // A request naming a company the user does not belong to is the single
        // most important thing this system can log: it is either a bug or an
        // attempt, and both need to be visible (SRS 34, ADR-061).
        app(AuditRecorder::class)->event(
            'SECURITY_EVENT',
            [
                'reason' => 'TENANT_ACCESS_DENIED',
                'requested_company_id' => $this->requestedCompanyId($request),
                'path' => $request->path(),
            ],
            userId: $request->user()?->id,
            label: 'TENANT_ACCESS_DENIED',
        );

        // 403 rather than 404 here: the caller named a tenant explicitly, so
        // there is nothing left to conceal (API 2).
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => __('auth.tenant_access_denied'),
                'code' => 'TENANT_ACCESS_DENIED',
                'meta' => ['request_id' => $request->attributes->get('request_id')],
            ], 403);
        }

        abort(403, __('auth.tenant_access_denied'));
    }

    private function denyNoMembership(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => __('auth.no_company_membership'),
                'code' => 'TENANT_CONTEXT_REQUIRED',
                'meta' => ['request_id' => $request->attributes->get('request_id')],
            ], 403);
        }

        abort(403, __('auth.no_company_membership'));
    }
}
