<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Web;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Api\Models\ApiToken;
use App\Modules\Asset\Services\QrCodeRenderer;
use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Identity\Actions\ManageMfa;
use App\Modules\Identity\Models\MfaRecoveryCode;
use App\Modules\Identity\Services\Totp;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Scopes\TenantScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The one screen a person owns rather than administers (SRS 50.2, 50.3).
 *
 * Password, second factor, and the list of devices currently signed in. Three
 * things that belong together because they answer one question: is this
 * account still only mine?
 *
 * No permission guards any of it. Every one of these acts on the account of
 * whoever is asking, and a technician who cannot reach a single other screen
 * still has to be able to change their own password.
 */
class AccountController extends Controller
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function index(Request $request, QrCodeRenderer $qr): View
    {
        $user = $request->user();

        $enrolling = $user->mfa_secret !== null && ! $user->hasMfa();

        return view('identity::account.index', [
            'user' => $user,
            'mfaOn' => $user->hasMfa(),
            'enrolling' => $enrolling,
            // Only while enrolling. Once confirmed there is no way back to the
            // secret, which is the point of it being a secret.
            'enrolmentQr' => $enrolling
                ? $qr->inlineSvg(
                    app(Totp::class)
                        ->provisioningUri($user->mfa_secret, $user->email, config('app.name')),
                    200,
                )
                : null,
            'enrolmentSecret' => $enrolling ? $user->mfa_secret : null,
            'recoveryRemaining' => MfaRecoveryCode::where('user_id', $user->id)
                ->whereNull('used_at')
                ->count(),
            'sessions' => $this->sessions($request),
            'tokens' => ApiToken::withoutGlobalScope(TenantScope::class)
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->orderByDesc('last_used_at')
                ->get(),
        ]);
    }

    public function changePassword(Request $request, ManageMfa $mfa, IssueApiToken $tokens): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            // SRS 50.1: at least 10 characters and checked against a
            // known-breached list. No forced periodic rotation.
            'password' => ['required', 'confirmed', PasswordRule::min(10)->uncompromised()],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            // Asked for even though the person is already signed in: this is
            // the check that stops a borrowed unlocked laptop from becoming a
            // permanent one.
            throw ValidationException::withMessages([
                'current_password' => __('account.current_password_wrong'),
            ]);
        }

        $user->forceFill(['password' => $data['password']])->save();

        // A password changed because it may have leaked is a password whose
        // sessions and tokens may have leaked with it. This session is kept so
        // the person is not thrown out of the screen they are standing on.
        $mfa->revokeEverythingFor($user, $tokens, $request->session()->getId());

        $this->audit->event(
            'SECURITY_EVENT',
            ['reason' => 'PASSWORD_CHANGED'],
            userId: $user->id,
            label: 'PASSWORD_CHANGED',
        );

        return back()->with('status', __('account.password_changed'));
    }

    // -- Second factor ------------------------------------------------------

    public function beginMfa(Request $request, ManageMfa $mfa): RedirectResponse
    {
        $mfa->begin($request->user(), config('app.name'));

        return back();
    }

    public function confirmMfa(Request $request, ManageMfa $mfa): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:16']]);

        $codes = $mfa->confirm($request->user(), $data['code']);

        // Flashed, never stored anywhere readable. Shown on the next render
        // and gone after it.
        return back()
            ->with('status', __('account.mfa_enabled'))
            ->with('recovery_codes', $codes);
    }

    public function cancelMfa(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasMfa()) {
            $user->forceFill(['mfa_secret' => null])->save();
        }

        return back();
    }

    public function disableMfa(Request $request, ManageMfa $mfa): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:32']]);

        $mfa->disable($request->user(), $data['code']);

        return back()->with('status', __('account.mfa_disabled'));
    }

    public function regenerateRecoveryCodes(Request $request, ManageMfa $mfa): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:32']]);

        if (! $mfa->verifyChallenge($request->user(), $data['code'])) {
            throw ValidationException::withMessages(['code' => __('account.mfa_code_wrong')]);
        }

        return back()
            ->with('status', __('account.recovery_codes_regenerated'))
            ->with('recovery_codes', $mfa->regenerateRecoveryCodes($request->user()));
    }

    // -- Devices ------------------------------------------------------------

    /**
     * Sign one device out.
     */
    public function revokeSession(Request $request, string $session): RedirectResponse
    {
        $deleted = DB::table('sessions')
            ->where('id', $session)
            // Scoped to the asker. Without this, anybody could sign out
            // anybody by guessing a session id.
            ->where('user_id', $request->user()->id)
            ->delete();

        return back()->with('status', $deleted > 0
            ? __('account.session_revoked')
            : __('account.session_gone'));
    }

    public function revokeToken(Request $request, string $token): RedirectResponse
    {
        // Resolved without the tenant scope, because this list is not scoped
        // to a company either: a person in three companies holds a token for
        // each, and being able to see one but not stop it would be worse than
        // showing neither. The ownership check is what makes that safe.
        $row = ApiToken::withoutGlobalScope(TenantScope::class)
            ->whereKey($token)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($row === null) {
            abort(404);
        }

        $row->revoke();

        return back()->with('status', __('account.token_revoked'));
    }

    /**
     * Everywhere this account is signed in, this browser included.
     *
     * @return list<array<string, mixed>>
     */
    private function sessions(Request $request): array
    {
        if (config('session.driver') !== 'database') {
            // Nothing to list. A file or cookie driver keeps no index of who
            // is signed in where, and inventing an empty list that looks
            // authoritative would be worse than saying so.
            return [];
        }

        $current = $request->session()->getId();

        return DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($row): array => [
                'id' => $row->id,
                'is_current' => $row->id === $current,
                'ip_address' => $row->ip_address,
                'agent' => $this->describeAgent((string) ($row->user_agent ?? '')),
                'last_activity' => $row->last_activity,
            ])
            ->values()
            ->all();
    }

    /**
     * A user agent string, reduced to something a person can recognise.
     *
     * Not parsing: recognising. Somebody deciding whether to sign a device out
     * needs "Chrome on Android", not a hundred characters of version tokens
     * they will skip over.
     */
    private function describeAgent(string $agent): string
    {
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Safari/') => 'Safari',
            default => __('account.unknown_browser'),
        };

        $platform = match (true) {
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => __('account.unknown_platform'),
        };

        return $browser.' · '.$platform;
    }
}
