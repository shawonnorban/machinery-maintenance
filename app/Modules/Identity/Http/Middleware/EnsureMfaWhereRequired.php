<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Settings\Services\SettingsResolver;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces a second factor where it is required (SRS 50.3).
 *
 * "MFA is enforceable per company policy, and is mandatory for Platform Super
 * Admin and Company Owner roles."
 *
 * The important design decision is that this runs *after* signing in, not
 * during it. Refusing the password would lock out every account that has not
 * enrolled yet — switch the policy on at nine in the morning and the whole
 * company is standing outside. Letting somebody in and sending them to the one
 * screen where they can enrol is the only way to enforce the rule without
 * throwing people out of a system they are paying for.
 *
 * Which is also why the exempt list matters: the account screen, signing out
 * and changing language have to stay reachable, or the road to compliance is
 * closed by the rule demanding it.
 */
class EnsureMfaWhereRequired
{
    /**
     * Roles that must hold a second factor whatever the company has decided.
     *
     * Named in SRS 50.3. The platform role matters most of the three: an
     * account that can open a support grant and step inside any customer is
     * not one that should stand behind a single password.
     */
    private const ALWAYS_REQUIRED = ['PLATFORM_SUPER_ADMIN', 'COMPANY_OWNER'];

    /**
     * Paths that stay open, so somebody being asked to enrol can actually get
     * there — and can leave if they would rather not.
     */
    private const ALWAYS_ALLOWED = [
        'app/account',
        'app/account/*',
        'logout',
        'app/locale',
        'app/switch-company',
    ];

    public function __construct(
        private readonly TenantContext $context,
        private readonly SettingsResolver $settings,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->hasMfa() || $request->is(self::ALWAYS_ALLOWED)) {
            return $next($request);
        }

        // A support session is somebody acting as a customer's user. Whether
        // *that* account has a second factor is the customer's business and
        // already decided by their own policy; asking the platform
        // administrator to enrol on the customer's behalf would be absurd, and
        // enrolling would leave a secret behind in the customer's account.
        if ($request->session()->has('impersonated_by')) {
            return $next($request);
        }

        if (! $this->required($user)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => __('account.mfa_required_now'),
                'code' => 'MFA_REQUIRED',
                'meta' => ['request_id' => $request->attributes->get('request_id')],
            ], 403);
        }

        return redirect()
            ->route('app.account')
            ->with('mfa_required', true);
    }

    /**
     * Does this person have to hold a second factor?
     */
    private function required($user): bool
    {
        if ($user->is_platform_admin) {
            return true;
        }

        $companyId = $this->context->companyIdOrNull();

        if ($companyId === null) {
            // No tenant resolved, so no company policy to read and no role to
            // check within one. Nothing to enforce here.
            return false;
        }

        if ($this->companyRequiresIt()) {
            return true;
        }

        return $this->holdsAlwaysRequiredRole($user->id, $companyId);
    }

    /**
     * Has this company turned the policy on?
     *
     * The resolver refuses an unknown key rather than storing it silently
     * (ADR-054), which is right everywhere except here: this middleware runs on
     * every authenticated request, so a deployment that shipped the code before
     * running the seeder would answer 500 on every page — the login screen
     * included — for want of one reference row.
     *
     * A missing definition is read as "not required", and that is safe rather
     * than lenient: without the definition nobody could have turned the policy
     * on. The half of SRS 50.3 that does not depend on a setting — owners and
     * platform staff — is checked separately and keeps working regardless.
     */
    private function companyRequiresIt(): bool
    {
        try {
            return $this->settings->bool('security.require_mfa');
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    private function holdsAlwaysRequiredRole(string $userId, string $companyId): bool
    {
        $roleIds = Role::withoutGlobalScope(TenantScope::class)
            ->whereIn('code', self::ALWAYS_REQUIRED)
            ->pluck('id');

        return UserRole::withoutGlobalScope(TenantScope::class)
            ->where('user_id', $userId)
            ->where('company_id', $companyId)
            ->whereIn('role_id', $roleIds)
            ->exists();
    }
}
