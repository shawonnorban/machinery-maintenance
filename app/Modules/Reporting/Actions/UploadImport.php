<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Reporting\Imports\Importer;
use App\Modules\Reporting\Models\ImportJob;
use App\Shared\Files\Models\FileAttachment;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Takes the uploaded file and records the attempt (SRS 33, SRS 37).
 *
 * The file is stored before anything is read from it, so an import that fails
 * halfway through validation can be looked at afterwards rather than being
 * something the person has to describe from memory.
 *
 * Extension and content type are both checked, and the file is stored under the
 * company with a generated name. An import file is untrusted input that a
 * person chose from their own machine, and the one place it must never be able
 * to reach is a path where something might execute it.
 */
class UploadImport
{
    public const ALLOWED_EXTENSIONS = ['csv', 'xlsx'];

    public const ALLOWED_MIME_TYPES = [
        'text/csv',
        'text/plain',
        'application/csv',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
    ];

    /** Twenty thousand rows of assets is comfortably inside this. */
    public const MAX_BYTES = 20 * 1024 * 1024;

    public function __construct(private readonly TenantContext $context) {}

    public function handle(Importer $importer, UploadedFile $file, User $user): ImportJob
    {
        if (! $importer->allows($user)) {
            throw new AuthorizationException(__('import.not_permitted'), Response::HTTP_FORBIDDEN);
        }

        if (! $file->isValid()) {
            throw ValidationException::withMessages(['file' => __('import.upload_failed')]);
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'file' => __('import.too_large', ['limit' => '20 MB']),
            ]);
        }

        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'file' => __('import.unsupported_file', ['extension' => $extension]),
            ]);
        }

        // Sniffed from the contents, not taken from the request. A client
        // declared type is a claim; only the first is evidence.
        $mime = $file->getMimeType() ?? 'application/octet-stream';

        if (! in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            throw ValidationException::withMessages([
                'file' => __('import.type_not_allowed', ['type' => $mime]),
            ]);
        }

        $companyId = $this->context->companyId();

        $path = $file->store("imports/{$companyId}", 'local');

        if ($path === false) {
            throw ValidationException::withMessages(['file' => __('import.upload_failed')]);
        }

        $contents = Storage::disk('local')->get($path);

        $attachment = FileAttachment::create([
            'company_id' => $companyId,
            'attachable_type' => 'import_job',
            // Set below once the job exists; the file has to be stored first so
            // a failed job still has something to look at.
            'attachable_id' => Str::ulid()->toString(),
            'disk' => 'local',
            'path' => $path,
            'original_name' => $this->safeName($file->getClientOriginalName(), $extension),
            'mime_type' => $mime,
            'size_bytes' => (int) Storage::disk('local')->size($path),
            'sha256' => hash('sha256', (string) $contents),
            'uploaded_by' => $user->id,
        ]);

        $job = ImportJob::create([
            'company_id' => $companyId,
            'user_id' => $user->id,
            'type' => $importer->type(),
            'file_id' => $attachment->id,
            'original_name' => $attachment->original_name,
            'status' => 'UPLOADED',
        ]);

        $attachment->update(['attachable_id' => $job->id]);

        return $job;
    }

    /**
     * The name is data, never a path. It is shown back to the person and used
     * only to work out how to parse the file.
     */
    private function safeName(string $name, string $extension): string
    {
        $base = Str::of(pathinfo($name, PATHINFO_FILENAME))
            ->replaceMatches('/[^\p{L}\p{N}\-_. ]/u', '')
            ->limit(120, '')
            ->trim()
            ->value();

        return ($base === '' ? 'import' : $base).'.'.$extension;
    }
}
