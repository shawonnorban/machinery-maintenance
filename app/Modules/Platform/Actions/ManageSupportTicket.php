<?php

declare(strict_types=1);

namespace App\Modules\Platform\Actions;

use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\PermissionResolver;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Modules\Platform\Models\SupportTicket;
use App\Modules\Platform\Models\SupportTicketMessage;
use App\Modules\Platform\Services\PlatformNotifier;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * A customer asking the platform something, in writing.
 *
 * The opposite direction from a support grant: nobody's data is touched by
 * opening or answering a ticket, so none of the ceremony a grant needs — a
 * reason, an expiry, an audit row at each end — applies here. What is worth
 * keeping is who is told: platform staff hear about a new ticket without
 * going to look, and the customer hears about a reply the same way.
 */
class ManageSupportTicket
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly NotificationDispatcher $notifications,
        private readonly PlatformNotifier $platformNotifier,
        private readonly TenantContext $context,
    ) {}

    public function open(Company $company, User $opener, string $subject, string $body): SupportTicket
    {
        $subject = trim($subject);
        $body = trim($body);

        if ($subject === '' || $body === '') {
            throw ValidationException::withMessages([
                'body' => __('platform.ticket_body_required'),
            ]);
        }

        $now = Carbon::now();

        $ticket = SupportTicket::create([
            'company_id' => $company->id,
            'opened_by' => $opener->id,
            'subject' => $subject,
            'status' => 'OPEN',
            'last_message_at' => $now,
        ]);

        SupportTicketMessage::create([
            'ticket_id' => $ticket->id,
            'company_id' => $company->id,
            'author_id' => $opener->id,
            'author_is_platform' => false,
            'body' => $body,
            'created_at' => $now,
        ]);

        $this->audit->event(
            'CREATED',
            ['reason' => 'TICKET_OPENED', 'company_id' => $company->id, 'ticket_id' => $ticket->id],
            userId: $opener->id,
            label: $subject,
        );

        $this->platformNotifier->notify(
            'PLATFORM_TICKET_OPENED',
            __('platform.notify_ticket_opened', ['name' => $opener->name, 'company' => $company->name]),
            $subject,
            'INFO',
            route('platform.tickets.show', $ticket),
        );

        return $ticket;
    }

    /**
     * Reply to a ticket, from either side of it.
     *
     * A customer's reply reopens a ticket the platform had marked RESOLVED —
     * "resolved" was somebody's belief, and a follow-up message is the
     * customer saying it was wrong. It does not reopen a CLOSED one; that is
     * the end of the conversation, and a customer who needs to raise it again
     * opens a new ticket, which starts a clean thread rather than tacking new
     * questions onto an old, cold answer.
     */
    public function reply(SupportTicket $ticket, User $author, string $body, bool $isPlatform): SupportTicketMessage
    {
        if ($ticket->status === 'CLOSED') {
            throw ValidationException::withMessages([
                'body' => __('platform.ticket_closed'),
            ]);
        }

        $body = trim($body);

        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => __('platform.ticket_body_required'),
            ]);
        }

        $now = Carbon::now();

        $message = SupportTicketMessage::create([
            'ticket_id' => $ticket->id,
            'company_id' => $ticket->company_id,
            'author_id' => $author->id,
            'author_is_platform' => $isPlatform,
            'body' => $body,
            'created_at' => $now,
        ]);

        $status = match (true) {
            $isPlatform && $ticket->status === 'OPEN' => 'IN_PROGRESS',
            ! $isPlatform && $ticket->status === 'RESOLVED' => 'OPEN',
            default => $ticket->status,
        };

        $ticket->forceFill(['last_message_at' => $now, 'status' => $status])->save();

        if ($isPlatform) {
            $this->tellTheCustomer($ticket, 'TICKET_REPLIED', [
                'name' => $author->name,
                'subject' => $ticket->subject,
            ]);
        } else {
            $this->platformNotifier->notify(
                'PLATFORM_TICKET_REPLIED',
                __('platform.notify_ticket_replied', ['name' => $author->name, 'subject' => $ticket->subject]),
                severity: 'INFO',
                actionUrl: route('platform.tickets.show', $ticket),
            );
        }

        return $message;
    }

    public function setStatus(SupportTicket $ticket, User $staff, string $status): void
    {
        if (! in_array($status, SupportTicket::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => __('platform.ticket_status_invalid')]);
        }

        $data = ['status' => $status];

        if ($status === 'RESOLVED') {
            $data['resolved_at'] = Carbon::now();
        }

        if ($status === 'CLOSED') {
            $data['closed_at'] = Carbon::now();
        }

        $ticket->forceFill($data)->save();

        $this->audit->event(
            'STATUS_CHANGED',
            [
                'reason' => 'TICKET_STATUS_CHANGED',
                'company_id' => $ticket->company_id,
                'ticket_id' => $ticket->id,
                'status' => $status,
            ],
            userId: $staff->id,
            label: $ticket->subject,
        );

        if ($status === 'RESOLVED') {
            $this->tellTheCustomer($ticket, 'TICKET_RESOLVED', ['subject' => $ticket->subject]);
        }
    }

    public function assign(SupportTicket $ticket, ?User $assignee): void
    {
        $ticket->forceFill(['assigned_to' => $assignee?->id])->save();
    }

    /**
     * The customer's own notification, sent to whoever can manage the
     * company's users rather than to everybody — a technician has no use for
     * "the platform replied to a ticket".
     *
     * The dispatcher writes tenant-scoped rows and reads the context for the
     * company id, so it needs a tenant set — and every caller of this method
     * is platform staff, who have none. Set for the duration and put back
     * exactly as ManageSupportAccess does for the same reason.
     *
     * @param  array<string, mixed>  $data
     */
    private function tellTheCustomer(SupportTicket $ticket, string $event, array $data): void
    {
        $recipients = $this->companyKeyholders($ticket->company_id);

        if ($recipients->isEmpty()) {
            return;
        }

        $restore = $this->context->companyIdOrNull();
        $this->context->set($ticket->company_id);

        try {
            $this->notifications->sendToMany(
                $recipients,
                $event,
                $data + ['company' => $ticket->company?->name ?? '—'],
                'INFO',
                actionUrl: route('app.support.tickets.show', $ticket),
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
    private function companyKeyholders(string $companyId)
    {
        $userIds = CompanyUser::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $companyId)
            ->where('status', 'ACTIVE')
            ->pluck('user_id');

        return User::whereIn('id', $userIds)
            ->get()
            ->filter(fn (User $user): bool => app(PermissionResolver::class)
                ->has($user, $companyId, 'admin.user.manage'))
            ->values();
    }
}
