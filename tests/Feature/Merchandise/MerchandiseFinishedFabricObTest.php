<?php

declare(strict_types=1);

namespace Tests\Feature\Merchandise;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Merchandise\Models\FabricType;
use App\Modules\Merchandise\Models\FiberComposition;
use App\Modules\Tenancy\Models\Company;
use App\Modules\TradeExport\Models\TxExchangeRate;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Trims & Accessories → Finished Fabric Opening Balance (AnnotechERP 06/06):
 * no workflow — a receiving store and OB date, the USD rate kept with the
 * document, one roll batch per line naming its description, item, type and
 * group; each line costed into BDT; numbered FFOB-2026100001; corrected and
 * removed freely.
 */
class MerchandiseFinishedFabricObTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $warehouse = Warehouse::query()->create(['company_id' => $this->delta->id, 'factory_id' => $factory->id, 'name' => 'Main Warehouse', 'code' => 'WH-1', 'active' => true]);
        $this->store = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => 'Finished Fabric Store', 'code' => 'ST-FAB', 'active' => true])->id;
    }

    public function test_a_finished_fabric_opening_balance_records_roll_batches_at_their_bdt_cost(): void
    {
        TxExchangeRate::query()->create(['company_id' => $this->delta->id, 'currency' => 'USD', 'rate' => 122.5, 'effective_date' => '2026-09-01']);
        $fleece = FabricType::query()->create(['code' => 'FLC', 'name' => 'Fleece', 'short_name' => 'FLC', 'category' => 'KNIT', 'construction' => 'CIRCULAR', 'active' => true])->id;
        $cotton = FiberComposition::query()->create(['code' => 'C100', 'description' => '100% Cotton', 'active' => true])->id;

        $options = $this->as()->getJson('/api/v1/merchandise/ffab-obs/options')->assertOk()->json('data');
        $this->assertSame([122.5, ['BODY', 'COLLAR', 'LINING', 'RIB'], ['KG', 'LBS', 'YDS', 'MTR']], [$options['usd_bdt'], $options['groups'], $options['uoms']]);

        $this->as()->postJson('/api/v1/merchandise/ffab-obs', ['lines' => []])->assertUnprocessable()
            ->assertJsonValidationErrors(['store_id' => 'Receiving Store is required.', 'ob_date' => 'OB Date is required.']);
        $head = ['store_id' => $this->store, 'ob_date' => '2026-10-01', 'currency' => 'usd', 'conv_rate' => 5];
        $this->as()->postJson('/api/v1/merchandise/ffab-obs', [...$head, 'lines' => []])->assertUnprocessable()
            ->assertJsonValidationErrors(['lines' => 'A opening balance with no lines is not a opening balance.']);
        // The first field each row still lacks, in Brickland's order; a batch only once.
        $this->as()->postJson('/api/v1/merchandise/ffab-obs', [...$head, 'lines' => [
            ['qty' => 5, 'uom' => 'KG'],
            ['description' => 'Navy fleece', 'fabric_item' => 'Fleece 280', 'qty' => 5, 'uom' => 'KG'],
            ['description' => 'Navy fleece', 'fabric_item' => 'Fleece 280', 'fabric_type_id' => $fleece, 'fabric_group' => 'BODY', 'uom' => 'KG', 'qty' => 0],
            ['description' => 'Navy fleece', 'fabric_item' => 'Fleece 280', 'fabric_type_id' => $fleece, 'fabric_group' => 'BODY', 'uom' => 'KG', 'qty' => 10, 'batch_no' => 'B-7'],
            ['description' => 'Navy fleece', 'fabric_item' => 'Fleece 280', 'fabric_type_id' => $fleece, 'fabric_group' => 'BODY', 'uom' => 'KG', 'qty' => 10, 'batch_no' => 'b-7'],
        ]])->assertUnprocessable()->assertJsonValidationErrors([
            'lines.0.description' => 'Row 1: Fab Description is needed.', 'lines.1.fabric_type_id' => 'Row 2: Fab Type is needed.', 'lines.2.qty' => 'Row 3: OB QTY must be above zero.',
            'lines.4.batch_no' => 'Row 5: batch b-7 is already on row 4.',
        ]);

        $lines = [
            ['category' => 'Finish Fabric', 'description' => 'Navy fleece 280', 'fabric_item' => 'Fleece 280 GSM', 'colour' => 'Navy', 'fabric_type_id' => $fleece, 'fabric_group' => 'BODY', 'composition_id' => $cotton,
                'gsm' => 280, 'dia' => 72, 'dia_uom' => 'INCH', 'dia_type' => 'OPEN', 'batch_no' => 'FB-101', 'rolls' => 12, 'uom' => 'KG', 'qty' => 420.5, 'rate' => 4.2],
            ['description' => 'Navy rib', 'fabric_item' => '1x1 Rib', 'fabric_type_id' => $fleece, 'fabric_group' => 'RIB', 'batch_no' => 'FB-102', 'rolls' => 2, 'uom' => 'KG', 'qty' => 35, 'rate' => 5],
        ];
        $saved = $this->as()->postJson('/api/v1/merchandise/ffab-obs', [...$head, 'lines' => $lines])->assertCreated()->json('data');
        // USD: conversion forced to 1, the rate on the OB date kept; 420.5 × 4.2 × 122.5 = 216,347.25; 35 × 5 × 122.5 = 21,437.50.
        $this->assertEquals(['FFOB-2026100001', 'USD', 1, 122.5], [$saved['ob_no'], $saved['currency'], $saved['conv_rate'], $saved['usd_bdt']]);
        $this->assertEquals([['Navy fleece 280', 'FB-101', 12, 420.5, 216347.25, 'Navy', 280], ['Navy rib', 'FB-102', 2, 35, 21437.5, null, null]], array_map(fn ($l) => [$l['description'], $l['batch_no'], $l['rolls'], $l['qty'], $l['cost_bdt'], $l['colour'], $l['gsm']], $saved['lines']));
        $this->assertSame([['FFOB-2026100001', '01 Oct 2026 · Finished Fabric Store · FFOB-2026100001']], array_map(fn ($b) => [$b['value'], $b['label']], $this->as()->getJson('/api/v1/merchandise/ffab-obs/options')->json('data.balances')));

        // Corrected freely: BDT needs no USD rate.
        $updated = $this->as()->putJson('/api/v1/merchandise/ffab-obs/FFOB-2026100001', [...$head, 'currency' => 'BDT', 'lines' => [[...$lines[0], 'rate' => 500]]])->assertOk()->json('data');
        $this->assertEquals(['BDT', null, 210250], [$updated['currency'], $updated['usd_bdt'], $updated['lines'][0]['cost_bdt']]);

        $records = $this->as()->getJson('/api/v1/merchandise/ffab-obs')->assertOk()->json('data');
        $this->assertEquals([['FFOB-2026100001', '2026-10-01', 'Delta Apparels Ltd', 'Finished Fabric Store', 'BDT', 1, 420.5, 210250]], array_map(fn ($r) => [$r['ob_no'], $r['ob_date'], $r['company'], $r['store'], $r['currency'], $r['items'], $r['total_qty'], $r['total_cost']], $records));

        $storeManager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'store@delta.test');
        $this->as($storeManager)->getJson('/api/v1/merchandise/ffab-obs')->assertOk();
        $this->as($storeManager)->deleteJson('/api/v1/merchandise/ffab-obs/FFOB-2026100001')->assertForbidden();
        $this->as()->deleteJson('/api/v1/merchandise/ffab-obs/FFOB-2026100001')->assertOk();
        $this->assertSame([], $this->as()->getJson('/api/v1/merchandise/ffab-obs')->json('data'));
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
