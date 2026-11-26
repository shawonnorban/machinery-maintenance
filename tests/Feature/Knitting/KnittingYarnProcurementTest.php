<?php

declare(strict_types=1);

namespace Tests\Feature\Knitting;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Knitting\Models\KnitYarnCount;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\FiberComposition;
use App\Modules\Settings\Models\Currency;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Knit Production → Yarn Procurement (AnnotechERP 15/02): a yarn indent
 * saved with its lines, submitted, rejected with a reason and approved; a
 * comparative statement filled from it, spinners' offers against its SL #s,
 * finalized; a purchase order raised off the finalized spinner at the agreed
 * price, placed — the indent becoming ordered and the yarn showing as the
 * open pipeline on the next comparison.
 */
class KnittingYarnProcurementTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $store;

    private string $cotton;

    private string $count;

    private string $spinnerA;

    private string $spinnerB;

    private string $usd;

    private string $buyer;

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
        $this->spinnerA = MaterialSupplier::query()->create(['code' => 'SUP-01', 'name' => 'Square Spinning', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $this->spinnerB = MaterialSupplier::query()->create(['code' => 'SUP-02', 'name' => 'Thermax Yarn', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $this->usd = Currency::query()->create(['company_id' => $this->delta->id, 'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'exchange_rate' => 119.5, 'active' => true])->id;
        $this->buyer = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true])->id;
    }

    public function test_indent_comparison_and_purchase_order(): void
    {
        $options = $this->as()->getJson('/api/v1/knitting/yarn/requisitions/options')->assertOk()->json('data');
        $this->assertSame(['100% Cotton'], array_column($options['compositions'], 'label'));
        $this->assertContains('Natural', $options['colours']);

        // Brickland's message for an indent with no yarn; a line with no quantity is refused.
        $head = ['requisition_date' => '2026-10-25', 'store_id' => $this->store, 'buyer_id' => $this->buyer, 'required_date' => '2026-11-05'];
        $this->as()->postJson('/api/v1/knitting/yarn/requisitions', $head + ['lines' => []])->assertUnprocessable()->assertJsonPath('errors.lines.0', 'A requisition that asks for no yarn asks for nothing.');
        $line = ['composition_id' => $this->cotton, 'yarn_count_id' => $this->count, 'colour' => 'Natural', 'uom' => 'kg', 'qty_local' => 500, 'qty_import' => 200, 'booking_price' => 3.4];
        $this->as()->postJson('/api/v1/knitting/yarn/requisitions', $head + ['lines' => [['qty_local' => 0, 'qty_import' => 0] + $line]])->assertUnprocessable()->assertJsonValidationErrors('lines.0.qty_local');
        $yr = $this->as()->postJson('/api/v1/knitting/yarn/requisitions', $head + ['lines' => [$line]])->assertCreated()->json('data');
        $this->assertEquals(['YR-2026100001', 'DRAFT', 700.0, 'KG'], [$yr['requisition_no'], $yr['status'], (float) $yr['lines'][0]['total'], strtoupper($yr['lines'][0]['uom'])]);
        $no = $yr['requisition_no'];

        // Submit; the form then refuses it; the approver rejects only with a reason.
        $this->as()->postJson("/api/v1/knitting/yarn/requisitions/{$no}/submit")->assertOk()->assertJsonPath('data.status', 'WAITING_APPROVAL');
        $this->as()->putJson("/api/v1/knitting/yarn/requisitions/{$no}", $head + ['lines' => [$line]])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertSame([$no], array_column($this->as()->getJson('/api/v1/knitting/yarn/approvals')->json('data'), 'requisition_no'));
        $this->as()->postJson('/api/v1/knitting/yarn/approvals/decide', ['action' => 'reject', 'numbers' => [$no]])->assertUnprocessable()->assertJsonPath('errors.note.0', 'A rejection with no reason is one nobody can answer.');
        $this->as()->postJson('/api/v1/knitting/yarn/approvals/decide', ['action' => 'reject', 'numbers' => [$no], 'note' => 'Split 30/1 by shade'])->assertOk();
        $this->assertSame(['REJECTED', 'Split 30/1 by shade'], [DB::table('knit_yarn_requisitions')->value('status'), DB::table('knit_yarn_requisitions')->value('decision_note')]);
        // Back at the desk it is rewritten, submitted again and approved.
        $this->as()->putJson("/api/v1/knitting/yarn/requisitions/{$no}", $head + ['lines' => [$line, ['colour' => 'Dyed', 'qty_local' => 100, 'qty_import' => 0] + $line]])->assertOk();
        $this->as()->postJson("/api/v1/knitting/yarn/requisitions/{$no}/submit")->assertOk();
        $this->as()->postJson('/api/v1/knitting/yarn/approvals/decide', ['action' => 'approve', 'numbers' => [$no]])->assertOk();
        $this->assertSame('APPROVED', DB::table('knit_yarn_requisitions')->value('status'));
        $this->assertEquals(800.0, (float) $this->as()->getJson('/api/v1/knitting/yarn/requisitions?status=APPROVED')->json('data.0.total'));

        // A comparison filled from the approved indent: SL 1 and 2, no stock or pipeline yet.
        $source = $this->as()->getJson("/api/v1/knitting/yarn/comparisons/source/{$no}")->assertOk()->json('data');
        $this->assertEquals([[1, 700.0, 0.0], [2, 100.0, 0.0]], array_map(fn ($l) => [$l['sl_no'], $l['qty_local'] + $l['qty_import'], $l['pipeline']], $source['lines']));
        $cs = ['cs_date' => '2026-10-26', 'requisition_no' => $no, 'lines' => $source['lines'], 'offers' => [
            ['sl_no' => 1, 'source' => 'LOCAL', 'supplier_id' => $this->spinnerA, 'price' => 3.20, 'currency_id' => $this->usd, 'lead_days' => 15],
            ['sl_no' => 1, 'source' => 'IMPORT', 'supplier_id' => $this->spinnerA, 'price' => 3.05, 'currency_id' => $this->usd],
            ['sl_no' => 1, 'source' => 'LOCAL', 'supplier_id' => $this->spinnerB, 'price' => 3.35, 'currency_id' => $this->usd],
            ['sl_no' => 2, 'source' => 'LOCAL', 'supplier_id' => $this->spinnerB, 'price' => 3.60, 'currency_id' => $this->usd],
        ]];
        $this->as()->postJson('/api/v1/knitting/yarn/comparisons', ['lines' => []] + $cs)->assertUnprocessable()->assertJsonPath('errors.lines.0', 'A comparison with no yarn on it compares nothing.');
        $this->as()->postJson('/api/v1/knitting/yarn/comparisons', ['offers' => [['sl_no' => 9, 'source' => 'LOCAL', 'supplier_id' => $this->spinnerA, 'price' => 3]]] + $cs)->assertUnprocessable()->assertJsonValidationErrors('offers.0.sl_no');
        $made = $this->as()->postJson('/api/v1/knitting/yarn/comparisons', $cs)->assertCreated()->json('data');
        $this->assertSame(['YCS-2026100001', 'DRAFT', 4, '2026-10-25'], [$made['cs_no'], $made['status'], count($made['offers']), $made['requisition_date']]);
        // Finalizing needs a spinner on every line.
        $this->as()->postJson("/api/v1/knitting/yarn/comparisons/{$made['cs_no']}/finalize")->assertUnprocessable()->assertJsonPath('errors.lines.0', 'Pick the spinner each line is bought from before finalizing (SL # 1, 2).');
        $cs['lines'][0]['supplier_id'] = $this->spinnerA;
        $cs['lines'][1]['supplier_id'] = $this->spinnerB;
        $this->as()->putJson("/api/v1/knitting/yarn/comparisons/{$made['cs_no']}", $cs)->assertOk();
        $this->as()->postJson("/api/v1/knitting/yarn/comparisons/{$made['cs_no']}/finalize")->assertOk()->assertJsonPath('data.status', 'FINALIZED');
        $this->as()->putJson("/api/v1/knitting/yarn/comparisons/{$made['cs_no']}", $cs)->assertUnprocessable()->assertJsonValidationErrors('status');

        // The purchase order desk lists the finalized comparison once per spinner, valued at the agreed price.
        $sources = $this->as()->getJson('/api/v1/knitting/yarn/orders/options')->assertOk()->json('data.finalized');
        $a = collect($sources)->firstWhere('supplier_id', $this->spinnerA);
        $this->assertEquals([2, 1, 700.0, 2135.0], [count($sources), $a['items'], (float) $a['qty'], (float) $a['value']]);
        $po = $this->as()->getJson("/api/v1/knitting/yarn/orders/source?cs={$made['cs_no']}&supplier={$this->spinnerA}")->assertOk()->json('data');
        $this->assertEquals([700.0, 3.05, 'IMPORT', $no], [(float) $po['lines'][0]['qty'], (float) $po['lines'][0]['rate'], $po['source'], $po['requisition_no']]);
        $order = ['po_date' => '2026-10-27', 'supplier_id' => $this->spinnerA, 'store_id' => $this->store, 'cs_no' => $made['cs_no'], 'source' => 'IMPORT', 'method' => 'LC',
            'inco_term' => 'CFR', 'payment_term' => 'LC-60', 'ship_mode' => 'SEA', 'currency_id' => $this->usd, 'conv_rate' => 119.5, 'lines' => $po['lines']];
        $this->as()->postJson('/api/v1/knitting/yarn/orders', ['lines' => []] + $order)->assertUnprocessable()->assertJsonPath('errors.lines.0', 'A purchase order that buys nothing orders nothing.');
        $this->as()->postJson('/api/v1/knitting/yarn/orders', ['method' => null] + $order)->assertUnprocessable()->assertJsonValidationErrors('method');
        $placed = $this->as()->postJson('/api/v1/knitting/yarn/orders', $order)->assertCreated()->json('data');
        $this->assertEquals(['YPO-2026100001', 2135.0, $no], [$placed['po_no'], (float) $placed['lines'][0]['amount'], $placed['requisition_no']]);
        $this->as()->postJson("/api/v1/knitting/yarn/orders/{$placed['po_no']}/place")->assertOk()->assertJsonPath('data.status', 'PLACED');
        $this->assertSame('ORDERED', DB::table('knit_yarn_requisitions')->value('status'));
        $this->as()->putJson("/api/v1/knitting/yarn/orders/{$placed['po_no']}", $order)->assertUnprocessable()->assertJsonValidationErrors('status');
        $list = $this->as()->getJson('/api/v1/knitting/yarn/orders?status=PLACED&yr=YR-2026')->json('data.0');
        $this->assertEquals(['Square Spinning', 700.0, 2135.0], [$list['spinner'], (float) $list['qty'], (float) $list['value']]);

        // The placed order is the next comparison's open pipeline, so less is worth ordering.
        $again = $this->as()->getJson("/api/v1/knitting/yarn/comparisons/source/{$no}")->json('data.lines.0');
        $this->assertEquals(700.0, (float) $again['pipeline']);

        // A line chief has no part in yarn buying.
        $chief = TenantFixture::user($this->delta, 'LINE_CHIEF', 'chief@delta.test');
        $this->as($chief)->getJson('/api/v1/knitting/yarn/requisitions')->assertForbidden();
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
