<?php

declare(strict_types=1);

namespace App\Modules\Breakdown\Services;

use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Breakdown\Models\DowntimeRecord;
use App\Modules\Calendar\Services\WorkingTimeService;
use App\Modules\Settings\Services\SettingsResolver;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Derives response, repair and total downtime from a breakdown's chain
 * (SRS 17, ADR-048).
 *
 * Three decisions worth stating, because each of them changes the number a
 * factory manager sees:
 *
 * 1. Minutes are working minutes, not wall-clock, when the factory says so. A
 *    breakdown reported at 21:50 in a factory whose shift ends at 22:00 accrues
 *    ten minutes that night, not eight hours. Wall-clock would make every
 *    overnight fault look catastrophic and every daytime one look trivial.
 *
 * 2. Repair time excludes hold time. Waiting for a part is a supply problem;
 *    counting it as repair hides the real constraint behind a slow-looking
 *    maintenance team (ADR-051).
 *
 * 3. Downtime ends when production resumes, not when the machine is fixed. The
 *    gap between the two is real lost output and it is nobody's if it is not
 *    measured.
 */
class DowntimeCalculator
{
    /**
     * Bump when the derivation itself changes. Existing rows keep their old
     * version and their old numbers; a backfill writes new rows beside them.
     */
    public const CALCULATION_VERSION = 1;

    public function __construct(
        private readonly WorkingTimeService $workingTime,
        private readonly SettingsResolver $settings,
    ) {}

    /**
     * Writes the downtime figures for a breakdown.
     *
     * Idempotent for a given calculation version: recalculating at the same
     * version overwrites that version's row rather than accumulating duplicates.
     * A changed version writes a new row and leaves history alone.
     */
    public function forBreakdown(Breakdown $breakdown, ?int $version = null): DowntimeRecord
    {
        $version ??= self::CALCULATION_VERSION;
        $factory = Factory::findOrFail($breakdown->factory_id);

        $calendarAware = (bool) $this->settings->get(
            'metrics.downtime_uses_shift_calendar',
            factoryId: $factory->id,
        );

        // Downtime runs from the failure to whichever end-point the breakdown
        // has actually reached. An open breakdown is measured to now, so a
        // stoppage that is still costing money is visible while it costs it,
        // rather than appearing as zero until someone closes the record.
        $end = $breakdown->production_resumed_at
            ?? $breakdown->repair_completed_at
            ?? CarbonImmutable::now();

        [$totalMinutes, $basis] = $this->minutesBetween(
            $factory, $breakdown->failure_at, $end, $calendarAware,
        );

        // Response is measured from the report, not the failure. A machine that
        // sat broken for an hour before anyone said so is a reporting problem,
        // and charging that hour to maintenance response measures the wrong
        // team.
        $responseEnd = $breakdown->technician_arrival_at ?? $breakdown->acknowledged_at;

        $responseMinutes = $responseEnd === null ? null : $this->minutesBetween(
            $factory, $breakdown->reported_at, $responseEnd, $calendarAware,
        )[0];

        // Coalesced rather than trusted: a breakdown that has never been held
        // may carry no value at all, and null minutes would propagate into the
        // stored figure as an absent number rather than a zero one.
        $holdMinutes = (int) ($breakdown->hold_minutes ?? 0);

        $repairMinutes = null;

        if ($breakdown->repair_started_at !== null && $breakdown->repair_completed_at !== null) {
            $gross = $this->minutesBetween(
                $factory, $breakdown->repair_started_at, $breakdown->repair_completed_at, $calendarAware,
            )[0];

            $repairMinutes = max($gross - $holdMinutes, 0);
        }

        $reason = $breakdown->downtimeReasonCode;

        // An unclassified row defaults to UNPLANNED and is flagged, never
        // silently excluded from the denominator (ERD Section 12 rule 1).
        $needsReview = $reason === null;

        $scheduled = $this->workingTime->scheduledOperatingMinutes(
            $factory, $breakdown->failure_at, $end,
        );

        return DowntimeRecord::updateOrCreate(
            ['breakdown_id' => $breakdown->id, 'calculation_version' => $version],
            [
                'factory_id' => $factory->id,
                'asset_id' => $breakdown->asset_id,
                'production_line_id' => $breakdown->production_line_id,
                'failure_at' => $breakdown->failure_at,
                'reported_at' => $breakdown->reported_at,
                'acknowledged_at' => $breakdown->acknowledged_at,
                'technician_arrival_at' => $breakdown->technician_arrival_at,
                'repair_started_at' => $breakdown->repair_started_at,
                'repair_completed_at' => $breakdown->repair_completed_at,
                'production_resumed_at' => $breakdown->production_resumed_at,
                'response_minutes' => $responseMinutes,
                'repair_minutes' => $repairMinutes,
                'total_downtime_minutes' => $totalMinutes,
                'hold_minutes' => $holdMinutes,
                'downtime_class' => $reason?->downtime_class ?? $breakdown->downtime_class,
                'downtime_reason_code_id' => $breakdown->downtime_reason_code_id,
                'counts_against_availability' => $reason?->counts_against_availability ?? true,
                'needs_review' => $needsReview,
                'calendar_aware' => $calendarAware,
                // Recorded, so a report can say which basis produced it rather
                // than changing silently (SRS 47.2 rule 4).
                'calculation_basis' => $basis,
                'scheduled_operating_minutes_in_window' => $scheduled['minutes'],
                'calculated_at' => CarbonImmutable::now(),
            ],
        );
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function minutesBetween(
        Factory $factory,
        CarbonInterface $from,
        CarbonInterface $to,
        bool $calendarAware,
    ): array {
        if (! $calendarAware) {
            return [
                (int) round($from->diffInMinutes($to, absolute: true)),
                'WALL_CLOCK',
            ];
        }

        $result = $this->workingTime->workingMinutesBetween($factory, $from, $to);

        return [$result['minutes'], $result['basis']];
    }
}
