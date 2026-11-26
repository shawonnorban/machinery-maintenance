<?php

declare(strict_types=1);

namespace Tests\Feature\Finishing;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Finishing Line → Fabric Store (AnnotechERP 18/05): passed batches handed
 * to the store on a one-order note, sent off the floor and received into
 * lots; rolls racked and barcoded; a cutting requisition releasing from the
 * lots' balances; returns and transfers moving them — and who may do what.
 */
class FinishingFabricStoreTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $store;

    private string $other;

    private string $order;

    private string $order2;

    private string $ym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $warehouse = Warehouse::query()->create(['company_id' => $this->delta->id, 'factory_id' => $factory->id, 'name' => 'Main Warehouse', 'code' => 'WH-1', 'active' => true]);
        $this->store = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => 'Finish Fabric Store', 'code' => 'ST-FIN', 'active' => true])->id;
        $this->other = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => 'Sample Store', 'code' => 'ST-SMP', 'active' => true])->id;
        $buyer = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $order = fn (string $no, string $style) => Order::query()->create(['company_id' => $this->delta->id, 'order_no' => $no, 'job_no' => $style, 'style_no' => $style, 'buyer_id' => $buyer, 'order_type' => 'BULK_NEW', 'total_qty' => 1200, 'ship_date' => '2026-12-15', 'status' => 'CONFIRMED'])->id;
        $this->order = $order('LPP-2026-000008', '529');
        $this->order2 = $order('LPP-2026-000009', '1111');
        $this->ym = now('Asia/Dhaka')->format('Ym');
    }

    public function test_passed_batches_are_sent_to_the_store_and_received_into_lots(): void
    {
        $ready = $this->card('B-26110001', $this->order, ready: true);
        $raw = $this->card('B-26110002', $this->order, ready: false);
        $elsewhere = $this->card('B-26110003', $this->order2, ready: true);
        $d = ['receive_date' => '2026-11-07', 'store_id' => $this->store, 'remarks' => 'Trolley 3'];
        $line = fn (string $card, array $over = []) => $over + ['receipt_id' => $card, 'fabric_type' => 'Single Jersey', 'fabric_details' => '30/1 combed', 'shade_no' => 'SH-2', 'dia_type' => 'OPEN', 'fin_gsm' => 180, 'fin_dia' => 72, 'grey_qty' => 100, 'finish_qty' => 95.5, 'rolls' => 6, 'rate' => 12, 'aop' => false];

        $this->as()->postJson('/api/v1/finishing/store-deliveries', $d + ['lines' => []])->assertUnprocessable()->assertJsonPath('errors.lines.0', 'A hand-over with no lots on it hands over nothing.');
        $bad = $this->as()->postJson('/api/v1/finishing/store-deliveries', $d + ['lines' => [$line($raw), $line($ready, ['finish_qty' => 101])]])->assertUnprocessable()->json('errors');
        $this->assertSame('Row 1: batch B-26110002 is not Ready for Delivery — it has not passed inspection.', $bad['lines.0.receipt_id'][0]);
        $this->assertSame('Row 2: the finish quantity cannot be more than the grey.', $bad['lines.1.finish_qty'][0]);
        $this->as()->postJson('/api/v1/finishing/store-deliveries', $d + ['lines' => [$line($ready), $line($elsewhere)]])->assertUnprocessable()->assertJsonPath('errors.lines.0', 'Every lot on one note must belong to the same order.');

        $no = $this->as()->postJson('/api/v1/finishing/store-deliveries', $d + ['lines' => [$line($ready)]])->assertCreated()
            ->assertJsonPath('data.delivery_no', "FFD-{$this->ym}0001")->assertJsonPath('data.order_id', $this->order)->assertJsonPath('data.lines.0.order_no', 'LPP-2026-000008')->json('data.delivery_no');
        $this->as()->postJson('/api/v1/finishing/store-deliveries', $d + ['lines' => [$line($ready)]])->assertUnprocessable()
            ->assertJsonPath('errors', fn ($e) => $e['lines.0.receipt_id'][0] === "Row 1: batch B-26110001 is on FFD-{$this->ym}0001 already.");
        $this->as()->getJson('/api/v1/finishing/store-deliveries')->assertJsonPath('data.0.buyer', 'LPP')->assertJsonPath('data.0.finish_qty', 95.5)->assertJsonPath('data.0.batches', 'B-26110001');

        $this->as()->postJson("/api/v1/finishing/store-deliveries/{$no}/receive")->assertUnprocessable();
        $this->as()->postJson("/api/v1/finishing/store-deliveries/{$no}/send")->assertOk()->assertJsonPath('data.status', 'SENT');
        $this->assertSame('DELIVERED', DB::table('fin_batch_receipts')->where('id', $ready)->value('stage'));
        $this->as()->putJson("/api/v1/finishing/store-deliveries/{$no}", $d + ['lines' => [$line($ready)]])->assertUnprocessable()->assertJsonPath('errors.delivery_no.0', "FFD-{$this->ym}0001 is Sent — it is not changed here.");
        $this->assertSame([], $this->opts()['lots']);
        $this->as()->postJson("/api/v1/finishing/store-deliveries/{$no}/receive")->assertOk()->assertJsonPath('data.status', 'RECEIVED');
        $lot = $this->opts()['lots'][0];
        $this->assertSame(["FSL-{$this->ym}0001", 95.5, 95.5, 6], [$lot['lot_code'], $lot['received_kg'], $lot['balance_kg'], $lot['balance_rolls']]);
    }

    public function test_cutting_requisitions_release_from_the_lots_balance(): void
    {
        $lot = $this->lot('B-26110011');
        $r = ['req_no' => ' cr-101 ', 'req_date' => '2026-11-08', 'store_id' => $this->store, 'cut_prog_ref' => 'CP-7', 'style' => '529', 'status' => 'PARTIAL'];
        $line = fn (array $over = []) => $over + ['lot_id' => $lot, 'req_qty' => 60, 'issue_qty' => 30, 'rolls' => 2];
        $this->as()->postJson('/api/v1/finishing/cut-requisitions', ['store_id' => $this->other] + $r + ['lines' => [$line()]])->assertUnprocessable()
            ->assertJsonPath('errors', fn ($e) => str_starts_with($e['lines.0.lot_id'][0], 'Row 1: lot FSL-'));
        $this->as()->postJson('/api/v1/finishing/cut-requisitions', $r + ['lines' => [$line(['issue_qty' => 96])]])->assertUnprocessable()
            ->assertJsonPath('errors', fn ($e) => str_ends_with($e['lines.0.issue_qty'][0], 'holds only 95.5 kg.'));
        $this->as()->postJson('/api/v1/finishing/cut-requisitions', ['status' => 'COMPLETE'] + $r + ['lines' => [$line()]])->assertUnprocessable()->assertJsonPath('errors.status.0', 'A requisition is complete only when every line is released in full.');
        $this->as()->postJson('/api/v1/finishing/cut-requisitions', $r + ['lines' => [$line(['issue_qty' => 0])]])->assertUnprocessable()->assertJsonPath('errors.status.0', 'A partial requisition has released something — give an Issue Qty.');

        $this->as()->postJson('/api/v1/finishing/cut-requisitions', $r + ['lines' => [$line()]])->assertCreated()->assertJsonPath('data.req_no', 'CR-101')->assertJsonPath('data.lines.0.balance_kg', 65.5);
        $this->as()->postJson('/api/v1/finishing/cut-requisitions', $r + ['lines' => [$line()]])->assertUnprocessable()->assertJsonPath('errors.req_no.0', 'Req No CR-101 is taken already.');
        // Re-saved, its release is booked afresh — never twice.
        $this->as()->putJson('/api/v1/finishing/cut-requisitions/CR-101', $r + ['lines' => [$line(['issue_qty' => 40])]])->assertOk()->assertJsonPath('data.lines.0.balance_kg', 55.5);
        $this->as()->putJson('/api/v1/finishing/cut-requisitions/CR-101', ['status' => 'DRAFT'] + $r + ['lines' => [$line(['issue_qty' => 40])]])->assertOk()->assertJsonPath('data.lines.0.balance_kg', 95.5);
        $this->as()->putJson('/api/v1/finishing/cut-requisitions/CR-101', ['status' => 'COMPLETE'] + $r + ['lines' => [$line(['issue_qty' => 60])]])->assertOk()->assertJsonPath('data.lines.0.balance_kg', 35.5);
        $this->as()->putJson('/api/v1/finishing/cut-requisitions/CR-101', $r + ['lines' => [$line()]])->assertUnprocessable()->assertJsonPath('errors.req_no.0', 'CR-101 is Complete and stands.');
        $this->as()->getJson('/api/v1/finishing/cut-requisitions')->assertJsonPath('data.0.issue_qty', 60)->assertJsonPath('data.0.status', 'COMPLETE');
    }

    public function test_a_lot_kept_by_length_is_issued_and_returned_by_length(): void
    {
        // Fabric by length (2026-11-16): 400 yds of Navy, 95.5 kg — the kilos follow at the lot's own kilos per metre.
        $lot = $this->lot('B-26110051', ['finish_len' => 400, 'colour' => 'Navy']);
        $mine = collect($this->opts()['lots'])->firstWhere('value', $lot);
        $this->assertEquals([400, 400, 'YDS'], [$mine['received_len'], $mine['balance_len'], $mine['len_unit']]);

        $r = ['req_no' => 'CR-301', 'req_date' => now('Asia/Dhaka')->toDateString(), 'store_id' => $this->store, 'colour' => 'Navy', 'status' => 'PARTIAL'];
        $this->as()->postJson('/api/v1/finishing/cut-requisitions', $r + ['lines' => [['lot_id' => $lot, 'req_len' => 150, 'issue_len' => 450]]])->assertUnprocessable()
            ->assertJsonPath('errors', fn ($e) => str_ends_with($e['lines.0.issue_len'][0], 'holds 400 yds — no more can be issued.'));
        $line = $this->as()->postJson('/api/v1/finishing/cut-requisitions', $r + ['lines' => [['lot_id' => $lot, 'req_len' => 150, 'issue_len' => 100]]])->assertCreated()->json('data.lines.0');
        // 100 yds = 91.44 m at 95.5 kg / 365.76 m.
        $this->assertEquals([150, 100, 300], [$line['req_len'], $line['issue_len'], $line['balance_len']]);
        $this->assertEqualsWithDelta(23.875, $line['issue_qty'], 0.001);
        $this->as()->getJson('/api/v1/finishing/cut-requisitions')->assertJsonPath('data.0.issue_len', 100)->assertJsonPath('data.0.len_unit', 'YDS');

        $req = DB::table('fin_cut_requisitions')->where('req_no', 'CR-301')->value('id');
        $back = $this->as()->postJson('/api/v1/erp/fin-store-returns', ['return_date' => now('Asia/Dhaka')->toDateString(), 'kind' => 'RETURN', 'lot_id' => $lot, 'requisition_id' => $req, 'qty_len' => 20])->assertCreated()->json('data');
        $this->assertEquals([20, 320], [$back['qty_len'], $back['balance_len']]);
        $this->assertEqualsWithDelta(4.775, (float) $back['qty_kg'], 0.001);
        $this->as()->postJson('/api/v1/erp/fin-store-returns', ['return_date' => now('Asia/Dhaka')->toDateString(), 'kind' => 'TRANSFER', 'lot_id' => $lot, 'qty_len' => 330, 'to_unit' => 'Unit 2'])->assertUnprocessable()->assertJsonValidationErrors('qty_len');

        // What reached cutting shows on the cutting floor's fabric panel, by length.
        $this->as()->getJson("/api/v1/cutting/context?order_id={$this->order}&colour=navy")->assertOk()->assertJsonPath('data.fabric.issued_len', 100)->assertJsonPath('data.fabric.unit', 'YDS');
    }

    public function test_rolls_are_racked_and_barcoded(): void
    {
        $lot = $this->lot('B-26110021');
        $roll = ['lot_id' => $lot, 'roll_no' => ' r-1 ', 'weight_kg' => 18.5, 'rack' => ' r-a1 ', 'bin' => 'b-03'];
        $this->as()->postJson('/api/v1/erp/fin-store-rolls', $roll)->assertCreated()->assertJsonPath('data.roll_no', 'R-1')->assertJsonPath('data.rack', 'R-A1')->assertJsonPath('data.status', 'IN_STORE')
            ->assertJsonPath('data.barcode', 'FR'.now('Asia/Dhaka')->format('ym').'000001')->assertJsonPath('data.lot_code', "FSL-{$this->ym}0001");
        $this->as()->postJson('/api/v1/erp/fin-store-rolls', $roll)->assertUnprocessable()->assertJsonPath('errors.roll_no.0', "Roll R-1 is racked on FSL-{$this->ym}0001 already.");
        $this->as()->postJson('/api/v1/erp/fin-store-rolls', ['roll_no' => 'R-2', 'weight_kg' => 80] + $roll)->assertUnprocessable()
            ->assertJsonPath('errors.weight_kg.0', "The rolls of FSL-{$this->ym}0001 would weigh more than it received — only 77 kg is left to rack.");
        $this->as()->postJson('/api/v1/erp/fin-store-rolls', ['roll_no' => 'R-2', 'weight_kg' => 77] + $roll)->assertCreated()->assertJsonPath('data.barcode', 'FR'.now('Asia/Dhaka')->format('ym').'000002');
    }

    public function test_returns_and_transfers_move_the_lots_balance(): void
    {
        $lot = $this->lot('B-26110031');
        $req = $this->as()->postJson('/api/v1/finishing/cut-requisitions', ['req_no' => 'CR-201', 'req_date' => now('Asia/Dhaka')->toDateString(), 'store_id' => $this->store, 'status' => 'PARTIAL', 'lines' => [['lot_id' => $lot, 'req_qty' => 50, 'issue_qty' => 40]]])->assertCreated()->json('data.id');
        $r = ['return_date' => now('Asia/Dhaka')->toDateString(), 'kind' => 'RETURN', 'lot_id' => $lot, 'requisition_id' => $req, 'qty_kg' => 12, 'rolls' => 1, 'reason' => 'Cut short'];
        $this->as()->postJson('/api/v1/erp/fin-store-returns', ['requisition_id' => null] + $r)->assertUnprocessable()->assertJsonPath('errors.requisition_id.0', 'Name the requisition a return comes back from.');
        $this->as()->postJson('/api/v1/erp/fin-store-returns', ['qty_kg' => 41] + $r)->assertUnprocessable()->assertJsonPath('errors.qty_kg.0', 'Only 40 kg released on CR-201 is still out.');
        $this->as()->postJson('/api/v1/erp/fin-store-returns', ['return_date' => '2026-11-01'] + $r)->assertUnprocessable()->assertJsonValidationErrors('return_date');
        $back = $this->as()->postJson('/api/v1/erp/fin-store-returns', $r)->assertCreated()->assertJsonPath('data.return_no', "FSR-{$this->ym}0001")->assertJsonPath('data.balance_kg', 67.5)->json('data.id');

        $t = ['return_date' => now('Asia/Dhaka')->toDateString(), 'kind' => 'TRANSFER', 'lot_id' => $lot, 'qty_kg' => 70, 'to_unit' => 'Unit 2 Cutting'];
        $this->as()->postJson('/api/v1/erp/fin-store-returns', ['to_unit' => null] + $t)->assertUnprocessable()->assertJsonPath('errors.to_unit.0', 'Name the unit a transfer goes to.');
        $this->as()->postJson('/api/v1/erp/fin-store-returns', $t)->assertUnprocessable()->assertJsonPath('errors.qty_kg.0', "Lot FSL-{$this->ym}0001 holds only 67.5 kg.");
        $moved = $this->as()->postJson('/api/v1/erp/fin-store-returns', ['qty_kg' => 20] + $t)->assertCreated()->assertJsonPath('data.balance_kg', 47.5)->json('data.id');
        $this->as()->patchJson("/api/v1/erp/fin-store-returns/{$moved}", ['qty_kg' => 25])->assertOk()->assertJsonPath('data.balance_kg', 42.5);
        $this->as()->deleteJson("/api/v1/erp/fin-store-returns/{$back}")->assertNoContent();
        $this->assertSame(30.5, collect($this->opts()['lots'])->firstWhere('value', $lot)['balance_kg']);
    }

    public function test_who_may_keep_the_store(): void
    {
        $store = $this->token('STORE_MANAGER', 'store@delta.test');
        $this->withToken($store)->postJson('/api/v1/finishing/store-deliveries', ['receive_date' => '2026-11-07', 'store_id' => $this->store, 'lines' => [['receipt_id' => $this->card('B-26110041', $this->order, true), 'grey_qty' => 10, 'finish_qty' => 9]]])->assertCreated();
        $chief = $this->token('LINE_CHIEF', 'chief@delta.test');
        $this->withToken($chief)->getJson('/api/v1/finishing/store-deliveries')->assertOk();
        $this->withToken($chief)->postJson('/api/v1/erp/fin-store-rolls', ['roll_no' => 'X'])->assertForbidden();
        $technician = $this->token('TECHNICIAN', 'tech@delta.test');
        $this->withToken($technician)->getJson('/api/v1/finishing/cut-requisitions')->assertForbidden();
    }

    /** A dyed batch of the order on the floor (100 kg, 6 rolls), passed on to Ready for Delivery if asked; its card id. */
    private function card(string $no, string $order, bool $ready): string
    {
        $batch = (string) Str::ulid();
        DB::table('dye_batches')->insert(['id' => $batch, 'company_id' => $this->delta->id, 'batch_no' => $no, 'batch_date' => '2026-11-01', 'scope' => 'BULK', 'source' => 'MANUAL', 'order_id' => $order, 'lot_no' => 'L-'.substr($no, -2), 'plan_qty' => 100, 'card_qty' => 100, 'status' => 'OPEN', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('dye_productions')->insert(['id' => (string) Str::ulid(), 'company_id' => $this->delta->id, 'batch_id' => $batch, 'production_date' => '2026-11-04', 'liquor_ratio' => '1:8', 'created_at' => now(), 'updated_at' => now()]);
        $card = $this->as()->postJson('/api/v1/erp/fin-batch-receipts', ['receive_date' => '2026-11-05', 'batch_id' => $batch, 'dia_type' => 'OPEN', 'recv_qty_kg' => 100, 'recv_rolls' => 6])->assertCreated()->json('data');
        if ($ready) {
            $this->as()->postJson("/api/v1/finishing/delivery-board/{$card['receipt_no']}/deliver", ['to_stage' => 'READY_FOR_DELIVERY'])->assertOk();
        }

        return $card['id'];
    }

    /** A received store lot of 95.5 kg / 6 rolls in the finish fabric store; its id. */
    /** @param array<string, mixed> $line more of the delivery line (a length, a colour) */
    private function lot(string $batch, array $line = []): string
    {
        $card = $this->card($batch, $this->order, true);
        $no = $this->as()->postJson('/api/v1/finishing/store-deliveries', ['receive_date' => '2026-11-07', 'store_id' => $this->store, 'lines' => [$line + ['receipt_id' => $card, 'grey_qty' => 100, 'finish_qty' => 95.5, 'rolls' => 6]]])->assertCreated()->json('data.delivery_no');
        $this->as()->postJson("/api/v1/finishing/store-deliveries/{$no}/send")->assertOk();
        $this->as()->postJson("/api/v1/finishing/store-deliveries/{$no}/receive")->assertOk();

        return collect($this->opts()['lots'])->firstWhere('batch_no', $batch)['value'];
    }

    /** @return array<string, mixed> */
    private function opts(): array
    {
        return $this->as()->getJson('/api/v1/finishing/store/options')->assertOk()->json('data');
    }

    private function token(string $role, string $email): string
    {
        return app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, $role, $email), $this->delta->id, 'Test token')['plain'];
    }

    private function as(): static
    {
        return $this->withToken(app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Test token')['plain']);
    }
}
