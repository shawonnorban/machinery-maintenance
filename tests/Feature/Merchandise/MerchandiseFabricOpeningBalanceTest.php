<?php

declare(strict_types=1);

namespace Tests\Feature\Merchandise;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Merchandise\Models\FiberComposition;
use App\Modules\Merchandise\Models\PurchasedFabricLedger;
use App\Modules\Tenancy\Models\Company;
use App\Modules\TradeExport\Models\TxExchangeRate;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Trims & Accessories → Purchased Fabric Opening Balance (AnnotechERP 06/06):
 * one fabric batch per row, its cost carried into BDT (as is, × the USD rate,
 * or × the conversion and the USD rate); a batch once per balance and once
 * per store; Draft → Submitted → Approved → Posted → Locked with every step
 * kept, posting and unposting the purchased-fabric stock ledger; only a draft
 * edited, deleted or attached to.
 */
class MerchandiseFabricOpeningBalanceTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private mixed $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
    }

    public function test_an_opening_balance_is_costed_in_taka_and_posted_through_its_workflow(): void
    {
        $warehouse = Warehouse::query()->create(['company_id' => $this->delta->id, 'factory_id' => $this->factory->id, 'name' => 'Main Warehouse', 'code' => 'WH-1', 'active' => true]);
        $fab = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => 'Finished Fabric Store', 'code' => 'ST-FAB', 'active' => true])->id;
        $grey = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => 'Grey Fabric Store', 'code' => 'ST-GRY', 'active' => true])->id;
        TxExchangeRate::query()->create(['company_id' => $this->delta->id, 'currency' => 'USD', 'rate' => 118, 'effective_date' => '2026-09-01']);
        TxExchangeRate::query()->create(['company_id' => $this->delta->id, 'currency' => 'USD', 'rate' => 119.5, 'effective_date' => '2026-10-01']);
        $cotton = FiberComposition::query()->create(['code' => 'C100', 'description' => '100% Cotton', 'active' => true])->id;

        $options = $this->as()->getJson('/api/v1/merchandise/fabric-obs/options')->assertOk()->json('data');
        $this->assertEquals([119.5, ['PHYSICAL_COUNT', 'LEGACY_MIGRATION', 'STOCK_TAKE', 'OTHER'], ['PURCHASE', 'WOVEN', 'KNIT', 'DENIM', 'LINING']], [$options['usd_bdt'], $options['sources'], $options['categories']]);
        $this->assertSame(['100% Cotton'], array_column($options['compositions'], 'label'));

        $this->as()->postJson('/api/v1/merchandise/fabric-obs', ['lines' => []])->assertUnprocessable()->assertJsonValidationErrors([
            'unit_company_id' => 'Company is required.', 'store_id' => 'Receiving Store is required.', 'ob_date' => 'OB Date is required.', 'currency' => 'Currency is required.', 'source' => 'Source is required.',
        ]);
        $head = ['unit_company_id' => $this->delta->id, 'store_id' => $fab, 'ob_date' => '2026-10-01', 'currency' => 'USD', 'usd_bdt' => 119.5, 'source' => 'PHYSICAL_COUNT', 'reference_no' => ' CNT-0926 '];
        $sj = ['category' => 'KNIT', 'description' => 'Single Jersey 160', 'fabric_item' => 'SJ-160-WHT', 'fabric_group' => 'BODY', 'composition_id' => $cotton, 'gsm' => 160, 'dia' => 72, 'dia_uom' => 'INCH', 'dia_type' => 'OPEN', 'batch_no' => 'B-1', 'rolls' => 2, 'roll_nos' => 'R-01, R-02', 'uom' => 'KG', 'qty' => 40, 'rate' => 3.5];
        $this->as()->postJson('/api/v1/merchandise/fabric-obs', [...$head, 'usd_bdt' => null, 'lines' => []])->assertUnprocessable()
            ->assertJsonValidationErrors(['usd_bdt' => '1 USD = BDT is required unless the currency is BDT.', 'lines' => 'An opening balance with no fabric rows is not a balance.']);
        $this->as()->postJson('/api/v1/merchandise/fabric-obs', [...$head, 'currency' => 'EUR', 'conv_rate' => null, 'lines' => [$sj]])->assertUnprocessable()
            ->assertJsonValidationErrors(['conv_rate' => 'Conversion Rate is required for this currency.']);
        $this->as()->postJson('/api/v1/merchandise/fabric-obs', [...$head, 'lines' => [[...$sj, 'fabric_item' => '', 'fabric_group' => null, 'qty' => 0], $sj, [...$sj, 'batch_no' => 'b-1']]])->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.fabric_item' => 'Row 1: Fabric Item is required.', 'lines.0.fabric_group' => 'Row 1: Fabric Group is required.', 'lines.0.qty' => 'Row 1: OB Qty must be above zero.', 'lines.2.batch_no' => 'Row 3: batch B-1 is already on this opening balance.']);

        // USD: 40 × 3.5 = 140 USD × 119.5 = 16,730 BDT; 10 × 2 = 20 USD = 2,390 BDT.
        $saved = $this->as()->postJson('/api/v1/merchandise/fabric-obs', [...$head, 'conv_rate' => 9, 'lines' => [$sj, [...$sj, 'batch_no' => 'B-2', 'fabric_group' => 'RIB', 'qty' => 10, 'rate' => 2, 'rolls' => null]]])->assertCreated()->json('data');
        $this->assertEquals(['PFOB-2026100001', 'DRAFT', 1, 119.5, 'CNT-0926', 50, 19120], [$saved['ob_no'], $saved['status'], $saved['conv_rate'], $saved['usd_bdt'], $saved['reference_no'], $saved['total_qty'], $saved['total_cost']]);
        $this->assertEquals([[140, 16730, 160, 'R-01, R-02'], [20, 2390, 160, 'R-01, R-02']], array_map(fn ($l) => [$l['amount'], $l['cost_bdt'], $l['gsm'], $l['roll_nos']], $saved['lines']));
        $no = $saved['ob_no'];

        // A batch once per store across balances; another store may hold it. EUR: 10 × 1 × 1.08 × 119.5 = 1,290.60; BDT as is.
        $this->as()->postJson('/api/v1/merchandise/fabric-obs', [...$head, 'lines' => [$sj]])->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.batch_no' => "Row 1: batch B-1 is already in this store on {$no}."]);
        $eur = $this->as()->postJson('/api/v1/merchandise/fabric-obs', [...$head, 'store_id' => $grey, 'currency' => 'EUR', 'conv_rate' => 1.08, 'lines' => [[...$sj, 'qty' => 10, 'rate' => 1]]])->assertCreated()->json('data');
        $this->assertEquals([1.08, 1290.6], [$eur['conv_rate'], $eur['total_cost']]);
        $bdt = $this->as()->postJson('/api/v1/merchandise/fabric-obs', [...$head, 'store_id' => $grey, 'currency' => 'BDT', 'source' => 'LEGACY_MIGRATION', 'lines' => [[...$sj, 'batch_no' => 'L-9', 'qty' => 5, 'rate' => 400]]])->assertCreated()->json('data');
        $this->assertEquals([null, 1, 2000], [$bdt['usd_bdt'], $bdt['conv_rate'], $bdt['total_cost']]);

        // A file is attached to a draft.
        Storage::fake('local');
        $this->as()->post("/api/v1/merchandise/fabric-obs/{$no}/attachment", ['file' => UploadedFile::fake()->create('count-sheet.pdf', 120, 'application/pdf')], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.attachment', 'count-sheet.pdf');

        // The workflow, each step only from the one before.
        $this->as()->postJson("/api/v1/merchandise/fabric-obs/{$no}/approve")->assertUnprocessable()->assertJsonValidationErrors(['status' => 'Approve is not open to a Draft opening balance.']);
        $this->as()->postJson("/api/v1/merchandise/fabric-obs/{$no}/submit")->assertOk()->assertJsonPath('data.status', 'SUBMITTED');
        $this->as()->putJson("/api/v1/merchandise/fabric-obs/{$no}", [...$head, 'lines' => [$sj]])->assertUnprocessable()
            ->assertJsonValidationErrors(['status' => 'This opening balance is Submitted — only a draft can be edited or deleted.']);
        $this->as()->postJson("/api/v1/merchandise/fabric-obs/{$no}/reject", ['remarks' => 'Recount rib'])->assertOk()->assertJsonPath('data.status', 'DRAFT');
        $this->as()->postJson("/api/v1/merchandise/fabric-obs/{$no}/submit")->assertOk();
        $this->as()->postJson("/api/v1/merchandise/fabric-obs/{$no}/approve")->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $this->assertSame(0, PurchasedFabricLedger::query()->count());

        // Posting puts every batch on the stock ledger in its store at its BDT cost; unposting takes them off.
        $this->as()->postJson("/api/v1/merchandise/fabric-obs/{$no}/post")->assertOk()->assertJsonPath('data.posted', 2);
        $this->assertEquals([['B-1', $fab, 40, 16730], ['B-2', $fab, 10, 2390]], PurchasedFabricLedger::query()->orderBy('batch_no')->get()->map(fn ($l) => [$l->batch_no, $l->store_id, $l->qty, $l->cost_bdt])->all());
        $this->as()->postJson("/api/v1/merchandise/fabric-obs/{$no}/unpost")->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $this->assertSame(0, PurchasedFabricLedger::query()->count());
        $this->as()->postJson("/api/v1/merchandise/fabric-obs/{$no}/post")->assertOk();
        $this->as()->postJson("/api/v1/merchandise/fabric-obs/{$no}/lock")->assertOk()->assertJsonPath('data.status', 'LOCKED');
        $this->as()->postJson("/api/v1/merchandise/fabric-obs/{$no}/unpost")->assertUnprocessable();
        $this->as()->deleteJson("/api/v1/merchandise/fabric-obs/{$no}")->assertUnprocessable();
        $this->assertSame(2, PurchasedFabricLedger::query()->count());

        $history = $this->as()->getJson("/api/v1/merchandise/fabric-obs/{$no}/history")->assertOk()->json('data.steps');
        $this->assertSame([['CREATE', null, 'DRAFT'], ['SUBMIT', 'DRAFT', 'SUBMITTED'], ['REJECT', 'SUBMITTED', 'DRAFT'], ['SUBMIT', 'DRAFT', 'SUBMITTED'], ['APPROVE', 'SUBMITTED', 'APPROVED'], ['POST', 'APPROVED', 'POSTED'], ['UNPOST', 'POSTED', 'APPROVED'], ['POST', 'APPROVED', 'POSTED'], ['LOCK', 'POSTED', 'LOCKED']],
            array_map(fn ($s) => [$s['action'], $s['from'], $s['to']], $history));
        $this->assertSame(['Recount rib', $this->owner->name], [$history[2]['remarks'], $history[2]['by']]);

        $records = $this->as()->getJson('/api/v1/merchandise/fabric-obs')->assertOk()->json('data');
        $this->assertEquals([$no, 'Finished Fabric Store', 'PHYSICAL_COUNT', 'USD', 2, 2, 50, 19120, 'LOCKED'], array_values(array_intersect_key(collect($records)->firstWhere('ob_no', $no), array_flip(['ob_no', 'store', 'source', 'currency', 'items', 'rolls', 'total_qty', 'total_cost', 'status']))));
        $this->as()->deleteJson("/api/v1/merchandise/fabric-obs/{$bdt['ob_no']}")->assertOk();

        $storeManager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'store@delta.test');
        $this->as($storeManager)->getJson('/api/v1/merchandise/fabric-obs')->assertOk();
        $this->as($storeManager)->postJson('/api/v1/merchandise/fabric-obs', [...$head, 'lines' => [$sj]])->assertForbidden();
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
