<?php

declare(strict_types=1);

namespace Tests\Feature\Knitting;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Knitting\Models\KnitScProgram;
use App\Modules\Knitting\Models\KnitYarnCount;
use App\Modules\Knitting\Models\KnitYdProgram;
use App\Modules\Knitting\Models\KnitYdProgramLine;
use App\Modules\Knitting\Models\YarnLot;
use App\Modules\Knitting\Support\YarnDyeing;
use App\Modules\Knitting\Support\YarnStore;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\FiberComposition;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Knit Production → Fabric Store (AnnotechERP 15/05), the yarn papers: an
 * approved S/C programme handed to another knitter on finalize (nothing
 * posts); greige off the yarn store on issue against a Y/D programme's
 * shades, counted in its Issue and back on cancel; dyed yarn onto the yarn
 * store when the receipt is finalised; and dyed yarn forwarded off its
 * receive line, never more than that line still holds unforwarded.
 */
class KnittingYarnTransferTest extends TestCase
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
        $this->store = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => 'Yarn Store', 'code' => 'ST-YRN', 'active' => true])->id;
    }

    public function test_programme_transfer_moves_the_programme_on_finalize(): void
    {
        $today = now()->toDateString();
        $ym = now()->format('Ym');
        $akij = MaterialSupplier::query()->create(['code' => 'SUP-40001', 'name' => 'Akij Knit', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $tamishna = MaterialSupplier::query()->create(['code' => 'SUP-40002', 'name' => 'Tamishna Knit', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $prog = KnitScProgram::query()->create(['program_no' => 'SCP-1', 'kind' => 'BULK_SOLID', 'program_type' => 'BULK', 'po_date' => $today, 'party_id' => $akij, 'status' => 'APPROVED']);
        KnitScProgram::query()->create(['program_no' => 'SCP-2', 'kind' => 'BULK_SOLID', 'program_type' => 'BULK', 'po_date' => $today, 'party_id' => $akij, 'status' => 'DRAFT']);
        [$cotton, $count] = $this->yarn();

        $this->assertEquals(['SCP-1'], array_column($this->as()->getJson('/api/v1/knitting/fstore/yarn/options')->json('data.sc_programs'), 'value'));
        $t = ['challan_date' => $today, 'program_no' => 'SCP-1', 'to_party_id' => $tamishna, 'gate_pass_no' => 'GP-1', 'lines' => []];
        $this->as()->postJson('/api/v1/knitting/fstore/yarn/prog-transfers', ['program_no' => 'SCP-2'] + $t)->assertUnprocessable()
            ->assertJsonPath('errors.program_no.0', 'SCP-2 is not running — only an approved programme can be handed over.');
        $this->as()->postJson('/api/v1/knitting/fstore/yarn/prog-transfers', ['to_party_id' => $akij] + $t)->assertUnprocessable()
            ->assertJsonPath('errors.to_party_id.0', 'To Party is the knitter already holding the programme.');
        $this->as()->postJson('/api/v1/knitting/fstore/yarn/prog-transfers', ['lines' => [['qty' => 10, 'uom' => 'kg']]] + $t)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.composition_id' => 'Line 1 needs a yarn item or count.']);

        // An empty grid is allowed: a programme may go over with nothing left to send.
        $doc = $this->as()->postJson('/api/v1/knitting/fstore/yarn/prog-transfers', $t)->assertCreated()->json('data');
        $no = $doc['challan_no'];
        $this->assertEquals(["OYT-{$ym}0001", 'Akij Knit', 'SCP-1', []], [$no, $doc['from_party'], $doc['program_no'], $doc['lines']]);
        $lines = [['composition_id' => $cotton, 'yarn_count_id' => $count, 'colour_group' => 'Natural', 'shade' => 'A', 'lot_no' => 'L-1', 'bags' => 4, 'qty' => 200, 'uom' => 'kg']];
        $doc = $this->as()->putJson("/api/v1/knitting/fstore/yarn/prog-transfers/{$no}", ['lines' => $lines] + $t)->assertOk()->json('data');
        $this->assertEquals(['100% Cotton 30/1', 'KG', 4], [$doc['lines'][0]['yarn'], $doc['lines'][0]['uom'], $doc['lines'][0]['bags']]);

        $this->as()->postJson("/api/v1/knitting/fstore/yarn/prog-transfers/{$no}/finalize")->assertUnprocessable()
            ->assertJsonPath('errors.status.0', "{$no} is Draft, so it cannot be finalized.");
        $this->as()->postJson("/api/v1/knitting/fstore/yarn/prog-transfers/{$no}/issue")->assertOk()->assertJsonPath('data.status', 'ISSUED');
        $this->as()->putJson("/api/v1/knitting/fstore/yarn/prog-transfers/{$no}", $t)->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertSame($akij, $prog->refresh()->party_id);
        $this->as()->postJson("/api/v1/knitting/fstore/yarn/prog-transfers/{$no}/finalize")->assertOk()->assertJsonPath('data.status', 'FINALIZED');
        $this->assertSame($tamishna, $prog->refresh()->party_id);
        $row = $this->as()->getJson('/api/v1/knitting/fstore/yarn/prog-transfers')->json('data.0');
        $this->assertEquals(['Akij Knit', 'Tamishna Knit', 4, 200.0], [$row['from_party'], $row['to_party'], $row['bags'], $row['qty']]);
        $this->as()->postJson("/api/v1/knitting/fstore/yarn/prog-transfers/{$no}/cancel")->assertUnprocessable();
    }

    public function test_grey_out_dyed_back_and_forwarded(): void
    {
        $today = now()->toDateString();
        $ym = now()->format('Ym');
        $dyer = MaterialSupplier::query()->create(['code' => 'SUP-40011', 'name' => 'Rainbow Dyeing', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $knitter = MaterialSupplier::query()->create(['code' => 'SUP-40012', 'name' => 'Akij Knit', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        [$cotton, $count] = $this->yarn();
        $grey = YarnStore::lotFor(['store_id' => $this->store, 'composition_id' => $cotton, 'yarn_count_id' => $count, 'colour' => 'Natural', 'lot_no' => 'G-77'], $today, 3.5);
        YarnStore::post('RECEIPT', 'YRC-T', $today, [['lot_id' => $grey->id, 'qty' => 300]]);
        $yd = KnitYdProgram::query()->create(['program_no' => 'YDP-1', 'program_date' => $today, 'yd_type' => 'SUB_CONTRACT', 'supplier_id' => $dyer, 'status' => 'RUNNING']);
        $shade = KnitYdProgramLine::query()->create(['program_id' => $yd->id, 'sort_order' => 1, 'colour_name' => 'Navy', 'composition_id' => $cotton, 'yarn_count_id' => $count, 'qty' => 250, 'uom' => 'kg']);
        $knitProg = KnitScProgram::query()->create(['program_no' => 'SCP-9', 'kind' => 'PARTY_YD', 'program_type' => 'BULK', 'po_date' => $today, 'party_id' => $knitter, 'status' => 'APPROVED']);

        $opt = collect($this->as()->getJson('/api/v1/knitting/fstore/yarn/options')->json('data.yd_programs'))->firstWhere('value', 'YDP-1');
        $this->assertEquals([250.0, 250.0], [$opt['to_send'], $opt['lines'][0]['to_send']]);

        // Grey yarn out: lots from the store, never more than a lot holds; the store falls back to the first lot's.
        $g = ['challan_date' => $today, 'party_id' => $dyer, 'program_no' => 'YDP-1', 'gate_pass_no' => 'GP-5',
            'lines' => [['program_line_id' => $shade->id, 'lot_id' => $grey->id, 'pack_qty' => 5, 'qty' => 120, 'uom' => 'kg']]];
        $this->as()->postJson('/api/v1/knitting/fstore/yarn/grey-transfers', ['lines' => []] + $g)->assertUnprocessable()->assertJsonPath('errors.lines.0', 'Add at least one shade.');
        $this->as()->postJson('/api/v1/knitting/fstore/yarn/grey-transfers', ['lines' => [['qty' => 301] + $g['lines'][0]]] + $g)->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'Lot G-77 holds only 300 — not enough to send.');
        $gyt = $this->as()->postJson('/api/v1/knitting/fstore/yarn/grey-transfers', $g)->assertCreated()->json('data');
        $this->assertEquals(["GYT-{$ym}0001", $this->store, 'G-77', 'Natural', 'Navy · 100% Cotton 30/1'],
            [$gyt['challan_no'], $gyt['store_id'], $gyt['lines'][0]['lot_no'], $gyt['lines'][0]['colour_name'], $gyt['lines'][0]['shade']]);
        $this->assertSame(300.0, YarnStore::balance($grey->id));
        $this->as()->postJson("/api/v1/knitting/fstore/yarn/grey-transfers/{$gyt['challan_no']}/issue")->assertOk();
        $this->assertSame(180.0, YarnStore::balance($grey->id));
        $this->assertSame(120.0, YarnDyeing::figures(collect([$yd]))['programs'][$yd->id]['issue']);
        $this->as()->postJson("/api/v1/knitting/fstore/yarn/grey-transfers/{$gyt['challan_no']}/cancel")->assertOk();
        $this->assertSame(300.0, YarnStore::balance($grey->id));
        $gyt = $this->as()->postJson('/api/v1/knitting/fstore/yarn/grey-transfers', $g)->assertCreated()->json('data.challan_no');
        $this->as()->postJson("/api/v1/knitting/fstore/yarn/grey-transfers/{$gyt}/issue")->assertOk();
        $this->as()->postJson("/api/v1/knitting/fstore/yarn/grey-transfers/{$gyt}/finalize")->assertOk()->assertJsonPath('data.status', 'FINALIZED');
        $side = collect($this->as()->getJson('/api/v1/knitting/fstore/yarn/options')->json('data.grey_challans'))->firstWhere('value', $gyt);
        $this->assertEquals([120.0, 0.0, '100% Cotton 30/1', 'YDP-1'], [$side['qty'], $side['received'], $side['lines'][0]['yarn'], $side['program_no']]);

        // Dyed yarn back: only against a challan issued to that party; onto the yarn store on finalize, into a store named first.
        $d = ['challan_date' => $today, 'party_id' => $dyer, 'grey_challan' => $gyt, 'gate_pass_no' => 'GP-6',
            'lines' => [['knit_prog' => 'SCP-9', 'composition_id' => $cotton, 'yarn_count_id' => $count, 'colour_name' => 'Navy', 'lot_no' => 'dy-1', 'pack_qty' => 5, 'qty' => 115, 'uom' => 'kg']]];
        $this->as()->postJson('/api/v1/knitting/fstore/yarn/dyed-receipts', ['party_id' => $knitter] + $d)->assertUnprocessable()
            ->assertJsonPath('errors.grey_challan.0', 'That grey challan is not an issued challan to this party.');
        $dyr = $this->as()->postJson('/api/v1/knitting/fstore/yarn/dyed-receipts', $d)->assertCreated()->json('data');
        $no = $dyr['challan_no'];
        $this->assertEquals(["DYR-{$ym}0001", 120.0, 'YDP-1', 'DY-1', 'SCP-9'], [$no, $dyr['g_yarn_qty'], $dyr['program_no'], $dyr['lines'][0]['lot_no'], $dyr['lines'][0]['knit_prog']]);
        $this->as()->postJson("/api/v1/knitting/fstore/yarn/dyed-receipts/{$no}/issue")->assertOk();
        $this->as()->postJson("/api/v1/knitting/fstore/yarn/dyed-receipts/{$no}/finalize")->assertUnprocessable()
            ->assertJsonPath('errors.store_id.0', 'Name the store the cones go onto before finalising.');
        $this->as()->postJson("/api/v1/knitting/fstore/yarn/dyed-receipts/{$no}/cancel")->assertOk();
        $no = $this->as()->postJson('/api/v1/knitting/fstore/yarn/dyed-receipts', ['store_id' => $this->store] + $d)->assertCreated()->json('data.challan_no');
        $this->as()->postJson("/api/v1/knitting/fstore/yarn/dyed-receipts/{$no}/issue")->assertOk();
        $this->as()->postJson("/api/v1/knitting/fstore/yarn/dyed-receipts/{$no}/finalize")->assertOk()->assertJsonPath('data.status', 'FINALIZED');
        $dyed = YarnLot::query()->where('lot_no', 'DY-1')->firstOrFail();
        $this->assertEquals([115.0, 'YARN_DYED', 'Navy', $dyer], [YarnStore::balance($dyed->id), $dyed->source, $dyed->colour, $dyed->supplier_id]);
        $this->as()->postJson("/api/v1/knitting/fstore/yarn/dyed-receipts/{$no}/cancel")->assertUnprocessable();

        // Forwarded on: off a finalised receive line, never more than it still holds.
        $src = $this->as()->getJson('/api/v1/knitting/fstore/yarn/forwardable')->json('data.0');
        $this->assertEquals([$no, 115.0, 'SCP-9', 'DY-1'], [$src['value'], $src['qty'], $src['lines'][0]['knit_prog'], $src['lines'][0]['lot_no']]);
        $line = $src['lines'][0]['value'];
        $f = ['challan_date' => $today, 'party_id' => $knitter, 'store_id' => $this->store, 'vehicle_no' => 'DM-11', 'lines' => [['receipt_line_id' => $line, 'qty' => 70, 'uom' => 'kg']]];
        $dyt = $this->as()->postJson('/api/v1/knitting/fstore/yarn/dyed-transfers', $f)->assertCreated()->json('data');
        $this->assertEquals(["DYT-{$ym}0001", $no, 'SCP-9', 'Navy', '100% Cotton 30/1'],
            [$dyt['challan_no'], $dyt['lines'][0]['receipt_no'], $dyt['lines'][0]['knit_prog'], $dyt['lines'][0]['colour_name'], $dyt['lines'][0]['yarn']]);
        // A draft holds its share; re-saving it never counts itself twice.
        $this->as()->putJson("/api/v1/knitting/fstore/yarn/dyed-transfers/{$dyt['challan_no']}", $f)->assertOk();
        $this->as()->postJson('/api/v1/knitting/fstore/yarn/dyed-transfers', ['lines' => [['qty' => 46] + $f['lines'][0]]] + $f)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.qty' => 'Line 1: only 45 is left unforwarded on that receive line.']);
        $this->as()->postJson("/api/v1/knitting/fstore/yarn/dyed-transfers/{$dyt['challan_no']}/issue")->assertOk();
        $this->assertSame(45.0, YarnStore::balance($dyed->id));
        $row = collect($this->as()->getJson('/api/v1/knitting/fstore/yarn/dyed-receipts')->json('data'))->firstWhere('challan_no', $no);
        $this->assertEquals([70.0, false, 120.0], [$row['forwarded'], $row['transferred'], $row['g_yarn_qty']]);
        $this->as()->postJson("/api/v1/knitting/fstore/yarn/dyed-transfers/{$dyt['challan_no']}/cancel")->assertOk();
        $this->assertSame(115.0, YarnStore::balance($dyed->id));
        $this->assertSame($knitProg->id, KnitScProgram::query()->where('program_no', 'SCP-9')->value('id'));
    }

    /** @return array{0: string, 1: string} */
    private function yarn(): array
    {
        return [
            FiberComposition::query()->create(['code' => 'C100', 'description' => '100% Cotton', 'active' => true])->id,
            KnitYarnCount::query()->create(['count_no' => 'YC-30-1', 'count_system' => 'Ne', 'description' => '30/1', 'count_value' => 30, 'is_single' => true, 'display_order' => 1, 'active' => true])->id,
        ];
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
