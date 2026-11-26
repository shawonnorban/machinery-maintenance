<?php

declare(strict_types=1);

namespace Tests\Feature\Finishing;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Finishing Line → S/C Process (AnnotechERP 18/03): a batch sent out to a
 * processor on a posted challan and brought back onto the floor once its
 * lines are answered, the finished receipt never taking back more than is
 * out, the processor's bill held to what came back, the party balance — and
 * who may do what.
 */
class FinishingScProcessTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $store;

    private string $party;

    private string $other;

    /** @var array<string, string> code => id */
    private array $process = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'FAC01');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $warehouse = Warehouse::query()->create(['company_id' => $this->delta->id, 'factory_id' => $factory->id, 'name' => 'Main Warehouse', 'code' => 'WH-1', 'active' => true]);
        $this->store = Store::query()->create(['company_id' => $this->delta->id, 'warehouse_id' => $warehouse->id, 'name' => 'Finish Fabric Store', 'code' => 'ST-FIN', 'active' => true])->id;
        $this->party = MaterialSupplier::query()->create(['code' => 'SUP-1', 'name' => 'Ha-Meem Processing', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $this->other = MaterialSupplier::query()->create(['code' => 'SUP-2', 'name' => 'Rupa Processing', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $this->process = collect($this->as()->getJson('/api/v1/erp/fin-process-types')->assertOk()->json('data'))->pluck('id', 'code')->all();
    }

    public function test_a_challan_sends_its_batches_out_and_a_cancelled_one_brings_them_back(): void
    {
        $card = $this->card('B-26110001', 'Navy');
        $c = ['challan_date' => '2026-11-06', 'supplier_id' => $this->party, 'store_id' => $this->store, 'vehicle_no' => 'DM-11-2233'];
        $line = ['receipt_id' => $card, 'process_id' => $this->process['DF-ST'], 'qty_kg' => 60, 'rolls' => 4];
        $this->as()->postJson('/api/v1/finishing/sc-challans', $c + ['lines' => []])->assertUnprocessable()->assertJsonPath('errors.lines.0', 'A challan with no shades on it sends nothing to the processor.');
        $bad = $this->as()->postJson('/api/v1/finishing/sc-challans', $c + ['lines' => [['process_id' => $this->process['DF-ST'], 'qty_kg' => 5], $line, ['qty_kg' => 50] + $line]])->assertUnprocessable()->json('errors');
        $this->assertSame('Row 1: name the batch, or give the lot or a description of the goods.', $bad['lines.0.receipt_id'][0]);
        $this->assertSame('Row 3: no more of batch B-26110001 can go out than the 100 kg received.', $bad['lines.2.qty_kg'][0]);

        $no = $this->as()->postJson('/api/v1/finishing/sc-challans', $c + ['lines' => [$line, ['receipt_id' => null, 'lot_no' => 'RIB-7', 'process_id' => $this->process['DF-CMP'], 'qty_kg' => 12.5, 'rate' => 7]]])->assertCreated()
            ->assertJsonPath('data.challan_no', 'FSC-CH-2026110001')->assertJsonPath('data.status', 'DRAFT')->assertJsonPath('data.lines.0.colour', 'Navy')->assertJsonPath('data.lines.0.process', 'Stentering (ST)')
            ->assertJsonPath('data.lines.0.balance_kg', 60)->json('data.challan_no');
        $this->as()->putJson("/api/v1/finishing/sc-challans/{$no}", ['gate_pass_no' => 'GP-501'] + $c + ['lines' => [$line]])->assertOk()->assertJsonCount(1, 'data.lines')->assertJsonPath('data.gate_pass_no', 'GP-501');

        $this->as()->postJson("/api/v1/finishing/sc-challans/{$no}/post")->assertOk()->assertJsonPath('data.status', 'POSTED');
        $this->assertSame('AT_SUB_CONTRACT', DB::table('fin_batch_receipts')->where('id', $card)->value('stage'));
        $this->assertSame([], $this->opts()['cards']);
        $this->as()->putJson("/api/v1/finishing/sc-challans/{$no}", $c + ['lines' => [$line]])->assertUnprocessable()->assertJsonPath('errors.challan_no.0', 'FSC-CH-2026110001 is Posted — it is not changed here.');
        $this->as()->getJson('/api/v1/finishing/sc-challans')->assertJsonPath('data.0.qty_kg', 60)->assertJsonPath('data.0.balance_kg', 60)->assertJsonPath('data.0.supplier', 'Ha-Meem Processing');

        // Nothing back, nothing billed: it may be cancelled, and the batch returns to the stage it left from.
        $this->as()->postJson("/api/v1/finishing/sc-challans/{$no}/cancel")->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        $this->assertSame('AWAITING_LOAD', DB::table('fin_batch_receipts')->where('id', $card)->value('stage'));
        $this->assertSame(['RECEIPT:AWAITING_LOAD', 'SC_OUT:AT_SUB_CONTRACT', 'SC_BACK:AWAITING_LOAD'], DB::table('fin_process_deliveries')->where('receipt_id', $card)->orderBy('created_at')->orderBy('id')->get()->map(fn ($d) => "{$d->source}:{$d->to_stage}")->all());
        $this->as()->postJson("/api/v1/finishing/sc-challans/{$no}/cancel")->assertUnprocessable();
    }

    public function test_the_finished_receipt_answers_challan_lines_and_brings_the_batch_back(): void
    {
        $card = $this->card('B-26110011', 'Red');
        $no = $this->challan([['receipt_id' => $card, 'process_id' => $this->process['DF-ST'], 'qty_kg' => 100, 'rolls' => 6]]);
        $draft = $this->as()->postJson('/api/v1/finishing/sc-challans', ['challan_date' => '2026-11-06', 'supplier_id' => $this->party, 'store_id' => $this->store, 'lines' => [['lot_no' => 'X', 'process_id' => $this->process['DF-ST'], 'qty_kg' => 5]]])->json('data');
        $challan = collect($this->opts()['challans'])->firstWhere('challan_no', $no);
        $lineId = $challan['lines'][0]['value'];
        $r = ['receive_date' => '2026-11-08', 'challan_id' => $challan['value'], 'supplier_id' => $this->party, 'store_id' => $this->store, 'gate_pass_no' => 'IN-77'];

        $this->as()->postJson('/api/v1/finishing/sc-receipts', ['challan_id' => $draft['id']] + $r + ['lines' => [['recv_kg' => 1]]])->assertUnprocessable()->assertJsonPath('errors.challan_id.0', 'FSC-CH-2026110002 is not posted — nothing is received against it.');
        $this->as()->postJson('/api/v1/finishing/sc-receipts', ['supplier_id' => $this->other] + $r + ['lines' => [['challan_line_id' => $lineId, 'recv_kg' => 50]]])->assertUnprocessable()->assertJsonPath('errors.supplier_id.0', 'The party is the one FSC-CH-2026110001 went out to.');
        $this->as()->postJson('/api/v1/finishing/sc-receipts', ['receive_date' => '2026-11-05'] + $r + ['lines' => [['challan_line_id' => $lineId, 'recv_kg' => 50]]])->assertUnprocessable()->assertJsonValidationErrors('receive_date');
        $this->as()->postJson('/api/v1/finishing/sc-receipts', $r + ['lines' => [['challan_line_id' => $lineId, 'recv_kg' => 99, 'short_kg' => 2]]])->assertUnprocessable()
            ->assertJsonPath('errors', fn ($e) => $e['lines.0.recv_kg'][0] === 'Row 1: only 100 kg is still out on that line.');

        // Part comes back: the batch is still out.
        $mrr = $this->as()->postJson('/api/v1/finishing/sc-receipts', $r + ['lines' => [['challan_line_id' => $lineId, 'recv_kg' => 58, 'rolls' => 3, 'short_kg' => 0.5]]])->assertCreated()
            ->assertJsonPath('data.mrr_no', 'FSC-MRR-2026110001')->assertJsonPath('data.status', 'DRAFT')->json('data.mrr_no');
        $this->as()->getJson('/api/v1/finishing/sc-challans')->assertJsonPath('data', fn ($rows) => collect($rows)->firstWhere('challan_no', $no)['received_kg'] === 0);
        $this->as()->postJson("/api/v1/finishing/sc-receipts/{$mrr}/post")->assertOk()->assertJsonPath('data.status', 'POSTED');
        $this->assertSame('AT_SUB_CONTRACT', DB::table('fin_batch_receipts')->where('id', $card)->value('stage'));
        $this->as()->putJson("/api/v1/finishing/sc-receipts/{$mrr}", $r + ['lines' => [['challan_line_id' => $lineId, 'recv_kg' => 1]]])->assertUnprocessable()->assertJsonPath('errors.mrr_no.0', 'FSC-MRR-2026110001 is posted and reads only.');
        $this->as()->getJson('/api/v1/finishing/sc-balance?open=1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.balance_kg', 41.5)->assertJsonPath('data.0.batch_no', 'B-26110011')->assertJsonPath('data.0.received_kg', 58);
        $this->as()->postJson("/api/v1/finishing/sc-challans/{$no}/cancel")->assertUnprocessable()->assertJsonPath('errors.challan_no.0', 'Fabric has come back or been billed against FSC-CH-2026110001, so it is not cancelled.');

        // The rest comes back: the batch is on the floor again, at the stage of the process done for it.
        $rest = $this->as()->postJson('/api/v1/finishing/sc-receipts', $r + ['lines' => [['challan_line_id' => $lineId, 'recv_kg' => 41, 'short_kg' => 0.5]]])->assertCreated()->json('data.mrr_no');
        $this->as()->postJson("/api/v1/finishing/sc-receipts/{$rest}/post")->assertOk();
        $this->assertSame('STENTERING', DB::table('fin_batch_receipts')->where('id', $card)->value('stage'));
        $this->as()->getJson('/api/v1/finishing/sc-balance?open=1')->assertJsonCount(0, 'data');
        $this->as()->getJson('/api/v1/finishing/sc-balance?party='.$this->party)->assertJsonCount(1, 'data')->assertJsonPath('data.0.balance_kg', 0)->assertJsonPath('data.0.days_out', null);
        $this->as()->get('/api/v1/finishing/sc-balance/export')->assertOk();
        // An unmatched return is booked, but nets off nothing.
        $this->as()->postJson('/api/v1/finishing/sc-receipts', ['challan_id' => null] + $r + ['lines' => [['description' => 'Loose rolls', 'recv_kg' => 4]]])->assertCreated();
        $this->as()->postJson('/api/v1/finishing/sc-receipts', ['challan_id' => null] + $r + ['lines' => [['challan_line_id' => $lineId, 'recv_kg' => 4]]])->assertUnprocessable()->assertJsonValidationErrors('lines.0.challan_line_id');
    }

    public function test_the_process_bill_is_held_to_what_came_back(): void
    {
        $card = $this->card('B-26110021', 'Navy');
        $no = $this->challan([['receipt_id' => $card, 'process_id' => $this->process['DF-ST'], 'qty_kg' => 100]]);
        $mine = collect($this->opts()['challans'])->firstWhere('challan_no', $no);
        $theirs = $this->challan([['lot_no' => 'L-9', 'process_id' => $this->process['DF-ST'], 'qty_kg' => 10]], $this->other);
        $mrr = $this->as()->postJson('/api/v1/finishing/sc-receipts', ['receive_date' => '2026-11-08', 'challan_id' => $mine['value'], 'supplier_id' => $this->party, 'store_id' => $this->store, 'lines' => [['challan_line_id' => $mine['lines'][0]['value'], 'recv_kg' => 80]]])->json('data.mrr_no');
        $this->as()->postJson("/api/v1/finishing/sc-receipts/{$mrr}/post")->assertOk();
        $theirsId = collect($this->opts()['challans'])->firstWhere('challan_no', $theirs)['value'];

        $b = ['bill_date' => '2026-11-10', 'bill_no' => ' hm-inv-77 ', 'supplier_id' => $this->party];
        $line = ['challan_id' => $mine['value'], 'process_id' => $this->process['DF-ST'], 'qty_kg' => 50];
        $bad = $this->as()->postJson('/api/v1/finishing/sc-bills', $b + ['lines' => [['challan_id' => $theirsId] + $line, ['process_id' => $this->process['DF-CMP']] + $line, ['qty_kg' => 90] + $line]])->assertUnprocessable()->json('errors');
        $this->assertSame("Row 1: {$theirs} is not a posted challan of this party.", $bad['lines.0.challan_id'][0]);
        $this->assertSame("Row 2: nothing went out on {$no} for that process.", $bad['lines.1.process_id'][0]);
        $this->assertSame('Row 3: only 80 kg has come back and not been billed for that process on that challan.', $bad['lines.2.qty_kg'][0]);

        // The rate defaults from the process (Stentering, 8.00); the amount is never typed.
        $this->as()->postJson('/api/v1/finishing/sc-bills', $b + ['lines' => [$line, ['qty_kg' => 20, 'rate' => 9.5] + $line]])->assertCreated()
            ->assertJsonPath('data.bill_no', 'HM-INV-77')->assertJsonPath('data.lines.0.rate', 8)->assertJsonPath('data.lines.0.amount', 400)->assertJsonPath('data.amount', 590)->assertJsonPath('data.party_code', 'SUP-1');
        $this->as()->postJson('/api/v1/finishing/sc-bills', $b + ['lines' => [['qty_kg' => 5] + $line]])->assertUnprocessable()->assertJsonPath('errors.bill_no.0', "The party's bill HM-INV-77 is entered already.");
        $this->as()->postJson('/api/v1/finishing/sc-bills', $b + ['lines' => [['qty_kg' => 11] + $line]])->assertUnprocessable();
        $this->as()->postJson('/api/v1/finishing/sc-bills', ['supplier_id' => $this->other] + $b + ['lines' => [['challan_id' => $theirsId, 'qty_kg' => 1] + $line]])->assertUnprocessable()->assertJsonValidationErrors('lines.0.qty_kg');

        $this->as()->getJson('/api/v1/finishing/sc-bills/hm-inv-77?party=SUP-1')->assertOk()->assertJsonPath('data.amount', 590);
        $this->as()->postJson('/api/v1/finishing/sc-bills/HM-INV-77/status?party=SUP-1', ['to' => 'COMPLETED'])->assertUnprocessable()->assertJsonPath('errors.to.0', 'HM-INV-77 is Draft — that step does not follow.');
        $this->as()->postJson('/api/v1/finishing/sc-bills/HM-INV-77/status?party=SUP-1', ['to' => 'SUBMITTED'])->assertOk()->assertJsonPath('data.status', 'SUBMITTED');
        $this->as()->putJson('/api/v1/finishing/sc-bills/HM-INV-77?party=SUP-1', $b + ['rev_no' => 'R1', 'lines' => [['qty_kg' => 80] + $line]])->assertOk()->assertJsonPath('data.amount', 640)->assertJsonPath('data.rev_no', 'R1');
        $this->as()->postJson('/api/v1/finishing/sc-bills/HM-INV-77/status?party=SUP-1', ['to' => 'COMPLETED'])->assertOk();
        $this->as()->putJson('/api/v1/finishing/sc-bills/HM-INV-77?party=SUP-1', $b + ['lines' => [$line]])->assertUnprocessable()->assertJsonPath('errors.bill_no.0', 'HM-INV-77 is completed or cancelled and stands.');
        $this->as()->postJson('/api/v1/finishing/sc-bills/HM-INV-77/status?party=SUP-1', ['to' => 'CANCELLED'])->assertUnprocessable();
        $this->as()->getJson('/api/v1/finishing/sc-bills')->assertJsonPath('data.0.status', 'COMPLETED')->assertJsonPath('data.0.qty_kg', 80);
    }

    public function test_who_may_send_fabric_out(): void
    {
        $store = $this->token('STORE_MANAGER', 'store@delta.test');
        $this->withToken($store)->postJson('/api/v1/finishing/sc-challans', ['challan_date' => '2026-11-06', 'supplier_id' => $this->party, 'store_id' => $this->store, 'lines' => [['lot_no' => 'L', 'process_id' => $this->process['DF-ST'], 'qty_kg' => 5]]])->assertCreated();
        $chief = $this->token('LINE_CHIEF', 'chief@delta.test');
        $this->withToken($chief)->getJson('/api/v1/finishing/sc-balance')->assertOk();
        $this->withToken($chief)->postJson('/api/v1/finishing/sc-challans/FSC-CH-2026110001/post')->assertForbidden();
        $technician = $this->token('TECHNICIAN', 'tech@delta.test');
        $this->withToken($technician)->getJson('/api/v1/finishing/sc-challans')->assertForbidden();
    }

    /** A dyed batch received onto the floor (100 kg, 6 rolls); its card id. */
    private function card(string $no, string $colour): string
    {
        $batch = (string) Str::ulid();
        DB::table('colours')->insert(['id' => $cid = (string) Str::ulid(), 'company_id' => $this->delta->id, 'name' => $colour, 'colour_type' => 'SOLID', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('dye_batches')->insert(['id' => $batch, 'company_id' => $this->delta->id, 'batch_no' => $no, 'batch_date' => '2026-11-01', 'scope' => 'BULK', 'source' => 'MANUAL', 'colour_id' => $cid, 'plan_qty' => 100, 'card_qty' => 100, 'status' => 'OPEN', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('dye_productions')->insert(['id' => (string) Str::ulid(), 'company_id' => $this->delta->id, 'batch_id' => $batch, 'production_date' => '2026-11-04', 'liquor_ratio' => '1:8', 'created_at' => now(), 'updated_at' => now()]);

        return $this->as()->postJson('/api/v1/erp/fin-batch-receipts', ['receive_date' => '2026-11-05', 'batch_id' => $batch, 'dia_type' => 'OPEN', 'recv_qty_kg' => 100, 'recv_rolls' => 6])->assertCreated()->json('data.id');
    }

    /** A posted challan; its number. @param list<array<string, mixed>> $lines */
    private function challan(array $lines, ?string $party = null): string
    {
        $no = $this->as()->postJson('/api/v1/finishing/sc-challans', ['challan_date' => '2026-11-06', 'supplier_id' => $party ?? $this->party, 'store_id' => $this->store, 'lines' => $lines])->assertCreated()->json('data.challan_no');
        $this->as()->postJson("/api/v1/finishing/sc-challans/{$no}/post")->assertOk();

        return $no;
    }

    /** @return array<string, mixed> */
    private function opts(): array
    {
        return $this->as()->getJson('/api/v1/finishing/sc/options')->assertOk()->json('data');
    }

    private function token(string $role, string $email): string
    {
        return app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, $role, $email), $this->delta->id, 'Test token')['plain'];
    }

    private function as(): static
    {
        return $this->withToken(app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'Test token')['plain']);
    }
}
