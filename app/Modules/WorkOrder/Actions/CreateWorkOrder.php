<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Actions;

use App\Modules\Asset\Models\Asset;
use App\Modules\Maintenance\Models\MaintenanceSchedule;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\Settings\Services\NumberSequenceGenerator;
use App\Modules\Settings\Services\SettingsResolver;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Models\WorkOrderStatusHistory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Raises a work order, manually or from a due maintenance occurrence.
 *
 * Several fields are resolved here and then frozen: the checklist version, and
 * whether verification is required. Reading them live would mean a settings
 * change reopening work that was already closed, or a work order suddenly
 * demanding a checklist its technician never saw.
 */
class CreateWorkOrder
{
    public function __construct(
        private readonly NumberSequenceGenerator $numbers,
        private readonly SettingsResolver $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?string $userId = null): WorkOrder
    {
        $asset = Asset::find($data['asset_id'] ?? null);

        if ($asset === null) {
            throw ValidationException::withMessages([
                'asset_id' => __('work_order.asset_not_found'),
            ]);
        }

        if ($asset->isTerminal()) {
            throw ValidationException::withMessages([
                'asset_id' => __('work_order.asset_terminal', ['status' => $asset->status]),
            ])->status(409);
        }

        $factory = Factory::findOrFail($asset->current_factory_id);
        $type = MaintenanceType::find($data['maintenance_type_id'] ?? null);

        if ($type === null) {
            throw ValidationException::withMessages([
                'maintenance_type_id' => __('work_order.maintenance_type_required'),
            ]);
        }

        return DB::transaction(function () use ($data, $asset, $factory, $type, $userId): WorkOrder {
            $workOrder = WorkOrder::create([
                'factory_id' => $asset->current_factory_id,
                'asset_id' => $asset->id,
                'asset_location_id' => $asset->asset_location_id,
                'maintenance_schedule_id' => $data['maintenance_schedule_id'] ?? null,
                'breakdown_id' => $data['breakdown_id'] ?? null,
                'maintenance_type_id' => $type->id,
                'template_version_id' => $data['template_version_id'] ?? null,
                'work_order_number' => $this->numbers->next('WORK_ORDER', $factory),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => 'DRAFT',
                'priority' => $data['priority'] ?? $type->default_priority,
                'source' => $data['source'] ?? 'MANUAL',
                'requires_verification' => $this->verificationRequired($asset),
                'requires_shutdown' => (bool) ($data['requires_shutdown'] ?? false),
                // A planned job's stoppage should not count against
                // availability; an unplanned one should (ADR-049).
                'downtime_class' => $this->downtimeClass($type, (bool) ($data['requires_shutdown'] ?? false)),
                'assigned_team_id' => $data['assigned_team_id'] ?? null,
                'scheduled_start' => $data['scheduled_start'] ?? null,
                'scheduled_end' => $data['scheduled_end'] ?? null,
                'estimated_parts_cost' => $data['estimated_parts_cost'] ?? null,
                'currency' => $data['currency'] ?? 'BDT',
                'created_by' => $userId,
            ]);

            WorkOrderStatusHistory::create([
                'work_order_id' => $workOrder->id,
                'from_status' => null,
                'to_status' => 'DRAFT',
                'changed_by' => $userId,
                'changed_at' => CarbonImmutable::now(),
                'reason' => 'Work order raised',
            ]);

            return $workOrder;
        });
    }

    /**
     * Raises the work order a due maintenance occurrence calls for, carrying
     * the plan's checklist version across so the technician gets the list the
     * planner chose.
     */
    public function fromSchedule(MaintenanceSchedule $schedule, ?string $userId = null): WorkOrder
    {
        if (! $schedule->isOpen()) {
            throw ValidationException::withMessages([
                'maintenance_schedule_id' => __('work_order.schedule_not_open'),
            ])->status(409);
        }

        $existing = WorkOrder::where('maintenance_schedule_id', $schedule->id)
            ->whereNotIn('status', ['CANCELLED'])
            ->first();

        if ($existing !== null) {
            // One occurrence, one job. A second would send a technician to a
            // machine that is already being serviced.
            return $existing;
        }

        $plan = $schedule->plan;
        $asset = Asset::findOrFail($schedule->asset_id);

        $workOrder = $this->handle([
            'asset_id' => $asset->id,
            'maintenance_type_id' => $plan?->maintenance_type_id,
            'template_version_id' => $plan?->template_version_id,
            'maintenance_schedule_id' => $schedule->id,
            'title' => $plan?->name ?? __('work_order.scheduled_maintenance'),
            'priority' => $plan?->priority ?? 'MEDIUM',
            'source' => 'PLAN',
            'requires_shutdown' => (bool) ($plan?->requires_shutdown ?? false),
            'assigned_team_id' => $plan?->assigned_team_id,
            'scheduled_start' => $schedule->due_at,
        ], $userId);

        $schedule->forceFill([
            'work_order_id' => $workOrder->id,
            'status' => 'IN_PROGRESS',
        ])->save();

        return $workOrder;
    }

    /**
     * Frozen at creation. If this were read live, raising the threshold later
     * would silently drop the verification requirement from work already
     * completed but not yet closed.
     */
    private function verificationRequired(Asset $asset): bool
    {
        $criticalities = $this->settings->list('work_order.require_verification_for_criticality');

        return in_array($asset->criticality, $criticalities, true);
    }

    private function downtimeClass(MaintenanceType $type, bool $requiresShutdown): string
    {
        if (! $requiresShutdown) {
            return 'NONE';
        }

        return $type->is_planned ? 'PLANNED' : 'UNPLANNED';
    }
}
