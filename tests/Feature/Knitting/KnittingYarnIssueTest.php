<?php

declare(strict_types=1);

namespace Tests\Feature\Knitting;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Knitting\Models\KnitYarnCount;
use App\Modules\Knitting\Models\YarnLot;
use App\Modules\Knitting\Support\YarnStore;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\FiberComposition;
use App\Modules\Settings\Models\Currency;
use App\Modules\Tenancy\Models\Company;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Knit Production → Yarn Store (AnnotechERP 15/03), the yarn going out: a
 * store requisition issued against off a lot (never past what a line asked
 * for, nor what the lot holds), its lines then locked; unused yarn returned
 * to the store, credited only when accepted and never more than went out;
 * the requisition closed; a party challan issued, rejected back and
 * accepted; a replacement to the spinner (its own lots only) submitted,
 * cancelled and accepted — and the month grid and exceptions reading it.
 */
class KnittingYarnIssueTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private string $store;

    private string $cotton;

    private string $count;

    private string $count2;

    private string $spinner;

    private string $spinnerB;

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
        $this->count2 = KnitYarnCount::query()->create(['count_no' => 'YC-24-1', 'count_system' => 'Ne', 'description' => '24/1', 'count_value' => 24, 'is_single' => true, 'display_order' => 2, 'active' => true])->id;
        $this->spinner = MaterialSupplier::query()->create(['code' => 'SUP-01', 'name' => 'Square Spinning', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
        $this->spinnerB = MaterialSupplier::query()->create(['code' => 'SUP-02', 'name' => 'Thermax Yarn', 'supplier_type' => 'LOCAL_NON_EPZ', 'active' => true])->id;
    }

    public function test_requisition_issue_return_party_challan_and_replacement(): void
    {
        $month = CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
        $period = $month->format('Y-m');
        $ym = $month->format('Ym');
        $day = fn (int $d) => $month->setDay($d)->toDateString();
        $usd = Currency::query()->create(['company_id' => $this->delta->id, 'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'exchange_rate' => 119.5, 'active' => true])->id;
        $yarn = ['composition_id' => $this->cotton, 'yarn_count_id' => $this->count, 'colour' => 'Natural'];

        // Stock to issue from: Square's lot L-1 (1,000 kg) and Thermax's L-2 (200 kg).
        $ob = $this->as()->postJson('/api/v1/knitting/ystore/obs', ['ob_date' => $day(1), 'store_id' => $this->store, 'currency_id' => $usd, 'is_buffer' => true, 'lines' => [
            $yarn + ['source' => 'LOCAL', 'lot_no' => 'L-1', 'supplier_id' => $this->spinner, 'total_qty' => 1000, 'cost_price' => 3],
            $yarn + ['source' => 'LOCAL', 'lot_no' => 'L-2', 'supplier_id' => $this->spinnerB, 'total_qty' => 200, 'cost_price' => 3.2],
        ]])->assertCreated()->json('data.ob_no');
        $this->as()->postJson("/api/v1/knitting/ystore/obs/{$ob}/finalize")->assertOk();
        $l1 = YarnLot::query()->where('lot_no', 'L-1')->value('id');
        $l2 = YarnLot::query()->where('lot_no', 'L-2')->value('id');

        // The requisition: a type is required, and at least one line.
        $req = ['req_date' => $day(3), 'req_type' => 'KNIT_BULK', 'store_id' => $this->store, 'program_no' => 'KP-501',
            'lines' => [$yarn + ['uom' => 'kg', 'req_qty' => 600, 'kp_alloc' => 100], ['composition_id' => $this->cotton, 'yarn_count_id' => $this->count2, 'uom' => 'kg', 'req_qty' => 50]]];
        $this->as()->postJson('/api/v1/knitting/ystore/issue-reqs', ['req_type' => null] + $req)->assertUnprocessable()->assertJsonPath('errors.req_type.0', 'Requisition Type is required.');
        $this->as()->postJson('/api/v1/knitting/ystore/issue-reqs', ['lines' => []] + $req)->assertUnprocessable()
            ->assertJsonPath('errors.lines.0', 'A requisition with no lines posts nothing, so at least one is required before this can be saved.');
        $yir = $this->as()->postJson('/api/v1/knitting/ystore/issue-reqs', $req)->assertCreated()->json('data');
        $this->assertEquals(["YIR-{$ym}0001", 'OPEN', true], [$yir['req_no'], $yir['status'], $yir['editable']]);
        $no = $yir['req_no'];
        $list = $this->as()->getJson('/api/v1/knitting/ystore/issue-reqs?type=KNIT_BULK')->assertOk()->json('data.0');
        $this->assertEquals([650.0, 0.0, 650.0], [$list['req_qty'], $list['issued_qty'], $list['balance']]);

        // The issue desk offers each line the store's lots of its yarn.
        $source = $this->as()->getJson("/api/v1/knitting/ystore/issue-reqs/{$no}/source")->assertOk()->json('data.lines');
        $this->assertEquals([[$l1, $l2], []], array_map(fn ($l) => array_column($l['lots'], 'lot_id'), $source));
        [$line1, $line2] = array_column($source, 'id');
        $issue = ['issue_date' => $day(4), 'req_no' => $no, 'gate_pass_no' => 'GP-88', 'lines' => [['req_line_id' => $line1, 'lot_id' => $l1, 'qty' => 400, 'cones' => 320]]];
        $this->as()->postJson('/api/v1/knitting/ystore/issues', ['lines' => [['qty' => 700] + $issue['lines'][0]]] + $issue)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.qty' => 'Line 1 takes the requisition line past what it asked for (600 left).']);
        $this->as()->postJson('/api/v1/knitting/ystore/issues', ['lines' => [['req_line_id' => $line2] + $issue['lines'][0]]] + $issue)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.lot_id' => "Line 1's lot is not the yarn its requisition line asks for."]);
        $yis = $this->as()->postJson('/api/v1/knitting/ystore/issues', $issue)->assertCreated()->json('data.issue_no');
        $this->assertEquals(["YIS-{$ym}0001", 600.0, 'PARTLY_ISSUED'], [$yis, YarnStore::balance($l1), DB::table('knit_yarn_issue_reqs')->value('status')]);
        // With yarn out, the requisition's lines are locked and it cannot be cancelled.
        $this->as()->putJson("/api/v1/knitting/ystore/issue-reqs/{$no}", $req)->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->as()->postJson("/api/v1/knitting/ystore/issue-reqs/{$no}/cancel")->assertUnprocessable()->assertJsonPath('errors.status.0', "{$no} is Partly Issued, so it cannot be cancelled.");
        // The issue is corrected in place: the lot and the requisition follow.
        $this->as()->putJson("/api/v1/knitting/ystore/issues/{$yis}", ['lines' => [['qty' => 450] + $issue['lines'][0]]] + $issue)->assertOk();
        $this->as()->postJson('/api/v1/knitting/ystore/issues', ['issue_date' => $day(5), 'req_no' => $no, 'lines' => [['req_line_id' => $line1, 'lot_id' => $l1, 'qty' => 150]]])->assertCreated();
        $this->assertEquals([400.0, [600.0, 0.0]], [YarnStore::balance($l1), DB::table('knit_yarn_issue_req_lines')->orderBy('sort_order')->pluck('issued_qty')->map(fn ($v) => (float) $v)->all()]);

        // Unused yarn back: one line against its issue line (30 sent, 28 counted), one unmatched.
        $issueLine = $this->as()->getJson("/api/v1/knitting/ystore/issue-returns/source/{$no}")->assertOk()->json('data.issue_lines.0');
        $this->assertEquals([450.0, 0.0, 450.0], [$issueLine['issued'], $issueLine['returned'], $issueLine['out']]);
        $ret = ['return_date' => $day(8), 'req_type' => 'KNIT_BULK', 'store_id' => $this->store, 'req_no' => $no, 'return_by' => 'Floor 2 — Rahim',
            'lines' => [['issue_line_id' => $issueLine['issue_line_id'], 'ret_qty' => 30, 'recv_qty' => 28], ['composition_id' => $this->cotton, 'yarn_count_id' => $this->count2, 'lot_no' => 'mix', 'ret_qty' => 5]]];
        $this->as()->postJson('/api/v1/knitting/ystore/issue-returns', ['return_by' => ''] + $ret)->assertUnprocessable()->assertJsonPath('errors.return_by.0', 'Return By is required.');
        $yirt = $this->as()->postJson('/api/v1/knitting/ystore/issue-returns', $ret)->assertCreated()->json('data');
        $this->assertEquals(["YIRT-{$ym}0001", 'DRAFT', 'L-1'], [$yirt['return_no'], $yirt['status'], $yirt['lines'][0]['lot_no']]);
        $rno = $yirt['return_no'];
        $this->as()->postJson("/api/v1/knitting/ystore/issue-returns/{$rno}/accept")->assertUnprocessable()->assertJsonPath('errors.status.0', "{$rno} is Draft, so it cannot be accepted.");
        $this->assertEquals(400.0, YarnStore::balance($l1));
        $this->as()->postJson("/api/v1/knitting/ystore/issue-returns/{$rno}/issue")->assertOk()->assertJsonPath('data.status', 'ISSUED');
        $this->as()->postJson("/api/v1/knitting/ystore/issue-returns/{$rno}/accept")->assertOk()->assertJsonPath('data.status', 'ACCEPTED');
        $mix = YarnLot::query()->where('lot_no', 'MIX')->firstOrFail();
        $this->assertEquals([428.0, 5.0, true, 572.0, 'PARTLY_ISSUED'], [YarnStore::balance($l1), YarnStore::balance($mix->id), $mix->is_buffer,
            (float) DB::table('knit_yarn_issue_req_lines')->where('id', $line1)->value('issued_qty'), DB::table('knit_yarn_issue_reqs')->value('status')]);
        $this->as()->putJson("/api/v1/knitting/ystore/issue-returns/{$rno}", $ret)->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->as()->putJson("/api/v1/knitting/ystore/issues/{$yis}", $issue)->assertUnprocessable()->assertJsonValidationErrors('issue_no');
        // Never more back than went out on the line (450 − 28 = 422).
        $over = $this->as()->postJson('/api/v1/knitting/ystore/issue-returns', ['lines' => [['issue_line_id' => $issueLine['issue_line_id'], 'ret_qty' => 500]]] + $ret)->json('data.return_no');
        $this->as()->postJson("/api/v1/knitting/ystore/issue-returns/{$over}/issue")->assertOk();
        $this->as()->postJson("/api/v1/knitting/ystore/issue-returns/{$over}/accept")->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.recv_qty' => 'Line 1 brings back more than went out on its issue line (422).']);
        $this->as()->postJson("/api/v1/knitting/ystore/issue-returns/{$over}/cancel")->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        $this->assertEquals([['ACCEPTED', 35.0, 33.0], ['CANCELLED', 500.0, 500.0]], array_map(fn ($r) => [$r['status'], $r['ret'], $r['recv']], array_reverse($this->as()->getJson('/api/v1/knitting/ystore/issue-returns')->json('data'))));

        // Closing is a decision: nothing more can be issued against it.
        $this->as()->postJson("/api/v1/knitting/ystore/issue-reqs/{$no}/close")->assertOk()->assertJsonPath('data.status', 'CLOSED');
        $this->as()->postJson('/api/v1/knitting/ystore/issues', ['issue_date' => $day(9), 'req_no' => $no, 'lines' => [['req_line_id' => $line1, 'lot_id' => $l1, 'qty' => 10]]])
            ->assertUnprocessable()->assertJsonPath('errors.req_no.0', 'Pick a requisition that is still open.');

        // Party challan: a draft moves nothing; Issued takes the yarn out, Rejected brings it back.
        $pc = ['challan_date' => $day(10), 'party_id' => $this->spinnerB, 'store_id' => $this->store, 'vehicle_no' => 'DM-TA-11', 'lines' => [['lot_id' => $l1, 'qty' => 100, 'rate' => 0.5, 'cones' => 80]]];
        $this->as()->postJson('/api/v1/knitting/ystore/party-challans', ['lines' => [['qty' => 500] + $pc['lines'][0]]] + $pc)->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.qty' => 'Line 1 takes more than the lot holds (428).']);
        $ypc = $this->as()->postJson('/api/v1/knitting/ystore/party-challans', $pc)->assertCreated()->json('data.challan_no');
        $this->assertEquals(["YPC-{$ym}0001", 428.0], [$ypc, YarnStore::balance($l1)]);
        $this->as()->postJson("/api/v1/knitting/ystore/party-challans/{$ypc}/issue")->assertOk();
        $this->assertEquals(328.0, YarnStore::balance($l1));
        $this->as()->putJson("/api/v1/knitting/ystore/party-challans/{$ypc}", $pc)->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->as()->postJson("/api/v1/knitting/ystore/party-challans/{$ypc}/reject")->assertOk()->assertJsonPath('data.status', 'REJECTED');
        $this->assertEquals(428.0, YarnStore::balance($l1));
        $second = $this->as()->postJson('/api/v1/knitting/ystore/party-challans', ['lines' => [['qty' => 50] + $pc['lines'][0]]] + $pc)->json('data.challan_no');
        $this->as()->postJson("/api/v1/knitting/ystore/party-challans/{$second}/issue")->assertOk();
        $this->as()->postJson("/api/v1/knitting/ystore/party-challans/{$second}/accept")->assertOk()->assertJsonPath('data.status', 'ACCEPTED');
        $this->as()->postJson("/api/v1/knitting/ystore/party-challans/{$second}/cancel")->assertUnprocessable()->assertJsonPath('errors.status.0', "{$second} is Accepted, so it cannot be cancelled.");
        $this->assertEquals(378.0, YarnStore::balance($l1));

        // Replacement: only the spinner's own lot goes back to it; Submit takes it out, Cancel brings it back.
        $rp = ['challan_date' => $day(12), 'store_id' => $this->store, 'supplier_id' => $this->spinner, 'return_type' => 'CLAIM_YARN_TEST', 'lc_no' => 'LC-77', 'lot_ref' => 'l-2',
            'lines' => [['lot_id' => $l2, 'cartons' => 2, 'qty' => 20]]];
        $this->as()->postJson('/api/v1/knitting/ystore/replacements', ['lines' => []] + $rp)->assertUnprocessable()->assertJsonPath('errors.lines.0', 'A challan with no yarn on it sends the spinner an empty lorry.');
        $this->as()->postJson('/api/v1/knitting/ystore/replacements', $rp)->assertUnprocessable()->assertJsonValidationErrors(['lines.0.lot_id' => "Line 1's lot came from another supplier."]);
        $yrp = $this->as()->postJson('/api/v1/knitting/ystore/replacements', ['supplier_id' => $this->spinnerB] + $rp)->assertCreated()->json('data');
        $this->assertEquals(["YRP-{$ym}0001", 'L-2', 'DRAFT'], [$yrp['challan_no'], $yrp['lot_ref'], $yrp['status']]);
        $this->as()->postJson("/api/v1/knitting/ystore/replacements/{$yrp['challan_no']}/submit")->assertOk();
        $this->assertEquals(180.0, YarnStore::balance($l2));
        $this->as()->postJson("/api/v1/knitting/ystore/replacements/{$yrp['challan_no']}/cancel")->assertOk();
        $this->assertEquals(200.0, YarnStore::balance($l2));
        $again = $this->as()->postJson('/api/v1/knitting/ystore/replacements', ['supplier_id' => $this->spinnerB] + $rp)->json('data.challan_no');
        $this->as()->postJson("/api/v1/knitting/ystore/replacements/{$again}/submit")->assertOk();
        $this->as()->postJson("/api/v1/knitting/ystore/replacements/{$again}/accept")->assertOk()->assertJsonPath('data.status', 'ACCEPTED');
        $this->assertEquals([[2, 20.0, 'ACCEPTED']], array_map(fn ($r) => [$r['cartons'], $r['qty'], $r['status']], array_filter($this->as()->getJson('/api/v1/knitting/ystore/replacements')->json('data'), fn ($r) => $r['status'] !== 'CANCELLED')));

        // The month grid: issued is what went out less what came back; a replacement is a return.
        $rows = collect($this->as()->getJson("/api/v1/knitting/ystore/period?store=ST-YRN&period={$period}")->assertOk()->json('data.rows'))->keyBy('lot_no');
        $this->assertEquals([1000.0, 622.0, 378.0], [$rows['L-1']['receive'], $rows['L-1']['issued'], $rows['L-1']['closing']]);
        $this->assertEquals([20.0, 180.0], [$rows['L-2']['returned'], $rows['L-2']['closing']]);
        // A draft party challan stops the month closing.
        $this->as()->postJson('/api/v1/knitting/ystore/party-challans', ['lines' => [['qty' => 5] + $pc['lines'][0]]] + $pc)->assertCreated();
        $kinds = array_column($this->as()->getJson("/api/v1/knitting/ystore/closes/exceptions?period={$period}")->json('data.exceptions'), 'kind');
        $this->assertSame(['PARTY', 'GRID'], $kinds);

        // A line chief has no part in the yarn store.
        $chief = TenantFixture::user($this->delta, 'LINE_CHIEF', 'chief@delta.test');
        $this->as($chief)->getJson('/api/v1/knitting/ystore/issue-reqs')->assertForbidden();
    }

    private function as(?User $user = null): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user ?? $this->owner, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
