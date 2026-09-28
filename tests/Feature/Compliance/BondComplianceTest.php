<?php

declare(strict_types=1);

namespace Tests\Feature\Compliance;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Compliance\Models\BondLicence;
use App\Modules\Compliance\Models\DutyAssessment;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\Store;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Services\ItemLedger;
use App\Modules\Procurement\Models\HsCode;
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
 * Bonded warehouse compliance (Shared Systems spec §3).
 *
 * Three things here would cost a factory real money if they were wrong,
 * and each is pinned: a declaration issued against a licence that is not
 * in force, a bond entitlement that drifts from what actually arrived,
 * and duty charged on goods that never left the bond — or not charged on
 * goods that did.
 */
class BondComplianceTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Bin $bin;

    private Store $bonded;

    private User $storeManager;

    private User $storekeeper;

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

        // The ordinary store the fixture made, plus a bonded one.
        $this->bonded = Store::create([
            'company_id' => $this->delta->id,
            'warehouse_id' => Store::findOrFail($this->bin->store_id)->warehouse_id,
            'name' => 'Bonded store',
            'code' => 'DHK-BND',
            'store_type' => 'BONDED',
            'active' => true,
        ]);

        $hsCode = HsCode::create([
            'company_id' => $this->delta->id,
            'code' => '5209.42',
            'description' => 'Woven denim',
            'duty_rate' => '25',
            'vat_rate' => '15',
            'active' => true,
        ]);

        $this->fabric = ItemFixture::item($this->delta, 'FAB-DNM-12', 'Denim 12oz', [
            'hs_code_id' => $hsCode->id,
        ]);
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
    private function licence(array $overrides = [], array $entitlements = []): array
    {
        return $this->withToken($this->tokenFor($this->storeManager))
            ->postJson('/api/v1/bond-licences', array_merge([
                'factory_id' => $this->dhaka->id,
                'licence_no' => 'BOND/DHK/2026/17',
                'issuing_office' => 'Dhaka Customs Bond Commissionerate',
                'valid_from' => CarbonImmutable::today()->subMonth()->toDateString(),
                'valid_to' => CarbonImmutable::today()->addYear()->toDateString(),
                'entitlements' => $entitlements,
            ], $overrides))->assertCreated()->json('data');
    }

    public function test_a_licence_knows_whether_it_applies_today(): void
    {
        $licence = $this->licence();

        $this->assertSame('ACTIVE', $licence['status']);
        $this->assertTrue($licence['is_live']);
        $this->assertFalse($licence['has_expired']);

        // A lapsed licence keeps its status and stops applying, which the
        // status alone cannot say.
        $lapsed = $this->licence([
            'licence_no' => 'BOND/DHK/2024/03',
            'valid_from' => CarbonImmutable::today()->subYears(2)->toDateString(),
            'valid_to' => CarbonImmutable::today()->subDay()->toDateString(),
        ]);

        $this->assertSame('ACTIVE', $lapsed['status']);
        $this->assertFalse($lapsed['is_live']);
        $this->assertTrue($lapsed['has_expired']);
    }

    public function test_a_declaration_cannot_be_issued_against_a_licence_that_is_not_in_force(): void
    {
        $token = $this->tokenFor($this->storeManager);

        $lapsed = $this->licence([
            'licence_no' => 'BOND/DHK/2024/03',
            'valid_from' => CarbonImmutable::today()->subYears(2)->toDateString(),
            'valid_to' => CarbonImmutable::today()->subDay()->toDateString(),
        ]);

        $declaration = $this->withToken($token)
            ->postJson("/api/v1/bond-licences/{$lapsed['id']}/declarations", [
                'ud_no' => 'UD/2026/881',
                'lines' => [['item_id' => $this->fabric->id, 'declared_quantity' => '5000', 'wastage_percent' => '3']],
            ])->assertCreated()->json('data');

        // Drafting against it is fine; issuing is not — the paperwork
        // would look valid here and be worthless at the port.
        $this->withToken($token)->postJson("/api/v1/utilisation-declarations/{$declaration['id']}/issue")
            ->assertStatus(409)->assertJsonValidationErrors(['bond_licence_id']);

        // A suspended licence is refused for the same reason.
        $live = $this->licence(['licence_no' => 'BOND/DHK/2026/17']);

        $this->withToken($token)->postJson("/api/v1/bond-licences/{$live['id']}/status", [
            'status' => 'SUSPENDED', 'reason' => 'Audit under way.',
        ])->assertOk()->assertJsonPath('data.status', 'SUSPENDED');

        $second = $this->withToken($token)
            ->postJson("/api/v1/bond-licences/{$live['id']}/declarations", [
                'ud_no' => 'UD/2026/882',
                'lines' => [['item_id' => $this->fabric->id, 'declared_quantity' => '100']],
            ])->assertCreated()->json('data');

        $this->withToken($token)->postJson("/api/v1/utilisation-declarations/{$second['id']}/issue")
            ->assertStatus(409);

        // Restored, and it goes through.
        $this->withToken($token)->postJson("/api/v1/bond-licences/{$live['id']}/status", ['status' => 'ACTIVE'])
            ->assertOk();

        $this->withToken($token)->postJson("/api/v1/utilisation-declarations/{$second['id']}/issue")
            ->assertOk()->assertJsonPath('data.status', 'ISSUED')
            ->assertJsonPath('data.is_live', true);
    }

    public function test_entitlement_is_measured_against_what_actually_arrived(): void
    {
        $token = $this->tokenFor($this->storeManager);

        $mill = Vendor::create([
            'company_id' => $this->delta->id, 'name' => 'Ningbo Textile Co', 'code' => 'NBT',
            'vendor_type' => 'SUPPLIER', 'status' => 'ACTIVE', 'default_currency' => 'USD',
        ]);

        $licence = $this->licence([], [
            ['item_id' => $this->fabric->id, 'entitled_quantity' => '10000', 'basis' => 'ANNUAL'],
        ]);

        // Nothing imported yet.
        $before = $this->withToken($token)->getJson("/api/v1/bond-licences/{$licence['id']}/utilisation")
            ->assertOk()->json('data.rows');

        $this->assertSame('0.0000', $before[0]['imported_quantity']);
        $this->assertSame('10000.0000', $before[0]['remaining_quantity']);
        $this->assertFalse($before[0]['exceeded']);

        // An import order flagged against this bond, received.
        $order = $this->withToken($token)->postJson('/api/v1/purchase-orders', [
            'factory_id' => $this->dhaka->id,
            'vendor_id' => $mill->id,
            'purchase_type' => 'IMPORT',
            'bond_applicable' => true,
            'bond_licence_id' => $licence['id'],
            'lines' => [['item_id' => $this->fabric->id, 'ordered_qty' => '4000', 'rate' => '3']],
        ])->assertCreated()->json('data');

        $this->withToken($token)->postJson("/api/v1/purchase-orders/{$order['id']}/submit")->assertOk();

        $grn = $this->withToken($token)->postJson('/api/v1/goods-receipts', [
            'factory_id' => $this->dhaka->id,
            'store_id' => $this->bin->store_id,
            'vendor_id' => $mill->id,
            'po_id' => $order['id'],
            'lines' => [['po_line_id' => $order['lines'][0]['id'], 'received_qty' => '4000', 'rate' => '3', 'bin_id' => $this->bin->id]],
        ])->assertCreated()->json('data');

        $this->withToken($token)->postJson("/api/v1/goods-receipts/{$grn['id']}/complete")->assertOk();

        $after = $this->withToken($token)->getJson("/api/v1/bond-licences/{$licence['id']}/utilisation")
            ->assertOk()->json('data.rows');

        // Counted from the receipt, not from a running total somebody
        // remembered to keep.
        $this->assertSame('4000.0000', $after[0]['imported_quantity']);
        $this->assertSame('6000.0000', $after[0]['remaining_quantity']);
        $this->assertSame('40.00', $after[0]['used_percent']);
        $this->assertFalse($after[0]['exceeded']);
    }

    public function test_duty_is_charged_on_what_leaves_the_bond_and_refused_on_what_was_never_in_it(): void
    {
        $manager = $this->tokenFor($this->storeManager);
        $licence = $this->licence();

        // Stock on the bonded shelf at a known cost.
        $bondedBin = Bin::create([
            'company_id' => $this->delta->id,
            'store_id' => $this->bonded->id,
            'name' => 'BND-A1',
            'code' => 'BND-A1',
            'active' => true,
        ]);

        app(ItemLedger::class)->post($this->fabric, $bondedBin, 'RECEIPT', '1000', '200', [
            'reference_type' => 'test', 'reference_id' => $this->fabric->id,
        ]);

        $assessment = $this->withToken($manager)->postJson('/api/v1/duty-assessments', [
            'factory_id' => $this->dhaka->id,
            'item_id' => $this->fabric->id,
            'store_id' => $this->bonded->id,
            'bond_licence_id' => $licence['id'],
            'reason' => 'LOCAL_SALE',
            'quantity' => '100',
        ])->assertCreated()->json('data');

        $this->assertStringStartsWith('DA-DHK-', $assessment['assessment_no']);
        // 100 at 200 = 20,000 assessable.
        $this->assertSame('20000.0000', $assessment['assessable_value']);
        $this->assertSame('5209.42', $assessment['hs_code']);
        // 25% duty = 5,000.
        $this->assertSame('5000.0000', $assessment['duty_amount']);
        // VAT sits on the duty-paid value: 15% of 25,000 = 3,750.
        $this->assertSame('3750.0000', $assessment['vat_amount']);
        $this->assertSame('8750.0000', $assessment['total_payable']);
        $this->assertTrue($assessment['is_rated']);

        // An ordinary store was never in bond, so nothing leaving it owes
        // duty — charging it would invent a bill.
        $this->withToken($manager)->postJson('/api/v1/duty-assessments', [
            'factory_id' => $this->dhaka->id,
            'item_id' => $this->fabric->id,
            'store_id' => $this->bin->store_id,
            'reason' => 'LOCAL_SALE',
            'quantity' => '10',
        ])->assertStatus(422)->assertJsonValidationErrors(['store_id']);

        // Assessing moves money, so it is not the storekeeper's to do.
        $this->withToken($this->tokenFor($this->storekeeper))->postJson('/api/v1/duty-assessments', [
            'factory_id' => $this->dhaka->id,
            'item_id' => $this->fabric->id,
            'reason' => 'SHORTAGE',
            'quantity' => '1',
        ])->assertForbidden();

        $this->assertSame(1, DutyAssessment::count());
    }

    public function test_an_item_with_no_tariff_heading_is_still_recorded_at_zero(): void
    {
        $manager = $this->tokenFor($this->storeManager);
        $this->licence();

        $unrated = ItemFixture::item($this->delta, 'TRM-BTN-20L', 'Button 20L');

        $assessment = $this->withToken($manager)->postJson('/api/v1/duty-assessments', [
            'factory_id' => $this->dhaka->id,
            'item_id' => $unrated->id,
            'reason' => 'SHORTAGE',
            'quantity' => '50',
            'unit_value' => '4',
        ])->assertCreated()->json('data');

        // Written rather than dropped: a released consignment nobody can
        // rate is a question for customs, not a row to lose.
        $this->assertFalse($assessment['is_rated']);
        $this->assertSame('200.0000', $assessment['assessable_value']);
        $this->assertSame('0.0000', $assessment['total_payable']);
        $this->assertNull($assessment['hs_code']);
    }

    public function test_reconciliation_reports_under_and_over_consumption_differently(): void
    {
        $token = $this->tokenFor($this->storeManager);
        $licence = $this->licence();

        $declaration = $this->withToken($token)
            ->postJson("/api/v1/bond-licences/{$licence['id']}/declarations", [
                'ud_no' => 'UD/2026/900',
                'issue_date' => CarbonImmutable::today()->subDays(10)->toDateString(),
                'lines' => [['item_id' => $this->fabric->id, 'declared_quantity' => '1000', 'wastage_percent' => '5']],
            ])->assertCreated()->json('data');

        // Declared 1,000 with 5% wastage allowed on top.
        $this->assertSame('1050.0000', $declaration['lines'][0]['allowed_quantity']);

        $this->withToken($token)->postJson("/api/v1/utilisation-declarations/{$declaration['id']}/issue")->assertOk();

        // Nothing drawn yet: under-consumed by the whole declaration.
        $under = $this->withToken($token)->getJson("/api/v1/utilisation-declarations/{$declaration['id']}")
            ->assertOk()->json('data.reconciliation');

        $this->assertSame('0.0000', $under[0]['consumed_quantity']);
        $this->assertSame('-1000.0000', $under[0]['variance_quantity']);
        $this->assertFalse($under[0]['exceeds_allowance']);

        // Draw more than the allowance.
        app(ItemLedger::class)->post($this->fabric, $this->bin, 'RECEIPT', '2000', '200', [
            'reference_type' => 'test', 'reference_id' => $this->fabric->id,
        ]);
        app(ItemLedger::class)->post($this->fabric, $this->bin, 'ISSUE', '1200', null, [
            'reference_type' => 'test', 'reference_id' => $this->fabric->id,
        ]);

        $over = $this->withToken($token)->getJson("/api/v1/utilisation-declarations/{$declaration['id']}")
            ->assertOk()->json('data.reconciliation');

        $this->assertSame('1200.0000', $over[0]['consumed_quantity']);
        // Signed, because under and over are different problems.
        $this->assertSame('200.0000', $over[0]['variance_quantity']);
        $this->assertSame('20.00', $over[0]['variance_percent']);
        $this->assertTrue($over[0]['exceeds_allowance']);
    }

    public function test_reading_is_the_stores_and_writing_is_the_compliance_desks(): void
    {
        $licence = $this->licence();
        $storekeeper = $this->tokenFor($this->storekeeper);

        // A storekeeper needs to know which stock is bonded.
        $this->withToken($storekeeper)->getJson('/api/v1/bond-licences')->assertOk();
        $this->withToken($storekeeper)->getJson("/api/v1/bond-licences/{$licence['id']}")->assertOk();

        // The licence itself is not theirs to write.
        $this->withToken($storekeeper)->postJson('/api/v1/bond-licences', [
            'factory_id' => $this->dhaka->id,
            'licence_no' => 'BOND/X',
            'issuing_office' => 'Nowhere',
            'valid_from' => CarbonImmutable::today()->toDateString(),
            'valid_to' => CarbonImmutable::today()->addYear()->toDateString(),
        ])->assertForbidden();

        // A different company's licence is not there at all.
        $other = TenantFixture::company('Orion Garments Ltd', 'OGL');
        $otherManager = TenantFixture::user($other, 'STORE_MANAGER', 'sm@orion.test');
        TenantFixture::actingAsTenant($this->delta);

        $this->withToken($this->tokenFor($otherManager))
            ->getJson("/api/v1/bond-licences/{$licence['id']}")->assertNotFound();

        // That request left the tenant context as Orion's, so it goes back
        // before anything is counted.
        TenantFixture::actingAsTenant($this->delta);

        $this->assertSame(1, BondLicence::count());
    }
}
