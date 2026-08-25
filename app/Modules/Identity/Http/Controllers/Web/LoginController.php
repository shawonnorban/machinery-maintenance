<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Web;

use App\Modules\Identity\Actions\AttemptLogin;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Thin by design (ADR-003): validate, delegate to the Action, respond.
 */
class LoginController extends Controller
{
    public function show(): View
    {
        return view('identity::auth.login');
    }

    public function store(LoginRequest $request, AttemptLogin $attempt): RedirectResponse
    {
        $user = $attempt->verify(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->ip() ?? '0.0.0.0',
        );

        Auth::login($user, $request->boolean('remember'));

        // Recorded here rather than inside the credential check: accepting a
        // password and arriving are not the same event, and this column is
        // asked "when was this account last used".
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        // Prevents session fixation: the pre-login session id is discarded.
        $request->session()->regenerate();

        // Platform staff have no company, so the tenant dashboard has nothing
        // to show them and would refuse to resolve a tenant. Their home is the
        // customer list (SRS 5).
        return redirect()->intended($user->is_platform_admin
            ? route('platform.tenants')
            : route('app.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', __('auth.logged_out'));
    }
}
