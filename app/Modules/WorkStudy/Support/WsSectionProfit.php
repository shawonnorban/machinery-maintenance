<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Support;

use App\Modules\WorkStudy\Models\WsSectionResult;
use App\Modules\WorkStudy\Models\WsSectionTarget;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What Work Study → Section Profitability (AnnotechERP 14/08) shares: the
 * company's sections, a month's income targets (Target Income = daily target
 * × Avg Rate × WrkDay/Mn) and a day's results, each section opening at its
 * day's share of the month — as Brickland works them out.
 */
final class WsSectionProfit
{
    /** WrkDay/Mn a section opens at, as Brickland's sheet does. */
    public const DEFAULT_DAYS = 26;

    /** The unit Brickland's sheet shows beside every section. */
    public const UNIT = 'Pcs';

    /** Target Income: daily target × Avg Rate × WrkDay/Mn — Brickland's 195,000 for 5,000 a day at 1.5 over 26 days. */
    public static function income(float $daily, float $rate, int $days): float
    {
        return round($daily * $rate * $days, 2);
    }

    /** Profit / Loss / Break-even, as the Records grid labels a result. */
    public static function standing(float $profit): string
    {
        return $profit > 0 ? 'PROFIT' : ($profit < 0 ? 'LOSS' : 'BREAK_EVEN');
    }

    /** The company's active sections by name. @return Collection<int, object> */
    public static function sections(): Collection
    {
        return DB::table('sections')->leftJoin('departments', 'departments.id', '=', 'sections.department_id')
            ->where('sections.company_id', app(TenantContext::class)->companyId())->where('sections.status', 'ACTIVE')
            ->orderBy('sections.name')->get(['sections.id', 'sections.name', 'departments.name as department']);
    }

    /** The sub-section a section opens at, as Brickland fills it: "Cutting [BDT=]". */
    public static function defaultSubSection(string $section): string
    {
        return "{$section} [BDT=]";
    }

    /**
     * Every section for a month: what is set, or the blank row it opens at.
     *
     * @return list<array<string, mixed>>
     */
    public static function targetSheet(string $period): array
    {
        $saved = WsSectionTarget::query()->where('period', $period)->get()->keyBy('section_id');

        return self::sections()->map(function ($s) use ($saved): array {
            $t = $saved->get($s->id);

            return [
                'id' => $t?->id, 'section_id' => $s->id, 'section' => $s->name, 'unit' => self::UNIT,
                'sub_section' => $t ? $t->sub_section : self::defaultSubSection($s->name),
                'daily_target' => $t?->daily_target, 'daily_target_inhouse' => $t?->daily_target_inhouse, 'daily_target_subcon' => $t?->daily_target_subcon,
                'avg_rate' => $t?->avg_rate, 'work_days' => $t ? $t->work_days : self::DEFAULT_DAYS,
                'target_income' => $t ? $t->target_income : 0.0, 'oh_calculated' => $t?->oh_calculated,
            ];
        })->values()->all();
    }

    /**
     * Every section for a day: what was posted, or the row it opens at —
     * IncomeTarget at the day's share of the month (daily target × Avg Rate)
     * and Prod Target at the daily target.
     *
     * @return list<array<string, mixed>>
     */
    public static function resultSheet(string $date): array
    {
        $saved = WsSectionResult::query()->whereDate('result_date', $date)->get()->keyBy('section_id');
        $targets = WsSectionTarget::query()->where('period', Carbon::parse($date)->format('Y-m'))->get()->keyBy('section_id');

        return self::sections()->map(function ($s) use ($saved, $targets): array {
            $r = $saved->get($s->id);
            $t = $targets->get($s->id);
            $row = [
                'id' => $r?->id, 'section_id' => $s->id, 'section' => $s->name,
                'income_target' => $r ? $r->income_target : ($t ? round($t->daily_target * $t->avg_rate, 2) : 0.0),
                'income' => $r ? $r->income : 0.0,
                'prod_target' => $r ? $r->prod_target : ($t ? $t->daily_target : 0.0),
                'prod_qty' => $r ? $r->prod_qty : 0.0,
                'oh_cost' => $r ? $r->oh_cost : 0.0, 'mat_cost' => $r ? $r->mat_cost : 0.0, 'expense' => $r ? $r->expense : 0.0,
                'comments' => $r?->comments,
            ];
            $row['profit'] = $r ? $r->profit() : 0.0;

            return $row;
        })->values()->all();
    }
}
