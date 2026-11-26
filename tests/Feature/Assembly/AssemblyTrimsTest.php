<?php

declare(strict_types=1);

namespace Tests\Feature\Assembly;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Planning\Models\PlanningLine;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Floor;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Assembly Floor → Accessories Store (AnnotechERP 20/08): a trims receipt
 * (MRR) drafted, rewritten while a draft and completed into a store's stock
 * (or cancelled); an issue to an order and sewing line submitted, then
 * approved off stock — never more than the store holds — or rejected with
 * the reason; a receipt return against a completed MRR, never more than it
 * brought in, off stock once completed — and who may.
 */
class AssemblyTrimsTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $trims;

    private string $floor;

    private string $button;

    private string $thread;

    private string $fabric;

    private string $supplier;

    private string $order;

    private string $line;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-06 10:00:00', 'Asia/Dhaka'));
        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $unit = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $warehouse = Warehouse::query()->create(['company_id' => $this->delta->id, 'factory_id' => $unit->id, 'name' => 'Main Warehouse', 'code' => 'WH-1', 'active' => true]);
        $this->trims = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => 'Trims Store', 'code' => 'ST-TRM', 'active' => true])->id;
        $this->floor = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => 'Sewing Floor Store', 'code' => 'ST-FLR', 'active' => true])->id;
        $item = fn (string $kind, string $code, string $name, string $uom) => MaterialItem::query()->create(['company_id' => $this->delta->id, 'kind' => $kind, 'item_code' => $code, 'name' => $name, 'category' => 'Trims', 'uom' => $uom, 'active' => true])->id;
        $this->button = $item('TRIMS', 'TR-BTN-12', 'Snap Button 12L', 'PCS');
        $this->thread = $item('TRIMS', 'TR-THR-40', 'Sewing Thread 40/2', 'CONE');
        $this->fabric = $item('FABRIC', 'FB-SJ-160', 'Single Jersey 160', 'KG');
        $this->supplier = MaterialSupplier::query()->create(['company_id' => $this->delta->id, 'code' => 'SUP-1', 'name' => 'Coats Bangladesh', 'supplier_type' => 'LOCAL', 'active' => true])->id;
        $lpp = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $this->order = Order::query()->create(['company_id' => $this->delta->id, 'order_no' => 'ORD-11565705', 'job_no' => '868MI', 'style_no' => '868MI', 'buyer_id' => $lpp, 'order_type' => 'BULK_NEW', 'total_qty' => 600,
            'ship_date' => '2026-12-15', 'status' => 'CONFIRMED', 'po_no' => '11565705', 'item' => 'T-Shirt'])->id;
        $building = Building::query()->create(['company_id' => $this->delta->id, 'factory_id' => $unit->id, 'name' => 'Main Building', 'code' => 'BLD-1']);
        $sewFloor = Floor::query()->create(['company_id' => $this->delta->id, 'building_id' => $building->id, 'name' => 'Sewing 1', 'code' => 'FLR-001']);
        $this->line = PlanningLine::query()->create(['company_id' => $this->delta->id, 'floor_id' => $sewFloor->id, 'code' => 'LINE01', 'name' => 'Line LINE01', 'std_manpower' => 40, 'std_machines' => 30, 'std_hours' => 10, 'std_efficiency' => 65, 'active' => true])->id;
    }

    public function test_a_receipt_is_drafted_rewritten_and_completed_into_stock(): void
    {
        $o = $this->as()->getJson('/api/v1/assembly/trims/options')->assertOk()->json('data');
        $this->assertSame(['Sewing Floor Store (ST-FLR)', 'Trims Store (ST-TRM)'], array_column($o['stores'], 'label'));
        $this->assertSame(['TR-THR-40 — Sewing Thread 40/2', 'TR-BTN-12 — Snap Button 12L'], array_column($o['items'], 'label'));
        $this->assertSame(['CASH', 'LC', 'CHEQUE', 'TT', 'CONSIGNMENT'], $o['pay_methods']);
        $this->assertSame(['868MI — ORD-11565705 [868MI]'], array_column($o['orders'], 'label'));
        $this->assertSame(['LINE01 · Line LINE01'], array_column($o['lines'], 'label'));

        $mrr = ['mrr_date' => '2026-11-06', 'store_id' => $this->trims, 'supplier_id' => $this->supplier, 'req_type' => 'Store Requisition', 'pay_method' => 'LC', 'pr_no' => 'PR-77', 'po_no' => 'PO-88',
            'lines' => [['item_id' => $this->button, 'qty' => 500, 'uom' => 'PCS', 'rate' => 1.5, 'description' => '12L antique brass'], ['item_id' => $this->thread, 'qty' => 100, 'rate' => 120]]];
        $this->as()->postJson('/api/v1/assembly/trims/receipts', ['mrr_date' => null] + $mrr)->assertUnprocessable()->assertJsonPath('errors.mrr_date.0', 'MRR Date is required.');
        $this->as()->postJson('/api/v1/assembly/trims/receipts', ['pay_method' => null] + $mrr)->assertUnprocessable()->assertJsonPath('errors.pay_method.0', 'Pick the payment method.');
        $this->as()->postJson('/api/v1/assembly/trims/receipts', ['lines' => []] + $mrr)->assertUnprocessable()->assertJsonPath('errors.lines.0', 'Add at least one item.');
        $this->as()->postJson('/api/v1/assembly/trims/receipts', ['lines' => [['item_id' => $this->fabric, 'qty' => 5]]] + $mrr)->assertUnprocessable()->assertJsonValidationErrors('lines.0.item_id');
        $this->as()->postJson('/api/v1/assembly/trims/receipts', ['lines' => [['item_id' => $this->button, 'qty' => 0]]] + $mrr)->assertUnprocessable()->assertJsonFragment(['lines.0.qty' => ['Every row needs a quantity above 0.']]);

        $r = $this->as()->postJson('/api/v1/assembly/trims/receipts', $mrr)->assertCreated()->json('data');
        $this->assertSame(['MRR-2026110001', 'DRAFT', 'Trims Store', 'Coats Bangladesh', 2, 600.0, 12750.0], [$r['mrr_no'], $r['status'], $r['store'], $r['supplier'], $r['items'], (float) $r['qty'], (float) $r['value']]);
        $this->assertSame([['TR-BTN-12 — Snap Button 12L', 'PCS', '12L antique brass'], ['TR-THR-40 — Sewing Thread 40/2', null, null]], array_map(fn ($l) => [$l['item'], $l['uom'], $l['description']], $r['lines']));
        $r = $this->as()->postJson('/api/v1/assembly/trims/receipts/MRR-2026110001', ['lines' => [['item_id' => $this->button, 'qty' => 480, 'rate' => 1.5], $mrr['lines'][1]]] + $mrr)->assertOk()->json('data');
        $this->assertSame([480.0, 100.0], array_map('floatval', array_column($r['lines'], 'qty')));
        $this->assertSame([], $this->stock($this->trims));

        $this->as()->postJson('/api/v1/assembly/trims/receipts/MRR-2026110001/complete')->assertOk()->assertJsonPath('data.status', 'COMPLETED');
        $this->assertSame([[$this->button, 480.0], [$this->thread, 100.0]], $this->stock($this->trims));
        $this->as()->postJson('/api/v1/assembly/trims/receipts/MRR-2026110001', $mrr)->assertUnprocessable()->assertJsonPath('errors.status.0', 'MRR-2026110001 is no longer a draft and cannot be changed.');
        $this->as()->postJson('/api/v1/assembly/trims/receipts/MRR-2026110001/complete')->assertUnprocessable();
        $this->as()->postJson('/api/v1/assembly/trims/receipts/MRR-2026110001/cancel')->assertUnprocessable();

        $this->as()->postJson('/api/v1/assembly/trims/receipts', ['store_id' => $this->floor] + $mrr)->assertCreated()->assertJsonPath('data.mrr_no', 'MRR-2026110002');
        $this->as()->postJson('/api/v1/assembly/trims/receipts/MRR-2026110002/cancel')->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        $this->assertSame([], $this->stock($this->floor));
        $this->assertSame(['MRR-2026110002', 'MRR-2026110001'], array_column($this->as()->getJson('/api/v1/assembly/trims/receipts')->json('data'), 'mrr_no'));
        $this->as()->getJson('/api/v1/assembly/trims/receipts/MRR-404')->assertNotFound();
    }

    public function test_an_issue_is_submitted_then_approved_off_stock_never_more_than_held(): void
    {
        $this->received(500, 100);
        $iss = ['issue_date' => '2026-11-06', 'store_id' => $this->trims, 'requisition_no' => 'REQ-12', 'order_id' => $this->order, 'line_id' => $this->line, 'lines' => [['item_id' => $this->button, 'qty' => 600]]];
        $this->as()->postJson('/api/v1/assembly/trims/issues', ['issue_date' => null] + $iss)->assertUnprocessable()->assertJsonPath('errors.issue_date.0', 'Issue Date is required.');
        $i = $this->as()->postJson('/api/v1/assembly/trims/issues', $iss)->assertCreated()->json('data');
        $this->assertSame(['ISS-2026110001', 'DRAFT', '868MI — ORD-11565705 [868MI]', 'LINE01 · Line LINE01', 500.0], [$i['issue_no'], $i['status'], $i['order'], $i['line'], (float) $i['lines'][0]['in_store']]);

        $this->act('issues', 'ISS-2026110001', 'approve')->assertUnprocessable()->assertJsonPath('errors.status.0', 'ISS-2026110001 is Draft.');
        $this->act('issues', 'ISS-2026110001', 'submit')->assertOk()->assertJsonPath('data.status', 'SUBMITTED');
        $this->as()->postJson('/api/v1/assembly/trims/issues/ISS-2026110001', $iss)->assertUnprocessable();
        $chief = $this->token('LINE_CHIEF', 'chief@delta.test');
        $this->withToken($chief)->postJson('/api/v1/assembly/trims/issues/ISS-2026110001/approve')->assertForbidden();
        $this->act('issues', 'ISS-2026110001', 'approve')->assertUnprocessable()->assertJsonPath('errors.lines.0', 'Not enough TR-BTN-12 — Snap Button 12L in Trims Store — 500 in stock, 600 needed.');
        $this->assertSame('SUBMITTED', DB::table('asm_trims_issues')->where('issue_no', 'ISS-2026110001')->value('status'));
        $this->act('issues', 'ISS-2026110001', 'reject')->assertUnprocessable()->assertJsonPath('errors.reason.0', 'Enter the reason.');
        $this->act('issues', 'ISS-2026110001', 'reject', ['reason' => 'More than the store holds'])->assertOk()->assertJsonPath('data.status', 'REJECTED');

        $this->withToken($chief)->postJson('/api/v1/assembly/trims/issues', ['lines' => [['item_id' => $this->button, 'qty' => 200], ['item_id' => $this->thread, 'qty' => 30]]] + $iss)->assertCreated()->assertJsonPath('data.issue_no', 'ISS-2026110002');
        $this->withToken($chief)->postJson('/api/v1/assembly/trims/issues/ISS-2026110002/submit')->assertOk();
        $manager = $this->token('STORE_MANAGER', 'store@delta.test');
        $this->withToken($manager)->postJson('/api/v1/assembly/trims/issues/ISS-2026110002/approve')->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $this->assertSame([[$this->button, 300.0], [$this->thread, 70.0]], $this->stock($this->trims));
        $this->assertSame(['ISS-2026110002', 'ISS-2026110001'], array_column($this->as()->getJson('/api/v1/assembly/trims/issues')->json('data'), 'issue_no'));
        $this->assertSame('More than the store holds', $this->as()->getJson('/api/v1/assembly/trims/issues')->json('data.1.reject_reason'));
    }

    public function test_a_receipt_return_and_who_may(): void
    {
        $this->received(500, 100);
        $this->as()->postJson('/api/v1/assembly/trims/receipts', ['mrr_date' => '2026-11-06', 'store_id' => $this->trims, 'pay_method' => 'CASH', 'lines' => [['item_id' => $this->button, 'qty' => 5]]])->assertCreated();
        $this->as()->getJson('/api/v1/assembly/trims/returns/source/MRR-2026110002')->assertUnprocessable()->assertJsonPath('errors.mrr_no.0', 'MRR MRR-2026110002 is not a completed receipt.');

        $src = $this->as()->getJson('/api/v1/assembly/trims/returns/source/MRR-2026110001')->assertOk()->json('data');
        $this->assertSame([[500.0, 0.0, 500.0], [100.0, 0.0, 100.0]], array_map(fn ($l) => [(float) $l['received'], (float) $l['returned'], (float) $l['returnable']], $src['lines']));
        $line = $src['lines'][0]['id'];
        $ret = ['return_date' => '2026-11-06', 'mrr_no' => 'MRR-2026110001', 'reason' => 'DAMAGED', 'lines' => [['receipt_line_id' => $line, 'qty' => 600]]];
        $this->as()->postJson('/api/v1/assembly/trims/returns', $ret)->assertUnprocessable()->assertJsonFragment(['lines.0.qty' => ['Only 500 of TR-BTN-12 — Snap Button 12L is left to return.']]);
        $this->as()->postJson('/api/v1/assembly/trims/returns', ['return_date' => '2026-11-05'] + $ret)->assertUnprocessable()->assertJsonPath('errors.return_date.0', 'A return cannot be dated before its MRR MRR-2026110001.');
        $this->as()->postJson('/api/v1/assembly/trims/returns', ['reason' => null] + $ret)->assertUnprocessable()->assertJsonPath('errors.reason.0', 'Pick the return reason.');
        $r = $this->as()->postJson('/api/v1/assembly/trims/returns', ['lines' => [['receipt_line_id' => $line, 'qty' => 50, 'remarks' => 'Broken caps']]] + $ret)->assertCreated()->json('data');
        $this->assertSame(['RTN-2026110001', 'DRAFT', 'MRR-2026110001', 'Coats Bangladesh', 'DAMAGED', 50.0], [$r['return_no'], $r['status'], $r['mrr_no'], $r['supplier'], $r['reason'], (float) $r['qty']]);
        $this->assertSame([450.0, 500.0], [
            (float) $this->as()->getJson('/api/v1/assembly/trims/returns/source/MRR-2026110001')->json('data.lines.0.returnable'),
            (float) $this->as()->getJson('/api/v1/assembly/trims/returns/source/MRR-2026110001?except=RTN-2026110001')->json('data.lines.0.returnable'),
        ]);
        $this->as()->postJson('/api/v1/assembly/trims/returns/RTN-2026110001', ['lines' => [['receipt_line_id' => $line, 'qty' => 60]]] + $ret)->assertOk()->assertJsonPath('data.qty', 60);
        $this->act('returns', 'RTN-2026110001', 'complete')->assertOk()->assertJsonPath('data.status', 'COMPLETED');
        $this->assertSame([[$this->button, 440.0], [$this->thread, 100.0]], $this->stock($this->trims));
        $this->as()->postJson('/api/v1/assembly/trims/returns', ['lines' => [['receipt_line_id' => $line, 'qty' => 441]]] + $ret)->assertUnprocessable()->assertJsonFragment(['lines.0.qty' => ['Only 440 of TR-BTN-12 — Snap Button 12L is left to return.']]);
        $this->as()->postJson('/api/v1/assembly/trims/returns', ['reason' => 'SHORT', 'lines' => [['receipt_line_id' => $line, 'qty' => 10]]] + $ret)->assertCreated();
        $this->act('returns', 'RTN-2026110002', 'cancel')->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        $this->assertSame(['RTN-2026110002', 'RTN-2026110001'], array_column($this->as()->getJson('/api/v1/assembly/trims/returns')->json('data'), 'return_no'));

        $keeper = $this->token('STOREKEEPER', 'keeper@delta.test');
        $this->withToken($keeper)->postJson('/api/v1/assembly/trims/issues', ['issue_date' => '2026-11-06', 'store_id' => $this->trims, 'lines' => [['item_id' => $this->thread, 'qty' => 1]]])->assertCreated();
        $this->withToken($keeper)->postJson('/api/v1/assembly/trims/issues/ISS-2026110001/submit')->assertOk();
        $this->withToken($keeper)->postJson('/api/v1/assembly/trims/issues/ISS-2026110001/approve')->assertForbidden();
        $this->withToken($this->token('TECHNICIAN', 'tech@delta.test'))->getJson('/api/v1/assembly/trims/receipts')->assertForbidden();
        $this->withToken($this->token('VIEWER', 'viewer@delta.test'))->postJson('/api/v1/assembly/trims/receipts', [])->assertForbidden();
    }

    /** A completed receipt into the trims store: buttons and thread. */
    private function received(int $buttons, int $thread): void
    {
        $this->as()->postJson('/api/v1/assembly/trims/receipts', ['mrr_date' => '2026-11-06', 'store_id' => $this->trims, 'supplier_id' => $this->supplier, 'pay_method' => 'LC',
            'lines' => [['item_id' => $this->button, 'qty' => $buttons, 'rate' => 1.5], ['item_id' => $this->thread, 'qty' => $thread, 'rate' => 120]]])->assertCreated();
        $this->as()->postJson('/api/v1/assembly/trims/receipts/'.DB::table('asm_trims_receipts')->orderByDesc('mrr_no')->value('mrr_no').'/complete')->assertOk();
    }

    /** @return list<array{0: string, 1: float}> */
    private function stock(string $store): array
    {
        return collect($this->as()->getJson('/api/v1/assembly/trims/stock?store_id='.$store)->assertOk()->json('data'))
            ->map(fn ($s) => [$s['item_id'], (float) $s['qty']])->sortBy(fn ($s) => $s[0] === $this->button ? 0 : 1)->values()->all();
    }

    private function act(string $kind, string $no, string $action, array $payload = []): TestResponse
    {
        return $this->as()->postJson("/api/v1/assembly/trims/{$kind}/{$no}/{$action}", $payload);
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
