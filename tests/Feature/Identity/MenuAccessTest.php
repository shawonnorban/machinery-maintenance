<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Actions\SyncMenus;
use App\Modules\Identity\Support\MenuRegistry;
use App\Modules\Identity\Models\Menu;
use App\Modules\Identity\Models\MenuPermission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Access Control's menu tree (AnnotechERP 02/01): the sidebar comes from the
 * menus a caller may open, and a role's V/C/E/D/A/P/X grants are permissions
 * underneath — so every existing permission check keeps working.
 */
class MenuAccessTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    public function test_every_permission_is_reachable_from_some_menu_and_sync_is_repeatable(): void
    {
        $first = app(SyncMenus::class)->handle();
        $second = app(SyncMenus::class)->handle();

        $this->assertSame($first, $second);

        // The old project's retired modules keep their permissions but no menu.
        $retired = fn (string $name): bool => \Illuminate\Support\Str::startsWith($name, MenuRegistry::RETIRED_PERMISSIONS);
        $unmapped = Permission::query()->whereNotIn('name', MenuPermission::query()->select('permission_name'))->pluck('name')->reject($retired)->values();
        $this->assertSame([], $unmapped->all(), 'A permission no menu names cannot be granted from the matrix.');
    }

    public function test_no_screen_shares_its_code_with_a_folder(): void
    {
        // A shared code makes the sync turn the folder row into the screen (or back),
        // and the module's screens drop out of the sidebar and its "NN of MM" count.
        $folders = array_keys(MenuRegistry::folders());
        $menus = array_column(MenuRegistry::menus(), 0);

        $this->assertSame([], array_values(array_intersect($menus, $folders)));
        $this->assertSame(count($menus), count(array_unique($menus)));
    }

    public function test_the_sidebar_offers_only_what_the_caller_may_open(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');

        $codes = fn (User $user): array => collect($this->withToken($this->tokenFor($user))->getJson('/api/v1/access/navigation')->assertOk()->json('data'))
            ->flatMap(fn (array $module) => collect($module['entries'])->flatMap(fn (array $entry) => $entry['type'] === 'folder' ? collect($entry['items'])->pluck('code') : [$entry['code']]))
            ->all();

        $ownerSees = $codes($this->owner);
        $techSees = $codes($technician);

        $this->assertContains('COMPANY.HOME', $ownerSees);
        $this->assertContains('MACHINERY.MAINT.WORK_ORDERS', $techSees);
        $this->assertNotContains('COMPANY.HOME', $techSees);
        $this->assertNotContains('ACCESS.HOME', $techSees);
        // Every Brickland screen is its own sidebar link; retired screens are not.
        $this->assertContains('COMPANY.BUILDING', $ownerSees);
        $this->assertNotContains('ACCESS.USERS', $ownerSees);

        // A hidden menu leaves everyone's sidebar.
        Menu::where('code', 'COMPANY.HOME')->update(['active' => false]);
        $this->assertNotContains('COMPANY.HOME', $codes($this->owner));
    }

    public function test_grants_become_permissions_and_leave_unmapped_ones_alone(): void
    {
        $role = Role::create([
            'company_id' => $this->delta->id, 'name' => 'MERCHANT', 'guard_name' => 'web', 'description' => 'Merchandiser',
            'scope' => 'COMPANY', 'is_system' => false,
        ]);
        // Something outside the matrix the role already holds.
        Permission::firstOrCreate(['name' => 'zz.outside.view', 'guard_name' => 'web']);
        $role->givePermissionTo('zz.outside.view');

        $this->withToken($this->tokenFor($this->owner))
            ->putJson('/api/v1/access/roles/'.$role->id.'/menu-grants', [
                'grants' => ['MACHINERY.MAINT.WORK_ORDERS' => ['V', 'C'], 'MACHINERY.PARTS.PARTS' => ['V']],
            ])
            ->assertOk()
            ->assertJsonPath('data.role.code', 'MERCHANT');

        $held = $role->fresh()->permissions->pluck('name')->all();
        $this->assertContains('work_order.work_order.view_any', $held);
        $this->assertContains('work_order.work_order.create', $held);
        $this->assertContains('inventory.part.view_any', $held);
        $this->assertNotContains('inventory.part.update', $held);
        $this->assertContains('zz.outside.view', $held);

        // The matrix reads back what was granted.
        $menus = collect($this->withToken($this->tokenFor($this->owner))->getJson('/api/v1/access/roles/'.$role->id.'/menu-grants')->json('data.menus'))->keyBy('code');
        $this->assertSame('all', $menus['MACHINERY.MAINT.WORK_ORDERS']['flags']['C']);
        $this->assertSame('none', $menus['MACHINERY.PARTS.STOCK']['flags']['V']);

        // Clearing the matrix removes the granted permissions, not the outside one.
        $this->withToken($this->tokenFor($this->owner))
            ->putJson('/api/v1/access/roles/'.$role->id.'/menu-grants', ['grants' => []])
            ->assertOk();
        $this->assertSame(['zz.outside.view'], $role->fresh()->permissions->pluck('name')->all());
    }

    public function test_a_system_role_is_refused_and_an_admin_role_holds_everything(): void
    {
        $system = Role::where('name', 'STORE_MANAGER')->whereNull('company_id')->firstOrFail();
        $this->withToken($this->tokenFor($this->owner))
            ->putJson('/api/v1/access/roles/'.$system->id.'/menu-grants', ['grants' => []])
            ->assertForbidden();

        $admin = $this->withToken($this->tokenFor($this->owner))
            ->postJson('/api/v1/roles', ['code' => 'ERP_ADMIN', 'name' => 'ERP Admin', 'short_name' => 'ADM', 'rank' => 1, 'is_admin' => true])
            ->assertCreated()
            ->assertJsonPath('data.is_admin', true)
            ->assertJsonPath('data.short_name', 'ADM')
            ->json('data.id');

        $this->assertSame(Permission::count(), Role::findOrFail($admin)->permissions()->count());
    }

    public function test_a_menu_is_renamed_and_hidden_but_not_its_url(): void
    {
        $menu = Menu::where('code', 'MACHINERY.MAINT.WORK_ORDERS')->firstOrFail();

        $this->withToken($this->tokenFor($this->owner))
            ->patchJson('/api/v1/access/menus/'.$menu->id, ['name' => 'Purchase Orders', 'name_bn' => 'ক্রয় আদেশ', 'sort_order' => 5, 'url' => '/elsewhere'])
            ->assertOk()
            ->assertJsonPath('data.name_bn', 'ক্রয় আদেশ')
            ->assertJsonPath('data.url', '/work-orders');

        // An admin's rename survives the next deploy's sync — it is this
        // company's override, never a write to the shared row.
        app(SyncMenus::class)->handle();
        $names = collect($this->withToken($this->tokenFor($this->owner))->getJson('/api/v1/access/menus')->json('data'))
            ->flatMap(fn (array $module) => $module['menus'])->pluck('name', 'code');
        $this->assertSame('Purchase Orders', $names['MACHINERY.MAINT.WORK_ORDERS']);
        $this->assertNotSame('Purchase Orders', $menu->fresh()->name);
    }

    public function test_menu_administration_is_closed_to_roles_that_do_not_configure(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech2@delta.test');

        $this->withToken($this->tokenFor($technician))->getJson('/api/v1/access/menus')->assertForbidden();
    }

    private function tokenFor(User $user): string
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $this->delta->id, 'Test token');

        return $plain;
    }
}
