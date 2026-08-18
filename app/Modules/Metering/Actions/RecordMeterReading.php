<?php

declare(strict_types=1);

namespace App\Modules\Metering\Actions;

use App\Modules\Maintenance\Services\MeterTriggerEvaluator;
use App\Modules\Metering\Models\AssetMeter;
use App\Modules\Metering\Models\MeterReading;
use App\Modules\Metering\Models\MeterResetEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Posts a meter reading (SRS 11, ADR-013).
 *
 * A cumulative meter cannot go backwards. Accepting a lower value would make
 * every hours-based due date jump backwards with it, so a genuine meter
 * replacement is a separate, audited reset event instead.
 *
 * Returns the reading plus any maintenance schedules the reading just brought
 * due, so the operator learns immediately rather than finding out overnight.
 */
class RecordMeterReading
{
    /**
     * @return array{reading: MeterReading, triggered: Collection}
     */
    public function handle(
        AssetMeter $meter,
        string|float $value,
        ?CarbonImmutable $readingAt = null,
        string $source = 'MANUAL',
        ?string $userId = null,
        ?string $notes = null,
        ?string $sourceReference = null,
    ): array {
        $readingAt ??= CarbonImmutable::now();
        $value = number_format((float) $value, 4, '.', '');

        if ($readingAt->isFuture()) {
            throw ValidationException::withMessages([
                'reading_at' => __('metering.reading_in_future'),
            ]);
        }

        $previous = $meter->current_value;

        if ($meter->type->is_cumulative && (float) $value < (float) $previous) {
            throw ValidationException::withMessages([
                'value' => __('metering.value_decreased', [
                    'current' => $previous,
                    'submitted' => $value,
                ]),
            ])->status(422);
        }

        return DB::transaction(function () use (
            $meter, $value, $previous, $readingAt, $source, $userId, $notes, $sourceReference
        ): array {
            $reading = MeterReading::create([
                'asset_id' => $meter->asset_id,
                'meter_id' => $meter->id,
                'value' => $value,
                'previous_value' => $previous,
                'delta' => number_format((float) $value - (float) $previous, 4, '.', ''),
                'reading_at' => $readingAt,
                'source' => $source,
                'source_reference' => $sourceReference,
                'notes' => $notes,
                'recorded_by' => $userId,
            ]);

            // Only advance the denormalised value when this reading is the
            // newest. A backdated reading is history, not the current state.
            if ($meter->last_reading_at === null || $readingAt->greaterThanOrEqualTo($meter->last_reading_at)) {
                $meter->forceFill([
                    'current_value' => $value,
                    'last_reading_at' => $readingAt,
                ])->save();
            }

            // Evaluated on posting, not only on the nightly tick: a machine
            // that reaches 500 hours at 02:00 should not wait until morning
            // (ERD Section 7, scheduler rule 5).
            $triggered = app(MeterTriggerEvaluator::class)
                ->evaluate($meter->fresh());

            return ['reading' => $reading, 'triggered' => $triggered];
        });
    }

    /**
     * A meter replacement or rollover: the one legitimate way a cumulative
     * reading goes down. Recorded as its own event so the drop has an
     * explanation and consumption reporting can bridge it.
     */
    public function reset(
        AssetMeter $meter,
        string|float $newValue,
        string $reason,
        ?string $userId = null,
    ): MeterReading {
        $newValue = number_format((float) $newValue, 4, '.', '');

        return DB::transaction(function () use ($meter, $newValue, $reason, $userId): MeterReading {
            MeterResetEvent::create([
                'meter_id' => $meter->id,
                'old_value' => $meter->current_value,
                'new_value' => $newValue,
                'reason' => $reason,
                'reset_at' => now(),
                'reset_by' => $userId,
            ]);

            $reading = MeterReading::create([
                'asset_id' => $meter->asset_id,
                'meter_id' => $meter->id,
                'value' => $newValue,
                'previous_value' => $meter->current_value,
                'delta' => '0.0000',
                'reading_at' => now(),
                'source' => 'MANUAL',
                'is_reset_baseline' => true,
                'notes' => $reason,
                'recorded_by' => $userId,
            ]);

            $meter->forceFill([
                'current_value' => $newValue,
                'last_reading_at' => now(),
            ])->save();

            return $reading;
        });
    }
}
