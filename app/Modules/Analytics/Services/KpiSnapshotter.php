<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\Models\KpiSnapshot;
use App\Shared\Support\TenantTimezone;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Writes and reads precomputed KPI snapshots (ADR-058, SRS 45).
 *
 * The read path is the reason the table exists. A dashboard asks for the last
 * thirty days; this class answers from thirty stored rows plus whatever part of
 * today has already happened, instead of scanning the downtime and work order
 * tables from scratch on every page load.
 *
 * A day here is a day on the factory clock, not a UTC day. A Dhaka night shift
 * ending at 06:00 belongs to the shift that worked it, and slicing on UTC
 * midnight would split it across two figures nobody in the factory recognises.
 */
class KpiSnapshotter
{
    public function __construct(
        private readonly KpiCalculator $calculator,
        private readonly TenantContext $context,
        private readonly TenantTimezone $timezone,
    ) {}

    /**
     * Every headline figure for a period, from snapshots where they exist.
     *
     * Falls back to a live scan when the window is not fully covered. A missing
     * snapshot must never turn into a missing number: a dashboard that silently
     * drops the first week of a month is worse than a slow one.
     *
     * @param  array{factory_id?: string|null, asset_id?: string|null}  $scope
     * @return array<string, mixed>
     */
    public function forPeriod(CarbonImmutable $from, CarbonImmutable $to, array $scope = []): array
    {
        $components = $this->componentsForPeriod($from, $to, $scope);

        return $this->calculator->derive($components, $from, $to);
    }

    /**
     * @param  array{factory_id?: string|null, asset_id?: string|null}  $scope
     * @return array<string, int>
     */
    public function componentsForPeriod(CarbonImmutable $from, CarbonImmutable $to, array $scope = []): array
    {
        $days = $this->wholeLocalDays($from, $to);

        if ($days->isEmpty()) {
            return $this->calculator->components($from, $to, $scope);
        }

        $snapshots = $this->storedFor($days, $scope);

        if ($snapshots->count() !== $days->count()) {
            // Partially covered. Summing what exists and live-scanning the rest
            // would cost more than one clean pass and risks double counting a
            // stoppage that spans the seam.
            return $this->calculator->components($from, $to, $scope);
        }

        $total = $this->sum($snapshots->map(
            fn (KpiSnapshot $s) => $this->componentsOf($s),
        ));

        // The uncovered edges: the part of the first day before the window
        // opened, and the part of today that has already happened.
        $firstDayStart = $this->dayBounds($days->first())[0];
        $lastDayEnd = $this->dayBounds($days->last())[1];

        $edges = [
            [$from, $firstDayStart->subMillisecond()],
            [$lastDayEnd->addMillisecond(), $to],
        ];

        foreach ($edges as [$edgeFrom, $edgeTo]) {
            if ($edgeTo->greaterThan($edgeFrom)) {
                $total = $this->sum(collect([$total, $this->calculator->components($edgeFrom, $edgeTo, $scope)]));
            }
        }

        return $total;
    }

    /**
     * Compute and store one local day for one scope.
     *
     * Idempotent: recomputing a day overwrites its row for the current
     * calculation version rather than adding a second one. A definition change
     * bumps the version and backfills beside the old rows, so a figure already
     * reported to a buyer does not move under them.
     *
     * @param  array{factory_id?: string|null, asset_id?: string|null}  $scope
     */
    public function writeDay(CarbonImmutable $day, array $scope = []): KpiSnapshot
    {
        [$start, $end] = $this->dayBounds($day);

        $components = $this->calculator->components($start, $end, $scope);
        $derived = $this->calculator->derive($components, $start, $end);

        $factoryId = $scope['factory_id'] ?? null;
        $assetId = $scope['asset_id'] ?? null;

        return KpiSnapshot::updateOrCreate(
            [
                'company_id' => $this->context->companyId(),
                'scope_type' => $this->scopeType($factoryId, $assetId),
                'factory_id' => $factoryId,
                'asset_id' => $assetId,
                'period_type' => 'DAY',
                'period_start' => $day->toDateString(),
                'calculation_version' => KpiCalculator::CALCULATION_VERSION,
            ],
            [
                ...$components,
                'period_end' => $day->toDateString(),
                'availability_percent' => $derived['availability_percent'],
                'mtbf_minutes' => $derived['mtbf_minutes'],
                'mttr_minutes' => $derived['mttr_minutes'],
                'pm_compliance_percent' => $derived['pm_compliance_percent'],
                'computed_at' => CarbonImmutable::now(),
            ],
        );
    }

    /**
     * Write the last N local days for one scope, most recent first.
     *
     * Today is included and rewritten on every run: the current period is a
     * moving figure, and a dashboard showing this morning's number at 6pm is
     * wrong in the way people notice fastest.
     *
     * @param  array{factory_id?: string|null, asset_id?: string|null}  $scope
     */
    public function backfillDays(int $days, array $scope = []): int
    {
        $today = $this->today();
        $written = 0;

        for ($offset = 0; $offset < $days; $offset++) {
            $this->writeDay($today->subDays($offset), $scope);
            $written++;
        }

        return $written;
    }

    /**
     * The local days fully contained in the window.
     *
     * A partial day is never snapshotted: storing half of today under today's
     * date makes the row silently wrong the moment anything else happens.
     *
     * @return Collection<int, CarbonImmutable>
     */
    private function wholeLocalDays(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $days = collect();
        $cursor = $this->localDate($from);
        $last = $this->localDate($to);

        while ($cursor->lessThanOrEqualTo($last)) {
            [$start, $end] = $this->dayBounds($cursor);

            if ($start->greaterThanOrEqualTo($from) && $end->lessThanOrEqualTo($to)) {
                $days->push($cursor);
            }

            $cursor = $cursor->addDay();
        }

        return $days;
    }

    /**
     * @param  Collection<int, CarbonImmutable>  $days
     * @param  array{factory_id?: string|null, asset_id?: string|null}  $scope
     * @return Collection<int, KpiSnapshot>
     */
    private function storedFor(Collection $days, array $scope): Collection
    {
        $factoryId = $scope['factory_id'] ?? null;
        $assetId = $scope['asset_id'] ?? null;

        return KpiSnapshot::query()
            ->where('period_type', 'DAY')
            ->where('calculation_version', KpiCalculator::CALCULATION_VERSION)
            ->where('scope_type', $this->scopeType($factoryId, $assetId))
            // Null is not a value in SQL comparisons, so a company-wide row is
            // matched by its nullity rather than by equality.
            ->when($factoryId === null, fn ($q) => $q->whereNull('factory_id'))
            ->when($factoryId !== null, fn ($q) => $q->where('factory_id', $factoryId))
            ->when($assetId === null, fn ($q) => $q->whereNull('asset_id'))
            ->when($assetId !== null, fn ($q) => $q->where('asset_id', $assetId))
            ->whereIn('period_start', $days->map(fn (CarbonImmutable $d) => $d->toDateString())->all())
            ->get();
    }

    /**
     * @return array<string, int>
     */
    private function componentsOf(KpiSnapshot $snapshot): array
    {
        $components = [];

        foreach (KpiCalculator::COMPONENTS as $key) {
            $components[$key] = (int) $snapshot->{$key};
        }

        return $components;
    }

    /**
     * @param  Collection<int, array<string, int>>  $rows
     * @return array<string, int>
     */
    private function sum(Collection $rows): array
    {
        $total = array_fill_keys(KpiCalculator::COMPONENTS, 0);

        foreach ($rows as $row) {
            foreach (KpiCalculator::COMPONENTS as $key) {
                $total[$key] += $row[$key] ?? 0;
            }
        }

        return $total;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function dayBounds(CarbonImmutable $day): array
    {
        $tz = $this->timezone->current();

        $start = CarbonImmutable::parse($day->toDateString().' 00:00:00', $tz)->setTimezone('UTC');

        // Half open, to the millisecond the rest of the system records in. An
        // inclusive midnight at both ends counts a failure reported exactly at
        // 00:00 twice, once in each day.
        return [$start, $start->addDay()->subMillisecond()];
    }

    private function localDate(CarbonImmutable $instant): CarbonImmutable
    {
        return CarbonImmutable::parse(
            $instant->setTimezone($this->timezone->current())->toDateString(),
        );
    }

    private function today(): CarbonImmutable
    {
        return $this->localDate(CarbonImmutable::now());
    }

    private function scopeType(?string $factoryId, ?string $assetId): string
    {
        return match (true) {
            $assetId !== null => 'ASSET',
            $factoryId !== null => 'FACTORY',
            default => 'COMPANY',
        };
    }
}
