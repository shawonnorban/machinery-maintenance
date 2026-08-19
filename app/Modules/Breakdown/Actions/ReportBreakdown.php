<?php

declare(strict_types=1);

namespace App\Modules\Breakdown\Actions;

use App\Modules\Asset\Actions\ChangeAssetStatus;
use App\Modules\Asset\Models\Asset;
use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Breakdown\Models\BreakdownStatusHistory;
use App\Modules\Breakdown\Models\DowntimeReasonCode;
use App\Modules\Breakdown\Services\DowntimeCalculator;
use App\Modules\Notification\Services\MaintenanceNotifier;
use App\Modules\Settings\Services\NumberSequenceGenerator;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reports a machine as broken down (SRS 15).
 *
 * Reporting is deliberately the lowest-friction thing in the product. An
 * operator standing at a stopped machine needs the asset, when it stopped and
 * what happened; everything else — failure code, root cause, corrective action
 * — is filled in by maintenance later. Demanding a diagnosis at report time
 * gets you either a delayed report or a guessed code, and both are worse than
 * an incomplete one.
 */
class ReportBreakdown
{
    public function __construct(
        private readonly NumberSequenceGenerator $numbers,
        private readonly ChangeAssetStatus $assetStatus,
        private readonly DowntimeCalculator $downtime,
        private readonly MaintenanceNotifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?string $userId = null): Breakdown
    {
        $asset = Asset::find($data['asset_id'] ?? null);

        if ($asset === null) {
            throw ValidationException::withMessages([
                'asset_id' => __('breakdown.asset_not_found'),
            ]);
        }

        if ($asset->isTerminal()) {
            throw ValidationException::withMessages([
                'asset_id' => __('breakdown.asset_terminal', ['status' => $asset->status]),
            ])->status(409);
        }

        $now = CarbonImmutable::now();
        $reportedAt = $this->parse($data['reported_at'] ?? null) ?? $now;
        $failureAt = $this->parse($data['failure_at'] ?? null) ?? $reportedAt;

        // Rule 1: the machine cannot break after it was reported broken. Almost
        // always a mistyped date, and left alone it produces a negative
        // response time that poisons the average.
        if ($failureAt->greaterThan($reportedAt)) {
            throw ValidationException::withMessages([
                'failure_at' => __('breakdown.failure_after_report'),
            ]);
        }

        if ($reportedAt->greaterThan($now->addMinutes(5))) {
            throw ValidationException::withMessages([
                'reported_at' => __('breakdown.reported_in_future'),
            ]);
        }

        $factory = Factory::findOrFail($asset->current_factory_id);
        $recurrenceOf = $this->openBreakdownFor($asset);

        return DB::transaction(function () use (
            $data, $asset, $factory, $failureAt, $reportedAt, $recurrenceOf, $userId
        ): Breakdown {
            $breakdown = Breakdown::create([
                'factory_id' => $factory->id,
                'asset_id' => $asset->id,
                'asset_location_id' => $asset->asset_location_id,
                'production_line_id' => $data['production_line_id'] ?? null,
                'breakdown_number' => $this->numbers->next('BREAKDOWN', $factory),
                'reported_by' => $userId,
                'failure_at' => $failureAt,
                'reported_at' => $reportedAt,
                'status' => 'REPORTED',
                'priority' => $data['priority'] ?? $this->priorityFor($asset),
                'severity' => $data['severity'] ?? 'MAJOR',
                'problem_description' => $data['problem_description'],
                'failure_category_id' => $data['failure_category_id'] ?? null,
                'failure_code_id' => $data['failure_code_id'] ?? null,
                'production_order_reference' => $data['production_order_reference'] ?? null,
                'downtime_class' => 'UNPLANNED',
                'downtime_reason_code_id' => $data['downtime_reason_code_id']
                    ?? $this->defaultReasonCodeId($asset->company_id),
                // Rule 4: a second report against a machine already down is the
                // same event. Counting it independently halves MTBF for a
                // machine that broke once.
                'is_recurrence_of_breakdown_id' => $recurrenceOf?->id,
            ]);

            BreakdownStatusHistory::create([
                'breakdown_id' => $breakdown->id,
                'from_status' => null,
                'to_status' => 'REPORTED',
                'changed_by' => $userId,
                'changed_at' => CarbonImmutable::now(),
                'reason' => $recurrenceOf === null
                    ? __('breakdown.reported')
                    : __('breakdown.linked_to_open', ['number' => $recurrenceOf->breakdown_number]),
            ]);

            // The asset record should say the machine is down while it is down,
            // not after the paperwork is filed. Guarded rather than forced: a
            // machine already under repair stays there.
            if ($asset->canTransitionTo('BREAKDOWN')) {
                $this->assetStatus->handle(
                    $asset, 'BREAKDOWN', $userId,
                    "Breakdown {$breakdown->breakdown_number} reported",
                    'BREAKDOWN',
                );
            }

            // Refreshed first: a just-created model does not carry the column
            // defaults the database applied, so hold_minutes and version are
            // absent rather than zero until it is reloaded.
            $breakdown = $breakdown->fresh();

            // Written immediately, so an open stoppage is visible as it accrues
            // rather than appearing as zero until somebody closes it.
            $this->downtime->forBreakdown($breakdown);

            // Last, and unable to fail the report: the machine is down whether
            // or not anyone could be told about it.
            $this->notifier->breakdownReported($breakdown);

            return $breakdown->fresh();
        });
    }

    /**
     * An open breakdown on the same machine. The new report is linked to it
     * rather than treated as an independent failure.
     */
    private function openBreakdownFor(Asset $asset): ?Breakdown
    {
        return Breakdown::where('asset_id', $asset->id)
            ->whereIn('status', Breakdown::OPEN_STATUSES)
            ->whereNull('is_recurrence_of_breakdown_id')
            ->orderByDesc('reported_at')
            ->first();
    }

    /**
     * A critical machine stopping is a critical breakdown by default. The
     * reporter may raise it, but they should not have to think about it while
     * a line is stopped.
     */
    private function priorityFor(Asset $asset): string
    {
        return match ($asset->criticality) {
            'CRITICAL' => 'CRITICAL',
            'HIGH' => 'HIGH',
            'LOW' => 'LOW',
            default => 'MEDIUM',
        };
    }

    private function defaultReasonCodeId(string $companyId): ?string
    {
        return DowntimeReasonCode::query()
            ->availableTo($companyId)
            ->where('code', 'MACHINE_BREAKDOWN')
            ->where('active', true)
            ->value('id');
    }

    private function parse(mixed $value): ?CarbonImmutable
    {
        return blank($value) ? null : CarbonImmutable::parse((string) $value);
    }
}
