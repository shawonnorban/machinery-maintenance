<?php

declare(strict_types=1);

namespace Tests\Feature\PreBudget;

use App\Modules\Api\Actions\IssueApiToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Pre-Budget step 4 (AnnotechERP 33/34): Knit/Dying Costing — fabrication
 * rates in BDT over the Dollar Rate, style Embellishment and CM, the Badge
 * Cost rows (user, 2026-11-19) — and Stock Yarn Use with its lots, each
 * completed for the budget (and open again when the budget changes), their
 * costs and stock in the budget, Store filling the stock, and the
 * declaration.
 */
class BudgetWorkTest extends TestCase
{
    use PreBudgetFixture;
    use RefreshDatabase;

    /** @return array{0: string, 1: list<string>} a budget of two knit requisitions (one yarn) and one woven */
    private function budget(): array
    {
        $woven = ['entry_type' => 'Woven', 'style' => 'W-100', 'fabric_mill' => 'Huatai Textile', 'width' => '58 inch'] + $this->knit();
        $ids = collect($this->as()->postJson('/api/v1/pre-budget/requisitions', ['rows' => [
            ['values' => $this->knit()], ['values' => ['style' => '2261513', 'cad_consumption' => 4.2] + $this->knit()], ['values' => $woven],
        ]])->assertCreated()->json('data.rows'))->pluck('id')->all();

        return [$this->as()->postJson('/api/v1/pre-budget/budgets', ['requisition_ids' => $ids])->assertCreated()->json('data.budget_no'), $ids];
    }

    public function test_knit_dying_costing_with_badge_cost_goes_into_the_budget(): void
    {
        [$no, $ids] = $this->budget();
        $c = $this->as()->getJson("/api/v1/pre-budget/budgets/{$no}/costing")->assertOk()->json('data');
        $this->assertSame([['fabrication' => 'Single Jersey 160 GSM', 'qty' => 1811]], array_map(fn ($f) => ['fabrication' => $f['fabrication'], 'qty' => $f['qty']], $c['costing']['fabrics']));
        $this->assertSame(['2261513', '2261582', 'W-100'], array_column($c['costing']['styles'], 'style'));
        $this->assertFalse($c['check']['costing']);

        $body = [
            'dollar_rate' => 122, 'knit_note' => 'Interlock 80', 'fabrics' => [['fabrication' => 'Single Jersey 160 GSM', 'knit_rate' => 25, 'dye_rate' => 150, 'yarn_dye_rate' => null, 'aop_rate' => 10]],
            // One row per style — Embellishment, CM and the Badge Cost on it (user, 2026-11-19).
            'styles' => [
                ['style' => '2261582', 'emb_per_dz' => 0.8, 'cm_per_dz' => 7, 'badge_wastage' => 1.03, 'badge_per_dz' => 47.04, 'badge_supplier' => 'Huatai Textile', 'badge_remarks' => 'Nominated by ZXY'],
                ['style' => '2261513', 'emb_per_dz' => null, 'cm_per_dz' => 4.5], ['style' => 'W-100', 'cm_per_dz' => null],
            ],
            'complete' => true,
        ];
        // A style without CM: not completed (nothing saved half-way either).
        $this->as()->putJson("/api/v1/pre-budget/budgets/{$no}/costing", $body)->assertUnprocessable()->assertJsonValidationErrors('complete');
        $body['styles'][2]['cm_per_dz'] = 8;
        $done = $this->as()->putJson("/api/v1/pre-budget/budgets/{$no}/costing", $body)->assertOk()->json('data');
        $this->assertTrue($done['check']['costing']);
        $t = $done['costing']['totals'];
        $this->assertEquals(371.11, $t['knit_usd']);   // 1,811 kg × 25 Tk ÷ 122
        $this->assertEquals(2226.64, $t['dye_usd']);   // 1,811 kg × 150 Tk ÷ 122
        $this->assertEquals(148.44, $t['aop_usd']);    // 1,811 kg × 10 Tk ÷ 122
        $this->assertEquals(133.33, $t['emb_usd']);    // 166.67 dz × 0.80
        $this->assertEquals(3250, $t['cm_usd']);       // 166.67 dz × (7 + 4.50 + 8)
        // Badge: 2,000 pcs → 166.67 dz × 1.03 = 171.67 dz × $47.04.
        $badge = collect($done['costing']['styles'])->firstWhere('style', '2261582');
        $this->assertEquals(171.6667, $badge['badge_dz']);
        $this->assertSame('Huatai Textile', $badge['badge_supplier']);
        $this->assertEquals(8075.2, $t['badge_usd']);

        $b = $this->as()->getJson("/api/v1/pre-budget/budgets/{$no}")->json('data');
        $this->assertEquals(8075.2, $b['costs']['badge']);
        $this->assertEquals(round(8075.2 + 133.33 + $b['costs']['fabric_total'], 2), $b['costs']['total']);
        $this->assertTrue($b['check']['costing']);

        // The budget changes (a style goes): the costing is open again.
        $this->as()->deleteJson("/api/v1/pre-budget/budgets/{$no}/styles", ['requisition_ids' => [$ids[1]]])->assertOk();
        $this->assertFalse($this->as()->getJson("/api/v1/pre-budget/budgets/{$no}")->json('data.check.costing'));
    }

    public function test_one_style_in_two_requisitions_is_one_costing_row(): void
    {
        // A style's two requisitions (two parts, or a copy): one Embellishment / CM / Badge row, their Order Qty added up.
        $ids = collect($this->as()->postJson('/api/v1/pre-budget/requisitions', ['rows' => [['values' => $this->knit()], ['values' => ['parts' => 'Body', 'quantity_ticket' => 500] + $this->knit()]]])->json('data.rows'))->pluck('id')->all();
        $no = $this->as()->postJson('/api/v1/pre-budget/budgets', ['requisition_ids' => $ids])->json('data.budget_no');
        $styles = $this->as()->getJson("/api/v1/pre-budget/budgets/{$no}/costing")->json('data.costing.styles');
        $this->assertCount(1, $styles);
        $this->assertSame('2261582', $styles[0]['style']);
        $this->assertEquals(3000, $styles[0]['order_qty']); // 2,000 + 1,000 pcs
        $this->assertEquals(250, $styles[0]['dz']);
    }

    public function test_stock_yarn_use_lots_store_and_the_declaration(): void
    {
        [$no] = $this->budget();
        $s = $this->as()->getJson("/api/v1/pre-budget/budgets/{$no}/stock")->assertOk()->json('data');
        $yarn = $s['sections']['YARN']['lines'][0]['key'];
        $woven = $s['sections']['WOVEN']['lines'][0]['key'];
        $lot = ['purchase_contract_no' => 'PC-1', 'stock_contract_no' => null, 'mill' => 'Huatai Textile', 'origin' => 'LOCAL', 'lc_no' => 'LC-9', 'lot_no' => 'L-2', 'qty' => 500, 'price' => 3];

        // Every line needs an answer.
        $this->as()->putJson("/api/v1/pre-budget/budgets/{$no}/stock", ['complete' => true, 'lines' => [['section' => 'YARN', 'key' => $yarn, 'lots' => [$lot]], ['section' => 'WOVEN', 'key' => $woven, 'lots' => []]]])
            ->assertUnprocessable()->assertJsonValidationErrors('complete');
        // Lots passing the Total Req are refused.
        $this->as()->putJson("/api/v1/pre-budget/budgets/{$no}/stock", ['complete' => true, 'lines' => [['section' => 'YARN', 'key' => $yarn, 'lots' => [['qty' => 2000] + $lot]], ['section' => 'WOVEN', 'key' => $woven, 'no_stock' => true, 'lots' => []]]])
            ->assertUnprocessable();

        // Store fills it.
        $store = app(IssueApiToken::class)->forUser(TenantFixture::user($this->delta, 'STOREKEEPER', 'store@delta.test'), $this->delta->id, 'Test')['plain'];
        $this->flushHeaders();
        $this->withToken($store)->getJson('/api/v1/pre-budget/budgets/work?kind=stock')->assertOk()->assertJsonCount(1, 'data');
        $done = $this->withToken($store)->putJson("/api/v1/pre-budget/budgets/{$no}/stock", ['complete' => true, 'lines' => [
            ['section' => 'YARN', 'key' => $yarn, 'lots' => [$lot, ['qty' => 100, 'origin' => 'IMPORT', 'mill' => 'Huatai Textile', 'price' => 4.5]]], ['section' => 'WOVEN', 'key' => $woven, 'no_stock' => true, 'lots' => []],
        ]])->assertOk()->json('data');
        $this->assertTrue($done['check']['stock']);
        // Store does not reach the costing.
        $this->withToken($store)->getJson("/api/v1/pre-budget/budgets/{$no}/costing")->assertForbidden();

        // In the budget: Use from Stock, and Need to Purchase what is left (up to a whole kg).
        $line = $this->as()->getJson("/api/v1/pre-budget/budgets/{$no}")->json('data.sections.YARN.lines.0');
        $this->assertEquals(600, $line['stock_qty']);
        $this->assertEquals(1950, $line['stock_value']); // 500 × 3 + 100 × 4.50
        $this->assertEquals(1211, $line['need_to_purchase']); // 1,811 − 600

        $decl = $this->as()->getJson("/api/v1/pre-budget/budgets/{$no}/declaration")->assertOk()->json('data');
        $this->assertCount(2, $decl['lots']);
        $this->assertSame(['LOCAL', 'IMPORT'], array_column($decl['lots'], 'origin'));
        $this->assertSame('30/1', $decl['lots'][0]['yarn_count']);
        $this->assertCount(1, $this->as()->getJson('/api/v1/pre-budget/budgets/work?kind=declaration')->json('data'));
    }
}
