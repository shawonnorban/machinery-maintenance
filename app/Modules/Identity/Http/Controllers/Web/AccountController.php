<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Web;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Api\Models\ApiToken;
use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Identity\Models\User;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Scopes\TenantScope;
use App\Shared\Support\UserAgent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The one screen a person owns rather than administers (SRS 50.2).
 *
 * Password and the list of devices currently signed in. The two belong
 * together because they answer one question: is this account still only mine?
 *
 * No permission guards any of it. Every one of these acts on the account of
 * whoever is asking, and a technician who cannot reach a single other screen
 * still has to be able to change their own password.
 */
class AccountController extends Controller
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('identity::account.index', [
            'user' => $user,
            'sessions' => $this->sessions($request),
            'tokens' => $this->tokens($user),
        ]);
    }

    /**
     * Every live token this person holds, from both schemes live during the
     * Sanctum migration — a person's tokens minted after the cutover are
     * Sanctum's, but one minted before it keeps working (and showing up
     * here) until it expires or is revoked.
     *
     * @return Collection<int, object{source: string, id: string, name: string, last_four: ?string, last_used_at: ?\Illuminate\Support\Carbon, expires_at: ?\Illuminate\Support\Carbon}>
     */
    private function tokens(User $user): Collection
    {
        $sanctum = $user->tokens->map(fn ($t): object => (object) [
            'source' => 'sanctum',
            'id' => (string) $t->id,
            'name' => $t->name,
            'last_four' => $t->last_four,
            'last_used_at' => $t->last_used_at,
            'expires_at' => $t->expires_at,
        ]);

        $legacy = ApiToken::withoutGlobalScope(TenantScope::class)
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->get()
            ->map(fn (ApiToken $t): object => (object) [
                'source' => 'legacy',
                'id' => $t->id,
                'name' => $t->name,
                'last_four' => $t->last_four,
                'last_used_at' => $t->last_used_at,
                'expires_at' => $t->expires_at,
            ]);

        return $sanctum->merge($legacy)
            ->sortByDesc(fn (object $t) => $t->last_used_at)
            ->values();
    }

    public function changePassword(Request $request, IssueApiToken $tokens): RedirectResponse
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
        // sessions and tokens may have leaked with it; leaving those alive
        // makes the change ceremonial. This session is kept so the person is
        // not thrown out of the screen they are standing on (SRS 50.2).
        $tokens->revokeAllFor($user);

        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        $this->audit->event(
            'SECURITY_EVENT',
            ['reason' => 'PASSWORD_CHANGED'],
            userId: $user->id,
            label: 'PASSWORD_CHANGED',
        );

        return back()->with('status', __('account.password_changed'));
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
        // Tried as a Sanctum token first (what this list mostly shows going
        // forward), falling back to the legacy scheme for a token minted
        // before the cutover. Both lookups are scoped to the asker rather
        // than a company: a person in three companies holds a token for
        // each, and being able to see one but not stop it would be worse
        // than showing neither.
        $sanctum = $request->user()->tokens()->find($token);

        if ($sanctum !== null) {
            $sanctum->delete();

            return back()->with('status', __('account.token_revoked'));
        }

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
                'agent' => UserAgent::describe((string) ($row->user_agent ?? '')),
                'last_activity' => $row->last_activity,
            ])
            ->values()
            ->all();
    }
}
