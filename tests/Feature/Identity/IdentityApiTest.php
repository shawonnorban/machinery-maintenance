<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * User, role, permission and team administration over the API (API 4.1,
 * Gap Analysis 3.4 #33). Absent before this: v1.0 specified a full RBAC
 * model with no way to administer it.
 */
class IdentityApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Company $rival;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');

        $this->rival = TenantFixture::company('Rival Textiles Ltd', 'RTL');
        TenantFixture::factory($this->rival, 'Savar Unit', 'SAV');
        TenantFixture::actingAsTenant($this->rival);
        TenantFixture::user($this->rival, 'COMPANY_OWNER', 'owner@rival.test');

        TenantFixture::actingAsTenant($this->delta);
    }

    // -- Users ----------------------------------------------------------

    public function test_a_person_can_be_invited_updated_and_deactivated(): void
    {
        $roleId = $this->roleId('TECHNICIAN');

        $created = $this->withToken($this->tokenFor($this->owner))
            ->postJson('/api/v1/users', [
                'name' => 'Karim Mia',
                'email' => 'karim@delta.test',
                'roles' => [$roleId],
                'factory_id' => $this->dhaka->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'karim@delta.test')
            ->assertJsonStructure(['data' => ['password']]);

        $userId = $created->json('data.id');

        $this->withToken($this->tokenFor($this->owner))
            ->patchJson("/api/v1/users/{$userId}", [
                'name' => 'Karim Uddin',
                'roles' => [$roleId],
                'factory_id' => $this->dhaka->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Karim Uddin');

        $this->withToken($this->tokenFor($this->owner))
            ->postJson("/api/v1/users/{$userId}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.status', 'SUSPENDED');

        $this->withToken($this->tokenFor($this->owner))
            ->postJson("/api/v1/users/{$userId}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', 'ACTIVE');
    }

    public function test_a_role_can_be_assigned_and_removed_without_replacing_the_whole_set(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test', $this->dhaka->id);
        $engineerRoleId = $this->roleId('MAINTENANCE_ENGINEER');

        $response = $this->withToken($this->tokenFor($this->owner))
            ->postJson("/api/v1/users/{$technician->id}/roles", [
                'role_id' => $engineerRoleId,
                'factory_id' => $this->dhaka->id,
            ])
            ->assertCreated();

        $assignmentId = $response->json('data.id');

        // Both roles are held now — the original was not replaced.
        $roles = $this->withToken($this->tokenFor($this->owner))
            ->getJson("/api/v1/users/{$technician->id}/roles")
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $roles);

        $this->withToken($this->tokenFor($this->owner))
            ->deleteJson("/api/v1/users/{$technician->id}/roles/{$assignmentId}")
            ->assertNoContent();

        $this->assertCount(1, UserRole::where('user_id', $technician->id)->get());
    }

    public function test_a_company_cannot_see_or_edit_another_companys_user(): void
    {
        TenantFixture::actingAsTenant($this->rival);
        $rivalUser = TenantFixture::user($this->rival, 'TECHNICIAN', 'stranger@rival.test');
        TenantFixture::actingAsTenant($this->delta);

        $this->withToken($this->tokenFor($this->owner))
            ->getJson("/api/v1/users/{$rivalUser->id}")
            ->assertNotFound();
    }

    public function test_managing_users_requires_the_permission(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech2@delta.test', $this->dhaka->id);

        $this->withToken($this->tokenFor($technician))
            ->getJson('/api/v1/users')
            ->assertForbidden();
    }

    // -- Roles ------------------------------------------------------------

    public function test_a_seeded_role_can_be_cloned_into_a_company_owned_one(): void
    {
        $source = Role::whereNull('company_id')->where('name', 'TECHNICIAN')->firstOrFail();

        $response = $this->withToken($this->tokenFor($this->owner))
            ->postJson('/api/v1/roles', [
                'code' => 'NIGHT_TECHNICIAN',
                'name' => 'Night Shift Technician',
                'clone_from' => $source->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'NIGHT_TECHNICIAN')
            ->assertJsonPath('data.is_system', false);

        // The clone starts with the source's permissions, not an empty set.
        $this->assertNotEmpty($response->json('data.permissions'));
    }

    public function test_a_seeded_role_cannot_be_edited_or_deleted(): void
    {
        $source = Role::whereNull('company_id')->where('name', 'TECHNICIAN')->firstOrFail();

        $this->withToken($this->tokenFor($this->owner))
            ->patchJson("/api/v1/roles/{$source->id}", ['name' => 'Renamed'])
            ->assertForbidden();

        $this->withToken($this->tokenFor($this->owner))
            ->deleteJson("/api/v1/roles/{$source->id}")
            ->assertForbidden();
    }

    public function test_a_role_still_assigned_cannot_be_deleted(): void
    {
        $source = Role::whereNull('company_id')->where('name', 'TECHNICIAN')->firstOrFail();

        $clone = $this->withToken($this->tokenFor($this->owner))
            ->postJson('/api/v1/roles', [
                'code' => 'CUSTOM_ROLE',
                'name' => 'Custom Role',
                'clone_from' => $source->id,
            ])
            ->json('data.id');

        TenantFixture::user($this->delta, 'TECHNICIAN', 'tobeassigned@delta.test', $this->dhaka->id);
        $assignee = User::where('email', 'tobeassigned@delta.test')->firstOrFail();

        UserRole::create([
            'company_id' => $this->delta->id,
            'user_id' => $assignee->id,
            'role_id' => $clone,
            'factory_id' => null,
        ]);

        $this->withToken($this->tokenFor($this->owner))
            ->deleteJson("/api/v1/roles/{$clone}")
            ->assertStatus(409);
    }

    /**
     * `permissions_count` and `holders_count` are what the web's read-only
     * role list shows next to each role — an administrator handing roles
     * out needs to see what a role means and whether it's actually in use.
     * Neither was on `index()`'s own `summary()` before this; the first is
     * free off `with('permissions:id,name')`, the second one grouped query
     * for the whole page.
     */
    public function test_the_list_carries_permission_and_holder_counts(): void
    {
        $source = Role::whereNull('company_id')->where('name', 'TECHNICIAN')->firstOrFail();
        $sourcePermissionCount = $source->permissions()->count();

        $clone = $this->withToken($this->tokenFor($this->owner))
            ->postJson('/api/v1/roles', [
                'code' => 'HOLDER_COUNT_ROLE',
                'name' => 'Holder Count Role',
                'clone_from' => $source->id,
            ])
            ->json('data.id');

        TenantFixture::user($this->delta, 'TECHNICIAN', 'holdercount@delta.test', $this->dhaka->id);
        $assignee = User::where('email', 'holdercount@delta.test')->firstOrFail();

        UserRole::create([
            'company_id' => $this->delta->id,
            'user_id' => $assignee->id,
            'role_id' => $clone,
            'factory_id' => null,
        ]);

        $response = $this->withToken($this->tokenFor($this->owner))
            ->getJson('/api/v1/roles?per_page=100')
            ->assertOk();

        $row = collect($response->json('data'))->firstWhere('id', $clone);

        $this->assertSame($sourcePermissionCount, $row['permissions_count']);
        $this->assertSame(1, $row['holders_count']);
    }

    // -- Permissions --------------------------------------------------------

    public function test_the_permission_catalog_is_grouped_by_module(): void
    {
        $codes = $this->withToken($this->tokenFor($this->owner))
            ->getJson('/api/v1/permissions')
            ->assertOk()
            ->json('data.asset');

        $this->assertContains('asset.asset.view_any', array_column($codes, 'code'));
    }

    // -- Teams ----------------------------------------------------------

    public function test_a_team_can_be_created_updated_and_deleted(): void
    {
        $created = $this->withToken($this->tokenFor($this->owner))
            ->postJson('/api/v1/teams', [
                'name' => 'Night Shift Electricians',
                'code' => 'night-elec',
                'factory_id' => $this->dhaka->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'NIGHT-ELEC');

        $teamId = $created->json('data.id');

        $this->withToken($this->tokenFor($this->owner))
            ->patchJson("/api/v1/teams/{$teamId}", [
                'name' => 'Night Shift Electricians (A)',
                'code' => 'night-elec',
                'factory_id' => $this->dhaka->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Night Shift Electricians (A)');

        $this->withToken($this->tokenFor($this->owner))
            ->deleteJson("/api/v1/teams/{$teamId}")
            ->assertNoContent();
    }

    public function test_team_form_options_are_reachable_by_a_caller_who_can_create_but_not_manage_factories(): void
    {
        // MAINTENANCE_MANAGER has admin.team.manage but not settings.
        // factory.manage (that starts one tier up, at FACTORY_MANAGER) —
        // the same shape of gap already found and closed for Asset's,
        // Inventory's, and WorkOrder's create forms.
        $manager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'maintmgr@delta.test');

        $response = $this->withToken($this->tokenFor($manager))
            ->getJson('/api/v1/teams/form-options')
            ->assertOk();

        $this->assertContains($this->dhaka->id, array_column($response->json('data.factories'), 'id'));
    }

    public function test_two_companies_can_use_the_same_team_code(): void
    {
        $this->withToken($this->tokenFor($this->owner))
            ->postJson('/api/v1/teams', [
                'name' => 'Utilities',
                'code' => 'UTIL',
                'factory_id' => $this->dhaka->id,
            ])
            ->assertCreated();

        TenantFixture::actingAsTenant($this->rival);
        $rivalFactory = Factory::where('company_id', $this->rival->id)->firstOrFail();
        $rivalOwner = User::where('email', 'owner@rival.test')->firstOrFail();

        $this->withToken($this->tokenFor($rivalOwner))
            ->postJson('/api/v1/teams', [
                'name' => 'Utilities',
                'code' => 'UTIL',
                'factory_id' => $rivalFactory->id,
            ])
            ->assertCreated();
    }

    // -- Helpers ------------------------------------------------------------

    private function tokenFor(User $user): string
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $this->currentCompanyId($user), 'Test token');

        return $plain;
    }

    private function currentCompanyId(User $user): string
    {
        return $user->memberships()->latest()->value('company_id');
    }

    private function roleId(string $code): int
    {
        return (int) Role::whereNull('company_id')->where('name', $code)->value('id');
    }
}
