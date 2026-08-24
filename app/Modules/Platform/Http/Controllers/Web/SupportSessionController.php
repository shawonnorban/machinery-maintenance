<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Web;

use App\Modules\Identity\Models\User;
use App\Modules\Platform\Actions\ManageSupportAccess;
use App\Modules\Platform\Models\SupportGrant;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The way back out of a support session (SRS 5.4).
 *
 * Lives in the tenant application rather than the platform area, because that
 * is where somebody is standing when they need it — signed in as a customer's
 * user, looking at the customer's screens.
 */
class SupportSessionController extends Controller
{
    public function leave(Request $request, ManageSupportAccess $support): RedirectResponse
    {
        $staffId = $request->session()->get(ManageSupportAccess::SESSION_KEY);
        $grantId = $request->session()->get(ManageSupportAccess::GRANT_KEY);

        if (! is_string($staffId)) {
            // Not in a support session. Nothing to leave, and pretending
            // otherwise would sign somebody out of their own account.
            return redirect()->route('app.dashboard');
        }

        $staff = User::find($staffId);
        $grant = is_string($grantId) ? SupportGrant::find($grantId) : null;

        if ($grant !== null && $staff !== null) {
            $support->leave($grant, $staff);
        }

        $request->session()->forget([
            ManageSupportAccess::SESSION_KEY,
            ManageSupportAccess::GRANT_KEY,
            'active_company_id',
        ]);

        if ($staff === null) {
            // The platform account is gone. Signing out entirely is the only
            // honest answer; silently leaving them as the customer's user is
            // the one thing that must not happen.
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        Auth::login($staff);

        // A fresh session id, exactly as a login gets: the session that was
        // acting as somebody else should not carry on as itself.
        $request->session()->regenerate();

        return redirect()->route('platform.tenants');
    }
}
