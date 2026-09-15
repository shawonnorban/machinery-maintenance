<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Identity\Services\PermissionResolver;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Permission grants the ability; a policy restricts the instance. This covers
 * the first half (Handbook 2.6, SRS 55.1 rule 2).
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Company $rival;

    private Factory $dhaka;

    private Factory $gazipur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->rival = TenantFixture::company('Rival Garments Ltd', 'RGL');

        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        $this->gazipur = TenantFixture::factory($this->delta, 'Gazipur Unit 2', 'GAZ');
    }

    public function test_a_technician_holds_only_technician_permissions(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');

        $this->assertTrue(Gate::forUser($technician)->allows('work_order.work_order.complete'));
        // Reporting a breakdown is the Line Chief's job now (`RoleSeeder`) —
        // the floor supervisor standing at the machine, not the repair
        // technician.
        $this->assertFalse(Gate::forUser($technician)->allows('breakdown.breakdown.create'));

        // A technician must not create assets, approve, or touch billing.
        $this->assertFalse(Gate::forUser($technician)->allows('asset.asset.create'));
        $this->assertFalse(Gate::forUser($technician)->allows('billing.subscription.manage'));
        $this->assertFalse(Gate::forUser($technician)->allows('inventory.adjustment.create'));
    }

    // `test_the_ui_hides_controls_the_user_cannot_use` and `test_an_owner_
    // sees_the_controls_a_technician_does_not` lived here — proving a gate
    // by whether a Blade screen printed a button's own route(). Both the
    // screens and the mechanism are gone (Phase D/F): Next.js decides its
    // own control visibility from `/auth/permissions` rather than from
    // markup the server chose to print, so there's no server-rendered HTML
    // left for an equivalent assertion to inspect.

    public function test_a_factory_scoped_role_grants_only_that_factory(): void
    {
        $manager = TenantFixture::user(
            $this->delta,
            'MAINTENANCE_MANAGER',
            'dhk-manager@delta.test',
            factoryId: $this->dhaka->id,
        );

        $resolver = app(PermissionResolver::class);

        $this->assertTrue(
            $resolver->has($manager, $this->delta->id, 'work_order.work_order.close', $this->dhaka->id),
        );

        // The same permission must not reach the factory they were not given.
        $this->assertFalse(
            $resolver->has($manager, $this->delta->id, 'work_order.work_order.close', $this->gazipur->id),
        );
    }

    public function test_a_company_wide_role_reaches_every_factory(): void
    {
        $owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');

        $resolver = app(PermissionResolver::class);

        foreach ([$this->dhaka->id, $this->gazipur->id] as $factoryId) {
            $this->assertTrue(
                $resolver->has($owner, $this->delta->id, 'work_order.work_order.close', $factoryId),
            );
        }

        $this->assertEqualsCanonicalizing(
            [$this->dhaka->id, $this->gazipur->id],
            $resolver->accessibleFactoryIds($owner, $this->delta->id),
        );
    }

    public function test_permissions_do_not_carry_across_companies(): void
    {
        $owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');

        $resolver = app(PermissionResolver::class);

        // Full rights in their own company...
        $this->assertTrue(
            $resolver->has($owner, $this->delta->id, 'billing.subscription.manage'),
        );

        // ...and none at all in a company they do not belong to, even though
        // the role row would grant it if membership were not checked.
        $this->assertFalse(
            $resolver->has($owner, $this->rival->id, 'billing.subscription.manage'),
        );
        $this->assertSame([], $resolver->permissionsFor($owner, $this->rival->id));
    }

    public function test_an_inactive_user_holds_no_permissions(): void
    {
        $owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        app(TenantContext::class)->set($this->delta->id);

        $this->assertTrue(Gate::forUser($owner)->allows('asset.asset.create'));

        $owner->forceFill(['status' => 'INACTIVE'])->save();

        $this->assertFalse(Gate::forUser($owner->fresh())->allows('asset.asset.create'));
    }

    public function test_gates_deny_when_there_is_no_tenant_context(): void
    {
        $owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');

        app(TenantContext::class)->forget();

        // Fail closed. Without a company there is no company to be an owner of.
        $this->assertFalse(Gate::forUser($owner)->allows('asset.asset.create'));
    }

    public function test_an_undefined_permission_is_denied(): void
    {
        $owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        app(TenantContext::class)->set($this->delta->id);

        // A typo in a permission string must never pass.
        $this->assertFalse(Gate::forUser($owner)->allows('asset.asset.creat'));
        $this->assertFalse(Gate::forUser($owner)->allows('made.up.permission'));
    }
}
