<?php

declare(strict_types=1);

namespace Tests\Feature\WorkOrder;

use App\Modules\Asset\Models\Asset;
use App\Modules\Maintenance\Actions\CreateTemplateVersion;
use App\Modules\Maintenance\Actions\PublishTemplateVersion;
use App\Modules\Maintenance\Actions\SaveChecklistItems;
use App\Modules\Maintenance\Models\ChecklistItem;
use App\Modules\Maintenance\Models\MaintenanceTemplateVersion;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\WorkOrder\Actions\AssignTechnicians;
use App\Modules\WorkOrder\Actions\CreateWorkOrder;
use App\Modules\WorkOrder\Actions\RecordChecklistResult;
use App\Modules\WorkOrder\Actions\TransitionWorkOrder;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Shared\Files\Models\FileAttachment;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * Checklist execution: SRS 12, ERD Section 9 rules 1-4.
 *
 * These four rules are why a completed checklist means anything. Without them
 * the record shows what the form allowed, not what the machine was like.
 */
class ChecklistExecutionTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Asset $asset;

    private RecordChecklistResult $record;

    private TransitionWorkOrder $transition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);
        $this->record = app(RecordChecklistResult::class);
        $this->transition = app(TransitionWorkOrder::class);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function inProgress(array $items = []): WorkOrder
    {
        $version = WorkOrderFixture::publishedChecklist($this->delta, $items);

        $workOrder = app(CreateWorkOrder::class)->handle([
            'asset_id' => $this->asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'PREVENTIVE')->firstOrFail()->id,
            'template_version_id' => $version->id,
            'title' => 'Monthly service',
        ], 'user-a');

        $workOrder = $this->transition->schedule($workOrder, 'user-a');

        $grade = WorkOrderFixture::grade($this->delta);
        $technician = WorkOrderFixture::technician($this->delta, $this->dhaka, $grade);

        app(AssignTechnicians::class)->handle($workOrder, [$technician->id], 'user-a');

        return $this->transition->start($workOrder->fresh(), 'user-a');
    }

    private function item(WorkOrder $workOrder, int $sequence = 1): ChecklistItem
    {
        return ChecklistItem::where('template_version_id', $workOrder->template_version_id)
            ->where('sequence', $sequence)
            ->firstOrFail();
    }

    public function test_an_answer_is_recorded_against_the_frozen_version(): void
    {
        $workOrder = $this->inProgress();
        $item = $this->item($workOrder);

        $result = $this->record->handle($workOrder, [
            'checklist_item_id' => $item->id,
            'result' => 'PASS',
        ], 'user-a');

        $this->assertSame('PASS', $result->result);
        $this->assertSame($item->id, $result->checklist_item_id);
        $this->assertSame('user-a', $result->completed_by);
        $this->assertNotNull($result->completed_at);
    }

    public function test_an_item_from_another_checklist_is_refused(): void
    {
        $workOrder = $this->inProgress();

        $other = WorkOrderFixture::publishedChecklist($this->delta, [
            ['label' => 'Something else entirely', 'input_type' => 'PASS_FAIL', 'required' => true],
        ], 'PM-OTHER');

        $foreignItem = $other->items()->firstOrFail();

        // Accepting it would file an answer from a different checklist against
        // this job, and the record would no longer reproduce what was executed.
        $this->expectException(ValidationException::class);
        $this->record->handle($workOrder, [
            'checklist_item_id' => $foreignItem->id,
            'result' => 'PASS',
        ], 'user-a');
    }

    public function test_a_checklist_cannot_be_answered_before_work_starts(): void
    {
        $version = WorkOrderFixture::publishedChecklist($this->delta);

        $workOrder = app(CreateWorkOrder::class)->handle([
            'asset_id' => $this->asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'PREVENTIVE')->firstOrFail()->id,
            'template_version_id' => $version->id,
            'title' => 'Monthly service',
        ], 'user-a');

        try {
            $this->record->handle($workOrder, [
                'checklist_item_id' => $version->items()->firstOrFail()->id,
                'result' => 'PASS',
            ], 'user-a');
            $this->fail('A draft work order must not accept checklist answers.');
        } catch (ValidationException $e) {
            $this->assertSame(409, $e->status);
        }
    }

    /** Rule 3. */
    public function test_a_numeric_reading_outside_tolerance_is_stored_as_a_failure(): void
    {
        $workOrder = $this->inProgress([
            ['label' => 'Discharge pressure', 'input_type' => 'NUMERIC', 'unit' => 'bar',
                'tolerance_min' => '6', 'tolerance_max' => '8', 'required' => true],
        ]);

        $item = $this->item($workOrder);

        // The technician taps PASS, but the reading says otherwise. The reading
        // is the observation; the tick is an opinion.
        $result = $this->record->handle($workOrder, [
            'checklist_item_id' => $item->id,
            'result' => 'PASS',
            'numeric_value' => '9.4',
        ], 'user-a');

        $this->assertSame('FAIL', $result->result);
        $this->assertFalse($result->is_within_tolerance);

        $inRange = $this->record->handle($workOrder, [
            'checklist_item_id' => $item->id,
            'result' => 'PASS',
            'numeric_value' => '7.0',
        ], 'user-a');

        $this->assertSame('PASS', $inRange->result);
        $this->assertTrue($inRange->is_within_tolerance);
    }

    public function test_a_numeric_item_demands_a_reading(): void
    {
        $workOrder = $this->inProgress([
            ['label' => 'Belt tension', 'input_type' => 'NUMERIC', 'unit' => 'mm', 'required' => true],
        ]);

        // "PASS" with no number recorded is a checklist that certifies nothing.
        $this->expectException(ValidationException::class);
        $this->record->handle($workOrder, [
            'checklist_item_id' => $this->item($workOrder)->id,
            'result' => 'PASS',
        ], 'user-a');
    }

    /** Rule 1. */
    public function test_a_failure_on_a_note_demanding_item_needs_a_note(): void
    {
        $workOrder = $this->inProgress([
            ['label' => 'Emergency stop works', 'input_type' => 'PASS_FAIL', 'required' => true,
                'is_safety_item' => true, 'requires_note_on_fail' => true],
        ]);

        $item = $this->item($workOrder);

        try {
            $this->record->handle($workOrder, [
                'checklist_item_id' => $item->id,
                'result' => 'FAIL',
            ], 'user-a');
            $this->fail('A failure needing a note must be refused without one.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('observation', $e->errors());
        }

        $result = $this->record->handle($workOrder, [
            'checklist_item_id' => $item->id,
            'result' => 'FAIL',
            'observation' => 'Button jams, does not latch',
        ], 'user-a');

        $this->assertSame('FAIL', $result->result);
        $this->assertSame('Button jams, does not latch', $result->observation);
    }

    public function test_a_failure_on_a_photo_demanding_item_needs_a_photo(): void
    {
        $workOrder = $this->inProgress([
            ['label' => 'Pressure gauge reading legible', 'input_type' => 'PASS_FAIL', 'required' => true,
                'is_safety_item' => true, 'requires_attachment_on_fail' => true],
        ]);

        $item = $this->item($workOrder);

        try {
            $this->record->handle($workOrder, [
                'checklist_item_id' => $item->id,
                'result' => 'FAIL',
            ], 'user-a');
            $this->fail('A failure needing evidence must be refused without it.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('file_id', $e->errors());
        }

        $file = FileAttachment::create([
            'attachable_type' => 'work_order',
            'attachable_id' => $workOrder->id,
            'disk' => 'local',
            'path' => 'attachments/test/gauge.jpg',
            'original_name' => 'gauge.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 12345,
            'sha256' => str_repeat('a', 64),
        ]);

        $result = $this->record->handle($workOrder, [
            'checklist_item_id' => $item->id,
            'result' => 'FAIL',
            'file_id' => $file->id,
        ], 'user-a');

        $this->assertSame($file->id, $result->file_id);
    }

    public function test_a_photo_belonging_to_another_work_order_is_refused(): void
    {
        $workOrder = $this->inProgress([
            ['label' => 'Guard in place', 'input_type' => 'PASS_FAIL', 'required' => true,
                'requires_attachment_on_fail' => true],
        ]);

        // Evidence from a different job presented as evidence on this one.
        $foreign = FileAttachment::create([
            'attachable_type' => 'work_order',
            'attachable_id' => (string) Str::ulid(),
            'disk' => 'local',
            'path' => 'attachments/test/other.jpg',
            'original_name' => 'other.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 100,
            'sha256' => str_repeat('b', 64),
        ]);

        $this->expectException(ValidationException::class);
        $this->record->handle($workOrder, [
            'checklist_item_id' => $this->item($workOrder)->id,
            'result' => 'FAIL',
            'file_id' => $foreign->id,
        ], 'user-a');
    }

    /** Rule 2. */
    public function test_a_failure_raises_corrective_work_when_the_item_says_so(): void
    {
        $workOrder = $this->inProgress([
            ['label' => 'Earthing continuity checked', 'input_type' => 'PASS_FAIL', 'required' => true,
                'is_safety_item' => true, 'requires_note_on_fail' => true,
                'fail_creates_followup_work_order' => true],
        ]);

        $before = WorkOrder::count();

        $result = $this->record->handle($workOrder, [
            'checklist_item_id' => $this->item($workOrder)->id,
            'result' => 'FAIL',
            'observation' => 'Continuity reads open at the motor frame',
        ], 'user-a');

        $this->assertSame($before + 1, WorkOrder::count());
        $this->assertNotNull($result->followup_work_order_id);

        $followUp = WorkOrder::findOrFail($result->followup_work_order_id);

        $this->assertSame($this->asset->id, $followUp->asset_id);
        $this->assertSame('CHECKLIST_FAILURE', $followUp->source);
        // A failed safety check is not routine work.
        $this->assertSame('CRITICAL', $followUp->priority);
        // The observation travels with it: whoever picks it up needs to know
        // what was seen.
        $this->assertStringContainsString('Continuity reads open', $followUp->description);
    }

    public function test_correcting_an_answer_overwrites_it_rather_than_duplicating(): void
    {
        $workOrder = $this->inProgress([
            ['label' => 'Oil level', 'input_type' => 'NUMERIC', 'unit' => 'mm', 'required' => true],
        ]);

        $item = $this->item($workOrder);

        $this->record->handle($workOrder, [
            'checklist_item_id' => $item->id, 'result' => 'PASS', 'numeric_value' => '12',
        ], 'user-a');

        $corrected = $this->record->handle($workOrder, [
            'checklist_item_id' => $item->id, 'result' => 'PASS', 'numeric_value' => '21',
        ], 'user-a');

        // A mistyped reading is corrected, not left as a second truth.
        $this->assertSame(1, $workOrder->checklistResults()->count());
        $this->assertSame('21.0000', $corrected->numeric_value);
    }

    public function test_a_required_item_cannot_be_marked_not_applicable(): void
    {
        $workOrder = $this->inProgress();

        // This is how a checklist gets emptied without anyone skipping anything.
        $this->expectException(ValidationException::class);
        $this->record->handle($workOrder, [
            'checklist_item_id' => $this->item($workOrder)->id,
            'result' => 'NA',
        ], 'user-a');
    }

    public function test_completion_is_blocked_while_required_items_are_unanswered(): void
    {
        $workOrder = $this->inProgress([
            ['label' => 'Needle condition', 'input_type' => 'PASS_FAIL', 'required' => true],
            ['label' => 'Thread path clean', 'input_type' => 'PASS_FAIL', 'required' => true],
            ['label' => 'Photo after service', 'input_type' => 'PHOTO', 'required' => false],
        ]);

        $this->record->handle($workOrder, [
            'checklist_item_id' => $this->item($workOrder, 1)->id, 'result' => 'PASS',
        ], 'user-a');

        $progress = $workOrder->fresh()->checklistProgress();
        $this->assertSame(3, $progress['total']);
        $this->assertSame(1, $progress['answered']);
        $this->assertSame(1, $progress['required_remaining']);

        try {
            $this->transition->complete($workOrder->fresh(), 'user-a');
            $this->fail('Completing with unanswered required items must be refused.');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->status);
        }

        $this->record->handle($workOrder->fresh(), [
            'checklist_item_id' => $this->item($workOrder, 2)->id, 'result' => 'PASS',
        ], 'user-a');

        // The optional photo is still unanswered, and that is fine.
        $completed = $this->transition->complete($workOrder->fresh(), 'user-a');
        $this->assertSame('COMPLETED', $completed->status);
    }

    public function test_a_choice_item_only_accepts_a_listed_option(): void
    {
        $workOrder = $this->inProgress([
            ['label' => 'Belt condition', 'input_type' => 'CHOICE', 'required' => true,
                'options_json' => ['Good', 'Worn', 'Replace']],
        ]);

        $item = $this->item($workOrder);

        try {
            $this->record->handle($workOrder, [
                'checklist_item_id' => $item->id, 'result' => 'PASS', 'text_value' => 'a bit iffy',
            ], 'user-a');
            $this->fail('Free text against a fixed list must be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('text_value', $e->errors());
        }

        $result = $this->record->handle($workOrder, [
            'checklist_item_id' => $item->id, 'result' => 'PASS', 'text_value' => 'Worn',
        ], 'user-a');

        $this->assertSame('Worn', $result->text_value);
    }

    public function test_the_checklist_survives_a_later_template_edit(): void
    {
        $workOrder = $this->inProgress();
        $frozenVersionId = $workOrder->template_version_id;
        $item = $this->item($workOrder);

        $this->record->handle($workOrder, [
            'checklist_item_id' => $item->id, 'result' => 'PASS',
        ], 'user-a');

        // The planner publishes a new version with a different list.
        $version = MaintenanceTemplateVersion::findOrFail($frozenVersionId);
        $template = $version->template;

        $v2 = app(CreateTemplateVersion::class)->handle($template->fresh());
        app(SaveChecklistItems::class)->handle($v2, [
            ['label' => 'Completely different check', 'input_type' => 'PASS_FAIL', 'required' => true],
        ]);
        app(PublishTemplateVersion::class)->handle($v2->fresh());

        // The work order still reproduces exactly what its technician worked
        // through, which is the entire point of freezing the version.
        $this->assertSame($frozenVersionId, $workOrder->fresh()->template_version_id);
        $this->assertSame(
            'Needle and presser foot condition',
            $workOrder->fresh()->checklistResults()->first()->item->label,
        );
    }
}
