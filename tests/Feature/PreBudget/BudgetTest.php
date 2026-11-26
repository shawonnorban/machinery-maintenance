<?php

declare(strict_types=1);

namespace Tests\Feature\PreBudget;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\BuyerUserPermission;
use App\Modules\Notification\Models\Notification;
use App\Modules\PreBudget\Models\PbRequisition;
use App\Shared\Scopes\TenantScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Pre-Budget → Create Budget (AnnotechERP 33/34, plan 2026-11-19): a budget
 * from completed requisitions of one buyer, numbered at once; Knit lines by
 * Yarn Count + Composition and Woven lines by Style + Composition +
 * Fabrication worked out from its requisitions (so a requisition changed
 * later changes it); Need to Purchase up to a whole kg; PI Qty, supplier
 * prices, the lowest price as the default, shortage Purchase, FOB and costs;
 * Add / Remove Style; one budget per requisition; Delete; who may.
 */
class BudgetTest extends TestCase
{
    use PreBudgetFixture;
    use RefreshDatabase;

    /** @return list<string> three requisitions: two knit of one yarn, one woven */
    private function requisitions(): array
    {
        $woven = ['entry_type' => 'Woven', 'style' => 'W-100', 'fabric_mill' => 'Huatai Textile', 'width' => '58 inch'] + $this->knit();

        return collect($this->as()->postJson('/api/v1/pre-budget/requisitions', ['rows' => [
            ['values' => $this->knit()], ['values' => ['style' => '2261513', 'cad_consumption' => 4.2] + $this->knit()], ['values' => $woven],
        ]])->assertCreated()->json('data.rows'))->pluck('id')->all();
    }

    public function test_a_budget_is_made_from_one_buyers_requisitions_and_works_its_lines_out(): void
    {
        $ids = $this->requisitions();
        $this->assertCount(3, $this->as()->getJson('/api/v1/pre-budget/budgets/candidates')->assertOk()->json('data.rows'));

        $no = $this->as()->postJson('/api/v1/pre-budget/budgets', ['requisition_ids' => $ids])->assertCreated()->json('data.budget_no');
        $this->assertMatchesRegularExpression('/^PB-CNA-\d{4}-0001$/', $no);
        // In a budget now: no longer free, and not in a second one.
        $this->assertCount(0, $this->as()->getJson('/api/v1/pre-budget/budgets/candidates')->json('data.rows'));
        $this->as()->postJson('/api/v1/pre-budget/budgets', ['requisition_ids' => [$ids[0]]])->assertUnprocessable()->assertJsonValidationErrors('requisition_ids');

        $b = $this->as()->getJson("/api/v1/pre-budget/budgets/{$no}")->assertOk()->json('data');
        $this->assertSame('DRAFT', $b['status']);
        $this->assertSame(['NCL'], $b['factories']);
        // Two knit rows of one yarn: one line. 982.5 + (4.2 × 1.1 / 12 × 2100 + 20 = 828.5) kg.
        $yarn = $b['sections']['YARN']['lines'];
        $this->assertCount(1, $yarn);
        $this->assertSame('30/1 | 100% Cotton', $yarn[0]['key']);
        $this->assertEquals(1811, $yarn[0]['total_req']);
        $this->assertEquals(1811, $yarn[0]['need_to_purchase']);
        $woven = $b['sections']['WOVEN']['lines'];
        $this->assertCount(1, $woven);
        $this->assertSame('W-100 |  | Single Jersey 160 GSM', $woven[0]['key']); // no fabric composition on it
        $this->assertEquals(983, $woven[0]['need_to_purchase']); // 982.5 yds, up to a whole yard
        $this->assertEquals(21000, $b['fob']['gross']);
        $this->assertSame([['order_type' => 'Confirmed PO', 'dept' => 'Mens', 'item' => 'T-Shirt', 'no_of_style' => 3, 'order_qty' => 6000]], $b['item_summary']['rows']);

        // A requisition changed while the budget is a draft: the budget follows.
        $r = PbRequisition::query()->whereKey($ids[1])->first();
        $this->as()->patchJson('/api/v1/pre-budget/requisitions', ['rows' => [['id' => $r->id, 'version' => $r->version, 'values' => ['sample_qty' => 0]]]])->assertOk();
        $this->assertEquals(1791, $this->as()->getJson("/api/v1/pre-budget/budgets/{$no}")->json('data.sections.YARN.lines.0.total_req'));

        // One buyer per budget.
        Buyer::query()->create(['company_id' => $this->delta->id, 'code' => 'HM', 'name' => 'H&M', 'short_name' => 'H&M', 'pi_address' => 'EU', 'registered_from' => '2026-01-01', 'active' => true]);
        $hm = $this->as()->postJson('/api/v1/pre-budget/requisitions', ['rows' => [['values' => ['buyer' => 'H&M', 'style' => 'HM-1'] + $this->knit()], ['values' => ['style' => 'C-9'] + $this->knit()]]])->json('data.rows');
        $this->as()->postJson('/api/v1/pre-budget/budgets', ['requisition_ids' => array_column($hm, 'id')])->assertUnprocessable()->assertJsonValidationErrors('requisition_ids');
        $this->assertMatchesRegularExpression('/^PB-HM-\d{4}-0002$/', $this->as()->postJson('/api/v1/pre-budget/budgets', ['requisition_ids' => [$hm[0]['id']]])->assertCreated()->json('data.budget_no'));
    }

    public function test_order_color_holds_colours_with_sizes_and_each_budget_has_an_order_overview(): void
    {
        $row = fn (array $v) => ['id' => strtolower((string) \Illuminate\Support\Str::ulid()), 'company_id' => $this->delta->id, 'created_at' => now(), 'updated_at' => now()] + $v;
        // The master's own order left unset (as on Norban's): still worn order, numbers after letters.
        foreach (['XL', 'L', 'M', 'S'] as $size) {
            DB::table('sizes')->insert($row(['size_group' => 'LETTER', 'code' => $size, 'name' => $size, 'display_order' => 0, 'active' => true]));
        }
        DB::table('sizes')->insert($row(['size_group' => 'NUMERIC', 'code' => '30', 'name' => '30', 'display_order' => 0, 'active' => true]));
        DB::table('colours')->insert($row(['code' => 'NVY', 'colour_type' => 'SOLID', 'name' => 'Navy', 'active' => true]));
        $meta = $this->as()->getJson('/api/v1/pre-budget/meta')->assertOk()->json('data');
        $this->assertSame('colorsize', collect($meta['columns'])->firstWhere('key', 'order_color')['type']);
        $this->assertSame(['S', 'M', 'L', 'XL', '30'], array_column($meta['sources']['sizes'], 'value'));

        // The colour is needed, the sizes are not; sizes come from the Size master, kept in its order; the Colour master's spelling.
        $rows = $this->as()->postJson('/api/v1/pre-budget/requisitions', ['rows' => [
            ['values' => ['order_color' => [['color' => 'navy', 'sizes' => ['XL', 'S', 'M']]]] + $this->knit()],
            ['values' => ['style' => 'S-2', 'order_color' => 'White (L, XL); Black'] + $this->knit()],
            ['values' => ['style' => 'S-3', 'order_color' => [['color' => 'Red', 'sizes' => []]]] + $this->knit()],
            // The first style again: another colour, another price — one line in the overview.
            ['values' => ['order_color' => 'Black (L)', 'fob_price' => 4.5] + $this->knit()],
        ]])->assertCreated()->json('data.rows');
        $this->assertSame([['color' => 'Navy', 'sizes' => ['S', 'M', 'XL']]], $rows[0]['values']['order_color']);
        $this->assertSame([['color' => 'White', 'sizes' => ['L', 'XL']], ['color' => 'Black', 'sizes' => []]], $rows[1]['values']['order_color']);
        $this->as()->postJson('/api/v1/pre-budget/requisitions', ['rows' => [['values' => ['style' => 'S-4', 'order_color' => [['color' => '', 'sizes' => ['S']]]] + $this->knit()]]])
            ->assertUnprocessable()->assertJsonValidationErrors('rows.0.values.order_color');
        $this->as()->postJson('/api/v1/pre-budget/requisitions', ['rows' => [['values' => ['style' => 'S-5', 'order_color' => [['color' => 'Navy', 'sizes' => ['XXXL']]]] + $this->knit()]]])
            ->assertUnprocessable()->assertJsonValidationErrors('rows.0.values.order_color');

        // The header filter lists each colour; filtering by one finds the rows holding it.
        $this->assertSame(['Black', 'Navy', 'Red', 'White'], collect($this->as()->getJson('/api/v1/pre-budget/requisitions/distinct?column=order_color')->assertOk()->json('data'))->pluck('value')->all());
        $this->assertCount(1, $this->as()->getJson('/api/v1/pre-budget/requisitions?f[order_color][]=White')->assertOk()->json('data.rows'));

        // The budget's Order Overview: one line per style, every colour and size it has, quantities and values added up.
        $no = $this->as()->postJson('/api/v1/pre-budget/budgets', ['requisition_ids' => array_column($rows, 'id')])->assertCreated()->json('data.budget_no');
        $o = $this->as()->getJson("/api/v1/pre-budget/budgets/{$no}/overview")->assertOk()->json('data');
        $this->assertSame('Order Overview of C&A - Clockhouse - SS-26 - Norban Composite', $o['title']);
        $this->assertSame([1, 2, 3], array_column($o['rows'], 'sl'));
        $this->assertSame(['2261582', 'S-2', 'S-3'], array_column($o['rows'], 'style'));
        $this->assertSame([2, 1, 1], array_column($o['rows'], 'requisitions'));
        $this->assertSame(['S, M, L, XL', 'L, XL', null], array_column($o['rows'], 'size_range'));
        $this->assertSame(['Navy, Black', 'White, Black', 'Red'], array_column($o['rows'], 'order_color'));
        $first = $o['rows'][0];
        $this->assertSame(['C&A', 'Clockhouse', 'Mens', 'T-Shirt'], [$first['buyer'], $first['brand'], $first['dept'], $first['item']]);
        $this->assertNotNull($first['artwork_url']);
        $this->assertEquals(2 * $o['rows'][1]['order_qty'], $first['order_qty']);
        $this->assertEquals(round($first['fob_value'] / $first['order_qty'], 4), $first['fob_price']); // two prices: value over quantity
        $this->assertEquals(3.5, $o['rows'][1]['fob_price']);
        $this->assertEquals(array_sum(array_column($o['rows'], 'order_qty')), $o['totals']['order_qty']);
        $this->assertEquals(array_sum(array_column($o['rows'], 'fob_value')), $o['totals']['fob_value']);
        $this->as()->getJson('/api/v1/pre-budget/budgets/PB-NOPE-0000-0001/overview')->assertNotFound();

        // Excel: the title over the table, a line per style, the totals.
        $file = $this->as()->get("/api/v1/pre-budget/budgets/{$no}/overview/export")->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'ov').'.xlsx';
        file_put_contents($path, $file->streamedContent());
        $ws = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
        $this->assertSame($o['title'], $ws->getCell('A1')->getValue());
        $this->assertSame(['SL', 'Buyer', 'Brand'], [$ws->getCell('A4')->getValue(), $ws->getCell('B4')->getValue(), $ws->getCell('C4')->getValue()]);
        $this->assertSame('2261582', (string) $ws->getCell('E5')->getValue());
        $this->assertSame('Navy, Black', $ws->getCell('J5')->getValue());
        $this->assertSame('S, M, L, XL', $ws->getCell('K5')->getValue());
        $this->assertSame('Total', $ws->getCell('A8')->getValue());
        $this->assertEquals($o['totals']['fob_value'], $ws->getCell('N8')->getValue());
    }

    public function test_the_dashboard_counts_requisitions_budgets_and_the_work_still_open(): void
    {
        $ids = $this->requisitions();
        $d = $this->as()->getJson('/api/v1/pre-budget/dashboard')->assertOk()->json('data');
        $this->assertSame(3, $d['totals']['requisitions']);
        $this->assertSame([2, 1], [$d['totals']['knit'], $d['totals']['woven']]);
        $this->assertEquals(6000, $d['totals']['order_qty']);
        $this->assertEquals(21000, $d['totals']['fob_value']);
        $this->assertSame(0, $d['totals']['budgets']);
        $this->assertSame(3, $d['pending']['free']);
        $this->assertSame([['buyer' => 'C&A', 'requisitions' => 3]], array_map(fn ($b) => ['buyer' => $b['buyer'], 'requisitions' => $b['requisitions']], $d['by_buyer']));
        $this->assertSame('REQUISITION', $d['recent'][0]['subject']);

        $this->as()->postJson('/api/v1/pre-budget/budgets', ['requisition_ids' => $ids])->assertCreated();
        $d = $this->as()->getJson('/api/v1/pre-budget/dashboard')->assertOk()->json('data');
        $this->assertSame(1, $d['totals']['budgets']);
        $this->assertSame(['DRAFT' => 1, 'PENDING' => 0, 'APPROVED' => 0, 'RETURNED' => 0], $d['budget_status']);
        $this->assertSame(['free' => 0, 'costing' => 1, 'stock' => 1, 'accessories' => 1], $d['pending']);
        $this->assertSame(['BUDGET', 'CREATE'], [$d['recent'][0]['subject'], $d['recent'][0]['kind']]);
    }

    public function test_the_team_and_the_store_hear_of_requisitions_budgets_and_the_mornings_work(): void
    {
        $notes = fn (string $userId, string $event) => Notification::withoutGlobalScope(TenantScope::class)->where('user_id', $userId)->where('event_type', $event)->orderBy('created_at')->orderBy('id')->get();
        $merch = TenantFixture::user($this->delta, 'VIEWER', 'merch@delta.test');
        BuyerUserPermission::query()->create(['company_id' => $this->delta->id, 'kind' => 'MERCHANDISING', 'user_id' => $merch->id, 'buyer_id' => Buyer::query()->where('code', 'CNA')->value('id')]);
        $store = TenantFixture::user($this->delta, 'STOREKEEPER', 'store@delta.test');

        // Completed requisitions: the buyer's merchandiser hears once, and is sent to Create Budget.
        $ids = $this->requisitions();
        $req = $notes($merch->id, 'PB_REQUISITIONS_CHANGED');
        $this->assertCount(1, $req);
        $this->assertSame(3, $req[0]->data_json['n']);
        $this->assertStringContainsString('completed 3 requisition(s)', $req[0]->title);
        $this->assertSame('/pre-budget/create-budget', $req[0]->action_url);
        $this->assertCount(0, $notes($this->owner->id, 'PB_REQUISITIONS_CHANGED'));
        $this->assertCount(0, $notes($store->id, 'PB_REQUISITIONS_CHANGED'));

        // A budget made: the team hears it; the Store is asked for its Stock Yarn Use.
        $no = $this->as()->postJson('/api/v1/pre-budget/budgets', ['requisition_ids' => $ids])->assertCreated()->json('data.budget_no');
        $made = $notes($merch->id, 'PB_BUDGET_CHANGED');
        $this->assertCount(1, $made);
        $this->assertSame($no, $made[0]->data_json['budget']);
        $this->assertSame("/pre-budget/create-budget?budget={$no}", $made[0]->action_url);
        $ask = $notes($store->id, 'PB_STOCK_NEEDED');
        $this->assertCount(1, $ask);
        $this->assertSame('WARNING', $ask[0]->severity);
        $this->assertSame("/pre-budget/stock-yarn-use?budget={$no}", $ask[0]->action_url);
        $this->assertCount(0, $notes($merch->id, 'PB_STOCK_NEEDED'));
        $this->assertCount(0, $notes($this->owner->id, 'PB_BUDGET_CHANGED'));

        // The morning: costing, stock yarn use and accessories all still to do.
        $this->artisan('erp:notify-pending-work', ['--company' => 'DAL'])->assertSuccessful();
        $work = $notes($merch->id, 'PB_PENDING_WORK');
        $this->assertCount(1, $work);
        $this->assertSame([1, 1, 1], [$work[0]->data_json['costing'], $work[0]->data_json['stock'], $work[0]->data_json['accessories']]);
        $this->assertSame([0, 1, 0], [$notes($store->id, 'PB_PENDING_WORK')[0]->data_json['costing'], $notes($store->id, 'PB_PENDING_WORK')[0]->data_json['stock'], $notes($store->id, 'PB_PENDING_WORK')[0]->data_json['accessories']]);
    }

    public function test_what_is_typed_on_a_line_and_the_fob_and_costs(): void
    {
        $no = $this->as()->postJson('/api/v1/pre-budget/budgets', ['requisition_ids' => $this->requisitions()])->json('data.budget_no');
        $b = $this->as()->getJson("/api/v1/pre-budget/budgets/{$no}")->json('data');
        $key = $b['sections']['YARN']['lines'][0]['key'];

        $this->as()->patchJson("/api/v1/pre-budget/budgets/{$no}", ['version' => $b['version'], 'suppliers' => ['YARN' => ['Not a supplier']]])->assertUnprocessable();
        $saved = $this->as()->patchJson("/api/v1/pre-budget/budgets/{$no}", [
            'version' => $b['version'], 'contract_no' => 'NCL/C&A/033', 'discount_percent' => 3, 'suppliers' => ['YARN' => ['Huatai Textile']],
            'lines' => [['section' => 'YARN', 'key' => $key, 'pi_qty' => 1800, 'supplier_prices' => ['Huatai Textile' => 3.2], 'shortage_action' => 'PURCHASE', 'remarks' => 'LC open']],
        ])->assertOk()->json('data');
        $line = $saved['sections']['YARN']['lines'][0];
        $this->assertEquals(3.2, $line['price']);          // no price typed: the lowest supplier price
        $this->assertEquals(5760, $line['value']);          // 1800 kg × 3.20
        $this->assertEquals(-11, $line['excess']);          // 1800 − 1811
        $this->assertEquals(35.2, $line['short_value']);    // the shortage bought short: 11 × 3.20
        $this->assertEquals(5795.2, $saved['sections']['YARN']['totals']['grand_value']);
        $this->assertEquals(5795.2, $saved['costs']['yarn']);
        $this->assertEquals(630, $saved['fob']['discount']); // 3 % of 21,000
        $this->assertEquals(20370, $saved['fob']['net']);
        $this->assertSame('NCL/C&A/033', $saved['contract_no']);

        // A price typed wins; a saved budget read with an old version is refused.
        $this->as()->patchJson("/api/v1/pre-budget/budgets/{$no}", ['version' => $saved['version'], 'lines' => [['section' => 'YARN', 'key' => $key, 'pi_qty' => 1800, 'price' => 3.6, 'supplier_prices' => ['Huatai Textile' => 3.2], 'shortage_action' => 'PURCHASE']]])
            ->assertOk()->assertJsonPath('data.sections.YARN.lines.0.value', 6480);
        $this->as()->patchJson("/api/v1/pre-budget/budgets/{$no}", ['version' => $saved['version'], 'contract_no' => 'x'])->assertStatus(409);
        $this->assertSame(3, DB::table('pb_changes')->where('subject', 'BUDGET')->distinct('batch')->count('batch')); // created, two edits
    }

    public function test_styles_come_and_go_a_requisition_in_a_budget_stays_and_a_draft_budget_is_deleted(): void
    {
        $ids = $this->requisitions();
        $no = $this->as()->postJson('/api/v1/pre-budget/budgets', ['requisition_ids' => [$ids[0]]])->json('data.budget_no');

        $this->assertCount(2, $this->as()->getJson("/api/v1/pre-budget/budgets/candidates?budget={$no}")->json('data.rows'));
        $this->as()->postJson("/api/v1/pre-budget/budgets/{$no}/styles", ['requisition_ids' => [$ids[1], $ids[2]]])->assertOk()->assertJsonCount(3, 'data.requisitions');
        $this->as()->deleteJson("/api/v1/pre-budget/budgets/{$no}/styles", ['requisition_ids' => $ids])->assertUnprocessable(); // one at least stays
        $this->as()->deleteJson("/api/v1/pre-budget/budgets/{$no}/styles", ['requisition_ids' => [$ids[2]]])->assertOk()->assertJsonCount(2, 'data.requisitions');
        // A requisition in a budget is not deleted; a free one is.
        $this->as()->deleteJson('/api/v1/pre-budget/requisitions', ['ids' => [$ids[0]]])->assertUnprocessable()->assertJsonValidationErrors('ids');

        $this->as()->deleteJson("/api/v1/pre-budget/budgets/{$no}")->assertOk();
        $this->as()->getJson("/api/v1/pre-budget/budgets/{$no}")->assertNotFound();
        $this->assertCount(3, $this->as()->getJson('/api/v1/pre-budget/budgets/candidates')->json('data.rows'));
        // The next budget gets the next number, not the deleted one's.
        $this->assertStringEndsWith('-0002', $this->as()->postJson('/api/v1/pre-budget/budgets', ['requisition_ids' => [$ids[0]]])->json('data.budget_no'));

        $store = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'STOREKEEPER', 'store@delta.test'), $this->delta->id, 'Test')['plain'];
        $this->flushHeaders();
        $this->withToken($store)->getJson('/api/v1/pre-budget/budgets')->assertForbidden();
        $this->withToken($store)->postJson('/api/v1/pre-budget/budgets', ['requisition_ids' => [$ids[1]]])->assertForbidden();
    }
}
