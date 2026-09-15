<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Controllers\Api;

use App\Modules\Reporting\Imports\ImporterRegistry;
use App\Modules\Reporting\Models\ExportJob;
use App\Modules\Reporting\Services\DataExporter;
use App\Modules\Reporting\Services\ReportRunner;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Raw data out, in the shape it can come back in (API 24; mirrors the web
 * `ImportController::export()`/`download()`, which this delegates to
 * unchanged per ADR-003).
 *
 * Different from a report job on purpose: a report is a view shaped for
 * reading, this is current data shaped so it can be re-imported — the same
 * columns the matching importer accepts.
 */
class ExportApiController extends ApiController
{
    public function __construct(
        private readonly ImporterRegistry $registry,
        private readonly DataExporter $exporter,
        private readonly ReportRunner $runner,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string'],
            'format' => ['required', 'string'],
        ]);

        $importer = $this->registry->find(str_replace('-', '_', $data['type']));

        if (! $importer->supportsExport()) {
            throw ApiException::of(ErrorCode::VALIDATION_ERROR, __('import.export_unavailable'), [
                'type' => [__('import.export_unavailable')],
            ]);
        }

        $format = strtoupper($data['format']);

        if (! in_array($format, $this->runner->formats(), true)) {
            throw ApiException::of(ErrorCode::VALIDATION_ERROR, __('report.unknown_format'), [
                'format' => [__('report.unknown_format')],
            ]);
        }

        $user = $this->caller()->user;

        if ($user === null) {
            throw ApiException::of(ErrorCode::FORBIDDEN, __('api.step_needs_a_person'));
        }

        try {
            $job = $this->exporter->handle($importer, $format, $user);
        } catch (AuthorizationException) {
            throw ApiException::of(ErrorCode::FORBIDDEN);
        }

        return ApiResponse::created($this->summary($job));
    }

    public function show(ExportJob $job): JsonResponse
    {
        $this->assertOwn($job);

        return ApiResponse::ok($this->summary($job));
    }

    public function download(ExportJob $job): StreamedResponse
    {
        $this->assertOwn($job);

        if (! $job->isDownloadable()) {
            throw new NotFoundHttpException;
        }

        $file = $job->file;

        if ($file === null || ! Storage::disk($file->disk)->exists($file->path)) {
            throw new NotFoundHttpException;
        }

        return Storage::disk($file->disk)->download(
            $file->path,
            $file->original_name,
            ['Content-Type' => $file->mime_type],
        );
    }

    private function assertOwn(ExportJob $job): void
    {
        if ($job->requested_by !== $this->caller()->auditUserId()) {
            throw new NotFoundHttpException;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(ExportJob $job): array
    {
        return [
            'id' => $job->id,
            'type' => str_replace('_', '-', $job->type),
            'format' => $job->format,
            'status' => $job->status,
            'row_count' => $job->row_count,
            'error_message' => $job->error_message,
            'is_downloadable' => $job->isDownloadable(),
            'completed_at' => $job->completed_at?->toIso8601String(),
            'expires_at' => $job->expires_at?->toIso8601String(),
        ];
    }
}
