<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Services;

use App\Modules\Asset\Models\Asset;
use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Breakdown\Models\DowntimeRecord;
use App\Modules\Calendar\Services\WorkingTimeService;
use App\Modules\Maintenance\Models\MaintenanceSchedule;
use App\Modules\Settings\Services\SettingsResolver;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The KPI definitions (SRS 31.1).
 *
 * One class, because rule 7 says a dashboard and a report showing the same KPI
 * for the same scope and period must return identical values. Two
 * implementations of "availability" always drift, and the day they disagree is
 * the day nobody trusts either.
 *
 * The class is split in two deliberately. {@see components()} counts things,
 * and every figure it returns is additive: a month is the sum of its days.
 * {@see derive()} turns those counts into the ratios people read. Snapshots
 * store components and derive on read (ADR-058), so a precomputed dashboard and
 * a live report run the same arithmetic rather than two versions of it.
 *
 * Two rules shape almost every method here.
 *
 * A zero denominator returns null, never 0. A machine with no failures has no
 * mean time between them; reporting 0 would say it fails constantly, which is
 * the opposite of the truth (rule 2).
 *
 * Everything is measured against the factory working calendar, not the wall
 * clock. Availability computed against 24 hours a day makes every factory look
 * two-thirds idle (Section 47).
 */
class KpiCalculator
{
    /** Bumped when a definition changes, so stored snapshots stay comparable. */
    public const CALCULATION_VERSION = 1;

    /**
     * The additive figures a snapshot stores. Sums are exact across periods;
     * means and percentages are not, which is why none of them are here.
     */
    public const COMPONENTS = [
        'scheduled_operating_minutes',
        'downtime_minutes',
        'unplanned_downtime_minutes',
        'counted_downtime_minutes',
        'failure_count',
        'repair_count',
        'repair_minutes_total',
        'response_count',
        'response_minutes_total',
        'arrival_count',
        'arrival_minutes_total',
        'pm_due_count',
        'pm_on_time_count',
        'work_order_scheduled_count',
        'work_order_closed_count',
    ];

    public function __construct(
        private readonly TenantContext $context,
        private readonly WorkingTimeService $workingTime,
        private readonly SettingsResolver $settings,
    ) {}

    /**
     * Every headline figure for one scope and period.
     *
     * @param  array{factory_id?: string|null, asset_id?: string|null}  $scope
     * @return array<string, mixed>
     */
    public function forPeriod(CarbonImmutable $from, CarbonImmutable $to, array $scope = []): array
    {
        return $this->derive($this->components($from, $to, $scope), $from, $to);
    }

    /**
     * Count everything, decide nothing.
     *
     * @param  array{factory_id?: string|null, asset_id?: string|null}  $scope
     * @return array<string, int>
     */
    public function components(CarbonImmutable $from, CarbonImmutable $to, array $scope = []): array
    {
        $factoryId = $scope['factory_id'] ?? null;
        $assetId = $scope['asset_id'] ?? null;

        $downtime = $this->downtimeMinutes($from, $to, $factoryId, $assetId);
        $repair = $this->repairTotals($from, $to, $factoryId, $assetId);
        $pm = $this->pmTotals($from, $to, $factoryId, $assetId);
        $orders = $this->workOrderTotals($from, $to, $factoryId);

        return [
            'scheduled_operating_minutes' => $this->scheduledOperatingMinutes($from, $to, $factoryId, $assetId),
            'downtime_minutes' => $downtime['total'],
            'unplanned_downtime_minutes' => $downtime['unplanned'],
            'counted_downtime_minutes' => $downtime['counted'],
            'failure_count' => $this->failureCount($from, $to, $factoryId, $assetId),
            'repair_count' => $repair['count'],
            'repair_minutes_total' => $repair['minutes'],
            ...$this->elapsedTotals($from, $to, $factoryId, $assetId, 'acknowledged_at', 'response'),
            ...$this->elapsedTotals($from, $to, $factoryId, $assetId, 'technician_arrival_at', 'arrival'),
            'pm_due_count' => $pm['due'],
            'pm_on_time_count' => $pm['on_time'],
            'work_order_scheduled_count' => $orders['scheduled'],
            'work_order_closed_count' => $orders['closed'],
        ];
    }

    /**
     * Turn counts into the figures people read.
     *
     * The only place a ratio is computed, so a snapshot summed over thirty days
     * and a live scan of the same thirty days cannot disagree.
     *
     * @param  array<string, int>  $c
     * @return array<string, mixed>
     */
    public function derive(array $c, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $operating = max($c['scheduled_operating_minutes'] - $c['counted_downtime_minutes'], 0);

        return [
            'period_start' => $from,
            'period_end' => $to,
            'scheduled_operating_minutes' => $c['scheduled_operating_minutes'],
            'downtime_minutes' => $c['downtime_minutes'],
            'unplanned_downtime_minutes' => $c['unplanned_downtime_minutes'],
            'counted_downtime_minutes' => $c['counted_downtime_minutes'],
            'operating_minutes' => $operating,
            'failure_count' => $c['failure_count'],

            'availability_percent' => $this->ratio($operating, $c['scheduled_operating_minutes']),
            'unplanned_downtime_percent' => $this->ratio(
                $c['unplanned_downtime_minutes'], $c['scheduled_operating_minutes'],
            ),
            // Operating time between failures, not calendar time.
            'mtbf_minutes' => $this->mean($operating, $c['failure_count']),
            'mttr_minutes' => $this->mean($c['repair_minutes_total'], $c['repair_count']),
            'mtta_minutes' => $this->mean($c['response_minutes_total'], $c['response_count']),
            'mean_time_to_arrive_minutes' => $this->mean($c['arrival_minutes_total'], $c['arrival_count']),
            'pm_compliance_percent' => $this->ratio($c['pm_on_time_count'], $c['pm_due_count']),
            'schedule_attainment_percent' => $this->ratio(
                $c['work_order_closed_count'], $c['work_order_scheduled_count'],
            ),
            'calculation_version' => self::CALCULATION_VERSION,
        ];
    }

    /**
     * Working minutes the scope was supposed to be producing.
     *
     * Retired and scrapped machines drop out from their status-change date
     * forward (rule 4): counting a scrapped machine's shift hours as scheduled
     * time would drag every availability figure down for ever. A stored daily
     * snapshot preserves that naturally — the machine was scheduled on the day
     * it ran, and stops being counted from the day it did not.
     */
    public function scheduledOperatingMinutes(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $factoryId = null,
        ?string $assetId = null,
    ): int {
        $assets = $this->assetsInScope($factoryId, $assetId);

        if ($assets->isEmpty()) {
            return 0;
        }

        $total = 0;

        foreach ($assets->groupBy('current_factory_id') as $factoryAssets) {
            $factory = Factory::find($factoryAssets->first()->current_factory_id);

            if ($factory === null) {
                continue;
            }

            $minutes = $this->workingTime->scheduledOperatingMinutes($factory, $from, $to)['minutes'];

            $total += $minutes * $factoryAssets->count();
        }

        return $total;
    }

    /**
     * Downtime in the period, split by what counts against availability.
     *
     * A breakdown spanning the period boundary contributes proportionally
     * (rule 3), so a stoppage running from Sunday into Monday is not counted
     * twice in a weekly report.
     *
     * @return array{total: int, unplanned: int, counted: int}
     */
    public function downtimeMinutes(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $factoryId = null,
        ?string $assetId = null,
    ): array {
        $records = $this->currentDowntimeRecords($from, $to, $factoryId, $assetId);

        $plannedCounts = (bool) $this->settings->get(
            'metrics.planned_downtime_counts_against_availability',
            factoryId: $factoryId,
        );

        $total = 0;
        $unplanned = 0;
        $counted = 0;

        foreach ($records as $record) {
            $minutes = $this->clippedMinutes($record, $from, $to);

            $total += $minutes;

            if ($record->downtime_class === 'UNPLANNED') {
                $unplanned += $minutes;
            }

            // Rule 5: planned downtime is excluded by default, and the setting
            // moves it rather than a report deciding for itself.
            $countsHere = $record->counts_against_availability
                || ($record->downtime_class === 'PLANNED' && $plannedCounts);

            if ($countsHere) {
                $counted += $minutes;
            }
        }

        return ['total' => $total, 'unplanned' => $unplanned, 'counted' => $counted];
    }

    /**
     * Rule 1: unplanned breakdowns only, and a duplicate report linked to an
     * open one is not a second failure.
     */
    public function failureCount(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $factoryId = null,
        ?string $assetId = null,
    ): int {
        return $this->countedBreakdowns($from, $to, $factoryId, $assetId)
            ->where('downtime_class', 'UNPLANNED')
            ->count();
    }

    /**
     * Mean repair time, hold time excluded.
     *
     * Waiting for a part is a supply problem, not slow repair work, and folding
     * it in hides the real constraint behind a slow-looking team (ADR-051).
     */
    public function mttr(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $factoryId = null,
        ?string $assetId = null,
    ): ?float {
        $totals = $this->repairTotals($from, $to, $factoryId, $assetId);

        return $this->mean($totals['minutes'], $totals['count']);
    }

    /**
     * PM completed within its due date plus grace, over PM due in the period.
     *
     * Grace matters: a plan with a two-day grace is not late on day one, and
     * counting it as late makes the compliance figure useless for the thing it
     * is meant to measure (SRS 31.1).
     */
    public function pmCompliance(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $factoryId = null,
        ?string $assetId = null,
    ): ?float {
        $totals = $this->pmTotals($from, $to, $factoryId, $assetId);

        return $this->ratio($totals['on_time'], $totals['due']);
    }

    /**
     * Work orders closed in the period over work orders scheduled into it.
     */
    public function scheduleAttainment(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $factoryId = null,
    ): ?float {
        $totals = $this->workOrderTotals($from, $to, $factoryId);

        return $this->ratio($totals['closed'], $totals['scheduled']);
    }

    /**
     * @return array{count: int, minutes: int}
     */
    private function repairTotals(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $factoryId,
        ?string $assetId,
    ): array {
        $minutes = $this->currentDowntimeRecords($from, $to, $factoryId, $assetId)
            ->filter(fn (DowntimeRecord $r) => $r->repair_minutes !== null)
            ->pluck('repair_minutes');

        return ['count' => $minutes->count(), 'minutes' => (int) $minutes->sum()];
    }

    /**
     * Minutes from report to a later point in the breakdown chain.
     *
     * Measured from the report rather than the failure: time before anybody
     * said so is a reporting problem, and charging it to maintenance response
     * measures the wrong team.
     *
     * @return array<string, int>
     */
    private function elapsedTotals(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $factoryId,
        ?string $assetId,
        string $endField,
        string $prefix,
    ): array {
        $breakdowns = $this->countedBreakdowns($from, $to, $factoryId, $assetId)
            ->whereNotNull($endField)
            ->get();

        $total = $breakdowns->reduce(
            fn (float $carry, Breakdown $b) => $carry
                + $b->reported_at->diffInMinutes($b->{$endField}, absolute: true),
            0.0,
        );

        return [
            "{$prefix}_count" => $breakdowns->count(),
            "{$prefix}_minutes_total" => (int) round($total),
        ];
    }

    /**
     * @return array{due: int, on_time: int}
     */
    private function pmTotals(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $factoryId,
        ?string $assetId,
    ): array {
        $due = MaintenanceSchedule::query()
            ->whereBetween('due_at', [$from, $to])
            ->whereNotIn('status', ['SKIPPED', 'CANCELLED'])
            ->when($assetId !== null, fn ($q) => $q->where('asset_id', $assetId))
            ->when(
                $factoryId !== null,
                fn ($q) => $q->whereHas('asset', fn ($a) => $a->where('current_factory_id', $factoryId)),
            )
            ->get();

        $onTime = $due->filter(function (MaintenanceSchedule $schedule): bool {
            if ($schedule->completed_at === null) {
                return false;
            }

            $deadline = $schedule->grace_until ?? $schedule->due_at;

            return $schedule->completed_at->lessThanOrEqualTo($deadline);
        });

        return ['due' => $due->count(), 'on_time' => $onTime->count()];
    }

    /**
     * @return array{scheduled: int, closed: int}
     */
    private function workOrderTotals(CarbonImmutable $from, CarbonImmutable $to, ?string $factoryId): array
    {
        $base = fn () => WorkOrder::query()
            ->whereBetween('scheduled_start', [$from, $to])
            ->when($factoryId !== null, fn ($q) => $q->where('factory_id', $factoryId));

        return [
            'scheduled' => $base()->count(),
            'closed' => $base()->whereIn('status', ['CLOSED', 'VERIFIED', 'COMPLETED'])->count(),
        ];
    }

    /**
     * Breakdowns that count as events: not cancelled, and not a duplicate
     * report of one already open (rule 1).
     */
    private function countedBreakdowns(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $factoryId,
        ?string $assetId,
    ) {
        return Breakdown::query()
            ->whereBetween('failure_at', [$from, $to])
            ->whereNotIn('status', ['CANCELLED'])
            ->whereNull('is_recurrence_of_breakdown_id')
            ->when($factoryId !== null, fn ($q) => $q->where('factory_id', $factoryId))
            ->when($assetId !== null, fn ($q) => $q->where('asset_id', $assetId));
    }

    /**
     * The latest calculation version of each breakdown's downtime.
     *
     * A recalculation writes a new version beside the old one, so taking every
     * row would count the same stoppage twice (SRS 17.3).
     *
     * @return Collection<int, DowntimeRecord>
     */
    private function currentDowntimeRecords(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $factoryId,
        ?string $assetId,
    ): Collection {
        return DowntimeRecord::query()
            ->where('failure_at', '<=', $to)
            ->where(fn ($q) => $q->whereNull('production_resumed_at')
                ->orWhere('production_resumed_at', '>=', $from))
            ->when($factoryId !== null, fn ($q) => $q->where('factory_id', $factoryId))
            ->when($assetId !== null, fn ($q) => $q->where('asset_id', $assetId))
            ->get()
            ->groupBy('breakdown_id')
            ->map(fn (Collection $versions) => $versions->sortByDesc('calculation_version')->first())
            ->values();
    }

    /**
     * A stoppage clipped to the period boundary (rule 3).
     */
    private function clippedMinutes(DowntimeRecord $record, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $start = CarbonImmutable::parse($record->failure_at);
        $end = $record->production_resumed_at !== null
            ? CarbonImmutable::parse($record->production_resumed_at)
            : CarbonImmutable::parse($record->repair_completed_at ?? $to);

        if ($end->lessThanOrEqualTo($start)) {
            return 0;
        }

        $clippedStart = $start->max($from);
        $clippedEnd = $end->min($to);

        if ($clippedEnd->lessThanOrEqualTo($clippedStart)) {
            return 0;
        }

        $totalMinutes = $record->total_downtime_minutes ?? 0;
        $wholeSpan = $start->diffInMinutes($end, absolute: true);

        if ($wholeSpan <= 0) {
            return 0;
        }

        // Proportional rather than recomputed: the stored figure already
        // carries the calendar basis it was derived on, and recalculating here
        // would silently change basis mid-report.
        $share = $clippedStart->diffInMinutes($clippedEnd, absolute: true) / $wholeSpan;

        return (int) round($totalMinutes * $share);
    }

    /**
     * @return Collection<int, Asset>
     */
    private function assetsInScope(?string $factoryId, ?string $assetId): Collection
    {
        return Asset::query()
            // Rule 4.
            ->whereNotIn('status', ['RETIRED', 'SCRAPPED', 'LOST', 'DRAFT'])
            ->when($factoryId !== null, fn ($q) => $q->where('current_factory_id', $factoryId))
            ->when($assetId !== null, fn ($q) => $q->where('id', $assetId))
            ->when(
                $factoryId === null && $assetId === null,
                fn ($q) => $q->whereIn('current_factory_id', $this->context->accessibleFactoryIds()),
            )
            ->get(['id', 'current_factory_id', 'status']);
    }

    /**
     * Rule 2: a zero denominator is null, never 0 and never 100.
     */
    private function ratio(int $numerator, int $denominator): ?float
    {
        if ($denominator <= 0) {
            return null;
        }

        return round($numerator / $denominator * 100, 1);
    }

    /** Rule 2 again: no events means no mean, not a mean of zero. */
    private function mean(int $total, int $count): ?float
    {
        if ($count <= 0) {
            return null;
        }

        return round($total / $count, 1);
    }
}
