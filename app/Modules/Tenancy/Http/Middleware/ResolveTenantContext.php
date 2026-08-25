<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\PermissionResolver;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\CompanyDomain;
use App\Shared\Scopes\TenantScope;
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

        // The host wins over the session, and that ordering is the point. A
        // person who works for two companies in a group and opens the second
        // one's address should land in the second one — not in whichever they
        // had open in another tab an hour ago.
        $hostCompanyId = $this->companyIdForHost($request->getHost());

        $requested = $hostCompanyId ?? $this->requestedCompanyId($request);

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

        // Suspension is checked here rather than at sign-in, because signing in
        // is not the only way to arrive: a live session, a company switch and a
        // bookmarked URL all pass through this middleware and none of them
        // through the login screen. Deleting sessions when a company is
        // suspended stops the people already inside; this is what stops them
        // coming back.
        // withTrashed, deliberately. Company is soft-deleted, so a closed
        // customer resolves to null here — and a null company skipped the check
        // below and let their people in with a context pointing at a company
        // that no longer exists. A membership naming a company we cannot find
        // is not a reason to admit somebody either.
        $company = Company::withTrashed()->find($companyId);

        if (! $this->isAlwaysAllowed($request)) {
            if ($company === null || $company->trashed()) {
                return $this->denyClosed($request, $company);
            }

            if ($company->isSuspended()) {
                return $this->denySuspended($request, $company);
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
     * The customer whose address this request arrived on, if any.
     *
     * Verified rows only. An unverified row is a claim — somebody has typed
     * maintenance.another-company.com into a form — and honouring a claim
     * would hand them another company's sign-in page.
     *
     * The platform's own host is skipped without a query, because that is the
     * common case and it resolves from membership as it always has.
     */
    private function companyIdForHost(string $host): ?string
    {
        $host = CompanyDomain::normaliseHost($host);

        if ($host === '' || $host === CompanyDomain::normaliseHost((string) config('tenancy.platform_host'))) {
            return null;
        }

        return CompanyDomain::withoutGlobalScope(TenantScope::class)
            ->where('host', $host)
            ->whereNotNull('verified_at')
            ->value('company_id');
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

    /**
     * The few things that stay open while a company is suspended.
     *
     * Signing out, changing language, and switching to another company the
     * person belongs to. Locking somebody into a dead-end page they cannot
     * leave is a second problem on top of the first, and a person who works
     * for two companies in a group should not lose both because one was
     * stopped.
     */
    private function isAlwaysAllowed(Request $request): bool
    {
        return $request->is('logout', 'app/locale', 'app/switch-company');
    }

    /**
     * The company is stopped, and the customer is told why.
     *
     * A screen rather than a bare 403, and the reason is on it. Somebody whose
     * whole company has just stopped working will otherwise ring support to ask
     * a question the platform already knows the answer to — and a refusal with
     * no explanation reads as a fault in the product rather than a decision
     * somebody made.
     *
     * Signing out stays available, and so does the account screen: locking a
     * person into a dead-end page they cannot leave is a second problem on top
     * of the first.
     */
    private function denySuspended(Request $request, Company $company): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => __('tenancy.suspended_body', [
                    'company' => $company->name,
                    'reason' => $company->suspension_reason ?? __('tenancy.suspended_no_reason'),
                ]),
                'code' => 'TENANT_SUSPENDED',
                'meta' => ['request_id' => $request->attributes->get('request_id')],
            ], 403);
        }

        return response()->view('tenancy::suspended', [
            'company' => $company,
            'since' => $company->suspended_at,
            'reason' => $company->suspension_reason,
        ], 403);
    }

    /**
     * A closed account, which is a different sentence from a suspended one: a
     * suspension is something that can be lifted this afternoon, and this is
     * an account that has been ended. Neither says "error", because neither is
     * one.
     */
    private function denyClosed(Request $request, ?Company $company): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => __('tenancy.closed_body'),
                'code' => 'TENANT_CLOSED',
                'meta' => ['request_id' => $request->attributes->get('request_id')],
            ], 403);
        }

        return response()->view('tenancy::closed', [
            'company' => $company,
            'since' => $company?->deleted_at,
        ], 403);
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
