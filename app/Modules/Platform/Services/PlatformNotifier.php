<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Notification\Models\Notification;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;

/**
 * Tells the rest of the platform staff what one of them just did.
 *
 * The audit log already records all of this, and that is not the same thing: an
 * audit log answers "what happened to this customer" when somebody thinks to
 * go and look. Support access being opened at four in the afternoon is
 * something colleagues should learn without going to look.
 *
 * Written to the same notifications table as everything else, with a null
 * company_id meaning "the platform". The tenant scope never matches null, so
 * these are invisible inside every customer's system without a single extra
 * condition anywhere.
 *
 * The person who did the thing is not told about it. A notification saying
 * "you opened support access" is noise, and noise is how a bell stops being
 * read.
 */
class PlatformNotifier
{
    public function __construct(private readonly TenantContext $context) {}

    public function notify(
        string $eventType,
        string $title,
        ?string $body = null,
        string $severity = 'INFO',
        ?string $actionUrl = null,
        ?string $exceptUserId = null,
    ): void {
        $recipients = User::query()
            ->where('is_platform_admin', true)
            ->where('status', 'ACTIVE')
            ->when($exceptUserId !== null, fn ($query) => $query->whereKeyNot($exceptUserId))
            ->pluck('id');

        // Written with no tenant context, which is not a trick — it is the
        // literal truth about these rows, and it is also the only way to get
        // them written correctly. BelongsToTenant fills company_id from
        // whatever context happens to be set, and it cannot tell a caller who
        // left the field out from one who deliberately passed null. A platform
        // administrator raising this from inside a customer's page would
        // otherwise stamp that customer's id onto a notification meant for the
        // platform, and it would then appear inside that customer's system.
        $this->context->runWithoutTenant(function () use ($recipients, $eventType, $title, $body, $severity, $actionUrl): void {
            foreach ($recipients as $userId) {
                Notification::withoutGlobalScope(TenantScope::class)->create([
                    'user_id' => $userId,
                    'event_type' => $eventType,
                    'title' => $title,
                    'body' => $body,
                    'severity' => $severity,
                    'action_url' => $actionUrl,
                    'locale' => 'en',
                ]);
            }
        });
    }
}
