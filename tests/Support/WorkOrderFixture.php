<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Asset\Actions\ChangeAssetStatus;
use App\Modules\Asset\Actions\CreateAsset;
use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetCategory;
use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Asset\Models\AssetType;
use App\Modules\Identity\Models\User;
use App\Modules\Maintenance\Actions\CreateTemplateVersion;
use App\Modules\Maintenance\Actions\PublishTemplateVersion;
use App\Modules\Maintenance\Actions\SaveChecklistItems;
use App\Modules\Maintenance\Models\MaintenanceTemplate;
use App\Modules\Maintenance\Models\MaintenanceTemplateVersion;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\WorkOrder\Models\Technician;

/**
 * Work order test scaffolding. A work order needs a commissioned asset, a
 * factory, a technician and usually a published checklist, and repeating
 * all of that in five test classes is how they drift apart.
 */
class WorkOrderFixture
{
    public static function runningAsset(Company $company, Factory $factory, string $code = 'SEW-DHK-00412'): Asset
    {
        $location = AssetLocation::firstOrCreate(
            ['factory_id' => $factory->id, 'code' => $factory->code.'-L3'],
            ['name' => 'Line 3'],
        );

        $asset = app(CreateAsset::class)->handle([
            'asset_type_id' => AssetType::where('code', 'SEWING')->firstOrFail()->id,
            'asset_category_id' => AssetCategory::where('code', 'LOCKSTITCH')->firstOrFail()->id,
            'asset_code' => $code,
            'name' => 'Juki DDL-9000C',
            'criticality' => 'MEDIUM',
            'current_factory_id' => $factory->id,
            'asset_location_id' => $location->id,
        ]);

        $status = app(ChangeAssetStatus::class);

        foreach (['PURCHASED', 'INSTALLED', 'COMMISSIONED', 'RUNNING'] as $step) {
            $asset = $status->handle($asset, $step);
        }

        return $asset;
    }

    public static function technician(
        Company $company,
        Factory $factory,
        string $name = 'Karim Mia',
        string $employeeId = 'EMP-1001',
        ?User $user = null,
        ?int $maxConcurrent = null,
        ?string $departmentId = null,
        ?string $productionLineId = null,
    ): Technician {
        return Technician::create([
            'company_id' => $company->id,
            'factory_id' => $factory->id,
            'user_id' => $user?->id,
            'department_id' => $departmentId,
            'production_line_id' => $productionLineId,
            'employee_id' => $employeeId,
            'name' => $name,
            'status' => 'ACTIVE',
            'max_concurrent_work_orders' => $maxConcurrent,
        ]);
    }

    /**
     * A published checklist. `$items` is a list of item definitions; the
     * defaults give one required pass/fail check.
     *
     * @param  list<array<string, mixed>>  $items
     */
    public static function publishedChecklist(
        Company $company,
        array $items = [],
        string $code = 'PM-TEST',
    ): MaintenanceTemplateVersion {
        $template = MaintenanceTemplate::create([
            'company_id' => $company->id,
            'name' => 'Test checklist',
            'code' => $code,
            'status' => 'ACTIVE',
        ]);

        $version = app(CreateTemplateVersion::class)->handle($template);

        app(SaveChecklistItems::class)->handle($version, $items === [] ? [
            ['label' => 'Needle and presser foot condition', 'input_type' => 'PASS_FAIL', 'required' => true],
        ] : $items);

        return app(PublishTemplateVersion::class)->handle($version->fresh());
    }
}
