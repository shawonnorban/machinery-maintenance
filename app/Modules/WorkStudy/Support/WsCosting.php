<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Support;

use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Planning\Models\PlanningDailyPlan;
use App\Modules\Planning\Models\PlanningLine;
use App\Modules\WorkStudy\Models\WsLineCosting;
use App\Modules\WorkStudy\Models\WsLineProduction;
use App\Modules\WorkStudy\Models\WsOhCost;
use App\Modules\WorkStudy\Models\WsOhHead;
use App\Modules\WorkStudy\Models\WsOhSection;
use App\Modules\WorkStudy\Models\WsSalaryRate;
use App\Modules\WorkStudy\Models\WsSewingTarget;
use App\Modules\WorkStudy\Models\WsSpec;
use Illuminate\Support\Collection;

/**
 * What Work Study → Costing (AnnotechERP 14/05) shares:
 *
 * - the overhead sections and their cost heads as Brickland ships them, made
 *   the first time Overhead Cost Allocation is opened;
 * - the monthly sheet — every active head in section order, the working days
 *   a blank line reads from the line above;
 * - a floor's day — the orders loaded on it, the lines and what each made —
 *   and the profit or loss each line ran at.
 *
 * The orders a floor has loaded and each line's OP, ASO, hours and target
 * come from Garment Target Setting's sheet (14/03), or the day's line plan
 * (Capacity Planning, 12/02) where no target was set. The OP + ASO salary and the
 * overtime rate come from Monthly Salary & OT Rates (14/06); a month without
 * them bills no salary or overtime and the sheet says so, as Brickland's does.
 */
final class WsCosting
{
    /** The six production columns the printed sheet carries, in its order. */
    public const COLUMNS = ['cutting', 'sewing', 'knitting', 'dyeing', 'dyeing_finishing', 'printing'];

    /** The columns a garment floor's day carries the overhead of. */
    public const GARMENT_COLUMNS = ['cutting', 'sewing'];

    /** The overtime tiers a line's output is split into after general hours. */
    public const TIERS = ['aot', 'eot', '2ot', '5ot'];

    /** section => [allocation target?, heads] — Brickland's sections and their particulars. */
    public const SECTIONS = [
        'Garments Section' => [false, ['Overtime', 'Wages And Allowance', 'Salary And Allowance', 'Carriage Outward And Inward', 'Conveyence', 'Carrage inward', 'Depreciation on GMTs M/C', 'Earn Leave Benefits', 'Export Tax', 'Festival Bonus', 'Floor Rent(140560 Sf x 8)', 'Garments Chemical', 'Lunch Expense', 'Maternity Allowance', 'Mobile Expenses', 'Needle Germents', 'Night Allowance', 'Night Bill', 'Production Bonus']],
        'Cutting Section' => [true, ['wages And Allowance', 'Overtime', 'Salary And Allowance', 'Conveyance', 'Earn Leave Benefit', 'Festival Bonus', 'Floor Rent (32000 sf x 8)', 'Mobile Expense', 'Tiffin bill', 'Night Bill', 'Depreciation M/C']],
        'Knitting Section' => [true, ['Wages And Allowance', 'Salary And Allowance', 'Overtime', 'Carriage Inward', 'service charge', 'Conveyance', 'Depreciation on M/C', 'Ern Leave Benefit', 'Festival Bonus', 'Floor Rent(43140 sf x 8)', 'Clearing charge', 'Knitting Oil', 'Lunch Exp', 'Mobile Exp', 'Needle Knitting', 'Repaire And Maintanance', 'Utility EMs', 'Tiffin Exp']],
        'Dyeing Section' => [true, ['Wages And Allowance', 'Salary And Allowance', 'Overtime', 'Depreciation on M/c', 'Carriage Inward', 'Conveyance', 'Earn Leave Benefit', 'Morisus exp', 'Festival Bonus', 'Floor Rent (32000 sf x 8)', 'Lunch Exp', 'Mobile Exp', 'Repaire And Maintanance', 'Rent Land And Landed', 'Production bonus', 'Tiffin Exp', 'Clearing Charge-Dyeing']],
        'Dyeing Finishing Section' => [true, ['Wages And Allowance', 'Salary And Allowance', 'Overtime', 'Depreciation on M/c', 'Clearing charge', 'Conveyance', 'Earn Leave Benefit', 'Entertainment', 'Festival Bonus', 'Floor Rent (32000 sf x 8)', 'Lunch Exp', 'Mobile Exp', 'Repaire And Maintanance', 'Rent Land And Landed', 'Production bonus', 'Tiffin Exp', 'Carriage inward']],
        'Printing Section' => [true, ['Wages And Allowance', 'Salary And Allowance', 'Overtime', 'Conveyance', 'Depreciation on M/c', 'Earn Leave Benefit', 'Festival Bonus', 'Floor Rent (32000 Sf x 8)', 'Lunch Exp', 'Mobile Exp', 'Night Aloowance', 'Night Bill', 'Printing Exp(Graphics)', 'Maternity/S.F', 'Tiffin Exp']],
        'Production Overhead' => [false, ['Dinner Expense', 'Electric Goods', 'Tax', 'Entertainment', 'Factory Maitenance', 'Municipal Tax', 'Fuel And Lubricant (Generator)', 'Vat', 'Fuel Expenses-Generator', 'Insurance Premium', 'Loading And Unloading', 'Lunch Expense -PO', 'Miscellanneous Expenses', 'Repair And Maintenance', 'Service Benefit', 'Spare PArts-Generator']],
        'Administrative Expenses' => [false, ['Salary And Allowance', 'Overtime', 'Travelling exp', 'Tiffin Exp.-H/O', 'Miscelleneous Exp.']],
        'Commercial Overhead' => [false, ['B/L Expenses', 'BKMEA Expenses', 'EPB Expenses', 'F.C.C Expenses', 'Forwading Charge', 'GSP Expenses', 'L/C Expenses -Export', 'Stamp Expenses', 'UD Charge', 'Document expenses', 'Central Fund']],
        'Financial Overhead' => [false, ['Bank Charge', 'Interest Expenses']],
        'Head Office Expenses' => [false, ['Salary And Allowance', 'Books And News paper', 'Cartidge And Colour', 'Conveyance-H/O', "Crokery's And Cultery", 'Dining Exp. Head Office', 'Dinner Exp. Head Office', 'Donation And Subcription', 'Electricity Expenses', 'Earn Leave Benefit', 'Entertainment-H/O', 'Entertainment- Buyer', 'Festival Bonus-H/O', 'Fuel And Lubricant H/O', 'Garrage Rent Head', 'Internet Expenses', 'Lunch Exp. Head Office', 'Medical Expenses-H/O']],
    ];

    /** Brickland's sections and heads, the first time a company opens the allocation. */
    public static function ensure(string $companyId): void
    {
        if (WsOhSection::query()->exists()) {
            return;
        }
        $order = 0;
        foreach (self::SECTIONS as $name => [$target, $heads]) {
            $section = WsOhSection::query()->create(['company_id' => $companyId, 'name' => $name, 'sort_order' => $order += 1, 'is_target' => $target]);
            foreach ($heads as $i => $head) {
                WsOhHead::query()->create(['company_id' => $companyId, 'section_id' => $section->id, 'name' => $head, 'sort_order' => $i + 1, 'active' => true]);
            }
        }
    }

    /** A month as the sheet names it: 2026-10. */
    public static function isPeriod(?string $period): bool
    {
        return is_string($period) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) === 1;
    }

    /**
     * Every head the sheet lists, in section then head order: its section, and
     * whether it is still active (an inactive head with figures still shows).
     *
     * @return Collection<int, WsOhHead>
     */
    public static function heads(bool $activeOnly = true): Collection
    {
        $sections = WsOhSection::query()->orderBy('sort_order')->orderBy('name')->get()->keyBy('id');
        $heads = WsOhHead::query()->when($activeOnly, fn ($q) => $q->where('active', true))->get()
            ->filter(fn (WsOhHead $h) => $sections->has($h->section_id))
            ->sortBy(fn (WsOhHead $h) => sprintf('%05d-%05d-%s', $sections[$h->section_id]->sort_order, $h->sort_order, $h->name))->values();
        $heads->each(fn (WsOhHead $h) => $h->setRelation('section', $sections[$h->section_id]));

        return $heads;
    }

    /**
     * A month's sheet: every head with its figures, the working days a blank
     * line reads from the line above.
     *
     * @return list<array<string, mixed>>
     */
    public static function sheet(string $period): array
    {
        $costs = WsOhCost::query()->where('period', $period)->get()->keyBy('head_id');
        $ordered = self::heads(false)->filter(fn (WsOhHead $h) => $h->active || $costs->has($h->id));
        $rows = [];
        foreach ($ordered as $head) {
            $c = $costs[$head->id] ?? null;
            $row = ['head_id' => $head->id, 'section' => $head->section->name, 'particular' => $head->name, 'monthly_expense' => $c?->monthly_expense, 'work_days' => $c?->work_days];
            foreach (self::COLUMNS as $col) {
                $row[$col] = $c?->{$col};
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /** What a garment floor carries for one day of the month: the cutting and sewing money over each line's working days. */
    public static function dailyOverhead(string $period): float
    {
        $costs = WsOhCost::query()->where('period', $period)->get()->keyBy('head_id');
        $days = null;
        $total = 0.0;
        foreach (self::heads(false) as $head) {
            $c = $costs[$head->id] ?? null;
            if (! $c) {
                continue;
            }
            // A line left without working days reads the line above.
            $days = $c->work_days ?: $days;
            $money = array_sum(array_map(fn ($col) => (float) $c->{$col}, self::GARMENT_COLUMNS));
            if ($money > 0 && $days) {
                $total += $money / $days;
            }
        }

        return round($total, 6);
    }

    /** The days a month's salary is spread over, as Brickland's sheet divides it. */
    public const SALARY_DAYS = 26;

    /**
     * The month's average OP and ASO salary and overtime rate, from Monthly
     * Salary & OT Rates (14/06); none until that month is costed.
     *
     * @return array{op_salary: float, aso_salary: float, op_ot: float, aso_ot: float}|null
     */
    public static function salaryRate(string $period): ?array
    {
        $r = WsSalaryRate::query()->where('period', $period)->first();

        return $r ? ['op_salary' => (float) $r->op_salary, 'aso_salary' => (float) $r->aso_salary, 'op_ot' => (float) $r->op_ot_rate, 'aso_ot' => (float) $r->aso_ot_rate] : null;
    }

    /** A buyer as the boxes name it: LPP (LPP). */
    public static function buyerLabel(?Buyer $b): ?string
    {
        return $b ? trim(($b->short_name ?: $b->name).($b->code ? " ({$b->code})" : '')) : null;
    }

    /**
     * What a floor has loaded on a day, line by line: from the morning's
     * target sheet (Garment Target Setting, 14/03) when it has been set, else
     * from the day's line plan (Capacity Planning, 12/02) — each order with its
     * buyer, style, item, SMV and FOB, and the line's OP, ASO, hours and target.
     *
     * @return list<array<string, mixed>>
     */
    public static function loaded(string $floorId, string $date): array
    {
        $targets = WsSewingTarget::query()->where('floor_id', $floorId)->whereDate('target_date', $date)->whereNotNull('order_style')->where('is_off', false)->orderBy('sort_order')->get();
        if ($targets->isNotEmpty()) {
            $orders = Order::query()->whereIn('order_no', $targets->pluck('order_style')->unique())->get()->keyBy('order_no');

            return $targets->map(function (WsSewingTarget $t) use ($orders) {
                $o = $orders[$t->order_style] ?? null;

                return [
                    'planning_line_id' => $t->planning_line_id, 'buyer_id' => $t->buyer_id ?? $o?->buyer_id, 'order_no' => $t->order_style, 'style_no' => $o?->style_no ?? $t->order_style,
                    'item' => $t->gmt_item ?? $o?->item, 'smv' => $t->smv, 'fob' => $t->fob ?? $o?->unit_price,
                    'op' => $t->used_op, 'aso' => $t->used_aso, 'wh' => $t->wh, 'target' => (int) $t->target_gen + (int) $t->target_ot,
                ];
            })->values()->all();
        }
        $lines = PlanningLine::query()->where('floor_id', $floorId)->pluck('id');
        $plans = PlanningDailyPlan::query()->whereIn('planning_line_id', $lines)->whereDate('plan_date', $date)->orderBy('planning_line_id')->get();
        $orders = Order::query()->whereIn('id', $plans->pluck('order_id')->filter()->unique())->get()->keyBy('id');
        $specs = WsSpec::query()->whereIn('style_no', $orders->pluck('style_no')->filter()->unique())->orderByDesc('variation_no')->get()->groupBy('style_no');

        return $plans->filter(fn ($p) => isset($orders[$p->order_id]))->map(function ($plan) use ($orders, $specs) {
            $o = $orders[$plan->order_id];
            $spec = ($specs[$o->style_no] ?? collect())->first();

            return [
                'planning_line_id' => $plan->planning_line_id, 'buyer_id' => $o->buyer_id, 'order_no' => $o->order_no, 'style_no' => $o->style_no,
                'item' => $o->item, 'smv' => $plan->smv, 'fob' => $o->unit_price,
                'op' => $spec?->op_manpower ?? $plan->manpower, 'aso' => $spec?->aso_manpower, 'wh' => $plan->hours, 'target' => $plan->daily_target,
            ];
        })->values()->all();
    }

    /**
     * Order Config Data: the orders the floor has loaded today, plus any
     * already costed here — each as costed, or as its order would cost it.
     *
     * @return list<array<string, mixed>>
     */
    public static function configRows(string $floorId, string $date): array
    {
        $costed = WsLineCosting::query()->where('floor_id', $floorId)->whereDate('cost_date', $date)->orderBy('order_no')->get()->keyBy('order_no');
        $rows = [];
        foreach (self::loaded($floorId, $date) as $l) {
            $rows[$l['order_no']] ??= [
                'id' => null, 'buyer_id' => $l['buyer_id'], 'style_no' => $l['style_no'], 'order_no' => $l['order_no'], 'gmt_item' => $l['item'],
                'bgt_as_cm_pct' => null, 'exc_rate' => null, 'smv' => $l['smv'], 'fob' => $l['fob'],
                'rate_cut' => null, 'rate_sew' => null, 'rate_fin' => null, 'loaded' => true,
            ];
        }
        foreach ($costed as $no => $c) {
            $rows[$no] = [
                'id' => $c->id, 'buyer_id' => $c->buyer_id, 'style_no' => $c->style_no, 'order_no' => $c->order_no, 'gmt_item' => $c->gmt_item,
                'bgt_as_cm_pct' => $c->bgt_as_cm_pct, 'exc_rate' => $c->exc_rate, 'smv' => $c->smv, 'fob' => $c->fob,
                'rate_cut' => $c->rate_cut, 'rate_sew' => $c->rate_sew, 'rate_fin' => $c->rate_fin, 'loaded' => isset($rows[$no]),
            ];
        }

        return array_values($rows);
    }

    /**
     * Line Wise Prod: every line on the floor, as entered, or filled from what
     * the floor loaded that morning — its order, OP, ASO, hours and target.
     *
     * @return list<array<string, mixed>>
     */
    public static function lineRows(string $floorId, string $date): array
    {
        $lines = PlanningLine::query()->where('floor_id', $floorId)->where('active', true)->orderBy('display_order')->orderBy('code')->get();
        $saved = WsLineProduction::query()->where('floor_id', $floorId)->whereDate('prod_date', $date)->get()->keyBy('planning_line_id');
        // A line running two orders is entered against the first.
        $loaded = collect(self::loaded($floorId, $date))->groupBy('planning_line_id')->map->first();
        $rows = [];
        foreach ($lines as $line) {
            $base = ['planning_line_id' => $line->id, 'line' => $line->code];
            if ($p = $saved[$line->id] ?? null) {
                $rows[] = $base + $p->only(['buyer_id', 'order_no', 'item_name', 'op', 'aso', 'wh', 'target', 'prod_g', 'prod_aot', 'prod_eot', 'prod_2ot', 'prod_5ot', 'manot_aot', 'manot_eot', 'manot_2ot', 'manot_5ot']) + ['saved' => true];

                continue;
            }
            $l = $loaded[$line->id] ?? null;
            $rows[] = $base + [
                'buyer_id' => $l['buyer_id'] ?? null, 'order_no' => $l['order_no'] ?? null, 'item_name' => $l['item'] ?? null,
                'op' => $l['op'] ?? null, 'aso' => $l['aso'] ?? null, 'wh' => $l['wh'] ?? null, 'target' => $l['target'] ?? null,
                'prod_g' => null, 'prod_aot' => null, 'prod_eot' => null, 'prod_2ot' => null, 'prod_5ot' => null,
                'manot_aot' => null, 'manot_eot' => null, 'manot_2ot' => null, 'manot_5ot' => null, 'saved' => false,
            ];
        }

        return $rows;
    }

    /** A line's output across general hours and the overtime tiers. */
    public static function produced(WsLineProduction $p): int
    {
        return (int) $p->prod_g + array_sum(array_map(fn ($t) => (int) $p->{"prod_{$t}"}, self::TIERS));
    }

    /**
     * Overall Profit Lost: what each line that ran earned in CM against its
     * labour and its share of the day's overhead, general hours and each
     * overtime tier apart. The overhead is shared equally by the lines that
     * ran, then follows each line's output across the tiers.
     *
     * @return array{rows: list<array<string, mixed>>, totals: array<string, float>, no_rate: bool, no_overhead: bool}
     */
    public static function profit(string $floorId, string $date): array
    {
        $period = substr($date, 0, 7);
        $lines = PlanningLine::query()->whereIn('id', WsLineProduction::query()->where('floor_id', $floorId)->whereDate('prod_date', $date)->pluck('planning_line_id'))->get()->keyBy('id');
        $ran = WsLineProduction::query()->where('floor_id', $floorId)->whereDate('prod_date', $date)->get()
            ->filter(fn (WsLineProduction $p) => self::produced($p) > 0)
            ->sortBy(fn (WsLineProduction $p) => sprintf('%05d-%s', $lines[$p->planning_line_id]->display_order ?? 0, $lines[$p->planning_line_id]->code ?? ''))->values();
        $costs = WsLineCosting::query()->where('floor_id', $floorId)->whereDate('cost_date', $date)->get()->keyBy('order_no');
        $buyers = Buyer::query()->whereIn('id', $ran->pluck('buyer_id')->filter()->unique())->get()->keyBy('id');
        $rate = self::salaryRate($period);
        $overhead = self::dailyOverhead($period);
        $share = $ran->count() ? $overhead / $ran->count() : 0.0;
        $rows = [];
        $totals = ['fob_earn' => 0.0, 'total_cost' => 0.0, 'cm_earn' => 0.0, 'profit' => 0.0];
        foreach ($ran as $p) {
            $c = $costs[$p->order_no] ?? null;
            $made = self::produced($p);
            $perPiece = (float) $c?->rate_cut + (float) $c?->rate_sew + (float) $c?->rate_fin;
            $minutes = ((int) $p->op + (int) $p->aso) * (float) $p->wh * 60;
            $row = [
                'line' => $lines[$p->planning_line_id]->code ?? null, 'buyer' => $buyers[$p->buyer_id]->short_name ?? null, 'order_no' => $p->order_no, 'item_name' => $p->item_name,
                'effi' => $minutes > 0 && $c ? round($made * (float) $c->smv / $minutes * 100, 2) : 0.0,
                'fob_earn' => round($made * (float) $c?->fob, 2),
            ];
            // A day's wages: each operator's and ASO's average monthly salary over 26 days; an overtime hour at the
            // operators' and ASOs' rates in proportion to how many of each the line runs.
            $heads = (int) $p->op + (int) $p->aso;
            $salary = $rate ? ((int) $p->op * $rate['op_salary'] + (int) $p->aso * $rate['aso_salary']) / self::SALARY_DAYS : 0.0;
            $otHour = $rate && $heads > 0 ? ((int) $p->op * $rate['op_ot'] + (int) $p->aso * $rate['aso_ot']) / $heads : 0.0;
            $cost = 0.0;
            $earn = 0.0;
            foreach (['g', ...self::TIERS] as $tier) {
                $pcs = (int) $p->{"prod_{$tier}"};
                $oh = $made > 0 ? $share * $pcs / $made : 0.0;
                $labour = $tier === 'g' ? $salary : (float) $p->{"manot_{$tier}"} / 60 * $otHour;
                $cm = $pcs * $perPiece;
                $row[$tier === 'g' ? 'sal_g' : "otbill_{$tier}"] = round($labour, 2);
                $row["oh_{$tier}"] = round($oh, 2);
                $row["cm_{$tier}"] = round($cm, 2);
                $row["pl_{$tier}"] = round($cm - $labour - $oh, 2);
                $cost += $labour + $oh;
                $earn += $cm;
            }
            $row['total_cost'] = round($cost, 2);
            $row['cm_earn'] = round($earn, 2);
            $row['profit'] = round($earn - $cost, 2);
            foreach (['fob_earn', 'total_cost', 'cm_earn', 'profit'] as $k) {
                $totals[$k] += $row[$k];
            }
            $rows[] = $row;
        }

        return ['rows' => $rows, 'totals' => array_map(fn ($v) => round($v, 2), $totals), 'no_rate' => $rate === null, 'no_overhead' => $overhead <= 0];
    }
}
