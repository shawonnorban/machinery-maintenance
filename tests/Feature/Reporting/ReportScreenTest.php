<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Modules\Identity\Models\User;
use App\Modules\Reporting\Actions\RequestReport;
use App\Modules\Reporting\Jobs\RunReportJob;
use App\Modules\Reporting\Models\ReportJob;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use App\Modules\Reporting\Reports\ReportRegistry;
use App\Modules\Reporting\Services\ReportPreview;
use App\Modules\Reporting\Services\ReportRunner;
use App\Modules\Reporting\Writers\CsvReportWriter;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * The reports screens over HTTP (SRS 32, SRS 33).
 */
class ReportScreenTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        WorkOrderFixture::runningAsset($this->delta, $this->dhaka, 'SEW-DHK-00412');
    }

    private function user(string $role, string $email): User
    {
        $user = TenantFixture::user($this->delta, $role, $email);
        TenantFixture::actingAsTenant($this->delta);

        return $user;
    }

    public function test_the_index_lists_only_reports_the_user_can_read(): void
    {
        $storekeeper = $this->user('STOREKEEPER', 'store@delta.test');

        $this->actingAs($storekeeper)
            ->get('/app/reports')
            ->assertOk()
            // A storekeeper has no cost permission, so the cost reports are not
            // offered — the gate is on the data, not on a blanket reporting
            // right.
            ->assertDontSee(__('report.maintenance_cost.title'))
            ->assertDontSee(__('report.lifecycle_cost.title'));
    }

    public function test_a_report_the_user_cannot_read_is_not_found(): void
    {
        $technician = $this->user('TECHNICIAN', 'tech@delta.test');

        // 404 rather than 403: that a costing report exists is itself
        // information about the tenant (API 2).
        $this->actingAs($technician)->get('/app/reports/maintenance_cost')->assertNotFound();
    }

    public function test_an_unknown_report_key_is_not_found(): void
    {
        $manager = $this->user('MAINTENANCE_MANAGER', 'mm@delta.test');

        $this->actingAs($manager)->get('/app/reports/no_such_report')->assertNotFound();
    }

    public function test_a_manager_can_run_a_report_and_see_its_rows(): void
    {
        $manager = $this->user('MAINTENANCE_MANAGER', 'mm2@delta.test');

        $this->actingAs($manager)
            ->get('/app/reports/asset_register')
            ->assertOk()
            ->assertSee('SEW-DHK-00412')
            ->assertSee(__('report.columns.asset_code'));
    }

    public function test_exporting_creates_a_job_and_lands_on_the_job_list(): void
    {
        $manager = $this->user('MAINTENANCE_MANAGER', 'mm3@delta.test');

        $this->actingAs($manager)
            ->post('/app/reports/asset_register/export', ['format' => 'CSV'])
            ->assertRedirect(route('app.reports.jobs'));

        $job = ReportJob::firstOrFail();

        $this->assertSame('asset_register', $job->report_type);
        $this->assertSame('COMPLETED', $job->status);
        $this->assertSame($manager->id, $job->user_id);
    }

    public function test_an_unknown_format_is_refused_rather_than_guessed(): void
    {
        $manager = $this->user('MAINTENANCE_MANAGER', 'mm4@delta.test');

        $this->actingAs($manager)
            ->from('/app/reports/asset_register')
            ->post('/app/reports/asset_register/export', ['format' => 'DOCX'])
            ->assertRedirect('/app/reports/asset_register');

        $this->assertSame(0, ReportJob::count());
    }

    public function test_a_person_cannot_download_someone_elses_report(): void
    {
        $manager = $this->user('MAINTENANCE_MANAGER', 'mm5@delta.test');
        $other = $this->user('MAINTENANCE_MANAGER', 'mm6@delta.test');

        $job = app(RequestReport::class)->handle(
            app(ReportRegistry::class)->find('asset_register'),
            new ReportQuery(CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-30')),
            'CSV',
            $manager,
        );

        $this->actingAs($manager)
            ->get(route('app.reports.jobs.download', $job))
            ->assertOk();

        // The file carries whatever the person who asked for it could see.
        // Handing it to a colleague walks around the check that produced it.
        $this->actingAs($other)
            ->get(route('app.reports.jobs.download', $job))
            ->assertNotFound();
    }

    public function test_an_expired_file_is_no_longer_downloadable(): void
    {
        $manager = $this->user('MAINTENANCE_MANAGER', 'mm7@delta.test');

        $job = app(RequestReport::class)->handle(
            app(ReportRegistry::class)->find('asset_register'),
            new ReportQuery(CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-30')),
            'CSV',
            $manager,
        );

        $job->update(['expires_at' => CarbonImmutable::now()->subDay()]);

        $this->actingAs($manager)
            ->get(route('app.reports.jobs.download', $job))
            ->assertNotFound();
    }

    public function test_a_large_report_is_queued_and_the_screen_says_so(): void
    {
        Queue::fake();

        $manager = $this->user('MAINTENANCE_MANAGER', 'mm8@delta.test');

        // Force the queue path without generating thousands of rows.
        $this->app->bind(ReportRunner::class, function ($app) {
            $runner = new class($app->make(ReportRegistry::class), $app->make(ReportPreview::class)) extends ReportRunner
            {
                public function shouldQueue(
                    Report $report,
                    ReportQuery $query,
                ): bool {
                    return true;
                }
            };

            // The writers still have to be there: a runner with none offers no
            // formats, and the export would be refused before it was queued.
            $runner->registerWriter(new CsvReportWriter);

            return $runner;
        });

        $this->actingAs($manager)
            ->post('/app/reports/asset_register/export', ['format' => 'CSV'])
            ->assertRedirect(route('app.reports.jobs'))
            ->assertSessionHas('status', __('report.queued'));

        Queue::assertPushed(RunReportJob::class);

        // Queued, so no file yet — and the row exists to say so rather than the
        // request vanishing.
        $this->assertSame('QUEUED', ReportJob::firstOrFail()->status);
    }

    public function test_the_reports_screens_render_in_bengali(): void
    {
        $manager = $this->user('MAINTENANCE_MANAGER', 'mm9@delta.test');
        $manager->update(['locale' => 'bn']);

        $this->actingAs($manager)
            ->get('/app/reports')
            ->assertOk()
            ->assertSee(__('report.reports', locale: 'bn'), false);
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get('/app/reports')->assertRedirect('/login');
    }
}
