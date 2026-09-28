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
    /**
     * Minutes to first response / to resolution, by priority. A reasonable
     * default desk standard, not a business-negotiated figure — adjustable
     * later without touching anything but this map.
     *
     * @var array<string, array{0: int, 1: int}>
     */
    private const SLA_MINUTES = [
        'URGENT' => [60, 240],
        'HIGH' => [240, 1440],
        'MEDIUM' => [1440, 4320],
        'LOW' => [2880, 7200],
    ];

    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly NotificationDispatcher $notifications,
        private readonly PlatformNotifier $platformNotifier,
        private readonly TenantContext $context,
    ) {}

    /**
     * `$priority` is the customer's own suggestion, not the final word —
     * platform staff make the real triage call via `reclassify()`, which can
     * (and often will) change it. An invalid or omitted value quietly falls
     * back to MEDIUM rather than failing the whole ticket over it.
     *
     * Category is never a customer choice at all — it always opens GENERAL,
     * because unlike priority it has no meaning as a customer's own
     * note-to-self about urgency.
     */
    public function open(Company $company, User $opener, string $subject, string $body, ?string $priority = null): SupportTicket
    {
        $subject = trim($subject);
        $body = trim($body);

        if ($subject === '' || $body === '') {
            throw ValidationException::withMessages([
                'body' => __('platform.ticket_body_required'),
            ]);
        }

        $priority = in_array($priority, SupportTicket::PRIORITIES, true) ? $priority : 'MEDIUM';

        $now = Carbon::now();
        [$firstResponseMinutes, $resolutionMinutes] = self::SLA_MINUTES[$priority];

        $ticket = SupportTicket::create([
            'company_id' => $company->id,
            'opened_by' => $opener->id,
            'subject' => $subject,
            'status' => 'OPEN',
            'priority' => $priority,
            'category' => 'GENERAL',
            'last_message_at' => $now,
            'first_response_due_at' => $now->clone()->addMinutes($firstResponseMinutes),
            'resolution_due_at' => $now->clone()->addMinutes($resolutionMinutes),
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
            // The platform console's own ticket thread is the Next.js app
            // now (Phase D/F, ADR-066's Platform console cutover) — not the
            // decommissioned Blade `platform.tickets.show` route.
            config('tenancy.frontend_url').'/platform/tickets/'.$ticket->id,
        );

        return $ticket;
    }

    /**
     * A quick correction, not a rewrite of history — allowed only while the
     * ticket is still exactly what the opener typed: OPEN, and with no
     * reply from anyone yet, platform or the opener's own follow-up.
     * Ownership itself is the controller's concern (a 403, not a domain
     * rule about the ticket's own state); this only ever refuses over the
     * state.
     */
    public function update(SupportTicket $ticket, User $editor, string $subject, string $body): void
    {
        if ($ticket->status !== 'OPEN' || $ticket->messages()->count() > 1) {
            throw ValidationException::withMessages(['subject' => __('platform.ticket_no_longer_editable')]);
        }

        $subject = trim($subject);
        $body = trim($body);

        if ($subject === '' || $body === '') {
            throw ValidationException::withMessages([
                'body' => __('platform.ticket_body_required'),
            ]);
        }

        $ticket->forceFill(['subject' => $subject])->save();

        $ticket->messages()->orderBy('created_at')->first()?->forceFill(['body' => $body])->save();

        $this->audit->event(
            'UPDATED',
            ['reason' => 'TICKET_EDITED', 'company_id' => $ticket->company_id, 'ticket_id' => $ticket->id],
            userId: $editor->id,
            label: $subject,
        );
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
    /**
     * An internal note (`$isInternal`, platform-only) is a side channel: it
     * never reaches the customer, never counts as the response the SLA is
     * timing, and never moves the ticket's own status. Only a real reply
     * does any of that.
     */
    public function reply(
        SupportTicket $ticket,
        User $author,
        string $body,
        bool $isPlatform,
        bool $isInternal = false,
    ): SupportTicketMessage {
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

        // A customer has no concept of an internal note — enforced here,
        // not just left to the caller, so a request-layer mistake can never
        // put a customer's own words in the platform-only channel.
        $isInternal = $isInternal && $isPlatform;

        $now = Carbon::now();

        $message = SupportTicketMessage::create([
            'ticket_id' => $ticket->id,
            'company_id' => $ticket->company_id,
            'author_id' => $author->id,
            'author_is_platform' => $isPlatform,
            'is_internal' => $isInternal,
            'body' => $body,
            'created_at' => $now,
        ]);

        if ($isInternal) {
            return $message;
        }

        $status = match (true) {
            $isPlatform && $ticket->status === 'OPEN' => 'IN_PROGRESS',
            ! $isPlatform && $ticket->status === 'RESOLVED' => 'OPEN',
            default => $ticket->status,
        };

        $data = ['last_message_at' => $now, 'status' => $status];

        if ($isPlatform && $ticket->first_responded_at === null) {
            $data['first_responded_at'] = $now;
        }

        $ticket->forceFill($data)->save();

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
                // Same as `open()` above — the Next.js platform console, not
                // the decommissioned Blade route.
                actionUrl: config('tenancy.frontend_url').'/platform/tickets/'.$ticket->id,
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
     * Platform staff's own triage decision, made after reading the ticket —
     * `open()` never accepts these from the customer (SRS: priority is a
     * platform call). Recomputes each SLA due-date only if its own deadline
     * has not already been met — reclassifying a ticket the customer already
     * got a first response on must not un-meet that.
     */
    public function reclassify(SupportTicket $ticket, User $staff, string $priority, string $category): void
    {
        if (! in_array($priority, SupportTicket::PRIORITIES, true)) {
            throw ValidationException::withMessages(['priority' => __('platform.ticket_priority_invalid')]);
        }

        if (! in_array($category, SupportTicket::CATEGORIES, true)) {
            throw ValidationException::withMessages(['category' => __('platform.ticket_category_invalid')]);
        }

        [$firstResponseMinutes, $resolutionMinutes] = self::SLA_MINUTES[$priority];
        $now = Carbon::now();

        $data = ['priority' => $priority, 'category' => $category];

        if ($ticket->first_responded_at === null) {
            $data['first_response_due_at'] = $now->clone()->addMinutes($firstResponseMinutes);
            // A reclassification is itself a fresh judgment of urgency, so
            // its own not-yet-notified breach flag resets with it.
            $data['first_response_breach_notified_at'] = null;
        }

        if ($ticket->resolved_at === null) {
            $data['resolution_due_at'] = $now->clone()->addMinutes($resolutionMinutes);
            $data['resolution_breach_notified_at'] = null;
        }

        $ticket->forceFill($data)->save();

        $this->audit->event(
            'UPDATED',
            [
                'reason' => 'TICKET_RECLASSIFIED',
                'company_id' => $ticket->company_id,
                'ticket_id' => $ticket->id,
                'priority' => $priority,
                'category' => $category,
            ],
            userId: $staff->id,
            label: $ticket->subject,
        );
    }

    /**
     * Told once per missed deadline — the caller (`CheckTicketSla`) only
     * calls this the first time it sees a given breach, but the dedup
     * columns are stamped here too so a second, mistaken call is still safe.
     */
    public function notifySlaBreach(SupportTicket $ticket, string $type): void
    {
        $column = match ($type) {
            'first_response' => 'first_response_breach_notified_at',
            'resolution' => 'resolution_breach_notified_at',
            default => throw new \InvalidArgumentException("Unknown SLA breach type [{$type}]."),
        };

        if ($ticket->{$column} !== null) {
            return;
        }

        $ticket->forceFill([$column => Carbon::now()])->save();

        $this->platformNotifier->notify(
            'PLATFORM_TICKET_SLA_BREACHED',
            __('platform.notify_ticket_sla_breached', [
                'subject' => $ticket->subject,
                'company' => $ticket->company?->name ?? '—',
            ]),
            severity: 'WARNING',
            actionUrl: config('tenancy.frontend_url').'/platform/tickets/'.$ticket->id,
        );
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
                // The customer's own ticket view is the Next.js app now
                // (Phase D/F), not this module's own (decommissioned) `/app`
                // routes.
                actionUrl: config('tenancy.frontend_url').'/support/tickets/'.$ticket->id,
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
