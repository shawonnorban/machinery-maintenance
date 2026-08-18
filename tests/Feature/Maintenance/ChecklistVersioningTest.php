<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenance;

use App\Modules\Maintenance\Actions\CreateTemplateVersion;
use App\Modules\Maintenance\Actions\PublishTemplateVersion;
use App\Modules\Maintenance\Actions\SaveChecklistItems;
use App\Modules\Maintenance\Models\MaintenanceTemplate;
use App\Modules\Maintenance\Models\MaintenanceTemplateVersion;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Template versioning: SRS 12, API 5.2.
 *
 * The rule that matters: a work order closed two years ago must still
 * reproduce the exact checklist its technician worked through, even though
 * the template has changed since.
 */
class ChecklistVersioningTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private MaintenanceTemplate $template;

    private SaveChecklistItems $saveItems;

    private PublishTemplateVersion $publish;

    private CreateTemplateVersion $newVersion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::actingAsTenant($this->delta);

        $this->template = MaintenanceTemplate::create([
            'company_id' => $this->delta->id,
            'name' => 'Lockstitch monthly PM',
            'code' => 'PM-LOCKSTITCH',
            'status' => 'ACTIVE',
        ]);

        $this->saveItems = app(SaveChecklistItems::class);
        $this->publish = app(PublishTemplateVersion::class);
        $this->newVersion = app(CreateTemplateVersion::class);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(int $count = 3): array
    {
        $items = [];

        for ($i = 1; $i <= $count; $i++) {
            $items[] = ['label' => "Check item {$i}", 'input_type' => 'PASS_FAIL', 'required' => true];
        }

        return $items;
    }

    private function publishedVersion(int $items = 3): MaintenanceTemplateVersion
    {
        $version = $this->newVersion->handle($this->template);
        $this->saveItems->handle($version, $this->items($items));

        return $this->publish->handle($version->fresh());
    }

    public function test_a_new_template_starts_at_version_one(): void
    {
        $version = $this->newVersion->handle($this->template);

        $this->assertSame(1, $version->version_number);
        $this->assertSame('DRAFT', $version->status);
        $this->assertTrue($version->isEditable());
    }

    public function test_a_draft_accepts_items(): void
    {
        $version = $this->newVersion->handle($this->template);
        $saved = $this->saveItems->handle($version, $this->items(4));

        $this->assertCount(4, $saved->items);
        // Sequence comes from submitted order, not from the client.
        $this->assertSame([1, 2, 3, 4], $saved->items->pluck('sequence')->all());
    }

    public function test_publishing_freezes_the_version(): void
    {
        $version = $this->publishedVersion();

        $this->assertSame('PUBLISHED', $version->status);
        $this->assertNotNull($version->published_at);
        $this->assertFalse($version->isEditable());
    }

    public function test_a_published_version_cannot_be_edited(): void
    {
        $version = $this->publishedVersion();

        // Editing it would silently rewrite what a technician certified.
        try {
            $this->saveItems->handle($version, $this->items(5));
            $this->fail('A published version must not accept edits.');
        } catch (ValidationException $e) {
            $this->assertSame(409, $e->status);
        }

        $this->assertCount(3, $version->fresh()->items);
    }

    public function test_editing_a_published_template_creates_a_new_draft_seeded_from_it(): void
    {
        $v1 = $this->publishedVersion(3);

        $v2 = $this->newVersion->handle($this->template->fresh());

        $this->assertSame(2, $v2->version_number);
        $this->assertSame('DRAFT', $v2->status);
        // Editing a 14-item checklist must not mean retyping 14 items.
        $this->assertCount(3, $v2->items);
        $this->assertSame(
            $v1->items->pluck('label')->all(),
            $v2->items->pluck('label')->all(),
        );
    }

    public function test_publishing_a_new_version_archives_the_previous_one(): void
    {
        $v1 = $this->publishedVersion(3);

        $v2 = $this->newVersion->handle($this->template->fresh());
        $this->saveItems->handle($v2, $this->items(5));
        $v2 = $this->publish->handle($v2->fresh());

        // Archived, never deleted: closed work orders still resolve it.
        $this->assertSame('ARCHIVED', $v1->fresh()->status);
        $this->assertNotNull($v1->fresh()->effective_to);
        $this->assertSame('PUBLISHED', $v2->status);
        $this->assertSame($v1->id, $v2->supersedes_version_id);

        // The old version keeps its own items untouched.
        $this->assertCount(3, $v1->fresh()->items);
        $this->assertCount(5, $v2->fresh()->items);
    }

    public function test_the_current_version_is_the_latest_published_one(): void
    {
        $v1 = $this->publishedVersion(3);
        $draft = $this->newVersion->handle($this->template->fresh());

        // A plan must never bind to a draft: the checklist could still change
        // underneath it.
        $this->assertSame($v1->id, $this->template->fresh()->currentVersion()->id);
        $this->assertSame($draft->id, $this->template->fresh()->draftVersion()->id);
    }

    public function test_only_one_draft_may_be_open_at_a_time(): void
    {
        $this->newVersion->handle($this->template);

        // Two open drafts makes "which one am I editing" unanswerable.
        try {
            $this->newVersion->handle($this->template->fresh());
            $this->fail('A second concurrent draft should be refused.');
        } catch (ValidationException $e) {
            $this->assertSame(409, $e->status);
        }
    }

    public function test_an_empty_checklist_cannot_be_published(): void
    {
        $version = $this->newVersion->handle($this->template);

        $this->expectException(ValidationException::class);
        $this->publish->handle($version);
    }

    public function test_a_checklist_with_no_required_item_cannot_be_published(): void
    {
        $version = $this->newVersion->handle($this->template);

        $this->saveItems->handle($version, [
            ['label' => 'Optional look', 'input_type' => 'PASS_FAIL', 'required' => false],
        ]);

        // Completion is gated on required items. With none, the checklist can
        // be closed without a single answer, which makes it decorative.
        $this->expectException(ValidationException::class);
        $this->publish->handle($version->fresh());
    }

    public function test_publishing_twice_is_refused(): void
    {
        $version = $this->publishedVersion();

        try {
            $this->publish->handle($version);
            $this->fail('Publishing an already published version should be refused.');
        } catch (ValidationException $e) {
            $this->assertSame(409, $e->status);
        }
    }

    public function test_a_tolerance_requires_a_numeric_item(): void
    {
        $version = $this->newVersion->handle($this->template);

        // A tolerance on pass/fail is silently ignored at execution, which is
        // worse than refusing it here.
        $this->expectException(ValidationException::class);
        $this->saveItems->handle($version, [
            ['label' => 'Belt tension', 'input_type' => 'PASS_FAIL', 'tolerance_min' => '1', 'tolerance_max' => '5'],
        ]);
    }

    public function test_an_inverted_tolerance_is_refused(): void
    {
        $version = $this->newVersion->handle($this->template);

        $this->expectException(ValidationException::class);
        $this->saveItems->handle($version, [
            ['label' => 'Pressure', 'input_type' => 'NUMERIC', 'tolerance_min' => '9', 'tolerance_max' => '2'],
        ]);
    }

    public function test_a_choice_item_needs_options(): void
    {
        $version = $this->newVersion->handle($this->template);

        $this->expectException(ValidationException::class);
        $this->saveItems->handle($version, [
            ['label' => 'Condition', 'input_type' => 'CHOICE'],
        ]);
    }

    public function test_a_numeric_reading_outside_tolerance_is_a_fail(): void
    {
        $version = $this->newVersion->handle($this->template);

        $saved = $this->saveItems->handle($version, [
            ['label' => 'Discharge pressure', 'input_type' => 'NUMERIC', 'unit' => 'bar',
                'tolerance_min' => '6', 'tolerance_max' => '8', 'required' => true],
        ]);

        $item = $saved->items->first();

        $this->assertTrue($item->isWithinTolerance('7.0'));
        $this->assertFalse($item->isWithinTolerance('5.9'));
        $this->assertFalse($item->isWithinTolerance('8.1'));
        $this->assertNull($item->isWithinTolerance(null));
    }

    public function test_the_seeded_garment_templates_are_published_and_usable(): void
    {
        $seeded = MaintenanceTemplate::whereNull('company_id')->get();

        $this->assertCount(5, $seeded);

        foreach ($seeded as $template) {
            $current = $template->currentVersion();

            $this->assertNotNull($current, "{$template->code} has no published version.");
            $this->assertGreaterThan(0, $current->items()->count());
            $this->assertGreaterThan(0, $current->requiredItemCount());
        }
    }

    public function test_seeded_safety_items_demand_evidence_on_failure(): void
    {
        $boiler = MaintenanceTemplate::whereNull('company_id')
            ->where('code', 'INSP-BOILER-WEEKLY')
            ->firstOrFail();

        $safety = $boiler->currentVersion()->items->where('is_safety_item', true);

        $this->assertGreaterThan(0, $safety->count());

        foreach ($safety as $item) {
            // Boiler inspection is regulated. An unsupported pass is worthless
            // in an audit (Seed Catalog 9.3).
            $this->assertTrue($item->requires_attachment_on_fail, "{$item->label} should demand a photo on fail.");
            $this->assertTrue($item->requires_note_on_fail, "{$item->label} should demand a note on fail.");
            $this->assertTrue($item->fail_creates_followup_work_order, "{$item->label} should raise follow-up work.");
        }
    }

    public function test_seeded_numeric_items_carry_a_unit(): void
    {
        $generator = MaintenanceTemplate::whereNull('company_id')
            ->where('code', 'PM-GENERATOR-MONTHLY')
            ->firstOrFail();

        $numeric = $generator->currentVersion()->items->where('input_type', 'NUMERIC');

        $this->assertGreaterThan(0, $numeric->count());

        foreach ($numeric as $item) {
            // A reading of "12" without a unit is ambiguous forever after.
            $this->assertNotNull($item->unit, "{$item->label} records a number with no unit.");
        }
    }
}
