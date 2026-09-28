<?php

declare(strict_types=1);

namespace Tests\Feature\Procurement;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Bin;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\Procurement\Models\PurchaseOrderLine;
use App\Modules\Procurement\Models\RateContract;
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
 * Rate contracts (Procurement spec §14).
 *
 * The contract only earns its keep if an order actually picks the price
 * up, so most of this is about the seam between the two: a line that says
 * nothing gets the agreed rate, a line that names a price keeps it, and a
 * price break is honoured by the quantity the order really is.
 *
 * The other half is the rule that makes any of that trustworthy — two live
 * contracts must never be able to answer for the same item, because then
 * "the agreed price" has two values and the system is guessing.
 */
class RateContractTest extends TestCase
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
            'company_id' => $this->delta->id, 'name' => 'Matin Textile Mills', 'code' => 'MTM',
            'vendor_type' => 'SUPPLIER', 'status' => 'ACTIVE', 'default_currency' => 'BDT',
        ]);

        $this->fabric = ItemFixture::item($this->delta, 'FAB-CVC-200', 'CVC Jersey 200gsm');
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    private function contract(array $lines, array $overrides = []): array
    {
        return $this->withToken($this->tokenFor($this->storeManager))
            ->postJson('/api/v1/rate-contracts', array_merge([
                'factory_id' => $this->dhaka->id,
                'vendor_id' => $this->mill->id,
                'valid_from' => CarbonImmutable::today()->subDay()->toDateString(),
                'valid_to' => CarbonImmutable::today()->addYear()->toDateString(),
                'lines' => $lines,
            ], $overrides))->assertCreated()->json('data');
    }

    /**
     * @return array<string, mixed>
     */
    private function order(array $line): array
    {
        return $this->withToken($this->tokenFor($this->storeManager))
            ->postJson('/api/v1/purchase-orders', [
                'factory_id' => $this->dhaka->id,
                'vendor_id' => $this->mill->id,
                'lines' => [$line],
            ])->assertCreated()->json('data');
    }

    public function test_an_order_line_with_no_rate_takes_the_agreed_one(): void
    {
        $contract = $this->contract([
            ['item_id' => $this->fabric->id, 'rate' => '200', 'lead_time_days' => 21],
        ]);

        $this->assertStringStartsWith('RC-DHK-', $contract['contract_no']);
        $this->assertSame('DRAFT', $contract['status']);
        // A draft answers nothing: the order below would price at zero.
        $this->assertFalse($contract['is_live']);

        $this->withToken($this->tokenFor($this->storeManager))
            ->postJson("/api/v1/rate-contracts/{$contract['id']}/activate")
            ->assertOk()->assertJsonPath('data.status', 'ACTIVE')
            ->assertJsonPath('data.is_live', true);

        $order = $this->order(['item_id' => $this->fabric->id, 'ordered_qty' => '1000']);

        $this->assertSame('200.0000', $order['lines'][0]['rate']);
        $this->assertSame('200000.0000', $order['subtotal']);

        // And it records which agreement it came from, so the price has a
        // provenance rather than being a number somebody once typed.
        $this->assertSame(
            $contract['id'],
            PurchaseOrderLine::findOrFail($order['lines'][0]['id'])->rate_contract_id,
        );
    }

    public function test_a_price_the_buyer_typed_is_never_overwritten(): void
    {
        $contract = $this->contract([['item_id' => $this->fabric->id, 'rate' => '200']]);
        $this->withToken($this->tokenFor($this->storeManager))
            ->postJson("/api/v1/rate-contracts/{$contract['id']}/activate")->assertOk();

        // A buyer naming a rate is making a decision — a one-off discount,
        // a correction — and the contract must not take it back.
        $order = $this->order(['item_id' => $this->fabric->id, 'ordered_qty' => '500', 'rate' => '185']);

        $this->assertSame('185.0000', $order['lines'][0]['rate']);
        $this->assertNull(PurchaseOrderLine::findOrFail($order['lines'][0]['id'])->rate_contract_id);
    }

    public function test_a_price_break_is_honoured_by_the_quantity_actually_ordered(): void
    {
        $contract = $this->contract([
            ['item_id' => $this->fabric->id, 'rate' => '200', 'min_quantity' => '0'],
            ['item_id' => $this->fabric->id, 'rate' => '190', 'min_quantity' => '500'],
        ]);

        $this->withToken($this->tokenFor($this->storeManager))
            ->postJson("/api/v1/rate-contracts/{$contract['id']}/activate")->assertOk();

        $small = $this->order(['item_id' => $this->fabric->id, 'ordered_qty' => '400']);
        $this->assertSame('200.0000', $small['lines'][0]['rate']);

        $large = $this->order(['item_id' => $this->fabric->id, 'ordered_qty' => '500']);
        // Exactly on the band: the break applies at it, not above it.
        $this->assertSame('190.0000', $large['lines'][0]['rate']);

        // The lookup the order form uses says the same thing.
        $this->withToken($this->tokenFor($this->storekeeper))
            ->getJson('/api/v1/rate-contracts/lookup?'.http_build_query([
                'vendor_id' => $this->mill->id,
                'item_id' => $this->fabric->id,
                'quantity' => '900',
            ]))->assertOk()
            ->assertJsonPath('data.rate', '190.0000')
            ->assertJsonPath('data.contract.contract_no', $contract['contract_no']);
    }

    public function test_two_live_contracts_cannot_answer_for_the_same_item(): void
    {
        $first = $this->contract([['item_id' => $this->fabric->id, 'rate' => '200']]);
        $token = $this->tokenFor($this->storeManager);

        $this->withToken($token)->postJson("/api/v1/rate-contracts/{$first['id']}/activate")->assertOk();

        // Drafting a replacement while the old one runs is fine — that is
        // how a renegotiation is typed up.
        $second = $this->contract([['item_id' => $this->fabric->id, 'rate' => '180']]);

        // Switching it on is not: the price would have two answers.
        $this->withToken($token)->postJson("/api/v1/rate-contracts/{$second['id']}/activate")
            ->assertStatus(409)->assertJsonValidationErrors(['lines']);

        // A different band is a price break, not a clash.
        $break = $this->contract([['item_id' => $this->fabric->id, 'rate' => '175', 'min_quantity' => '1000']]);
        $this->withToken($token)->postJson("/api/v1/rate-contracts/{$break['id']}/activate")->assertOk();

        // Once the old one is out of the way the replacement goes live.
        $this->withToken($token)->postJson("/api/v1/rate-contracts/{$first['id']}/cancel", [
            'reason' => 'Renegotiated.',
        ])->assertOk();

        $this->withToken($token)->postJson("/api/v1/rate-contracts/{$second['id']}/activate")->assertOk();

        $this->assertSame('180.0000', $this->order([
            'item_id' => $this->fabric->id, 'ordered_qty' => '10',
        ])['lines'][0]['rate']);
    }

    public function test_a_contract_outside_its_dates_prices_nothing(): void
    {
        $token = $this->tokenFor($this->storeManager);

        $expired = $this->contract(
            [['item_id' => $this->fabric->id, 'rate' => '150']],
            [
                'valid_from' => CarbonImmutable::today()->subYear()->toDateString(),
                'valid_to' => CarbonImmutable::today()->subDay()->toDateString(),
            ],
        );

        $this->withToken($token)->postJson("/api/v1/rate-contracts/{$expired['id']}/activate")
            ->assertOk()
            // Active, but its period is behind us — the status cannot say
            // that on its own, which is why expiry is read off the calendar.
            ->assertJsonPath('data.status', 'ACTIVE')
            ->assertJsonPath('data.is_live', false)
            ->assertJsonPath('data.has_expired', true);

        $order = $this->order(['item_id' => $this->fabric->id, 'ordered_qty' => '100']);

        $this->assertSame('0.0000', $order['lines'][0]['rate']);
        $this->assertNull(PurchaseOrderLine::findOrFail($order['lines'][0]['id'])->rate_contract_id);
    }

    public function test_an_active_contract_is_not_edited_in_place_and_needs_the_right_hands(): void
    {
        $contract = $this->contract([['item_id' => $this->fabric->id, 'rate' => '200']]);
        $manager = $this->tokenFor($this->storeManager);

        // A draft is still being negotiated, so it can be changed.
        $this->withToken($manager)->patchJson("/api/v1/rate-contracts/{$contract['id']}", [
            'title' => 'Autumn cotton programme',
            'lines' => [['item_id' => $this->fabric->id, 'rate' => '205']],
        ])->assertOk()->assertJsonPath('data.lines.0.rate', '205.0000');

        $this->withToken($manager)->postJson("/api/v1/rate-contracts/{$contract['id']}/activate")->assertOk();

        // Once live, orders have been priced from it: editing would rewrite
        // the reason those orders say what they say.
        $this->withToken($manager)->patchJson("/api/v1/rate-contracts/{$contract['id']}", [
            'lines' => [['item_id' => $this->fabric->id, 'rate' => '1']],
        ])->assertStatus(409);

        // Agreeing a price commits the factory for a year, so a storekeeper
        // may read one but not make one.
        $storekeeper = $this->tokenFor($this->storekeeper);

        $this->withToken($storekeeper)->getJson('/api/v1/rate-contracts')->assertOk();
        $this->withToken($storekeeper)->postJson('/api/v1/rate-contracts', [
            'factory_id' => $this->dhaka->id,
            'vendor_id' => $this->mill->id,
            'valid_from' => CarbonImmutable::today()->toDateString(),
            'valid_to' => CarbonImmutable::today()->addYear()->toDateString(),
            'lines' => [['item_id' => $this->fabric->id, 'rate' => '1']],
        ])->assertForbidden();

        $this->assertSame(1, RateContract::where('status', 'ACTIVE')->count());
    }
}
