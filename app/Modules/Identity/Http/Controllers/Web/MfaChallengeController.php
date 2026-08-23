<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Web;

use App\Modules\Identity\Actions\ManageMfa;
use App\Modules\Identity\Models\User;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The second half of signing in (SRS 50.3).
 *
 * Nothing is authenticated while this screen is on the display. The session
 * holds an id and a remember-me preference, and neither is a login — somebody
 * who closes the tab here is signed out, not signed in, which is the only
 * behaviour that makes the factor worth having.
 */
class MfaChallengeController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return redirect()->route('login');
        }

        return view('identity::auth.mfa-challenge', ['email' => $user->email]);
    }

    public function store(Request $request, ManageMfa $mfa, LoginController $login): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            // The half-finished login expired or the session was cleared.
            // Starting again is the honest answer.
            return redirect()->route('login')->withErrors(['email' => __('account.mfa_start_again')]);
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        if (! $mfa->verifyChallenge($user, $data['code'])) {
            throw ValidationException::withMessages([
                'code' => __('account.mfa_code_wrong'),
            ]);
        }

        return $login->completeLogin(
            $request,
            $user,
            (bool) $request->session()->get(LoginController::PENDING_REMEMBER_KEY, false),
        );
    }

    /**
     * Who is halfway through, if anybody.
     *
     * Re-read from the database rather than trusted from the session: an
     * account suspended between the password and the code should not get in
     * on the strength of a session written a minute ago.
     */
    private function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get(LoginController::PENDING_USER_KEY);

        if (! is_string($id)) {
            return null;
        }

        $user = User::find($id);

        return $user !== null && $user->isActive() && $user->hasMfa() ? $user : null;
    }
}
