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
use App\Modules\Merchandise\Models\Colour;
use App\Modules\Merchandise\Models\FiberComposition;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Settings\Models\Currency;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Knit Production → Production (AnnotechERP 15/04), sub-contract knitting:
 * a Bulk (Solid) programme placed with a party — its Type limited to the
 * screen's, at least one fabric line, the order naming the buyer and work
 * style — submitted (its lines then fixed), rejected back, put right,
 * submitted again and approved, and a draft deleted; and a programme
 * received from a party — the party's own number or the next SCR, serials
 * filled in where left blank, yarn rows only against a serial the fabric
 * lines carry — confirmed, put into production and closed.
 */
class KnittingSubContractTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $party;

    private string $order;

    private string $buyer;

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
        $this->store = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => 'Yarn Store', 'code' => 'ST-YRN', 'active' => true])->id;
        $this->party = MaterialSupplier::query()->create(['code' => 'SUP-10001', 'name' => 'Arafah Knit Composite Ltd', 'supplier_type' => 'LOCAL_NON_EPZ', 'address' => 'Gazipur', 'active' => true])->id;
        $this->buyer = Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'LPP', 'name' => 'LPP SA', 'short_name' => 'LPP', 'pi_address' => 'Gdansk', 'registered_from' => '2026-01-01', 'active' => true])->id;
        $this->order = Order::query()->create(['company_id' => $this->delta->id, 'order_no' => 'LPP-2026-000060', 'job_no' => '7701', 'style_no' => '770MR', 'buyer_id' => $this->buyer,
            'order_type' => 'BULK_NEW', 'total_qty' => 1200, 'ship_date' => '2026-12-15', 'status' => 'CONFIRMED'])->id;
    }

    public function test_programme_submitted_rejected_approved_and_a_draft_deleted(): void
    {
        $today = now()->toDateString();
        $ym = now()->format('Ym');
        $navy = Colour::query()->create(['code' => 'NV-01', 'name' => 'Navy', 'colour_type' => 'SOLID', 'active' => true])->id;
        $options = $this->as()->getJson('/api/v1/knitting/prod/sc/options')->assertOk()->json('data');
        $this->assertSame(['BULK', 'SHORT_BULK'], $options['types']['BULK_SOLID']);
        $this->assertContains('Arafah Knit Composite Ltd (SUP-10001)', array_column($options['parties'], 'label'));

        $line = ['fabric' => 'S/J 30/1 Combed', 'colour_id' => $navy, 'colour_group' => 'Average Colour', 'machine_dia' => '30', 'gauge' => '24', 'finish_dia' => '72', 'gsm' => '160',
            'qty' => 500, 'rate' => 14, 'uom' => 'kg'];
        $p = ['po_date' => $today, 'program_type' => 'BULK', 'party_id' => $this->party, 'category_code' => 'FP04', 'order_id' => $this->order, 'lines' => [$line]];
        $this->as()->postJson('/api/v1/knitting/prod/sc/BULK_SOLID', ['lines' => []] + $p)->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'A programme with no fabrics on it asks the party for nothing.');
        $this->as()->postJson('/api/v1/knitting/prod/sc/BULK_SOLID', ['program_type' => 'SAMPLE'] + $p)->assertUnprocessable()->assertJsonValidationErrors('program_type');
        $this->as()->postJson('/api/v1/knitting/prod/sc/BULK_SOLID', ['lines' => [['qty' => 0] + $line]] + $p)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.qty' => 'Qty must be more than nothing.']);
        $made = $this->as()->postJson('/api/v1/knitting/prod/sc/BULK_SOLID', $p)->assertCreated()->json('data');
        $no = "SCP-{$ym}0001";
        $this->assertEquals([$no, 'DRAFT', 'LPP', '770MR', '770MR · LPP-2026-000060', 'Gazipur', 'Navy (NV-01)', 500.0],
            [$made['program_no'], $made['status'], $made['buyer'], $made['work_style_no'], $made['order'], $made['party_address'], $made['lines'][0]['colour'], $made['lines'][0]['qty']]);

        // The register lists it under its own screen only.
        $rows = $this->as()->getJson('/api/v1/knitting/prod/sc/BULK_SOLID')->assertOk()->json('data');
        $this->assertEquals([[$no, 500.0, 'Arafah Knit Composite Ltd', false]], array_map(fn ($r) => [$r['program_no'], $r['qty'], $r['party'], $r['transferred']], $rows));
        $this->assertSame([], $this->as()->getJson('/api/v1/knitting/prod/sc/PARTY_YD')->json('data'));
        $this->as()->getJson("/api/v1/knitting/prod/sc/PARTY_YD/{$no}")->assertNotFound();

        // Two lines in the order they were sent; then submitted, and the lines are fixed.
        $two = ['lines' => [$line, ['fabric' => '1x1 Rib', 'qty' => 80, 'uom' => 'kg'] + $line]] + $p;
        $this->as()->putJson("/api/v1/knitting/prod/sc/BULK_SOLID/{$no}", $two)->assertOk()->assertJsonPath('data.lines.1.fabric', '1x1 Rib');
        $this->as()->postJson("/api/v1/knitting/prod/sc/BULK_SOLID/{$no}/approve")->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->as()->postJson("/api/v1/knitting/prod/sc/BULK_SOLID/{$no}/submit")->assertOk()->assertJsonPath('data.status', 'SUBMITTED');
        $this->as()->putJson("/api/v1/knitting/prod/sc/BULK_SOLID/{$no}", $p)->assertUnprocessable()
            ->assertJsonPath('errors.status.0', "{$no} is Submitted for Approval — its lines can no longer be rewritten.");
        $this->as()->deleteJson("/api/v1/knitting/prod/sc/BULK_SOLID/{$no}")->assertUnprocessable()->assertJsonValidationErrors('status');

        // Rejected back to the desk, put right, submitted again and approved.
        $this->as()->postJson("/api/v1/knitting/prod/sc/BULK_SOLID/{$no}/reject", ['note' => 'Rate too high'])->assertOk()->assertJsonPath('data.decision_note', 'Rate too high');
        $this->as()->putJson("/api/v1/knitting/prod/sc/BULK_SOLID/{$no}", ['lines' => [['rate' => 12.5] + $line]] + $p)->assertOk();
        $this->as()->postJson("/api/v1/knitting/prod/sc/BULK_SOLID/{$no}/submit")->assertOk();
        $approved = $this->as()->postJson("/api/v1/knitting/prod/sc/BULK_SOLID/{$no}/approve")->assertOk()->json('data');
        $this->assertEquals(['APPROVED', null, 12.5], [$approved['status'], $approved['decision_note'], $approved['lines'][0]['rate']]);
        $this->assertNotNull(DB::table('knit_sc_programs')->where('program_no', $no)->value('decided_by'));

        // Another screen's programme takes the next number; a draft can be deleted.
        $yd = $this->as()->postJson('/api/v1/knitting/prod/sc/PARTY_YD', ['program_type' => 'SHORT_BULK', 'order_id' => null, 'work_style_no' => 'AHEAD-01'] + $p)->assertCreated()->json('data');
        $this->assertEquals(["SCP-{$ym}0002", 'AHEAD-01', null], [$yd['program_no'], $yd['work_style_no'], $yd['buyer']]);
        $this->as()->deleteJson("/api/v1/knitting/prod/sc/PARTY_YD/{$yd['program_no']}")->assertOk();
        $this->assertSame(1, DB::table('knit_sc_programs')->count());
    }

    public function test_receipt_numbered_serials_yarn_and_register_moves(): void
    {
        $today = now()->toDateString();
        $ym = now()->format('Ym');
        $cotton = FiberComposition::query()->create(['code' => 'C100', 'description' => '100% Cotton', 'active' => true])->id;
        $count = KnitYarnCount::query()->create(['count_no' => 'YC-30-1', 'count_system' => 'Ne', 'description' => '30/1', 'count_value' => 30, 'is_single' => true, 'display_order' => 1, 'active' => true])->id;
        $dia = collect($this->as()->getJson('/api/v1/knitting/ref-lists/DIA')->json('data'))->firstWhere('code', 'D30')['id'];
        $gg = collect($this->as()->getJson('/api/v1/knitting/ref-lists/GG')->json('data'))->firstWhere('code', 'GG24')['id'];

        // The party's yarn sitting in the store: what the yarn rows pick from.
        $usd = Currency::query()->create(['company_id' => $this->delta->id, 'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'exchange_rate' => 119.5, 'active' => true])->id;
        $ob = $this->as()->postJson('/api/v1/knitting/ystore/obs', ['ob_date' => now()->startOfMonth()->toDateString(), 'store_id' => $this->store, 'currency_id' => $usd, 'lines' => [
            ['composition_id' => $cotton, 'yarn_count_id' => $count, 'colour' => 'Natural', 'brand' => 'Square', 'source' => 'SC_PARTY', 'lot_no' => 'P-1', 'supplier_id' => $this->party, 'total_qty' => 900, 'cost_price' => 0],
        ]])->assertCreated()->json('data.ob_no');
        $this->as()->postJson("/api/v1/knitting/ystore/obs/{$ob}/finalize")->assertOk();
        $held = $this->as()->getJson("/api/v1/knitting/prod/sc-receipts/store-yarns?store={$this->store}")->assertOk()->json('data');
        $this->assertSame([['Square', 'P-1']], array_map(fn ($l) => [$l['brand'], $l['lot_no']], $held));

        $fab = ['order_id' => $this->order, 'composition_id' => $cotton, 'gsm' => 160, 'qty' => 1200, 'uom' => 'kg', 'finish_dia' => 70, 'dia_unit' => 'INCH', 'width_type' => 'OPEN',
            'yd_type' => 'SOLID', 'mc_dia_id' => $dia, 'gauge_id' => $gg, 'rate_type' => 'PER_KG', 'rate' => 14, 'start_date' => $today, 'end_date' => $today];
        $r = ['prog_date' => $today, 'party_id' => $this->party, 'yarn_store_id' => $this->store, 'fabrics' => [$fab], 'yarns' => []];
        $this->as()->postJson('/api/v1/knitting/prod/sc-receipts', ['fabrics' => []] + $r)->assertUnprocessable()
            ->assertJsonPath('errors.fabrics.0', 'A programme with no fabric on it asks this mill to knit nothing.');
        $this->as()->postJson('/api/v1/knitting/prod/sc-receipts', ['auto_number' => false] + $r)->assertUnprocessable()->assertJsonValidationErrors('program_no');
        $this->as()->postJson('/api/v1/knitting/prod/sc-receipts', ['auto_number' => false, 'program_no' => 'AK/77'] + $r)->assertUnprocessable()->assertJsonValidationErrors('program_no');

        // Serials: a blank one takes the next free number; a yarn row must hang off one the fabrics carry.
        $three = ['fabrics' => [['sl' => 5] + $fab, $fab, ['qty' => 300, 'colour_name' => 'Navy'] + $fab]] + $r;
        $yarn = ['composition_id' => $cotton, 'yarn_count_id' => $count, 'brand' => 'Square', 'lot_no' => 'P-1', 'stitch_length' => 2.75, 'ratio_qty' => 100, 'required_qty' => 1200];
        $this->as()->postJson('/api/v1/knitting/prod/sc-receipts', ['yarns' => [['sl' => 9] + $yarn]] + $three)->assertUnprocessable()
            ->assertJsonValidationErrors(['yarns.0.sl' => 'Yarn row 1 hangs off serial 9, which no fabric line carries.']);
        $this->as()->postJson('/api/v1/knitting/prod/sc-receipts', ['fabrics' => [['sl' => 2] + $fab, ['sl' => 2] + $fab]] + $r)->assertUnprocessable()
            ->assertJsonValidationErrors(['fabrics.1.sl' => 'Fab. Ord. SL 2 is on more than one fabric line.']);
        $made = $this->as()->postJson('/api/v1/knitting/prod/sc-receipts', ['yarns' => [['sl' => 6] + $yarn, ['sl' => 5, 'required_qty' => 50] + $yarn]] + $three)->assertCreated()->json('data');
        $no = "SCR-{$ym}0001";
        $this->assertEquals([$no, [5, 6, 7], 2700.0, 1250.0, '100% Cotton 30/1', '770MR · LPP-2026-000060', '30"', '24GG'],
            [$made['program_no'], array_column($made['fabrics'], 'sl'), $made['qty'], $made['yarn_qty'], $made['yarns'][0]['yarn'], $made['fabrics'][0]['order'], $made['fabrics'][0]['mc_dia'], $made['fabrics'][0]['gauge']]);

        // The party's own number instead; taken once only.
        $own = $this->as()->postJson('/api/v1/knitting/prod/sc-receipts', ['auto_number' => false, 'program_no' => 'AKC-PRG 0091'] + $r)->assertCreated()->json('data');
        $this->assertEquals(['AKC-PRG 0091', false], [$own['program_no'], $own['auto_number']]);
        $this->as()->postJson('/api/v1/knitting/prod/sc-receipts', ['auto_number' => false, 'program_no' => 'AKC-PRG 0091'] + $r)->assertUnprocessable()
            ->assertJsonPath('errors.program_no.0', 'That Program# is already on another programme.');

        // The register: Confirm fixes the lines, then production, then closed.
        $rows = $this->as()->getJson("/api/v1/knitting/prod/sc-receipts?party={$this->party}")->assertOk()->json('data');
        $this->assertEquals([[$no, 3, 2700.0, 1250.0, 'Yarn Store']], array_values(array_map(fn ($x) => [$x['program_no'], $x['fabrics'], $x['qty'], $x['yarn_qty'], $x['yarn_store']],
            array_filter($rows, fn ($x) => $x['program_no'] === $no))));
        $this->as()->postJson("/api/v1/knitting/prod/sc-receipts/{$no}/start")->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->as()->postJson("/api/v1/knitting/prod/sc-receipts/{$no}/confirm")->assertOk()->assertJsonPath('data.status', 'CONFIRMED');
        $this->as()->putJson("/api/v1/knitting/prod/sc-receipts/{$no}", $r)->assertUnprocessable()
            ->assertJsonPath('errors.status.0', "{$no} is Confirmed — only a draft can be rewritten.");
        $this->as()->postJson("/api/v1/knitting/prod/sc-receipts/{$no}/start")->assertOk()->assertJsonPath('data.status', 'IN_PRODUCTION');
        $this->as()->postJson("/api/v1/knitting/prod/sc-receipts/{$no}/close")->assertOk()->assertJsonPath('data.status', 'CLOSED');
        $this->as()->postJson("/api/v1/knitting/prod/sc-receipts/{$no}/cancel")->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->as()->postJson('/api/v1/knitting/prod/sc-receipts/'.rawurlencode('AKC-PRG 0091').'/cancel')->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        $this->assertSame(['CANCELLED'], array_column($this->as()->getJson('/api/v1/knitting/prod/sc-receipts?status=CANCELLED')->json('data'), 'status'));
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
