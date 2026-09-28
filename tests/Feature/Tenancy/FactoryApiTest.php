<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * Factories, over the API (API 5) — the unit everything else in the product
 * is scoped to (SRS 4).
 */
class FactoryApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $manager;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::actingAsTenant($this->delta);

        $this->manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test');
        $this->technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
    }

    public function test_a_factory_can_be_created_updated_and_closed(): void
    {
        $created = $this->withToken($this->tokenFor($this->manager))
            ->postJson('/api/v1/factories', [
                'name' => 'Dhaka Unit 1',
                'code' => 'DHK',
                'timezone' => 'Asia/Dhaka',
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'DHK')
            ->assertJsonPath('data.status', 'ACTIVE');

        $factoryId = $created->json('data.id');

        $this->withToken($this->tokenFor($this->manager))
            ->patchJson("/api/v1/factories/{$factoryId}", [
                'name' => 'Dhaka Unit 1 (Main)',
                'timezone' => 'Asia/Dhaka',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Dhaka Unit 1 (Main)')
            ->assertJsonPath('data.code', 'DHK');

        $this->withToken($this->tokenFor($this->manager))
            ->patchJson("/api/v1/factories/{$factoryId}/active", ['active' => false])
            ->assertOk()
            ->assertJsonPath('data.status', 'INACTIVE');

        $this->withToken($this->tokenFor($this->manager))
            ->getJson('/api/v1/factories')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_factory_still_running_a_machine_cannot_be_deleted(): void
    {
        $factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        WorkOrderFixture::runningAsset($this->delta, $factory);

        $this->withToken($this->tokenFor($this->manager))
            ->deleteJson("/api/v1/factories/{$factory->id}")
            ->assertStatus(409);

        $this->assertNotNull(Factory::find($factory->id));
    }

    public function test_an_empty_factory_can_be_deleted(): void
    {
        $factory = TenantFixture::factory($this->delta, 'Narayanganj Unit', 'NGJ');

        $this->withToken($this->tokenFor($this->manager))
            ->deleteJson("/api/v1/factories/{$factory->id}")
            ->assertNoContent();

        $this->assertNull(Factory::find($factory->id));
    }

    public function test_a_second_factory_with_the_same_code_in_one_company_is_refused(): void
    {
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');

        $this->withToken($this->tokenFor($this->manager))
            ->postJson('/api/v1/factories', [
                'name' => 'Another Dhaka Unit',
                'code' => 'DHK',
                'timezone' => 'Asia/Dhaka',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_two_companies_can_use_the_same_factory_code(): void
    {
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');

        $omega = TenantFixture::company('Omega Textiles Ltd', 'OTL');
        TenantFixture::actingAsTenant($omega);
        $omegaOwner = TenantFixture::user($omega, 'COMPANY_OWNER', 'owner@omega.test');

        $this->withToken($this->tokenFor($omegaOwner))
            ->postJson('/api/v1/factories', [
                'name' => 'Narayanganj Unit',
                'code' => 'DHK',
                'timezone' => 'Asia/Dhaka',
            ])
            ->assertCreated();
    }

    public function test_the_endpoints_are_closed_to_a_role_that_does_not_administer_the_estate(): void
    {
        $this->withToken($this->tokenFor($this->technician))
            ->getJson('/api/v1/factories')
            ->assertForbidden();

        $this->withToken($this->tokenFor($this->technician))
            ->postJson('/api/v1/factories', ['name' => 'X', 'code' => 'X', 'timezone' => 'UTC'])
            ->assertForbidden();
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}
