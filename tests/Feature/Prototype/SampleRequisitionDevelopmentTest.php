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
use App\Modules\Tenancy\Models\Company;
use App\Shared\Scopes\TenantScope;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Prototype Desk → Sample Planning & Development (AnnotechERP 07/03): a
 * requisition goes Draft → each approval level in turn, a rejection needing
 * a reason and sending it back; the sample manager's approval mints the
 * sample and assigns it; one development per version works its fourteen
 * points to completion and locks; a revision moves the sample on a version.
 */
class SampleRequisitionDevelopmentTest extends TestCase
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

    public function test_a_requisition_climbs_its_approval_levels_and_mints_an_assigned_sample(): void
    {
        [$buyer, $style, $order] = $this->buyerStyleOrder();
        $options = $this->as()->getJson('/api/v1/prototype/requisitions/options')->assertOk()->json('data');
        $pp = collect($options['sample_types'])->firstWhere('label', 'PP Sample (PP)');
        $this->assertSame([$style], collect($options['styles'])->where('buyer_id', $buyer)->pluck('value')->all());
        $maker = TenantFixture::user($this->delta, 'FACTORY_ADMIN', 'maker@delta.test');

        // Brickland's message on an empty save; an order-bound type wants its order.
        $this->as()->postJson('/api/v1/prototype/requisitions', [])->assertUnprocessable()->assertJsonPath('errors.req_date.0', 'Requisition Date is required.');
        SampleType::query()->whereKey($pp['value'])->update(['order_required' => true]);
        $form = ['req_date' => '2026-10-10', 'buyer_id' => $buyer, 'style_id' => $style, 'sample_type_id' => $pp['value'], 'qty' => 2, 'required_date' => '2026-10-24', 'priority' => 'URGENT'];
        $this->as()->postJson('/api/v1/prototype/requisitions', $form)->assertUnprocessable()->assertJsonPath('errors.order_id.0', 'PP Sample samples are raised against an order: pick the Order No.');
        $row = $this->as()->postJson('/api/v1/prototype/requisitions', $form + ['order_id' => $order])->assertCreated()->json('data');
        // The stage comes from the sample type; the requester is the signed-in user.
        $this->assertSame(['SR-2026-00001', 'DRAFT', 'PRE_ORDER', 'URGENT', $this->owner->id], [$row['req_no'], $row['status'], $row['sample_stage'], $row['priority'], $row['requested_by']]);
        $url = '/api/v1/prototype/requisitions/SR-2026-00001';

        $this->as()->postJson("{$url}/decide", ['decision' => 'APPROVE'])->assertUnprocessable();
        $this->as()->postJson("{$url}/submit")->assertOk()->assertJsonPath('data.status', 'PENDING_APPROVAL')->assertJsonPath('data.pending_level', 'MERCHANDISER');
        $this->as()->postJson("{$url}/decide", ['decision' => 'APPROVE', 'comments' => 'Need 2 pcs'])->assertOk()->assertJsonPath('data.pending_level', 'MERCHANDISING_MANAGER');
        // Assigned To belongs to the sample manager; a rejection needs its reason and goes back.
        $this->as()->postJson("{$url}/decide", ['decision' => 'APPROVE', 'assigned_to' => $maker->id])->assertUnprocessable()->assertJsonPath('errors.assigned_to.0', 'Assigned To is set at the Sample Manager level only.');
        $this->as()->postJson("{$url}/decide", ['decision' => 'REJECT'])->assertUnprocessable()->assertJsonPath('errors.rejection_reason.0', 'A rejection needs a reason.');
        $this->as()->postJson("{$url}/decide", ['decision' => 'REJECT', 'rejection_reason' => 'Wrong fabric'])->assertOk()->assertJsonPath('data.status', 'REJECTED');
        // Saving a rejected one returns it to Draft; it climbs again.
        $this->as()->putJson($url, array_merge($form, ['order_id' => $order, 'qty' => 3]))->assertOk()->assertJsonPath('data.status', 'DRAFT')->assertJsonPath('data.qty', 3);
        $this->as()->postJson("{$url}/submit")->assertOk();
        foreach (['MERCHANDISER', 'MERCHANDISING_MANAGER'] as $level) {
            $this->as()->postJson("{$url}/decide", ['decision' => 'APPROVE'])->assertOk();
        }
        $done = $this->as()->postJson("{$url}/decide", ['decision' => 'APPROVE', 'assigned_to' => $maker->id, 'comments' => 'Start Monday'])->assertOk()->json('data');
        $this->assertSame(['ASSIGNED', null, 'SMP-'.now()->format('Y').'-00001', $maker->id], [$done['status'], $done['pending_level'], $done['sample_no'], $done['assigned_to']]);
        $this->assertSame(['MERCHANDISER:APPROVED', 'MERCHANDISING_MANAGER:REJECTED', 'MERCHANDISER:APPROVED', 'MERCHANDISING_MANAGER:APPROVED', 'SAMPLE_MANAGER:APPROVED'],
            array_map(fn ($d) => $d['level'].':'.$d['decision'], $done['decisions']));
        $this->assertSame('ASSIGNED', Sample::query()->value('state'));
        $this->assertSame(1, Notification::withoutGlobalScope(TenantScope::class)->where('event_type', 'SAMPLE_ASSIGNED')->where('user_id', $maker->id)->count());
        // Once approved only Assigned To is taken from the form; a sample in development is not cancelled.
        $this->as()->putJson($url, ['qty' => 99, 'assigned_to' => $this->owner->id])->assertOk()->assertJsonPath('data.qty', 3)->assertJsonPath('data.assigned_to', $this->owner->id);

        // A second requisition: approved without an assignee, then Assign; another is cancelled.
        $second = $this->as()->postJson('/api/v1/prototype/requisitions', $form + ['order_id' => $order])->assertCreated()->json('data.req_no');
        $this->as()->postJson("/api/v1/prototype/requisitions/{$second}/submit")->assertOk();
        foreach (range(1, 3) as $i) {
            $this->as()->postJson("/api/v1/prototype/requisitions/{$second}/decide", ['decision' => 'APPROVE'])->assertOk();
        }
        $this->as()->postJson("/api/v1/prototype/requisitions/{$second}/assign")->assertUnprocessable()->assertJsonPath('errors.assigned_to.0', 'Pick who in the sample room takes this sample (Assigned To).');
        $this->as()->postJson("/api/v1/prototype/requisitions/{$second}/assign", ['assigned_to' => $maker->id])->assertOk()->assertJsonPath('data.status', 'ASSIGNED');
        $third = $this->as()->postJson('/api/v1/prototype/requisitions', $form + ['order_id' => $order])->json('data.req_no');
        $this->as()->postJson("/api/v1/prototype/requisitions/{$third}/cancel")->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        $this->assertSame(['SR-2026-00003', 'SR-2026-00002', 'SR-2026-00001'], collect($this->as()->getJson('/api/v1/prototype/requisitions')->assertOk()->json('data'))->pluck('req_no')->all());

        // The factory manager decides but does not raise; a technician sees nothing.
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $this->as($manager)->getJson('/api/v1/prototype/requisitions')->assertOk();
        $this->as($manager)->postJson('/api/v1/prototype/requisitions', $form + ['order_id' => $order])->assertForbidden();
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $this->as($technician)->getJson('/api/v1/prototype/requisitions')->assertForbidden();
    }

    public function test_a_development_works_its_checklist_to_a_locked_close_and_a_revision_moves_the_version(): void
    {
        $sample = $this->approvedSample();
        $options = $this->as()->getJson('/api/v1/prototype/developments/options')->assertOk()->json('data');
        $this->assertSame([$sample->sample_no], collect($options['samples'])->pluck('sample_no')->all());

        $this->as()->postJson('/api/v1/prototype/developments', ['sample_id' => $sample->id])->assertUnprocessable()->assertJsonPath('errors.dev_date.0', 'Development Date is required.');
        $dev = $this->as()->postJson('/api/v1/prototype/developments', ['sample_id' => $sample->id, 'dev_date' => '2026-10-12', 'start_date' => '2026-10-13', 'expected_completion' => '2026-10-20',
            'tasks' => [['task' => 'PATTERN', 'status' => 'COMPLETED'], ['task' => 'PRINT', 'status' => 'NOT_REQUIRED'], ['task' => 'EMBROIDERY', 'status' => 'NOT_REQUIRED'], ['task' => 'WASH', 'status' => 'NOT_REQUIRED']]])
            ->assertCreated()->json('data');
        // Completed over applicable: 1 of 11, the three Not Required ones left out.
        $this->assertSame(['DEV-2026-00001', 1, 'IN_PROGRESS', 9.1, 14, 1, 11], [$dev['dev_no'], $dev['version'], $dev['status'], $dev['progress'], count($dev['tasks']), $dev['tasks_done'], $dev['tasks_total']]);
        $this->assertSame(['DEVELOPMENT', 'IN_PROGRESS'], [$sample->fresh()->phase, $sample->fresh()->state]);
        $this->assertSame('IN_DEVELOPMENT', $this->as()->getJson('/api/v1/prototype/requisitions/SR-2026-00001')->json('data.status'));
        $this->as()->postJson('/api/v1/prototype/developments', ['sample_id' => $sample->id, 'dev_date' => '2026-10-12'])->assertUnprocessable()->assertJsonPath('errors.sample_id.0', "{$sample->sample_no} version 1 already has a development.");

        // Every applicable point completed closes it: 100%, today's completion, locked.
        $tasks = collect(['PATTERN', 'FABRIC', 'COLOR', 'MEASUREMENT', 'ACCESSORIES', 'LABEL', 'CONSTRUCTION', 'CUTTING', 'SEWING', 'FINISHING', 'QUALITY'])->map(fn ($t) => ['task' => $t, 'status' => 'COMPLETED'])->all();
        $closed = $this->as()->putJson('/api/v1/prototype/developments/DEV-2026-00001', ['dev_date' => '2026-10-12', 'start_date' => '2026-10-13', 'tasks' => $tasks])->assertOk()->json('data');
        $this->assertSame(['COMPLETED', 100.0, now()->toDateString(), true], [$closed['status'], (float) $closed['progress'], $closed['actual_completion'], $closed['locked']]);
        $this->assertNotNull(collect($closed['tasks'])->firstWhere('task', 'SEWING')['completed_at']);
        $this->as()->putJson('/api/v1/prototype/developments/DEV-2026-00001', ['dev_date' => '2026-10-12'])->assertUnprocessable()->assertJsonPath('errors.status.0', 'A development that is Completed is closed and cannot be changed.');
        $this->assertSame(['QUALITY', 'PENDING'], [$sample->fresh()->phase, $sample->fresh()->state]);
        $this->assertSame('COMPLETED', $this->as()->getJson('/api/v1/prototype/requisitions/SR-2026-00001')->json('data.status'));

        // A revision moves the sample to version 2, back to Revision · In Progress.
        $this->as()->postJson('/api/v1/prototype/revisions', ['sample_id' => $sample->id, 'revision_date' => '2026-10-21'])->assertUnprocessable()->assertJsonPath('errors.reason.0', 'Revision Reason is required.');
        $rev = $this->as()->postJson('/api/v1/prototype/revisions', ['sample_id' => $sample->id, 'revision_date' => '2026-10-21', 'due_date' => '2026-10-28', 'reason' => 'Buyer asked for a longer body', 'changed_measurement' => 'Body length +2 cm', 'requested_by' => null])
            ->assertCreated()->json('data');
        $this->assertSame(['REV-2026-00001', 1, 2, 'OPEN', 1, $this->owner->id], [$rev['rev_no'], $rev['previous_version'], $rev['new_version'], $rev['status'], $rev['revisions'], $rev['requested_by']]);
        $this->assertSame([2, 'REVISION', 'IN_PROGRESS'], [$sample->fresh()->version, $sample->fresh()->phase, $sample->fresh()->state]);
        $this->assertSame('IN_DEVELOPMENT', $this->as()->getJson('/api/v1/prototype/requisitions/SR-2026-00001')->json('data.status'));
        $this->as()->postJson('/api/v1/prototype/revisions/REV-2026-00001/approve')->assertOk()->assertJsonPath('data.approved_by_name', $this->owner->name);
        $this->as()->postJson('/api/v1/prototype/revisions/REV-2026-00001/approve')->assertUnprocessable();

        // The new version gets its own development, which takes the revision into progress and closes it with it.
        $this->assertSame([$sample->sample_no], collect($this->as()->getJson('/api/v1/prototype/developments/options')->json('data.samples'))->pluck('sample_no')->all());
        $v2 = $this->as()->postJson('/api/v1/prototype/developments', ['sample_id' => $sample->id, 'dev_date' => '2026-10-22', 'tasks' => [['task' => 'MEASUREMENT', 'status' => 'IN_PROGRESS']]])->assertCreated()->json('data');
        $this->assertSame([2, 'REV-2026-00001'], [$v2['version'], $v2['revision_no']]);
        $this->assertSame('IN_PROGRESS', $this->as()->getJson('/api/v1/prototype/revisions/REV-2026-00001')->json('data.status'));
        $this->as()->putJson('/api/v1/prototype/revisions/REV-2026-00001', ['revision_date' => '2026-10-21', 'reason' => 'x'])->assertUnprocessable()->assertJsonPath('errors.status.0', 'A revision that is In Progress cannot be changed.');
        $this->as()->putJson("/api/v1/prototype/developments/{$v2['dev_no']}", ['dev_date' => '2026-10-22', 'status' => 'COMPLETED'])->assertOk()->assertJsonPath('data.status', 'COMPLETED');
        $this->assertSame('COMPLETED', $this->as()->getJson('/api/v1/prototype/revisions/REV-2026-00001')->json('data.status'));
        $this->assertSame(['REV-2026-00001'], collect($this->as()->getJson('/api/v1/prototype/revisions/REV-2026-00001')->json('data.history'))->pluck('rev_no')->all());

        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $this->as($technician)->getJson('/api/v1/prototype/developments')->assertForbidden();
        $this->as($technician)->getJson('/api/v1/prototype/revisions')->assertForbidden();
    }

    /** A sample through all three approval levels. */
    private function approvedSample(): Sample
    {
        [$buyer, $style] = $this->buyerStyleOrder();
        $type = collect($this->as()->getJson('/api/v1/prototype/requisitions/options')->json('data.sample_types'))->firstWhere('label', 'Fit Sample (FIT)');
        $this->as()->postJson('/api/v1/prototype/requisitions', ['req_date' => '2026-10-10', 'buyer_id' => $buyer, 'style_id' => $style, 'sample_type_id' => $type['value'], 'qty' => 1, 'required_date' => '2026-10-30'])->assertCreated();
        $this->as()->postJson('/api/v1/prototype/requisitions/SR-2026-00001/submit')->assertOk();
        foreach (range(1, 3) as $i) {
            $this->as()->postJson('/api/v1/prototype/requisitions/SR-2026-00001/decide', ['decision' => 'APPROVE'])->assertOk();
        }

        return Sample::query()->firstOrFail();
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
