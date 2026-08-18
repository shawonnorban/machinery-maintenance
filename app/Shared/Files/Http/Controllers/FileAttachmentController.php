<?php

declare(strict_types=1);

namespace App\Shared\Files\Http\Controllers;

use App\Shared\Files\Models\FileAttachment;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a private attachment through the application (SRS 37).
 *
 * Never a public disk link. The file is evidence attached to a tenant's work,
 * and a public URL is a permanent unauthenticated grant to anyone who has ever
 * seen it. Signed URLs come with the full storage workstream; the authorisation
 * this performs is the part that cannot wait.
 */
class FileAttachmentController extends Controller
{
    public function show(FileAttachment $attachment): StreamedResponse
    {
        // The tenant scope on the model already refuses another company's file.
        // The permission check is what stops a member of the right company from
        // reading work they have no business seeing.
        $this->authorize('work_order.work_order.view');

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->response(
            $attachment->path,
            $attachment->original_name,
            [
                'Content-Type' => $attachment->mime_type,
                // inline for a photo the technician wants to look at; the
                // filename is the sanitised original.
                'Content-Disposition' => sprintf(
                    '%s; filename="%s"',
                    $attachment->isImage() ? 'inline' : 'attachment',
                    $attachment->original_name,
                ),
                // Evidence should not sit in a shared cache.
                'Cache-Control' => 'private, max-age=0, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
