<?php

declare(strict_types=1);

namespace Tests\Feature\Erp;

use App\Modules\DevSample\Models\DsColumn;
use App\Modules\DevSample\Models\DsGroup;
use App\Modules\DevSample\Support\DsCatalog;
use App\Modules\OrderSummary\Models\OsColumn;
use App\Modules\OrderSummary\Models\OsGroup;
use App\Modules\OrderSummary\Support\ColumnCatalog;
use App\Modules\PreBudget\Models\PbColumn;
use App\Modules\PreBudget\Models\PbGroup;
use App\Modules\PreBudget\Models\PbUnitRule;
use App\Modules\PreBudget\Support\BomCatalog;
use App\Modules\PreBudget\Support\RequisitionCatalog;
use App\Shared\Support\ColumnSetupSnapshot;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Column Setup snapshots (user 2026-11-21): a new company's Order Summary,
 * Requisition, Acc Budget and Dev Sample sheets start from the saved files exactly as
 * they were saved (columns, groups, colours, formulas, unit rules); with the
 * snapshots off they start from the catalog; and a save writes back what it read.
 */
class ColumnSetupSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_a_new_company_starts_from_the_saved_column_setup(): void
    {
        config(['app.column_setup_snapshots' => true]);
        $company = TenantFixture::company('Fresh Apparels Ltd', 'FAL');
        TenantFixture::actingAsTenant($company);
        ColumnCatalog::ensure($company->id);
        RequisitionCatalog::ensure($company->id);
        BomCatalog::ensure($company->id);
        DsCatalog::ensure($company->id);

        $sheets = [
            [ColumnCatalog::snapshotPath(), OsGroup::class, OsColumn::class, null, null],
            [RequisitionCatalog::snapshotPath(), PbGroup::class, PbColumn::class, 'REQ', null],
            [BomCatalog::snapshotPath(), PbGroup::class, PbColumn::class, BomCatalog::SHEET, PbUnitRule::class],
            [DsCatalog::snapshotPath(), DsGroup::class, DsColumn::class, null, null],
        ];
        foreach ($sheets as [$path, $group, $column, $sheet, $rules]) {
            $saved = ColumnSetupSnapshot::read($path);
            $this->assertNotNull($saved, "{$path} is missing");
            $this->assertEquals($saved, ColumnSetupSnapshot::export($company->id, $group, $column, $sheet, $rules), basename($path));
        }
    }

    public function test_with_snapshots_off_a_new_company_starts_from_the_catalog(): void
    {
        config(['app.column_setup_snapshots' => false]);
        $company = TenantFixture::company('Fresh Apparels Ltd', 'FAL');
        TenantFixture::actingAsTenant($company);
        ColumnCatalog::ensure($company->id);

        $this->assertNull(ColumnSetupSnapshot::read(ColumnCatalog::snapshotPath()));
        $this->assertSame(array_column(ColumnCatalog::COLUMNS, 'key'), OsColumn::query()->withoutGlobalScopes()->where('company_id', $company->id)->orderBy('sort_order')->pluck('key')->all());
    }
}
