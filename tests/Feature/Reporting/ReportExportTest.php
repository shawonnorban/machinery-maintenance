<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Modules\Asset\Models\Asset;
use App\Modules\Reporting\Actions\RequestReport;
use App\Modules\Reporting\Jobs\RunReportJob;
use App\Modules\Reporting\Models\ReportJob;
use App\Modules\Reporting\Reports\ReportQuery;
use App\Modules\Reporting\Reports\ReportRegistry;
use App\Modules\Reporting\Services\ReportRunner;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * Producing the file (SRS 32, SRS 33).
 *
 * An export is the moment data leaves the system, so the two things worth
 * pinning are that the file contains what the screen showed, and that it says
 * what it was asked for. A spreadsheet without its period and scope is a set of
 * numbers nobody can check.
 */
class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private ReportRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        WorkOrderFixture::runningAsset($this->delta, $this->dhaka, 'SEW-DHK-00412');
        WorkOrderFixture::runningAsset($this->delta, $this->dhaka, 'SEW-DHK-00413');

        $this->registry = app(ReportRegistry::class);
    }

    private function reportQuery(): ReportQuery
    {
        return new ReportQuery(
            CarbonImmutable::parse('2026-06-01'),
            CarbonImmutable::parse('2026-06-30 23:59:59'),
            $this->dhaka->id,
        );
    }

    private function contents(ReportJob $job): string
    {
        $file = $job->file;

        return Storage::disk($file->disk)->get($file->path);
    }

    public function test_a_csv_export_carries_the_header_the_columns_and_the_rows(): void
    {
        $manager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'mm@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        $job = app(RequestReport::class)->handle(
            $this->registry->find('asset_register'), $this->reportQuery(), 'CSV', $manager,
        );

        $this->assertSame('COMPLETED', $job->status);
        $this->assertSame(2, $job->row_count);

        $csv = $this->contents($job);

        // A byte order mark, or Excel reads the file in the system codepage and
        // every Bengali column arrives as mojibake.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString(__('report.meta.generated_at'), $csv);
        $this->assertStringContainsString(__('report.columns.asset_code'), $csv);
        $this->assertStringContainsString('SEW-DHK-00412', $csv);
        $this->assertStringContainsString('SEW-DHK-00413', $csv);
    }

    public function test_the_file_records_the_period_and_scope_it_was_run_for(): void
    {
        $manager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'mm2@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        $job = app(RequestReport::class)->handle(
            $this->registry->find('downtime'), $this->reportQuery(), 'CSV', $manager,
        );

        $csv = $this->contents($job);

        // Provenance: a report emailed on without its parameters cannot be
        // checked by whoever receives it (SRS 44).
        $this->assertStringContainsString('2026-06-01', $csv);
        $this->assertStringContainsString('Dhaka Unit 1', $csv);
        $this->assertSame($this->reportQuery()->toArray()['factory_id'], $job->parameters_json['factory_id']);
    }

    public function test_an_xlsx_export_writes_numbers_as_numbers(): void
    {
        $manager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'mm3@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        Asset::where('asset_code', 'SEW-DHK-00412')->update(['acquisition_cost' => '285000.0000']);

        $job = app(RequestReport::class)->handle(
            $this->registry->find('asset_register'), $this->reportQuery(), 'XLSX', $manager,
        );

        $this->assertSame('COMPLETED', $job->status);

        $path = Storage::disk('local')->path($job->file->path);

        $reader = new Reader;
        $reader->open($path);

        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }

            break;
        }

        $reader->close();

        $data = collect($rows)->first(fn (array $row) => ($row[0] ?? null) === 'SEW-DHK-00412');

        $this->assertNotNull($data, 'The exported sheet has no row for the asset.');

        // Acquisition cost is a decimal string in the database. Written as text
        // it becomes a column nobody can sum, which is the first thing anyone
        // does with an exported cost column.
        $cost = $data[array_search('acquisition_cost', array_keys($this->registry->find('asset_register')->columns()), true)];

        $this->assertIsNotString($cost);
        $this->assertEqualsWithDelta(285000.0, $cost, 0.001);
    }

    public function test_a_large_report_is_queued_rather_than_run_in_the_request(): void
    {
        $report = $this->registry->find('asset_register');
        $runner = app(ReportRunner::class);

        $this->assertFalse($runner->shouldQueue($report, $this->reportQuery()));

        // The threshold is the whole point of ADR-032: an HTTP request must not
        // hold a connection open while a fleet's history is assembled.
        $this->assertGreaterThan(0, ReportRunner::SYNCHRONOUS_ROW_LIMIT);
    }

    public function test_exporting_requires_the_export_right_not_only_the_view_right(): void
    {
        $viewer = TenantFixture::user($this->delta, 'VIEWER', 'viewer@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        $this->assertTrue($viewer->can('report.report.view'));
        $this->assertFalse($viewer->can('report.report.export'));

        // Reading a figure on screen and walking out with the spreadsheet are
        // different rights (SRS 33).
        $this->expectException(AuthorizationException::class);

        app(RequestReport::class)->handle(
            $this->registry->find('asset_register'), $this->reportQuery(), 'CSV', $viewer,
        );
    }

    public function test_a_failed_report_records_why_on_its_row(): void
    {
        $manager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'mm4@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        $job = ReportJob::create([
            'company_id' => $this->delta->id,
            'user_id' => $manager->id,
            'report_type' => 'no_such_report',
            'parameters_json' => $this->reportQuery()->toArray(),
            'format' => 'CSV',
            'locale' => 'en',
            'status' => 'QUEUED',
            'expires_at' => CarbonImmutable::now()->addDay(),
        ]);

        try {
            app(ReportRunner::class)->fulfil($job);
            $this->fail('An unknown report type should not produce a file.');
        } catch (\Throwable) {
            // Expected: the point is what the row says afterwards.
        }

        $job->refresh();

        // A queue worker's log is not somewhere the person waiting can look.
        $this->assertSame('FAILED', $job->status);
        $this->assertStringContainsString('no_such_report', $job->error_message);
        $this->assertNull($job->file_id);
    }

    public function test_the_generated_file_is_stored_under_the_company(): void
    {
        $manager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'mm5@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        $job = app(RequestReport::class)->handle(
            $this->registry->find('asset_register'), $this->reportQuery(), 'CSV', $manager,
        );

        // Same isolation as every other stored file: a generated report holds
        // the data of the screens it came from.
        $this->assertStringContainsString("reports/{$this->delta->id}/", $job->file->path);
        $this->assertSame($this->delta->id, $job->file->company_id);
        $this->assertSame(hash('sha256', $this->contents($job)), $job->file->sha256);
    }

    public function test_the_queued_job_restores_the_tenant_and_the_locale(): void
    {
        $manager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'mmq@delta.test');
        $manager->update(['locale' => 'bn']);
        TenantFixture::actingAsTenant($this->delta);

        $job = ReportJob::create([
            'company_id' => $this->delta->id,
            'user_id' => $manager->id,
            'report_type' => 'asset_register',
            'parameters_json' => $this->reportQuery()->toArray(),
            'format' => 'CSV',
            'locale' => 'bn',
            'status' => 'QUEUED',
            'expires_at' => CarbonImmutable::now()->addDay(),
        ]);

        // A worker has no session. Without the company the tenant scope has
        // nothing to scope to, and without the locale a Bengali user gets a
        // file they cannot read.
        app(TenantContext::class)->forget();
        App::setLocale('en');

        (new RunReportJob($job->id, $this->delta->id, 'bn'))
            ->handle(app(TenantContext::class), app(ReportRunner::class));

        $job->refresh();

        $this->assertSame('COMPLETED', $job->status);
        $this->assertSame(2, $job->row_count);

        $csv = $this->contents($job);

        $this->assertStringContainsString('SEW-DHK-00412', $csv);
        $this->assertStringContainsString(__('report.columns.asset_code', locale: 'bn'), $csv);
    }

    public function test_pruning_deletes_the_file_and_keeps_the_record(): void
    {
        $manager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'mm7@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        $job = app(RequestReport::class)->handle(
            $this->registry->find('asset_register'), $this->reportQuery(), 'CSV', $manager,
        );

        $path = $job->file->path;

        $this->assertTrue(Storage::disk('local')->exists($path));

        $job->update(['expires_at' => CarbonImmutable::now()->subDay()]);

        $this->artisan('reports:prune')->assertSuccessful();

        $job->refresh();

        // The file goes; the row stays. What was asked for and by whom is the
        // part an audit needs, and it costs a few hundred bytes (SRS 35).
        $this->assertFalse(Storage::disk('local')->exists($path));
        $this->assertSame('EXPIRED', $job->status);
        $this->assertNull($job->file_id);
        $this->assertSame('asset_register', $job->report_type);
    }

    public function test_a_report_expires_so_generated_files_do_not_live_for_ever(): void
    {
        $manager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'mm6@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        $job = app(RequestReport::class)->handle(
            $this->registry->find('asset_register'), $this->reportQuery(), 'CSV', $manager,
        );

        $this->assertTrue($job->isDownloadable());

        $job->update(['expires_at' => CarbonImmutable::now()->subMinute()]);

        // A copy of a tenant's cost figures sitting in a file nobody remembers
        // exists is a retention problem, not a feature (SRS 35).
        $this->assertFalse($job->fresh()->isDownloadable());
    }
}
