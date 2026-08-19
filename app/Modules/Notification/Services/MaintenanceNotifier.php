<?php

declare(strict_types=1);

namespace App\Modules\Notification\Services;

use App\Modules\Asset\Models\Asset;
use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\WorkOrder\Models\Technician;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Turns things that happen into notifications for the right people (SRS 27).
 *
 * Kept apart from the domain actions so a notification failure can never roll
 * back the thing it was announcing. A breakdown that fails to save because
 * nobody could be emailed about it is the wrong trade every time.
 *
 * Who gets told is decided by role rather than by name. A factory whose
 * maintenance manager changes on Monday should not need its notification
 * routing edited.
 */
class MaintenanceNotifier
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    /**
     * A machine has stopped.
     *
     * Critical machines raise a different event, not merely a louder severity,
     * because escalation rules key off the event: a line-stopping failure and
     * a spare bench machine deserve different chains.
     */
    public function breakdownReported(Breakdown $breakdown): void
    {
        $this->guard(function () use ($breakdown): void {
            $asset = Asset::find($breakdown->asset_id);
            $isCritical = in_array($asset?->criticality, ['CRITICAL', 'HIGH'], true);

            $this->dispatcher->sendToMany(
                recipients: $this->holdersOf('MAINTENANCE_MANAGER', $breakdown->factory_id)
                    ->merge($this->holdersOf('MAINTENANCE_ENGINEER', $breakdown->factory_id))
                    ->unique('id'),
                eventType: $isCritical ? 'BREAKDOWN_CRITICAL' : 'BREAKDOWN_REPORTED',
                data: [
                    'asset' => $asset?->asset_code ?? '',
                    'number' => $breakdown->breakdown_number,
                    'problem' => Str::limit($breakdown->problem_description, 120),
                ],
                severity: $isCritical ? 'CRITICAL' : 'WARNING',
                factoryId: $breakdown->factory_id,
                entityType: 'breakdown',
                entityId: $breakdown->id,
                actionUrl: route('app.breakdowns.show', $breakdown->id),
            );
        });
    }

    /**
     * Work has been given to somebody. Told to the technician, not to their
     * manager: the person who has to do it is the person who needs to know.
     */
    public function workOrderAssigned(WorkOrder $workOrder, Technician $technician): void
    {
        $this->guard(function () use ($workOrder, $technician): void {
            if ($technician->user_id === null) {
                // A technician without a login is told by their supervisor.
                // Silently dropping it would be wrong; there is simply nobody
                // to address.
                return;
            }

            $user = User::find($technician->user_id);

            if ($user === null) {
                return;
            }

            $this->dispatcher->send(
                recipient: $user,
                eventType: 'WORK_ORDER_ASSIGNED',
                data: [
                    'number' => $workOrder->work_order_number,
                    'title' => $workOrder->title,
                    'asset' => Asset::find($workOrder->asset_id)?->asset_code ?? '',
                ],
                severity: $workOrder->priority === 'CRITICAL' ? 'CRITICAL' : 'INFO',
                factoryId: $workOrder->factory_id,
                entityType: 'work_order',
                entityId: $workOrder->id,
                actionUrl: route('app.work-orders.show', $workOrder->id),
            );
        });
    }

    /**
     * Somebody's signature is needed. Told to whoever can actually give it,
     * which is the point of routing by role.
     *
     * @param  Collection<int, User>  $approvers
     */
    public function approvalRequested(WorkOrder $workOrder, Collection $approvers, string $cost): void
    {
        $this->guard(function () use ($workOrder, $approvers, $cost): void {
            $this->dispatcher->sendToMany(
                recipients: $approvers,
                eventType: 'APPROVAL_REQUESTED',
                data: [
                    'number' => $workOrder->work_order_number,
                    'title' => $workOrder->title,
                    'cost' => number_format((float) $cost, 2).' '.($workOrder->currency ?? 'BDT'),
                ],
                severity: 'WARNING',
                factoryId: $workOrder->factory_id,
                entityType: 'work_order',
                entityId: $workOrder->id,
                actionUrl: route('app.approvals'),
            );
        });
    }

    /**
     * A part has fallen to its reorder level. Told to the store, not to
     * maintenance: the people who can do something about it are the ones who
     * order.
     */
    public function lowStock(SparePart $part, string $onHand): void
    {
        $this->guard(function () use ($part, $onHand): void {
            $this->dispatcher->sendToMany(
                recipients: $this->holdersOf('STORE_MANAGER')
                    ->merge($this->holdersOf('STOREKEEPER'))
                    ->unique('id'),
                eventType: 'LOW_STOCK',
                data: [
                    'part' => $part->part_number,
                    'on_hand' => rtrim(rtrim($onHand, '0'), '.'),
                    'reorder_level' => rtrim(rtrim((string) $part->reorder_level, '0'), '.'),
                ],
                // A critical spare running out stops a critical machine, so it
                // is not the same warning as a box of washers.
                severity: $part->is_critical_spare ? 'CRITICAL' : 'WARNING',
                entityType: 'spare_part',
                entityId: $part->id,
                actionUrl: route('app.inventory.parts.show', $part->id),
            );
        });
    }

    /**
     * Everyone in this company holding a role, optionally narrowed to a
     * factory.
     *
     * A company-wide holder is included whatever the factory; a factory-scoped
     * one only for their own site. Telling the Gazipur manager about a Dhaka
     * breakdown is how a notification list gets muted.
     *
     * @return Collection<int, User>
     */
    private function holdersOf(string $roleCode, ?string $factoryId = null): Collection
    {
        $role = Role::whereNull('company_id')
            ->where('code', $roleCode)
            ->first();

        if ($role === null) {
            return collect();
        }

        $userIds = UserRole::withoutGlobalScope(TenantScope::class)
            ->where('company_id', app(TenantContext::class)->companyId())
            ->where('role_id', $role->id)
            ->when(
                $factoryId !== null,
                fn ($q) => $q->where(fn ($w) => $w->whereNull('factory_id')->orWhere('factory_id', $factoryId)),
            )
            ->pluck('user_id')
            ->unique();

        return User::whereIn('id', $userIds)->where('status', 'ACTIVE')->get();
    }

    /**
     * Notification failures are logged, never propagated.
     *
     * The thing being announced has already happened and is already committed.
     * Failing the breakdown report because the notification could not be
     * written would lose the record that actually matters.
     */
    private function guard(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            Log::warning('Notification dispatch failed', ['error' => $e->getMessage()]);
        }
    }
}
