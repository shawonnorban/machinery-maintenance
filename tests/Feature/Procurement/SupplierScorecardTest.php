<?php

declare(strict_types=1);

namespace Tests\Feature\Procurement;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Bin;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\Procurement\Actions\CalculateSupplierScorecard;
use App\Modules\Procurement\Models\GoodsReceipt;
use App\Modules\Procurement\Models\SupplierScorecard;
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
 * Supplier scorecards (Procurement spec §15).
 *
 * The figures are judgement calls, and the judgement is what these tests
 * hold: a promise nobody made is not a promise broken, a measure with no
 * data is not a zero, and a supplier the factory never compared on price
 * is not thereby expensive. Each of those, got wrong, produces a number
 * somebody would quote at a mill — so each is pinned here.
 */
class SupplierScorecardTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Bin $bin;

    private User $storeManager;

    private User $storekeeper;

    private Vendor $mill;

    private Vendor $rival;

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

        $this->mill = $this->vendor('Matin Textile Mills', 'MTM');
        $this->rival = $this->vendor('Ningbo Textile Co', 'NBT');

        $this->fabric = ItemFixture::item($this->delta, 'FAB-CVC-200', 'CVC Jersey 200gsm');
    }

    private function vendor(string $name, string $code): Vendor
    {
        return Vendor::create([
            'company_id' => $this->delta->id, 'name' => $name, 'code' => $code,
            'vendor_type' => 'SUPPLIER', 'status' => 'ACTIVE', 'default_currency' => 'BDT',
        ]);
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }

    /**
     * A completed receipt, optionally against an order that promised a
     * date — which is what makes it count towards on-time delivery.
     */
    private function receive(
        Vendor $vendor,
        string $quantity,
        string $rate,
        ?string $promised = null,
        ?string $grnDate = null,
    ): GoodsReceipt {
        $token = $this->tokenFor($this->storeManager);
        $orderId = null;
        $orderLineId = null;

        if ($promised !== null) {
            $order = $this->withToken($token)->postJson('/api/v1/purchase-orders', [
                'factory_id' => $this->dhaka->id,
                'vendor_id' => $vendor->id,
                'delivery_date' => $promised,
                'lines' => [['item_id' => $this->fabric->id, 'ordered_qty' => $quantity, 'rate' => $rate]],
            ])->assertCreated()->json('data');

            $this->withToken($token)->postJson("/api/v1/purchase-orders/{$order['id']}/submit")->assertOk();
            $orderId = $order['id'];
            $orderLineId = $order['lines'][0]['id'];
        }

        $line = [
            'received_qty' => $quantity,
            'rate' => $rate,
            'bin_id' => $this->bin->id,
        ];

        // Against an order the receipt draws down that order's own line,
        // which is what ties the delivery to the date it promised.
        if ($orderLineId !== null) {
            $line['po_line_id'] = $orderLineId;
        } else {
            $line['item_id'] = $this->fabric->id;
        }

        $payload = [
            'factory_id' => $this->dhaka->id,
            'store_id' => $this->bin->store_id,
            'vendor_id' => $vendor->id,
            'lines' => [$line],
        ];

        if ($grnDate !== null) {
            $payload['grn_date'] = $grnDate;
        }

        if ($orderId !== null) {
            $payload['po_id'] = $orderId;
        }

        $grn = $this->withToken($token)->postJson('/api/v1/goods-receipts', $payload)
            ->assertCreated()->json('data');

        $this->withToken($token)->postJson("/api/v1/goods-receipts/{$grn['id']}/complete")->assertOk();

        return GoodsReceipt::findOrFail($grn['id']);
    }

    private function score(Vendor $vendor): SupplierScorecard
    {
        return app(CalculateSupplierScorecard::class)->forVendor(
            $vendor,
            CarbonImmutable::today()->subMonths(3),
            CarbonImmutable::today(),
        );
    }

    public function test_a_promise_nobody_made_is_not_a_promise_broken(): void
    {
        // One receipt against an order that promised today, delivered
        // today; one against no order at all.
        $this->receive($this->mill, '100', '200', promised: CarbonImmutable::today()->toDateString());
        $this->receive($this->mill, '50', '200');

        $card = $this->score($this->mill);

        $this->assertSame(2, (int) $card->receipt_count);
        // Only the dated one can be judged, and it was met.
        $this->assertSame(1, (int) $card->dated_receipt_count);
        $this->assertSame(1, (int) $card->on_time_count);
        $this->assertSame('100.00', $card->on_time_percent);
        $this->assertContains('on_time', $card->components_json);
    }

    public function test_a_late_delivery_shows_as_late(): void
    {
        $this->receive(
            $this->mill,
            '100',
            '200',
            promised: CarbonImmutable::today()->subDays(10)->toDateString(),
        );
        $this->receive(
            $this->mill,
            '100',
            '200',
            promised: CarbonImmutable::today()->toDateString(),
        );

        $card = $this->score($this->mill);

        $this->assertSame(2, (int) $card->dated_receipt_count);
        $this->assertSame(1, (int) $card->on_time_count);
        $this->assertSame('50.00', $card->on_time_percent);
    }

    public function test_a_measure_with_no_data_is_dropped_rather_than_scored_zero(): void
    {
        // Nothing at all from this supplier in the period.
        $card = $this->score($this->rival);

        $this->assertTrue($card->isEmpty());
        $this->assertNull($card->overall_score);
        $this->assertNull($card->on_time_percent);
        $this->assertNull($card->price_index);
        $this->assertSame([], $card->components_json);

        // And the supplier's own rating is left alone rather than set to
        // zero — a mill nobody ordered from has not earned a bad name.
        $this->assertNull($this->rival->fresh()->rating);
    }

    public function test_price_is_only_scored_against_another_supplier(): void
    {
        $this->receive($this->mill, '100', '200');

        // Only one supplier of this item so far: nothing to compare to.
        $alone = $this->score($this->mill);
        $this->assertNull($alone->price_index);
        $this->assertNotContains('price', $alone->components_json);

        // A second supplier sells the same cloth cheaper.
        $this->receive($this->rival, '100', '160');

        $compared = $this->score($this->mill);

        // 200 against a market of 160 is 1.25 — a quarter over.
        $this->assertSame('1.2500', $compared->price_index);
        $this->assertContains('price', $compared->components_json);
        // Two points per percent over market: 25% dear costs 50 points.
        $this->assertSame('50.00', $compared->price_score);

        // And the cheaper mill scores full marks on price.
        $cheaper = $this->score($this->rival);
        $this->assertSame('0.8000', $cheaper->price_index);
        $this->assertSame('100.00', $cheaper->price_score);
    }

    public function test_returned_goods_count_against_quality_by_value(): void
    {
        $receipt = $this->receive($this->mill, '100', '200');
        $token = $this->tokenFor($this->storeManager);

        $returned = $this->withToken($token)
            ->postJson("/api/v1/goods-receipts/{$receipt->id}/returns", [
                'reason' => 'QUALITY_FAIL',
                'lines' => [['goods_receipt_line_id' => $receipt->lines()->firstOrFail()->id, 'quantity' => '10']],
            ])->assertCreated()->json('data');

        $this->withToken($token)->postJson("/api/v1/purchase-returns/{$returned['id']}/submit")->assertOk();
        $this->withToken($token)->postJson("/api/v1/purchase-returns/{$returned['id']}/dispatch")->assertOk();

        $card = $this->score($this->mill);

        // 10 of 100 units at 200: 2,000 of 20,000 sent back.
        $this->assertSame('20000.0000', (string) $card->received_value);
        $this->assertSame('2000.0000', (string) $card->rejected_value);
        $this->assertSame('10.00', $card->quality_reject_percent);
        $this->assertContains('quality', $card->components_json);
    }

    public function test_the_overall_score_is_renormalised_over_what_counted(): void
    {
        // One receipt, on time, nothing returned, no claims, no rival.
        $this->receive($this->mill, '100', '200', promised: CarbonImmutable::today()->toDateString());

        $card = $this->score($this->mill);

        // On-time, quality and claims had data; price did not.
        $this->assertEqualsCanonicalizing(['on_time', 'quality', 'claims'], $card->components_json);
        // All three are perfect, so the renormalised mean is 100 — not 80,
        // which is what scoring the missing price component as zero would
        // have produced.
        $this->assertSame('100.00', $card->overall_score);

        // The supplier master carries it on its own five-point scale.
        $this->assertSame('5.00', $card->asRating());
        $this->assertSame('5.00', $this->mill->fresh()->rating);
    }

    public function test_the_api_scores_on_demand_and_gates_who_may(): void
    {
        $this->receive($this->mill, '100', '200', promised: CarbonImmutable::today()->toDateString());

        $manager = $this->tokenFor($this->storeManager);
        $storekeeper = $this->tokenFor($this->storekeeper);

        // Re-scoring moves a rating, so it is not the storekeeper's to run.
        $this->withToken($storekeeper)->postJson('/api/v1/supplier-scorecards/calculate')->assertForbidden();

        $this->withToken($manager)->postJson('/api/v1/supplier-scorecards/calculate')
            ->assertOk()->assertJsonPath('data.scored', 1);

        // Reading one is fine for either.
        $this->withToken($storekeeper)->getJson("/api/v1/vendors/{$this->mill->id}/scorecard")
            ->assertOk()
            ->assertJsonPath('data.scorecard.overall_score', '100.00')
            ->assertJsonPath('data.can_calculate', false);

        // The ranking list leaves out suppliers with nothing behind them.
        $this->withToken($manager)->getJson('/api/v1/supplier-scorecards')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.vendor.code', 'MTM');
    }
}
