<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Reporting\Imports\Importer;
use App\Modules\Reporting\Models\ExportJob;
use App\Modules\Reporting\Reports\ReportQuery;
use App\Shared\Files\Models\FileAttachment;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Raw data out, in the shape it comes back in (SRS 33, ERD Section 21).
 *
 * Different from a report on purpose. A report is a view of the data shaped for
 * reading; this is the data shaped so it can be re-imported — the same columns
 * the importer accepts, so a person can pull the asset register out, fix two
 * hundred rows in a spreadsheet, and upload it again.
 *
 * Exporting requires both the export right and the right to create what is
 * being exported. The file is a complete copy of a company's register, and the
 * fact that it is read-only on the way out does not make it less sensitive on
 * the way to somebody's laptop (SRS 33).
 */
class DataExporter
{
    /** Same retention as a generated report (SRS 35). */
    public const RETENTION_DAYS = 7;

    public function __construct(
        private readonly ReportRunner $runner,
        private readonly TenantContext $context,
    ) {}

    public function handle(Importer $importer, string $format, User $user): ExportJob
    {
        if (! $importer->supportsExport()) {
            throw new \InvalidArgumentException("[{$importer->type()}] cannot be exported.");
        }

        if (! $user->can('export.job.create') || ! $user->can($importer->permission())) {
            throw new AuthorizationException(__('import.not_permitted'), Response::HTTP_FORBIDDEN);
        }

        $writer = $this->runner->writer($format);

        $job = ExportJob::create([
            'company_id' => $this->context->companyId(),
            'requested_by' => $user->id,
            'type' => $importer->type(),
            'format' => $format,
            'status' => 'QUEUED',
            'expires_at' => CarbonImmutable::now()->addDays(self::RETENTION_DAYS),
        ]);

        try {
            $path = $this->runner->temporaryPath($writer->extension());

            $rows = $writer->write($path, new ImporterReportAdapter($importer), $this->emptyQuery());

            $file = $this->store($job, $writer->extension(), $writer->mimeType(), $path, $importer);

            $job->update([
                'status' => 'COMPLETED',
                'file_id' => $file->id,
                'row_count' => $rows,
                'completed_at' => CarbonImmutable::now(),
            ]);
        } catch (Throwable $e) {
            $job->update([
                'status' => 'FAILED',
                'error_message' => Str::limit($e->getMessage(), 500),
                'completed_at' => CarbonImmutable::now(),
            ]);

            throw $e;
        }

        return $job->fresh();
    }

    private function emptyQuery(): ReportQuery
    {
        // A raw export has no period: it is what exists now, which is the only
        // thing that can be imported back.
        return new ReportQuery(
            CarbonImmutable::now()->subCentury(),
            CarbonImmutable::now(),
        );
    }

    private function store(
        ExportJob $job,
        string $extension,
        string $mime,
        string $temporary,
        Importer $importer,
    ): FileAttachment {
        $contents = file_get_contents($temporary);

        if ($contents === false) {
            throw new \RuntimeException('The export file could not be read back after writing.');
        }

        $path = "exports/{$job->company_id}/".Str::ulid()->toString().'.'.$extension;

        Storage::disk('local')->put($path, $contents);

        $file = FileAttachment::create([
            'company_id' => $job->company_id,
            'attachable_type' => 'export_job',
            'attachable_id' => $job->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $importer->type().'-'.CarbonImmutable::now()->format('Ymd-His').'.'.$extension,
            'mime_type' => $mime,
            'size_bytes' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'uploaded_by' => $job->requested_by,
        ]);

        @unlink($temporary);

        return $file;
    }
}
