<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Api;

use App\Modules\Platform\Models\SupportTicket;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Files\Models\FileAttachment;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Platform staff's own files on a ticket — a fix confirmation, a screenshot
 * sent back to the customer. Gated only by `platform.auth` (the route
 * group), same as the rest of the platform ticket API.
 */
class PlatformTicketAttachmentApiController extends ApiController
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(string $ticket): JsonResponse
    {
        $attachments = FileAttachment::where('attachable_type', 'support_ticket')
            ->where('attachable_id', $this->ticket($ticket)->id)
            ->latest()
            ->get();

        return ApiResponse::ok($attachments->map(fn (FileAttachment $file): array => $file->toApiSummary())->all());
    }

    /**
     * `StoreFileAttachment` resolves the storage path from the current
     * tenant context, and a platform request carries none — set the
     * ticket's own company for the duration and put back exactly what
     * `ManageSupportTicket::tellTheCustomer()` already does for the same
     * reason, so the file lands under the customer's own company, not "no
     * company" or whichever tenant happened to be set beforehand.
     */
    public function store(Request $request, string $ticket, StoreFileAttachment $files): JsonResponse
    {
        $request->validate(['file' => ['required', 'file']]);

        $target = $this->ticket($ticket);
        // `request()->user()`, not `$this->caller()`: `ApiCaller` only
        // knows how to resolve a tenant token, and this route runs under
        // `platform.auth` (a Sanctum token through a different guard
        // entirely) — `request()->user()` is what every other method on
        // the platform ticket controllers already reads the acting user
        // from, for exactly this reason.
        $uploaderId = $request->user()?->id;

        $restore = $this->context->companyIdOrNull();
        $this->context->set($target->company_id);

        try {
            $attachment = $files->handle($request->file('file'), 'support_ticket', $target->id, $uploaderId);
        } finally {
            $this->context->forget();

            if ($restore !== null) {
                $this->context->set($restore);
            }
        }

        return ApiResponse::created($attachment->toApiSummary());
    }

    private function ticket(string $id): SupportTicket
    {
        return SupportTicket::withoutGlobalScope(TenantScope::class)->findOrFail($id);
    }
}
