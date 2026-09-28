<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Reporting\Jobs\RunReportJob;
use App\Modules\Reporting\Models\ReportJob;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use App\Modules\Reporting\Services\ReportRunner;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Asks for a report and decides how it gets produced (SRS 32, ADR-032).
 *
 * One entry point for the web, the API and the console, so the size decision
 * and the permission check cannot differ between them (ADR-066).
 *
 * The permission is checked here rather than only in the controller. A queued
 * report runs minutes later with no session behind it, and a check that lives
 * on the request is a check that does not exist by the time the file is
 * written.
 */
class RequestReport
{
    /** How long a generated file stays downloadable (SRS 35). */
    public const RETENTION_DAYS = 7;

    public function __construct(
        private readonly ReportRunner $runner,
        private readonly TenantContext $context,
    ) {}

    public function handle(Report $report, ReportQuery $query, string $format, User $user): ReportJob
    {
        $this->authorize($report, $user);

        $job = ReportJob::create([
            'company_id' => $this->context->companyId(),
            'user_id' => $user->id,
            'report_type' => $report->key(),
            'parameters_json' => $query->toArray(),
            'filters_json' => $report->filters(),
            'format' => $format,
            // The requester's language, frozen now. A report generated tonight
            // and downloaded next week must read the way it was asked for.
            'locale' => $user->locale ?? app()->getLocale(),
            'status' => 'QUEUED',
            'expires_at' => CarbonImmutable::now()->addDays(self::RETENTION_DAYS),
        ]);

        if ($this->runner->shouldQueue($report, $query)) {
            RunReportJob::dispatch($job->id, $job->company_id, $job->locale);

            return $job;
        }

        // Small enough to answer now. Same code path as the queued run, so the
        // file is identical either way.
        return $this->runner->fulfil($job);
    }

    /**
     * Both permissions, not either.
     *
     * Exporting is a separate right from reading (SRS 33): a person may be
     * trusted to look at costs on screen without being trusted to walk out with
     * the spreadsheet.
     */
    private function authorize(Report $report, User $user): void
    {
        foreach (['report.report.view', 'report.report.export', $report->permission()] as $permission) {
            if (! $user->can($permission)) {
                throw new AuthorizationException(
                    __('report.not_permitted'),
                    Response::HTTP_FORBIDDEN,
                );
            }
        }
    }
}
