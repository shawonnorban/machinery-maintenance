<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Web;

use App\Modules\Identity\Actions\AttemptLogin;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Models\User;
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
    /** Where the half-finished login waits while a code is entered. */
    public const PENDING_USER_KEY = 'mfa.pending_user_id';

    public const PENDING_REMEMBER_KEY = 'mfa.pending_remember';

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

        if ($user->hasMfa()) {
            // Nothing is logged in yet. Only the identity of who is halfway
            // through is remembered, so a challenge screen somebody navigates
            // away from leaves them signed out rather than signed in
            // (SRS 50.3).
            $request->session()->put(self::PENDING_USER_KEY, $user->id);
            $request->session()->put(self::PENDING_REMEMBER_KEY, $request->boolean('remember'));

            return redirect()->route('mfa.challenge');
        }

        return $this->completeLogin($request, $user, $request->boolean('remember'));
    }

    /**
     * Start the session, once there is nothing left to prove.
     */
    public function completeLogin(Request $request, User $user, bool $remember): RedirectResponse
    {
        Auth::login($user, $remember);

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        // Prevents session fixation: the pre-login session id is discarded.
        $request->session()->regenerate();

        $request->session()->forget([self::PENDING_USER_KEY, self::PENDING_REMEMBER_KEY]);

        return redirect()->intended(route('app.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', __('auth.logged_out'));
    }
}
