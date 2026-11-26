<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Support;

use App\Modules\PreBudget\Models\PbColumn;
use App\Modules\PreBudget\Models\PbGroup;
use App\Modules\PreBudget\Models\PbUnitRule;
use App\Shared\Formula\Formula;
use App\Shared\Support\ColumnSetupSnapshot;
use Illuminate\Support\Facades\DB;

/**
 * The accessories BOM worksheet's starting columns (Pre-Budget → Acc Budget
 * Column Setup, user 2026-11-19; Norban's Accessories Pre-Budget worksheet)
 * and the starting Unit Cost Rules. A company's worksheet is made from these
 * once; Column Setup changes it after — every column, the calculation ones
 * too (their formulas are the company's to change).
 *
 *  - Style (group S): the style's cells, filled from the budget's
 *    requisitions — SL, Brand, Job No, Style No, Artwork, Order Qty, FOB,
 *    Style Value; a line formula may read them. No column is added here.
 *  - Accessories (group A): the line's columns. Cost/Pc reads Unit Rule
 *    Cost — the line's unit's rule worked out on the line.
 */
final class BomCatalog
{
    public const SHEET = 'BOM';

    /** What a BOM dropdown may offer. */
    public const SOURCES = ['accessory_items', 'suppliers', 'units', 'nominated'];

    public const NOMINATED = ['Nominated', 'Non Nominated'];

    /** The style group: filled from the budget, never typed. */
    public const STYLE_GROUP = 'S';

    /** The column a unit's rule fills. */
    public const UNIT_COST = 'unit_cost';

    /** The column that is the line's accessories value (the budget's Accessories Cost adds it up). */
    public const VALUE = 'value';

    public const GROUPS = [
        ['code' => 'S', 'name' => 'Style'],
        ['code' => 'A', 'name' => 'Accessories'],
    ];

    public const COLUMNS = [
        ['code' => 'S', 'key' => 'sl', 'label' => 'SL', 'type' => 'system', 'width' => 40, 'vertical' => true, 'decimals' => 0],
        ['code' => 'S', 'key' => 'brand', 'label' => 'Brand', 'type' => 'system', 'width' => 40, 'vertical' => true],
        ['code' => 'S', 'key' => 'job_no', 'label' => 'Job No', 'type' => 'system', 'width' => 40, 'vertical' => true],
        ['code' => 'S', 'key' => 'style', 'label' => 'Style No', 'type' => 'system', 'width' => 44, 'vertical' => true],
        ['code' => 'S', 'key' => 'artwork', 'label' => 'Artwork', 'type' => 'system', 'width' => 70],
        ['code' => 'S', 'key' => 'order_qty', 'label' => 'Order Qty', 'type' => 'system', 'width' => 80, 'decimals' => 0],
        ['code' => 'S', 'key' => 'fob', 'label' => 'FOB', 'type' => 'system', 'width' => 70, 'decimals' => 4],
        ['code' => 'S', 'key' => 'style_value', 'label' => 'Style Value', 'type' => 'system', 'width' => 90, 'decimals' => 2],
        ['code' => 'A', 'key' => 'item', 'label' => 'Accessories Item', 'type' => 'dropdown', 'source' => 'accessory_items', 'width' => 170, 'required' => true],
        ['code' => 'A', 'key' => 'item_code', 'label' => 'Item Code', 'type' => 'text', 'width' => 110],
        ['code' => 'A', 'key' => 'supplier', 'label' => 'Supplier', 'type' => 'dropdown', 'source' => 'suppliers', 'width' => 150, 'required' => true],
        ['code' => 'A', 'key' => 'nominated', 'label' => 'Nominated', 'type' => 'dropdown', 'source' => 'nominated', 'width' => 120],
        ['code' => 'A', 'key' => 'item_order_qty', 'label' => 'Item Order Qty', 'type' => 'number', 'width' => 90, 'decimals' => 0],
        ['code' => 'A', 'key' => 'extra_percent', 'label' => 'Extra %', 'type' => 'percent', 'width' => 70, 'decimals' => 2, 'suffix' => '%'],
        ['code' => 'A', 'key' => 'qty_with', 'label' => 'Qty with %', 'type' => 'number', 'width' => 80, 'decimals' => 0, 'formula' => 'IF(ISBLANK([Item Order Qty]), [Order Qty], [Item Order Qty]) * (1 + N([Extra %]) / 100)'],
        ['code' => 'A', 'key' => 'cost_pc', 'label' => 'Cost/Pc', 'type' => 'number', 'width' => 80, 'decimals' => 6, 'formula' => '[Unit Rule Cost]', 'kpi' => 'SUM'],
        ['code' => 'A', 'key' => 'cost_dz', 'label' => 'Cost/Doz', 'type' => 'number', 'width' => 80, 'decimals' => 6, 'formula' => '[Cost/Pc] * 12', 'kpi' => 'SUM'],
        ['code' => 'A', 'key' => 'value', 'label' => 'Total Value', 'type' => 'number', 'width' => 90, 'decimals' => 2, 'formula' => 'ROUND([Qty with %] * [Cost/Pc], 2)', 'kpi' => 'SUM'],
        ['code' => 'A', 'key' => 'acc_pct', 'label' => 'Acc Cost (%)', 'type' => 'percent', 'width' => 80, 'decimals' => 2, 'suffix' => '%', 'formula' => '[Total Value] / [Style Value] * 100', 'kpi' => 'SUM'],
        ['code' => 'A', 'key' => 'rate', 'label' => 'Rate', 'type' => 'number', 'width' => 80, 'decimals' => 8, 'required' => true],
        ['code' => 'A', 'key' => 'unit', 'label' => 'Unit', 'type' => 'dropdown', 'source' => 'units', 'width' => 80, 'required' => true],
        ['code' => 'A', 'key' => 'body_cons', 'label' => 'B.Cons', 'type' => 'number', 'width' => 70, 'decimals' => 4, 'required' => true],
        ['code' => 'A', 'key' => 'item_cons', 'label' => 'I.Cons', 'type' => 'number', 'width' => 70, 'decimals' => 4],
        ['code' => 'A', 'key' => 'remarks', 'label' => 'Remarks', 'type' => 'text', 'width' => 140],
        ['code' => 'A', 'key' => 'unit_cost', 'label' => 'Unit Rule Cost', 'type' => 'system', 'width' => 80, 'decimals' => 6, 'hidden' => true],
    ];

    /**
     * The starting Unit Cost Rules (Norban's BomItem formulas). B = B.Cons, I = I.Cons.
     * A unit of N pieces (DZN, GROSS, PAIR) or a length / area / weight: Rate ÷ N × B;
     * a pack holding I (cone, box, kg …): Rate ÷ I × B; a piece or set: Rate × I ÷ B
     * (Rate ÷ B without I); a roll serving B garments: Rate ÷ B.
     *
     * @return array<string, string> unit => formula with column names
     */
    public static function defaultRules(): array
    {
        $fixed = fn (int $n) => $n === 1 ? '[Rate] * [B.Cons]' : "[Rate] / {$n} * [B.Cons]";
        $out = ['DZN' => $fixed(12), 'GRS' => $fixed(144), 'PAIR' => $fixed(2)];
        foreach (['YDS', 'MTR', 'INCH', 'FEET', 'RFT', 'SFT', 'CFT', 'M3', 'MG'] as $u) {
            $out[$u] = $fixed(1);
        }
        foreach (['CONE', 'CONE2000', 'CONE3000', 'CONE4000', 'CONE5000', 'BOX', 'KG', 'LBS', 'LTR', 'DRUM', 'BAG', 'BTL', 'PAK', 'REAM', 'BOOK', 'COIL', 'REEL', 'POT', 'AMPL'] as $u) {
            $out[$u] = '[Rate] / [I.Cons] * [B.Cons]';
        }
        $out['PCS'] = $out['SET'] = 'IF(ISBLANK([I.Cons]), [Rate] / [B.Cons], [Rate] * [I.Cons] / [B.Cons])';
        $out['ROLL'] = '[Rate] / [B.Cons]';

        return $out;
    }

    /** Where `erp:save-column-setup` keeps the saved Acc Budget (BOM) Column Setup and unit rules. */
    public static function snapshotPath(): string
    {
        return dirname(__DIR__).'/Database/Data/bom_column_setup.json';
    }

    /** The company's BOM worksheet and unit rules, made from the saved setup (else this catalog) if it has none yet. */
    public static function ensure(string $companyId): void
    {
        if (PbColumn::query()->where('company_id', $companyId)->where('sheet', self::SHEET)->exists()) {
            return;
        }
        DB::transaction(function () use ($companyId): void {
            DB::table('companies')->where('id', $companyId)->lockForUpdate()->value('id');
            if (PbColumn::query()->where('company_id', $companyId)->where('sheet', self::SHEET)->exists()) {
                return;
            }
            // The factory's saved Acc Budget Column Setup and unit rules, when there are, rather than this catalog.
            if ($saved = ColumnSetupSnapshot::read(self::snapshotPath())) {
                ColumnSetupSnapshot::restore($companyId, $saved, PbGroup::class, PbColumn::class, self::SHEET, PbUnitRule::class);

                return;
            }
            $groups = [];
            foreach (self::GROUPS as $i => $g) {
                $groups[$g['code']] = PbGroup::query()->create(['company_id' => $companyId, 'sheet' => self::SHEET, 'code' => $g['code'], 'name' => $g['name'], 'sort_order' => ($i + 1) * 10, 'is_system' => true, 'active' => true])->id;
            }
            $byLabel = array_column(self::COLUMNS, 'key', 'label');
            foreach (self::COLUMNS as $i => $c) {
                PbColumn::query()->create([
                    'company_id' => $companyId, 'sheet' => self::SHEET, 'group_id' => $groups[$c['code']], 'key' => $c['key'], 'label' => $c['label'], 'sort_order' => ($i + 1) * 10,
                    'type' => $c['type'], 'source' => $c['source'] ?? null, 'multiple' => false, 'prefix' => $c['prefix'] ?? null, 'suffix' => $c['suffix'] ?? null,
                    'decimals' => $c['decimals'] ?? null, 'width' => $c['width'] ?? 130, 'vertical' => $c['vertical'] ?? false, 'frozen' => false,
                    'required' => $c['required'] ?? false, 'formula' => isset($c['formula']) ? Formula::stored($c['formula'], fn (string $label) => $byLabel[$label] ?? null) : null,
                    'kpi' => $c['kpi'] ?? null, 'is_system' => true, 'hidden' => $c['hidden'] ?? false,
                ]);
            }
            if (! PbUnitRule::query()->where('company_id', $companyId)->exists()) {
                foreach (self::defaultRules() as $uom => $formula) {
                    PbUnitRule::query()->create(['company_id' => $companyId, 'uom' => $uom, 'formula' => Formula::stored($formula, fn (string $label) => $byLabel[$label] ?? null)]);
                }
            }
        });
    }
}
