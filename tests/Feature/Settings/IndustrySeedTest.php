<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Modules\Asset\Models\AssetCategory;
use App\Modules\Asset\Models\AssetType;
use App\Modules\Asset\Models\Manufacturer;
use App\Modules\Breakdown\Models\DowntimeReasonCode;
use App\Modules\Breakdown\Models\FailureCategory;
use App\Modules\Breakdown\Models\FailureCode;
use App\Modules\Inventory\Models\SparePartCategory;
use App\Modules\Maintenance\Models\ChecklistItem;
use App\Modules\Maintenance\Models\MaintenanceTemplate;
use App\Modules\Metering\Models\MeterType;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The industry seed covers the whole mill (Seed Catalog 2, 3, 7, 9, 10).
 *
 * A composite factory runs yarn, knitting, dyeing, finishing, cutting and
 * sewing under one roof. A seed that only knows the sewing floor is a seed the
 * dye house works around with its own spreadsheet, and every figure the product
 * reports is then missing half the factory.
 */
class IndustrySeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_every_part_of_the_mill_has_an_asset_type(): void
    {
        $expected = [
            'YARN_PREP', 'KNITTING', 'DYEING', 'FABRIC_FINISHING',
            'CUTTING', 'SEWING', 'FINISHING', 'WET_PROCESS',
            'EMBROIDERY', 'PRINTING', 'UTILITY', 'HVAC',
            'MATERIAL_HANDLING', 'SAFETY', 'QUALITY_LAB',
        ];

        foreach ($expected as $code) {
            $type = AssetType::whereNull('company_id')->where('code', $code)->first();

            $this->assertNotNull($type, "Asset type {$code} is not seeded.");
            $this->assertGreaterThan(
                0,
                AssetCategory::where('asset_type_id', $type->id)->count(),
                "Asset type {$code} has no categories.",
            );
        }
    }

    /**
     * A dye vessel that stops mid-batch does not pause; it ruins the batch
     * inside it. Seeding it MEDIUM would put it below a sewing machine in
     * every queue the product builds.
     */
    public function test_dyeing_is_seeded_critical(): void
    {
        $this->assertSame(
            'CRITICAL',
            AssetType::whereNull('company_id')->where('code', 'DYEING')->firstOrFail()->default_criticality,
        );
    }

    public function test_fabric_dyeing_is_not_filed_under_garment_washing(): void
    {
        $washing = AssetType::whereNull('company_id')->where('code', 'WET_PROCESS')->firstOrFail();

        // Two places to file the same machine is exactly the ambiguity a
        // seeded taxonomy exists to prevent.
        $this->assertSame(
            0,
            AssetCategory::where('asset_type_id', $washing->id)
                ->whereIn('code', ['DYEING_MACHINE', 'SAMPLE_DYEING'])
                ->count(),
        );

        $dyeing = AssetType::whereNull('company_id')->where('code', 'DYEING')->firstOrFail();

        $this->assertSame(
            13,
            AssetCategory::where('asset_type_id', $dyeing->id)->count(),
        );
    }

    public function test_the_failure_vocabulary_covers_knitting_dyeing_and_instrumentation(): void
    {
        foreach (['KNITTING', 'DYEING_FINISHING', 'PROCESS_CONTROL'] as $category) {
            $this->assertNotNull(
                FailureCategory::whereNull('company_id')->where('code', $category)->first(),
                "Failure category {$category} is not seeded.",
            );
        }

        foreach ([
            'SINKER_BROKEN', 'YARN_FEEDER_FAULT', 'DROPPED_STITCH',
            'PUMP_SEAL_LEAK', 'NOZZLE_BLOCKED', 'HEAT_EXCHANGER_SCALING',
            'STENTER_CHAIN_FAULT', 'TEMP_SENSOR_DRIFT', 'PH_PROBE_FAULT',
            'RECIPE_DISPENSING_ERROR', 'WATER_QUALITY_FAULT',
        ] as $code) {
            $this->assertNotNull(
                FailureCode::whereNull('company_id')->where('code', $code)->first(),
                "Failure code {$code} is not seeded.",
            );
        }
    }

    public function test_a_dye_house_can_account_for_the_way_it_stops(): void
    {
        foreach (['BATCH_CHANGEOVER', 'STEAM_UNAVAILABLE', 'WATER_UNAVAILABLE', 'EFFLUENT_LIMIT'] as $code) {
            $reason = DowntimeReasonCode::whereNull('company_id')->where('code', $code)->first();

            $this->assertNotNull($reason, "Downtime reason {$code} is not seeded.");
            // None of these is a machine failure, so none of them may be
            // charged against the maintenance team's availability.
            $this->assertFalse($reason->counts_against_availability);
        }
    }

    public function test_usage_can_be_counted_in_batches_and_kilograms_not_only_hours(): void
    {
        foreach (['RUNNING_HOURS', 'BATCH_COUNT', 'FABRIC_LENGTH', 'FABRIC_WEIGHT', 'STEAM_CONSUMED'] as $code) {
            $this->assertNotNull(
                MeterType::whereNull('company_id')->where('code', $code)->first(),
                "Meter type {$code} is not seeded.",
            );
        }
    }

    public function test_the_store_can_separate_knitting_dyeing_and_finishing_spend(): void
    {
        foreach (['KNITTING_PARTS', 'DYEING_PARTS', 'FINISHING_PARTS', 'INSTRUMENTATION'] as $code) {
            $this->assertNotNull(
                SparePartCategory::whereNull('company_id')->where('code', $code)->first(),
                "Spare part category {$code} is not seeded.",
            );
        }
    }

    public function test_each_part_of_the_mill_can_run_a_pm_on_day_one(): void
    {
        foreach ([
            'PM-SEWING-MONTHLY', 'PM-CUTTING-MONTHLY', 'PM-KNITTING-MONTHLY',
            'PM-DYEING-MONTHLY', 'PM-STENTER-MONTHLY', 'CAL-LAB-QUARTERLY',
        ] as $code) {
            $template = MaintenanceTemplate::whereNull('company_id')->where('code', $code)->first();

            $this->assertNotNull($template, "Template {$code} is not seeded.");

            $version = $template->versions()->firstOrFail();

            $this->assertSame('PUBLISHED', $version->status);
            $this->assertGreaterThan(
                5,
                ChecklistItem::where('template_version_id', $version->id)->count(),
                "Template {$code} has too few items to be useful.",
            );
        }
    }

    /**
     * The dye house and the sewing floor buy from entirely different people.
     */
    public function test_the_manufacturer_list_covers_the_whole_mill(): void
    {
        foreach ([
            'JUKI', 'BROTHER',
            'MAYER_CIE', 'FUKUHARA', 'PAILUNG',
            'THIES', 'FONGS', 'SCLAVOS',
            'MONFORTS', 'BRUCKNER',
            'DATACOLOR',
        ] as $code) {
            $this->assertNotNull(
                Manufacturer::whereNull('company_id')->where('code', $code)->first(),
                "Manufacturer {$code} is not seeded.",
            );
        }
    }

    /**
     * The seed has to survive being run again on a live database.
     *
     * Shipping new master data to an existing installation means re-running
     * it, and a seeder that only works on an empty database cannot deliver
     * anything after launch.
     */
    public function test_the_seed_can_be_run_twice(): void
    {
        $before = AssetCategory::whereNull('company_id')->count();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($before, AssetCategory::whereNull('company_id')->count());
    }
}
