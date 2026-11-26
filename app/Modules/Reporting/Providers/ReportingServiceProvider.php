<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Providers;

use App\Modules\Reporting\Imports\ImporterRegistry;
use App\Modules\Reporting\Reports\ReportRegistry;
use App\Modules\Reporting\Services\ReportPreview;
use App\Modules\Reporting\Services\ReportRunner;
use App\Modules\Reporting\Writers\CsvReportWriter;
use App\Modules\Reporting\Writers\PdfReportWriter;
use App\Modules\Reporting\Writers\XlsxReportWriter;
use Illuminate\Support\ServiceProvider;

class ReportingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The registry resolves every report class the first time it is asked;
        // doing that once per request is enough.
        $this->app->singleton(ReportRegistry::class);
        $this->app->singleton(ImporterRegistry::class);

        $this->app->singleton(ReportRunner::class, function ($app): ReportRunner {
            $runner = new ReportRunner(
                $app->make(ReportRegistry::class),
                $app->make(ReportPreview::class),
            );

            // Registered here rather than discovered, so the list of formats a
            // screen offers is the list that can actually be produced. PDF
            // came with mPDF, which shapes Bangla (App\Shared\Pdf\TablePdf).
            $runner->registerWriter(new CsvReportWriter);
            $runner->registerWriter(new XlsxReportWriter);
            $runner->registerWriter(new PdfReportWriter);

            return $runner;
        });
    }
}
