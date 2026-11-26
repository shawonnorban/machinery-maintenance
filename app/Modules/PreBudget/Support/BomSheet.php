<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Support;

use App\Modules\PreBudget\Models\PbBom;
use App\Modules\PreBudget\Models\PbBomLine;
use App\Modules\PreBudget\Models\PbBomStyle;
use App\Modules\PreBudget\Models\PbBudget;
use App\Modules\PreBudget\Models\PbRequisition;
use App\Shared\Formula\Formula;
use Illuminate\Support\Collection;

/**
 * A budget's accessories BOMs worked out (Pre-Budget → Accessories Budget
 * Pending, Norban's Accessories Pre-Budget worksheet), on the company's BOM
 * worksheet (Acc Budget Column Setup, BomColumns):
 *
 *  - A style's cells come from the budget each time: Order Qty its
 *    requisitions' Order Qty added up, Style Value their FOB Value, FOB that
 *    value ÷ the qty; brand, job no and artwork from its first requisition.
 *  - A line: its typed values, Unit Rule Cost from its unit's rule, then the
 *    worksheet's formulas (Qty with %, Cost/Pc, Cost/Doz, Total Value, Acc
 *    Cost % to start — the company may change any of them).
 *  - A style's TOTAL row adds up the columns marked Total (SUM) in Column
 *    Setup; its value is its lines' Total Value.
 *  - The budget: Accessories cost = every BOM style's value (drafts too);
 *    a style counts as budgeted once its BOM is final; the checklist's BOM
 *    is done when every style is budgeted.
 */
final class BomSheet
{
    /** @var array<string, BomColumns> company => worksheet */
    private array $sheets = [];

    public function __construct(?BomColumns $columns = null)
    {
        if ($columns) {
            $this->sheets[$columns->companyId] = $columns;
        }
    }

    public function columns(string $companyId): BomColumns
    {
        return $this->sheets[$companyId] ??= new BomColumns($companyId);
    }

    /**
     * The budget's styles by name: Order Qty, Style Value, FOB, brand, job no, artwork, requisition serials.
     *
     * @param  Collection<int, PbRequisition>  $reqs
     * @return array<string, array<string, mixed>>
     */
    public static function styles(Collection $reqs): array
    {
        $out = [];
        // Each order's Order Qty and Style Value once, however many parts (rows) it has.
        foreach (OrderLink::countOnce($reqs)->sortBy('serial_no')->groupBy(fn ($r) => self::styleOf($r)) as $style => $rows) {
            $qty = round($rows->sum(fn ($r) => Formula::num($r->values['order_qty'] ?? null) ?? 0), 2);
            $value = round($rows->sum(fn ($r) => Formula::num($r->values['fob_value'] ?? null) ?? 0), 2);
            $first = $rows->first()->values ?? [];
            $art = $rows->first(fn ($r) => ! empty($r->values['artwork']))?->values['artwork'] ?? null;
            $out[(string) $style] = [
                'style' => (string) $style, 'order_qty' => $qty, 'style_value' => $value, 'fob' => $qty > 0 ? round($value / $qty, 4) : null,
                'brand' => Formula::text($first['brand'] ?? ''), 'job_no' => Formula::text($first['job_no'] ?? ''),
                'artwork_url' => $art ? "/api/files/{$art}/download" : null, 'serials' => $rows->pluck('serial_no')->all(),
            ];
        }
        uksort($out, 'strnatcasecmp');

        return $out;
    }

    public static function styleOf(PbRequisition $r): string
    {
        return trim(Formula::text($r->values['style'] ?? ''));
    }

    /** A style's cells on the worksheet (what a line formula may read of it). @param  array<string, mixed>  $info  from styles() */
    public static function cells(array $info, int $sl): array
    {
        return [
            'sl' => $sl, 'brand' => $info['brand'] ?: null, 'job_no' => $info['job_no'] ?: null, 'style' => $info['style'] ?: null, 'artwork' => $info['artwork_url'],
            'order_qty' => $info['order_qty'], 'fob' => $info['fob'], 'style_value' => $info['style_value'],
        ];
    }

    /**
     * One BOM worked out: its styles (those still in the budget) with their lines and totals.
     *
     * @param  array<string, array<string, mixed>>  $budgetStyles  from styles()
     * @return array<string, mixed>
     */
    public function bom(PbBom $bom, array $budgetStyles, ?PbBudget $budget = null): array
    {
        $cols = $this->columns($bom->company_id);
        $styles = [];
        foreach ($bom->styles()->with('lines')->get() as $bs) {
            if (isset($budgetStyles[$bs->style])) {
                $styles[] = $this->style($cols, $bs, $budgetStyles[$bs->style], count($styles) + 1);
            }
        }
        $t = fn (string $k) => round(array_sum(array_map(fn ($s) => $s[$k], $styles)), 2);
        $qty = $t('order_qty');
        $value = $t('value');

        return [
            'bom_no' => $bom->label($budget), 'status' => $bom->status, 'version' => $bom->version,
            'finalized_at' => $bom->finalized_at?->toIso8601String(), 'styles' => $styles,
            'totals' => ['order_qty' => $qty, 'style_value' => $t('style_value'), 'value' => $value, 'cost_pc' => $qty > 0 ? round($value / $qty, 4) : 0.0,
                'acc_pct' => $t('style_value') > 0 ? round($value / $t('style_value') * 100, 2) : 0.0],
        ];
    }

    /** @param  array<string, mixed>  $info  the style from styles() */
    private function style(BomColumns $cols, PbBomStyle $bs, array $info, int $sl): array
    {
        $cells = self::cells($info, $sl);
        $lines = $bs->lines->map(fn (PbBomLine $l) => $cols->work($l->values ?? [], $cells))->all();
        $totals = [];
        foreach ($cols->columns as $c) {
            if ($c->kpi === 'SUM' || $c->kpi === 'AVG') {
                $nums = array_filter(array_map(fn ($l) => Formula::num($l[$c->key] ?? null), $lines), fn ($n) => $n !== null);
                $totals[$c->key] = $nums === [] ? null : round($c->kpi === 'SUM' ? array_sum($nums) : array_sum($nums) / count($nums), 6);
            }
        }
        $value = round(array_sum(array_map(fn ($l) => Formula::num($l[BomCatalog::VALUE] ?? null) ?? 0, $lines)), 2);

        return $info + [
            'cells' => $cells, 'lines' => $lines, 'totals' => $totals, 'value' => $value,
            'acc_pct' => $info['style_value'] > 0 ? round($value / $info['style_value'] * 100, 2) : 0.0,
        ];
    }

    /**
     * Where a budget's accessories stand: each style's BOM (or none), the
     * BOMs, the Accessories cost and the state — PENDING (nothing yet),
     * PARTIAL, CREATED (every style in a final BOM).
     *
     * @param  Collection<int, PbRequisition>|null  $reqs
     * @return array<string, mixed>
     */
    public function summary(PbBudget $b, ?Collection $reqs = null): array
    {
        $budgetStyles = self::styles($reqs ?? $b->requisitions()->get());
        $boms = $b->boms()->get();
        $placed = PbBomStyle::query()->where('budget_id', $b->id)->get()->keyBy('style');
        $byId = $boms->keyBy('id');
        $views = $boms->map(fn (PbBom $bom) => $this->bom($bom, $budgetStyles, $b))->values()->all();
        $styles = [];
        foreach ($budgetStyles as $name => $info) {
            $bom = ($p = $placed->get($name)) ? $byId->get($p->bom_id) : null;
            $styles[] = ['style' => $name, 'order_qty' => $info['order_qty'], 'bom_no' => $bom?->label($b), 'bom_status' => $bom?->status];
        }
        $count = count($styles);
        $budgeted = count(array_filter($styles, fn ($s) => $s['bom_status'] === PbBom::FINAL));
        $started = count(array_filter($styles, fn ($s) => $s['bom_no'] !== null));

        return [
            'styles' => $styles, 'boms' => $views, 'count' => $count, 'budgeted' => $budgeted,
            'state' => $count > 0 && $budgeted === $count ? 'CREATED' : ($started > 0 ? 'PARTIAL' : 'PENDING'),
            'total' => round(array_sum(array_map(fn ($v) => $v['totals']['value'], $views)), 2),
            'done' => $count > 0 && $budgeted === $count,
        ];
    }
}
