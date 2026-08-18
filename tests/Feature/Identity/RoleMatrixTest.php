<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Identity\Database\Seeders\PermissionSeeder;
use App\Modules\Identity\Database\Seeders\RoleSeeder;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Enforces the three seed rules from Seed Catalog Section 13. These fail the
 * build rather than being checked by hand.
 */
class RoleMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_every_permission_is_granted_to_at_least_one_role(): void
    {
        $granted = Permission::query()
            ->whereHas('roles')
            ->pluck('code')
            ->all();

        $orphans = array_diff(PermissionSeeder::allCodes(), $granted);

        $this->assertSame(
            [],
            array_values($orphans),
            'These permissions are granted to no seeded role, so no user can ever hold them: '
            .implode(', ', $orphans)
        );
    }

    public function test_viewer_and_auditor_hold_no_write_permission(): void
    {
        foreach (['VIEWER', 'AUDITOR'] as $code) {
            $role = Role::where('code', $code)->whereNull('company_id')->firstOrFail();

            $writes = $role->permissions
                ->pluck('code')
                ->filter(function (string $permission): bool {
                    $action = substr($permission, strrpos($permission, '.') + 1);

                    return in_array($action, RoleSeeder::WRITE_ACTIONS, true);
                })
                ->values()
                ->all();

            $this->assertSame(
                [],
                $writes,
                "{$code} is a read-only role but holds write permissions: ".implode(', ', $writes)
            );
        }
    }

    public function test_no_role_references_a_permission_outside_the_catalog(): void
    {
        $catalog = PermissionSeeder::allCodes();

        foreach (RoleSeeder::matrix() as $code => $definition) {
            $unknown = array_diff($definition['permissions'], $catalog);

            $this->assertSame(
                [],
                array_values($unknown),
                "Role {$code} references permissions that do not exist: ".implode(', ', $unknown)
            );
        }
    }

    public function test_seeded_roles_are_system_roles_and_not_tenant_owned(): void
    {
        $roles = Role::whereNull('company_id')->get();

        $this->assertCount(12, $roles, 'Expected the 12 roles defined in SRS 5.');

        foreach ($roles as $role) {
            $this->assertTrue($role->is_system, "{$role->code} must be a system role.");
            $this->assertFalse($role->isEditable(), "{$role->code} must not be editable.");
        }
    }

    public function test_platform_super_admin_has_no_tenant_data_access_by_default(): void
    {
        $role = Role::where('code', 'PLATFORM_SUPER_ADMIN')->firstOrFail();

        $this->assertCount(
            0,
            $role->permissions,
            'Platform staff must reach tenant data only through an audited support grant (SRS 5.4).'
        );
    }
}
