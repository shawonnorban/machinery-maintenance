<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\PreBudget\Database\Seeders\PreBudgetMasterSeed;
use App\Modules\Accounting\Database\Seeders\AccountingMasterSeed;
use App\Modules\Api\Support\ApiCaller;
use App\Modules\ColourLab\Database\Seeders\ColourLabMasterSeed;
use App\Modules\DyeHouse\Database\Seeders\DyeHouseMasterSeed;
use App\Modules\Erp\Support\MasterSeed;
use App\Modules\Erp\Support\ResourceSeeder;
use App\Modules\Erp\Support\SeedTokenHandle;
use App\Modules\FabricPlanning\Database\Seeders\FabricPlanningMasterSeed;
use App\Modules\Finishing\Database\Seeders\FinishingMasterSeed;
use App\Modules\GatePass\Database\Seeders\GatePassMasterSeed;
use App\Modules\GeneralStore\Database\Seeders\GeneralStoreMasterSeed;
use App\Modules\HrPayroll\Database\Seeders\HrPayrollMasterSeed;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Knitting\Database\Seeders\KnittingMasterSeed;
use App\Modules\MaterialProcurement\Database\Seeders\MaterialProcurementMasterSeed;
use App\Modules\Merchandise\Database\Seeders\MerchandiseMasterSeed;
use App\Modules\Planning\Database\Seeders\PlanningMasterSeed;
use App\Modules\Settings\Database\Seeders\SettingsMasterSeed;
use App\Modules\Tenancy\Models\Company;
use App\Modules\TradeExport\Database\Seeders\TradeExportMasterSeed;
use App\Modules\WorkStudy\Database\Seeders\WorkStudyMasterSeed;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Sample master data for every module built so far, seeded through each
 * screen's own rules into each company (or the ones named by code), as the
 * company's owner. Rows already there are kept as they are, so it can run
 * again at any time:
 *
 *   php artisan erp:seed-masters            every company
 *   php artisan erp:seed-masters DAL OTL    named companies
 *
 * Not part of DatabaseSeeder: tests start from empty masters on purpose.
 */
class ErpMasterDataSeeder extends Seeder
{
    /** In dependency order: a module comes after the masters it points at. @var list<class-string<MasterSeed>> */
    public const SEEDS = [
        SettingsMasterSeed::class,
        MaterialProcurementMasterSeed::class,
        MerchandiseMasterSeed::class,
        TradeExportMasterSeed::class,
        AccountingMasterSeed::class,
        GeneralStoreMasterSeed::class,
        GatePassMasterSeed::class,
        PlanningMasterSeed::class,
        DyeHouseMasterSeed::class,
        ColourLabMasterSeed::class,
        FabricPlanningMasterSeed::class,
        WorkStudyMasterSeed::class,
        KnittingMasterSeed::class,
        FinishingMasterSeed::class,
        HrPayrollMasterSeed::class,
        PreBudgetMasterSeed::class,
    ];

    /** @var list<string> company codes; empty = every company */
    public array $companies = [];

    /** @var array<string, array<string, int>> company code => resource => rows created */
    public array $report = [];

    /** @var array<string, list<string>> company code => seed rows turned away, with the reason */
    public array $skipped = [];

    public function run(): void
    {
        $context = app(TenantContext::class);
        $query = Company::query()->orderBy('code');
        if ($this->companies !== []) {
            $query->whereIn('code', $this->companies);
        }

        try {
            foreach ($query->get() as $company) {
                $context->forget();
                $context->set($company->id);
                // The rows read as the owner's own: "raised by", "prepared by" and the like.
                $owner = $this->owner($company);
                if ($owner === null) {
                    $this->report[$company->code] = [];
                    $this->skipped[$company->code] = ['the company has no user to seed as'];

                    continue;
                }
                app()->instance(ApiCaller::class, ApiCaller::forUser(new SeedTokenHandle($company->id, $owner->id), $owner));
                $seeder = app(ResourceSeeder::class);
                $failed = [];
                foreach (self::SEEDS as $class) {
                    try {
                        app($class)->seed($seeder);
                    } catch (RuntimeException $e) {
                        // A company without the setup a module needs (no factory, no floor): the rest still seed.
                        $failed[] = class_basename($class).' stopped: '.$e->getMessage();
                    }
                }
                $this->report[$company->code] = $seeder->created();
                $this->skipped[$company->code] = [...$seeder->skipped(), ...$failed];
            }
        } finally {
            $context->forget();
            app()->forgetInstance(ApiCaller::class);
        }
    }

    /** The company's owner; failing that its first user. */
    private function owner(Company $company): ?User
    {
        $roleId = Role::query()->whereNull('company_id')->where('name', 'COMPANY_OWNER')->value('id');
        $userId = UserRole::withoutGlobalScope(TenantScope::class)->where('company_id', $company->id)->where('role_id', $roleId)->orderBy('created_at')->value('user_id')
            ?? CompanyUser::withoutGlobalScope(TenantScope::class)->where('company_id', $company->id)->orderBy('created_at')->value('user_id');

        return $userId ? User::find($userId) : null;
    }
}
