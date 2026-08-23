<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetCategory;
use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Asset\Models\AssetType;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Reporting\Actions\UploadImport;
use App\Modules\Reporting\Imports\ImporterRegistry;
use App\Modules\Reporting\Models\ImportJob;
use App\Modules\Reporting\Services\DataExporter;
use App\Modules\Reporting\Services\ImportProcessor;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\WorkOrder\Models\WorkOrder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Upload, validate, preview, confirm (SRS 33, ADR-031).
 *
 * The property under test throughout is that the preview tells the truth: what
 * the review screen says will happen is exactly what confirming does. An import
 * that writes one more row than it promised is worse than one that refuses the
 * file, because nobody goes looking for the difference.
 */
class ImportTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private User $owner;

    private ImporterRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        AssetLocation::create([
            'factory_id' => $this->dhaka->id,
            'name' => 'Line 3',
            'code' => 'DHK-L3',
            'status' => 'ACTIVE',
        ]);

        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        $this->registry = app(ImporterRegistry::class);
    }

    private function upload(string $type, string $csv, string $name = 'import.csv'): ImportJob
    {
        $file = UploadedFile::fake()->createWithContent($name, $csv);

        $job = app(UploadImport::class)->handle($this->registry->find($type), $file, $this->owner);

        return app(ImportProcessor::class)->validate($job);
    }

    private function assetCsv(): string
    {
        return <<<'CSV'
        asset_code,name,asset_type_code,asset_category_code,factory_code,location_code,criticality,status,acquisition_cost
        IMP-001,Imported lockstitch A,SEWING,LOCKSTITCH,DHK,DHK-L3,HIGH,INSTALLED,250000
        IMP-002,Imported lockstitch B,SEWING,LOCKSTITCH,DHK,DHK-L3,MEDIUM,INSTALLED,240000
        CSV;
    }

    public function test_validation_writes_nothing(): void
    {
        $job = $this->upload('assets', $this->assetCsv());

        $this->assertSame('VALIDATED', $job->status);
        $this->assertSame(2, $job->total_rows);
        $this->assertSame(2, $job->valid_rows);

        // The whole point of a preview: a person sees what would happen before
        // anything happens.
        $this->assertSame(0, Asset::where('asset_code', 'like', 'IMP-%')->count());
    }

    public function test_confirming_writes_the_valid_rows(): void
    {
        $job = $this->upload('assets', $this->assetCsv());

        $job = app(ImportProcessor::class)->import($job);

        $this->assertSame('COMPLETED', $job->status);
        $this->assertSame(2, $job->success_rows);
        $this->assertSame(0, $job->updated_rows);

        $asset = Asset::where('asset_code', 'IMP-001')->firstOrFail();

        $this->assertSame('Imported lockstitch A', $asset->name);
        $this->assertSame('HIGH', $asset->criticality);
        $this->assertSame('INSTALLED', $asset->status);
        // Traceable back to the file it came from, which is what makes three
        // hundred machines from a bad upload findable.
        $this->assertTrue((bool) $asset->is_imported);
        $this->assertSame($job->id, $asset->imported_batch_id);
    }

    public function test_a_bad_row_is_reported_by_line_column_and_value(): void
    {
        $csv = <<<'CSV'
        asset_code,name,asset_type_code,asset_category_code,factory_code,location_code
        IMP-001,Good row,SEWING,LOCKSTITCH,DHK,DHK-L3
        IMP-002,Unknown type,NOSUCHTYPE,LOCKSTITCH,DHK,DHK-L3
        CSV;

        $job = $this->upload('assets', $csv);

        $this->assertSame(1, $job->valid_rows);
        $this->assertSame(1, $job->failed_rows);

        $error = $job->errors()->firstOrFail();

        // Row 3 counting the header as row 1: the number has to match the row
        // the person sees in their spreadsheet.
        $this->assertSame(3, $error->row_number);
        $this->assertSame('asset_type_code', $error->field);
        $this->assertSame('NOSUCHTYPE', $error->value);
    }

    public function test_a_category_from_the_wrong_type_is_refused(): void
    {
        $boiler = AssetCategory::where('code', 'BOILER')->firstOrFail();
        $sewing = AssetType::where('code', 'SEWING')->firstOrFail();

        $this->assertNotSame($sewing->id, $boiler->asset_type_id);

        $csv = <<<'CSV'
        asset_code,name,asset_type_code,asset_category_code,factory_code,location_code
        IMP-001,Mismatched,SEWING,BOILER,DHK,DHK-L3
        CSV;

        $job = $this->upload('assets', $csv);

        // The rule lives in CreateAsset; catching it in the preview means the
        // person fixes the file rather than discovering a failed row after
        // confirming.
        $this->assertSame(0, $job->valid_rows);
        $this->assertStringContainsString(
            __('import.errors.category_type_mismatch'),
            $job->errors()->firstOrFail()->error,
        );
    }

    public function test_a_code_repeated_in_the_file_is_imported_once(): void
    {
        $csv = <<<'CSV'
        asset_code,name,asset_type_code,asset_category_code,factory_code,location_code,criticality
        IMP-001,First version,SEWING,LOCKSTITCH,DHK,DHK-L3,HIGH
        IMP-001,Second version,SEWING,LOCKSTITCH,DHK,DHK-L3,LOW
        CSV;

        $job = $this->upload('assets', $csv);

        $this->assertSame(1, $job->valid_rows);
        $this->assertSame(1, $job->failed_rows);

        $job = app(ImportProcessor::class)->import($job);

        // The confirm must write exactly what the preview promised. Skipping
        // the duplicate only during validation would let the second row
        // overwrite the first, and nobody would know which one won.
        $this->assertSame(1, $job->success_rows);
        $this->assertSame(0, $job->updated_rows);
        $this->assertSame('First version', Asset::where('asset_code', 'IMP-001')->firstOrFail()->name);
    }

    public function test_importing_the_same_file_twice_updates_rather_than_duplicates(): void
    {
        app(ImportProcessor::class)->import($this->upload('assets', $this->assetCsv()));

        $second = app(ImportProcessor::class)->import($this->upload('assets', $this->assetCsv()));

        // Factories re-send a corrected file. An import that answers a re-send
        // with a second copy of every machine is one nobody uses twice.
        $this->assertSame(0, $second->success_rows);
        $this->assertSame(2, $second->updated_rows);
        $this->assertSame(2, Asset::where('asset_code', 'like', 'IMP-%')->count());
    }

    public function test_an_update_does_not_reset_a_machines_status(): void
    {
        app(ImportProcessor::class)->import($this->upload('assets', $this->assetCsv()));

        $asset = Asset::where('asset_code', 'IMP-001')->firstOrFail();
        $asset->update(['status' => 'RUNNING']);

        app(ImportProcessor::class)->import($this->upload('assets', $this->assetCsv()));

        // A machine that has been running for six months is not returned to
        // INSTALLED because a stale spreadsheet says so.
        $this->assertSame('RUNNING', $asset->fresh()->status);
    }

    public function test_a_file_missing_a_required_column_is_reported_once(): void
    {
        $csv = <<<'CSV'
        asset_code,name
        IMP-001,No type or location
        CSV;

        $job = $this->upload('assets', $csv);

        // A file-level problem, not a row-level one. Three thousand identical
        // errors would bury the one sentence that matters.
        $this->assertSame(1, $job->errors()->count());
        $this->assertStringContainsString('asset_type_code', $job->errors()->first()->error);
        $this->assertFalse($job->isConfirmable());
    }

    public function test_a_file_with_nothing_valid_cannot_be_confirmed(): void
    {
        $csv = <<<'CSV'
        asset_code,name,asset_type_code,asset_category_code,factory_code,location_code
        IMP-001,Unknown factory,SEWING,LOCKSTITCH,NOSUCHFACTORY,DHK-L3
        CSV;

        $job = $this->upload('assets', $csv);

        $this->assertSame(0, $job->valid_rows);
        $this->assertFalse($job->isConfirmable());
    }

    public function test_headers_are_matched_regardless_of_case_and_spacing(): void
    {
        $csv = "Asset_Code , NAME ,asset_type_code,asset_category_code,factory_code,location_code\n"
            .'IMP-001,Case insensitive,SEWING,LOCKSTITCH,DHK,DHK-L3';

        $job = $this->upload('assets', $csv);

        // A file rejected over a capital letter is a file that gets emailed to
        // support instead of imported.
        $this->assertSame(1, $job->valid_rows);
    }

    public function test_spare_parts_import_the_catalogue_but_never_stock(): void
    {
        $csv = <<<'CSV'
        part_number,name,category_code,unit,unit_cost,is_critical_spare
        JK-HOOK-01,Rotary hook,SEWING_PARTS,PCS,2450,yes
        CSV;

        $job = app(ImportProcessor::class)->import($this->upload('spare_parts', $csv));

        $this->assertSame(1, $job->success_rows);

        $part = SparePart::where('part_number', 'JK-HOOK-01')->firstOrFail();

        $this->assertTrue((bool) $part->is_critical_spare);

        // Stock arrives through the ledger, as a receipt with a quantity, a
        // cost and a bin. A balance written without a transaction behind it
        // breaks the first replay of the ledger (SRS 23).
        $this->assertSame(0, $part->balances()->count());
    }

    public function test_maintenance_history_arrives_closed_and_marked_as_imported(): void
    {
        app(ImportProcessor::class)->import($this->upload('assets', $this->assetCsv()));

        $csv = <<<'CSV'
        asset_code,title,maintenance_type_code,completed_at,started_at,parts_cost
        IMP-001,Quarterly service,PREVENTIVE,2025-11-14 16:30,2025-11-14 14:30,2450
        CSV;

        $job = app(ImportProcessor::class)->import($this->upload('maintenance_history', $csv));

        $this->assertSame(1, $job->success_rows);

        $order = WorkOrder::where('title', 'Quarterly service')->firstOrFail();

        $this->assertSame('CLOSED', $order->status);
        $this->assertSame('IMPORT', $order->source);
        // Declared history, distinguishable from history this system measured.
        $this->assertTrue((bool) $order->is_imported);
        // Parts only: a salaried technician's imported hours are not a cost.
        $this->assertSame('2450.0000', $order->actual_cost);

        // Two rows: raised, then closed as imported history. No SCHEDULED, no
        // IN_PROGRESS — replaying the state machine over a record that arrived
        // from a spreadsheet would write transitions at times nobody recorded,
        // and a fabricated audit trail is worse than a thin one.
        // The relation reads newest first, which is how the screens show it.
        $statuses = $order->statusHistories()->reorder('id')->pluck('to_status')->all();

        $this->assertSame(['DRAFT', 'CLOSED'], $statuses);
    }

    public function test_history_timestamps_are_read_on_the_factory_clock(): void
    {
        app(ImportProcessor::class)->import($this->upload('assets', $this->assetCsv()));

        $csv = <<<'CSV'
        asset_code,title,maintenance_type_code,completed_at
        IMP-001,Evening service,PREVENTIVE,2025-11-14 16:30
        CSV;

        app(ImportProcessor::class)->import($this->upload('maintenance_history', $csv));

        $order = WorkOrder::where('title', 'Evening service')->firstOrFail();

        // 16:30 in Dhaka is 10:30 UTC. Storing the typed time as UTC would move
        // every imported record six hours and corrupt anything derived from it.
        $this->assertSame('10:30', $order->completed_at->utc()->format('H:i'));
    }

    public function test_history_dated_in_the_future_is_refused(): void
    {
        app(ImportProcessor::class)->import($this->upload('assets', $this->assetCsv()));

        $csv = 'asset_code,title,maintenance_type_code,completed_at'."\n"
            .'IMP-001,Next year somehow,PREVENTIVE,2099-01-01 10:00';

        $job = $this->upload('maintenance_history', $csv);

        // A typo in the year column would otherwise put maintenance that has
        // not happened into a compliance figure.
        $this->assertSame(0, $job->valid_rows);
        $this->assertStringContainsString(
            __('import.errors.not_in_the_past'),
            $job->errors()->firstOrFail()->error,
        );
    }

    public function test_locations_import_and_assets_can_then_reference_them(): void
    {
        $csv = <<<'CSV'
        code,name,factory_code
        DHK-L4,Line 4,DHK
        CSV;

        $job = app(ImportProcessor::class)->import($this->upload('locations', $csv));

        $this->assertSame(1, $job->success_rows);
        $this->assertSame($this->dhaka->id, AssetLocation::where('code', 'DHK-L4')->firstOrFail()->factory_id);
    }

    public function test_an_unsupported_file_type_is_refused_before_anything_is_read(): void
    {
        $this->expectException(ValidationException::class);

        app(UploadImport::class)->handle(
            $this->registry->find('assets'),
            UploadedFile::fake()->createWithContent('payload.php', '<?php echo 1;'),
            $this->owner,
        );
    }

    public function test_an_export_can_be_imported_back_unchanged(): void
    {
        app(ImportProcessor::class)->import($this->upload('assets', $this->assetCsv()));

        $export = app(DataExporter::class)->handle($this->registry->find('assets'), 'CSV', $this->owner);

        $this->assertSame('COMPLETED', $export->status);
        $this->assertSame(2, $export->row_count);

        $csv = Storage::disk('local')->get($export->file->path);

        // The round trip is the point: pull the register out, fix rows in a
        // spreadsheet, upload it again. An export the importer cannot read is
        // a report, not an export.
        $job = app(ImportProcessor::class)->import(
            $this->upload('assets', $csv, 'roundtrip.csv'),
        );

        $this->assertSame(2, $job->total_rows);
        $this->assertSame(0, $job->failed_rows);
        $this->assertSame(2, $job->updated_rows);
        $this->assertSame(2, Asset::where('asset_code', 'like', 'IMP-%')->count());
    }

    public function test_a_value_a_spreadsheet_would_run_as_a_formula_survives_the_round_trip(): void
    {
        app(ImportProcessor::class)->import($this->upload('assets', $this->assetCsv()));

        // A valid machine name in this system, and a command execution in
        // Excel. The export escapes it; the import must give it back intact.
        $hostile = '=cmd|\' /c calc\'!A1';

        Asset::where('asset_code', 'IMP-001')->firstOrFail()->update(['name' => $hostile]);

        $export = app(DataExporter::class)->handle($this->registry->find('assets'), 'CSV', $this->owner);

        $csv = Storage::disk('local')->get($export->file->path);

        $this->assertStringContainsString("'=cmd", $csv);

        app(ImportProcessor::class)->import($this->upload('assets', $csv, 'roundtrip.csv'));

        $this->assertSame($hostile, Asset::where('asset_code', 'IMP-001')->firstOrFail()->name);
    }

    public function test_exporting_requires_the_export_right(): void
    {
        $engineer = TenantFixture::user($this->delta, 'MAINTENANCE_ENGINEER', 'eng@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        $this->assertTrue($engineer->can('asset.asset.create'));
        $this->assertFalse($engineer->can('export.job.create'));

        $this->expectException(AuthorizationException::class);

        app(DataExporter::class)->handle($this->registry->find('assets'), 'CSV', $engineer);
    }

    public function test_maintenance_history_is_not_offered_as_an_export(): void
    {
        // It is derived from work orders, and the maintenance history report
        // already answers that question properly. An export that cannot be
        // imported back is a report wearing the wrong label.
        $this->assertFalse($this->registry->find('maintenance_history')->supportsExport());
        $this->assertTrue($this->registry->find('assets')->supportsExport());
    }

    public function test_every_importer_has_its_columns_translated_in_both_languages(): void
    {
        foreach ($this->registry->all() as $type => $importer) {
            foreach (['en', 'bn'] as $locale) {
                $this->assertTrue(
                    Lang::has("import.types.{$type}.title", $locale),
                    "Importer [{$type}] has no title in [{$locale}].",
                );

                foreach ($importer->columns() as $name => $column) {
                    $this->assertTrue(
                        Lang::has($column->label, $locale),
                        "Column [{$name}] of [{$type}] has no label in [{$locale}].",
                    );
                }
            }
        }
    }
}
