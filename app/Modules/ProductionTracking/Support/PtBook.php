<?php

declare(strict_types=1);

namespace App\Modules\ProductionTracking\Support;

use App\Modules\ProductionTracking\Models\PtLineCapacity;
use App\Modules\ProductionTracking\Models\PtSetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Production Tracking's rules (AnnotechERP 30): which lines it follows, its
 * settings, a line's capacity on a day and the target a method makes of it.
 *
 * - Sewing lines are Capacity Planning's planning lines — the lines Assembly
 *   Floor scans bundles onto; finishing lines are Company Management's
 *   production lines of type FINISHING.
 * - Methods (Brickland's SRS §6.3–6.5):
 *   A Fixed hourly  = pieces per hour × planned hours;
 *   B Fixed daily   = pieces per day;
 *   C Efficiency-adjusted = pieces per hour × planned hours × efficiency %,
 *     refused when the capacity already includes efficiency;
 *   D SMV-based     = operators × hours × 60 × efficiency % ÷ SMV, only when
 *     Settings has the SMV method on.
 */
final class PtBook
{
    public const SECTIONS = ['SEWING', 'FINISHING'];

    public const ENTRY_SECTIONS = ['CUTTING', 'SEWING', 'FINISHING'];

    public const METHODS = ['A', 'B', 'C', 'D'];

    public const STATUSES = ['DRAFT', 'PENDING_APPROVAL', 'APPROVED', 'REJECTED', 'CANCELLED'];

    public const TZ = 'Asia/Dhaka';

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TZ)->startOfDay();
    }

    /** The company's settings, created with Brickland's defaults on first use. */
    public static function settings(string $companyId): PtSetting
    {
        return PtSetting::query()->firstOrCreate(['company_id' => $companyId], [
            'refresh_seconds' => 30, 'stale_seconds' => 180, 'on_track_pct' => 90, 'below_pct' => 75, 'approval_workflow' => true, 'smv_method' => false,
        ]);
    }

    /**
     * The lines a section follows: id => {id, code, name, floor, active}.
     *
     * @return Collection<string, object>
     */
    public static function lines(string $companyId, string $section): Collection
    {
        if ($section === 'FINISHING') {
            return DB::table('production_lines as l')->leftJoin('floors as f', 'f.id', '=', 'l.floor_id')
                ->where('l.company_id', $companyId)->where('l.line_type', 'FINISHING')
                ->orderBy('l.code')->get(['l.id', 'l.code', 'l.name', 'f.name as floor', 'l.status'])
                ->map(fn ($l) => (object) ['id' => $l->id, 'code' => $l->code, 'name' => $l->name, 'floor' => $l->floor, 'active' => $l->status === 'ACTIVE', 'section' => 'FINISHING'])
                ->keyBy('id');
        }

        return DB::table('planning_lines as l')->leftJoin('floors as f', 'f.id', '=', 'l.floor_id')
            ->where('l.company_id', $companyId)->orderBy('l.display_order')->orderBy('l.code')
            ->get(['l.id', 'l.code', 'l.name', 'f.name as floor', 'l.active'])
            ->map(fn ($l) => (object) ['id' => $l->id, 'code' => $l->code, 'name' => $l->name, 'floor' => $l->floor, 'active' => (bool) $l->active, 'section' => 'SEWING'])
            ->keyBy('id');
    }

    /** "LN-001 — 1" */
    public static function label(?object $line): ?string
    {
        return $line ? trim($line->code.' — '.$line->name) : null;
    }

    /** The capacity in force on a line on a day, if any. */
    public static function capacityOn(string $section, string $lineId, string $date): ?PtLineCapacity
    {
        return PtLineCapacity::query()->where('section', $section)->where('line_id', $lineId)->where('active', true)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->orderByDesc('effective_from')->orderByDesc('created_at')->first();
    }

    /**
     * The target a method makes of a capacity.
     *
     * @param  array{operators?: ?int, smv?: ?float, efficiency?: ?float}  $extra
     * @return array{target: int, formula: string}
     */
    public static function calculate(PtLineCapacity $capacity, string $method, float $hours, PtSetting $settings, array $extra = []): array
    {
        $std = (float) $capacity->standard_capacity;
        $eff = (float) ($extra['efficiency'] ?? $capacity->default_efficiency ?? 0);
        $perHour = $capacity->capacity_type === 'PER_HOUR';

        return match ($method) {
            'A' => $perHour
                ? ['target' => (int) round($std * $hours), 'formula' => "{$std} × {$hours} h"]
                : throw ValidationException::withMessages(['method' => __('productiontracking.v.method_needs_hourly')]),
            'B' => ! $perHour
                ? ['target' => (int) round($std), 'formula' => "{$std} / day"]
                : ['target' => (int) round($std * $hours), 'formula' => "{$std} × {$hours} h"],
            'C' => match (true) {
                (bool) $capacity->includes_efficiency => throw ValidationException::withMessages(['method' => __('productiontracking.v.method_c_refused')]),
                $eff <= 0 => throw ValidationException::withMessages(['method' => __('productiontracking.v.method_c_efficiency')]),
                default => ['target' => (int) round(($perHour ? $std * $hours : $std) * $eff / 100), 'formula' => ($perHour ? "{$std} × {$hours} h" : "{$std} / day")." × {$eff}%"],
            },
            'D' => self::smvTarget($settings, $hours, $eff, $extra),
            default => throw ValidationException::withMessages(['method' => __('productiontracking.v.method')]),
        };
    }

    /** @return array{target: int, formula: string} */
    private static function smvTarget(PtSetting $settings, float $hours, float $eff, array $extra): array
    {
        if (! $settings->smv_method) {
            throw ValidationException::withMessages(['method' => __('productiontracking.v.method_d_off')]);
        }
        $operators = (int) ($extra['operators'] ?? 0);
        $smv = (float) ($extra['smv'] ?? 0);
        if ($operators <= 0 || $smv <= 0 || $eff <= 0) {
            throw ValidationException::withMessages(['method' => __('productiontracking.v.method_d_inputs')]);
        }

        return ['target' => (int) floor($operators * $hours * 60 * $eff / 100 / $smv), 'formula' => "({$operators} × {$hours} h × 60 × {$eff}%) ÷ {$smv}"];
    }

    /** Green, amber, red or grey for an achievement %, by the settings' thresholds. */
    public static function band(?float $pct, PtSetting $settings): string
    {
        return match (true) {
            $pct === null => 'NO_TARGET',
            $pct >= $settings->on_track_pct => 'ON_TRACK',
            $pct >= $settings->below_pct => 'ATTENTION',
            default => 'BELOW',
        };
    }

    /**
     * Today / Last 3 Days / Last 7 Days / Custom → a from–to pair of dates.
     *
     * @return array{0: string, 1: string}
     */
    public static function period(?string $range, ?string $from, ?string $to): array
    {
        $today = self::today();

        return match ($range) {
            '3D' => [$today->subDays(2)->toDateString(), $today->toDateString()],
            '7D' => [$today->subDays(6)->toDateString(), $today->toDateString()],
            'CUSTOM' => [CarbonImmutable::parse($from ?? $today)->toDateString(), CarbonImmutable::parse($to ?? $from ?? $today)->toDateString()],
            default => [$today->toDateString(), $today->toDateString()],
        };
    }
}
