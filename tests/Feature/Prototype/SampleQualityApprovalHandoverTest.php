<?php

declare(strict_types=1);

namespace Tests\Feature\Prototype;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Merchandise\Models\SampleType;
use App\Modules\Notification\Models\Notification;
use App\Modules\Prototype\Models\Sample;
use App\Modules\Prototype\Models\SampleDevelopment;
use App\Modules\Prototype\Models\SampleRequisition;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Scopes\TenantScope;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Prototype Desk → Sample Quality & Approval, Handover & Delivery (AnnotechERP
 * 07/03): a developed sample is checked checkpoint by checkpoint, the verdict
 * moving it; a passed check opens merchandising review then buyer approval,
 * a rejection answered with a revision; buyer feedback alerts its assignee
 * until resolved; an approved sample is handed over within its quantity and
 * tracked to delivery.
 */
class SampleQualityApprovalHandoverTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    public function test_the_quality_verdict_moves_the_sample_and_only_the_latest_check_is_corrected(): void
    {
        $this->as()->getJson('/api/v1/prototype/quality-checks/options')->assertOk()->assertJsonCount(0, 'data.samples');
        $sample = $this->developedSample();
        $options = $this->as()->getJson('/api/v1/prototype/quality-checks/options')->assertOk()->json('data');
        $this->assertSame([$sample->sample_no], collect($options['samples'])->pluck('sample_no')->all());
        $this->assertCount(13, $options['checkpoints']);

        $form = ['sample_id' => $sample->id, 'check_date' => '2026-10-15'];
        $this->as()->postJson('/api/v1/prototype/quality-checks', ['sample_id' => $sample->id])->assertUnprocessable()
            ->assertJsonPath('errors.check_date.0', 'Check Date is required.')->assertJsonPath('errors.result.0', 'QC Result is required.');
        // Fail needs its defect; a Not OK checkpoint needs its own; a Major one cannot be passed outright.
        $this->as()->postJson('/api/v1/prototype/quality-checks', $form + ['result' => 'FAIL'])->assertUnprocessable()->assertJsonPath('errors.defect.0', 'Fail and Rework Required need the defect.');
        $this->as()->postJson('/api/v1/prototype/quality-checks', $form + ['result' => 'PASS_WITH_COMMENT', 'checkpoints' => [['checkpoint' => 'SEAM', 'result' => 'NOT_OK']]])
            ->assertUnprocessable()->assertJsonPath('errors.checkpoints.0', 'Seam is Not OK: describe the defect.');
        $this->as()->postJson('/api/v1/prototype/quality-checks', $form + ['result' => 'PASS', 'checkpoints' => [['checkpoint' => 'SEAM', 'result' => 'NOT_OK', 'defect' => 'Open seam', 'severity' => 'MAJOR']]])
            ->assertUnprocessable()->assertJsonPath('errors.result.0', 'Seam is a Major or Critical defect: the sample cannot be passed outright.');

        // Fail keeps it in Quality; every checkpoint is written, in checklist order.
        $fail = $this->as()->postJson('/api/v1/prototype/quality-checks', $form + ['result' => 'FAIL', 'severity' => 'MAJOR', 'defect' => 'Open seam at side',
            'checkpoints' => [['checkpoint' => 'SEAM', 'result' => 'NOT_OK', 'defect' => 'Open seam', 'severity' => 'MAJOR'], ['checkpoint' => 'MEASUREMENT', 'result' => 'OK']]])->assertCreated()->json('data');
        $this->assertSame(['QC-2026-00001', 1, 1, $this->owner->id, 13, 'MEASUREMENT', 'OK'], [$fail['qc_no'], $fail['version'], $fail['defects'], $fail['checked_by'], count($fail['checkpoints']), $fail['checkpoints'][0]['checkpoint'], $fail['checkpoints'][0]['result']]);
        $this->assertSame(['QUALITY', 'FAILED'], [$sample->fresh()->phase, $sample->fresh()->state]);

        // Rework Required sends it back to production and reopens its development.
        $rework = $this->as()->postJson('/api/v1/prototype/quality-checks', $form + ['result' => 'REWORK_REQUIRED', 'defect' => 'Resew both side seams'])->assertCreated()->json('data.qc_no');
        $this->assertSame(['DEVELOPMENT', 'REWORK'], [$sample->fresh()->phase, $sample->fresh()->state]);
        $this->assertSame('IN_PROGRESS', SampleDevelopment::query()->value('status'));
        $this->assertSame('IN_DEVELOPMENT', SampleRequisition::query()->value('status'));
        $this->as()->postJson('/api/v1/prototype/quality-checks', $form + ['result' => 'PASS'])->assertUnprocessable()->assertJsonPath('errors.sample_id.0', 'This sample is not waiting for a quality check.');
        // An earlier check is no longer corrected.
        $this->as()->putJson('/api/v1/prototype/quality-checks/QC-2026-00001', ['check_date' => '2026-10-15', 'result' => 'PASS'])->assertUnprocessable()
            ->assertJsonPath('errors.qc_no.0', 'Only the latest check of the current version can be corrected, and not once an approval has been raised on it.');

        // Finished again, it passes; the latest check is corrected in place.
        $this->as()->putJson('/api/v1/prototype/developments/DEV-2026-00001', ['dev_date' => '2026-10-12', 'status' => 'COMPLETED'])->assertOk();
        $this->assertSame(['QUALITY', 'PENDING'], [$sample->fresh()->phase, $sample->fresh()->state]);
        $pass = $this->as()->postJson('/api/v1/prototype/quality-checks', $form + ['check_date' => '2026-10-17', 'result' => 'PASS_WITH_COMMENT', 'remarks' => 'Minor thread ends'])->assertCreated()->json('data.qc_no');
        $this->assertSame(['APPROVAL', 'PENDING'], [$sample->fresh()->phase, $sample->fresh()->state]);
        $this->as()->putJson("/api/v1/prototype/quality-checks/{$pass}", ['check_date' => '2026-10-17', 'result' => 'PASS', 'checked_by' => $this->owner->id])->assertOk()->assertJsonPath('data.result', 'PASS');
        $this->assertSame([$pass, $rework, 'QC-2026-00001'], collect($this->as()->getJson('/api/v1/prototype/quality-checks')->assertOk()->json('data'))->pluck('qc_no')->all());

        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $this->as($technician)->getJson('/api/v1/prototype/quality-checks')->assertForbidden();
    }

    public function test_approval_needs_a_passed_check_and_review_before_buyer_and_a_rejection_is_answered_with_a_revision(): void
    {
        $sample = $this->developedSample();
        $form = ['sample_id' => $sample->id, 'submitted_date' => '2026-10-18'];
        $this->as()->postJson('/api/v1/prototype/approvals', $form + ['step' => 'MERCHANDISING_REVIEW'])->assertUnprocessable()->assertJsonPath('errors.sample_id.0', 'This sample is not waiting for approval.');
        $qc = $this->as()->postJson('/api/v1/prototype/quality-checks', ['sample_id' => $sample->id, 'check_date' => '2026-10-17', 'result' => 'PASS'])->assertCreated()->json('data.qc_no');
        $this->assertSame([$sample->sample_no], collect($this->as()->getJson('/api/v1/prototype/approvals/options')->json('data.samples'))->pluck('sample_no')->all());

        // Buyer approval waits for the merchandising review.
        $this->as()->postJson('/api/v1/prototype/approvals', $form)->assertUnprocessable()->assertJsonPath('errors.step.0', 'Step is required.');
        $this->as()->postJson('/api/v1/prototype/approvals', $form + ['step' => 'BUYER_APPROVAL'])->assertUnprocessable()->assertJsonPath('errors.step.0', 'Buyer approval needs an approved merchandising review on the same version.');
        $review = $this->as()->postJson('/api/v1/prototype/approvals', $form + ['step' => 'MERCHANDISING_REVIEW'])->assertCreated()->json('data');
        $this->assertSame(['APR-2026-00001', 'PENDING', $qc, null, false], [$review['approval_no'], $review['decision'], $review['qc_no'], $review['approval_date'], $review['decided']]);
        $this->assertSame(['APPROVAL', 'IN_PROGRESS'], [$sample->fresh()->phase, $sample->fresh()->state]);
        $this->as()->postJson('/api/v1/prototype/approvals', $form + ['step' => 'MERCHANDISING_REVIEW'])->assertUnprocessable()->assertJsonPath('errors.step.0', 'Merchandising Review is already pending or approved on this version.');
        // With an approval raised the check is no longer corrected.
        $this->as()->putJson("/api/v1/prototype/quality-checks/{$qc}", ['check_date' => '2026-10-17', 'result' => 'PASS'])->assertUnprocessable();

        // Deciding: the approval date defaults to today and the decision locks it.
        $decided = $this->as()->putJson('/api/v1/prototype/approvals/APR-2026-00001', $form + ['decision' => 'APPROVED', 'approval_by' => 'Anna (LPP QA)'])->assertOk()->json('data');
        $this->assertSame([now()->toDateString(), true], [$decided['approval_date'], $decided['decided']]);
        $this->as()->putJson('/api/v1/prototype/approvals/APR-2026-00001', $form + ['decision' => 'REJECTED', 'buyer_comment' => 'x'])->assertUnprocessable()->assertJsonPath('errors.decision.0', 'An approval that is Approved is locked.');

        // The buyer wants a revision: a comment is needed, and the revision answers it on version 2.
        $buyer = $this->as()->postJson('/api/v1/prototype/approvals', $form + ['step' => 'BUYER_APPROVAL'])->assertCreated()->json('data.approval_no');
        $this->as()->putJson("/api/v1/prototype/approvals/{$buyer}", $form + ['decision' => 'REVISION_REQUIRED'])->assertUnprocessable()->assertJsonPath('errors.buyer_comment.0', 'Rejected and Revision Required need a buyer or internal comment.');
        $this->as()->putJson("/api/v1/prototype/approvals/{$buyer}", $form + ['decision' => 'REVISION_REQUIRED', 'approval_date' => '2026-10-17', 'buyer_comment' => 'Shorten sleeve'])->assertUnprocessable()
            ->assertJsonPath('errors.approval_date.0', 'Approval Date cannot be before the Submitted Date.');
        $this->as()->putJson("/api/v1/prototype/approvals/{$buyer}", $form + ['decision' => 'REVISION_REQUIRED', 'approval_date' => '2026-10-20', 'buyer_comment' => 'Shorten sleeve'])
            ->assertOk()->assertJsonPath('data.answer_with_revision', true)->assertJsonPath('data.waiting_days', 2);
        $this->assertSame(['APPROVAL', 'REVISION_REQUIRED'], [$sample->fresh()->phase, $sample->fresh()->state]);
        $source = collect($this->as()->getJson('/api/v1/prototype/revisions/options')->json('data.approvals'))->firstWhere('no', $buyer);
        $this->assertSame([$sample->id, 'Shorten sleeve'], [$source['sample_id'], $source['comment']]);
        $rev = $this->as()->postJson('/api/v1/prototype/revisions', ['sample_id' => $sample->id, 'revision_date' => '2026-10-21', 'reason' => 'Buyer: shorten sleeve', 'source_approval_id' => $source['value']])->assertCreated()->json('data');
        $this->assertSame([2, $buyer], [$rev['new_version'], $rev['source_approval_no']]);
        $this->assertSame([2, 'REVISION'], [$sample->fresh()->version, $sample->fresh()->phase]);

        // Version 2 starts its approvals afresh: the version-1 review does not carry over.
        $this->as()->postJson('/api/v1/prototype/developments', ['sample_id' => $sample->id, 'dev_date' => '2026-10-22', 'status' => 'COMPLETED'])->assertCreated();
        $this->as()->postJson('/api/v1/prototype/quality-checks', ['sample_id' => $sample->id, 'check_date' => '2026-10-23', 'result' => 'PASS'])->assertCreated();
        $v2 = ['sample_id' => $sample->id, 'submitted_date' => '2026-10-23'];
        $this->as()->postJson('/api/v1/prototype/approvals', $v2 + ['step' => 'BUYER_APPROVAL'])->assertUnprocessable();
        $r2 = $this->as()->postJson('/api/v1/prototype/approvals', $v2 + ['step' => 'MERCHANDISING_REVIEW', 'decision' => 'APPROVED'])->assertCreated()->json('data');
        $this->assertSame([2, true], [$r2['version'], $r2['decided']]);
        $this->as()->postJson('/api/v1/prototype/approvals', $v2 + ['step' => 'BUYER_APPROVAL', 'decision' => 'APPROVED_WITH_COMMENT', 'buyer_comment' => 'OK for bulk'])->assertCreated();
        $this->assertSame(['HANDOVER', 'READY'], [$sample->fresh()->phase, $sample->fresh()->state]);

        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $this->as($manager)->getJson('/api/v1/prototype/approvals')->assertOk()->assertJsonCount(4, 'data');
        $this->as($manager)->postJson('/api/v1/prototype/approvals', $v2 + ['step' => 'MERCHANDISING_REVIEW'])->assertForbidden();
    }

    public function test_feedback_alerts_its_assignee_until_resolved_and_a_closed_one_is_locked(): void
    {
        $sample = $this->developedSample();
        $maker = TenantFixture::user($this->delta, 'FACTORY_ADMIN', 'maker@delta.test');
        $form = ['sample_id' => $sample->id, 'feedback_date' => '2026-10-19', 'feedback_type' => 'CORRECTION', 'comment' => 'Collar too wide', 'measurement_change' => 'Collar width -1 cm'];

        $this->as()->postJson('/api/v1/prototype/feedback', ['sample_id' => $sample->id])->assertUnprocessable()
            ->assertJsonPath('errors.feedback_date.0', 'Feedback Date is required.')->assertJsonPath('errors.feedback_type.0', 'Feedback Type is required.');
        $this->as()->postJson('/api/v1/prototype/feedback', $form + ['action_required' => true])->assertUnprocessable()->assertJsonPath('errors.assigned_to.0', 'Action Required needs Assigned To and a Due Date.');
        $this->as()->postJson('/api/v1/prototype/feedback', $form + ['action_required' => true, 'assigned_to' => $maker->id, 'due_date' => '2026-10-18'])->assertUnprocessable()
            ->assertJsonPath('errors.due_date.0', 'Due Date cannot be before the Feedback Date.');
        $fb = $this->as()->postJson('/api/v1/prototype/feedback', $form + ['action_required' => true, 'assigned_to' => $maker->id, 'due_date' => '2026-10-20'])->assertCreated()->json('data');
        $this->assertSame(['FB-2026-00001', 'OPEN', 1, true, $maker->name], [$fb['feedback_no'], $fb['status'], $fb['version'], $fb['action_required'], $fb['assigned_to_name']]);
        // Past its due date and still open, it is overdue.
        $this->assertSame((int) now()->startOfDay()->diffInDays('2026-10-20', true), $fb['overdue_days'] ?? 0);
        $alerts = fn () => Notification::withoutGlobalScope(TenantScope::class)->where('event_type', 'SAMPLE_FEEDBACK_ACTION')->where('user_id', $maker->id);
        $this->assertSame(1, $alerts()->count());

        // Saving again without changing the assignee does not alert twice; Resolved clears the alert.
        $this->as()->putJson('/api/v1/prototype/feedback/FB-2026-00001', $form + ['action_required' => true, 'assigned_to' => $maker->id, 'due_date' => '2026-10-20', 'status' => 'IN_PROGRESS'])->assertOk();
        $this->assertSame(1, $alerts()->count());
        $resolved = $this->as()->putJson('/api/v1/prototype/feedback/FB-2026-00001', $form + ['action_required' => true, 'assigned_to' => $maker->id, 'due_date' => '2026-10-20', 'status' => 'RESOLVED'])->assertOk()->json('data');
        $this->assertNull($resolved['overdue_days']);
        $this->assertSame(0, $alerts()->whereNull('read_at')->count());
        $this->as()->putJson('/api/v1/prototype/feedback/FB-2026-00001', $form + ['status' => 'CLOSED'])->assertOk();
        $this->as()->putJson('/api/v1/prototype/feedback/FB-2026-00001', $form + ['status' => 'OPEN'])->assertUnprocessable()->assertJsonPath('errors.status.0', 'A closed feedback is locked.');

        // A related approval must be the same sample's.
        $other = $this->secondSample();
        $this->as()->postJson('/api/v1/prototype/quality-checks', ['sample_id' => $other->id, 'check_date' => '2026-10-17', 'result' => 'PASS'])->assertCreated();
        $apr = $this->as()->postJson('/api/v1/prototype/approvals', ['sample_id' => $other->id, 'submitted_date' => '2026-10-18', 'step' => 'MERCHANDISING_REVIEW'])->assertCreated()->json('data.id');
        $this->as()->postJson('/api/v1/prototype/feedback', $form + ['approval_id' => $apr])->assertUnprocessable()->assertJsonPath('errors.approval_id.0', 'The related approval belongs to another sample.');
        $this->as()->postJson('/api/v1/prototype/feedback', array_merge($form, ['sample_id' => $other->id, 'approval_id' => $apr, 'feedback_type' => 'COMMENT']))->assertCreated()->assertJsonPath('data.approval_no', 'APR-2026-00001');
        $this->assertCount(2, $this->as()->getJson('/api/v1/prototype/revisions/options')->json('data.feedback'));
    }

    public function test_an_approved_sample_is_handed_over_within_its_quantity_and_tracked_to_delivery(): void
    {
        $sample = $this->developedSample(qty: 3);
        $this->as()->postJson('/api/v1/prototype/quality-checks', ['sample_id' => $sample->id, 'check_date' => '2026-10-17', 'result' => 'PASS'])->assertCreated();
        $form = ['sample_id' => $sample->id, 'qty' => 2, 'handover_date' => '2026-10-20', 'handover_to' => 'LPP Buying House'];
        // Its type needs buyer approval, which it does not have yet.
        $this->as()->postJson('/api/v1/prototype/handovers', $form)->assertUnprocessable()->assertJsonPath('errors.sample_id.0', 'This sample does not have the approval its type requires for handover.');
        $this->as()->postJson('/api/v1/prototype/approvals', ['sample_id' => $sample->id, 'submitted_date' => '2026-10-18', 'step' => 'MERCHANDISING_REVIEW', 'decision' => 'APPROVED'])->assertCreated();
        $this->as()->postJson('/api/v1/prototype/approvals', ['sample_id' => $sample->id, 'submitted_date' => '2026-10-18', 'step' => 'BUYER_APPROVAL', 'decision' => 'APPROVED'])->assertCreated();
        $this->assertSame([$sample->sample_no], collect($this->as()->getJson('/api/v1/prototype/handovers/options')->json('data.samples'))->pluck('sample_no')->all());

        $this->as()->postJson('/api/v1/prototype/handovers', ['sample_id' => $sample->id])->assertUnprocessable()
            ->assertJsonPath('errors.qty.0', 'Handover Qty must be at least 1.')->assertJsonPath('errors.handover_to.0', 'Handover To is required.');
        $this->as()->postJson('/api/v1/prototype/handovers', array_merge($form, ['qty' => 4]))->assertUnprocessable()->assertJsonPath('errors.qty.0', 'No more than the sample quantity (3) can be handed over: 3 left.');
        $ho = $this->as()->postJson('/api/v1/prototype/handovers', $form + ['expected_delivery' => '2026-10-21'])->assertCreated()->json('data');
        $this->assertSame(['HO-2026-00001', 'READY', $this->owner->id, 3, true], [$ho['handover_no'], $ho['status'], $ho['handover_from'], $ho['sample_qty'], $ho['removable']]);
        $this->as()->postJson('/api/v1/prototype/handovers', $form)->assertUnprocessable()->assertJsonPath('errors.qty.0', 'No more than the sample quantity (3) can be handed over: 1 left.');

        // Each status asks for what proves it.
        $url = '/api/v1/prototype/handovers/HO-2026-00001';
        $this->as()->putJson($url, $form + ['status' => 'COURIERED', 'courier' => 'DHL'])->assertUnprocessable()->assertJsonPath('errors.courier.0', 'Couriered needs the courier and the tracking number.');
        $this->as()->putJson($url, $form + ['status' => 'COURIERED', 'courier' => 'DHL', 'tracking_no' => '1Z999'])->assertOk()->assertJsonPath('data.removable', false);
        $this->as()->deleteJson($url)->assertUnprocessable()->assertJsonPath('errors.status.0', 'Only a Ready handover can be removed.');
        $this->as()->putJson($url, $form + ['status' => 'DELIVERED', 'courier' => 'DHL', 'tracking_no' => '1Z999'])->assertUnprocessable()->assertJsonPath('errors.actual_delivery.0', 'Delivered needs the actual delivery date.');
        $this->as()->putJson($url, $form + ['status' => 'DELIVERED', 'actual_delivery' => '2026-10-19'])->assertUnprocessable()->assertJsonPath('errors.actual_delivery.0', 'Actual Delivery cannot be before the Handover Date.');
        $delivered = $this->as()->putJson($url, $form + ['status' => 'DELIVERED', 'expected_delivery' => '2026-10-21', 'actual_delivery' => '2026-10-23'])->assertOk()->json('data');
        $this->assertSame(2, $delivered['delay_days']);
        $this->assertSame(['HANDOVER', 'DELIVERED'], [$sample->fresh()->phase, $sample->fresh()->state]);
        $this->as()->putJson($url, $form + ['status' => 'RECEIVED', 'received_by' => 'Anna'])->assertUnprocessable()->assertJsonPath('errors.received_by.0', 'Received needs who received it and when.');
        $this->as()->putJson($url, $form + ['status' => 'RECEIVED', 'actual_delivery' => '2026-10-23', 'received_by' => 'Anna', 'received_date' => '2026-10-23'])->assertOk()->assertJsonPath('data.locked', true);
        $this->as()->putJson($url, $form + ['status' => 'READY'])->assertUnprocessable()->assertJsonPath('errors.status.0', 'A received handover is locked.');

        // A Ready one is removed and its quantity freed.
        $last = $this->as()->postJson('/api/v1/prototype/handovers', array_merge($form, ['qty' => 1]))->assertCreated()->json('data.handover_no');
        $this->as()->deleteJson("/api/v1/prototype/handovers/{$last}")->assertOk();
        $this->assertSame(['HO-2026-00001'], collect($this->as()->getJson('/api/v1/prototype/handovers')->json('data'))->pluck('handover_no')->all());
        $this->assertSame('DELIVERED', $sample->fresh()->state);
    }

    public function test_a_type_without_buyer_approval_is_handed_over_once_through_qc(): void
    {
        $sample = $this->developedSample();
        $typeId = SampleRequisition::query()->value('sample_type_id');
        SampleType::query()->whereKey($typeId)->update(['buyer_approval_required' => false]);
        $form = ['sample_id' => $sample->id, 'qty' => 1, 'handover_date' => '2026-10-20', 'handover_to' => 'Merchandising'];
        $this->as()->postJson('/api/v1/prototype/handovers', $form)->assertUnprocessable();
        $this->as()->postJson('/api/v1/prototype/quality-checks', ['sample_id' => $sample->id, 'check_date' => '2026-10-17', 'result' => 'PASS'])->assertCreated();
        $this->as()->postJson('/api/v1/prototype/handovers', $form)->assertCreated();
        $this->assertSame(['HANDOVER', 'READY'], [$sample->fresh()->phase, $sample->fresh()->state]);

        // Needing neither QC nor buyer approval, a finished development is enough.
        $other = $this->secondSample();
        SampleType::query()->whereKey($typeId)->update(['qc_required' => false]);
        $this->as()->postJson('/api/v1/prototype/handovers', array_merge($form, ['sample_id' => $other->id]))->assertCreated();
    }

    /** A sample through its requisition and a completed development: waiting in Quality. */
    private function developedSample(int $qty = 1): Sample
    {
        [$buyer, $style] = $this->buyerStyleOrder();
        $type = collect($this->as()->getJson('/api/v1/prototype/requisitions/options')->json('data.sample_types'))->firstWhere('label', 'Fit Sample (FIT)');
        $this->as()->postJson('/api/v1/prototype/requisitions', ['req_date' => '2026-10-10', 'buyer_id' => $buyer, 'style_id' => $style, 'sample_type_id' => $type['value'], 'qty' => $qty, 'required_date' => '2026-10-30'])->assertCreated();

        return $this->approveAndDevelop('SR-2026-00001');
    }

    /** A second requisition of the same buyer, style and type, developed. */
    private function secondSample(): Sample
    {
        $first = SampleRequisition::query()->firstOrFail();
        $no = $this->as()->postJson('/api/v1/prototype/requisitions', ['req_date' => '2026-10-10', 'buyer_id' => $first->buyer_id, 'style_id' => $first->style_id, 'sample_type_id' => $first->sample_type_id, 'qty' => 1, 'required_date' => '2026-10-30'])
            ->assertCreated()->json('data.req_no');

        return $this->approveAndDevelop($no);
    }

    private function approveAndDevelop(string $req): Sample
    {
        $this->as()->postJson("/api/v1/prototype/requisitions/{$req}/submit")->assertOk();
        foreach (range(1, 3) as $i) {
            $this->as()->postJson("/api/v1/prototype/requisitions/{$req}/decide", ['decision' => 'APPROVE'])->assertOk();
        }
        $sample = Sample::query()->where('requisition_id', SampleRequisition::query()->where('req_no', $req)->value('id'))->firstOrFail();
        $this->as()->postJson('/api/v1/prototype/developments', ['sample_id' => $sample->id, 'dev_date' => '2026-10-12', 'status' => 'COMPLETED'])->assertCreated();

        return $sample->fresh();
    }

    /** @return array{string, string, string} buyer, style and a confirmed order for LPP */
    private function buyerStyleOrder(): array
    {
        $office = $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => 'LPP Office', 'office_type' => 'HEAD_OFFICE'])->json('data.id');
        $country = OriginCountry::query()->firstOrCreate(['company_id' => $this->delta->id, 'code' => '616'], ['name' => 'Poland', 'active' => true])->id;
        $buyer = $this->as()->postJson('/api/v1/erp/buyers', ['code' => 'LPP', 'name' => 'LPP Spolka Akcyjna', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'country_id' => $country, 'office_ids' => [$office]])
            ->assertCreated()->json('data.id');
        $size = $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'M', 'name' => 'M'])->json('data.id');
        $style = $this->as()->postJson('/api/v1/merchandise/styles', ['buyer_id' => $buyer, 'buyer_office_id' => $office, 'style_ref' => 'SS27-TEE01', 'description' => 'Summer Set', 'qty_unit' => 'PCS', 'pcs_per_unit' => 1, 'status' => 'DEVELOPMENT', 'lines' => [['size_ids' => [$size]]]])
            ->assertCreated()->json('data.id');
        $order = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => '529', 'style_id' => $style, 'total_qty' => 1000, 'currency' => 'usd', 'unit_price' => 2])->assertCreated()->json('data.id');
        Order::query()->whereKey($order)->update(['status' => 'CONFIRMED']);

        return [$buyer, $style, $order];
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
