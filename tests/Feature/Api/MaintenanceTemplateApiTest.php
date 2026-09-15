<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Maintenance\Models\MaintenanceTemplate;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Checklists, and writing them, over the API (API 9, SRS 12).
 *
 * `TemplateAuthoringTest`/`ChecklistVersioningTest` prove the same rules
 * over the web screens; this suite proves the JSON wiring — the same
 * property every one of those circles back to: a published version is
 * frozen, so changes go into a new draft.
 */
class MaintenanceTemplateApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private User $engineer;

    private User $technician;

    private string $engineerToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->engineer = TenantFixture::user($this->delta, 'MAINTENANCE_ENGINEER', 'eng@delta.test');
        $this->technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');

        $this->engineerToken = app(IssueApiToken::class)->forUser($this->engineer, $this->delta->id, 'x')['plain'];
    }

    private function asEngineer(): self
    {
        $this->withHeader('Authorization', 'Bearer '.$this->engineerToken);

        return $this;
    }

    public function test_a_checklist_is_born_with_a_draft_and_versioning_flows_through(): void
    {
        $created = $this->asEngineer()->postJson('/api/v1/maintenance-templates', [
            'name' => 'Soft flow dyeing — monthly PM',
            'code' => 'PM-DYEING',
            'estimated_duration_minutes' => 60,
        ])->assertCreated();

        $templateId = $created->json('data.id');
        $this->assertNull($created->json('data.current_version_id'));
        $draftId = $created->json('data.draft_version_id');
        $this->assertNotNull($draftId);
        $this->assertSame(0, count($created->json('data.version.items')));

        // Cannot publish empty.
        $this->asEngineer()->postJson("/api/v1/maintenance-templates/{$templateId}/versions/{$draftId}/publish")
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');

        $item = $this->asEngineer()->postJson(
            "/api/v1/maintenance-templates/{$templateId}/versions/{$draftId}/items",
            ['label' => 'Check pump seal for leaks', 'input_type' => 'PASS_FAIL', 'is_safety_item' => true],
        )->assertCreated();

        // A safety item demands evidence by itself, without asking.
        $this->assertTrue($item->json('data.requires_attachment_on_fail'));
        $this->assertTrue($item->json('data.requires_note_on_fail'));
        $itemId = $item->json('data.id');

        $this->asEngineer()->postJson("/api/v1/maintenance-templates/{$templateId}/versions/{$draftId}/publish")
            ->assertOk()
            ->assertJsonPath('data.version.status', 'PUBLISHED');

        $show = $this->asEngineer()->getJson("/api/v1/maintenance-templates/{$templateId}")->assertOk();
        $this->assertSame($draftId, $show->json('data.current_version_id'));
        $this->assertSame('PUBLISHED', $show->json('data.version.status'));

        // Only one open draft at a time, and a revision copies what came
        // before rather than starting blank — as a genuinely new row per
        // item (a fresh id each), not the same one carried over.
        $revision = $this->asEngineer()->postJson("/api/v1/maintenance-templates/{$templateId}/draft")->assertCreated();
        $this->assertCount(1, $revision->json('data.items'));
        $this->assertSame('Check pump seal for leaks', $revision->json('data.items.0.label'));
        $this->assertNotSame($itemId, $revision->json('data.items.0.id'));

        $this->asEngineer()->postJson("/api/v1/maintenance-templates/{$templateId}/draft")
            ->assertStatus(422)
            ->assertJsonValidationErrors('version');

        // The published version itself cannot be edited.
        $this->asEngineer()->patchJson(
            "/api/v1/maintenance-templates/{$templateId}/versions/{$draftId}/items/{$itemId}",
            ['label' => 'Rewriting a signed checklist', 'input_type' => 'PASS_FAIL'],
        )->assertStatus(409);
    }

    public function test_removing_an_item_resequences_the_rest(): void
    {
        $templateId = $this->asEngineer()->postJson('/api/v1/maintenance-templates', [
            'name' => 'Cutting table PM',
            'code' => 'PM-CUTTING',
        ])->json('data.id');

        $draftId = $this->asEngineer()->getJson("/api/v1/maintenance-templates/{$templateId}")
            ->json('data.draft_version_id');

        $first = $this->asEngineer()->postJson(
            "/api/v1/maintenance-templates/{$templateId}/versions/{$draftId}/items",
            ['label' => 'Blade sharpness', 'input_type' => 'PASS_FAIL'],
        )->json('data.id');

        $second = $this->asEngineer()->postJson(
            "/api/v1/maintenance-templates/{$templateId}/versions/{$draftId}/items",
            ['label' => 'Guard in place', 'input_type' => 'PASS_FAIL'],
        )->json('data.id');

        $this->asEngineer()->deleteJson("/api/v1/maintenance-templates/{$templateId}/versions/{$draftId}/items/{$first}")
            ->assertNoContent();

        $items = $this->asEngineer()->getJson("/api/v1/maintenance-templates/{$templateId}?version={$draftId}")
            ->json('data.version.items');

        $this->assertCount(1, $items);
        $this->assertSame($second, $items[0]['id']);
        $this->assertSame(1, $items[0]['sequence']);
    }

    public function test_a_platform_template_is_readable_but_not_editable(): void
    {
        $platform = MaintenanceTemplate::create([
            'company_id' => null,
            'name' => 'Seeded platform checklist',
            'code' => 'PLATFORM-1',
            'status' => 'ACTIVE',
        ]);

        $this->asEngineer()->getJson("/api/v1/maintenance-templates/{$platform->id}")->assertOk();

        $this->asEngineer()->postJson("/api/v1/maintenance-templates/{$platform->id}/draft")
            ->assertForbidden();
    }

    public function test_a_technician_does_not_reach_the_checklist_library(): void
    {
        $technicianToken = app(IssueApiToken::class)->forUser($this->technician, $this->delta->id, 'x')['plain'];

        $this->withHeader('Authorization', 'Bearer '.$technicianToken)
            ->getJson('/api/v1/maintenance-templates')
            ->assertForbidden();
    }

    public function test_form_options_are_reachable_by_the_same_role_that_authors_templates(): void
    {
        $response = $this->asEngineer()->getJson('/api/v1/maintenance-templates/form-options')->assertOk();

        $response->assertJsonStructure(['data' => ['asset_types', 'maintenance_types', 'input_types']]);
    }
}
