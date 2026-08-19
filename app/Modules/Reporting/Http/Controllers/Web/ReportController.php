<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Controllers\Web;

use App\Modules\Asset\Models\Asset;
use App\Modules\Reporting\Actions\RequestReport;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use App\Modules\Reporting\Reports\ReportRegistry;
use App\Modules\Reporting\Services\ReportPreview;
use App\Modules\Reporting\Services\ReportRunner;
use App\Modules\Tenancy\Http\Middleware\ResolveTenantContext;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Support\TenantTimezone;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The reports screens (SRS 32).
 *
 * A report is picked, scoped, previewed on screen and then exported. The
 * preview matters: a person who exports a spreadsheet only to find they picked
 * the wrong month has learned nothing except not to trust the screen.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly ReportRegistry $registry,
        private readonly ReportPreview $preview,
        private readonly ReportRunner $runner,
        private readonly TenantContext $context,
        private readonly TenantTimezone $timezone,
    ) {}

    public function index(Request $request): View
    {
        return view('reporting::reports.index', [
            'groups' => $this->registry->groupedFor($request->user()),
        ]);
    }

    public function show(Request $request, string $key): View
    {
        $report = $this->resolve($request, $key);
        $query = $this->queryFrom($request, $report);

        $preview = $this->preview->rows($report, $query);

        return view('reporting::reports.show', [
            'report' => $report,
            'query' => $query,
            'columns' => $report->columns(),
            'rows' => $preview['rows'],
            'truncated' => $preview['truncated'],
            'meta' => $this->preview->metaFor($report, $query),
            'formats' => $this->runner->formats(),
            'willQueue' => $this->runner->shouldQueue($report, $query),
            'factories' => $this->factories(),
            'assets' => in_array('asset', $report->filters(), true) ? $this->assets($query) : collect(),
            'statuses' => Asset::STATUSES,
            'previewLimit' => ReportPreview::PREVIEW_ROWS,
        ]);
    }

    /**
     * Ask for the file.
     *
     * Always leaves through the job list, whether the report ran immediately or
     * was queued. One place to look for a report is easier to explain than two,
     * and the queued case has nowhere else to go.
     */
    public function export(Request $request, string $key, RequestReport $action): RedirectResponse
    {
        $report = $this->resolve($request, $key);

        $format = strtoupper((string) $request->input('format', 'CSV'));

        if (! in_array($format, $this->runner->formats(), true)) {
            return back()->with('error', __('report.unknown_format'));
        }

        $job = $action->handle($report, $this->queryFrom($request, $report), $format, $request->user());

        return redirect()
            ->route('app.reports.jobs')
            ->with('status', $job->status === 'COMPLETED'
                ? __('report.ready')
                : __('report.queued'));
    }

    private function resolve(Request $request, string $key): Report
    {
        if (! $this->registry->has($key)) {
            throw new NotFoundHttpException;
        }

        $report = $this->registry->find($key);

        // 404 rather than 403 for a report the user cannot see: the existence
        // of a costing report is itself information about the tenant (API 2).
        if (! $request->user()->can('report.report.view') || ! $request->user()->can($report->permission())) {
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

        // The global factory scope unless the form narrows it further. A report
        // must never widen what the header has restricted (Frontend 4.2).
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

    private function factories()
    {
        return Factory::whereIn('id', $this->context->accessibleFactoryIds())->orderBy('name')->get();
    }

    private function assets(ReportQuery $query)
    {
        return Asset::query()
            ->when($query->factoryId !== null, fn ($q) => $q->where('current_factory_id', $query->factoryId))
            ->orderBy('asset_code')
            ->limit(500)
            ->get(['id', 'asset_code', 'name']);
    }
}
