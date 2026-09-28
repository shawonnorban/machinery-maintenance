<?php

declare(strict_types=1);

namespace App\Modules\Platform\Actions;

use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Platform\Models\SupportTicket;
use App\Shared\Files\Models\FileAttachment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Only ever refuses over a ticket somebody has already engaged with — the
 * same "orphan check" `DeleteAsset` runs before its own hard delete, just
 * phrased in terms of replies rather than foreign-key history: a second
 * message (from either side) means somebody already responded to what's
 * here, and deleting it would pull the floor out from under that reply
 * rather than tidy up a mistake.
 */
class DeleteSupportTicket
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function handle(SupportTicket $ticket, string $deletedBy): void
    {
        if ($ticket->status !== 'OPEN' || $ticket->messages()->count() > 1) {
            throw ValidationException::withMessages(['ticket' => __('platform.ticket_no_longer_deletable')]);
        }

        $attachments = FileAttachment::where('attachable_type', 'support_ticket')
            ->where('attachable_id', $ticket->id)
            ->get();

        foreach ($attachments as $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
            $attachment->delete();
        }

        $this->audit->event(
            'DELETED',
            ['reason' => 'TICKET_DELETED', 'company_id' => $ticket->company_id, 'ticket_id' => $ticket->id],
            userId: $deletedBy,
            label: $ticket->subject,
        );

        $ticket->messages()->delete();
        $ticket->delete();
    }
}
