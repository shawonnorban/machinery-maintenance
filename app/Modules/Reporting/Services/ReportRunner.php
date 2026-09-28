<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Services;

use App\Modules\Reporting\Models\ReportJob;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use App\Modules\Reporting\Reports\ReportRegistry;
use App\Modules\Reporting\Writers\ReportWriter;
use App\Shared\Files\Models\FileAttachment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Runs a report and produces its file (SRS 32, ADR-032).
 *
 * The same code path serves an immediate download and a queued job, so a report
 * cannot behave differently depending on its size. Only the decision of when to
 * run it differs.
 */
class ReportRunner
{
    /**
     * Above this, a report is queued rather than answered in the request.
     *
     * Chosen against the response budget rather than a round number: the point
     * is that an HTTP request should not be holding a connection open while a
     * fleet's history is assembled (SRS 45).
     */
    public const SYNCHRONOUS_ROW_LIMIT = 2000;

    /** @var array<string, ReportWriter> */
    private array $writers = [];

    public function __construct(
        private readonly ReportRegistry $registry,
        private readonly ReportPreview $preview,
    ) {}

    public function registerWriter(ReportWriter $writer): void
    {
        $this->writers[$writer->format()] = $writer;
    }

    /**
     * @return list<string>
     */
    public function formats(): array
    {
        return array_keys($this->writers);
    }

    public function writer(string $format): ReportWriter
    {
        return $this->writers[$format]
            ?? throw new InvalidArgumentException("No writer for format [{$format}].");
    }

    public function shouldQueue(Report $report, ReportQuery $query): bool
    {
        return $report->estimatedRows($query) > self::SYNCHRONOUS_ROW_LIMIT;
    }

    /**
     * Produce the file for a job and record the outcome on it.
     *
     * Every failure is written to the row. A queue worker's log is not somewhere
     * the person who asked for the report can look.
     */
    public function fulfil(ReportJob $job): ReportJob
    {
        $job->update(['status' => 'RUNNING', 'started_at' => CarbonImmutable::now()]);

        try {
            $report = $this->registry->find($job->report_type);
            $query = ReportQuery::fromArray($job->parameters_json ?? []);
            $writer = $this->writer($job->format);

            $temporary = $this->temporaryPath($writer->extension());

            $rows = $writer->write($temporary, $report, $query, $this->preview->metaFor($report, $query));

            $file = $this->store($job, $report, $writer, $temporary);

            $job->update([
                'status' => 'COMPLETED',
                'file_id' => $file->id,
                'row_count' => $rows,
                'completed_at' => CarbonImmutable::now(),
            ]);
        } catch (Throwable $e) {
            $job->update([
                'status' => 'FAILED',
                // The message, not the stack trace: the row is shown to a user,
                // and a trace tells them nothing while telling an attacker a
                // little too much.
                'error_message' => Str::limit($e->getMessage(), 500),
                'completed_at' => CarbonImmutable::now(),
            ]);

            throw $e;
        }

        return $job->fresh();
    }

    public function temporaryPath(string $extension): string
    {
        $directory = storage_path('app/tmp');

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        return $directory.'/report-'.Str::ulid()->toString().'.'.$extension;
    }

    /**
     * File name a person can recognise a week later: what it is, and when.
     */
    public function fileName(Report $report, ReportWriter $writer): string
    {
        return Str::slug($report->key()).'-'.CarbonImmutable::now()->format('Ymd-His').'.'.$writer->extension();
    }

    private function store(ReportJob $job, Report $report, ReportWriter $writer, string $temporary): FileAttachment
    {
        $contents = file_get_contents($temporary);

        if ($contents === false) {
            throw new InvalidArgumentException('Report file could not be read back after writing.');
        }

        // Under the company, like every other stored file: a generated report
        // holds the same data as the screens it came from, and it gets the same
        // isolation.
        $path = "reports/{$job->company_id}/".Str::ulid()->toString().'.'.$writer->extension();

        Storage::disk('local')->put($path, $contents);

        $file = FileAttachment::create([
            'company_id' => $job->company_id,
            'attachable_type' => 'report_job',
            'attachable_id' => $job->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $this->fileName($report, $writer),
            'mime_type' => $writer->mimeType(),
            'size_bytes' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'uploaded_by' => $job->user_id,
        ]);

        @unlink($temporary);

        return $file;
    }
}
