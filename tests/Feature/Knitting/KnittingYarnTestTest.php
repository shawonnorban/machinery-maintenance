<?php

declare(strict_types=1);

namespace Tests\Feature\Knitting;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Knitting\Models\KnitYarnCount;
use App\Modules\Knitting\Models\YarnLot;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\FiberComposition;
use App\Modules\Settings\Models\Currency;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Knit Production → Production (AnnotechERP 15/04), the yarn test chain: a
 * test order raised (lots required) as Waiting for Issue and issued; a lab
 * test refused until the store has issued, then filed — one register row per
 * lot, the order Tested — and filed again in place of the first reading; and
 * lots the store holds released without test on someone's authority, a
 * second release replacing the first; the register reading both.
 */
class KnittingYarnTestTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $store;

    private string $cotton;

    private string $count;

    private string $spinner;

    private string $labHead;

    private string $qcManager;

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
        $this->cotton = FiberComposition::query()->create(['code' => 'C100', 'description' => '100% Cotton', 'active' => true])->id;
        $this->count = KnitYarnCount::query()->create(['count_no' => 'YC-30-1', 'count_system' => 'Ne', 'description' => '30/1', 'count_value' => 30, 'is_single' => true, 'display_order' => 1, 'active' => true])->id;
        $this->spinner = MaterialSupplier::query()->create(['code' => 'SUP-01', 'name' => 'Square Spinning', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
    }

    public function test_order_issued_tested_refiled_and_lots_released_without_test(): void
    {
        $this->labHead = $this->employee('50000554', 'Manik Raton');
        $this->qcManager = $this->employee('50000701', 'Shayem Shamsuddin');
        $today = now()->toDateString();
        $ym = now()->format('Ym');
        $usd = Currency::query()->create(['company_id' => $this->delta->id, 'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'exchange_rate' => 119.5, 'active' => true])->id;
        $yarn = ['composition_id' => $this->cotton, 'yarn_count_id' => $this->count, 'colour' => 'Natural', 'brand' => 'Square', 'source' => 'LOCAL', 'supplier_id' => $this->spinner, 'cost_price' => 3];
        $ob = $this->as()->postJson('/api/v1/knitting/ystore/obs', ['ob_date' => now()->startOfMonth()->toDateString(), 'store_id' => $this->store, 'currency_id' => $usd, 'lines' => [
            $yarn + ['lot_no' => 'SQ-3011', 'total_qty' => 500], $yarn + ['lot_no' => 'SQ-3012', 'total_qty' => 300],
        ]])->assertCreated()->json('data.ob_no');
        $this->as()->postJson("/api/v1/knitting/ystore/obs/{$ob}/finalize")->assertOk();

        $options = $this->as()->getJson('/api/v1/knitting/prod/yarn-test/options')->assertOk()->json('data');
        $this->assertSame(['Manik Raton', 'Shayem Shamsuddin'], array_map(fn ($l) => explode(' (', $l)[0], array_column($options['employees'], 'label')));
        $this->assertEqualsCanonicalizing(['SQ-3011', 'SQ-3012'], array_column($options['lots'], 'lot_no'));

        // The test order: Yarn Description and at least one lot.
        $line = ['composition_id' => $this->cotton, 'yarn_count_id' => $this->count, 'colour_group' => 'Natural', 'brand' => 'Square', 'supplier_id' => $this->spinner, 'lc_no' => 'LC-45/26', 'lot_no' => 'SQ-3011', 'qty' => 2, 'uom' => 'kg'];
        $o = ['order_date' => $today, 'store_id' => $this->store, 'requested_by' => $this->qcManager, 'attn_to' => $this->labHead, 'yarn_description' => '30/1 Combed Cotton — new spinner trial',
            'knit_card_no' => 'KC-TRIAL-01', 'lines' => [$line, ['lot_no' => 'SQ-3012', 'qty' => 1.5] + $line]];
        $this->as()->postJson('/api/v1/knitting/prod/yarn-test/orders', ['lines' => []] + $o)->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'A test order with no lots on it sends the lab nothing to look at.');
        $this->as()->postJson('/api/v1/knitting/prod/yarn-test/orders', ['yarn_description' => ''] + $o)->assertUnprocessable()->assertJsonValidationErrors('yarn_description');
        $made = $this->as()->postJson('/api/v1/knitting/prod/yarn-test/orders', $o)->assertCreated()->json('data');
        $no = "YTO-{$ym}0001";
        $this->assertEquals([$no, 'WAITING_ISSUE', 'YARN_TEST', 'Shayem Shamsuddin', 'Manik Raton', '100% Cotton 30/1', 'Square Spinning'],
            [$made['order_no'], $made['status'], $made['req_type'], $made['requested_by_name'], $made['attn_to_name'], $made['lines'][0]['yarn'], $made['lines'][0]['supplier']]);

        // The lab cannot file before the store has issued.
        $lab = fn (array $lines) => ['order_no' => $no, 'test_date' => $today, 'test_by' => $this->labHead, 'lines' => $lines];
        $passed = ['result' => 'PASSED', 'colour' => 'Natural'] + collect($line)->except(['qty', 'uom'])->all();
        $failed = ['lot_no' => 'SQ-3012', 'result' => 'FAILED', 'note' => 'Neps over limit'] + $passed;
        $this->as()->postJson('/api/v1/knitting/prod/yarn-test/tests', $lab([$passed]))->assertUnprocessable()
            ->assertJsonPath('errors.order_no.0', "{$no} is Waiting for Issue — the store has not issued its yarn to the lab.");
        $issued = $this->as()->postJson("/api/v1/knitting/prod/yarn-test/orders/{$no}/issue")->assertOk()->json('data');
        $this->assertEquals(['ISSUED', 'SQ-3011, SQ-3012', null], [$issued['status'], $issued['lots'], $issued['results']]);
        $this->as()->postJson("/api/v1/knitting/prod/yarn-test/orders/{$no}/issue")->assertUnprocessable()->assertJsonValidationErrors('status');

        // Filed: one register row per lot, the order Tested and fixed.
        $this->as()->postJson('/api/v1/knitting/prod/yarn-test/tests', $lab([]))->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'A test with no lots on it files no answer about any yarn.');
        $test = $this->as()->postJson('/api/v1/knitting/prod/yarn-test/tests', $lab([$passed, ['result' => 'PENDING', 'note' => null] + $failed]))->assertCreated()->json('data');
        $tsNo = "YTS-{$ym}0001";
        $this->assertEquals([$tsNo, $no, ['PASSED', 'PENDING']], [$test['test_no'], $test['order_no'], array_column($test['lines'], 'result')]);
        $orders = $this->as()->getJson('/api/v1/knitting/prod/yarn-test/orders')->json('data');
        $this->assertEquals(['TESTED', '1 Passed · 1 Pending'], [$orders[0]['status'], $orders[0]['results']]);
        $this->as()->putJson("/api/v1/knitting/prod/yarn-test/orders/{$no}", $o)->assertUnprocessable()
            ->assertJsonPath('errors.status.0', "{$no} is Tested — it can no longer be rewritten.");
        $this->as()->postJson("/api/v1/knitting/prod/yarn-test/orders/{$no}/cancel")->assertUnprocessable()->assertJsonValidationErrors('status');

        // Filed again against the same order: the reading is replaced, not added to.
        $again = $this->as()->postJson('/api/v1/knitting/prod/yarn-test/tests', $lab([$passed, $failed]))->assertOk()->json('data');
        $this->assertEquals([$tsNo, ['PASSED', 'FAILED']], [$again['test_no'], array_column($again['lines'], 'result')]);
        $this->assertSame(2, DB::table('knit_yarn_test_results')->count());
        $this->assertSame('1 Passed · 1 Failed', $this->as()->getJson('/api/v1/knitting/prod/yarn-test/orders')->json('data.0.results'));
        $this->assertEquals(['PASSED', 'FAILED'], array_column($this->as()->getJson("/api/v1/knitting/prod/yarn-test/orders/{$no}")->json('data.lines'), 'result'));

        // Released without test: lots the store holds, each with the answer it carries.
        $held = collect($this->as()->getJson('/api/v1/knitting/prod/yarn-test/waiver-lots?brand=Square')->assertOk()->json('data'))->keyBy('lot_no');
        $this->assertEquals([['FAILED', 'LAB'], 500.0], [[$held['SQ-3012']['answered']['result'], $held['SQ-3012']['answered']['basis']], $held['SQ-3011']['balance']]);
        $this->assertCount(1, $this->as()->getJson('/api/v1/knitting/prod/yarn-test/waiver-lots?lot=3012')->json('data'));
        $lot = YarnLot::query()->where('lot_no', 'SQ-3012')->value('id');
        $waiver = ['test_date' => $today, 'test_by' => $this->qcManager, 'lots' => [['lot_id' => $lot, 'note' => 'Buyer accepted the neps for a dark shade']]];
        $this->as()->postJson('/api/v1/knitting/prod/yarn-test/waivers', ['lots' => []] + $waiver)->assertUnprocessable()->assertJsonPath('errors.lots.0', 'Tick at least one lot to release.');
        $this->as()->postJson('/api/v1/knitting/prod/yarn-test/waivers', ['test_by' => null] + $waiver)->assertUnprocessable()
            ->assertJsonPath('errors.test_by.0', 'Test By is required — it is the authority the release is made on.');
        $rows = $this->as()->postJson('/api/v1/knitting/prod/yarn-test/waivers', $waiver)->assertCreated()->json('data.rows');
        $this->assertEquals([['WAIVED', 'PASSED', 'Shayem Shamsuddin', '100% Cotton 30/1', 'SQ-3012']], array_map(fn ($r) => [$r['basis'], $r['result'], $r['test_by'], $r['yarn'], $r['lot_no']], $rows));
        $this->assertSame(['PASSED', 'WAIVED'], array_values(collect($this->as()->getJson('/api/v1/knitting/prod/yarn-test/waiver-lots?lot=SQ-3012')->json('data.0.answered'))->only(['result', 'basis'])->all()));
        // Released again: the first release is replaced.
        $this->as()->postJson('/api/v1/knitting/prod/yarn-test/waivers', ['test_by' => $this->labHead, 'lots' => [['lot_id' => $lot, 'note' => 'Corrected authority']]] + $waiver)->assertCreated();
        $waived = $this->as()->getJson('/api/v1/knitting/prod/yarn-test/results?basis=WAIVED')->json('data');
        $this->assertEquals([['Manik Raton', 'Corrected authority']], array_map(fn ($r) => [$r['test_by'], $r['note']], $waived));

        // The register: both kinds, filtered.
        $this->assertCount(3, $this->as()->getJson('/api/v1/knitting/prod/yarn-test/results')->json('data'));
        $this->assertEquals([[$tsNo, $no]], array_map(fn ($r) => [$r['test_no'], $r['order_no']], $this->as()->getJson('/api/v1/knitting/prod/yarn-test/results?basis=LAB&status=FAILED')->json('data')));
        $this->assertCount(2, $this->as()->getJson("/api/v1/knitting/prod/yarn-test/results?count={$this->count}&lot=SQ-3012")->json('data'));

        // A second order, cancelled before it is issued.
        $two = $this->as()->postJson('/api/v1/knitting/prod/yarn-test/orders', ['lines' => [$line]] + $o)->assertCreated()->json('data.order_no');
        $this->assertSame("YTO-{$ym}0002", $two);
        $this->as()->postJson("/api/v1/knitting/prod/yarn-test/orders/{$two}/cancel")->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        $this->as()->postJson('/api/v1/knitting/prod/yarn-test/tests', ['order_no' => $two] + $lab([$passed]))->assertUnprocessable()->assertJsonValidationErrors('order_no');
    }

    private function employee(string $card, string $name): string
    {
        $worker = collect($this->as()->getJson('/api/v1/hr-payroll/employees/options')->json('data.structures'))->firstWhere('label', 'Worker — RMG Minimum Wage 2023 (Non-EPZ)');
        $this->as()->postJson('/api/v1/hr-payroll/employees', ['card_no' => $card, 'name' => $name, 'nid' => '1234567890', 'mobile' => '01712345678', 'structure' => $worker['value'], 'gross_a' => 14000, 'gross_b' => 18000])->assertCreated();

        return DB::table('hr_employees')->where('company_id', $this->delta->id)->where('card_no', $card)->value('id');
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
