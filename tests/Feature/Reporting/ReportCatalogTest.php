<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Modules\Identity\Models\Permission;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use App\Modules\Reporting\Reports\ReportRegistry;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * The catalogue itself (SRS 32).
 *
 * These tests exist because a broken report is invisible until somebody needs
 * it. A missing translation shows as a raw key, a wrong permission name silently
 * hides a report from everyone, and both survive any amount of manual clicking
 * around the screens that do work.
 */
class ReportCatalogTest extends TestCase
{
    use RefreshDatabase;

    private ReportRegistry $registry;

    private Company $delta;

    private Factory $dhaka;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

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

    public function test_every_report_has_a_title_and_description_in_both_languages(): void
    {
        foreach ($this->registry->all() as $key => $report) {
            foreach (['en', 'bn'] as $locale) {
                foreach (['title', 'description'] as $part) {
                    $this->assertTrue(
                        Lang::has("report.{$key}.{$part}", $locale),
                        "Report [{$key}] has no {$part} in [{$locale}].",
                    );
                }
            }
        }
    }

    public function test_every_column_label_resolves_in_both_languages(): void
    {
        foreach ($this->registry->all() as $key => $report) {
            foreach ($report->columns() as $column => $meta) {
                foreach (['en', 'bn'] as $locale) {
                    // A missing key renders as "report.columns.foo" in a column
                    // header, which reaches a customer before it reaches us.
                    $this->assertTrue(
                        Lang::has($meta['label'], $locale),
                        "Column [{$column}] of [{$key}] has no label in [{$locale}].",
                    );
                }
            }
        }
    }

    public function test_every_report_names_a_permission_that_exists(): void
    {
        $known = Permission::pluck('code')->all();

        foreach ($this->registry->all() as $key => $report) {
            // A typo here does not fail loudly: it hides the report from every
            // role in the product, including the owner.
            $this->assertContains(
                $report->permission(),
                $known,
                "Report [{$key}] requires unknown permission [{$report->permission()}].",
            );
        }
    }

    public function test_every_report_runs_and_yields_rows_shaped_like_its_columns(): void
    {
        WorkOrderFixture::runningAsset($this->delta, $this->dhaka);

        foreach ($this->registry->all() as $key => $report) {
            $columns = array_keys($report->columns());
            $rows = 0;

            foreach ($report->rows($this->reportQuery()) as $row) {
                $this->assertSame(
                    [],
                    array_diff($columns, array_keys($row)),
                    "Report [{$key}] yielded a row missing declared columns.",
                );

                if (++$rows >= 3) {
                    break;
                }
            }

            // Whether or not there is data, the query must be valid SQL against
            // the real schema.
            $this->assertIsInt($report->estimatedRows($this->reportQuery()), "Report [{$key}] cannot be counted.");
        }
    }

    public function test_a_report_never_reaches_another_companys_rows(): void
    {
        $other = TenantFixture::company('Beta Textiles Ltd', 'BTL');
        $otherFactory = TenantFixture::factory($other, 'Gazipur Unit', 'GAZ');

        TenantFixture::actingAsTenant($other);
        $theirs = WorkOrderFixture::runningAsset($other, $otherFactory, 'SEW-GAZ-00001');

        TenantFixture::actingAsTenant($this->delta);
        WorkOrderFixture::runningAsset($this->delta, $this->dhaka, 'SEW-DHK-00412');

        $register = $this->registry->find('asset_register');

        $codes = [];

        foreach ($register->rows(new ReportQuery(
            CarbonImmutable::parse('2020-01-01'),
            CarbonImmutable::parse('2030-01-01'),
        )) as $row) {
            $codes[] = $row['asset_code'];
        }

        // A report is the easiest place in a system to leak: read-only,
        // aggregated, and rarely looked at twice.
        $this->assertContains('SEW-DHK-00412', $codes);
        $this->assertNotContains($theirs->asset_code, $codes);
    }

    public function test_report_keys_are_unique(): void
    {
        $keys = array_map(fn (Report $r) => $r->key(), array_values($this->registry->all()));

        // The registry is keyed by report key, so a duplicate would silently
        // drop a report rather than fail.
        $this->assertSame(count($keys), count(array_unique($keys)));
    }
}
