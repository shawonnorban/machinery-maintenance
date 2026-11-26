<?php

declare(strict_types=1);

namespace Tests\Feature\Knitting;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Knitting\Models\KnitYarnCount;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\FiberComposition;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Knit Production → Production (AnnotechERP 15/04) → Yarn Twisting Entry: a
 * twist programme placed on a party; twisted yarn received against its
 * lines — never more grey than a line sent, never more back than the grey,
 * the programme's Received following — after which the programme's yarn
 * lines are fixed; a finalised challan keeping its weights; and a bill that
 * prices only finalised challans of that party, once, or an adjustment that
 * says what it is, the amount worked out as qty × rate.
 */
class KnittingYarnTwistTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $store;

    private string $twister;

    private string $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $warehouse = Warehouse::query()->create(['company_id' => $this->delta->id, 'factory_id' => $factory->id, 'name' => 'Main Warehouse', 'code' => 'WH-1', 'active' => true]);
        $this->store = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => 'Yarn Store', 'code' => 'ST-YRN', 'active' => true])->id;
        $this->twister = MaterialSupplier::query()->create(['code' => 'SUP-10001', 'name' => 'Arafah Twisting', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $this->other = MaterialSupplier::query()->create(['code' => 'SUP-10002', 'name' => 'Other Twister', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
    }

    public function test_programme_receives_and_bill(): void
    {
        $today = now()->toDateString();
        $ym = now()->format('Ym');
        $cotton = FiberComposition::query()->create(['code' => 'C100', 'description' => '100% Cotton', 'active' => true])->id;
        $count = KnitYarnCount::query()->create(['count_no' => 'YC-30-1', 'count_system' => 'Ne', 'description' => '30/1', 'count_value' => 30, 'is_single' => true, 'display_order' => 1, 'active' => true])->id;

        // The programme: lines required; status from the form.
        $line = ['composition_id' => $cotton, 'yarn_count_id' => $count, 'colour_group' => 'Natural', 'brand' => 'Square', 'lot_no' => 'SQ-3011', 'grey_qty' => 100, 'tpi' => 18, 'ply' => 2, 'rate' => 35, 'uom' => 'kg'];
        $p = ['program_date' => $today, 'target_date' => now()->addDays(10)->toDateString(), 'supplier_id' => $this->twister, 'store_id' => $this->store, 'status' => 'DRAFT', 'lines' => [$line]];
        $this->as()->postJson('/api/v1/knitting/prod/twist/programs', ['lines' => []] + $p)->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'A twist programme with no lines on it sends the party nothing to twist.');
        $this->as()->postJson('/api/v1/knitting/prod/twist/programs', ['status' => null] + $p)->assertUnprocessable()->assertJsonPath('errors.status.0', 'Status is required.');
        $made = $this->as()->postJson('/api/v1/knitting/prod/twist/programs', ['lines' => [$line, ['lot_no' => 'SQ-3012', 'grey_qty' => 40] + $line]] + $p)->assertCreated()->json('data');
        $no = "TWP-{$ym}0001";
        $this->assertEquals([$no, 'DRAFT', '100% Cotton 30/1', 18.0, 2], [$made['program_no'], $made['status'], $made['lines'][0]['yarn'], $made['lines'][0]['tpi'], $made['lines'][0]['ply']]);
        [$l1, $l2] = array_column($made['lines'], 'id');

        // Nothing is received against a draft.
        $r = ['receive_date' => $today, 'program_no' => $no, 'gate_pass_no' => 'GP-221', 'store_id' => $this->store, 'finalized' => false, 'transferred' => false,
            'lines' => [['program_line_id' => $l1, 'grey_qty' => 60, 'receive_qty' => 58.5, 'cones' => 48, 'uom' => 'kg']]];
        $this->as()->postJson('/api/v1/knitting/prod/twist/receives', $r)->assertUnprocessable()
            ->assertJsonPath('errors.program_no.0', "{$no} is Draft — nothing is received against it.");
        // Rewritten before anything came back: the lines are written afresh.
        $running = $this->as()->putJson("/api/v1/knitting/prod/twist/programs/{$no}", ['status' => 'RUNNING', 'lines' => [$line, ['lot_no' => 'SQ-3012', 'grey_qty' => 40] + $line]] + $p)->assertOk()->json('data');
        [$l1, $l2] = array_column($running['lines'], 'id');
        $r['lines'][0]['program_line_id'] = $l1;

        // Receives: never more grey than the line sent, never more back than the grey.
        $this->as()->postJson('/api/v1/knitting/prod/twist/receives', ['lines' => [['grey_qty' => 120] + $r['lines'][0]]] + $r)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.grey_qty' => 'Line 1 takes back more grey than the programme line has left (100 Kg).']);
        $this->as()->postJson('/api/v1/knitting/prod/twist/receives', ['lines' => [['receive_qty' => 61] + $r['lines'][0]]] + $r)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.receive_qty' => 'Line 1 receives more than the grey it accounts for — twisting does not add weight.']);
        $c1 = $this->as()->postJson('/api/v1/knitting/prod/twist/receives', $r)->assertCreated()->json('data');
        $this->assertEquals(["TWR-{$ym}0001", 'Arafah Twisting', '100% Cotton 30/1'], [$c1['challan_no'], $c1['party'], $c1['lines'][0]['yarn']]);
        $this->as()->postJson('/api/v1/knitting/prod/twist/receives', ['lines' => [['grey_qty' => 50, 'receive_qty' => 49] + $r['lines'][0]]] + $r)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.grey_qty' => 'Line 1 takes back more grey than the programme line has left (40 Kg).']);
        $c2 = $this->as()->postJson('/api/v1/knitting/prod/twist/receives', ['lines' => [['grey_qty' => 40, 'receive_qty' => 39] + $r['lines'][0], ['program_line_id' => $l2, 'grey_qty' => 40, 'receive_qty' => 39.2, 'uom' => 'kg']]] + $r)
            ->assertCreated()->json('data.challan_no');

        // The programme reads what came back; its yarn lines are now fixed.
        $row = $this->as()->getJson('/api/v1/knitting/prod/twist/programs')->assertOk()->json('data.0');
        $this->assertEquals([140.0, 136.7, 3.3], [$row['grey'], $row['received'], $row['balance']]);
        $this->as()->putJson("/api/v1/knitting/prod/twist/programs/{$no}", ['status' => 'RUNNING', 'lines' => [$line]] + $p)->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', "{$no} has receives against it — its yarn lines are what the party was sent and cannot be rewritten.");
        $this->as()->putJson("/api/v1/knitting/prod/twist/programs/{$no}", ['status' => 'CLOSED', 'remarks' => 'All back', 'lines' => [$line, ['lot_no' => 'SQ-3012', 'grey_qty' => 40] + $line]] + $p)
            ->assertOk()->assertJsonPath('data.status', 'CLOSED');

        // A finalised challan keeps its weights; Transfer may still be set.
        $this->as()->putJson("/api/v1/knitting/prod/twist/receives/{$c1['challan_no']}", ['finalized' => true] + $r)->assertOk()->assertJsonPath('data.finalized', true);
        $after = $this->as()->putJson("/api/v1/knitting/prod/twist/receives/{$c1['challan_no']}", ['finalized' => true, 'transferred' => true, 'lines' => [['receive_qty' => 10] + $r['lines'][0]]] + $r)->assertOk()->json('data');
        $this->assertEquals([true, 58.5], [$after['transferred'], $after['lines'][0]['receive_qty']]);

        // The bill: finalised challans of this party, once; adjustments say what they are; amount = qty × rate.
        $b = ['bill_date' => $today, 'supplier_id' => $this->twister, 'program_no' => $no, 'party_bill_no' => 'AT/INV/0091', 'status' => 'DRAFT',
            'lines' => [['challan_no' => $c1['challan_no'], 'composition_id' => $cotton, 'yarn_count_id' => $count, 'qty' => 58.5, 'rate' => 35, 'uom' => 'kg']]];
        $this->as()->postJson('/api/v1/knitting/prod/twist/bills', ['lines' => []] + $b)->assertUnprocessable()->assertJsonPath('errors.lines.0', 'A twist bill with no lines owes the twister nothing.');
        $this->as()->postJson('/api/v1/knitting/prod/twist/bills', ['lines' => [['challan_no' => $c2] + $b['lines'][0]]] + $b)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.challan_no' => "{$c2} is not finalised yet — its weights can still change."]);
        $this->as()->postJson('/api/v1/knitting/prod/twist/bills', ['supplier_id' => $this->other, 'program_no' => null] + $b)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.challan_no' => "Line 1's challan is not this supplier's."]);
        $this->as()->postJson('/api/v1/knitting/prod/twist/bills', ['lines' => [['challan_no' => null, 'particulars' => ''] + $b['lines'][0]]] + $b)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.particulars' => 'Line 1 has no challan behind it — say what the adjustment is in its particulars.']);
        $bill = $this->as()->postJson('/api/v1/knitting/prod/twist/bills', ['lines' => [$b['lines'][0], ['challan_no' => null, 'particulars' => 'Carriage, agreed', 'qty' => 1, 'rate' => 450, 'uom' => 'kg']]] + $b)
            ->assertCreated()->json('data');
        $this->assertEquals(["TWB-{$ym}0001", 2047.5, 2497.5, $c1['challan_no']], [$bill['bill_no'], $bill['lines'][0]['amount'], $bill['amount'], $bill['lines'][0]['challan_no']]);
        $this->as()->postJson('/api/v1/knitting/prod/twist/bills', $b)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.challan_no' => "{$c1['challan_no']} is already on another bill."]);
        $this->as()->putJson("/api/v1/knitting/prod/twist/bills/{$bill['bill_no']}", ['status' => 'APPROVED'] + $b)->assertOk();
        $this->as()->putJson("/api/v1/knitting/prod/twist/bills/{$bill['bill_no']}", ['status' => 'PAID'] + $b)->assertUnprocessable()
            ->assertJsonPath('errors.status.0', "{$bill['bill_no']} is Approved — it can no longer be rewritten.");
        $this->assertEquals([[$bill['bill_no'], 2047.5, 'APPROVED']], array_map(fn ($x) => [$x['bill_no'], $x['amount'], $x['status']], $this->as()->getJson('/api/v1/knitting/prod/twist/bills')->json('data')));
        $this->assertSame(2, DB::table('knit_twist_receives')->count());
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
