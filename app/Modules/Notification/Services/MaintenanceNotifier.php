<?php

declare(strict_types=1);

namespace App\Modules\Notification\Services;

use App\Modules\Asset\Models\Asset;
use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Breakdown\Services\BreakdownScopeGuard;
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
                    ->merge($this->coveringTechnicianUsers($breakdown, $asset))
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
                // A tenant user's own screens are the Next.js app now (Phase
                // D/F), not this module's own (decommissioned) `/app` routes.
                actionUrl: config('tenancy.frontend_url').'/breakdowns/'.$breakdown->id,
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
                actionUrl: config('tenancy.frontend_url').'/work-orders/'.$workOrder->id,
            );
        });
    }

    /**
     * Somebody's signature is needed. Told to whoever can actually give it,
     * which is the point of routing by role.
     *
     * @param  Collection<int, User>  $approvers
     */
    /**
     * @param  array{number: string, title: string, url: string, factory_id: ?string}  $summary
     *         how the document names itself — a work order number, a PR number...
     * @param  string  $entityType  the approval engine's entity type (WORK_ORDER, PURCHASE_REQUISITION...)
     */
    public function approvalRequested(array $summary, string $entityType, string $entityId, Collection $approvers, string $cost, string $currency = 'BDT'): void
    {
        $this->guard(function () use ($summary, $entityType, $entityId, $approvers, $cost, $currency): void {
            $this->dispatcher->sendToMany(
                recipients: $approvers,
                eventType: 'APPROVAL_REQUESTED',
                data: [
                    'number' => $summary['number'],
                    'title' => $summary['title'],
                    'cost' => number_format((float) $cost, 2).' '.$currency,
                ],
                severity: 'WARNING',
                factoryId: $summary['factory_id'] ?? null,
                entityType: strtolower($entityType),
                entityId: $entityId,
                actionUrl: config('tenancy.frontend_url').'/approvals',
            );
        });
    }

    /**
     * The verdict, back to whoever asked. A requester who has to keep
     * opening the document to see whether it moved stops raising them.
     *
     * @param  array{number: string, title: string, url: string, factory_id: ?string}  $summary
     */
    public function approvalDecided(array $summary, string $entityType, string $entityId, ?User $requester, string $decision): void
    {
        if ($requester === null) {
            return;
        }

        $this->guard(function () use ($summary, $entityType, $entityId, $requester, $decision): void {
            $this->dispatcher->send(
                recipient: $requester,
                eventType: 'APPROVAL_DECIDED',
                data: [
                    'number' => $summary['number'],
                    'title' => $summary['title'],
                    'decision' => strtolower($decision),
                ],
                severity: $decision === 'APPROVED' ? 'INFO' : 'WARNING',
                factoryId: $summary['factory_id'] ?? null,
                entityType: strtolower($entityType),
                entityId: $entityId,
                actionUrl: $summary['url'],
            );
        });
    }

    /**
     * The repair is done (or the line's running again) — the Line Chief who
     * owns this floor is the one who confirms it actually holds, before it
     * goes anywhere near an Engineer/Manager's final approval (SRS 15's
     * extended sign-off chain).
     */
    public function breakdownAwaitingVerification(Breakdown $breakdown): void
    {
        $this->guard(function () use ($breakdown): void {
            $asset = Asset::find($breakdown->asset_id);

            $this->dispatcher->sendToMany(
                recipients: $this->coveringLineChiefUsers($breakdown),
                eventType: 'BREAKDOWN_AWAITING_VERIFICATION',
                data: [
                    'asset' => $asset?->asset_code ?? '',
                    'number' => $breakdown->breakdown_number,
                ],
                severity: 'INFO',
                factoryId: $breakdown->factory_id,
                entityType: 'breakdown',
                entityId: $breakdown->id,
                actionUrl: config('tenancy.frontend_url').'/breakdowns/'.$breakdown->id,
            );
        });
    }

    /**
     * The Line Chief has signed off — an Engineer/Manager's own signature is
     * what actually closes it, the same "somebody's signature is needed"
     * shape as `approvalRequested()`.
     */
    public function breakdownAwaitingApproval(Breakdown $breakdown): void
    {
        $this->guard(function () use ($breakdown): void {
            $asset = Asset::find($breakdown->asset_id);

            $this->dispatcher->sendToMany(
                recipients: $this->holdersOf('MAINTENANCE_MANAGER', $breakdown->factory_id)
                    ->merge($this->holdersOf('MAINTENANCE_ENGINEER', $breakdown->factory_id))
                    ->unique('id'),
                eventType: 'BREAKDOWN_AWAITING_APPROVAL',
                data: [
                    'asset' => $asset?->asset_code ?? '',
                    'number' => $breakdown->breakdown_number,
                ],
                severity: 'WARNING',
                factoryId: $breakdown->factory_id,
                entityType: 'breakdown',
                entityId: $breakdown->id,
                actionUrl: config('tenancy.frontend_url').'/breakdowns/'.$breakdown->id,
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
                actionUrl: config('tenancy.frontend_url').'/inventory/parts/'.$part->id,
            );
        });
    }

    /**
     * The technician(s) whose floor this breakdown happened on, not just
     * maintenance management. Nobody is assigned yet at report time (see
     * `BreakdownScopeGuard`), so this is every active, logged-in technician
     * this factory has whose own coverage area matches the machine's line or
     * department — the same people the hard restriction would later let
     * report or repair it.
     *
     * @return Collection<int, User>
     */
    private function coveringTechnicianUsers(Breakdown $breakdown, ?Asset $asset): Collection
    {
        $location = $asset?->asset_location_id === null ? null : $asset->location;

        $technicians = Technician::where('factory_id', $breakdown->factory_id)
            ->where('status', 'ACTIVE')
            ->whereNotNull('user_id')
            ->get()
            ->filter(fn (Technician $t): bool => $t->coversLocation($location?->department_id, $location?->production_line_id)
                // A factory-wide technician (no area named at all) is not
                // "covering" this one machine any more than any other — that
                // breadth is for sorting an assignment list, not for deciding
                // who gets woken up by every single report.
                && ($t->department_id !== null || $t->production_line_id !== null));

        return User::whereIn('id', $technicians->pluck('user_id'))->where('status', 'ACTIVE')->get();
    }

    /**
     * The Line Chief(s) whose own line/department covers this breakdown's
     * machine — reuses `BreakdownScopeGuard`'s own coverage matching (the
     * same check the write path enforces) rather than a third copy of it.
     *
     * @return Collection<int, User>
     */
    private function coveringLineChiefUsers(Breakdown $breakdown): Collection
    {
        $location = $breakdown->asset?->location;

        return $this->holdersOf('LINE_CHIEF', $breakdown->factory_id)
            ->filter(fn (User $user): bool => BreakdownScopeGuard::covers(
                BreakdownScopeGuard::coverageFor($user),
                $location?->department_id,
                $location?->production_line_id,
            ));
    }

    /**
     * Everyone in this company holding a role, optionally narrowed to a
     * factory.
     *
     * A company-wide holder is included whatever the factory; a factory-scoped
     * one only for their own site. Telling the Gazipur manager about a Dhaka
     * breakdown is how a notification list gets muted.
     *
     * Public so sibling notifiers (ItemNotifier) share one definition of
     * "who holds this role here" rather than each re-deriving it.
     *
     * @return Collection<int, User>
     */
    public function holdersOf(string $roleCode, ?string $factoryId = null): Collection
    {
        // Spatie's `name` is the machine code now (COMPANY_OWNER, ...).
        // The company's own copy of the role, else the seeded one.
        $role = \App\Modules\Identity\Support\RoleProvisioner::resolve($roleCode, app(TenantContext::class)->companyId());

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
