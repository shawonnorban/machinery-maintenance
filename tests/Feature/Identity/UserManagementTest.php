<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

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
 * The screens that make the product self-service: until they existed a tenant
 * could not add its second user, which meant every account came from a seeder.
 *
 * Two rules carry most of the weight here. A user account is not owned by a
 * company, so removing somebody ends their membership and leaves the account
 * and their signed-off work alone. And nobody may take away the last ability
 * to manage users — a company that locks itself out has no way back except
 * support.
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        TenantFixture::actingAsTenant($this->delta);
    }

    private function role(string $code): Role
    {
        return Role::whereNull('company_id')->where('code', $code)->firstOrFail();
    }

    public function test_a_person_can_be_added_and_their_password_is_shown_once(): void
    {
        $response = $this->actingAs($this->owner)->post('/app/settings/users', [
            'name' => 'Karim Mia',
            'email' => 'karim@delta.test',
            'roles' => [$this->role('TECHNICIAN')->id],
            'factory_id' => $this->dhaka->id,
        ]);

        $response->assertRedirect('/app/settings/users');
        $response->assertSessionHas('user_password');

        $password = session('user_password');

        $user = User::where('email', 'karim@delta.test')->firstOrFail();

        // The password works, which is the only thing that makes handing it
        // over meaningful.
        $this->assertTrue(Hash::check($password, $user->password));

        // Shown on the page the redirect lands on — that request consumes the
        // flash — and gone from the one after it.
        $this->actingAs($this->owner)->get('/app/settings/users')->assertOk()->assertSee($password);
        $this->actingAs($this->owner)->get('/app/settings/users')->assertOk()->assertDontSee($password);

        $assignment = UserRole::where('user_id', $user->id)->firstOrFail();

        // A factory-scoped role is pinned to the chosen factory.
        $this->assertSame($this->dhaka->id, $assignment->factory_id);
    }

    public function test_a_company_scoped_role_is_not_pinned_to_a_factory(): void
    {
        $this->actingAs($this->owner)->post('/app/settings/users', [
            'name' => 'Nasrin Akter',
            'email' => 'nasrin@delta.test',
            'roles' => [$this->role('AUDITOR')->id],
            'factory_id' => $this->dhaka->id,
        ])->assertRedirect();

        $user = User::where('email', 'nasrin@delta.test')->firstOrFail();

        // An auditor's remit is the company, so pinning it to one factory
        // would quietly narrow what they were given.
        $this->assertNull(UserRole::where('user_id', $user->id)->firstOrFail()->factory_id);
    }

    public function test_a_factory_role_without_a_factory_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->from('/app/settings/users/create')
            ->post('/app/settings/users', [
                'name' => 'Rafiq',
                'email' => 'rafiq@delta.test',
                'roles' => [$this->role('TECHNICIAN')->id],
            ])
            ->assertSessionHasErrors('factory_id');

        // A role scoped to no factory grants nothing anywhere: it looks like an
        // assignment and behaves like none.
        $this->assertNull(User::where('email', 'rafiq@delta.test')->first());
    }

    public function test_a_platform_role_cannot_be_handed_out_by_a_tenant(): void
    {
        $platformRole = Role::whereNull('company_id')->where('code', 'PLATFORM_SUPER_ADMIN')->firstOrFail();

        $this->actingAs($this->owner)
            ->from('/app/settings/users/create')
            ->post('/app/settings/users', [
                'name' => 'Would-be admin',
                'email' => 'sneaky@delta.test',
                'roles' => [$platformRole->id],
            ])
            ->assertSessionHasErrors('roles');

        $this->assertNull(User::where('email', 'sneaky@delta.test')->first());
    }

    public function test_an_existing_account_joins_rather_than_being_duplicated(): void
    {
        $other = TenantFixture::company('Beta Textiles Ltd', 'BTL');
        $shared = TenantFixture::user($other, 'COMPANY_OWNER', 'shared@group.test');

        TenantFixture::actingAsTenant($this->delta);

        $this->actingAs($this->owner)->post('/app/settings/users', [
            'name' => 'Shared Person',
            'email' => 'shared@group.test',
            'roles' => [$this->role('AUDITOR')->id],
        ])->assertRedirect();

        // One account, two memberships: the same person moving between two
        // companies in a group keeps one set of credentials.
        $this->assertSame(1, User::where('email', 'shared@group.test')->count());
        $this->assertSame(
            2,
            CompanyUser::withoutGlobalScopes()->where('user_id', $shared->id)->count(),
        );

        // And no new password was issued for an account that already has one.
        $this->assertNull(session('user_password'));
    }

    public function test_somebody_already_in_this_company_cannot_be_added_twice(): void
    {
        $this->actingAs($this->owner)
            ->from('/app/settings/users/create')
            ->post('/app/settings/users', [
                'name' => 'Owner again',
                'email' => $this->owner->email,
                'roles' => [$this->role('AUDITOR')->id],
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_roles_can_be_changed(): void
    {
        $person = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test', factoryId: $this->dhaka->id);
        TenantFixture::actingAsTenant($this->delta);

        $this->actingAs($this->owner)
            ->patch('/app/settings/users/'.$person->id, [
                'name' => 'Karim Mia',
                'roles' => [$this->role('MAINTENANCE_ENGINEER')->id],
                'factory_id' => $this->dhaka->id,
            ])
            ->assertRedirect();

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
        // none, so the owner is the only keyholder. Acting as the technician's
        // manager would be the same story.
        $this->actingAs($second);

        $this->actingAs($second)
            ->from('/app/settings/users')
            ->delete('/app/settings/users/'.$this->owner->id)
            ->assertForbidden();

        // And the owner cannot demote themselves either.
        $this->actingAs($this->owner)
            ->from('/app/settings/users/'.$this->owner->id.'/edit')
            ->patch('/app/settings/users/'.$this->owner->id, [
                'name' => $this->owner->name,
                'roles' => [$this->role('TECHNICIAN')->id],
                'factory_id' => $this->dhaka->id,
            ])
            ->assertSessionHasErrors('roles');

        $this->assertTrue(
            app(PermissionResolver::class)->has($this->owner->fresh(), $this->delta->id, 'admin.user.manage'),
        );
    }

    public function test_administration_can_be_handed_over_and_then_given_up(): void
    {
        $successor = TenantFixture::user($this->delta, 'TECHNICIAN', 'successor@delta.test', factoryId: $this->dhaka->id);
        TenantFixture::actingAsTenant($this->delta);

        // Make somebody else an administrator first...
        $this->actingAs($this->owner)
            ->patch('/app/settings/users/'.$successor->id, [
                'name' => 'Successor',
                'roles' => [$this->role('COMPANY_ADMIN')->id],
            ])
            ->assertRedirect();

        app(PermissionResolver::class)->flush();

        // ...and only then may the outgoing one step down.
        $this->actingAs($this->owner)
            ->patch('/app/settings/users/'.$this->owner->id, [
                'name' => $this->owner->name,
                'roles' => [$this->role('VIEWER')->id],
                'factory_id' => $this->dhaka->id,
            ])
            ->assertRedirect();

        $this->assertSame(
            $this->role('VIEWER')->id,
            UserRole::withoutGlobalScopes()->where('user_id', $this->owner->id)->firstOrFail()->role_id,
        );
    }

    public function test_nobody_can_suspend_or_remove_themselves(): void
    {
        $this->actingAs($this->owner)
            ->from('/app/settings/users')
            ->post('/app/settings/users/'.$this->owner->id.'/toggle')
            ->assertSessionHasErrors('user');

        $this->actingAs($this->owner)
            ->from('/app/settings/users')
            ->delete('/app/settings/users/'.$this->owner->id)
            ->assertSessionHasErrors('user');
    }

    public function test_removing_somebody_ends_the_membership_and_leaves_the_account(): void
    {
        $person = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test', factoryId: $this->dhaka->id);
        TenantFixture::actingAsTenant($this->delta);

        $this->actingAs($this->owner)
            ->delete('/app/settings/users/'.$person->id)
            ->assertRedirect('/app/settings/users');

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

        $this->actingAs($this->owner)
            ->post('/app/settings/users/'.$person->id.'/toggle')
            ->assertRedirect();

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

        $this->actingAs($this->owner)
            ->post('/app/settings/users/'.$person->id.'/password')
            ->assertRedirect()
            ->assertSessionHas('user_password');

        $this->assertNotSame($before, $person->fresh()->password);
        $this->assertTrue(Hash::check(session('user_password'), $person->fresh()->password));
    }

    public function test_another_companys_user_is_not_reachable(): void
    {
        $other = TenantFixture::company('Beta Textiles Ltd', 'BTL');
        $theirs = TenantFixture::user($other, 'COMPANY_OWNER', 'theirs@btl.test');

        TenantFixture::actingAsTenant($this->delta);

        // 404 rather than 403: whether that account exists is none of this
        // company's business.
        $this->actingAs($this->owner)->get('/app/settings/users/'.$theirs->id.'/edit')->assertNotFound();
        $this->actingAs($this->owner)->delete('/app/settings/users/'.$theirs->id)->assertNotFound();
    }

    public function test_the_screens_are_closed_to_roles_that_do_not_administer(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test', factoryId: $this->dhaka->id);
        TenantFixture::actingAsTenant($this->delta);

        $this->actingAs($technician)->get('/app/settings/users')->assertForbidden();
        $this->actingAs($technician)->get('/app/settings/roles')->assertForbidden();
        $this->actingAs($technician)
            ->post('/app/settings/users', ['name' => 'X', 'email' => 'x@delta.test', 'roles' => []])
            ->assertForbidden();
    }

    public function test_the_role_reference_lists_what_each_role_can_do(): void
    {
        $this->actingAs($this->owner)
            ->get('/app/settings/roles')
            ->assertOk()
            ->assertSee($this->role('STORE_MANAGER')->name)
            ->assertSee('inventory.stock.receive');
    }
}
