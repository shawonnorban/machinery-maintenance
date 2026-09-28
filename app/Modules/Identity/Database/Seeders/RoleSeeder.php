<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The seeded role matrix: SRS 5.1 and 5.3.
 *
 * These are platform roles (company_id null, is_system true). A tenant clones
 * one to customize it; the seeded rows are never editable.
 *
 * Rules asserted by RoleMatrixTest:
 *   1. Every permission is granted to at least one role, or it is dead code.
 *   2. VIEWER and AUDITOR hold no write permission.
 *   3. No role references a permission outside the catalog.
 */
class RoleSeeder extends Seeder
{
    /** Actions that constitute a write. VIEWER and AUDITOR may hold none. */
    public const WRITE_ACTIONS = [
        'create', 'update', 'delete', 'restore', 'approve', 'reject',
        'assign', 'verify', 'close', 'cancel', 'manage', 'issue',
        'receive', 'return', 'reserve', 'dispatch', 'reverse', 'reopen',
        'start', 'complete', 'acknowledge', 'repair', 'activate',
        'publish', 'reschedule', 'skip', 'reset', 'regenerate', 'import',
    ];

    /**
     * @return array<string, array{name: string, scope: string, permissions: list<string>|string}>
     */
    public static function matrix(): array
    {
        $all = PermissionSeeder::allCodes();

        $readOnly = array_values(array_filter(
            $all,
            fn (string $c) => str_ends_with($c, '.view') || str_ends_with($c, '.view_any'),
        ));

        $technician = [
            'asset.asset.view_any', 'asset.asset.view',
            'maintenance.schedule.view_any',
            'meter.reading.view_any', 'meter.reading.create',
            'work_order.work_order.view_any', 'work_order.work_order.view',
            'work_order.work_order.start', 'work_order.work_order.complete',
            'work_order.labor.manage', 'work_order.part.request',
            'breakdown.breakdown.view_any', 'breakdown.breakdown.view',
            // Deliberately no `.create` — reporting is Line Chief's job now
            // (a floor supervisor is the one standing next to the machine
            // when it stops), not the repair technician's. A technician
            // still self-claims repair work straight off an acknowledged
            // report via `.repair` below; they just don't originate one.
            // Also no `.acknowledge` (moved to Engineer/Manager only,
            // SRS 15's extended sign-off chain) — acknowledging is now the
            // same click that raises the work order and rosters the line,
            // a decision the factory wants a level above the technician.
            'breakdown.breakdown.repair',
            'inventory.part.view_any', 'inventory.stock.view',
            // Anyone on the floor can ask the store for items.
            'itemmaster.item.view_any', 'requisition.floor.create',
        ];

        // Reports a breakdown when they spot one; never repairs it themselves.
        // Deliberately narrower than $technician — no acknowledge/repair
        // permissions, and `BreakdownScopeGuard` restricts *which* machines
        // they may even report against to their own line/department. Holds
        // `.verify` on its own (not inherited by Engineer/Manager) — the
        // Line Chief who owns this line is the one who confirms a repair
        // actually holds, before it goes to the Engineer/Manager for final
        // approval.
        $lineChief = [
            'asset.asset.view_any', 'asset.asset.view',
            'breakdown.breakdown.view_any', 'breakdown.breakdown.view',
            'breakdown.breakdown.create',
            'breakdown.breakdown.verify',
            'work_order.work_order.view_any', 'work_order.work_order.view',
            'dashboard.maintenance.view',
            'itemmaster.item.view_any', 'requisition.floor.create',
        ];

        $engineer = array_merge($technician, [
            'asset.asset.create', 'asset.asset.update', 'asset.status.update',
            'asset.document.manage', 'asset.transfer.create',
            'maintenance.plan.view_any', 'maintenance.plan.create',
            'maintenance.plan.update', 'maintenance.plan.activate',
            'maintenance.template.view_any', 'maintenance.template.create',
            'maintenance.template.update', 'maintenance.template.publish',
            'maintenance.schedule.reschedule',
            // A skip is a compliance exception, but deciding one is squarely a
            // maintenance decision: without this the role could not run its
            // own schedule.
            'maintenance.schedule.skip',
            'meter.meter.manage',
            'work_order.work_order.create', 'work_order.work_order.update',
            'work_order.work_order.assign', 'work_order.work_order.verify',
            'work_order.cost.view',
            // Re-added here (not just inherited) — engineers and above keep
            // the ability to report a breakdown themselves; only the plain
            // `$technician` tier lost it.
            'breakdown.breakdown.create',
            // Also re-added rather than inherited: acknowledge moved off
            // `$technician` entirely (see its own comment there), so an
            // Engineer/Manager needs it explicitly here, not via the merge.
            'breakdown.breakdown.acknowledge',
            'breakdown.breakdown.assign', 'breakdown.breakdown.close',
            'inventory.reservation.manage',
            'cost.entry.view',
            'dashboard.maintenance.view',
            'report.report.view',
        ]);

        $maintenanceManager = array_merge($engineer, [
            'work_order.work_order.close', 'work_order.work_order.cancel',
            'maintenance.plan.delete',
            'cost.entry.create',
            'technician.technician.manage', 'technician.performance.view',
            'admin.team.manage',
            'approval.request.approve', 'approval.request.reject',
            'report.report.export',
            'dashboard.management.view',
            'vendor.vendor.view_any',
            'asset.financial.view',
            'itemmaster.item.view_any',
            // Sees what the store is asking to buy and what is on order;
            // the approval itself goes through the approval engine above.
            'procurement.requisition.view_any', 'procurement.order.view_any',
            'procurement.receipt.view_any',
        ]);

        $storekeeper = [
            'asset.asset.view_any', 'asset.asset.view',
            'inventory.part.view_any', 'inventory.stock.view',
            'inventory.stock.receive', 'inventory.stock.issue',
            'inventory.stock.return', 'inventory.reservation.manage',
            'inventory.transfer.receive',
            'work_order.work_order.view_any', 'work_order.work_order.view',
            'dashboard.store.view',
            'itemmaster.item.view_any',
            // Receives at the gate and raises what the store needs; the
            // order itself is the store manager's.
            'procurement.requisition.view_any', 'procurement.requisition.create',
            'procurement.order.view_any',
            'procurement.receipt.view_any', 'procurement.receipt.create', 'procurement.receipt.complete',
            // Issuing across the counter is the same tier as completing a
            // goods receipt: the person who holds the stock.
            'requisition.floor.view_any', 'requisition.floor.create', 'requisition.floor.issue',
            // Receiving fabric and inspecting it are the same store-floor job.
            // Cutting a roll down to issue part of it is the same hands.
            'itemmaster.roll.view_any', 'itemmaster.roll.inspect', 'itemmaster.roll.split',
            // Sending cloth to a dyer and checking it back in is the
            // gate again, not a separate department.
            'itemmaster.subcontract.view_any', 'itemmaster.subcontract.manage',
            // Raises a return when goods fail at the gate; sending them
            // out is the store manager's.
            'procurement.return.view_any', 'procurement.return.create',
            // Issuing against a hold is the storekeeper's job, so seeing and
            // placing one has to be too.
            'itemmaster.reservation.view_any', 'itemmaster.reservation.manage',
            // Walks the aisles and writes down what is there; posting the
            // adjustment it produces is the store manager's.
            'itemmaster.count.view_any', 'itemmaster.count.create',
            // Reads the production plan and issues against a cutting
            // programme; the plan itself belongs to whoever runs production.
            'production.order.view_any', 'production.issue.create',
            // Needs to know what is landing and when to clear the floor
            // for it; the papers themselves are the buying desk's.
            'procurement.import.view_any',
            // Reads the agreed price when raising a requisition; agreeing
            // one is the buying desk's.
            'procurement.contract.view_any',
            // Sees how a mill has been performing; the scoring run itself
            // moves a rating, so it is not the storekeeper's to trigger.
            'procurement.scorecard.view_any',
            // Needs to know which stock is bonded before issuing it;
            // the licence itself is the compliance desk's.
            'compliance.bond.view_any',
        ];

        $storeManager = array_merge($storekeeper, [
            'inventory.part.create', 'inventory.part.update',
            'inventory.adjustment.create',
            'inventory.transfer.create', 'inventory.transfer.approve',
            'inventory.transfer.dispatch',
            'vendor.vendor.view_any', 'vendor.vendor.create', 'vendor.vendor.update',
            'itemmaster.item.manage',
            'procurement.order.create',
            // Booking fabric is buying-side work: it commits the factory to
            // a mill and turns into a purchase requisition.
            'procurement.booking.view_any', 'procurement.booking.manage',
            // A count adjustment moves stock value with nothing arriving
            // or leaving, so it sits with the same signature as landed cost.
            'itemmaster.count.post',
            // Landed cost changes what stock is worth, so posting it sits
            // with the person who signs for the store's valuation.
            'procurement.landed_cost.view_any', 'procurement.landed_cost.manage',
            'procurement.landed_cost.post',
            // Returning goods and debiting the supplier is buying-side
            // work; the storekeeper raises it, this role sends it out.
            'procurement.return.view_any', 'procurement.return.create',
            'procurement.return.dispatch',
            // Claims and advances are money, not stock — the same
            // buying-side desk that raises the order settles them.
            'procurement.claim.view_any', 'procurement.claim.manage',
            'procurement.advance.view_any', 'procurement.advance.manage',
            // The PI, the LC and the shipment behind an imported order
            // are the same desk's paperwork as the order itself.
            'procurement.import.view_any', 'procurement.import.manage',
            // A rate contract commits the factory to a price for a year,
            // so it sits with the desk that signs for the buying.
            'procurement.contract.view_any', 'procurement.contract.manage',
            'procurement.scorecard.view_any', 'procurement.scorecard.calculate',
            // Bond paperwork and the duty that falls due when goods leave
            // it: the same desk that signs for the importing.
            'compliance.bond.view_any', 'compliance.bond.manage', 'compliance.duty.assess',
            // Tallying a roll is the storekeeper's; accepting or sending
            // back a delivery is a decision with money attached.
            'itemmaster.inspection.approve',
            // Reads what the store's movements posted to the books; wiring
            // events to accounts is the company owner's.
            'finance.gl.view_any',
            'cost.entry.view',
            'report.report.view', 'report.report.export',
            'import.job.create', 'export.job.create',
        ]);

        $factoryManager = array_merge($maintenanceManager, [
            'asset.transfer.approve', 'asset.transfer.receive',
            // Undoing a posted cost changes a figure somebody has already
            // reported, so it sits with the person who signs off high-cost
            // work rather than with whoever entered it.
            'cost.entry.reverse',
            'asset.asset.delete',
            'work_order.work_order.reopen',
            'inventory.adjustment.create',
            'settings.factory.manage', 'settings.calendar.manage',
            'masterdata.manage', 'custom_field.definition.manage',
            'itemmaster.item.manage',
            'dashboard.store.view',
            'vendor.warranty.manage', 'vendor.contract.manage',
            'procurement.requisition.create', 'procurement.order.create',
            'procurement.receipt.create', 'procurement.receipt.complete',
            'requisition.floor.view_any', 'requisition.floor.create', 'requisition.floor.issue',
            // Production planning: the order book, its bills of materials and
            // the cutting programmes the store issues against. Sits with the
            // factory manager until a merchandising module owns it.
            'production.order.view_any', 'production.order.manage', 'production.issue.create',
            'procurement.booking.view_any', 'procurement.booking.manage',
        ]);

        $factoryAdmin = array_merge($factoryManager, [
            'admin.user.manage', 'admin.role.manage',
            'inventory.part.create', 'inventory.part.update',
            'inventory.stock.receive', 'inventory.stock.issue',
            'inventory.stock.return',
            'inventory.transfer.create', 'inventory.transfer.approve',
            'inventory.transfer.dispatch', 'inventory.transfer.receive',
            'import.job.create', 'export.job.create',
            'meter.meter.reset',
            'audit.log.view',
        ]);

        $companyAdmin = array_values(array_diff($all, [
            'billing.subscription.manage',
            'billing.payment.manage',
        ]));

        return [
            'PLATFORM_SUPER_ADMIN' => [
                'name' => 'Platform Super Admin',
                'scope' => 'PLATFORM',
                // No tenant data access by default; access requires an audited
                // support grant (SRS 5.4).
                'permissions' => [],
            ],
            'COMPANY_OWNER' => [
                'name' => 'Company Owner',
                'scope' => 'COMPANY',
                'permissions' => $all,
            ],
            'COMPANY_ADMIN' => [
                'name' => 'Company Admin',
                'scope' => 'COMPANY',
                'permissions' => $companyAdmin,
            ],
            'FACTORY_ADMIN' => [
                'name' => 'Factory Admin',
                'scope' => 'FACTORY',
                'permissions' => $factoryAdmin,
            ],
            'FACTORY_MANAGER' => [
                'name' => 'Factory Manager',
                'scope' => 'FACTORY',
                'permissions' => $factoryManager,
            ],
            'MAINTENANCE_MANAGER' => [
                'name' => 'Maintenance Manager',
                'scope' => 'FACTORY',
                'permissions' => $maintenanceManager,
            ],
            'MAINTENANCE_ENGINEER' => [
                'name' => 'Maintenance Engineer',
                'scope' => 'FACTORY',
                'permissions' => $engineer,
            ],
            'TECHNICIAN' => [
                'name' => 'Technician',
                'scope' => 'FACTORY',
                'permissions' => $technician,
            ],
            'LINE_CHIEF' => [
                'name' => 'Line Chief',
                'scope' => 'FACTORY',
                'permissions' => $lineChief,
            ],
            'STORE_MANAGER' => [
                'name' => 'Store Manager',
                'scope' => 'FACTORY',
                'permissions' => $storeManager,
            ],
            'STOREKEEPER' => [
                'name' => 'Storekeeper',
                'scope' => 'FACTORY',
                'permissions' => $storekeeper,
            ],
            'VIEWER' => [
                'name' => 'Viewer',
                'scope' => 'FACTORY',
                'permissions' => $readOnly,
            ],
            'AUDITOR' => [
                'name' => 'Auditor',
                'scope' => 'COMPANY',
                'permissions' => array_values(array_unique(
                    array_merge($readOnly, ['audit.log.view']),
                )),
            ],
        ];
    }

    public function run(): void
    {
        // As with PermissionSeeder: Spatie's `name` is the machine code
        // (its own uniqueness key), the human-readable label goes in
        // `description`.
        $permissionIds = Permission::pluck('id', 'name');

        foreach (self::matrix() as $code => $definition) {
            $role = Role::updateOrCreate(
                ['company_id' => null, 'name' => $code, 'guard_name' => 'web'],
                [
                    'description' => $definition['name'],
                    'scope' => $definition['scope'],
                    'is_system' => true,
                ],
            );

            $ids = [];

            foreach ($definition['permissions'] as $permissionCode) {
                if (isset($permissionIds[$permissionCode])) {
                    $ids[] = $permissionIds[$permissionCode];
                }
            }

            $role->permissions()->sync(array_unique($ids));
        }
    }
}
