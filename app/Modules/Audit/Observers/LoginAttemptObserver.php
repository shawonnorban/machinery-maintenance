<?php

declare(strict_types=1);

namespace App\Modules\Audit\Observers;

use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Identity\Models\LoginAttempt;
use App\Modules\Identity\Models\User;

/**
 * Failed sign-ins, from the record the login flow already writes (SRS 34, 50).
 *
 * Taken from LoginAttempt rather than from Laravel's Failed event, because this
 * application validates credentials itself and never calls Auth::attempt — so
 * that event never fires. The attempt row is also the better source: it
 * distinguishes an unknown address from a wrong password from a locked-out
 * account, and a security review wants exactly that difference.
 *
 * Successful attempts are left alone. Auth::login already fires the Login event
 * the recorder listens for, and two rows for one sign-in makes the trail read
 * as though people logged in twice.
 */
class LoginAttemptObserver
{
    public function __construct(private readonly AuditRecorder $recorder) {}

    public function created(LoginAttempt $attempt): void
    {
        if ($attempt->successful) {
            return;
        }

        $this->recorder->event(
            'LOGIN_FAILED',
            [
                'email' => $attempt->email,
                'reason' => $attempt->failure_reason,
                'ip_address' => $attempt->ip_address,
            ],
            // An attempt against an address nobody recognises belongs to no
            // tenant. Inheriting whichever company happened to be resolved
            // would file a stranger's attempt under a real customer.
            companyId: $this->companyFor($attempt),
            userId: $attempt->user_id,
            label: $attempt->email,
            inheritCompany: false,
        );
    }

    /**
     * The company the attempted account belongs to, where the email matched a
     * user at all.
     *
     * A factory admin reviewing failed sign-ins wants to see attempts against
     * their own people; attempts against addresses nobody recognises belong to
     * the platform, not to a tenant.
     */
    private function companyFor(LoginAttempt $attempt): ?string
    {
        if ($attempt->user_id === null) {
            return null;
        }

        return User::find($attempt->user_id)
            ?->memberships()
            ->where('status', 'ACTIVE')
            ->orderByDesc('is_default')
            ->value('company_id');
    }
}
