<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Api;

use App\Modules\Platform\Models\SupportTicket;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Files\Models\FileAttachment;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A customer's own files on a ticket — a screenshot of the error, a log
 * export. Carries no permission gate, the same as the rest of this
 * controller family (Platform API §7's note — a customer asking the
 * platform something needs no admin gate). `SupportTicket` is tenant-scoped,
 * so route-model binding already refuses another company's ticket.
 */
class SupportTicketAttachmentApiController extends ApiController
{
    public function index(SupportTicket $ticket): JsonResponse
    {
        $attachments = FileAttachment::where('attachable_type', 'support_ticket')
            ->where('attachable_id', $ticket->id)
            ->latest()
            ->get();

        return ApiResponse::ok($attachments->map(fn (FileAttachment $file): array => $file->toApiSummary())->all());
    }

    public function store(Request $request, SupportTicket $ticket, StoreFileAttachment $files): JsonResponse
    {
        $request->validate(['file' => ['required', 'file']]);

        $attachment = $files->handle(
            $request->file('file'),
            'support_ticket',
            $ticket->id,
            $this->caller()->auditUserId(),
        );

        return ApiResponse::created($attachment->toApiSummary());
    }
}
