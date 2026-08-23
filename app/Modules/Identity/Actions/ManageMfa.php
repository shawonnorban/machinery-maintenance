<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Identity\Models\MfaRecoveryCode;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\Totp;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turning a second factor on, off, and using it (SRS 50.3).
 *
 * Enrolment is two steps on purpose. `begin()` hands out a secret and a QR
 * code but changes nothing about how the account signs in; `confirm()` is what
 * actually switches the factor on, and it only succeeds if the person can
 * produce a code from the phone they just scanned with. Somebody who scans and
 * then drops the phone in a dye vat is exactly where they started, rather than
 * locked out.
 */
class ManageMfa
{
    /** How many recovery codes are issued at a time. */
    private const RECOVERY_CODE_COUNT = 8;

    /** Attempts per account before a challenge stops being answerable. */
    private const CHALLENGE_ATTEMPTS = 5;

    private const CHALLENGE_DECAY_SECONDS = 60;

    public function __construct(
        private readonly Totp $totp,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * Start enrolment: a secret, and the URI a phone scans.
     *
     * Replaces any unconfirmed secret. A person who abandoned enrolment
     * yesterday and starts again today should get a fresh one rather than a
     * code from a QR they no longer have.
     *
     * @return array{secret: string, uri: string}
     */
    public function begin(User $user, string $issuer): array
    {
        if ($user->hasMfa()) {
            // Re-enrolling silently would replace a working factor with one
            // the person has not proved they can use.
            throw ValidationException::withMessages([
                'mfa' => __('account.mfa_already_on'),
            ]);
        }

        $secret = $this->totp->generateSecret();

        $user->forceFill(['mfa_secret' => $secret, 'mfa_confirmed_at' => null])->save();

        return [
            'secret' => $secret,
            'uri' => $this->totp->provisioningUri($secret, $user->email, $issuer),
        ];
    }

    /**
     * Finish enrolment by proving the phone works.
     *
     * @return list<string> the recovery codes, shown once
     */
    public function confirm(User $user, string $code): array
    {
        if ($user->mfa_secret === null) {
            throw ValidationException::withMessages([
                'code' => __('account.mfa_not_started'),
            ]);
        }

        if (! $this->totp->verify($user->mfa_secret, $code)) {
            throw ValidationException::withMessages([
                'code' => __('account.mfa_code_wrong'),
            ]);
        }

        $user->forceFill(['mfa_confirmed_at' => Carbon::now()])->save();

        $this->audit->event('SECURITY_EVENT', ['reason' => 'MFA_ENABLED'], userId: $user->id, label: 'MFA_ENABLED');

        return $this->regenerateRecoveryCodes($user);
    }

    /**
     * Turn it off.
     *
     * Requires a current code or a recovery code — never a password alone.
     * Somebody who has stolen a session should not be able to remove the
     * factor that would stop them using it.
     */
    public function disable(User $user, string $code): void
    {
        if (! $user->hasMfa()) {
            return;
        }

        if (! $this->verifyChallenge($user, $code)) {
            throw ValidationException::withMessages([
                'code' => __('account.mfa_code_wrong'),
            ]);
        }

        DB::transaction(function () use ($user): void {
            $user->forceFill(['mfa_secret' => null, 'mfa_confirmed_at' => null])->save();

            MfaRecoveryCode::where('user_id', $user->id)->delete();
        });

        $this->audit->event('SECURITY_EVENT', ['reason' => 'MFA_DISABLED'], userId: $user->id, label: 'MFA_DISABLED');
    }

    /**
     * A fresh set of recovery codes, returned in the clear exactly once.
     *
     * The old set stops working immediately. Half a set is worse than none:
     * nobody can tell which of the codes on their printout still work.
     *
     * @return list<string>
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = [];

        DB::transaction(function () use ($user, &$codes): void {
            MfaRecoveryCode::where('user_id', $user->id)->delete();

            for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
                // Grouped for transcription: these get written down, and a
                // run of ten characters is one somebody copies wrongly.
                $code = strtoupper(Str::random(5).'-'.Str::random(5));

                MfaRecoveryCode::create([
                    'user_id' => $user->id,
                    'code_hash' => Hash::make($code),
                ]);

                $codes[] = $code;
            }
        });

        return $codes;
    }

    /**
     * Answer a challenge with either a TOTP code or a recovery code.
     *
     * Rate limited per account. Six digits is a million possibilities, which
     * is a great many for a person and not many at all for a script.
     */
    public function verifyChallenge(User $user, string $code): bool
    {
        $key = 'mfa:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, self::CHALLENGE_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'code' => __('account.mfa_throttled', ['seconds' => RateLimiter::availableIn($key)]),
            ])->status(429);
        }

        if ($user->mfa_secret !== null && $this->totp->verify($user->mfa_secret, $code)) {
            RateLimiter::clear($key);

            return true;
        }

        if ($this->consumeRecoveryCode($user, $code)) {
            RateLimiter::clear($key);

            return true;
        }

        RateLimiter::hit($key, self::CHALLENGE_DECAY_SECONDS);

        $this->audit->event(
            'SECURITY_EVENT',
            ['reason' => 'MFA_FAILED'],
            userId: $user->id,
            label: 'MFA_FAILED',
        );

        return false;
    }

    /**
     * Everything that survives a password change (SRS 50.2).
     *
     * A password changed because it may have leaked is a password whose
     * sessions and tokens may have leaked with it. Leaving those alive makes
     * the change ceremonial.
     */
    public function revokeEverythingFor(User $user, IssueApiToken $tokens, ?string $keepSessionId = null): void
    {
        $tokens->revokeAllFor($user);

        DB::table('sessions')
            ->where('user_id', $user->id)
            ->when($keepSessionId !== null, fn ($q) => $q->where('id', '!=', $keepSessionId))
            ->delete();
    }

    /**
     * A recovery code, used once and never again.
     *
     * Every unused code is checked rather than looked up, because they are
     * hashed and there is nothing to look up by. Eight bcrypt comparisons is
     * the cost of storing them properly.
     */
    private function consumeRecoveryCode(User $user, string $code): bool
    {
        $candidate = strtoupper(trim($code));

        foreach (MfaRecoveryCode::where('user_id', $user->id)->whereNull('used_at')->get() as $row) {
            if (! Hash::check($candidate, $row->code_hash)) {
                continue;
            }

            $row->forceFill(['used_at' => Carbon::now()])->save();

            $this->audit->event(
                'SECURITY_EVENT',
                ['reason' => 'MFA_RECOVERY_CODE_USED'],
                userId: $user->id,
                label: 'MFA_RECOVERY_CODE_USED',
            );

            return true;
        }

        return false;
    }
}
