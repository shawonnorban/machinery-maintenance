<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Controllers\Api;

use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use App\Modules\Reporting\Reports\ReportRegistry;
use App\Modules\Reporting\Services\ReportPreview;
use App\Modules\Reporting\Services\ReportRunner;
use App\Modules\Tenancy\Http\Middleware\ResolveTenantContext;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Support\TenantTimezone;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The report catalogue and its on-screen preview, over the wire (API 22;
 * mirrors the web `ReportController`, which this delegates to unchanged per
 * ADR-003).
 *
 * One generic endpoint per action rather than one route per report, exactly
 * as `ReportRegistry` is one registry for the eighteen reports SRS 32 names
 * rather than eighteen controllers. `POST /report-jobs` is where the full,
 * unbounded export is asked for (`ReportJobApiController`); this controller
 * only ever returns the same capped preview the run screen shows, because a
 * report with forty thousand rows is not something either surface should
 * hand back inline.
 */
class ReportApiController extends ApiController
{
    public function __construct(
        private readonly ReportRegistry $registry,
        private readonly ReportPreview $preview,
        private readonly ReportRunner $runner,
        private readonly TenantContext $context,
        private readonly TenantTimezone $timezone,
    ) {}

    /**
     * Every report this caller may run, grouped the way the run screen
     * groups them.
     */
    public function index(): JsonResponse
    {
        $user = $this->caller()->user;

        // Every report's permission is checked against a person's roles;
        // a machine caller has none to check and so can run none, the same
        // way `AuthController::companies()` answers a machine with an empty
        // list rather than an error.
        if ($user === null) {
            return ApiResponse::ok([]);
        }

        $reports = $this->registry->availableTo($user)
            ->map(fn (Report $report): array => $this->definition($report))
            ->values();

        return ApiResponse::ok($reports->all());
    }

    /**
     * The capped preview a run screen shows before anybody commits to an
     * export — same 200-row cap, same meta block.
     */
    public function show(Request $request, string $key): JsonResponse
    {
        $report = $this->resolve($key);
        $query = $this->queryFrom($request, $report);

        $preview = $this->preview->rows($report, $query);

        return ApiResponse::ok([
            'report' => $this->definition($report),
            'query' => [
                'from' => $query->from->toIso8601String(),
                'to' => $query->to->toIso8601String(),
                'factory_id' => $query->factoryId,
                'asset_id' => $query->assetId,
            ],
            'meta' => $this->preview->metaFor($report, $query),
            // Mirrors `reports/show.blade.php`'s own `__($column['label'])`:
            // columns() carries a translation key, not display text — the
            // web view translates it at render time, so the API must do
            // the same rather than handing the raw key to the frontend.
            'columns' => collect($report->columns())
                ->map(fn (array $column): array => [...$column, 'label' => __($column['label'])])
                ->all(),
            'rows' => $preview['rows'],
            'truncated' => $preview['truncated'],
            // Mirrors `ReportController::run()`'s own `formats` — the export
            // button's format choices are never assumed, since ReportRunner's
            // registered writers are the actual source of truth for what a
            // caller can ask ReportJobApiController::store() to produce.
            'formats' => $this->runner->formats(),
            // Only when the report's own filters() actually offer a factory
            // picker — and read at this report's own `report.report.view`-
            // adjacent permission tier, not `settings.factory.manage` (the
            // same over-privileged-dropdown mistake `AssetApiController::
            // formOptions()` and `TechnicianApiController::formOptions()`
            // were already found and fixed for elsewhere this session).
            'factories' => in_array('factory', $report->filters(), true)
                ? Factory::whereIn('id', $this->context->accessibleFactoryIds())
                    ->orderBy('name')->get(['id', 'name'])->all()
                : [],
        ]);
    }

    private function resolve(string $key): Report
    {
        if (! $this->registry->has($key)) {
            throw new NotFoundHttpException;
        }

        $report = $this->registry->find($key);

        $user = $this->caller()->user;

        // 404 rather than 403 for a report the caller cannot see: the
        // existence of a costing report is itself information about the
        // tenant (API 2).
        if ($user === null || ! $user->can('report.report.view') || ! $user->can($report->permission())) {
            throw new NotFoundHttpException;
        }

        return $report;
    }

    private function queryFrom(Request $request, Report $report): ReportQuery
    {
        $to = $request->filled('to')
            ? $this->timezone->toUtc($request->string('to').' 23:59:59')
            : CarbonImmutable::now();

        $from = $request->filled('from')
            ? $this->timezone->toUtc($request->string('from').' 00:00:00')
            : $to->subDays(30)->startOfDay();

        // The global factory scope unless the request narrows it further. A
        // report must never widen what the header has restricted (Frontend 4.2).
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

    /**
     * @return array<string, mixed>
     */
    private function definition(Report $report): array
    {
        return [
            'key' => $report->key(),
            'title' => $report->title(),
            'description' => $report->description(),
            'group' => $report->group(),
            'filters' => $report->filters(),
        ];
    }
}
