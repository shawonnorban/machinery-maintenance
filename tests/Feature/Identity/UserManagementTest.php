<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Identity\Services\PermissionResolver;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Adding people to a company and deciding what they may do (SRS 5).
 *
 * Two rules carry most of the weight here. A user account is not owned by a
 * company, so removing somebody ends their membership and leaves the account
 * and their signed-off work alone. And nobody may take away the last ability
 * to manage users — a company that locks itself out has no way back except
 * support.
 *
 * Ported from the Blade `/app/settings/users` screen (Phase D/F, docs/12-
 * Stack-Migration-Implementation-Plan.md — that screen is gone) onto
 * `Api/UserApiController`, the same `ManageCompanyUser` action underneath
 * either way (ADR-003). `resetPassword`/`destroy` didn't have an API route
 * at all until this file needed one — added alongside this test, mirroring
 * the web controller's own `resetPassword`/`destroy` exactly.
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private User $owner;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        $this->token = $this->tokenFor($this->owner);
    }

    private function tokenFor(User $user): string
    {
        return app(IssueApiToken::class)->forUser($user, $this->delta->id, 'Test')['plain'];
    }

    private function as(User $user): self
    {
        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user));

        return $this;
    }

    private function role(string $code): Role
    {
        return Role::whereNull('company_id')->where('name', $code)->firstOrFail();
    }

    public function test_a_person_can_be_added_and_their_password_is_shown_once(): void
    {
        $response = $this->as($this->owner)->postJson('/api/v1/users', [
            'name' => 'Karim Mia',
            'email' => 'karim@delta.test',
            'roles' => [$this->role('TECHNICIAN')->id],
            'factory_id' => $this->dhaka->id,
        ])->assertCreated();

        $password = $response->json('data.password');
        $this->assertIsString($password);

        $user = User::where('email', 'karim@delta.test')->firstOrFail();

        // The password works, which is the only thing that makes handing it
        // over meaningful.
        $this->assertTrue(Hash::check($password, $user->password));

        $assignment = UserRole::where('user_id', $user->id)->firstOrFail();

        // A factory-scoped role is pinned to the chosen factory.
        $this->assertSame($this->dhaka->id, $assignment->factory_id);
    }

    public function test_a_platform_role_cannot_be_handed_out_by_a_tenant(): void
    {
        $platformRole = Role::whereNull('company_id')->where('name', 'PLATFORM_SUPER_ADMIN')->firstOrFail();

        $this->as($this->owner)->postJson('/api/v1/users', [
            'name' => 'Would-be admin',
            'email' => 'sneaky@delta.test',
            'roles' => [$platformRole->id],
        ])->assertStatus(422)->assertJsonValidationErrors('roles');

        $this->assertNull(User::where('email', 'sneaky@delta.test')->first());
    }

    public function test_an_existing_account_joins_rather_than_being_duplicated(): void
    {
        $other = TenantFixture::company('Beta Textiles Ltd', 'BTL');
        $shared = TenantFixture::user($other, 'COMPANY_OWNER', 'shared@group.test');

        TenantFixture::actingAsTenant($this->delta);

        $response = $this->as($this->owner)->postJson('/api/v1/users', [
            'name' => 'Shared Person',
            'email' => 'shared@group.test',
            'roles' => [$this->role('AUDITOR')->id],
        ])->assertCreated();

        // One account, two memberships: the same person moving between two
        // companies in a group keeps one set of credentials.
        $this->assertSame(1, User::where('email', 'shared@group.test')->count());
        $this->assertSame(
            2,
            CompanyUser::withoutGlobalScopes()->where('user_id', $shared->id)->count(),
        );

        // And no new password was issued for an account that already has one.
        $this->assertNull($response->json('data.password'));
    }

    public function test_somebody_already_in_this_company_cannot_be_added_twice(): void
    {
        $this->as($this->owner)->postJson('/api/v1/users', [
            'name' => 'Owner again',
            'email' => $this->owner->email,
            'roles' => [$this->role('AUDITOR')->id],
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_roles_can_be_changed(): void
    {
        $person = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test', factoryId: $this->dhaka->id);
        TenantFixture::actingAsTenant($this->delta);

        $this->as($this->owner)->patchJson('/api/v1/users/'.$person->id, [
            'name' => 'Karim Mia',
            'roles' => [$this->role('MAINTENANCE_ENGINEER')->id],
            'factory_id' => $this->dhaka->id,
        ])->assertOk();

        $assignments = UserRole::where('user_id', $person->id)->get();

        $this->assertCount(1, $assignments);
        $this->assertSame($this->role('MAINTENANCE_ENGINEER')->id, $assignments->first()->role_id);
        $this->assertSame('Karim Mia', $person->fresh()->name);
    }

    /**
     * The rule that stops a company locking itself out.
     */
    public function test_the_last_administrator_cannot_be_removed_or_demoted(): void
    {
        $second = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test', factoryId: $this->dhaka->id);
        TenantFixture::actingAsTenant($this->delta);

        // The technician can remove nobody's administration because they have
        // none, so the owner is the only keyholder.
        $this->as($second)->deleteJson('/api/v1/users/'.$this->owner->id)->assertForbidden();

        // And the owner cannot demote themselves either.
        $this->as($this->owner)->patchJson('/api/v1/users/'.$this->owner->id, [
            'name' => $this->owner->name,
            'roles' => [$this->role('TECHNICIAN')->id],
            'factory_id' => $this->dhaka->id,
        ])->assertStatus(422)->assertJsonValidationErrors('roles');

        $this->assertTrue(
            app(PermissionResolver::class)->has($this->owner->fresh(), $this->delta->id, 'admin.user.manage'),
        );
    }

    public function test_administration_can_be_handed_over_and_then_given_up(): void
    {
        $successor = TenantFixture::user($this->delta, 'TECHNICIAN', 'successor@delta.test', factoryId: $this->dhaka->id);
        TenantFixture::actingAsTenant($this->delta);

        // Make somebody else an administrator first...
        $this->as($this->owner)->patchJson('/api/v1/users/'.$successor->id, [
            'name' => 'Successor',
            'roles' => [$this->role('COMPANY_ADMIN')->id],
        ])->assertOk();

        app(PermissionResolver::class)->flush();

        // ...and only then may the outgoing one step down.
        $this->as($this->owner)->patchJson('/api/v1/users/'.$this->owner->id, [
            'name' => $this->owner->name,
            'roles' => [$this->role('VIEWER')->id],
            'factory_id' => $this->dhaka->id,
        ])->assertOk();

        $this->assertSame(
            $this->role('VIEWER')->id,
            UserRole::withoutGlobalScopes()->where('user_id', $this->owner->id)->firstOrFail()->role_id,
        );
    }

    public function test_nobody_can_suspend_or_remove_themselves(): void
    {
        $this->as($this->owner)->postJson('/api/v1/users/'.$this->owner->id.'/deactivate')
            ->assertStatus(422)->assertJsonValidationErrors('user');

        $this->as($this->owner)->deleteJson('/api/v1/users/'.$this->owner->id)
            ->assertStatus(422)->assertJsonValidationErrors('user');
    }

    public function test_removing_somebody_ends_the_membership_and_leaves_the_account(): void
    {
        $person = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test', factoryId: $this->dhaka->id);
        TenantFixture::actingAsTenant($this->delta);

        $this->as($this->owner)->deleteJson('/api/v1/users/'.$person->id)->assertNoContent();

        // The account survives: a work order this person closed still names
        // them, and they may work for another company in the group.
        $this->assertNotNull(User::find($person->id));
        $this->assertSame(0, CompanyUser::withoutGlobalScopes()->where('user_id', $person->id)->count());
        $this->assertSame(0, UserRole::withoutGlobalScopes()->where('user_id', $person->id)->count());
    }

    public function test_a_suspended_member_keeps_their_account(): void
    {
        $person = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test', factoryId: $this->dhaka->id);
        TenantFixture::actingAsTenant($this->delta);

        $this->as($this->owner)->postJson('/api/v1/users/'.$person->id.'/deactivate')->assertOk();

        $this->assertSame(
            'SUSPENDED',
            CompanyUser::withoutGlobalScopes()->where('user_id', $person->id)->firstOrFail()->status,
        );
        $this->assertSame('ACTIVE', $person->fresh()->status);
    }

    public function test_a_password_can_be_reissued(): void
    {
        $person = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test', factoryId: $this->dhaka->id);
        TenantFixture::actingAsTenant($this->delta);

        $before = $person->password;

        $response = $this->as($this->owner)
            ->postJson('/api/v1/users/'.$person->id.'/reset-password')
            ->assertOk();

        $password = $response->json('data.password');
        $this->assertIsString($password);
        $this->assertNotSame($before, $person->fresh()->password);
        $this->assertTrue(Hash::check($password, $person->fresh()->password));
    }

    public function test_another_companys_user_is_not_reachable(): void
    {
        $other = TenantFixture::company('Beta Textiles Ltd', 'BTL');
        $theirs = TenantFixture::user($other, 'COMPANY_OWNER', 'theirs@btl.test');

        TenantFixture::actingAsTenant($this->delta);

        // 404 rather than 403: whether that account exists is none of this
        // company's business.
        $this->as($this->owner)->getJson('/api/v1/users/'.$theirs->id)->assertNotFound();
        $this->as($this->owner)->deleteJson('/api/v1/users/'.$theirs->id)->assertNotFound();
    }

    public function test_the_endpoints_are_closed_to_roles_that_do_not_administer(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test', factoryId: $this->dhaka->id);
        TenantFixture::actingAsTenant($this->delta);

        $this->as($technician)->getJson('/api/v1/users')->assertForbidden();
        $this->as($technician)->getJson('/api/v1/roles')->assertForbidden();
        $this->as($technician)
            ->postJson('/api/v1/users', ['name' => 'X', 'email' => 'x@delta.test', 'roles' => []])
            ->assertForbidden();
    }
}
