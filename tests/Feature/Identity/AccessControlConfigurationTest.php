<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\LookupTable;
use App\Modules\Identity\Models\Menu;
use App\Modules\Identity\Models\MenuModule;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\CategoryAccess;
use App\Modules\ItemMaster\Models\ItemCategory;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Access Control, step C (AnnotechERP 02/01): Module Registration and Menu
 * Definition as per-company overrides, Lookup Table Master, System Parameter
 * Update's change record, and Category Access Permission.
 */
class AccessControlConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Company $gamma;

    private User $owner;

    private User $gammaOwner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->gamma = TenantFixture::company('Gamma Knit Ltd', 'GKL');
        TenantFixture::factory($this->gamma, 'Gazipur', 'GZP');
        TenantFixture::actingAsTenant($this->gamma);
        $this->gammaOwner = TenantFixture::user($this->gamma, 'COMPANY_OWNER', 'owner@gamma.test');

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    public function test_a_company_renames_and_hides_a_module_for_itself_only(): void
    {
        $module = MenuModule::where('code', 'MACHINERY')->firstOrFail();

        $this->as($this->owner)->patchJson('/api/v1/access/modules/'.$module->id, ['name' => 'Purchasing', 'sort_order' => 1, 'active' => false])
            ->assertOk()
            ->assertJsonPath('data.name', 'Purchasing')
            ->assertJsonPath('data.active', false);

        $modules = fn (User $user, Company $company): array => collect($this->as($user, $company)->getJson('/api/v1/access/navigation')->assertOk()->json('data'))->pluck('code')->all();

        $this->assertNotContains('MACHINERY', $modules($this->owner, $this->delta));
        $this->assertContains('MACHINERY', $modules($this->gammaOwner, $this->gamma));

        $gammaNames = collect($this->as($this->gammaOwner, $this->gamma)->getJson('/api/v1/access/modules')->json('data'))->pluck('name', 'code');
        $this->assertNotSame('Purchasing', $gammaNames['MACHINERY']);
        // The shared row is untouched.
        $this->assertTrue($module->fresh()->active);
    }

    public function test_sending_back_the_codes_own_value_follows_the_code_again(): void
    {
        $menu = Menu::where('code', 'MACHINERY.MAINT.WORK_ORDERS')->firstOrFail();

        $this->as($this->owner)->patchJson('/api/v1/access/menus/'.$menu->id, ['name' => 'Purchase Orders'])->assertJsonPath('data.name', 'Purchase Orders');
        $this->as($this->owner)->patchJson('/api/v1/access/menus/'.$menu->id, ['name' => $menu->name])->assertJsonPath('data.name', $menu->name);

        $this->assertDatabaseHas('menu_overrides', ['company_id' => $this->delta->id, 'menu_id' => $menu->id, 'name' => null]);
    }

    public function test_lookup_tables_are_shared_read_only_and_each_company_adds_its_own(): void
    {
        $shift = LookupTable::whereNull('company_id')->where('code', 'SHIFT')->firstOrFail();

        $this->as($this->owner)->getJson('/api/v1/access/lookups')
            ->assertOk()
            ->assertJsonFragment(['code' => 'SHIFT', 'is_system' => true, 'values_count' => 4]);

        // A shared table is read-only, but a company adds its own value to it.
        $this->as($this->owner)->patchJson('/api/v1/access/lookups/'.$shift->id, ['name' => 'Shifts'])->assertForbidden();
        $night = $this->as($this->owner)->postJson('/api/v1/access/lookups/'.$shift->id.'/values', ['code' => 'night', 'name' => 'Night'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'NIGHT')
            ->json('data.id');
        // A shared value changed or deleted: Delta gets its own copy of the table (every value, its own NIGHT moved in).
        [$shared, $gone] = $shift->values()->whereNull('company_id')->orderBy('code')->take(2)->get()->all();
        $this->as($this->owner)->patchJson('/api/v1/access/lookups/'.$shift->id.'/values/'.$shared->id, ['name' => 'Morning (Delta)'])->assertOk()
            ->assertJsonPath('data.is_system', false);
        $this->as($this->owner)->deleteJson('/api/v1/access/lookups/'.$shift->id.'/values/'.$gone->id)->assertNoContent();
        $this->as($this->owner)->postJson('/api/v1/access/lookups/'.$shift->id.'/values', ['code' => 'NIGHT', 'name' => 'Again'])->assertUnprocessable();
        $deltaList = collect($this->as($this->owner)->getJson('/api/v1/access/lookups')->json('data'))->where('code', 'SHIFT')->values();
        $this->assertCount(1, $deltaList);
        $this->assertTrue($deltaList[0]['customised']);
        $this->assertFalse($deltaList[0]['is_system']);
        $deltaValues = collect($this->as($this->owner)->getJson('/api/v1/access/lookups/'.$shift->id.'/values')->json('data.values'));
        $this->assertSame('Morning (Delta)', $deltaValues->firstWhere('code', $shared->code)['name']);
        $this->assertNull($deltaValues->firstWhere('code', $gone->code));
        $this->assertNotNull($deltaValues->firstWhere('code', 'NIGHT'));
        $this->assertSame($shared->name, $shared->fresh()->name);

        // Another company keeps the shared list as it was, without Delta's.
        $gammaCodes = collect($this->as($this->gammaOwner, $this->gamma)->getJson('/api/v1/access/lookups/'.$shift->id.'/values')->json('data.values'))->pluck('code');
        $this->assertNotContains('NIGHT', $gammaCodes->all());
        $this->assertCount(4, $gammaCodes);
        $this->as($this->gammaOwner, $this->gamma)->patchJson('/api/v1/access/lookups/'.$shift->id.'/values/'.$night, ['name' => 'Mine'])->assertNotFound();

        // Its own table: created, unique by code, invisible to others.
        $own = $this->as($this->owner)->postJson('/api/v1/access/lookups', ['code' => 'defect-zone', 'name' => 'Defect Zone'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'DEFECT-ZONE')
            ->json('data.id');
        $this->as($this->owner)->postJson('/api/v1/access/lookups', ['code' => 'SHIFT', 'name' => 'Clash'])->assertUnprocessable();
        $this->as($this->gammaOwner, $this->gamma)->getJson('/api/v1/access/lookups/'.$own.'/values')->assertNotFound();
        $this->as($this->owner)->deleteJson('/api/v1/access/lookups/'.$own)->assertNoContent();
    }

    public function test_a_parameter_records_who_changed_it_and_resets_to_its_default(): void
    {
        $this->as($this->owner)->putJson('/api/v1/settings/inventory.count_stale_days', ['value' => 21])->assertOk();

        $row = collect($this->as($this->owner)->getJson('/api/v1/settings')->json('data'))->firstWhere('key', 'inventory.count_stale_days');
        $this->assertSame(21, $row['value']);
        $this->assertSame('COMPANY', $row['level']);
        $this->assertSame($this->owner->name, $row['updated_by']);
        $this->assertNotNull($row['updated_at']);

        $this->as($this->owner)->deleteJson('/api/v1/settings/inventory.count_stale_days')->assertNoContent();
        $row = collect($this->as($this->owner)->getJson('/api/v1/settings')->json('data'))->firstWhere('key', 'inventory.count_stale_days');
        $this->assertSame('PLATFORM', $row['level']);
        $this->assertNull($row['updated_by']);
    }

    public function test_category_permissions_restrict_a_role_to_its_categories_and_their_children(): void
    {
        $fabric = ItemCategory::create(['company_id' => $this->delta->id, 'name' => 'Fabric', 'code' => 'FAB', 'active' => true]);
        $knit = ItemCategory::create(['company_id' => $this->delta->id, 'parent_id' => $fabric->id, 'name' => 'Knit', 'code' => 'FAB-KNIT', 'active' => true]);
        $trims = ItemCategory::create(['company_id' => $this->delta->id, 'name' => 'Trims', 'code' => 'TRM', 'active' => true]);

        $role = Role::where('name', 'STOREKEEPER')->whereNull('company_id')->firstOrFail();
        $factory = $this->delta->factories()->firstOrFail();
        $keeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test', $factory->id);

        TenantFixture::actingAsTenant($this->delta);
        $access = app(CategoryAccess::class);
        $this->assertNull($access->allowedCategoryIds($keeper, 'create'), 'No rows: not restricted by category.');

        $this->as($this->owner)->putJson('/api/v1/access/roles/'.$role->id.'/category-permissions', ['permissions' => [
            ['item_category_id' => $fabric->id, 'can_create' => true],
            ['item_category_id' => $trims->id, 'can_view' => false],
        ]])
            ->assertOk()
            ->assertJsonPath('data.restricted', true);

        $rows = collect($this->as($this->owner)->getJson('/api/v1/access/roles/'.$role->id.'/category-permissions')->json('data.categories'))->keyBy('code');
        // Create implies View; an all-clear row is no row.
        $this->assertTrue($rows['FAB']['can_view']);
        $this->assertTrue($rows['FAB']['can_create']);
        $this->assertFalse($rows['TRM']['can_view']);
        // Sub-categories sit under their parent.
        $this->assertSame(2, $rows['FAB-KNIT']['depth']);

        TenantFixture::actingAsTenant($this->delta);
        $access = app(CategoryAccess::class);
        $this->assertEqualsCanonicalizing([$fabric->id, $knit->id], $access->allowedCategoryIds($keeper, 'create'));
        $this->assertFalse($access->allows($keeper, $trims->id, 'view'));
        $this->assertSame([], $access->allowedCategoryIds($keeper, 'approve'));

        // Another company's category cannot be granted.
        TenantFixture::actingAsTenant($this->gamma);
        $foreign = ItemCategory::create(['company_id' => $this->gamma->id, 'name' => 'Yarn', 'code' => 'YRN', 'active' => true]);
        $this->as($this->owner)->putJson('/api/v1/access/roles/'.$role->id.'/category-permissions', ['permissions' => [
            ['item_category_id' => $foreign->id, 'can_view' => true],
        ]])->assertUnprocessable();
    }

    public function test_the_sidebar_has_brickland_sections_folders_and_counts(): void
    {
        $modules = collect($this->as($this->owner)->getJson('/api/v1/access/navigation')->assertOk()->json('data'))->keyBy('code');

        $this->assertSame('SYSTEM_ADMINISTRATION', $modules['ACCESS']['section']);
        $this->assertSame('HUMAN_RESOURCE', $modules['COMPANY']['section']);
        $this->assertSame(13, $modules['COMPANY']['count']); // Grade moved to HR & Payroll → Pay Grade (2026-11-15)
        $entries = collect($modules['COMPANY']['entries']);
        $this->assertSame(['COMPANY.HOME', 'COMPANY.COMPANY_PROFILE', 'COMPANY.FACTORY_SETUP', 'COMPANY.ORGANIZATION', 'COMPANY.MACHINE_MANAGEMENT', 'COMPANY.DESIGNATION_GRADE', 'COMPANY.COMPANY_DOCUMENTS'], $entries->pluck('code')->all());
        $this->assertSame(['COMPANY.FACTORY', 'COMPANY.BUILDING', 'COMPANY.FLOOR'], collect($entries->firstWhere('code', 'COMPANY.FACTORY_SETUP')['items'])->pluck('code')->all());
        $this->assertSame(['ACCESS.STRUCTURE', 'ACCESS.ACCESS_CONTROL', 'ACCESS.MAPPINGS', 'ACCESS.CONFIGURATION', 'ACCESS.OPERATIONS'], collect($modules['ACCESS']['entries'])->pluck('code')->all());

        // Sections come in Brickland's order; Overview opens with Chat, and Machinery is last.
        $sections = $modules->pluck('section')->unique()->values()->all();
        $this->assertSame(['OVERVIEW', 'SYSTEM_ADMINISTRATION', 'HUMAN_RESOURCE', 'SALES_MERCHANDISING'], array_slice($sections, 0, 4));
        $this->assertSame('MAINTENANCE', end($sections));
    }

    public function test_one_menu_grant_keeps_a_permission_another_menu_still_needs(): void
    {
        $role = Role::create([
            'company_id' => $this->delta->id, 'name' => 'STRUCT', 'guard_name' => 'web', 'description' => 'Structure keeper',
            'scope' => 'COMPANY', 'is_system' => false,
        ]);
        $lookup = Menu::where('code', 'ACCESS.LOOKUP_TABLE')->firstOrFail();
        $building = Menu::where('code', 'COMPANY.BUILDING')->firstOrFail();

        // Both screens open on masterdata.manage.
        $this->as($this->owner)->putJson("/api/v1/access/roles/{$role->id}/menu-grants/{$building->id}", ['flags' => ['V']])->assertOk();
        $this->as($this->owner)->putJson("/api/v1/access/roles/{$role->id}/menu-grants/{$lookup->id}", ['flags' => ['V']])->assertOk();
        $this->assertTrue($role->fresh()->hasPermissionTo('masterdata.manage'));

        // Clearing Lookup Table alone must not take Building away.
        $this->as($this->owner)->putJson("/api/v1/access/roles/{$role->id}/menu-grants/{$lookup->id}", ['flags' => []])->assertOk();
        $this->assertTrue($role->fresh()->hasPermissionTo('masterdata.manage'));

        $records = collect($this->as($this->owner)->getJson('/api/v1/access/menu-grants')->assertOk()->json('data'))->where('role_id', $role->id);
        $this->assertContains('Building', $records->pluck('menu')->all());

        // A system role is refused here too.
        $system = Role::where('name', 'STORE_MANAGER')->whereNull('company_id')->firstOrFail();
        $this->as($this->owner)->putJson("/api/v1/access/roles/{$system->id}/menu-grants/{$lookup->id}", ['flags' => ['V']])->assertForbidden();
    }

    public function test_category_permission_records_and_single_row_saves(): void
    {
        $fabric = ItemCategory::create(['company_id' => $this->delta->id, 'name' => 'Fabric', 'code' => 'FAB', 'active' => true]);
        $role = Role::where('name', 'STOREKEEPER')->whereNull('company_id')->firstOrFail();

        $this->as($this->owner)->putJson("/api/v1/access/roles/{$role->id}/category-permissions/{$fabric->id}", ['can_edit' => true])->assertOk();
        $this->as($this->owner)->getJson('/api/v1/access/category-permissions')
            ->assertOk()
            ->assertJsonFragment(['category' => 'Fabric', 'can_view' => true, 'can_edit' => true, 'can_approve' => false]);

        // All clear removes the row.
        $this->as($this->owner)->putJson("/api/v1/access/roles/{$role->id}/category-permissions/{$fabric->id}", [])->assertOk();
        $this->assertSame([], $this->as($this->owner)->getJson('/api/v1/access/category-permissions')->json('data'));

        // Another company's category does not resolve.
        TenantFixture::actingAsTenant($this->gamma);
        $foreign = ItemCategory::create(['company_id' => $this->gamma->id, 'name' => 'Yarn', 'code' => 'YRN', 'active' => true]);
        $this->as($this->owner)->putJson("/api/v1/access/roles/{$role->id}/category-permissions/{$foreign->id}", ['can_view' => true])->assertNotFound();
    }

    public function test_company_dashboard_counts_its_own_structure(): void
    {
        $this->as($this->owner)->getJson('/api/v1/company/dashboard')
            ->assertOk()
            ->assertJsonPath('data.employees.total', 1)
            ->assertJsonPath('data.buildings', 0);
        $this->as(TenantFixture::user($this->delta, 'TECHNICIAN', 'tech-dash@delta.test', $this->delta->factories()->firstOrFail()->id))
            ->getJson('/api/v1/company/dashboard')->assertForbidden();
    }

    public function test_configuration_is_closed_to_roles_that_do_not_configure(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test', $this->delta->factories()->firstOrFail()->id);

        $this->as($technician)->getJson('/api/v1/access/modules')->assertForbidden();
        $this->as($technician)->getJson('/api/v1/access/lookups')->assertForbidden();
        $role = Role::where('name', 'TECHNICIAN')->whereNull('company_id')->firstOrFail();
        $this->as($technician)->getJson('/api/v1/access/roles/'.$role->id.'/category-permissions')->assertForbidden();
    }

    private function as(User $user, ?Company $company = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, ($company ?? $this->delta)->id, 'Test token');

        return $this->withToken($plain);
    }
}
