<?php

declare(strict_types=1);

namespace Tests\Feature\PreBudget;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pre-Budget step 5 (AnnotechERP 33/34): Accessories Budget Pending and the
 * BOMs — a BOM of one or more budget styles, each with its own accessory
 * lines; Cost/Pc by the unit (Norban's formulas), Qty with %, Total Value; a
 * style in one BOM at a time; final wants every line complete; the BOMs'
 * value is the budget's Accessories cost and every style in a final BOM
 * ticks the checklist; a style leaving the budget leaves its BOM.
 */
class BomTest extends TestCase
{
    use PreBudgetFixture;
    use RefreshDatabase;

    /** @var array<string, string> accessory item ids by name */
    private array $items = [];

    /** @return array{0: string, 1: list<string>} a budget of styles 2261582, 2261513 (2,000 pcs at $3.50 each) and W-100 */
    private function budget(): array
    {
        $woven = ['entry_type' => 'Woven', 'style' => 'W-100', 'fabric_mill' => 'Huatai Textile', 'width' => '58 inch'] + $this->knit();
        $ids = collect($this->as()->postJson('/api/v1/pre-budget/requisitions', ['rows' => [
            ['values' => $this->knit()], ['values' => ['style' => '2261513'] + $this->knit()], ['values' => $woven],
        ]])->assertCreated()->json('data.rows'))->pluck('id')->all();
        foreach (['Sewing Thread' => 'CONE', 'Care Label' => 'PCS', 'Button' => 'GRS'] as $name => $uom) {
            $id = strtolower((string) Str::ulid());
            DB::table('pb_accessory_items')->insert(['id' => $id, 'company_id' => $this->delta->id, 'code' => 'ACC-'.count($this->items), 'name' => $name, 'uom' => $uom, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            $this->items[$name] = $id;
        }

        return [$this->as()->postJson('/api/v1/pre-budget/budgets', ['requisition_ids' => $ids])->assertCreated()->json('data.budget_no'), $ids];
    }

    /** @return array<string, mixed> */
    private function line(string $item, float $rate, string $uom, float $body, ?float $itemCons = null, float $extra = 5, ?float $orderQty = null, ?string $supplier = 'Huatai Textile'): array
    {
        return ['item' => $item, 'supplier' => $supplier, 'nominated' => 'Nominated', 'rate' => $rate, 'unit' => $uom, 'body_cons' => $body, 'item_cons' => $itemCons, 'extra_percent' => $extra, 'item_order_qty' => $orderQty];
    }

    public function test_a_bom_works_out_its_lines_and_gives_the_budget_its_accessories_cost(): void
    {
        [$no] = $this->budget();
        $styles = [
            ['style' => '2261582', 'lines' => [
                $this->line('Sewing Thread', 0.75, 'CONE', 50, 3000),  // 0.75 ÷ 3,000 × 50 = 0.0125 a piece
                $this->line('Care Label', 0.028, 'PCS', 1),            // 0.028 ÷ 1
                ['item' => null, 'supplier' => null, 'nominated' => 'Nominated', 'extra_percent' => 2], // blank: left out
            ]],
            ['style' => '2261513', 'lines' => [$this->line('Button', 2.88, 'GRS', 6, null, 2, 1000)]], // 2.88 ÷ 144 × 6 = 0.12, for 1,000 pcs
        ];
        $bomNo = $this->as()->postJson("/api/v1/pre-budget/budgets/{$no}/boms", ['styles' => $styles])->assertCreated()->json('data.bom_no');
        $this->assertSame("{$no}-A1", $bomNo);

        $bom = $this->as()->getJson("/api/v1/pre-budget/boms/{$bomNo}")->assertOk()->json('data.bom');
        $this->assertSame('DRAFT', $bom['status']);
        [$a, $b] = $bom['styles'];
        $this->assertCount(2, $a['lines']);
        $this->assertEquals(2000, $a['order_qty']);
        $this->assertEquals(7000, $a['style_value']);
        $this->assertEquals(3.5, $a['fob']);
        $this->assertEquals(2100, $a['lines'][0]['qty_with']);   // 2,000 × 1.05
        $this->assertEquals(0.0125, $a['lines'][0]['cost_pc']);
        $this->assertEquals(0.15, $a['lines'][0]['cost_dz']);
        $this->assertEquals(26.25, $a['lines'][0]['value']);          // 2,100 × 0.0125
        $this->assertEquals(58.8, $a['lines'][1]['value']);           // 2,100 × 0.028
        $this->assertEquals(85.05, $a['value']);
        $this->assertEquals(1020, $b['lines'][0]['qty_with']);  // 1,000 × 1.02
        $this->assertEquals(122.4, $b['value']);                      // 1,020 × 0.12
        $this->assertEquals(207.45, $bom['totals']['value']);

        // The budget: Accessories cost from the BOMs; not every style budgeted yet.
        $budget = $this->as()->getJson("/api/v1/pre-budget/budgets/{$no}")->json('data');
        $this->assertEquals(207.45, $budget['costs']['accessories']);
        $this->assertFalse($budget['check']['bom']);
        $this->assertSame('PARTIAL', $budget['accessories']['state']);

        // A style is in one BOM at a time.
        $this->as()->postJson("/api/v1/pre-budget/budgets/{$no}/boms", ['styles' => [['style' => '2261582', 'lines' => []]]])
            ->assertUnprocessable()->assertJsonValidationErrors('styles.0.style');

        // Final wants every line complete: a supplier missing.
        $styles[1]['lines'][0]['supplier'] = null;
        $this->as()->putJson("/api/v1/pre-budget/boms/{$bomNo}", ['version' => 1, 'styles' => $styles, 'final' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('styles.1.lines.0');
        $styles[1]['lines'][0]['supplier'] = 'Huatai Textile';
        $saved = $this->as()->putJson("/api/v1/pre-budget/boms/{$bomNo}", ['version' => 1, 'styles' => $styles, 'final' => true])->assertOk()->json('data.bom');
        $this->assertSame('FINAL', $saved['status']);
        // A stale version is refused.
        $this->as()->putJson("/api/v1/pre-budget/boms/{$bomNo}", ['version' => 1, 'styles' => $styles])->assertStatus(409);

        $pending = collect($this->as()->getJson('/api/v1/pre-budget/boms/pending')->assertOk()->json('data.rows'))->firstWhere('budget_no', $no);
        $this->assertSame('PARTIAL', $pending['state']);
        $this->assertSame(2, $pending['budgeted']);

        // The last style in a second BOM, final: every style budgeted.
        $this->as()->postJson("/api/v1/pre-budget/budgets/{$no}/boms", ['styles' => [['style' => 'W-100', 'lines' => [$this->line('Care Label', 0.03, 'PCS', 1)]]], 'final' => true])
            ->assertCreated()->assertJsonPath('data.bom_no', "{$no}-A2");
        $budget = $this->as()->getJson("/api/v1/pre-budget/budgets/{$no}")->json('data');
        $this->assertTrue($budget['check']['bom']);
        $this->assertSame('CREATED', $budget['accessories']['state']);
        $this->assertEquals(round(207.45 + 2100 * 0.03, 2), $budget['costs']['accessories']);

        $list = $this->as()->getJson('/api/v1/pre-budget/boms')->assertOk()->json('data');
        $this->assertSame(['2261513', '2261582', 'W-100'], collect($list)->pluck('style')->sort()->values()->all());
        $this->assertEquals(0.0425, collect($list)->firstWhere('style', '2261582')['cost_pc']); // 85.05 ÷ 2,000
    }

    public function test_a_style_leaving_the_budget_leaves_its_bom_and_only_a_draft_bom_is_deleted(): void
    {
        [$no, $ids] = $this->budget();
        $first = $this->as()->postJson("/api/v1/pre-budget/budgets/{$no}/boms", ['styles' => [['style' => '2261582', 'lines' => [$this->line('Care Label', 0.03, 'PCS', 1)]]], 'final' => true])->json('data.bom_no');
        $second = $this->as()->postJson("/api/v1/pre-budget/budgets/{$no}/boms", ['styles' => [['style' => '2261513', 'lines' => []]]])->json('data.bom_no');

        // Style 2261513 leaves the budget: its draft BOM (now empty) goes.
        $this->as()->deleteJson("/api/v1/pre-budget/budgets/{$no}/styles", ['requisition_ids' => [$ids[1]]])->assertOk();
        $this->as()->getJson("/api/v1/pre-budget/boms/{$second}")->assertNotFound();
        $this->assertSame([$first], array_column($this->as()->getJson("/api/v1/pre-budget/budgets/{$no}/accessories")->json('data.summary.boms'), 'bom_no'));

        // A final BOM is not deleted; a draft one is.
        $this->as()->deleteJson("/api/v1/pre-budget/boms/{$first}")->assertUnprocessable();
        $draft = $this->as()->postJson("/api/v1/pre-budget/budgets/{$no}/boms", ['styles' => [['style' => 'W-100', 'lines' => []]]])->json('data.bom_no');
        $this->as()->deleteJson("/api/v1/pre-budget/boms/{$draft}")->assertOk();
        $this->as()->getJson("/api/v1/pre-budget/boms/{$draft}")->assertNotFound();
    }

    public function test_the_company_sets_the_worksheet_columns_formulas_and_unit_rules(): void
    {
        [$no] = $this->budget();
        $line = $this->line('Sewing Thread', 0.75, 'CONE', 50, 3000); // 2,100 × 0.0125 = 26.25
        $bomNo = $this->as()->postJson("/api/v1/pre-budget/budgets/{$no}/boms", ['styles' => [['style' => '2261582', 'lines' => [$line]]]])->assertCreated()->json('data.bom_no');
        $value = fn () => $this->as()->getJson("/api/v1/pre-budget/boms/{$bomNo}")->json('data.bom.styles.0.lines.0.value');
        $this->assertEquals(26.25, $value());

        // Acc Budget Column Setup: the BOM sheet's own columns and rules, apart from the requisition sheet's.
        $setup = $this->as()->getJson('/api/v1/pre-budget/bom-columns')->assertOk()->json('data');
        $cols = collect($setup['columns'])->keyBy('key');
        $this->assertSame('[Unit Rule Cost]', $cols['cost_pc']['formula_text']);
        $this->assertSame('[Rate] / [I.Cons] * [B.Cons]', collect($setup['unit_rules'])->firstWhere('uom', 'CONE')['formula_text']);
        $this->assertNotContains('cost_pc', collect($this->as()->getJson('/api/v1/pre-budget/columns')->json('data.columns'))->pluck('key')->all());
        $groups = collect($setup['groups'])->pluck('id', 'code');

        // A unit's rule, the company's own: the BOM works out by it.
        $this->as()->putJson('/api/v1/pre-budget/bom-columns/unit-rules/CONE', ['formula' => '[Rate] / [I.Cons] * [B.Cons] * 2'])->assertOk();
        $this->assertEquals(52.5, $value());
        // A rule reads typed columns, not a formula column.
        $this->as()->putJson('/api/v1/pre-budget/bom-columns/unit-rules/CONE', ['formula' => '[Cost/Pc] * 2'])->assertUnprocessable()->assertJsonValidationErrors('formula');
        $this->as()->putJson('/api/v1/pre-budget/bom-columns/unit-rules/CONE', ['formula' => '[Rate] / [I.Cons] * [B.Cons]'])->assertOk();

        // A column of the company's own, and Total Value's formula changed to read it.
        $this->as()->postJson('/api/v1/pre-budget/bom-columns', ['label' => 'Wastage %', 'group_id' => $groups['S'], 'type' => 'percent', 'decimals' => 2])
            ->assertUnprocessable()->assertJsonValidationErrors('group_id'); // not among the style's cells
        $key = $this->as()->postJson('/api/v1/pre-budget/bom-columns', ['label' => 'Wastage %', 'group_id' => $groups['A'], 'type' => 'percent', 'decimals' => 2])->assertCreated()->json('data.key');
        $this->as()->patchJson('/api/v1/pre-budget/bom-columns/value', [
            'label' => 'Total Value', 'group_id' => $groups['A'], 'type' => 'number', 'decimals' => 2, 'kpi' => 'SUM',
            'formula' => 'ROUND([Qty with %] * [Cost/Pc] * (1 + N([Wastage %]) / 100), 2)',
        ])->assertOk();
        $this->assertEquals(26.25, $value()); // no wastage typed yet
        $this->as()->putJson("/api/v1/pre-budget/boms/{$bomNo}", ['version' => 1, 'styles' => [['style' => '2261582', 'lines' => [$line + [$key => 10]]]]])->assertOk();
        $this->assertEquals(28.88, $value()); // 2,100 × 0.0125 × 1.10
        $this->assertEquals(28.88, $this->as()->getJson("/api/v1/pre-budget/budgets/{$no}")->json('data.costs.accessories'));
        // The worksheet's columns may show bold too (user, 2026-11-21).
        $this->as()->patchJson('/api/v1/pre-budget/bom-columns/value', [
            'label' => 'Total Value', 'group_id' => $groups['A'], 'type' => 'number', 'decimals' => 2, 'kpi' => 'SUM', 'bold' => true,
            'formula' => 'ROUND([Qty with %] * [Cost/Pc] * (1 + N([Wastage %]) / 100), 2)',
        ])->assertOk();
        $this->assertTrue(collect($this->as()->getJson('/api/v1/pre-budget/bom-columns')->json('data.columns'))->firstWhere('key', 'value')['bold']);
    }
}
