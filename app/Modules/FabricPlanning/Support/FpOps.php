<?php

declare(strict_types=1);

namespace App\Modules\FabricPlanning\Support;

use App\Modules\FabricPlanning\Models\FpStatusArea;
use App\Modules\FabricPlanning\Models\FpStatusGroup;
use App\Modules\FabricPlanning\Models\FpStatusRule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * What Fabric Requirement → Operations (AnnotechERP 13/02) shares: the
 * constraint families, areas and score bands as Brickland ships them (made
 * the first time the ruleset is opened, the company's to change after), the
 * planning weeks (Saturday to Friday — Brickland keeps no week master), and
 * the running numbers of batch plans and schedule boards.
 */
final class FpOps
{
    /** sl => [code, name] */
    public const GROUPS = [1 => ['CD', 'Critical Date'], 2 => ['CP', 'Capacity'], 3 => ['LT', 'Lead Time'], 4 => ['CT', 'Critical Path'], 5 => ['BL', 'BP Load'], 6 => ['BF', 'BP Performance']];

    /** group sl => [[code, area, weighting], …] */
    public const AREAS = [
        1 => [['CD-SHIP', 'Days to shipment', 1], ['CD-PCD', 'Days to PCD', 0.8], ['CD-SLIP', 'Slippage against last plan', 0.6]],
        2 => [['CP-MC', 'Machine capacity used', 1], ['CP-HOUR', 'Capacity hours consumed', 0.9], ['CP-FREE', 'Free capacity remaining', 0.5]],
        3 => [['LT-PROD', 'Production lead time honoured', 1], ['LT-ORDER', 'Order lead time honoured', 0.7]],
        4 => [['CT-GREY', 'Grey fabric availability', 1], ['CT-DYES', 'Dyes and chemicals availability', 0.8], ['CT-LAB', 'Lab dip approval', 0.9]],
        5 => [['BL-FILL', 'Batch fill against machine load', 1], ['BL-SPLIT', 'Batch splitting required', 0.4]],
        6 => [['BF-RFT', 'Right first time', 1], ['BF-REDYE', 'Re-dye rate', 0.8], ['BF-ONTIME', 'Batch delivered on time', 0.9]],
    ];

    /** [from, to, code, description, colour, green rank] — every family starts on the same five bands. */
    public const RULES = [
        [0, 20, 'CRITICAL', 'Critical - escalate today', '#DC2626', 0],
        [20.01, 40, 'POOR', 'Poor - needs attention', '#EA580C', 0.25],
        [40.01, 60, 'FAIR', 'Fair - watch', '#CA8A04', 0.5],
        [60.01, 80, 'GOOD', 'Good - on plan', '#16A34A', 0.75],
        [80.01, 100, 'EXCELLENT', 'Excellent - ahead of plan', '#15803D', 1],
    ];

    public static function ensureRuleset(string $companyId): void
    {
        if (FpStatusGroup::query()->exists()) {
            return;
        }
        DB::transaction(function () use ($companyId): void {
            foreach (self::GROUPS as $sl => [$code, $name]) {
                $group = FpStatusGroup::query()->create(['company_id' => $companyId, 'sl' => $sl, 'code' => $code, 'name' => $name]);
                foreach (self::AREAS[$sl] as $i => [$areaCode, $area, $wt]) {
                    FpStatusArea::query()->create(['company_id' => $companyId, 'group_id' => $group->id, 'sl' => $i + 1, 'code' => $areaCode, 'name' => $area, 'wt_factor' => $wt, 'active' => true]);
                }
                foreach (self::RULES as $i => [$from, $to, $ruleCode, $desc, $colour, $rank]) {
                    FpStatusRule::query()->create(['company_id' => $companyId, 'group_id' => $group->id, 'score_from' => $from, 'score_to' => $to, 'status_code' => $ruleCode, 'status_desc' => $desc, 'color_code' => $colour, 'green_rank' => $rank, 'sort_order' => $i + 1]);
                }
            }
        });
    }

    /** The Saturday a day's planning week starts on. */
    public static function weekStart(CarbonImmutable|string $day): CarbonImmutable
    {
        $d = ($day instanceof CarbonImmutable ? $day : CarbonImmutable::parse($day))->startOfDay();

        return $d->subDays(($d->dayOfWeek + 1) % 7);
    }

    /** "2026-W44 · 24-Oct → 30-Oct": the ISO number of the week's Monday, and its days. */
    public static function weekLabel(CarbonImmutable|string $start): string
    {
        $s = self::weekStart($start);
        $monday = $s->addDays(2);

        return sprintf('%d-W%02d · %s → %s', $monday->isoWeekYear, $monday->isoWeek, $s->format('d-M'), $s->addDays(6)->format('d-M'));
    }

    /**
     * The weeks a plan can be placed in: eight back to twenty-six ahead, and any already used.
     *
     * @param  iterable<string>  $used
     * @return list<array{value: string, label: string}>
     */
    public static function weekOptions(iterable $used = []): array
    {
        $now = self::weekStart(CarbonImmutable::today());
        $weeks = [];
        for ($i = -8; $i <= 26; $i++) {
            $weeks[$now->addWeeks($i)->toDateString()] = true;
        }
        foreach ($used as $w) {
            $weeks[self::weekStart((string) $w)->toDateString()] = true;
        }
        ksort($weeks);

        return array_map(fn ($w) => ['value' => $w, 'label' => self::weekLabel($w)], array_keys($weeks));
    }

    /** The next running number: BP-00001, SP-00001 … per company. */
    public static function nextNumber(string $table, string $column, string $prefix, string $companyId): string
    {
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ["{$table}:{$companyId}"]);
        $last = DB::table($table)->where('company_id', $companyId)->where($column, 'like', "{$prefix}-%")->orderByDesc($column)->value($column);

        return sprintf('%s-%05d', $prefix, $last ? (int) substr((string) $last, strlen($prefix) + 1) + 1 : 1);
    }
}
