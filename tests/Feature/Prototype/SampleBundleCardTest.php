<?php

declare(strict_types=1);

namespace Tests\Feature\Prototype;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Prototype\Models\Bundle;
use App\Modules\Prototype\Models\Sample;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Department;
use App\Modules\Tenancy\Models\ProductionLine;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Prototype Desk → Sample Bundle Card Issue (AnnotechERP 07/03): a sample lay
 * cut on a cutting table — a Cutting line of Company Management — with its
 * bundles, the cutting number unique per table for the buyer and the buyer
 * the sample's; every bundle's scan code minted from the card number and its
 * position; Issue finalizes the card, and the bundles move through Receive,
 * Hold, Start / Resume and Return, re-bundled only until they move.
 */
class SampleBundleCardTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    /** @var array<string, string> */
    private array $tables = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $unit = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $cutting = Department::query()->create(['company_id' => $this->delta->id, 'factory_id' => $unit->id, 'name' => 'Cutting', 'code' => 'DPT-CUT']);
        foreach (['C1' => ['Cutting 1', 'CUTTING'], 'C2' => ['Cutting 2', 'CUTTING'], 'LN-001' => ['Line 1', 'SEWING']] as $code => [$name, $type]) {
            $this->tables[$code] = ProductionLine::query()->create(['company_id' => $this->delta->id, 'department_id' => $cutting->id, 'code' => $code, 'name' => $name, 'line_type' => $type, 'status' => 'ACTIVE'])->id;
        }
    }

    public function test_a_card_is_bundled_on_its_table_and_its_bundles_move_through_the_floor(): void
    {
        [$sample, $buyer, $style, $order, $other] = $this->sample();
        $options = $this->as()->getJson('/api/v1/prototype/bundle-cards/options')->assertOk()->json('data');
        // Only Cutting lines are tables; the sample carries its buyer and style.
        $this->assertSame(['C1 — Cutting 1', 'C2 — Cutting 2'], collect($options['tables'])->pluck('label')->all());
        $this->assertSame([$buyer, $style], [$options['samples'][0]['buyer_id'], $options['samples'][0]['style_id']]);

        $head = ['sample_id' => $sample->id, 'buyer_id' => $buyer, 'style_id' => $style, 'order_id' => $order, 'cutting_no' => '7', 'table_id' => $this->tables['C1'], 'cutting_date' => '2026-10-22', 'due_date' => '2026-10-24', 'priority' => 'HIGH', 'operator_id' => $this->owner->id];
        $bundles = [
            ['order_id' => $order, 'color' => 'Navy', 'size' => 'M', 'req_qty' => 2, 'cutting_qty' => 2],
            ['order_id' => $order, 'color' => 'Navy', 'size' => 'L', 'req_qty' => 2, 'cutting_qty' => 3],
        ];
        $this->as()->postJson('/api/v1/prototype/bundle-cards', [])->assertUnprocessable()
            ->assertJsonPath('errors.buyer_id.0', 'Buyer is required.')->assertJsonPath('errors.cutting_no.0', 'Cutting# is required.')->assertJsonPath('errors.table_id.0', 'Pick the cutting Table.');
        $this->as()->postJson('/api/v1/prototype/bundle-cards', array_merge($head, ['table_id' => $this->tables['LN-001'], 'bundles' => $bundles]))->assertUnprocessable()->assertJsonPath('errors.table_id.0', 'Pick the cutting Table.');
        $this->as()->postJson('/api/v1/prototype/bundle-cards', $head)->assertUnprocessable()
            ->assertJsonPath('errors.bundles.0', 'A bundle card with no lines posts nothing, so at least one is required before this can be saved.');
        $this->as()->postJson('/api/v1/prototype/bundle-cards', array_merge($head, ['buyer_id' => $other, 'style_id' => null, 'order_id' => null, 'bundles' => $bundles]))->assertUnprocessable()
            ->assertJsonPath('errors.buyer_id.0', "The buyer must be the sample's.");
        $this->as()->postJson('/api/v1/prototype/bundle-cards', $head + ['bundles' => [['cutting_qty' => 0]]])->assertUnprocessable()
            ->assertJsonPath('errors', ['bundles.0.cutting_qty' => ['Cutting must be at least 1 on every row.']]);

        $card = $this->as()->postJson('/api/v1/prototype/bundle-cards', $head + ['bundles' => $bundles])->assertCreated()->json('data');
        $this->assertSame(['SBC-2026100001', 'DRAFT', 'CREATED', false, 2, 4, 5, 'C1 — Cutting 1', 'SMP-2026-00001', 'LPP'],
            [$card['card_no'], $card['status'], $card['bundle_status'], $card['is_finalized'], $card['bundles_count'], $card['req_qty'], $card['cutting_qty'], $card['table'], $card['sample_no'], $card['buyer']]);
        $this->assertSame(['SBC-2026100001-01', 'SBC-2026100001-02'], array_column($card['bundles'], 'bundle_no'));
        // The cutting number is unique per table for the buyer; another table takes it.
        $this->as()->postJson('/api/v1/prototype/bundle-cards', $head + ['bundles' => $bundles])->assertUnprocessable()
            ->assertJsonPath('errors.cutting_no.0', 'Cutting# 7 is already used on this table for this buyer.');
        $second = $this->as()->postJson('/api/v1/prototype/bundle-cards', array_merge($head, ['table_id' => $this->tables['C2'], 'cutting_date' => '2026-10-23', 'bundles' => $bundles]))->assertCreated()->json('data.card_no');
        $this->assertSame('SBC-2026100002', $second);

        // Re-bundled while nothing has moved: the codes follow the new rows.
        $url = '/api/v1/prototype/bundle-cards/SBC-2026100001';
        $rebundled = $this->as()->putJson($url, $head + ['bundles' => [...$bundles, ['color' => 'Navy', 'size' => 'XL', 'req_qty' => 1, 'cutting_qty' => 1]]])->assertOk()->json('data');
        $this->assertSame(['SBC-2026100001-01', 'SBC-2026100001-02', 'SBC-2026100001-03'], array_column($rebundled['bundles'], 'bundle_no'));

        // Each move only from where the bundles stand; Issue finalizes and dates the card today.
        $this->as()->postJson("{$url}/receive")->assertUnprocessable()->assertJsonPath('errors.bundle_status.0', 'Receive is not open to a card whose bundles are Created.');
        $issued = $this->as()->postJson("{$url}/issue")->assertOk()->json('data');
        $this->assertSame(['ISSUED', true, now()->toDateString()], [$issued['bundle_status'], $issued['is_finalized'], $issued['issue_date']]);
        // Issued but not yet received, it is still re-bundled.
        $this->as()->putJson($url, $head + ['bundles' => $bundles])->assertOk()->assertJsonPath('data.bundles_count', 2);
        $this->as()->postJson("{$url}/receive")->assertOk()->assertJsonPath('data.bundle_status', 'RECEIVED');
        // Received, the bundles have moved: a save keeps them.
        $kept = $this->as()->putJson($url, $head + ['remarks' => 'Shade checked', 'bundles' => [['cutting_qty' => 9]]])->assertOk()->json('data');
        $this->assertSame([2, 'Shade checked', false], [$kept['bundles_count'], $kept['remarks'], $kept['rebundle']]);
        $this->as()->postJson("{$url}/start")->assertOk()->assertJsonPath('data.bundle_status', 'IN_PRODUCTION');
        $this->as()->postJson("{$url}/hold")->assertOk()->assertJsonPath('data.bundle_status', 'HOLD');
        $this->as()->postJson("{$url}/start")->assertOk()->assertJsonPath('data.bundle_status', 'IN_PRODUCTION');
        $this->as()->postJson("{$url}/return")->assertOk()->assertJsonPath('data.bundle_status', 'RETURNED');
        $this->as()->putJson($url, $head)->assertUnprocessable()->assertJsonPath('errors.bundle_status.0', 'A card whose bundles are Returned can no longer be changed.');
        $this->as()->postJson("{$url}/cancel")->assertUnprocessable();

        // The second card is cancelled; its paperwork follows.
        $this->as()->postJson("/api/v1/prototype/bundle-cards/{$second}/cancel")->assertOk()->assertJsonPath('data.bundle_status', 'CANCELLED')->assertJsonPath('data.status', 'CANCELLED');
        $rows = collect($this->as()->getJson('/api/v1/prototype/bundle-cards')->assertOk()->json('data'));
        $this->assertSame(['SBC-2026100002', 'SBC-2026100001'], $rows->pluck('card_no')->all());
        // Two bundles on each card: the first re-bundled back to two before it moved.
        $this->assertSame(4, Bundle::query()->count());

        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $this->as($technician)->getJson('/api/v1/prototype/bundle-cards')->assertForbidden();
        $manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $this->as($manager)->getJson('/api/v1/prototype/bundle-cards')->assertOk();
        $this->as($manager)->postJson("{$url}/issue")->assertForbidden();
    }

    /** @return array{Sample, string, string, string, string} the sample, its buyer, style and order, and another buyer */
    private function sample(): array
    {
        $office = $this->as()->postJson('/api/v1/erp/buyer-offices', ['name' => 'LPP Office', 'office_type' => 'HEAD_OFFICE'])->json('data.id');
        $country = OriginCountry::query()->firstOrCreate(['company_id' => $this->delta->id, 'code' => '616'], ['name' => 'Poland', 'active' => true])->id;
        $buyer = $this->as()->postJson('/api/v1/erp/buyers', ['code' => 'LPP', 'name' => 'LPP Spolka Akcyjna', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'country_id' => $country, 'office_ids' => [$office]])
            ->assertCreated()->json('data.id');
        $other = $this->as()->postJson('/api/v1/erp/buyers', ['code' => 'ROLY', 'name' => 'Roly Ltd', 'short_name' => 'ROLY', 'pi_address' => 'Madrid', 'registered_from' => '2026-01-01', 'country_id' => $country, 'office_ids' => [$office]])
            ->assertCreated()->json('data.id');
        $size = $this->as()->postJson('/api/v1/erp/sizes', ['size_group' => 'LETTER', 'code' => 'M', 'name' => 'M'])->json('data.id');
        $style = $this->as()->postJson('/api/v1/merchandise/styles', ['buyer_id' => $buyer, 'buyer_office_id' => $office, 'style_ref' => 'SS27-TEE01', 'description' => 'Summer Set', 'qty_unit' => 'PCS', 'pcs_per_unit' => 1, 'status' => 'DEVELOPMENT', 'lines' => [['size_ids' => [$size]]]])
            ->assertCreated()->json('data.id');
        $order = $this->as()->postJson('/api/v1/erp/merch-orders', ['job_no' => '529', 'style_id' => $style, 'total_qty' => 1000, 'currency' => 'usd', 'unit_price' => 2])->assertCreated()->json('data.id');
        $type = collect($this->as()->getJson('/api/v1/prototype/requisitions/options')->json('data.sample_types'))->firstWhere('label', 'Fit Sample (FIT)');
        $this->as()->postJson('/api/v1/prototype/requisitions', ['req_date' => '2026-10-10', 'buyer_id' => $buyer, 'style_id' => $style, 'sample_type_id' => $type['value'], 'qty' => 1, 'required_date' => '2026-10-30'])->assertCreated();
        $this->as()->postJson('/api/v1/prototype/requisitions/SR-2026-00001/submit')->assertOk();
        foreach (range(1, 3) as $i) {
            $this->as()->postJson('/api/v1/prototype/requisitions/SR-2026-00001/decide', ['decision' => 'APPROVE'])->assertOk();
        }

        return [Sample::query()->firstOrFail(), $buyer, $style, $order, $other];
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
