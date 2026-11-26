<?php

declare(strict_types=1);

namespace Tests\Feature\Prototype;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\Merchandise\Models\FabricType;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Merchandise\Models\SampleProgram;
use App\Modules\Prototype\Models\Sample;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Prototype Desk → Prototype Material Requisition (AnnotechERP 07/03): a
 * sample's ask of the stores is raised with its programs and lines, walked
 * through submit, department approval, store verification (every line's
 * available quantity and warehouse first; the lines lock) and approval —
 * rejected only on a saved reason, cancelled until issued — and issued
 * against within what is asked and what the store holds, to Partially and
 * Fully Issued.
 */
class SampleMaterialRequisitionTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    /** @var array<string, string> */
    private array $stores = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $warehouse = Warehouse::query()->create(['company_id' => $this->delta->id, 'factory_id' => $factory->id, 'name' => 'Main Warehouse', 'code' => 'WH-1', 'active' => true]);
        foreach (['ST-FAB' => 'Finished Fabric Store', 'ST-SMP' => 'GMT Sample Store'] as $code => $name) {
            $this->stores[$code] = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => $name, 'code' => $code, 'active' => true])->id;
        }
    }

    public function test_a_requisition_walks_its_approvals_and_is_issued_within_what_the_store_holds(): void
    {
        $sample = $this->sample();
        $item = MaterialItem::query()->create(['company_id' => $this->delta->id, 'kind' => 'FABRIC', 'item_code' => 'FB-SJ-160', 'name' => 'Single Jersey 160 GSM', 'category' => 'Knit', 'uom' => 'KG', 'active' => true])->id;
        $rib = MaterialItem::query()->create(['company_id' => $this->delta->id, 'kind' => 'FABRIC', 'item_code' => 'FB-RIB', 'name' => '1x1 Rib', 'category' => 'Knit', 'uom' => 'KG', 'active' => true])->id;
        $sj = FabricType::query()->create(['company_id' => $this->delta->id, 'code' => 'SJ', 'name' => 'Single Jersey', 'short_name' => 'SJ', 'construction' => 'KNIT', 'active' => true])->id;
        $program = SampleProgram::query()->create(['company_id' => $this->delta->id, 'program_no' => 'SP-2026-00001', 'program_date' => '2026-10-15', 'status' => 'DRAFT'])->id;
        $options = $this->as()->getJson('/api/v1/prototype/material-requisitions/options')->assertOk()->json('data');
        $this->assertContains('Fin Fab Req by Sample Section', $options['requisition_types']);
        $this->assertSame(['Finished Fabric Store (ST-FAB)', 'GMT Sample Store (ST-SMP)'], collect($options['stores'])->pluck('label')->all());
        $this->assertSame('KG', collect($options['items'])->firstWhere('value', $item)['uom']);

        $head = ['sample_id' => $sample->id, 'req_date' => '2026-10-22', 'required_date' => '2026-10-25', 'requested_by' => $this->owner->id, 'requisition_type' => 'Fin Fab Req by Sample Section',
            'requisition_to_id' => $this->stores['ST-FAB'], 'delivery_to_id' => $this->stores['ST-SMP']];
        $this->as()->postJson('/api/v1/prototype/material-requisitions', [])->assertUnprocessable()
            ->assertJsonPath('errors.sample_id.0', 'Sample No is required for a new requisition.')->assertJsonPath('errors.requisition_to_id.0', 'Requisition To is required.');
        $this->as()->postJson('/api/v1/prototype/material-requisitions', $head)->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'A requisition with no lines posts nothing, so at least one is required before this can be saved.');
        $this->as()->postJson('/api/v1/prototype/material-requisitions', array_merge($head, ['required_date' => '2026-10-20', 'lines' => [['item_id' => $item, 'req_qty' => 1]]]))->assertUnprocessable()
            ->assertJsonPath('errors.required_date.0', 'Required Date cannot be before the Requisition Date.');

        $lines = [
            ['item_id' => $item, 'material_group' => 'FABRIC', 'fabric_type_id' => $sj, 'color' => 'Navy', 'size' => 'M', 'req_qty' => 12],
            ['item_id' => $rib, 'material_group' => 'FABRIC', 'color' => 'Navy', 'req_qty' => 2],
        ];
        $mr = $this->as()->postJson('/api/v1/prototype/material-requisitions', $head + ['programs' => [$program], 'lines' => $lines])->assertCreated()->json('data');
        $this->assertSame(['MR-2026-00001', 'DRAFT', 'SR-2026-00001', 'LPP', 1, 2, 'KG', 'Finished Fabric Store', 'GMT Sample Store'],
            [$mr['mr_no'], $mr['state'], $mr['requisition_no'], $mr['buyer'], $mr['programs_count'], $mr['items_count'], $mr['lines'][0]['unit'], $mr['requisition_to'], $mr['delivery_to']]);
        $this->assertSame('SP-2026-00001', $mr['programs'][0]['program_no']);
        $this->assertSame(['MR-2026-00001'], array_column(collect($this->as()->getJson('/api/v1/prototype/material-requisitions/options')->json('data.programs'))->firstWhere('value', $program)['requisitions'], 'mr_no'));
        $url = '/api/v1/prototype/material-requisitions/MR-2026-00001';

        // Each step only from its own state; Reject needs the reason saved first.
        $this->as()->postJson("{$url}/dept-approve")->assertUnprocessable()->assertJsonPath('errors.state.0', 'Dept. Approve is not open to a requisition that is Draft.');
        $this->as()->postJson("{$url}/submit")->assertOk()->assertJsonPath('data.state', 'SUBMITTED');
        $this->as()->postJson("{$url}/reject")->assertUnprocessable()->assertJsonPath('errors.rejection_reason.0', 'Save the reason first — Reject is refused without one.');
        $this->as()->putJson($url, $head + ['lines' => $lines, 'rejection_reason' => 'Wrong fabric'])->assertOk();
        $this->as()->postJson("{$url}/reject")->assertOk()->assertJsonPath('data.state', 'REJECTED');
        // A rejected requisition saved again is a draft, and climbs again.
        $this->as()->putJson($url, $head + ['lines' => $lines])->assertOk()->assertJsonPath('data.state', 'DRAFT')->assertJsonPath('data.rejection_reason', null);
        $this->as()->postJson("{$url}/submit")->assertOk();
        $dept = $this->as()->postJson("{$url}/dept-approve")->assertOk()->json('data');
        $this->assertSame(['DEPT_APPROVED', $this->owner->name.' · '.now()->format('d M Y')], [$dept['state'], $dept['dept_approved']]);

        // The store enters what it holds, and where, on every line before verifying; Short Qty follows.
        $this->as()->postJson("{$url}/verify")->assertUnprocessable()->assertJsonPath('errors.lines.0', 'Enter the Available Qty and Warehouse on every line, and save, before Store Verify.');
        $held = [array_merge($lines[0], ['available_qty' => 8, 'warehouse_id' => $this->stores['ST-FAB']]), array_merge($lines[1], ['available_qty' => 5, 'warehouse_id' => $this->stores['ST-FAB']])];
        $saved = $this->as()->putJson($url, $head + ['lines' => $held])->assertOk()->json('data');
        $this->assertSame([4.0, 0.0, 1], [(float) $saved['lines'][0]['short_qty'], (float) $saved['lines'][1]['short_qty'], $saved['short_lines']]);
        $this->as()->postJson("{$url}/verify")->assertOk()->assertJsonPath('data.state', 'STORE_VERIFIED')->assertJsonPath('data.lines_open', false);
        // Verified lines are locked: a save keeps them as they were.
        $this->as()->putJson($url, $head + ['lines' => [['item_id' => $item, 'req_qty' => 99]], 'remarks' => 'Urgent'])->assertOk()->assertJsonPath('data.items_count', 2)->assertJsonPath('data.remarks', 'Urgent');
        $this->as()->postJson("{$url}/approve")->assertOk()->assertJsonPath('data.state', 'APPROVED')->assertJsonPath('data.editable', false);
        $this->as()->putJson($url, $head + ['lines' => $held])->assertUnprocessable()->assertJsonPath('errors.state.0', 'A requisition that is Approved can no longer be changed.');
        $this->as()->deleteJson($url)->assertUnprocessable();

        // Material Issue: within what is asked and what the store holds; lines labelled with their balance.
        $issueOptions = $this->as()->getJson('/api/v1/prototype/material-issues/options')->assertOk()->json('data');
        $choice = collect($issueOptions['requisitions'])->firstWhere('value', $mr['id']);
        $this->assertSame(['MR-2026-00001 · Single Jersey 160 GSM (FB-SJ-160) · Navy · bal 8 KG', 8.0], [$choice['lines'][0]['label'], (float) $choice['lines'][0]['balance']]);
        [$sjLine, $ribLine] = array_column($choice['lines'], 'value');
        $issue = ['requisition_id' => $mr['id'], 'issue_date' => '2026-10-22', 'received_by' => 'Sample Room'];
        $this->as()->postJson('/api/v1/prototype/material-issues', $issue)->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'A material issue with no lines posts nothing, so at least one is required before this can be saved.');
        $this->as()->postJson('/api/v1/prototype/material-issues', $issue + ['lines' => [['line_id' => $sjLine, 'qty' => 10]]])->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'Row 1: no more than 8 KG is left to issue on this line.');
        $first = $this->as()->postJson('/api/v1/prototype/material-issues', $issue + ['lines' => [['line_id' => $sjLine, 'qty' => 5]]])->assertCreated()->json('data');
        $this->assertSame(['MI-2026-00001', 'MR-2026-00001', 'Finished Fabric Store (ST-FAB)', 5.0], [$first['issue_no'], $first['mr_no'], $first['store'], (float) $first['total_qty']]);
        $after = $this->as()->getJson($url)->json('data');
        $this->assertSame(['PARTIALLY_ISSUED', 35.7, 0], [$after['state'], $after['issued_pct'], $after['short_lines']]);
        // The rest: 3 more of the jersey and both rib — Fully Issued.
        $this->as()->postJson('/api/v1/prototype/material-issues', $issue + ['lines' => [['line_id' => $sjLine, 'qty' => 2], ['line_id' => $sjLine, 'qty' => 2]]])->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'Row 2: no more than 3 KG is left to issue on this line.');
        $this->as()->postJson('/api/v1/prototype/material-issues', $issue + ['lines' => [['line_id' => $sjLine, 'qty' => 3], ['line_id' => $ribLine, 'qty' => 2]]])->assertCreated();
        $this->assertSame('FULLY_ISSUED', $this->as()->getJson($url)->json('data.state'));
        $this->as()->postJson('/api/v1/prototype/material-issues', $issue + ['lines' => [['line_id' => $ribLine, 'qty' => 1]]])->assertUnprocessable()
            ->assertJsonPath('errors.requisition_id.0', 'Only approved and partially issued requisitions can be issued against.');
        $this->as()->postJson("{$url}/cancel")->assertUnprocessable()->assertJsonPath('errors.state.0', 'A requisition that is Fully Issued cannot be cancelled.');
        $this->assertSame(['MI-2026-00002', 'MI-2026-00001'], collect($this->as()->getJson('/api/v1/prototype/material-issues')->assertOk()->json('data'))->pluck('issue_no')->all());
        $this->assertCount(2, $this->as()->getJson('/api/v1/prototype/material-issues/MI-2026-00002')->json('data.lines'));

        // A second requisition is cancelled, then deleted.
        $second = $this->as()->postJson('/api/v1/prototype/material-requisitions', $head + ['lines' => $lines])->assertCreated()->json('data.mr_no');
        $this->as()->postJson("/api/v1/prototype/material-requisitions/{$second}/cancel")->assertOk()->assertJsonPath('data.state', 'CANCELLED');
        $this->as()->deleteJson("/api/v1/prototype/material-requisitions/{$second}")->assertOk();
        $this->assertSame(['MR-2026-00001'], collect($this->as()->getJson('/api/v1/prototype/material-requisitions')->json('data'))->pluck('mr_no')->all());

        // The factory manager approves but neither raises nor issues; a technician sees nothing.
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $this->as($manager)->getJson('/api/v1/prototype/material-requisitions')->assertOk();
        $this->as($manager)->postJson('/api/v1/prototype/material-requisitions', $head + ['lines' => $lines])->assertForbidden();
        $this->as($manager)->postJson('/api/v1/prototype/material-issues', $issue)->assertForbidden();
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $this->as($technician)->getJson('/api/v1/prototype/material-requisitions')->assertForbidden();
    }

    /** A sample through its requisition's three approval levels. */
    private function sample(): Sample
    {
        $office = $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => 'LPP Office', 'office_type' => 'HEAD_OFFICE'])->json('data.id');
        $country = OriginCountry::query()->firstOrCreate(['company_id' => $this->delta->id, 'code' => '616'], ['name' => 'Poland', 'active' => true])->id;
        $buyer = $this->as()->postJson('/api/v1/erp/buyers', ['code' => 'LPP', 'name' => 'LPP Spolka Akcyjna', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'country_id' => $country, 'office_ids' => [$office]])
            ->assertCreated()->json('data.id');
        $size = $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'M', 'name' => 'M'])->json('data.id');
        $style = $this->as()->postJson('/api/v1/merchandise/styles', ['buyer_id' => $buyer, 'buyer_office_id' => $office, 'style_ref' => 'SS27-TEE01', 'description' => 'Summer Set', 'qty_unit' => 'PCS', 'pcs_per_unit' => 1, 'status' => 'DEVELOPMENT', 'lines' => [['size_ids' => [$size]]]])
            ->assertCreated()->json('data.id');
        $type = collect($this->as()->getJson('/api/v1/prototype/requisitions/options')->json('data.sample_types'))->firstWhere('label', 'Fit Sample (FIT)');
        $this->as()->postJson('/api/v1/prototype/requisitions', ['req_date' => '2026-10-10', 'buyer_id' => $buyer, 'style_id' => $style, 'sample_type_id' => $type['value'], 'qty' => 1, 'required_date' => '2026-10-30'])->assertCreated();
        $this->as()->postJson('/api/v1/prototype/requisitions/SR-2026-00001/submit')->assertOk();
        foreach (range(1, 3) as $i) {
            $this->as()->postJson('/api/v1/prototype/requisitions/SR-2026-00001/decide', ['decision' => 'APPROVE'])->assertOk();
        }

        return Sample::query()->firstOrFail();
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
