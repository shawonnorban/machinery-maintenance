<?php

declare(strict_types=1);

namespace Tests\Feature\CustomField;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\CustomField\Models\CustomFieldDefinition;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Defining custom fields (the schema, not a value) — one company-wide
 * permission covers every entity type, the same shape `masterdata.manage`
 * already has for its own generic admin screen.
 */
class CustomFieldDefinitionTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $factory;

    private User $manager;

    private string $managerToken;

    private User $engineer;

    private string $engineerToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        // Holds custom_field.definition.manage (granted starting at the
        // FACTORY_MANAGER tier).
        $this->manager = TenantFixture::user($this->delta, 'FACTORY_ADMIN', 'manager@delta.test');
        $this->managerToken = app(IssueApiToken::class)->forUser($this->manager, $this->delta->id, 'x')['plain'];

        // Does not — custom fields are an admin-only setup.
        $this->engineer = TenantFixture::user($this->delta, 'MAINTENANCE_ENGINEER', 'engineer@delta.test');
        $this->engineerToken = app(IssueApiToken::class)->forUser($this->engineer, $this->delta->id, 'x')['plain'];
    }

    private function asManager(): self
    {
        $this->withHeader('Authorization', 'Bearer '.$this->managerToken);

        return $this;
    }

    private function asEngineer(): self
    {
        $this->withHeader('Authorization', 'Bearer '.$this->engineerToken);

        return $this;
    }

    public function test_a_role_without_the_permission_cannot_define_fields(): void
    {
        $this->asEngineer()->postJson('/api/v1/custom-fields/definitions', [
            'fieldable_type' => 'asset',
            'code' => 'serial_prefix',
            'label' => 'Serial prefix',
            'field_type' => 'TEXT',
        ])->assertForbidden();

        $this->asEngineer()
            ->getJson('/api/v1/custom-fields/definitions?fieldable_type=asset')
            ->assertForbidden();
    }

    public function test_a_field_can_be_defined_listed_updated_and_deactivated(): void
    {
        $created = $this->asManager()->postJson('/api/v1/custom-fields/definitions', [
            'fieldable_type' => 'asset',
            'code' => 'serial_prefix',
            'label' => 'Serial prefix',
            'field_type' => 'TEXT',
            'required' => false,
        ])->assertCreated()->assertJsonPath('data.code', 'serial_prefix');

        $id = $created->json('data.id');

        $this->asManager()->getJson('/api/v1/custom-fields/definitions?fieldable_type=asset')
            ->assertOk()
            ->assertJsonFragment(['code' => 'serial_prefix']);

        $this->asManager()->patchJson("/api/v1/custom-fields/definitions/{$id}", [
            'label' => 'Serial number prefix',
            'field_type' => 'TEXT',
        ])->assertOk()->assertJsonPath('data.label', 'Serial number prefix');

        $this->asManager()->patchJson("/api/v1/custom-fields/definitions/{$id}/active", ['active' => false])
            ->assertOk()->assertJsonPath('data.active', false);

        $this->assertFalse(CustomFieldDefinition::findOrFail($id)->active);
    }

    public function test_a_duplicate_code_for_the_same_entity_is_refused(): void
    {
        $this->asManager()->postJson('/api/v1/custom-fields/definitions', [
            'fieldable_type' => 'asset',
            'code' => 'inspection_days',
            'label' => 'Inspection interval',
            'field_type' => 'NUMBER',
        ])->assertCreated();

        $this->asManager()->postJson('/api/v1/custom-fields/definitions', [
            'fieldable_type' => 'asset',
            'code' => 'inspection_days',
            'label' => 'A different label, same code',
            'field_type' => 'TEXT',
        ])->assertStatus(422)->assertJsonValidationErrors('code');
    }

    public function test_the_same_code_is_fine_on_a_different_entity_type(): void
    {
        $this->asManager()->postJson('/api/v1/custom-fields/definitions', [
            'fieldable_type' => 'asset',
            'code' => 'notes',
            'label' => 'Notes',
            'field_type' => 'TEXT',
        ])->assertCreated();

        $this->asManager()->postJson('/api/v1/custom-fields/definitions', [
            'fieldable_type' => 'vendor',
            'code' => 'notes',
            'label' => 'Notes',
            'field_type' => 'TEXT',
        ])->assertCreated();
    }

    public function test_a_select_field_requires_at_least_one_option(): void
    {
        $this->asManager()->postJson('/api/v1/custom-fields/definitions', [
            'fieldable_type' => 'asset',
            'code' => 'condition',
            'label' => 'Condition',
            'field_type' => 'SELECT',
            'options' => [],
        ])->assertStatus(422)->assertJsonValidationErrors('options');

        $this->asManager()->postJson('/api/v1/custom-fields/definitions', [
            'fieldable_type' => 'asset',
            'code' => 'condition',
            'label' => 'Condition',
            'field_type' => 'SELECT',
            'options' => ['Good', 'Fair', 'Poor'],
        ])->assertCreated()->assertJsonPath('data.options', ['Good', 'Fair', 'Poor']);
    }

    public function test_a_field_can_be_deleted(): void
    {
        $created = $this->asManager()->postJson('/api/v1/custom-fields/definitions', [
            'fieldable_type' => 'asset',
            'code' => 'to_delete',
            'label' => 'To delete',
            'field_type' => 'TEXT',
        ])->assertCreated();

        $this->asManager()->deleteJson('/api/v1/custom-fields/definitions/'.$created->json('data.id'))
            ->assertNoContent();

        $this->assertDatabaseMissing('custom_field_definitions', ['id' => $created->json('data.id')]);
    }
}
