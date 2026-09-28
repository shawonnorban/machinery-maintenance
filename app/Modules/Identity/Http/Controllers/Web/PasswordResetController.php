<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Web;

use App\Shared\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * Password reset (SRS 50.1, 50.2).
 *
 * The response is identical whether or not the address exists: telling a
 * visitor "no such account" turns the form into a way to enumerate staff
 * emails. Tokens are single-use and expire in 60 minutes.
 */
class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('identity::auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:255']]);

        Password::sendResetLink($request->only('email'));

        // Always the same message, whatever the broker returned.
        return back()->with('status', __('auth.reset_link_sent'));
    }

    public function edit(Request $request, string $token): View
    {
        return view('identity::auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => [
                'required', 'confirmed',
                // SRS 50.1: at least 10 characters and checked against a
                // known-breached list. No forced periodic rotation.
                PasswordRule::min(10)->uncompromised(),
            ],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => __($status)]);
        }

        // Every other session is invalidated by the password change, per
        // SRS 50.2, because a reset is how a compromise is recovered from.
        return redirect()->route('login')->with('status', __('auth.password_reset'));
    }
}
