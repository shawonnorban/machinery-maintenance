<?php

declare(strict_types=1);

namespace App\Modules\Breakdown\Actions;

use App\Modules\Asset\Actions\ChangeAssetStatus;
use App\Modules\Asset\Models\Asset;
use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Breakdown\Models\BreakdownStatusHistory;
use App\Modules\Breakdown\Services\DowntimeCalculator;
use App\Modules\WorkOrder\Models\Technician;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The breakdown lifecycle (SRS 15, ERD Section 10).
 *
 * Each step stamps its own timestamp, and every stamp is checked against the
 * chain before it is written. An out-of-order chain does not throw at read
 * time; it quietly produces a negative response time or an impossible repair
 * duration, and those numbers end up in a management report.
 */
class TransitionBreakdown
{
    public function __construct(
        private readonly ChangeAssetStatus $assetStatus,
        private readonly DowntimeCalculator $downtime,
    ) {}

    public function acknowledge(Breakdown $breakdown, string $userId, ?CarbonImmutable $at = null): Breakdown
    {
        $at ??= CarbonImmutable::now();

        return $this->stamp($breakdown, 'ACKNOWLEDGED', $userId, [
            'acknowledged_at' => $at,
            'acknowledged_by' => $userId,
        ]);
    }

    public function assign(Breakdown $breakdown, string $technicianId, string $userId): Breakdown
    {
        $technician = Technician::find($technicianId);

        if ($technician === null || ! $technician->isActive()) {
            throw ValidationException::withMessages([
                'assigned_technician_id' => __('breakdown.technician_unavailable'),
            ]);
        }

        if ($technician->factory_id !== $breakdown->factory_id) {
            throw ValidationException::withMessages([
                'assigned_technician_id' => __('breakdown.technician_other_factory', [
                    'name' => $technician->name,
                ]),
            ]);
        }

        return $this->stamp($breakdown, 'ASSIGNED', $userId, [
            'assigned_technician_id' => $technician->id,
            'assigned_at' => CarbonImmutable::now(),
        ]);
    }

    /**
     * Arrival is recorded separately from repair start. The walk to the machine
     * is response time; the work on it is repair time. A team that arrives fast
     * and repairs slowly has a different problem from one that does the reverse,
     * and one combined figure hides which.
     */
    public function recordArrival(Breakdown $breakdown, string $userId, ?CarbonImmutable $at = null): Breakdown
    {
        $at ??= CarbonImmutable::now();

        $this->assertChain($breakdown, ['technician_arrival_at' => $at]);

        return DB::transaction(function () use ($breakdown, $at): Breakdown {
            $breakdown->forceFill(['technician_arrival_at' => $at])->save();
            $this->downtime->forBreakdown($breakdown->fresh());

            return $breakdown->fresh();
        });
    }

    public function startRepair(Breakdown $breakdown, string $userId, ?CarbonImmutable $at = null): Breakdown
    {
        $at ??= CarbonImmutable::now();

        // Arriving is implied by starting work. Requiring a separate tap for it
        // means it gets skipped and the arrival timestamp stays empty.
        $fields = ['repair_started_at' => $at];

        if ($breakdown->technician_arrival_at === null) {
            $fields['technician_arrival_at'] = $at;
        }

        $breakdown = $this->stamp($breakdown, 'IN_REPAIR', $userId, $fields);

        $asset = Asset::find($breakdown->asset_id);

        if ($asset !== null && $asset->canTransitionTo('UNDER_REPAIR')) {
            $this->assetStatus->handle(
                $asset, 'UNDER_REPAIR', $userId,
                "Breakdown {$breakdown->breakdown_number} repair started",
                'BREAKDOWN',
            );
        }

        return $breakdown->fresh();
    }

    public function hold(Breakdown $breakdown, string $reasonCode, string $userId, ?string $notes = null): Breakdown
    {
        if (! in_array($reasonCode, Breakdown::HOLD_REASONS, true)) {
            throw ValidationException::withMessages([
                'reason_code' => __('breakdown.hold_reason_unknown'),
            ]);
        }

        $now = CarbonImmutable::now();

        return $this->stamp($breakdown, 'ON_HOLD', $userId, [
            'on_hold_since' => $now,
            'hold_reason_code' => $reasonCode,
        ], $notes === null ? $reasonCode : "{$reasonCode}: {$notes}");
    }

    public function resume(Breakdown $breakdown, string $userId): Breakdown
    {
        $now = CarbonImmutable::now();
        $minutes = 0;

        if ($breakdown->on_hold_since !== null) {
            $minutes = (int) round($breakdown->on_hold_since->diffInMinutes($now, absolute: true));
        }

        return $this->stamp($breakdown, 'IN_REPAIR', $userId, [
            // Accumulated so repair time can exclude it (ADR-051).
            'hold_minutes' => $breakdown->hold_minutes + $minutes,
            'on_hold_since' => null,
            'hold_reason_code' => null,
        ]);
    }

    public function completeRepair(Breakdown $breakdown, string $userId, ?CarbonImmutable $at = null): Breakdown
    {
        $at ??= CarbonImmutable::now();

        if ($breakdown->repair_started_at === null) {
            throw ValidationException::withMessages([
                'repair_completed_at' => __('breakdown.repair_never_started'),
            ])->status(409);
        }

        return $this->stamp($breakdown, 'REPAIRED', $userId, ['repair_completed_at' => $at]);
    }

    /**
     * The machine being fixed and the line running again are different events.
     * The gap between them is real lost output, and it belongs to nobody unless
     * it is measured.
     */
    public function resumeProduction(Breakdown $breakdown, string $userId, ?CarbonImmutable $at = null): Breakdown
    {
        $at ??= CarbonImmutable::now();

        $breakdown = $this->stamp($breakdown, 'PRODUCTION_RESUMED', $userId, [
            'production_resumed_at' => $at,
        ]);

        $asset = Asset::find($breakdown->asset_id);

        if ($asset !== null && $asset->canTransitionTo('RUNNING')) {
            $this->assetStatus->handle(
                $asset, 'RUNNING', $userId,
                "Breakdown {$breakdown->breakdown_number} resolved",
                'BREAKDOWN',
            );
        }

        return $breakdown->fresh();
    }

    /**
     * Closing needs a cause, not just a repair (ERD Section 10 rule 3).
     *
     * @param  array<string, mixed>  $data
     */
    public function close(Breakdown $breakdown, array $data, string $userId): Breakdown
    {
        $breakdown->fill(array_filter([
            'failure_code_id' => $data['failure_code_id'] ?? null,
            'failure_category_id' => $data['failure_category_id'] ?? null,
            'root_cause_id' => $data['root_cause_id'] ?? null,
            'corrective_action' => $data['corrective_action'] ?? null,
            'preventive_action' => $data['preventive_action'] ?? null,
        ], fn ($value) => $value !== null));

        $missing = $breakdown->missingForClosure();

        if ($missing !== []) {
            // A breakdown closed with no recorded cause is a machine that broke
            // for no reason, and it makes the whole failure-analysis half of the
            // product produce nothing.
            throw ValidationException::withMessages(
                array_combine(
                    $missing,
                    array_map(fn (string $field) => __("breakdown.closure_needs_{$field}"), $missing),
                ),
            )->status(422);
        }

        return DB::transaction(function () use ($breakdown, $data, $userId): Breakdown {
            $breakdown->save();

            $breakdown = $this->move($breakdown, 'CLOSED', $userId, $data['closure_notes'] ?? null);

            $breakdown->forceFill([
                'closed_by' => $userId,
                'closed_at' => CarbonImmutable::now(),
                'closure_notes' => $data['closure_notes'] ?? null,
            ])->save();

            $this->downtime->forBreakdown($breakdown->fresh());

            return $breakdown->fresh();
        });
    }

    public function cancel(Breakdown $breakdown, string $reason, string $userId): Breakdown
    {
        if (blank($reason)) {
            throw ValidationException::withMessages([
                'cancellation_reason' => __('breakdown.cancel_needs_reason'),
            ]);
        }

        return DB::transaction(function () use ($breakdown, $reason, $userId): Breakdown {
            $breakdown = $this->move($breakdown, 'CANCELLED', $userId, $reason);

            $breakdown->forceFill([
                'cancelled_by' => $userId,
                'cancelled_at' => CarbonImmutable::now(),
                'cancellation_reason' => $reason,
            ])->save();

            // A machine reported down in error goes back to running; one that
            // was genuinely worked on is left where the repair put it.
            $asset = Asset::find($breakdown->asset_id);

            if ($asset !== null && $asset->status === 'BREAKDOWN' && $asset->canTransitionTo('UNDER_REPAIR')) {
                $this->assetStatus->handle(
                    $asset, 'UNDER_REPAIR', $userId,
                    "Breakdown {$breakdown->breakdown_number} cancelled",
                    'BREAKDOWN',
                );

                $asset = Asset::find($breakdown->asset_id);

                if ($asset->canTransitionTo('RUNNING')) {
                    $this->assetStatus->handle(
                        $asset, 'RUNNING', $userId,
                        "Breakdown {$breakdown->breakdown_number} cancelled",
                        'BREAKDOWN',
                    );
                }
            }

            $this->downtime->forBreakdown($breakdown->fresh());

            return $breakdown->fresh();
        });
    }

    /**
     * Corrects one chain timestamp without changing status.
     *
     * A machine that stopped at 21:50 and was reported at 06:10 the next
     * morning is real and common, so the stamps have to be editable. The chain
     * check still applies, and the correction is recorded: silently rewriting a
     * timestamp that a downtime figure was derived from is exactly the kind of
     * change an audit needs to see.
     */
    public function correctTimestamp(
        Breakdown $breakdown,
        string $field,
        CarbonImmutable $value,
        string $userId,
    ): Breakdown {
        if (! in_array($field, Breakdown::TIMESTAMP_CHAIN, true)) {
            throw ValidationException::withMessages([
                'field' => __('breakdown.unknown_timestamp'),
            ]);
        }

        if ($breakdown->isTerminal()) {
            throw ValidationException::withMessages([
                'field' => __('breakdown.timestamp_after_close'),
            ])->status(409);
        }

        if ($value->isFuture()) {
            throw ValidationException::withMessages([
                'value' => __('breakdown.timestamp_in_future'),
            ]);
        }

        $this->assertChain($breakdown, [$field => $value]);

        return DB::transaction(function () use ($breakdown, $field, $value, $userId): Breakdown {
            $previous = $breakdown->{$field};

            $breakdown->forceFill([$field => $value])->save();

            BreakdownStatusHistory::create([
                'breakdown_id' => $breakdown->id,
                'from_status' => $breakdown->status,
                'to_status' => $breakdown->status,
                'changed_by' => $userId,
                'changed_at' => CarbonImmutable::now(),
                'reason' => __('breakdown.timestamp_correction', [
                    'field' => __("breakdown.{$field}"),
                    'from' => $previous?->toDateTimeString() ?? '—',
                    'to' => $value->toDateTimeString(),
                ]),
            ]);

            $this->downtime->forBreakdown($breakdown->fresh());

            return $breakdown->fresh();
        });
    }

    /**
     * Moves status and writes the timestamps in one guarded step.
     *
     * @param  array<string, mixed>  $fields
     */
    private function stamp(
        Breakdown $breakdown,
        string $to,
        string $userId,
        array $fields,
        ?string $reason = null,
    ): Breakdown {
        $this->assertChain($breakdown, $fields);

        return DB::transaction(function () use ($breakdown, $to, $userId, $fields, $reason): Breakdown {
            $breakdown = $this->move($breakdown, $to, $userId, $reason);

            $breakdown->forceFill($fields)->save();

            // Every stamp changes a derived figure, so the record is refreshed
            // with it rather than left stale until closure.
            $this->downtime->forBreakdown($breakdown->fresh());

            return $breakdown->fresh();
        });
    }

    private function move(Breakdown $breakdown, string $to, string $userId, ?string $reason = null): Breakdown
    {
        $from = $breakdown->status;

        if (! $breakdown->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => __('breakdown.invalid_transition', ['from' => $from, 'to' => $to]),
            ])->status(409);
        }

        $breakdown->forceFill([
            'status' => $to,
            'version' => $breakdown->version + 1,
        ])->save();

        BreakdownStatusHistory::create([
            'breakdown_id' => $breakdown->id,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $userId,
            'changed_at' => CarbonImmutable::now(),
            'reason' => $reason,
        ]);

        return $breakdown;
    }

    /**
     * Rule 2: the chain must be non-decreasing.
     *
     * Checked against the record's existing stamps as well as the incoming
     * ones, so backdating a repair start to before the report is refused rather
     * than producing an impossible duration downstream.
     *
     * @param  array<string, mixed>  $incoming
     */
    private function assertChain(Breakdown $breakdown, array $incoming): void
    {
        $chain = [];

        foreach (Breakdown::TIMESTAMP_CHAIN as $field) {
            $value = array_key_exists($field, $incoming)
                ? $incoming[$field]
                : $breakdown->{$field};

            if ($value !== null) {
                $chain[$field] = CarbonImmutable::parse($value);
            }
        }

        $previousField = null;
        $previousValue = null;

        foreach ($chain as $field => $value) {
            if ($previousValue !== null && $value->lessThan($previousValue)) {
                throw ValidationException::withMessages([
                    $field => __('breakdown.chain_out_of_order', [
                        'field' => __("breakdown.{$field}"),
                        'previous' => __("breakdown.{$previousField}"),
                    ]),
                ])->status(422);
            }

            $previousField = $field;
            $previousValue = $value;
        }
    }
}
