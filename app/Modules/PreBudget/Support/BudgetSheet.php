<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Support;

use App\Modules\PreBudget\Models\PbBudget;
use App\Modules\PreBudget\Models\PbBudgetCost;
use App\Modules\PreBudget\Models\PbBudgetLine;
use App\Modules\PreBudget\Models\PbStockLot;
use App\Modules\PreBudget\Models\PbRequisition;
use App\Shared\Formula\Formula;
use Illuminate\Support\Collection;

/**
 * A pre-budget as its page shows it (Pre-Budget, AnnotechERP 33/34; Norban's
 * Create Budget): worked out from its requisitions each time, what was typed
 * on its lines laid on top.
 *
 *  - Knit Yarn lines: the Knit requisitions by Yarn Count + Composition;
 *    Woven lines: the Woven requisitions by Style + Composition + Fabrication
 *    (user, 2026-11-19). Total Req is their Total Req, in kg / yds.
 *  - Need to Purchase = Total Req − Use from Stock, up to a whole kg / yd
 *    (changeable); PI Qty = what is bought now (the Need unless typed);
 *    Total Stock/Now Purchase = stock + PI Qty; Excess/Shortage = that − Total
 *    Req; a shortage marked Purchase is bought short (its value counted).
 *  - Price: the one typed, else the lowest supplier price on the line;
 *    PI Value = PI Qty × Price. Grand Total = PI Value + stock value + short
 *    purchase value — the budget's Yarn (or Woven) Cost.
 *  - Use from Stock: the line's stock lots (Stock Yarn Use).
 *  - Item, Fabric Type / Fabrication and Delivery Date summaries; Gross FOB,
 *    Discount/TPA, Net FOB, Avg FOB and the cost breakdown: Knitting, Dyeing
 *    Finishing, Yarn Dyeing and AOP from the fabrication rates (BDT per kg ÷
 *    the Dollar Rate), Embellishment, CM and Badge Cost from the style rates
 *    (USD per dozen, one row per style) (Knit/Dying Costing); Accessories
 *    from the BOMs (BomSheet).
 *  - The checklist: Knit/Dying Costing and Stock Yarn Use count as done while
 *    the budget is what it was when they were completed (its fabrications /
 *    styles, its lines) — a change opens them again; the BOM is done when every
 *    style is in a final BOM.
 */
final class BudgetSheet
{
    public const SECTIONS = ['YARN', 'WOVEN'];

    /** What a requisition's line stands for in its section, or null when it has none (no yarn count yet …). */
    public static function lineKey(string $section, PbRequisition $r): ?string
    {
        $v = $r->values ?? [];
        $part = fn (string $k) => trim(Formula::text($v[$k] ?? ''));
        if ($section === 'YARN') {
            return $r->entry_type === 'KNIT' && ($part('yarn_count') !== '' || $part('composition') !== '') ? $part('yarn_count').' | '.$part('composition') : null;
        }

        return $r->entry_type === 'WOVEN' ? $part('style').' | '.$part('fabric_composition').' | '.$part('fabrication') : null;
    }

    /** @return array<string, mixed> */
    public function view(PbBudget $b): array
    {
        $reqs = $b->requisitions()->orderBy('serial_no')->get();
        // Each order's Order Qty and FOB Value once, however many parts (rows) it has.
        $counted = OrderLink::countOnce($reqs);
        $stored = $b->lines()->get()->keyBy(fn (PbBudgetLine $l) => $l->section.'|'.$l->line_key);
        $lots = $b->stockLots()->get()->groupBy(fn (PbStockLot $l) => $l->section.'|'.$l->line_key);
        $suppliers = ['YARN' => array_values($b->suppliers['YARN'] ?? []), 'WOVEN' => array_values($b->suppliers['WOVEN'] ?? [])];

        $sections = [];
        foreach (self::SECTIONS as $section) {
            $groups = [];
            foreach ($reqs as $r) {
                $key = self::lineKey($section, $r);
                if ($key !== null) {
                    $groups[$key][] = $r;
                }
            }
            $lines = [];
            foreach ($groups as $key => $rows) {
                $lines[] = $this->line($section, $key, collect($rows), $stored->get("{$section}|{$key}"), $suppliers[$section], $lots->get("{$section}|{$key}") ?? collect());
            }
            $sections[$section] = ['suppliers' => $suppliers[$section], 'lines' => $lines, 'totals' => self::totals($lines)];
        }

        $knit = $counted->where('entry_type', 'KNIT');
        $sum = fn (Collection $rs, string $k) => round($rs->sum(fn ($r) => Formula::num($r->values[$k] ?? null) ?? 0), 6);
        $gross = $sum($counted, 'fob_value');
        $orderQty = $sum($counted, 'order_qty');
        $discount = round($gross * $b->discount_percent / 100, 2);
        $net = round($gross - $discount, 2);
        $yarnCost = $sections['YARN']['totals']['grand_value'];
        $wovenCost = $sections['WOVEN']['totals']['grand_value'];
        $costing = $this->costing($b, $counted);
        $t = $costing['totals'];
        // Accessories: the BOMs' Total Value (Accessories Budget Pending).
        $acc = (new BomSheet)->summary($b, $reqs);
        $costs = [
            'yarn' => $yarnCost, 'woven' => $wovenCost, 'knitting' => $t['knit_usd'], 'dyeing' => $t['dye_usd'], 'yarn_dyeing' => $t['yarn_dye_usd'], 'aop' => $t['aop_usd'],
            'accessories' => $acc['total'], 'embellishment' => $t['emb_usd'], 'badge' => $t['badge_usd'], 'cm' => $t['cm_usd'],
        ];
        $fabric = $costs['yarn'] + $costs['woven'] + $costs['knitting'] + $costs['dyeing'] + $costs['yarn_dyeing'] + $costs['aop'];
        $total = $fabric + $costs['accessories'] + $costs['embellishment'] + $costs['badge'];
        $others = round($net - $total - $costs['cm'], 2);
        $check = [
            'costing' => $b->costing_done_at !== null && $b->costing_basis === $costing['basis'],
            'stock' => $b->stock_done_at !== null && $b->stock_basis === self::stockBasis($sections),
            'bom' => $acc['done'],
        ];

        return [
            'sections' => $sections,
            'item_summary' => self::groupSum($counted, fn ($r) => [$r->values['order_type'] ?? '', $r->values['dept'] ?? '', $r->values['item'] ?? ''], ['order_type', 'dept', 'item'], ['no_of_style', 'order_qty']),
            'fabric_type_summary' => self::groupSum($knit, fn ($r) => [$r->values['fabric_type'] ?? ''], ['fabric_type'], ['order_qty', 'total_req']),
            'fabrication_summary' => self::groupSum($knit, fn ($r) => [$r->values['fabrication'] ?? ''], ['fabrication'], ['order_qty', 'total_req']),
            'delivery_summary' => self::groupSum($knit, fn ($r) => [$r->values['delivery_date'] ?? ''], ['delivery_date'], ['no_of_style', 'order_qty', 'fob_value', 'total_req']),
            // Woven's own, beside the knit ones (user 2026-11-22): fabric yards by fabric type / fabrication and by delivery date.
            'woven_fabric_type_summary' => self::groupSum($counted->where('entry_type', 'WOVEN'), fn ($r) => [$r->values['fabric_type'] ?? ''], ['fabric_type'], ['order_qty', 'total_req']),
            'woven_fabrication_summary' => self::groupSum($counted->where('entry_type', 'WOVEN'), fn ($r) => [$r->values['fabrication'] ?? ''], ['fabrication'], ['order_qty', 'total_req']),
            'woven_delivery_summary' => self::groupSum($counted->where('entry_type', 'WOVEN'), fn ($r) => [$r->values['delivery_date'] ?? ''], ['delivery_date'], ['no_of_style', 'order_qty', 'fob_value', 'total_req']),
            'fob' => [
                'gross' => $gross, 'discount_percent' => $b->discount_percent, 'discount' => $discount, 'net' => $net,
                'order_qty' => $orderQty, 'avg' => $orderQty > 0 ? round($gross / $orderQty, 4) : null,
            ],
            'costs' => $costs + ['fabric_total' => round($fabric, 2), 'total' => round($total, 2), 'others' => $others],
            'factories' => $reqs->pluck('values.factory')->filter()->unique()->values()->all(),
            'costing' => $costing,
            'accessories' => ['state' => $acc['state'], 'count' => $acc['count'], 'budgeted' => $acc['budgeted'], 'boms' => count($acc['boms'])],
            'check' => $check + [
                'costing_at' => $check['costing'] ? $b->costing_done_at?->toIso8601String() : null,
                'stock_at' => $check['stock'] ? $b->stock_done_at?->toIso8601String() : null,
            ],
        ];
    }

    /**
     * Knit/Dying Costing worked out: each fabrication of the Knit requisitions
     * (its kg — their Total Req) with its rates and values in BDT and USD, each
     * style (requisition) with its dozens, Embellishment and CM, the Badge Cost
     * rows, and the totals. `basis` is what the costing was done on.
     *
     * @param  Collection<int, PbRequisition>  $reqs
     * @return array<string, mixed>
     */
    public function costing(PbBudget $b, Collection $reqs): array
    {
        $rates = $b->costs()->get()->keyBy(fn (PbBudgetCost $c) => $c->kind.'|'.$c->cost_key);
        $dollar = $b->dollar_rate && $b->dollar_rate > 0 ? $b->dollar_rate : null;
        $usd = fn (float $bdt) => $dollar ? round($bdt / $dollar, 2) : 0.0;
        $num = fn ($r, string $k) => Formula::num($r->values[$k] ?? null) ?? 0.0;

        $fabrics = [];
        foreach ($reqs->where('entry_type', 'KNIT')->groupBy(fn ($r) => trim(Formula::text($r->values['fabrication'] ?? ''))) as $fabrication => $rows) {
            $qty = round($rows->sum(fn ($r) => $num($r, 'total_req')), 3);
            $rate = $rates->get("FABRIC|{$fabrication}");
            $row = ['fabrication' => (string) $fabrication, 'qty' => $qty];
            foreach (['knit', 'dye', 'yarn_dye', 'aop'] as $k) {
                $r = $rate?->{"{$k}_rate"};
                $bdt = $r === null ? 0.0 : round($qty * $r, 2);
                $row += ["{$k}_rate" => $r, "{$k}_bdt" => $bdt, "{$k}_usd" => $usd($bdt)];
            }
            $fabrics[] = $row;
        }

        // One row per style, as Norban: its requisitions' Order Qty added up (a style's other parts carry 0).
        // Badge Cost on the same rows: Qty/Dz × the wastage factor × cost per dozen.
        $styles = [];
        foreach ($reqs->groupBy(fn ($r) => trim(Formula::text($r->values['style'] ?? ''))) as $style => $rows) {
            $rate = $rates->get("STYLE|{$style}");
            $pcs = round($rows->sum(fn ($r) => $num($r, 'order_qty')), 2);
            $dz = round($pcs / 12, 4);
            $withWastage = round($dz * ($rate?->badge_wastage ?? 1), 4);
            $styles[] = [
                'style' => (string) $style, 'serials' => $rows->pluck('serial_no')->all(), 'order_qty' => $pcs, 'dz' => $dz,
                'emb_per_dz' => $rate?->emb_per_dz, 'emb_usd' => round($dz * ($rate?->emb_per_dz ?? 0), 2),
                'cm_per_dz' => $rate?->cm_per_dz, 'cm_usd' => round($dz * ($rate?->cm_per_dz ?? 0), 2),
                'badge_wastage' => $rate?->badge_wastage, 'badge_dz' => $withWastage, 'badge_per_dz' => $rate?->badge_per_dz,
                'badge_usd' => round($withWastage * ($rate?->badge_per_dz ?? 0), 2), 'badge_supplier' => $rate?->badge_supplier, 'badge_remarks' => $rate?->badge_remarks,
            ];
        }
        usort($styles, fn ($x, $y) => strnatcasecmp($x['style'], $y['style']));

        $sum = fn (array $rows, string $k) => round(array_sum(array_column($rows, $k)), 2);
        $kg = array_sum(array_column($fabrics, 'qty'));
        $dzTotal = array_sum(array_column($styles, 'dz'));
        $totals = [
            'qty' => round($kg, 3), 'knit_bdt' => $sum($fabrics, 'knit_bdt'), 'knit_usd' => $sum($fabrics, 'knit_usd'), 'dye_bdt' => $sum($fabrics, 'dye_bdt'), 'dye_usd' => $sum($fabrics, 'dye_usd'),
            'yarn_dye_bdt' => $sum($fabrics, 'yarn_dye_bdt'), 'yarn_dye_usd' => $sum($fabrics, 'yarn_dye_usd'), 'aop_bdt' => $sum($fabrics, 'aop_bdt'), 'aop_usd' => $sum($fabrics, 'aop_usd'),
            'order_qty' => $sum($styles, 'order_qty'), 'dz' => round($dzTotal, 4), 'emb_usd' => $sum($styles, 'emb_usd'), 'cm_usd' => $sum($styles, 'cm_usd'), 'badge_usd' => $sum($styles, 'badge_usd'),
        ];
        $totals['avg_knit_per_kg'] = $kg > 0 ? round($totals['knit_bdt'] / $kg, 2) : null;
        $totals['avg_dye_per_kg'] = $kg > 0 ? round($totals['dye_bdt'] / $kg, 2) : null;
        $totals['cm_per_dz'] = $dzTotal > 0 ? round($totals['cm_usd'] / $dzTotal, 2) : null;

        return [
            'dollar_rate' => $b->dollar_rate, 'knit_note' => $b->knit_note, 'dye_note' => $b->dye_note,
            'fabrics' => $fabrics, 'styles' => $styles, 'totals' => $totals,
            'basis' => md5(json_encode([array_column($fabrics, 'fabrication'), array_column($styles, 'style')])),
        ];
    }

    /** What the stock was answered for: every line and its Total Req. @param  array<string, array<string, mixed>>  $sections */
    public static function stockBasis(array $sections): string
    {
        $out = [];
        foreach ($sections as $section => $data) {
            foreach ($data['lines'] as $l) {
                $out[] = [$section, $l['key'], $l['total_req']];
            }
        }

        return md5(json_encode($out));
    }

    /** @param  Collection<int, PbRequisition>  $rows  @param  list<string>  $columns */
    /** @param  Collection<int, PbStockLot>  $lots */
    private function line(string $section, string $key, Collection $rows, ?PbBudgetLine $typed, array $columns, Collection $lots): array
    {
        $first = $rows->first()->values ?? [];
        $totalReq = round($rows->sum(fn ($r) => Formula::num($r->values['total_req'] ?? null) ?? 0), 3);
        // Use from Stock: the line's stock lots (Stock Yarn Use).
        $stockQty = round($lots->sum('qty'), 3);
        $stockValue = round($lots->sum(fn (PbStockLot $l) => $l->qty * ($l->price ?? 0)), 2);
        $needWorked = self::upToWhole(max($totalReq - $stockQty, 0));
        $need = $typed?->need_to_purchase ?? $needWorked;
        $pi = $typed?->pi_qty ?? $need;
        $prices = [];
        foreach ($columns as $name) {
            $p = $typed?->supplier_prices[$name] ?? null;
            $prices[$name] = $p === null || $p === '' ? null : (float) $p;
        }
        $offered = array_filter($prices, fn ($p) => $p !== null && $p > 0);
        $lowest = $offered === [] ? null : min($offered);
        $price = $typed?->price ?? $lowest;
        $stockPurchase = round($stockQty + $pi, 3);
        $diff = round($stockPurchase - $totalReq, 3);
        $action = $typed?->shortage_action;
        $value = $price === null ? 0.0 : round($pi * $price, 2);
        $short = $diff < 0 && $action === 'PURCHASE' && $price !== null ? round(-$diff * $price, 2) : 0.0;

        return [
            'key' => $key, 'section' => $section,
            'yarn_count' => $first['yarn_count'] ?? null, 'composition' => $section === 'YARN' ? ($first['composition'] ?? null) : ($first['fabric_composition'] ?? null),
            'style' => $first['style'] ?? null, 'fabrication' => $first['fabrication'] ?? null,
            'fabric_types' => $rows->pluck('values.fabric_type')->filter()->unique()->values()->all(),
            'requisitions' => $rows->pluck('serial_no')->all(),
            'total_req' => $totalReq, 'stock_qty' => $stockQty, 'stock_value' => $stockValue,
            'need_to_purchase' => $need, 'need_worked' => $needWorked, 'need_typed' => $typed?->need_to_purchase !== null,
            'pi_qty' => $pi, 'pi_typed' => $typed?->pi_qty !== null,
            'stock_purchase' => $stockPurchase, 'excess' => $diff, 'shortage_action' => $action,
            'supplier_prices' => $prices, 'lowest_price' => $lowest, 'price' => $price, 'price_typed' => $typed?->price !== null,
            'value' => $value, 'short_value' => $short, 'remarks' => $typed?->remarks,
            'no_stock' => (bool) $typed?->no_stock,
            'lots' => $lots->values()->map(fn (PbStockLot $l) => $l->only(['purchase_contract_no', 'stock_contract_no', 'mill', 'origin', 'lc_no', 'lot_no', 'qty', 'price']) + ['value' => round($l->qty * ($l->price ?? 0), 2)])->all(),
        ];
    }

    /** @param  list<array<string, mixed>>  $lines */
    private static function totals(array $lines): array
    {
        $t = fn (string $k) => round(array_sum(array_column($lines, $k)), 3);
        $value = round(array_sum(array_column($lines, 'value')), 2);
        $stock = round(array_sum(array_column($lines, 'stock_value')), 2);
        $short = round(array_sum(array_column($lines, 'short_value')), 2);

        return [
            'total_req' => $t('total_req'), 'stock_qty' => $t('stock_qty'), 'stock_value' => $stock, 'need_to_purchase' => $t('need_to_purchase'),
            'pi_qty' => $t('pi_qty'), 'stock_purchase' => $t('stock_purchase'), 'excess' => $t('excess'),
            'value' => $value, 'short_value' => $short, 'grand_value' => round($value + $stock + $short, 2),
        ];
    }

    /** Up to a whole kg / yd (user, 2026-11-19), float noise aside. */
    public static function upToWhole(float $qty): float
    {
        return (float) ceil(round($qty, 6) - 1e-9);
    }

    /**
     * Rows grouped by some values, with sums.
     *
     * @param  Collection<int, PbRequisition>  $reqs
     * @param  list<string>  $names  what the grouping values are called
     * @param  list<string>  $sums  values summed
     * @return array{rows: list<array<string, mixed>>, total: array<string, float>}
     */
    private static function groupSum(Collection $reqs, callable $by, array $names, array $sums): array
    {
        // No of Style counts the group's different styles (user 2026-11-22: a requisition's own "No of Style"
        // is 1 on a style's first requisition and 0 on the rest, so adding them up showed 0 for a budget's woven style).
        $groups = [];
        $styles = [];
        foreach ($reqs as $r) {
            $keys = array_map(fn ($v) => Formula::text($v), $by($r));
            $id = implode("\u{1F}", $keys);
            $groups[$id] ??= array_combine($names, $keys) + array_fill_keys($sums, 0.0);
            foreach ($sums as $s) {
                if ($s === 'no_of_style') {
                    $style = mb_strtolower(trim(Formula::text($r->values['style'] ?? '')));
                    $styles[$id][$style !== '' ? $style : "#{$r->id}"] = true;
                    $groups[$id][$s] = (float) count($styles[$id]);
                } else {
                    $groups[$id][$s] += Formula::num($r->values[$s] ?? null) ?? 0;
                }
            }
        }
        $rows = array_values($groups);
        usort($rows, fn ($a, $b) => strcmp(implode('|', array_map(fn ($n) => (string) $a[$n], $names)), implode('|', array_map(fn ($n) => (string) $b[$n], $names))));
        $total = [];
        // A style in two groups (two delivery dates) is still one style in the total (+ keeps numeric style keys apart from renumbering).
        $allStyles = [];
        foreach ($styles as $set) {
            $allStyles += $set;
        }
        foreach ($sums as $s) {
            $total[$s] = $s === 'no_of_style' ? (float) count($allStyles) : round(array_sum(array_column($rows, $s)), 3);
        }

        return ['rows' => array_map(fn ($row) => array_map(fn ($v) => is_float($v) ? round($v, 3) : $v, $row), $rows), 'total' => $total];
    }
}
