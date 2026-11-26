<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\Menu;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Support\MappingRegistry;
use App\Modules\Inventory\Models\Store;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Scopes\TenantScope;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryFixture;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Access Control → Mappings (AnnotechERP 02/02): the shared mapping engine
 * (user × store, requisition type, production category, business rule;
 * menu × report) and User Company Mapping over memberships.
 */
class AccessMappingTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private User $owner;

    private User $keeper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->keeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'keeper@delta.test', $this->dhaka->id);
    }

    public function test_a_users_store_matrix_saves_and_restricts_only_once_it_has_rows(): void
    {
        $main = Store::findOrFail(InventoryFixture::bin($this->delta, $this->dhaka)->store_id);
        $other = Store::create(['company_id' => $this->delta->id, 'warehouse_id' => $main->warehouse_id, 'name' => 'Trims store', 'code' => 'DHK-TRM', 'store_type' => 'GENERAL', 'active' => true]);
        $registry = app(MappingRegistry::class);

        $this->assertNull($registry->targetsFor('user-store', $this->keeper->id, 'issue'), 'No rows: not restricted.');

        $this->as($this->owner)->putJson("/api/v1/access/mappings/user-store/subjects/{$this->keeper->id}", ['rows' => [
            ['target_id' => $main->id, 'flags' => ['issue' => true, 'receive' => true]],
            ['target_id' => $other->id, 'flags' => ['issue' => false, 'receive' => false, 'adjust' => false]],
        ]])
            ->assertOk()
            ->assertJsonFragment(['target_id' => $main->id, 'selected' => true])
            ->assertJsonFragment(['target_id' => $other->id, 'selected' => false]);

        TenantFixture::actingAsTenant($this->delta);
        $this->assertSame([$main->id], $registry->targetsFor('user-store', $this->keeper->id, 'issue'));
        $this->assertFalse($registry->allows('user-store', $this->keeper->id, $other->id, 'receive'));
        $this->assertSame([], $registry->targetsFor('user-store', $this->keeper->id, 'adjust'));

        $this->as($this->owner)->getJson('/api/v1/access/mappings/user-store')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.target', 'Spare parts store (DHK-ST)')
            ->assertJsonPath('data.0.flag_issue', true)
            ->assertJsonPath('data.0.flag_adjust', false);
    }

    public function test_a_new_row_is_validated_against_the_kinds_own_subjects_and_targets(): void
    {
        $this->as($this->owner)->postJson('/api/v1/access/mappings/user-requisition', ['target_id' => 'PURCHASE'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.subject_id.0', 'User is required.');

        // A lookup value the table does not have, and a user of another company.
        $this->as($this->owner)->postJson('/api/v1/access/mappings/user-requisition', ['subject_id' => $this->keeper->id, 'target_id' => 'NOPE'])->assertUnprocessable();
        $gamma = TenantFixture::company('Gamma Knit Ltd', 'GKL');
        $stranger = TenantFixture::user($gamma, 'COMPANY_OWNER', 'stranger@gamma.test');
        $this->as($this->owner)->postJson('/api/v1/access/mappings/user-requisition', ['subject_id' => $stranger->id, 'target_id' => 'PURCHASE'])->assertUnprocessable();

        $id = $this->as($this->owner)->postJson('/api/v1/access/mappings/user-requisition', [
            'subject_id' => $this->keeper->id, 'target_id' => 'PURCHASE', 'flag_raise' => true, 'flag_approve' => true, 'amount' => 50000,
        ])->assertCreated()->assertJsonPath('data.target', 'PURCHASE — Purchase Requisition')->json('data.id');

        $this->as($this->owner)->patchJson("/api/v1/access/mappings/user-requisition/{$id}", ['flag_approve' => false])
            ->assertOk()
            ->assertJsonPath('data.flag_approve', false)
            ->assertJsonPath('data.flag_raise', true)
            ->assertJsonPath('data.amount', '50000.00');

        // An id under the wrong kind is not found.
        $this->as($this->owner)->deleteJson("/api/v1/access/mappings/user-store/{$id}")->assertNotFound();
        $this->as($this->owner)->deleteJson("/api/v1/access/mappings/user-requisition/{$id}")->assertNoContent();
        $this->as($this->owner)->getJson('/api/v1/access/mappings/unknown-kind')->assertNotFound();
    }

    public function test_a_menu_has_one_default_report(): void
    {
        $menu = Menu::where('code', 'MACHINERY.REPORTS')->firstOrFail();

        $this->as($this->owner)->putJson("/api/v1/access/mappings/menu-report/subjects/{$menu->id}", ['rows' => [
            ['target_id' => 'asset_register', 'selected' => true, 'sort_order' => 10, 'is_default' => true],
            ['target_id' => 'downtime', 'selected' => true, 'sort_order' => 20, 'is_default' => true],
            ['target_id' => 'root_cause', 'selected' => false],
        ]])->assertOk();

        $rows = collect($this->as($this->owner)->getJson('/api/v1/access/mappings/menu-report')->json('data'));
        $this->assertCount(2, $rows);
        $this->assertSame(1, $rows->where('is_default', true)->count());

        // A new default clears the others.
        $this->as($this->owner)->postJson('/api/v1/access/mappings/menu-report', ['subject_id' => $menu->id, 'target_id' => 'root_cause', 'is_default' => true])->assertCreated();
        $rows = collect($this->as($this->owner)->getJson('/api/v1/access/mappings/menu-report')->json('data'));
        $this->assertSame(['root_cause'], $rows->where('is_default', true)->pluck('target_id')->values()->all());
    }

    public function test_user_company_mapping_joins_only_where_the_caller_administers(): void
    {
        $sister = TenantFixture::company('Delta Knit Ltd', 'DKL');
        TenantFixture::addMembership($this->owner, $sister, 'COMPANY_OWNER');
        $foreign = TenantFixture::company('Gamma Knit Ltd', 'GKL');
        TenantFixture::actingAsTenant($this->delta);

        $options = $this->as($this->owner)->getJson('/api/v1/access/user-companies/options')->assertOk()->json('data.companies');
        $this->assertEqualsCanonicalizing(['Delta Apparels Ltd', 'Delta Knit Ltd'], collect($options)->pluck('label')->all());

        $this->as($this->owner)->postJson('/api/v1/access/user-companies', ['user_id' => $this->keeper->id, 'company_id' => $sister->id, 'is_default' => true])->assertCreated();
        $memberships = CompanyUser::withoutGlobalScope(TenantScope::class)->where('user_id', $this->keeper->id)->get()->keyBy('company_id');
        $this->assertTrue($memberships[$sister->id]->is_default);
        $this->assertFalse($memberships[$this->delta->id]->is_default);

        // A company the caller does not belong to is not offered, let alone joined.
        $this->as($this->owner)->postJson('/api/v1/access/user-companies', ['user_id' => $this->keeper->id, 'company_id' => $foreign->id])->assertUnprocessable();

        // Leaving this company is done elsewhere.
        $this->as($this->owner)->deleteJson('/api/v1/access/user-companies/'.$memberships[$this->delta->id]->id)->assertStatus(409);
        $this->as($this->owner)->deleteJson('/api/v1/access/user-companies/'.$memberships[$sister->id]->id)->assertNoContent();
    }

    public function test_mappings_are_closed_to_roles_that_do_not_administer_users(): void
    {
        $this->as($this->keeper)->getJson('/api/v1/access/mappings/user-store')->assertForbidden();
        $this->as($this->keeper)->getJson('/api/v1/access/user-companies')->assertForbidden();
    }

    private function as(User $user, ?Company $company = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, ($company ?? $this->delta)->id, 'Test token');

        return $this->withToken($plain);
    }
}
