<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenance;

use App\Modules\Identity\Models\User;
use App\Modules\Maintenance\Models\ChecklistItem;
use App\Modules\Maintenance\Models\MaintenanceTemplate;
use App\Modules\Maintenance\Models\MaintenanceTemplateVersion;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Writing a checklist (SRS 12).
 *
 * The seeded templates were the only ones a factory could ever use, so a dye
 * house ran its soft flow machines against a checklist written for a sewing
 * floor — or against nothing.
 *
 * The property every test here circles: a published version is frozen. Editing
 * one would silently rewrite what a technician signed to say they had checked.
 */
class TemplateAuthoringTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private User $engineer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->engineer = TenantFixture::user($this->delta, 'MAINTENANCE_ENGINEER', 'eng@delta.test');
        TenantFixture::actingAsTenant($this->delta);
    }

    private function template(string $code = 'PM-DYEING'): MaintenanceTemplate
    {
        $this->actingAs($this->engineer)->post('/app/maintenance/templates', [
            'name' => 'Soft flow dyeing — monthly PM',
            'code' => $code,
            'estimated_duration_minutes' => 60,
        ]);

        return MaintenanceTemplate::where('code', $code)->firstOrFail();
    }

    private function draftOf(MaintenanceTemplate $template): MaintenanceTemplateVersion
    {
        return MaintenanceTemplateVersion::where('template_id', $template->id)
            ->where('status', 'DRAFT')
            ->firstOrFail();
    }

    private function addCheck(MaintenanceTemplate $template, MaintenanceTemplateVersion $version, array $overrides = []): void
    {
        $this->actingAs($this->engineer)->post(
            '/app/maintenance/templates/'.$template->id.'/versions/'.$version->id.'/items',
            array_merge(['label' => 'Check pump seal for leaks', 'input_type' => 'PASS_FAIL'], $overrides),
        );
    }

    public function test_a_checklist_is_born_with_a_draft_to_write_into(): void
    {
        $template = $this->template();

        $this->assertSame($this->delta->id, $template->company_id);

        // A template with no version is a name with nothing behind it.
        $draft = $this->draftOf($template);

        $this->assertSame(1, $draft->version_number);
        $this->assertSame(60, $draft->estimated_duration_minutes);
    }

    public function test_checks_can_be_added_and_removed_while_it_is_a_draft(): void
    {
        $template = $this->template();
        $draft = $this->draftOf($template);

        $this->addCheck($template, $draft, ['label' => 'Check pump seal for leaks']);
        $this->addCheck($template, $draft, ['label' => 'Record vessel temperature', 'input_type' => 'NUMERIC', 'unit' => '°C']);

        $items = ChecklistItem::where('template_version_id', $draft->id)->orderBy('sequence')->get();

        $this->assertCount(2, $items);
        $this->assertSame(1, $items[0]->sequence);
        $this->assertSame(2, $items[1]->sequence);

        $this->actingAs($this->engineer)
            ->delete('/app/maintenance/templates/'.$template->id.'/versions/'.$draft->id.'/items/'.$items[0]->id)
            ->assertRedirect();

        // Resequenced, so the numbers a technician reads down the page have no
        // holes in them.
        $remaining = ChecklistItem::where('template_version_id', $draft->id)->get();

        $this->assertCount(1, $remaining);
        $this->assertSame(1, $remaining[0]->sequence);
    }

    /**
     * A safety check is not a stricter tick box.
     */
    public function test_a_safety_check_demands_evidence_by_itself(): void
    {
        $template = $this->template();
        $draft = $this->draftOf($template);

        $this->addCheck($template, $draft, [
            'label' => 'Emergency stop functional',
            'is_safety_item' => '1',
        ]);

        $item = ChecklistItem::where('template_version_id', $draft->id)->firstOrFail();

        // Marking it safety is enough: the author does not have to remember to
        // tick three more boxes for a guard that was missing to be recorded.
        $this->assertTrue($item->is_safety_item);
        $this->assertTrue($item->requires_attachment_on_fail);
        $this->assertTrue($item->requires_note_on_fail);
        $this->assertTrue($item->fail_creates_followup_work_order);
    }

    public function test_an_empty_checklist_cannot_be_published(): void
    {
        $template = $this->template();
        $draft = $this->draftOf($template);

        $this->actingAs($this->engineer)
            ->from('/app/maintenance/templates/'.$template->id)
            ->post('/app/maintenance/templates/'.$template->id.'/versions/'.$draft->id.'/publish')
            ->assertSessionHasErrors('items');

        $this->assertSame('DRAFT', $draft->fresh()->status);
    }

    public function test_publishing_freezes_the_version(): void
    {
        $template = $this->template();
        $draft = $this->draftOf($template);

        $this->addCheck($template, $draft);

        $this->actingAs($this->engineer)
            ->post('/app/maintenance/templates/'.$template->id.'/versions/'.$draft->id.'/publish')
            ->assertRedirect();

        $this->assertSame('PUBLISHED', $draft->fresh()->status);
        $this->assertNotNull($draft->fresh()->published_at);

        // And it can no longer be written to: what a technician certified they
        // checked must not change afterwards.
        $this->actingAs($this->engineer)
            ->from('/app/maintenance/templates/'.$template->id)
            ->post(
                '/app/maintenance/templates/'.$template->id.'/versions/'.$draft->id.'/items',
                ['label' => 'Sneaked in later', 'input_type' => 'PASS_FAIL'],
            )
            ->assertSessionHasErrors('version');

        $this->assertSame(1, ChecklistItem::where('template_version_id', $draft->id)->count());
    }

    /**
     * A revision copies what is published, because it is almost always "the
     * same fourteen checks with one changed".
     */
    public function test_a_revision_starts_from_the_published_version(): void
    {
        $template = $this->template();
        $first = $this->draftOf($template);

        $this->addCheck($template, $first, ['label' => 'Check pump seal for leaks']);
        $this->addCheck($template, $first, ['label' => 'Record vessel temperature']);

        $this->actingAs($this->engineer)
            ->post('/app/maintenance/templates/'.$template->id.'/versions/'.$first->id.'/publish');

        $this->actingAs($this->engineer)
            ->post('/app/maintenance/templates/'.$template->id.'/draft')
            ->assertRedirect();

        $second = $this->draftOf($template);

        $this->assertSame(2, $second->version_number);
        $this->assertSame($first->id, $second->supersedes_version_id);
        $this->assertSame(2, ChecklistItem::where('template_version_id', $second->id)->count());

        // The published one is untouched while the draft is being written.
        $this->assertSame('PUBLISHED', $first->fresh()->status);
    }

    public function test_publishing_a_revision_archives_the_one_it_replaces(): void
    {
        $template = $this->template();
        $first = $this->draftOf($template);

        $this->addCheck($template, $first);
        $this->actingAs($this->engineer)
            ->post('/app/maintenance/templates/'.$template->id.'/versions/'.$first->id.'/publish');

        $this->actingAs($this->engineer)->post('/app/maintenance/templates/'.$template->id.'/draft');
        $second = $this->draftOf($template);

        $this->actingAs($this->engineer)
            ->post('/app/maintenance/templates/'.$template->id.'/versions/'.$second->id.'/publish')
            ->assertRedirect();

        $this->assertSame('ARCHIVED', $first->fresh()->status);
        $this->assertSame('PUBLISHED', $second->fresh()->status);

        // No day is claimed by two published checklists, and none by neither.
        $this->assertNotNull($first->fresh()->effective_to);
    }

    public function test_only_one_draft_can_be_open_at_a_time(): void
    {
        $template = $this->template();
        $first = $this->draftOf($template);

        $this->addCheck($template, $first);
        $this->actingAs($this->engineer)
            ->post('/app/maintenance/templates/'.$template->id.'/versions/'.$first->id.'/publish');

        $this->actingAs($this->engineer)->post('/app/maintenance/templates/'.$template->id.'/draft');

        $this->actingAs($this->engineer)
            ->from('/app/maintenance/templates/'.$template->id)
            ->post('/app/maintenance/templates/'.$template->id.'/draft')
            ->assertSessionHasErrors('version');

        $this->assertSame(
            1,
            MaintenanceTemplateVersion::where('template_id', $template->id)->where('status', 'DRAFT')->count(),
        );
    }

    public function test_a_platform_checklist_cannot_be_edited(): void
    {
        $platform = MaintenanceTemplate::whereNull('company_id')->firstOrFail();

        $this->actingAs($this->engineer)
            ->get('/app/maintenance/templates/'.$platform->id.'/edit')
            ->assertForbidden();

        $this->actingAs($this->engineer)
            ->post('/app/maintenance/templates/'.$platform->id.'/draft')
            ->assertForbidden();
    }

    /**
     * A technician meets a checklist inside the work order they were handed,
     * not in the library. Authoring is somebody else's job entirely, and the
     * library is not on their menu at all.
     */
    public function test_a_technician_does_not_reach_the_checklist_library(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        $this->actingAs($technician)->get('/app/maintenance/templates')->assertForbidden();
        $this->actingAs($technician)->get('/app/maintenance/templates/create')->assertForbidden();
        $this->actingAs($technician)
            ->post('/app/maintenance/templates', ['name' => 'Mine', 'code' => 'MINE'])
            ->assertForbidden();

        $this->assertSame(0, MaintenanceTemplate::where('code', 'MINE')->count());
    }

    public function test_another_companys_checklist_is_not_reachable(): void
    {
        $template = $this->template();

        $other = TenantFixture::company('Beta Textiles Ltd', 'BTL');
        TenantFixture::factory($other, 'Their Unit', 'BTU');
        TenantFixture::actingAsTenant($other);
        $theirs = TenantFixture::user($other, 'MAINTENANCE_ENGINEER', 'eng@btl.test');

        $this->flushSession();

        $this->actingAs($theirs)
            ->get('/app/maintenance/templates/'.$template->id)
            ->assertNotFound();
    }
}
