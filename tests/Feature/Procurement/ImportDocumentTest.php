<?php

declare(strict_types=1);

namespace Tests\Feature\Procurement;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Bin;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemStockTransaction;
use App\Modules\Notification\Models\Notification;
use App\Modules\Procurement\Models\LetterOfCredit;
use App\Modules\Procurement\Models\ProformaInvoice;
use App\Modules\Procurement\Models\Shipment;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Vendor\Models\Vendor;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryFixture;
use Tests\Support\ItemFixture;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * The paperwork behind an imported order (Procurement spec §10).
 *
 * What these tests are really pinning down is that the chain keeps its
 * history: a revised proforma invoice does not overwrite the one the bank
 * opened against, an amendment keeps its own charge and reason, and a
 * shipment that slips keeps the ETA it was first given. None of it moves
 * stock — the goods only exist once a receipt is posted against them.
 */
class ImportDocumentTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Bin $bin;

    private User $storeManager;

    private User $storekeeper;

    private Vendor $mill;

    private Item $fabric;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->bin = InventoryFixture::bin($this->delta, $this->dhaka);
        $this->storeManager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'sm@delta.test');
        $this->storekeeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'sk@delta.test');

        $this->mill = Vendor::create([
            'company_id' => $this->delta->id, 'name' => 'Ningbo Textile Co', 'code' => 'NBT',
            'vendor_type' => 'SUPPLIER', 'status' => 'ACTIVE', 'default_currency' => 'USD',
        ]);

        $this->fabric = ItemFixture::item($this->delta, 'FAB-CVC-200', 'CVC Single Jersey 200gsm');
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }

    /**
     * @return array<string, mixed>
     */
    private function importOrder(): array
    {
        return $this->withToken($this->tokenFor($this->storeManager))
            ->postJson('/api/v1/purchase-orders', [
                'factory_id' => $this->dhaka->id,
                'vendor_id' => $this->mill->id,
                'purchase_type' => 'IMPORT',
                'currency' => 'USD',
                'lines' => [['item_id' => $this->fabric->id, 'ordered_qty' => '10000', 'rate' => '3.5']],
            ])->assertCreated()->json('data');
    }

    public function test_the_import_chain_runs_from_invoice_to_cleared_shipment(): void
    {
        $token = $this->tokenFor($this->storeManager);
        $order = $this->importOrder();

        $this->assertSame('IMPORT', $order['purchase_type']);
        $this->assertSame('35000.0000', $order['subtotal']);

        $pi = $this->withToken($token)->postJson("/api/v1/purchase-orders/{$order['id']}/proforma-invoices", [
            'pi_no' => 'NBT/PI/2026/114',
            'amount' => '35000',
            'incoterm' => 'FOB',
            'port_of_loading' => 'Ningbo',
            'port_of_discharge' => 'Chattogram',
        ])->assertCreated()->json('data');

        $this->assertSame(1, $pi['version']);
        $this->assertSame('DRAFT', $pi['status']);

        $this->withToken($token)->postJson("/api/v1/proforma-invoices/{$pi['id']}/confirm")
            ->assertOk()->assertJsonPath('data.status', 'CONFIRMED');

        $lc = $this->withToken($token)->postJson("/api/v1/purchase-orders/{$order['id']}/letters-of-credit", [
            'lc_no' => 'LC-0301-26-001',
            'bank_name' => 'Janata Bank',
            'branch' => 'Local Office',
            'amount' => '35000',
            'proforma_invoice_id' => $pi['id'],
            'expiry_date' => CarbonImmutable::today()->addDays(90)->toDateString(),
            'tenor_days' => 120,
        ])->assertCreated()->json('data');

        // The order is worth exactly what the bank committed.
        $this->assertSame('0.0000', $lc['variance_against_order']);
        $this->assertSame('DRAFT', $lc['status']);

        $this->withToken($token)->postJson("/api/v1/letters-of-credit/{$lc['id']}/open")
            ->assertOk()->assertJsonPath('data.status', 'OPENED');

        $shipment = $this->withToken($token)->postJson("/api/v1/purchase-orders/{$order['id']}/shipments", [
            'mode' => 'SEA',
            'letter_of_credit_id' => $lc['id'],
            'bl_awb_no' => 'MAEU-7741820',
            'container_no' => 'MSKU-9930112',
            'shipping_date' => CarbonImmutable::today()->toDateString(),
            'eta' => CarbonImmutable::today()->addDays(21)->toDateString(),
        ])->assertCreated()->json('data');

        $this->assertStringStartsWith('SHP-DHK-', $shipment['shipment_no']);
        $this->assertSame('PLANNED', $shipment['status']);
        $this->assertSame('AWAITED', $shipment['document_status']);
        // The ports come off the invoice rather than being typed again.
        $this->assertSame('Ningbo', $shipment['port_of_loading']);
        $this->assertSame(21, $shipment['days_to_eta']);

        // Shipping it drags the letter of credit along.
        $this->withToken($token)->patchJson("/api/v1/shipments/{$shipment['id']}", [
            'status' => 'SHIPPED', 'document_status' => 'AT_BANK',
        ])->assertOk()->assertJsonPath('data.status', 'SHIPPED');

        $this->assertSame('SHIPPED', LetterOfCredit::findOrFail($lc['id'])->status);

        $this->withToken($token)->patchJson("/api/v1/shipments/{$shipment['id']}", ['status' => 'IN_TRANSIT'])
            ->assertOk();

        $arrived = $this->withToken($token)->patchJson("/api/v1/shipments/{$shipment['id']}", [
            'status' => 'ARRIVED', 'document_status' => 'RELEASED',
        ])->assertOk()->json('data');

        // Landing without a date would leave the delivery record blank.
        $this->assertSame(CarbonImmutable::today()->toDateString(), $arrived['actual_arrival_date']);

        $cleared = $this->withToken($token)->patchJson("/api/v1/shipments/{$shipment['id']}", [
            'status' => 'CLEARED',
        ])->assertOk()->json('data');

        $this->assertNotNull($cleared['cleared_date']);

        $this->withToken($token)->postJson("/api/v1/letters-of-credit/{$lc['id']}/close")
            ->assertOk()->assertJsonPath('data.status', 'CLOSED');

        // Papers, not goods: nothing reached the ledger.
        $this->assertSame(0, ItemStockTransaction::count());

        // The whole file reads back off the order.
        $file = $this->withToken($token)->getJson("/api/v1/purchase-orders/{$order['id']}/import-documents")
            ->assertOk()->json('data');

        $this->assertCount(1, $file['proforma_invoices']);
        $this->assertCount(1, $file['letters_of_credit']);
        $this->assertCount(1, $file['shipments']);
        $this->assertTrue($file['can_manage']);
    }

    public function test_a_revised_invoice_supersedes_rather_than_overwrites(): void
    {
        $token = $this->tokenFor($this->storeManager);
        $order = $this->importOrder();

        $first = $this->withToken($token)->postJson("/api/v1/purchase-orders/{$order['id']}/proforma-invoices", [
            'pi_no' => 'NBT/PI/2026/114', 'amount' => '35000', 'incoterm' => 'FOB',
        ])->assertCreated()->json('data');

        $this->withToken($token)->postJson("/api/v1/proforma-invoices/{$first['id']}/confirm")->assertOk();

        $second = $this->withToken($token)->postJson("/api/v1/proforma-invoices/{$first['id']}/revise", [
            'pi_no' => 'NBT/PI/2026/114-R1', 'amount' => '36400',
        ])->assertCreated()->json('data');

        $this->assertSame(2, $second['version']);
        $this->assertSame($first['id'], $second['supersedes_id']);
        $this->assertSame('CONFIRMED', $second['status']);
        // The document the bank saw still says what it said.
        $this->assertSame('SUPERSEDED', ProformaInvoice::findOrFail($first['id'])->status);
        $this->assertSame('35000.0000', (string) ProformaInvoice::findOrFail($first['id'])->amount);
        // Terms it did not restate carry across.
        $this->assertSame('FOB', $second['incoterm']);

        // The old version is finished: it takes neither a revision nor an LC.
        $this->withToken($token)->postJson("/api/v1/proforma-invoices/{$first['id']}/revise", [
            'amount' => '1',
        ])->assertStatus(409);

        $this->withToken($token)->postJson("/api/v1/purchase-orders/{$order['id']}/letters-of-credit", [
            'lc_no' => 'LC-STALE', 'bank_name' => 'Janata Bank', 'amount' => '35000',
            'proforma_invoice_id' => $first['id'],
        ])->assertStatus(409);

        $this->withToken($token)->postJson("/api/v1/purchase-orders/{$order['id']}/letters-of-credit", [
            'lc_no' => 'LC-0301-26-002', 'bank_name' => 'Janata Bank', 'amount' => '36400',
            'proforma_invoice_id' => $second['id'],
        ])->assertCreated();
    }

    public function test_an_amendment_keeps_its_charge_and_its_reason(): void
    {
        $token = $this->tokenFor($this->storeManager);
        $order = $this->importOrder();
        $expiry = CarbonImmutable::today()->addDays(60);

        $lc = $this->withToken($token)->postJson("/api/v1/purchase-orders/{$order['id']}/letters-of-credit", [
            'lc_no' => 'LC-0301-26-003', 'bank_name' => 'Janata Bank', 'amount' => '30000',
            'expiry_date' => $expiry->toDateString(),
        ])->assertCreated()->json('data');

        // Short of the order by 5,000 — which is why it needs amending.
        $this->assertSame('-5000.0000', $lc['variance_against_order']);

        $this->withToken($token)->postJson("/api/v1/letters-of-credit/{$lc['id']}/open")->assertOk();

        // An amendment costs money and changes what the mill can draw, so
        // it cannot be filed silently.
        $this->withToken($token)->postJson("/api/v1/letters-of-credit/{$lc['id']}/amend", [
            'new_amount' => '35000',
        ])->assertStatus(422)->assertJsonValidationErrors(['reason']);

        $extended = $expiry->addDays(30);

        $amended = $this->withToken($token)->postJson("/api/v1/letters-of-credit/{$lc['id']}/amend", [
            'new_amount' => '35000',
            'new_expiry_date' => $extended->toDateString(),
            'bank_charge' => '4500',
            'reason' => 'Order raised to 10,000 yds after the second cutting plan.',
        ])->assertOk()->json('data');

        $this->assertSame('35000.0000', $amended['amount']);
        $this->assertSame('0.0000', $amended['variance_against_order']);
        $this->assertCount(1, $amended['amendments']);

        $row = $amended['amendments'][0];
        $this->assertSame(1, $row['amendment_no']);
        $this->assertSame('30000.0000', $row['previous_amount']);
        $this->assertSame('5000.0000', $row['amount_change']);
        $this->assertSame('4500.0000', $row['bank_charge']);
        $this->assertSame($expiry->toDateString(), $row['previous_expiry_date']);
        $this->assertSame($extended->toDateString(), $row['new_expiry_date']);

        $second = $this->withToken($token)->postJson("/api/v1/letters-of-credit/{$lc['id']}/amend", [
            'new_expiry_date' => $extended->addDays(21)->toDateString(),
            'bank_charge' => '2500',
            'reason' => 'Vessel rolled over to the next sailing.',
        ])->assertOk()->json('data');

        $this->assertCount(2, $second['amendments']);
        $this->assertSame(2, $second['amendments'][1]['amendment_no']);
        // An expiry-only amendment leaves the commitment alone.
        $this->assertSame('0.0000', $second['amendments'][1]['amount_change']);
        $this->assertSame($extended->addDays(21)->toDateString(), $second['expiry_date']);

        // Once it is closed the bank is done with it.
        $this->withToken($token)->postJson("/api/v1/letters-of-credit/{$lc['id']}/close")->assertOk();
        $this->withToken($token)->postJson("/api/v1/letters-of-credit/{$lc['id']}/amend", [
            'new_amount' => '1000', 'reason' => 'Too late.',
        ])->assertStatus(409);
    }

    public function test_a_slipping_eta_is_recorded_against_the_original_not_over_it(): void
    {
        $token = $this->tokenFor($this->storeManager);
        $order = $this->importOrder();
        $promised = CarbonImmutable::today()->addDays(20);

        $shipment = $this->withToken($token)->postJson("/api/v1/purchase-orders/{$order['id']}/shipments", [
            'mode' => 'SEA', 'eta' => $promised->toDateString(),
        ])->assertCreated()->json('data');

        $this->assertSame($promised->toDateString(), $shipment['original_eta']);
        $this->assertSame($promised->toDateString(), $shipment['eta']);

        $slipped = $this->withToken($token)->patchJson("/api/v1/shipments/{$shipment['id']}", [
            'status' => 'SHIPPED',
            'eta' => $promised->addDays(9)->toDateString(),
            'notes' => 'Vessel rolled over at Singapore.',
        ])->assertOk()->json('data');

        // The promise is still on the record; only the forecast moved.
        $this->assertSame($promised->toDateString(), $slipped['original_eta']);
        $this->assertSame($promised->addDays(9)->toDateString(), $slipped['eta']);

        $landed = $this->withToken($token)->patchJson("/api/v1/shipments/{$shipment['id']}", [
            'status' => 'ARRIVED',
            'actual_arrival_date' => $promised->addDays(7)->toDateString(),
        ])->assertOk()->json('data');

        // Seven days late against what was first promised — the supplier's
        // delivery record, not the forwarder's latest guess.
        $this->assertSame(7, $landed['delay_days']);
        $this->assertFalse($landed['overdue']);

        // The status ladder is not a free-for-all: it has already arrived.
        $this->withToken($token)->patchJson("/api/v1/shipments/{$shipment['id']}", ['status' => 'SHIPPED'])
            ->assertStatus(409);

        // And it is too late to cancel something that has landed.
        $this->withToken($token)->postJson("/api/v1/shipments/{$shipment['id']}/cancel", [
            'reason' => 'Changed our minds.',
        ])->assertStatus(409);
    }

    public function test_the_shipment_board_shows_what_is_still_coming_and_what_is_late(): void
    {
        $token = $this->tokenFor($this->storeManager);
        $order = $this->importOrder();

        $late = $this->withToken($token)->postJson("/api/v1/purchase-orders/{$order['id']}/shipments", [
            'mode' => 'SEA', 'eta' => CarbonImmutable::today()->subDays(4)->toDateString(),
        ])->assertCreated()->json('data');

        $soon = $this->withToken($token)->postJson("/api/v1/purchase-orders/{$order['id']}/shipments", [
            'mode' => 'AIR', 'eta' => CarbonImmutable::today()->addDays(3)->toDateString(),
        ])->assertCreated()->json('data');

        $done = $this->withToken($token)->postJson("/api/v1/purchase-orders/{$order['id']}/shipments", [
            'mode' => 'SEA', 'eta' => CarbonImmutable::today()->subDays(30)->toDateString(),
        ])->assertCreated()->json('data');

        $this->withToken($token)->patchJson("/api/v1/shipments/{$done['id']}", [
            'status' => 'SHIPPED',
        ])->assertOk();
        $this->withToken($token)->patchJson("/api/v1/shipments/{$done['id']}", [
            'status' => 'ARRIVED',
        ])->assertOk();

        $board = $this->withToken($token)->getJson('/api/v1/shipments?in_flight=1&sort=eta')
            ->assertOk()->json('data');

        $this->assertCount(2, $board);
        // Oldest ETA first: the one that should already be here.
        $this->assertSame($late['id'], $board[0]['id']);
        $this->assertTrue($board[0]['overdue']);
        $this->assertSame(-4, $board[0]['days_to_eta']);
        $this->assertSame($soon['id'], $board[1]['id']);
        $this->assertFalse($board[1]['overdue']);

        // Filters read the same words the board shows.
        $this->withToken($token)->getJson('/api/v1/shipments?mode=AIR')
            ->assertOk()->assertJsonCount(1, 'data');

        $this->withToken($token)->getJson('/api/v1/shipments?status=ARRIVED')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $done['id']);
    }

    public function test_the_daily_sweep_reports_what_is_late_and_what_the_bank_is_about_to_close(): void
    {
        $token = $this->tokenFor($this->storeManager);
        $order = $this->importOrder();

        // One landing inside the notice period, one already overdue, one
        // still months away.
        $soon = $this->withToken($token)->postJson("/api/v1/purchase-orders/{$order['id']}/shipments", [
            'mode' => 'AIR', 'eta' => CarbonImmutable::today()->addDays(3)->toDateString(),
        ])->assertCreated()->json('data');

        $late = $this->withToken($token)->postJson("/api/v1/purchase-orders/{$order['id']}/shipments", [
            'mode' => 'SEA', 'eta' => CarbonImmutable::today()->subDays(6)->toDateString(),
        ])->assertCreated()->json('data');

        $this->withToken($token)->postJson("/api/v1/purchase-orders/{$order['id']}/shipments", [
            'mode' => 'SEA', 'eta' => CarbonImmutable::today()->addDays(60)->toDateString(),
        ])->assertCreated();

        // An open letter of credit about to lapse, and a closed one that
        // cannot lapse at all.
        $lapsing = $this->withToken($token)->postJson("/api/v1/purchase-orders/{$order['id']}/letters-of-credit", [
            'lc_no' => 'LC-LAPSING', 'bank_name' => 'Janata Bank', 'amount' => '35000',
            'expiry_date' => CarbonImmutable::today()->addDays(9)->toDateString(),
        ])->assertCreated()->json('data');

        $this->withToken($token)->postJson("/api/v1/letters-of-credit/{$lapsing['id']}/open")->assertOk();

        $closed = $this->withToken($token)->postJson("/api/v1/purchase-orders/{$order['id']}/letters-of-credit", [
            'lc_no' => 'LC-DONE', 'bank_name' => 'Janata Bank', 'amount' => '100',
            'expiry_date' => CarbonImmutable::today()->addDays(2)->toDateString(),
        ])->assertCreated()->json('data');

        $this->withToken($token)->postJson("/api/v1/letters-of-credit/{$closed['id']}/open")->assertOk();
        $this->withToken($token)->postJson("/api/v1/letters-of-credit/{$closed['id']}/close")->assertOk();

        // The command sweeps every company and drops the tenant context on
        // the way out, so the test puts it back before reading anything.
        $this->artisan('procurement:shipment-alerts')->assertSuccessful();
        TenantFixture::actingAsTenant($this->delta);

        $this->assertSame(1, $this->alertsAbout('SHIPMENT_ARRIVING', $soon['id']));
        $this->assertSame(1, $this->alertsAbout('SHIPMENT_DELAYED', $late['id']));
        // Two months out is not news, and a closed LC has nothing to lose.
        $this->assertSame(2, Notification::whereIn('event_type', ['SHIPMENT_ARRIVING', 'SHIPMENT_DELAYED'])
            ->where('user_id', $this->storeManager->id)->count());
        $this->assertSame(1, $this->alertsAbout('LC_EXPIRING', $lapsing['id']));
        $this->assertSame(0, $this->alertsAbout('LC_EXPIRING', $closed['id']));

        // Run again the same day: chased once, not once per sweep.
        $this->artisan('procurement:shipment-alerts')->assertSuccessful();
        TenantFixture::actingAsTenant($this->delta);

        $this->assertSame(1, $this->alertsAbout('SHIPMENT_DELAYED', $late['id']));
    }

    /** How many times the store manager has been told about one document. */
    private function alertsAbout(string $event, string $entityId): int
    {
        return Notification::where('event_type', $event)
            ->where('entity_id', $entityId)
            ->where('user_id', $this->storeManager->id)
            ->count();
    }

    public function test_the_papers_are_the_buying_desks_and_another_companys_order_is_invisible(): void
    {
        $order = $this->importOrder();
        $storekeeper = $this->tokenFor($this->storekeeper);

        // A storekeeper needs to know what is landing, not to file it.
        $this->withToken($storekeeper)->getJson("/api/v1/purchase-orders/{$order['id']}/import-documents")
            ->assertOk()->assertJsonPath('data.can_manage', false);

        $this->withToken($storekeeper)->postJson("/api/v1/purchase-orders/{$order['id']}/proforma-invoices", [
            'pi_no' => 'X', 'amount' => '1',
        ])->assertForbidden();

        // A different company's order is not there at all.
        $other = TenantFixture::company('Orion Garments Ltd', 'OGL');
        $otherManager = TenantFixture::user($other, 'STORE_MANAGER', 'sm@orion.test');
        TenantFixture::actingAsTenant($this->delta);

        $this->withToken($this->tokenFor($otherManager))
            ->getJson("/api/v1/purchase-orders/{$order['id']}/import-documents")
            ->assertNotFound();

        $this->assertSame(0, Shipment::withoutGlobalScopes()->where('purchase_order_id', $order['id'])->count());
    }
}
