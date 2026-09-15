<?php

declare(strict_types=1);

namespace App\Shared\Files\Http\Controllers\Api;

use App\Shared\Files\Models\FileAttachment;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Scopes\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A file, once it already belongs to something (API 19.1).
 *
 * Uploading is done through the resource that owns the file — `POST
 * /assets/{asset}/documents`, `POST /work-orders/{workOrder}/attachments` —
 * because what a file may be attached to, and who may attach one, differs by
 * owner. Everything after that (reading metadata, downloading, deleting) is
 * the same operation regardless of what the file is evidence for, which is
 * what lives here.
 *
 * Two endpoints from the v1.0 spec are deliberately not built:
 *
 * `POST /files/presign` (a direct-to-storage upload URL) has nothing to
 * presign against — this deployment writes to local disk, not an
 * S3-compatible bucket, and a presigned URL for a filesystem that isn't
 * there would be a lie the client acts on.
 *
 * `GET/POST /files/{file}/versions` has no data model to serve — a
 * `FileAttachment` row has no previous-version column, and inventing one to
 * answer an endpoint nothing populates would be a version history that is
 * always empty. Both wait for the storage workstream ADR-066 deferred.
 */
class FileApiController extends ApiController
{
    public function show(FileAttachment $file): JsonResponse
    {
        $this->authorizeView($file);

        return ApiResponse::ok($file->toApiSummary());
    }

    /**
     * `302` to a short-lived signed URL (API 19.1), not the bytes directly.
     *
     * A bearer token proves who is asking; a signed URL proves what they were
     * allowed to fetch, for five minutes, without a token at all — the only
     * way to hand a photo to something that cannot send an Authorization
     * header, like a browser tab or an `<img>` tag.
     */
    public function download(FileAttachment $file): RedirectResponse
    {
        $this->authorizeView($file);
        $this->assertDownloadable($file);

        return redirect()->away(
            URL::temporarySignedRoute('api.v1.files.signed', now()->addMinutes(5), ['file' => $file->id]),
        );
    }

    /**
     * The actual bytes, reached only through a signature `download()` minted
     * moments earlier. No bearer token and no permission check here — the
     * signature already proves a caller who passed both was handed this exact
     * file id, and there is no caller identity on this request to check a
     * permission against even if one were wanted.
     */
    public function signed(string $file): StreamedResponse
    {
        $attachment = FileAttachment::withoutGlobalScope(TenantScope::class)->findOrFail($file);

        $this->assertDownloadable($attachment);

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->response(
            $attachment->path,
            $attachment->original_name,
            [
                'Content-Type' => $attachment->mime_type,
                'Content-Disposition' => sprintf(
                    '%s; filename="%s"',
                    $attachment->isImage() ? 'inline' : 'attachment',
                    $attachment->original_name,
                ),
                'Cache-Control' => 'private, max-age=0, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function destroy(FileAttachment $file): JsonResponse
    {
        $this->authorizeView($file);
        $this->authorizeManage($file);

        // A checklist result that still names this file is a completed
        // safety record with evidence attached to it. Nothing else in this
        // schema keeps a foreign key to a file, so this is the one reference
        // worth refusing over rather than a general-purpose lookup.
        if ($file->attachable_type === 'work_order'
            && DB::table('work_order_checklist_results')->where('file_id', $file->id)->exists()) {
            throw ApiException::of(ErrorCode::DEPENDENT_RECORDS_EXIST, __('file.referenced_by_checklist'));
        }

        Storage::disk($file->disk)->delete($file->path);
        $file->delete();

        return ApiResponse::noContent();
    }

    private function assertDownloadable(FileAttachment $file): void
    {
        if (! $file->isDownloadable()) {
            // 409, not 404 (API 19.1 rule 3): the file exists and the caller
            // may see it, it simply is not usable yet.
            throw ApiException::of(
                ErrorCode::FILE_SCAN_PENDING,
                $file->scan_status === 'INFECTED' ? __('file.infected') : __('file.scan_pending'),
            );
        }
    }

    /**
     * Which permission answers for a file depends on what it is attached to
     * (FileAttachmentController mirrors this for the web download route): a
     * machine's manual is not evidence from somebody's work order, and asking
     * for the work order permission to read one would deny it to the people
     * who need it.
     */
    private function authorizeView(FileAttachment $file): void
    {
        $this->allow(match ($file->attachable_type) {
            'asset' => 'asset.asset.view',
            default => 'work_order.work_order.view',
        });
    }

    private function authorizeManage(FileAttachment $file): void
    {
        $this->allow(match ($file->attachable_type) {
            'asset' => 'asset.document.manage',
            default => 'work_order.work_order.update',
        });
    }
}
