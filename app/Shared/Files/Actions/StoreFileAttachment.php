<?php

declare(strict_types=1);

namespace App\Shared\Files\Actions;

use App\Shared\Files\Models\FileAttachment;
use App\Shared\Files\Services\FileScanner;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Stores an uploaded file privately (SRS 37).
 *
 * The mime type is taken from the file's own contents, not from the request:
 * a client-declared type is a claim, and accepting it is how a .php lands on
 * disk as an "image/jpeg".
 */
class StoreFileAttachment
{
    /** Evidence photos and vendor documents. Nothing executable. */
    public const ALLOWED_MIME_TYPES = [
        'image/jpeg', 'image/png', 'image/webp', 'image/heic',
        'application/pdf',
    ];

    public const MAX_BYTES = 10 * 1024 * 1024;

    public function __construct(
        private readonly TenantContext $context,
        private readonly FileScanner $scanner,
    ) {}

    public function handle(
        UploadedFile $file,
        string $attachableType,
        string $attachableId,
        ?string $userId = null,
    ): FileAttachment {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'file' => __('file.upload_failed'),
            ]);
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'file' => __('file.too_large', ['limit' => '10 MB']),
            ]);
        }

        // getMimeType() sniffs the contents; getClientMimeType() repeats what
        // the browser said. Only the first is evidence.
        $mime = $file->getMimeType() ?? 'application/octet-stream';

        if (! in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            throw ValidationException::withMessages([
                'file' => __('file.type_not_allowed', ['type' => $mime]),
            ]);
        }

        $companyId = $this->context->companyId();

        // Stored under the company, so a path traversal or a leaked filename
        // still cannot reach another tenant's evidence. The name is generated;
        // the original is kept as data and used only when serving.
        $path = $file->store("attachments/{$companyId}", 'local');

        if ($path === false) {
            throw ValidationException::withMessages([
                'file' => __('file.upload_failed'),
            ]);
        }

        $attachment = FileAttachment::create([
            'attachable_type' => $attachableType,
            'attachable_id' => $attachableId,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $this->safeName($file->getClientOriginalName()),
            'mime_type' => $mime,
            'size_bytes' => (int) Storage::disk('local')->size($path),
            'sha256' => hash_file('sha256', Storage::disk('local')->path($path)) ?: '',
            'uploaded_by' => $userId,
            // Not downloadable until something has looked at it (API 19.1
            // rule 3). With scanning off the scanner records SKIPPED below and
            // it becomes usable immediately.
            'scan_status' => 'PENDING',
        ]);

        // Inline rather than queued. A technician who photographs a seized
        // needle bar expects to see it on the work order a second later, and
        // an upload that becomes visible only once a worker gets to it is one
        // they will assume failed and do again. A local daemon answers in
        // milliseconds; if it cannot, the file stays PENDING and the reason is
        // in the log.
        $status = $this->scanner->scan($attachment);

        if ($status === 'INFECTED') {
            $this->scanner->quarantine($attachment);

            throw ValidationException::withMessages([
                'file' => __('file.infected'),
            ]);
        }

        return $attachment->fresh();
    }

    /**
     * The original name is echoed back in a Content-Disposition header, so it is
     * stripped of anything that could break out of it.
     */
    private function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^\p{L}\p{N}._ -]+/u', '_', $name) ?? 'file';

        return mb_substr(trim($name) === '' ? 'file' : $name, 0, 255);
    }
}
