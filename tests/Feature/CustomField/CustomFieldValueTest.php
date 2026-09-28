<?php

declare(strict_types=1);

namespace Tests\Feature\CustomField;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\CustomField\Actions\SaveCustomFieldDefinition;
use App\Modules\CustomField\Models\CustomFieldValue;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\WorkOrder\Models\Technician;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * Reading/writing a *record's own* custom field values — gated by that
 * record's own permission (`CustomFieldValueApiController::PERMISSIONS`),
 * not a separate custom-field permission, mirroring `FileApiController`'s
 * own per-`attachable_type` permission match.
 */
class CustomFieldValueTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $factory;

    private User $engineer;

    private string $engineerToken;

    private User $technician;

    private string $technicianToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->engineer = TenantFixture::user($this->delta, 'MAINTENANCE_ENGINEER', 'engineer@delta.test');
        $this->engineerToken = app(IssueApiToken::class)->forUser($this->engineer, $this->delta->id, 'x')['plain'];

        // Holds asset.asset.view but not asset.asset.update.
        $this->technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $this->technicianToken = app(IssueApiToken::class)->forUser($this->technician, $this->delta->id, 'x')['plain'];
    }

    private function asEngineer(): self
    {
        $this->withHeader('Authorization', 'Bearer '.$this->engineerToken);

        return $this;
    }

    private function asTechnician(): self
    {
        $this->withHeader('Authorization', 'Bearer '.$this->technicianToken);

        return $this;
    }

    public function test_an_unknown_entity_type_is_not_found(): void
    {
        $this->asEngineer()
            ->getJson('/api/v1/custom-fields/values?fieldable_type=spaceship&fieldable_id=whatever')
            ->assertNotFound();
    }

    public function test_reading_values_rides_on_the_records_own_view_permission(): void
    {
        $asset = WorkOrderFixture::runningAsset($this->delta, $this->factory);

        // Technician holds asset.asset.view — reading succeeds.
        $this->asTechnician()
            ->getJson("/api/v1/custom-fields/values?fieldable_type=asset&fieldable_id={$asset->id}")
            ->assertOk();
    }

    public function test_writing_values_requires_the_records_own_update_permission(): void
    {
        $asset = WorkOrderFixture::runningAsset($this->delta, $this->factory);

        app(SaveCustomFieldDefinition::class)->create($this->delta->id, 'asset', [
            'code' => 'notes', 'label' => 'Notes', 'field_type' => 'TEXT',
        ]);

        // Technician holds asset.asset.view but not .update.
        $this->asTechnician()
            ->postJson('/api/v1/custom-fields/values', [
                'fieldable_type' => 'asset',
                'fieldable_id' => $asset->id,
                'values' => ['notes' => 'Should be refused'],
            ])
            ->assertForbidden();

        $this->asEngineer()
            ->postJson('/api/v1/custom-fields/values', [
                'fieldable_type' => 'asset',
                'fieldable_id' => $asset->id,
                'values' => ['notes' => 'Engineer can write this'],
            ])
            ->assertNoContent();
    }

    public function test_a_required_field_left_blank_is_refused(): void
    {
        $asset = WorkOrderFixture::runningAsset($this->delta, $this->factory);

        app(SaveCustomFieldDefinition::class)->create($this->delta->id, 'asset', [
            'code' => 'inspection_days', 'label' => 'Inspection interval', 'field_type' => 'NUMBER', 'required' => true,
        ]);

        $this->asEngineer()
            ->postJson('/api/v1/custom-fields/values', [
                'fieldable_type' => 'asset',
                'fieldable_id' => $asset->id,
                'values' => [],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('custom_fields.inspection_days');
    }

    public function test_a_select_value_outside_the_defined_options_is_refused(): void
    {
        $asset = WorkOrderFixture::runningAsset($this->delta, $this->factory);

        app(SaveCustomFieldDefinition::class)->create($this->delta->id, 'asset', [
            'code' => 'condition', 'label' => 'Condition', 'field_type' => 'SELECT',
            'options' => ['Good', 'Fair', 'Poor'],
        ]);

        $this->asEngineer()
            ->postJson('/api/v1/custom-fields/values', [
                'fieldable_type' => 'asset',
                'fieldable_id' => $asset->id,
                'values' => ['condition' => 'Excellent'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('custom_fields.condition');

        $this->asEngineer()
            ->postJson('/api/v1/custom-fields/values', [
                'fieldable_type' => 'asset',
                'fieldable_id' => $asset->id,
                'values' => ['condition' => 'Fair'],
            ])
            ->assertNoContent();
    }

    public function test_a_deactivated_definitions_value_is_kept_but_no_longer_offered(): void
    {
        $asset = WorkOrderFixture::runningAsset($this->delta, $this->factory);

        $definition = app(SaveCustomFieldDefinition::class)->create($this->delta->id, 'asset', [
            'code' => 'legacy_note', 'label' => 'Legacy note', 'field_type' => 'TEXT',
        ]);

        $this->asEngineer()->postJson('/api/v1/custom-fields/values', [
            'fieldable_type' => 'asset',
            'fieldable_id' => $asset->id,
            'values' => ['legacy_note' => 'Kept from before'],
        ])->assertNoContent();

        app(SaveCustomFieldDefinition::class)->setActive($definition, false);

        $response = $this->asEngineer()
            ->getJson("/api/v1/custom-fields/values?fieldable_type=asset&fieldable_id={$asset->id}")
            ->assertOk();

        // Not offered in the schema/values the form would render...
        $this->assertSame([], $response->json('data.schema'));
        $this->assertArrayNotHasKey('legacy_note', $response->json('data.values'));

        // ...but the row itself is still there, not deleted.
        $this->assertDatabaseHas('custom_field_values', [
            'definition_id' => $definition->id,
            'fieldable_id' => $asset->id,
        ]);
    }

    public function test_values_are_scoped_per_record_not_shared_across_records(): void
    {
        $assetOne = WorkOrderFixture::runningAsset($this->delta, $this->factory, 'SEW-DHK-00001');
        $assetTwo = WorkOrderFixture::runningAsset($this->delta, $this->factory, 'SEW-DHK-00002');

        app(SaveCustomFieldDefinition::class)->create($this->delta->id, 'asset', [
            'code' => 'notes', 'label' => 'Notes', 'field_type' => 'TEXT',
        ]);

        $this->asEngineer()->postJson('/api/v1/custom-fields/values', [
            'fieldable_type' => 'asset', 'fieldable_id' => $assetOne->id, 'values' => ['notes' => 'First machine'],
        ])->assertNoContent();

        $response = $this->asEngineer()
            ->getJson("/api/v1/custom-fields/values?fieldable_type=asset&fieldable_id={$assetTwo->id}")
            ->assertOk();

        $this->assertNull($response->json('data.values.notes'));
    }
}
