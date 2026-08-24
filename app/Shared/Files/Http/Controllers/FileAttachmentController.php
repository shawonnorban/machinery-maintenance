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
        // reading something they have no business seeing — and which permission
        // that is depends on what the file is attached to. A machine's manual
        // is not evidence from somebody's work order, and asking for the work
        // order permission to read one would deny it to the people who need it.
        $this->authorize(match ($attachment->attachable_type) {
            'asset' => 'asset.asset.view',
            default => 'work_order.work_order.view',
        });

        // 409, not 404 (API 19.1 rule 3). The file exists and the caller may
        // see it; it is simply not usable yet, and "come back in a moment" is a
        // different answer from "no such file".
        //
        // `abort` rather than an ApiException, because this is a web route: the
        // JSON envelope is only rendered for api/* and this would otherwise
        // become a 500. The API's own file endpoints, when they exist, throw
        // FILE_SCAN_PENDING instead — the code is already in the enum.
        if (! $attachment->isDownloadable()) {
            abort(409, $attachment->scan_status === 'INFECTED'
                ? __('file.infected')
                : __('file.scan_pending'));
        }

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
