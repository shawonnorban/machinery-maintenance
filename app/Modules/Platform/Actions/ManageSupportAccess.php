<?php

declare(strict_types=1);

namespace App\Modules\Platform\Actions;

use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\PermissionResolver;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Modules\Platform\Models\SupportGrant;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Platform support access to a customer's data (SRS 5.4).
 *
 * "Silent platform access to tenant data is prohibited", and four things make
 * that true rather than aspirational: a written reason, an expiry, an audit row
 * at each end, and a notification the customer can read.
 *
 * The mechanism is impersonation of a real user in the company rather than some
 * parallel platform-wide permission. That decision carries most of the safety
 * here. Acting as a named user means every policy, factory scope and permission
 * check applies exactly as it does for that person — support staff cannot see
 * more than the customer's own account can — and every row written during the
 * session already carries `impersonated_by`, so the audit trail says who was
 * really behind it without anything else having to know about support at all.
 */
class ManageSupportAccess
{
    /** The longest a grant may run. Support calls end; standing access does not. */
    private const MAX_HOURS = 8;

    public const SESSION_KEY = 'impersonated_by';

    public const GRANT_KEY = 'support_grant_id';

    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly NotificationDispatcher $notifications,
        private readonly TenantContext $context,
    ) {}

    /**
     * Open a grant. This alone lets nobody see anything: it is permission to
     * enter, and entering is a separate, separately audited step.
     */
    public function open(Company $company, User $staff, string $reason, int $hours): SupportGrant
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 10) {
            // A dropdown of reasons becomes a habit; a sentence somebody has
            // to write is one they have to mean.
            throw ValidationException::withMessages([
                'reason' => __('platform.reason_too_short'),
            ]);
        }

        $hours = min(max($hours, 1), self::MAX_HOURS);
        $now = Carbon::now();

        $grant = SupportGrant::create([
            'company_id' => $company->id,
            'granted_to' => $staff->id,
            'reason' => $reason,
            'starts_at' => $now,
            'expires_at' => $now->copy()->addHours($hours),
        ]);

        $this->audit->event(
            'SECURITY_EVENT',
            [
                'reason' => 'SUPPORT_GRANT_OPENED',
                'company_id' => $company->id,
                'grant_id' => $grant->id,
                'stated_reason' => $reason,
                'expires_at' => $grant->expires_at->toIso8601String(),
            ],
            userId: $staff->id,
            label: 'SUPPORT_GRANT_OPENED',
        );

        $this->tellTheCustomer($company, [
            'name' => $staff->name,
            'reason' => $reason,
            'until' => $grant->expires_at->toDayDateTimeString(),
        ]);

        return $grant;
    }

    /**
     * Step inside, as a named user of the company.
     *
     * The user has to be picked rather than derived, and the choice is visible
     * in the audit row: "acted as the owner" and "acted as a storekeeper" are
     * different amounts of access, and whoever reviews this later should not
     * have to guess which happened.
     */
    public function enter(SupportGrant $grant, User $staff, User $asUser): void
    {
        if (! $grant->isActive() || $grant->granted_to !== $staff->id) {
            throw ValidationException::withMessages([
                'grant' => __('platform.grant_not_active'),
            ]);
        }

        $membership = CompanyUser::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $grant->company_id)
            ->where('user_id', $asUser->id)
            ->where('status', 'ACTIVE')
            ->exists();

        if (! $membership) {
            throw ValidationException::withMessages([
                'user_id' => __('platform.not_a_member'),
            ]);
        }

        $this->audit->event(
            'SECURITY_EVENT',
            [
                'reason' => 'SUPPORT_SESSION_STARTED',
                'company_id' => $grant->company_id,
                'grant_id' => $grant->id,
                'acting_as' => $asUser->id,
                'acting_as_email' => $asUser->email,
            ],
            userId: $staff->id,
            label: 'SUPPORT_SESSION_STARTED',
        );
    }

    /**
     * Step back out.
     *
     * Audited as its own event, because "when did they leave" is half of the
     * answer to "what could they have seen". A grant that only records its
     * beginning tells a customer nothing about how long somebody was inside.
     */
    public function leave(SupportGrant $grant, User $staff): void
    {
        $this->audit->event(
            'SECURITY_EVENT',
            [
                'reason' => 'SUPPORT_SESSION_ENDED',
                'company_id' => $grant->company_id,
                'grant_id' => $grant->id,
            ],
            userId: $staff->id,
            label: 'SUPPORT_SESSION_ENDED',
        );
    }

    /**
     * Hand the grant back before the clock runs out, which is the normal case:
     * the call ends before the eight hours do.
     */
    public function close(SupportGrant $grant, User $staff): void
    {
        $grant->end($staff->id);

        $this->audit->event(
            'SECURITY_EVENT',
            [
                'reason' => 'SUPPORT_GRANT_CLOSED',
                'company_id' => $grant->company_id,
                'grant_id' => $grant->id,
            ],
            userId: $staff->id,
            label: 'SUPPORT_GRANT_CLOSED',
        );

        // No second notification. The customer was told when access was
        // granted, which is what SRS 5.4 asks for; telling them again when it
        // ends turns a message that matters into a pair they learn to skip.
        // The close is in the audit log, where the question "how long were
        // they inside" is actually asked.
    }

    /**
     * The customer's own notification (SRS 5.4: "Tenant-visible notification
     * that support access occurred").
     *
     * Sent to whoever can manage the company's users — its owners and
     * administrators — rather than to everybody. A technician has no use for
     * it, and a notification everybody receives is one nobody reads.
     *
     * @param  array<string, mixed>  $data
     */
    private function tellTheCustomer(?Company $company, array $data): void
    {
        if ($company === null) {
            return;
        }

        // The dispatcher writes tenant-scoped rows and reads the context for
        // the company id, so it needs the tenant it is writing for — and this
        // runs from the platform area, which deliberately has none.
        $restore = $this->context->companyIdOrNull();
        $this->context->set($company->id);

        try {
            $recipients = $this->companyKeyholders($company);

            if ($recipients->isEmpty()) {
                return;
            }

            $this->notifications->sendToMany(
                $recipients,
                'SUPPORT_ACCESS',
                $data + ['company' => $company->name],
                'WARNING',
            );
        } finally {
            $this->context->forget();

            if ($restore !== null) {
                $this->context->set($restore);
            }
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function companyKeyholders(Company $company)
    {
        $userIds = CompanyUser::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $company->id)
            ->where('status', 'ACTIVE')
            ->pluck('user_id');

        return User::whereIn('id', $userIds)
            ->get()
            ->filter(fn (User $user): bool => app(PermissionResolver::class)
                ->has($user, $company->id, 'admin.user.manage'))
            ->values();
    }
}
