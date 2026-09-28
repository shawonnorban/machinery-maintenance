<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Controllers\Api;

use App\Modules\Identity\Models\User;
use App\Modules\Reporting\Actions\RequestReport;
use App\Modules\Reporting\Models\ReportJob;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use App\Modules\Reporting\Reports\ReportRegistry;
use App\Modules\Reporting\Services\ReportRunner;
use App\Modules\Tenancy\Http\Middleware\ResolveTenantContext;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Support\TenantTimezone;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Asking for the file, over the wire (API 22; mirrors the web
 * `ReportController::export()` and `ReportJobController`, which this
 * delegates to unchanged per ADR-003).
 *
 * `RequestReport` decides synchronously vs. queued and checks the
 * permission itself — the same action the web export button and the
 * console both call, so the size threshold and the permission check can
 * never differ between them.
 */
class ReportJobApiController extends ApiController
{
    public function __construct(
        private readonly ReportRegistry $registry,
        private readonly RequestReport $requester,
        private readonly ReportRunner $runner,
        private readonly TenantContext $context,
        private readonly TenantTimezone $timezone,
    ) {}

    /**
     * This caller's own requests. A report carries whatever the person who
     * asked for it was allowed to see, so it is never listed for anybody
     * else (SRS 33).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->person();

        $query = ReportJob::query()->where('user_id', $user->id)->latest();

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (ReportJob $job): array => $this->summary($job),
        );
    }

    public function show(ReportJob $job): JsonResponse
    {
        $this->assertOwn($job);

        return ApiResponse::ok($this->summary($job));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->person();

        $data = $request->validate([
            'report' => ['required', 'string'],
            'format' => ['required', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'factory_id' => ['nullable', 'string', 'size:26'],
            'asset_id' => ['nullable', 'string', 'size:26'],
            'status' => ['nullable', 'string'],
        ]);

        if (! $this->registry->has($data['report'])) {
            throw new NotFoundHttpException;
        }

        $report = $this->registry->find($data['report']);
        $format = strtoupper($data['format']);

        if (! in_array($format, $this->runner->formats(), true)) {
            throw ApiException::of(ErrorCode::VALIDATION_ERROR, __('report.unknown_format'), [
                'format' => [__('report.unknown_format')],
            ]);
        }

        $job = $this->requester->handle($report, $this->queryFrom($request), $format, $user);

        // A small report is answered in the same request, same as the web
        // export button; only a queued one is genuinely still in progress.
        return $job->status === 'QUEUED'
            ? ApiResponse::accepted($this->summary($job))
            : ApiResponse::ok($this->summary($job));
    }

    /**
     * `302` to the file, streamed the same way the web download does — this
     * is a private evidence file under the company, never a public link.
     */
    public function download(ReportJob $job): StreamedResponse
    {
        $this->assertOwn($job);

        if (! $job->isDownloadable()) {
            // Covers failed, still running and expired alike. Saying which
            // is which on a download route tells nobody anything the show
            // endpoint does not already say.
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

    private function queryFrom(Request $request): ReportQuery
    {
        $to = $request->filled('to')
            ? $this->timezone->toUtc($request->string('to').' 23:59:59')
            : CarbonImmutable::now();

        $from = $request->filled('from')
            ? $this->timezone->toUtc($request->string('from').' 00:00:00')
            : $to->subDays(30)->startOfDay();

        $scoped = session(ResolveTenantContext::FACTORY_SCOPE_KEY);
        $factoryId = $scoped ?? ($request->input('factory_id') ?: null);

        if ($factoryId !== null && ! $this->context->canAccessFactory($factoryId)) {
            $factoryId = null;
        }

        return new ReportQuery(
            from: $from,
            to: $to,
            factoryId: $factoryId,
            assetId: $request->input('asset_id') ?: null,
            extra: array_filter([
                'status' => $request->input('status'),
            ], fn ($value) => $value !== null && $value !== ''),
        );
    }

    private function assertOwn(ReportJob $job): void
    {
        if ($job->user_id !== $this->person()->id) {
            abort(404);
        }
    }

    private function person(): User
    {
        $user = $this->caller()->user;

        if ($user === null) {
            throw ApiException::of(ErrorCode::FORBIDDEN, __('api.step_needs_a_person'));
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(ReportJob $job): array
    {
        return [
            'id' => $job->id,
            'report_type' => $job->report_type,
            'format' => $job->format,
            'status' => $job->status,
            'row_count' => $job->row_count,
            'error_message' => $job->status === 'FAILED' ? $job->error_message : null,
            'is_downloadable' => $job->isDownloadable(),
            'started_at' => $job->started_at?->toIso8601String(),
            'completed_at' => $job->completed_at?->toIso8601String(),
            'expires_at' => $job->expires_at?->toIso8601String(),
            'created_at' => $job->created_at?->toIso8601String(),
        ];
    }
}
