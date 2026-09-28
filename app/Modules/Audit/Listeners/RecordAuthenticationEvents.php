<?php

declare(strict_types=1);

namespace App\Modules\Audit\Listeners;

use App\Modules\Audit\Observers\LoginAttemptObserver;
use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;

/**
 * Who signed in, and who signed out (SRS 34, SRS 50).
 *
 * Failed attempts are recorded from the login attempt row instead
 * ({@see LoginAttemptObserver}), which knows why
 * the attempt failed.
 *
 * Nothing here ever records a submitted password — not hashed, not truncated,
 * not its length. The audit log is the last place a credential should be
 * recoverable from.
 */
class RecordAuthenticationEvents
{
    public function __construct(private readonly AuditRecorder $recorder) {}

    public function onLogin(Login $event): void
    {
        $user = $event->user;

        $this->recorder->event(
            'LOGIN',
            ['guard' => $event->guard, 'remember' => $event->remember],
            companyId: $this->companyFor($user),
            userId: $user->getAuthIdentifier(),
            label: $user->email ?? null,
        );
    }

    public function onLogout(Logout $event): void
    {
        $user = $event->user;

        if ($user === null) {
            return;
        }

        $this->recorder->event(
            'LOGOUT',
            [],
            companyId: $this->companyFor($user),
            userId: $user->getAuthIdentifier(),
            label: $user->email ?? null,
        );
    }

    public function onPasswordReset(PasswordReset $event): void
    {
        $user = $event->user;

        $this->recorder->event(
            'PASSWORD_CHANGED',
            ['method' => 'RESET_LINK'],
            companyId: $this->companyFor($user),
            userId: $user->getAuthIdentifier(),
            label: $user->email ?? null,
        );
    }

    private function companyFor(mixed $user): ?string
    {
        if (! $user instanceof User) {
            return null;
        }

        // The default membership: a login is not yet scoped to a company, and
        // an audit row with no company at all would be invisible on every
        // tenant's screen.
        return $user->memberships()
            ->where('status', 'ACTIVE')
            ->orderByDesc('is_default')
            ->value('company_id');
    }
}
