<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Controllers\Api;

use App\Modules\Identity\Models\User;
use App\Modules\Reporting\Actions\UploadImport;
use App\Modules\Reporting\Imports\Importer;
use App\Modules\Reporting\Imports\ImporterRegistry;
use App\Modules\Reporting\Imports\PreparedRow;
use App\Modules\Reporting\Jobs\RunImportJob;
use App\Modules\Reporting\Models\ImportError;
use App\Modules\Reporting\Models\ImportJob;
use App\Modules\Reporting\Services\ImportProcessor;
use App\Modules\Reporting\Services\ImportTemplate;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Upload, validate, preview, confirm, over the wire (API 23; mirrors the
 * web `ImportController`, which this delegates to unchanged per ADR-003).
 *
 * The route segment is hyphenated (`spare-parts`, `maintenance-history`) to
 * match this API's own convention; `ImporterRegistry`'s keys underneath are
 * unchanged (`spare_parts`, `maintenance_history`) since they are also the
 * stored `import_jobs.type` value.
 */
class ImportApiController extends ApiController
{
    public function __construct(
        private readonly ImporterRegistry $registry,
        private readonly UploadImport $upload,
        private readonly ImportProcessor $processor,
    ) {}

    /**
     * Every import type this caller may run.
     */
    public function index(): JsonResponse
    {
        $user = $this->person(orAbort: false);

        if ($user === null) {
            return ApiResponse::ok([]);
        }

        $importers = $this->registry->availableTo($user)
            ->map(fn (Importer $importer): array => $this->definition($importer))
            ->values();

        return ApiResponse::ok($importers->all());
    }

    /**
     * The blank file a person starts from, same as the web download button
     * (`ImportController::template()`) — one filled example row so a date
     * format or a reference-by-code convention needs no separate reading.
     */
    public function template(string $type, ImportTemplate $template): StreamedResponse
    {
        return $template->download($this->resolve($type));
    }

    /**
     * Upload and validate in one step (SRS 33) — writes nothing; a person
     * (or an integration) finds out whether the file is usable before
     * anything is confirmed.
     */
    public function store(Request $request, string $type): JsonResponse
    {
        $importer = $this->resolve($type);

        $request->validate(['file' => ['required', 'file']]);

        $job = $this->upload->handle($importer, $request->file('file'), $this->person());

        try {
            $job = $this->processor->validate($job);
        } catch (ValidationException $e) {
            throw ApiException::of(ErrorCode::VALIDATION_ERROR, implode(' ', $e->validator->errors()->all()));
        }

        return ApiResponse::created($this->jobSummary($job));
    }

    public function show(ImportJob $job): JsonResponse
    {
        $this->authorizeJob($job);

        $preview = $this->processor->preview($job);

        return ApiResponse::ok($this->jobSummary($job) + [
            // label is a translation key (see ImportColumn's own docblock):
            // `imports/review.blade.php` calls __($column->label) at render
            // time, so the API must translate it too rather than handing
            // the raw key to the frontend.
            'columns' => array_map(fn ($column): array => [
                'label' => __($column->label),
                'required' => $column->required,
                'example' => $column->example,
            ], $this->registry->find($job->type)->columns()),
            'preview_rows' => array_map(fn (PreparedRow $row): array => [
                'row_number' => $row->rowNumber,
                'is_valid' => $row->isValid(),
                'values' => $row->values,
                'errors' => $row->errors,
            ], $preview['rows']),
        ]);
    }

    /**
     * The rows the validation pass rejected.
     */
    public function errors(ImportJob $job): JsonResponse
    {
        $this->authorizeJob($job);

        $errors = $job->errors()->orderBy('row_number')->get()
            ->map(fn (ImportError $e): array => [
                'row_number' => $e->row_number,
                'field' => $e->field,
                'value' => $e->value,
                'error' => $e->error,
            ]);

        return ApiResponse::ok($errors->all());
    }

    /**
     * Write the rows that passed validation. Above the synchronous row
     * limit this queues, same threshold and same job the web confirm
     * button uses.
     */
    public function confirm(ImportJob $job): JsonResponse
    {
        $this->authorizeJob($job);

        if (! $job->isConfirmable()) {
            // Covers a file with nothing valid in it, and a second press of
            // an import that already ran. Imports are not idempotent from
            // the caller's point of view, so the state guard is the
            // protection.
            throw ApiException::of(ErrorCode::CONFLICT, __('import.not_confirmable'));
        }

        // Same threshold as the web confirm button (ImportController); not
        // a shared constant today, so kept in sync by hand.
        if ($job->valid_rows > 500) {
            $job->update(['status' => 'IMPORTING']);

            RunImportJob::dispatch($job->id, $job->company_id, $job->user->locale ?? 'en', 'IMPORT');

            return ApiResponse::accepted($this->jobSummary($job->fresh()));
        }

        return ApiResponse::ok($this->jobSummary($this->processor->import($job)));
    }

    public function cancel(ImportJob $job): JsonResponse
    {
        $this->authorizeJob($job);

        if (! in_array($job->status, ['UPLOADED', 'VALIDATED'], true)) {
            throw ApiException::of(ErrorCode::CONFLICT, __('import.not_cancellable'));
        }

        $job->update(['status' => 'CANCELLED', 'completed_at' => now()]);

        return ApiResponse::noContent();
    }

    private function resolve(string $type): Importer
    {
        $key = str_replace('-', '_', $type);

        if (! $this->registry->has($key)) {
            throw new NotFoundHttpException;
        }

        $importer = $this->registry->find($key);

        if (! $importer->allows($this->person())) {
            throw new NotFoundHttpException;
        }

        return $importer;
    }

    /**
     * The uploader's own jobs. An import carries whatever the person who
     * ran it was allowed to write, and the error report repeats their file
     * back to whoever opens it (SRS 33).
     */
    private function authorizeJob(ImportJob $job): void
    {
        $user = $this->person();

        if ($job->user_id !== $user->id) {
            throw new NotFoundHttpException;
        }

        if (! $this->registry->has($job->type) || ! $this->registry->find($job->type)->allows($user)) {
            throw new NotFoundHttpException;
        }
    }

    private function person(bool $orAbort = true): ?User
    {
        $user = $this->caller()->user;

        if ($user === null && $orAbort) {
            throw ApiException::of(ErrorCode::FORBIDDEN, __('api.step_needs_a_person'));
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(Importer $importer): array
    {
        return [
            'type' => str_replace('_', '-', $importer->type()),
            'title' => $importer->title(),
            'description' => $importer->description(),
            'supports_export' => $importer->supportsExport(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function jobSummary(ImportJob $job): array
    {
        return [
            'id' => $job->id,
            'type' => str_replace('_', '-', $job->type),
            'status' => $job->status,
            'original_name' => $job->original_name,
            'total_rows' => $job->total_rows,
            'valid_rows' => $job->valid_rows,
            'failed_rows' => $job->failed_rows,
            'success_rows' => $job->success_rows,
            'updated_rows' => $job->updated_rows,
            'is_confirmable' => $job->isConfirmable(),
            'error_message' => $job->error_message,
            'validated_at' => $job->validated_at?->toIso8601String(),
            'completed_at' => $job->completed_at?->toIso8601String(),
        ];
    }
}
