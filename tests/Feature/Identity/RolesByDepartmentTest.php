<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Actions\SyncMenus;
use App\Modules\Identity\Database\Seeders\RoleSeeder;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Identity\Support\RoleBlueprint;
use App\Modules\Identity\Support\RoleProvisioner;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Scopes\TenantScope;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Roles by department and designation (user, 2026-11-22): each department's
 * Executive and Head hold only its modules; master data, company settings
 * and setup stay with the admins (their write permissions, their menus and
 * their typed-in addresses); every company works on its own editable copy of
 * the roles, which follows later template changes without losing its own;
 * and people are given the role of their HR department and designation.
 */
class RolesByDepartmentTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        app(SyncMenus::class)->handle();
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    public function test_department_roles_hold_their_modules_and_only_admins_keep_setup(): void
    {
        $m = RoleSeeder::matrix();
        foreach ($m as $code => $role) {
            if (! in_array($code, RoleBlueprint::ADMIN_ROLES, true) && is_array($role['permissions'])) {
                $this->assertSame([], array_values(array_filter($role['permissions'], fn ($p) => RoleBlueprint::adminOnly($p))), $code);
            }
        }
        foreach (RoleBlueprint::ADMIN_ROLES as $code) {
            $this->assertContains('merchandise.master.manage', $m[$code]['permissions']);
        }
        $merch = $m['MERCHANDISER']['permissions'];
        $head = $m['MERCHANDISING_HEAD']['permissions'];
        $this->assertContains('ordersummary.update', $merch);
        $this->assertContains('devsample.create', $merch);
        $this->assertContains('merchandise.master.view', $merch); // pick lists
        $this->assertNotContains('merchandise.master.manage', $merch);
        $this->assertNotContains('ordersummary.columns.manage', $merch);
        $this->assertNotContains('ordersummary.view_all', $merch);
        $this->assertNotContains('merchandise.style.approve', $merch);
        $this->assertNotContains('hrpayroll.employee.view', $merch);
        $this->assertContains('ordersummary.view_all', $head);
        $this->assertContains('merchandise.style.approve', $head);
        $this->assertContains('approval.request.approve', $head);
        $this->assertContains('exportstatement.approve', $m['COMMERCIAL_HEAD']['permissions']);
        $this->assertNotContains('exportstatement.approve', $m['COMMERCIAL_EXECUTIVE']['permissions']);
        $this->assertSame('Management (GM / Director)', $m['FACTORY_MANAGER']['name']);
        $this->assertNotContains('masterdata.manage', $m['FACTORY_MANAGER']['permissions']);
        $this->assertContains('hrpayroll.salary.approve', $m['FACTORY_MANAGER']['permissions']);
        $this->assertSame(['approval.ticket.create'], $m['EMPLOYEE']['permissions']);

        // HR's department and designation pick the role.
        $this->assertSame('MERCHANDISER', RoleBlueprint::roleFor('Merchandising Department', 'Sr. Merchandiser'));
        $this->assertSame('MERCHANDISING_HEAD', RoleBlueprint::roleFor('Merchandising Department', 'AGM'));
        $this->assertSame('COMMERCIAL_EXECUTIVE', RoleBlueprint::roleFor('Commercial Department', 'Jr.Executive'));
        $this->assertSame('ACCOUNTS_HEAD', RoleBlueprint::roleFor('Accounts & Finance', 'CFO'));
        $this->assertSame('STOREKEEPER', RoleBlueprint::roleFor('Fabric Store Department', 'Store Assistant'));
        $this->assertSame('LINE_CHIEF', RoleBlueprint::roleFor('Sewing / Production Department', 'Line Chief'));
        $this->assertSame('PRODUCTION_HEAD', RoleBlueprint::roleFor('Sewing / Production Department', 'GM (Production and Technical)'));
        $this->assertSame('EMPLOYEE', RoleBlueprint::roleFor('HR & Administration', 'Driver'));
        $this->assertSame('FACTORY_MANAGER', RoleBlueprint::roleFor('HR & Administration', 'Director (M & M)'));
        $this->assertNull(RoleBlueprint::roleFor(null, null));
    }

    public function test_a_merchandiser_sees_no_setup_screen_and_an_admin_does(): void
    {
        $merch = TenantFixture::user($this->delta, 'MERCHANDISER', 'merch@delta.test');
        $codes = fn (User $u): array => collect($this->api($u)->getJson('/api/v1/access/navigation')->assertOk()->json('data'))
            ->flatMap(fn (array $module) => collect($module['entries'])->flatMap(fn (array $e) => $e['type'] === 'folder' ? collect($e['items'])->pluck('code') : [$e['code']]))->all();

        $sees = $codes($merch);
        $this->assertContains('ORDER_SUMMARY.LIST', $sees);
        $this->assertContains('DEV_SAMPLE.LIST', $sees);
        $this->assertNotContains('ORDER_SUMMARY.COLUMNS', $sees);
        $this->assertNotContains('MERCHANDISE.TASK_ACTION', $sees);
        $this->assertNotContains('COMPANY.HOME', $sees);
        $this->assertNotContains('HR_PAYROLL.EMPLOYEE_LIST', $sees);
        $this->assertEmpty(array_filter($sees, fn ($c) => str_starts_with($c, 'SETTINGS.') || str_starts_with($c, 'ACCESS.')));
        $closed = $this->api($merch)->getJson('/api/v1/access/restricted-screens')->assertOk()->json('data');
        $this->assertContains('/order-summary/columns', $closed);
        $this->assertContains('/company-management', $closed);
        $this->assertNotContains('/order-summary/list', $closed);

        $ownerSees = $codes($this->owner);
        $this->assertContains('ORDER_SUMMARY.COLUMNS', $ownerSees);
        $this->assertContains('COMPANY.HOME', $ownerSees);
        $this->assertSame([], $this->api($this->owner)->getJson('/api/v1/access/restricted-screens')->assertOk()->json('data'));
    }

    public function test_each_company_edits_its_own_copy_and_template_changes_reach_it(): void
    {
        $merch = TenantFixture::user($this->delta, 'MERCHANDISER', 'merch@delta.test');
        $template = Role::query()->whereNull('company_id')->where('name', 'MERCHANDISER')->firstOrFail();
        // A seeded role is not editable as such.
        $this->api($this->owner)->patchJson("/api/v1/roles/{$template->id}", ['name' => 'X'])->assertForbidden();

        $r = RoleProvisioner::provision($this->delta->id);
        $this->assertGreaterThan(40, $r['created']);
        $copy = Role::query()->where('company_id', $this->delta->id)->where('name', 'MERCHANDISER')->firstOrFail();
        $this->assertFalse($copy->is_system);
        $this->assertSame($copy->id, UserRole::withoutGlobalScope(TenantScope::class)->where('user_id', $merch->id)->value('role_id'));
        $this->assertSame($template->permissions()->pluck('name')->sort()->values()->all(), $copy->permissions()->pluck('name')->sort()->values()->all());
        $this->assertTrue(Role::query()->where('company_id', $this->delta->id)->where('name', 'COMPANY_OWNER')->value('is_admin'));
        // The company lists only its copies, never the platform's own role.
        $listed = collect($this->api($this->owner)->getJson('/api/v1/roles?per_page=100')->assertOk()->json('data'));
        $this->assertSame(1, $listed->where('code', 'MERCHANDISER')->count());
        $this->assertSame($copy->id, $listed->firstWhere('code', 'MERCHANDISER')['id']);
        $this->assertNull($listed->firstWhere('code', 'PLATFORM_SUPER_ADMIN'));

        // Editable, and only this company's.
        $other = TenantFixture::company('Omega Textiles Ltd', 'OTL');
        RoleProvisioner::provision($other->id);
        $this->api($this->owner)->patchJson("/api/v1/roles/{$copy->id}", ['name' => 'Merchandiser (Knit)', 'permissions' => [...$copy->permissions()->pluck('name')->all(), 'hrpayroll.employee.view']])->assertOk();
        $this->assertSame('Merchandiser (Knit)', $copy->fresh()->description);
        $this->assertTrue($copy->fresh()->hasPermissionTo('hrpayroll.employee.view'));
        $otl = Role::query()->where('company_id', $other->id)->where('name', 'MERCHANDISER')->firstOrFail();
        $this->assertSame('Merchandiser', $otl->description);
        $this->assertFalse($otl->hasPermissionTo('hrpayroll.employee.view'));

        // The template later loses one permission and gains another: both reach the copy; the company's own addition stays.
        $template->revokePermissionTo('devsample.create');
        $template->givePermissionTo('knitting.report.view');
        $this->seed(RoleSeeder::class); // the seeder restores the template, then syncs every copy
        $template->revokePermissionTo('devsample.create');
        $template->givePermissionTo('knitting.report.view');
        RoleProvisioner::provision($this->delta->id);
        $copy->refresh();
        $this->assertFalse($copy->hasPermissionTo('devsample.create'));
        $this->assertTrue($copy->hasPermissionTo('knitting.report.view'));
        $this->assertTrue($copy->hasPermissionTo('hrpayroll.employee.view'));
        $this->assertSame('Merchandiser (Knit)', $copy->description);
        $this->assertGreaterThan(0, Permission::query()->count());
    }

    public function test_people_get_the_role_of_their_department_and_designation(): void
    {
        $factory = DB::table('factories')->where('company_id', $this->delta->id)->value('id');
        $dept = strtolower((string) Str::ulid());
        DB::table('departments')->insert(['id' => $dept, 'company_id' => $this->delta->id, 'factory_id' => $factory, 'name' => 'Commercial Department', 'code' => 'COM', 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);
        $viewer = TenantFixture::user($this->delta, 'VIEWER', 'commercial@delta.test', $factory);
        $tech = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test', $factory);
        DB::table('users')->whereIn('id', [$viewer->id, $tech->id])->update(['department_id' => $dept]);

        $this->artisan('access:roles-by-department', ['company' => 'DAL', '--dry-run' => true])->assertSuccessful();
        $this->assertSame('VIEWER', $this->roleOf($viewer));

        $this->artisan('access:roles-by-department', ['company' => 'DAL'])->assertSuccessful();
        $this->assertSame('COMMERCIAL_EXECUTIVE', $this->roleOf($viewer));
        $this->assertSame('TECHNICIAN', $this->roleOf($tech)); // a particular role is kept
        $this->assertSame('COMPANY_OWNER', $this->roleOf($this->owner));
        $this->assertSame(0, UserRole::withoutGlobalScope(TenantScope::class)->where('company_id', $this->delta->id)
            ->whereIn('role_id', Role::query()->whereNull('company_id')->pluck('id'))->count());
        $this->api($viewer)->getJson('/api/v1/export-statement/statements')->assertOk();
    }

    private function roleOf(User $user): ?string
    {
        return DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')->where('user_roles.user_id', $user->id)->value('roles.name');
    }

    private function api(User $user): static
    {
        $this->flushHeaders();
        app(\App\Modules\Identity\Services\PermissionResolver::class)->flush();
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
