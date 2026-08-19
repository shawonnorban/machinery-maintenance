<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Identity\Database\Seeders\PermissionSeeder;
use App\Modules\Identity\Database\Seeders\RoleSeeder;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use Database\Seeders\DatabaseSeeder;
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

        $this->seed(DatabaseSeeder::class);
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
            .implode(', ', $orphans),
        );
    }

    /**
     * Permissions that are administrative on purpose.
     *
     * Each of these belongs to whoever owns the account rather than to anyone
     * running a factory: billing is the account holder's, and integration
     * credentials and company-wide numbering are configuration, not daily work.
     * Regenerating a QR token invalidates a printed label, which is a security
     * action rather than a reprint.
     *
     * The list is explicit so that adding a permission here is a decision
     * somebody made, not something that happened by omission.
     *
     * @var list<string>
     */
    private const DELIBERATELY_ADMINISTRATIVE = [
        'asset.qr.regenerate',
        'admin.api_client.manage',
        'settings.company.manage',
        'settings.numbering.manage',
        'billing.subscription.manage',
        'billing.payment.manage',
        'webhook.endpoint.manage',
    ];

    /**
     * The previous test passes as long as SOME role holds a permission, and the
     * owner holds every one — so a permission reaching nobody who actually does
     * the job still looks granted. That masked two real gaps already:
     * maintenance.schedule.skip, which stopped a maintenance manager running
     * their own schedule, and cost.entry.reverse, which no operational role
     * could use at all. This checks the roles that do the work.
     */
    public function test_every_permission_reaches_a_role_that_is_not_the_owner(): void
    {
        $reachable = Role::query()
            ->whereNull('company_id')
            ->whereNotIn('code', ['COMPANY_OWNER', 'COMPANY_ADMIN', 'PLATFORM_SUPER_ADMIN'])
            ->with('permissions')
            ->get()
            ->flatMap(fn (Role $role) => $role->permissions->pluck('code'))
            ->unique()
            ->all();

        $unreachable = array_values(array_diff(
            PermissionSeeder::allCodes(),
            $reachable,
            self::DELIBERATELY_ADMINISTRATIVE,
        ));

        $this->assertSame(
            [],
            $unreachable,
            'These permissions reach only the owner or admin, so nobody who does the job can hold them. '
            .'Either grant one to an operational role, or add it to DELIBERATELY_ADMINISTRATIVE with a reason: '
            .implode(', ', $unreachable),
        );
    }

    public function test_the_administrative_allowlist_holds_no_stale_entries(): void
    {
        // An entry that is now granted operationally, or no longer exists, is a
        // stale exemption quietly weakening the check above.
        $reachable = Role::query()
            ->whereNull('company_id')
            ->whereNotIn('code', ['COMPANY_OWNER', 'COMPANY_ADMIN', 'PLATFORM_SUPER_ADMIN'])
            ->with('permissions')
            ->get()
            ->flatMap(fn (Role $role) => $role->permissions->pluck('code'))
            ->unique()
            ->all();

        $stale = array_values(array_intersect(self::DELIBERATELY_ADMINISTRATIVE, $reachable));
        $missing = array_values(array_diff(self::DELIBERATELY_ADMINISTRATIVE, PermissionSeeder::allCodes()));

        $this->assertSame([], $stale, 'Now granted operationally, so the exemption is stale: '.implode(', ', $stale));
        $this->assertSame([], $missing, 'No longer a real permission: '.implode(', ', $missing));
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
                "{$code} is a read-only role but holds write permissions: ".implode(', ', $writes),
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
                "Role {$code} references permissions that do not exist: ".implode(', ', $unknown),
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
            'Platform staff must reach tenant data only through an audited support grant (SRS 5.4).',
        );
    }
}
