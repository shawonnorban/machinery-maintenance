<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Services;

use App\Modules\Reporting\Imports\ImportColumn;
use App\Modules\Reporting\Imports\ImportContext;
use App\Modules\Reporting\Imports\Importer;
use App\Modules\Reporting\Imports\ImporterRegistry;
use App\Modules\Reporting\Imports\ImportReader;
use App\Modules\Reporting\Imports\PreparedRow;
use App\Modules\Reporting\Imports\RowContext;
use App\Modules\Reporting\Models\ImportError;
use App\Modules\Reporting\Models\ImportJob;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Validates a file, then imports it (SRS 33, ADR-031).
 *
 * Two passes over the same file, deliberately. The first writes nothing and
 * reports everything wrong with the file; the second writes only the rows that
 * passed. A person uploading three thousand machines gets to see what would
 * happen before it happens, which is the whole difference between an import
 * they trust and one they try once.
 *
 * Invalid rows are skipped rather than blocking the file. A spreadsheet
 * maintained by hand for six years will have four bad rows in it, and refusing
 * the whole upload over them means the good 2,996 never arrive. What matters is
 * that the four are reported precisely enough to fix, and counted where nobody
 * can miss them.
 */
class ImportProcessor
{
    /** Errors kept per job. Beyond this the file needs fixing, not reading. */
    public const MAX_STORED_ERRORS = 500;

    /** Rows shown in the preview before confirming. */
    public const PREVIEW_ROWS = 20;

    public function __construct(
        private readonly ImporterRegistry $registry,
        private readonly ImportReader $reader,
    ) {}

    /**
     * First pass: read everything, write nothing.
     */
    public function validate(ImportJob $job): ImportJob
    {
        $job->update(['status' => 'VALIDATING']);

        $importer = $this->registry->find($job->type);

        $job->errors()->delete();

        try {
            $missing = $this->missingColumns($importer, $job);

            if ($missing !== []) {
                // A missing required column is a problem with the file, not
                // with a row, so it is reported once rather than three thousand
                // times.
                $this->recordError($job, 1, null, __('import.errors.missing_columns', [
                    'columns' => implode(', ', $missing),
                ]), null);

                $job->update([
                    'status' => 'VALIDATED',
                    'total_rows' => 0,
                    'valid_rows' => 0,
                    'failed_rows' => 0,
                    'validated_at' => CarbonImmutable::now(),
                ]);

                return $job->fresh();
            }

            $total = 0;
            $valid = 0;
            $failed = 0;
            $stored = 0;
            $seen = [];

            foreach ($this->rows($job) as $rowNumber => $row) {
                $total++;

                $prepared = $importer->prepare($row, new RowContext($rowNumber));

                if ($prepared->isValid()) {
                    $duplicate = $this->duplicateKey($importer, $prepared, $seen);

                    if ($duplicate !== null) {
                        // Two rows claiming the same code is a mistake in the
                        // file: importing both means the second silently
                        // overwrites the first, and nobody finds out which won.
                        $failed++;
                        $stored += $this->recordErrors($job, $rowNumber, [[
                            'field' => $duplicate,
                            'error' => __('import.errors.duplicate_in_file'),
                            'value' => (string) $prepared->values[$duplicate],
                        ]], $stored);

                        continue;
                    }

                    $valid++;

                    continue;
                }

                $failed++;
                $stored += $this->recordErrors($job, $rowNumber, $prepared->errors, $stored);
            }

            $job->update([
                'status' => 'VALIDATED',
                'total_rows' => $total,
                'valid_rows' => $valid,
                'failed_rows' => $failed,
                'validated_at' => CarbonImmutable::now(),
            ]);
        } catch (Throwable $e) {
            $job->update([
                'status' => 'FAILED',
                'error_message' => mb_substr($e->getMessage(), 0, 500),
                'completed_at' => CarbonImmutable::now(),
            ]);

            throw $e;
        }

        return $job->fresh();
    }

    /**
     * Second pass: write the rows that passed.
     */
    public function import(ImportJob $job): ImportJob
    {
        $job->update(['status' => 'IMPORTING', 'started_at' => CarbonImmutable::now()]);

        $importer = $this->registry->find($job->type);
        $context = new ImportContext($job->id, $job->user_id);

        $created = 0;
        $updated = 0;
        $failed = 0;
        $stored = $job->errors()->count();
        $seen = [];

        try {
            foreach ($this->rows($job) as $rowNumber => $row) {
                $prepared = $importer->prepare($row, new RowContext($rowNumber));

                if (! $prepared->isValid()) {
                    // Already reported in the validation pass, and already
                    // counted in failed_rows.
                    continue;
                }

                if ($this->duplicateKey($importer, $prepared, $seen) !== null) {
                    // Skipped here as well as in validation, and for the same
                    // reason: writing both copies means the second silently
                    // overwrites the first. Checking only during validation
                    // would let the confirm write a row the preview said it
                    // would not.
                    continue;
                }

                $outcome = $importer->write($prepared, $context);

                if ($outcome->isFailure()) {
                    // A row that validated and then failed to write is the case
                    // worth being loud about: it means a rule the preview did
                    // not know to check.
                    $failed++;
                    $stored += $this->recordErrors($job, $rowNumber, [[
                        'field' => null,
                        'error' => $outcome->error ?? __('import.errors.write_failed'),
                        'value' => null,
                    ]], $stored);

                    continue;
                }

                $outcome->result === 'UPDATED' ? $updated++ : $created++;
            }

            $job->update([
                'status' => 'COMPLETED',
                'success_rows' => $created,
                'updated_rows' => $updated,
                'failed_rows' => $job->failed_rows + $failed,
                'completed_at' => CarbonImmutable::now(),
            ]);
        } catch (Throwable $e) {
            $job->update([
                'status' => 'FAILED',
                'error_message' => mb_substr($e->getMessage(), 0, 500),
                'success_rows' => $created,
                'updated_rows' => $updated,
                'completed_at' => CarbonImmutable::now(),
            ]);

            throw $e;
        }

        return $job->fresh();
    }

    /**
     * The first rows, prepared, for the confirmation screen.
     *
     * @return array{rows: list<PreparedRow>, columns: array<string, ImportColumn>}
     */
    public function preview(ImportJob $job, int $limit = self::PREVIEW_ROWS): array
    {
        $importer = $this->registry->find($job->type);
        $rows = [];

        foreach ($this->rows($job) as $rowNumber => $row) {
            $rows[] = $importer->prepare($row, new RowContext($rowNumber));

            if (count($rows) >= $limit) {
                break;
            }
        }

        return ['rows' => $rows, 'columns' => $importer->columns()];
    }

    /**
     * File rows, with every declared column present.
     *
     * A file that leaves out an optional column is a normal file, so the
     * missing keys are filled with null here rather than leaving every importer
     * to guard each lookup. One place to forget it is better than forty.
     *
     * @return \Generator<int, array<string, string|null>>
     */
    private function rows(ImportJob $job): \Generator
    {
        $file = $job->file;

        if ($file === null) {
            throw new \RuntimeException('The uploaded file is no longer available.');
        }

        $blank = array_fill_keys(array_keys($this->registry->find($job->type)->columns()), null);

        $rows = $this->reader->rows(
            Storage::disk($file->disk)->path($file->path),
            pathinfo($file->original_name, PATHINFO_EXTENSION),
        );

        foreach ($rows as $rowNumber => $row) {
            yield $rowNumber => [...$blank, ...$row];
        }
    }

    /**
     * @return list<string>
     */
    private function missingColumns(Importer $importer, ImportJob $job): array
    {
        $file = $job->file;

        $headers = $this->reader->headers(
            Storage::disk($file->disk)->path($file->path),
            pathinfo($file->original_name, PATHINFO_EXTENSION),
        );

        $missing = [];

        foreach ($importer->columns() as $name => $column) {
            if ($column->required && ! in_array($name, $headers, true)) {
                $missing[] = $name;
            }
        }

        return $missing;
    }

    /**
     * The first natural-key column a row repeats, if any.
     *
     * @param  array<string, bool>  $seen
     */
    private function duplicateKey(Importer $importer, PreparedRow $row, array &$seen): ?string
    {
        foreach (['asset_code', 'code', 'part_number'] as $key) {
            if (! array_key_exists($key, $row->values)) {
                continue;
            }

            $value = (string) $row->values[$key];

            if (isset($seen["{$key}:{$value}"])) {
                return $key;
            }

            $seen["{$key}:{$value}"] = true;

            return null;
        }

        return null;
    }

    /**
     * @param  list<array{field: string|null, error: string, value: string|null}>  $errors
     */
    private function recordErrors(ImportJob $job, int $rowNumber, array $errors, int $alreadyStored): int
    {
        $written = 0;

        foreach ($errors as $error) {
            if ($alreadyStored + $written >= self::MAX_STORED_ERRORS) {
                break;
            }

            $this->recordError($job, $rowNumber, $error['field'], $error['error'], $error['value'] ?? null);
            $written++;
        }

        return $written;
    }

    private function recordError(ImportJob $job, int $rowNumber, ?string $field, string $message, ?string $value): void
    {
        ImportError::create([
            'company_id' => $job->company_id,
            'import_job_id' => $job->id,
            'row_number' => $rowNumber,
            'field' => $field,
            'error' => $message,
            'value' => $value === null ? null : mb_substr($value, 0, 255),
            'created_at' => CarbonImmutable::now(),
        ]);
    }
}
