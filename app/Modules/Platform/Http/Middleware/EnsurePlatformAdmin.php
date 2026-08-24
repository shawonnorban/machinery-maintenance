<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Middleware;

use App\Modules\Audit\Services\AuditRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The gate on the platform area (SRS 5, 5.4).
 *
 * `is_platform_admin` is a column on the user rather than a role assignment,
 * and that is the right shape: every role in this system is scoped to a company
 * or a factory, and platform staff belong to neither. A role assignment would
 * need a `company_id` to hang from, and inventing one would put platform staff
 * inside a tenant — the exact thing SRS 5 says they are not.
 *
 * Being a platform administrator grants nothing inside any company. It grants
 * the ability to see the list of customers, their contracts and their usage,
 * and to open an audited support grant. Tenant data still requires that grant.
 */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('login'));
        }

        if (! $user->is_platform_admin) {
            // Recorded, not merely refused. Somebody reaching for the platform
            // area who should not be there is either a mistake worth knowing
            // about or an attempt worth knowing about.
            app(AuditRecorder::class)->event(
                'SECURITY_EVENT',
                ['reason' => 'PLATFORM_ACCESS_DENIED', 'path' => $request->path()],
                userId: $user->id,
                label: 'PLATFORM_ACCESS_DENIED',
            );

            abort(404);
        }

        return $next($request);
    }
}
