<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\LoginAttempt;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The login attempt, shared by the web controller and the API controller
 * (ADR-066). A rule implemented here applies to both entry points; a rule
 * written in a controller would apply to only one.
 *
 * Implements SRS 50.4: rate limiting per account and per IP, progressive
 * lockout, and an audit row for every attempt whatever the outcome.
 */
class AttemptLogin
{
    /** Per-account limit, SRS 50.4 and API 35.1. */
    private const ACCOUNT_ATTEMPTS = 5;

    private const ACCOUNT_DECAY_SECONDS = 60;

    /** Per-IP limit, higher because a factory shares one outbound address. */
    private const IP_ATTEMPTS = 20;

    private const IP_DECAY_SECONDS = 60;

    /**
     * @throws ValidationException
     */
    public function handle(string $email, string $password, string $ip, bool $remember = false): User
    {
        $user = $this->verify($email, $password, $ip);

        Auth::login($user, $remember);

        return $user;
    }

    /**
     * Everything `handle()` does except starting a session.
     *
     * The API needs the credential check, the rate limiting and the audit row,
     * and must not have the session: a bearer token is not a login, and a
     * session cookie handed back from a token endpoint is a cookie nobody
     * asked for and nobody will clear.
     *
     * @throws ValidationException
     */
    public function verify(string $email, string $password, string $ip): User
    {
        $email = Str::lower(trim($email));

        $this->ensureNotRateLimited($email, $ip);

        $user = User::where('email', $email)->first();

        if ($user === null || ! Auth::getProvider()->validateCredentials($user, ['password' => $password])) {
            $this->recordFailure($email, $user?->id, $ip, $user === null ? 'UNKNOWN_EMAIL' : 'BAD_PASSWORD');
            $this->hit($email, $ip);

            // One generic message for both cases. Distinguishing them tells an
            // attacker which addresses are registered (SRS 50.4, API 3).
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if (! $user->isActive()) {
            $this->recordFailure($email, $user->id, $ip, 'ACCOUNT_INACTIVE');
            $this->hit($email, $ip);

            throw ValidationException::withMessages(['email' => __('auth.account_inactive')]);
        }

        RateLimiter::clear($this->accountKey($email));

        LoginAttempt::create([
            'email' => $email,
            'user_id' => $user->id,
            'ip_address' => $ip,
            'successful' => true,
            'attempted_at' => now(),
        ]);

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return $user;
    }

    private function ensureNotRateLimited(string $email, string $ip): void
    {
        foreach ([
            [$this->accountKey($email), self::ACCOUNT_ATTEMPTS],
            [$this->ipKey($ip), self::IP_ATTEMPTS],
        ] as [$key, $max]) {
            if (! RateLimiter::tooManyAttempts($key, $max)) {
                continue;
            }

            Event::dispatch(new Lockout(request()));

            $seconds = RateLimiter::availableIn($key);

            LoginAttempt::create([
                'email' => $email,
                'ip_address' => $ip,
                'successful' => false,
                'failure_reason' => 'RATE_LIMITED',
                'attempted_at' => now(),
            ]);

            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => $seconds]),
            ])->status(429);
        }
    }

    private function hit(string $email, string $ip): void
    {
        RateLimiter::hit($this->accountKey($email), self::ACCOUNT_DECAY_SECONDS);
        RateLimiter::hit($this->ipKey($ip), self::IP_DECAY_SECONDS);
    }

    private function recordFailure(string $email, ?string $userId, string $ip, string $reason): void
    {
        LoginAttempt::create([
            'email' => $email,
            'user_id' => $userId,
            'ip_address' => $ip,
            'successful' => false,
            'failure_reason' => $reason,
            'attempted_at' => now(),
        ]);
    }

    private function accountKey(string $email): string
    {
        return 'login:account:'.sha1($email);
    }

    private function ipKey(string $ip): string
    {
        return 'login:ip:'.sha1($ip);
    }
}
