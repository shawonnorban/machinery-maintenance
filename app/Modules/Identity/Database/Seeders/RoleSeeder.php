<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Seeders;

use App\Modules\Identity\Support\RoleBlueprint;
use App\Modules\Identity\Support\RoleProvisioner;
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
            'settings.backup.restore',
        ]));

        $matrix = [
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

        // Everyone who works in the company can raise a request and decide
        // the ones that reach them (Approval Center, AnnotechERP 04); viewers
        // and auditors only look.
        foreach ($matrix as $code => $role) {
            if (is_array($role['permissions']) && ! in_array($code, ['PLATFORM_SUPER_ADMIN', 'VIEWER', 'AUDITOR'], true)) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$role['permissions'], 'approval.ticket.create']));
            }
        }

        // Merchandise Desk's reference lists (AnnotechERP 06/01): the factory
        // admin keeps them, as with the other master data; managers and the
        // store read them.
        // Inquiries and development styles (AnnotechERP 06/05) go with the merchandising master data.
        foreach (['FACTORY_ADMIN' => ['merchandise.master.view', 'merchandise.master.manage', 'merchandise.inquiry.view', 'merchandise.inquiry.manage', 'merchandise.style.view', 'merchandise.style.manage', 'merchandise.style.approve', 'merchandise.order.view', 'merchandise.order.manage', 'merchandise.costing.view', 'merchandise.costing.manage', 'merchandise.costing.approve', 'merchandise.sample.view', 'merchandise.sample.manage', 'merchandise.booking.view', 'merchandise.booking.manage', 'merchandise.tna.view', 'merchandise.tna.manage', 'merchandise.report.view', 'merchandise.accessories.view', 'merchandise.accessories.manage', 'merchandise.accessories.approve'], 'FACTORY_MANAGER' => ['merchandise.master.view', 'merchandise.inquiry.view', 'merchandise.style.view', 'merchandise.order.view', 'merchandise.costing.view', 'merchandise.sample.view', 'merchandise.booking.view', 'merchandise.tna.view', 'merchandise.report.view', 'merchandise.accessories.view', 'merchandise.accessories.approve'], 'STORE_MANAGER' => ['merchandise.master.view', 'merchandise.booking.view', 'merchandise.accessories.view']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // Buyer Gateway → Access & Onboarding (AnnotechERP 25/01): the factory
        // admin and manager run the buyer portal's accounts; the auditor reads them.
        foreach (['FACTORY_ADMIN' => ['buyergateway.access.view', 'buyergateway.access.manage', 'buyergateway.visibility.view', 'buyergateway.visibility.manage', 'buyergateway.billing.view', 'buyergateway.billing.manage', 'buyergateway.engagement.view', 'buyergateway.engagement.manage', 'buyergateway.admin.view', 'buyergateway.admin.manage', 'aiimport.intake.view', 'aiimport.intake.manage', 'commandcenter.command.view', 'commandcenter.command.manage'], 'FACTORY_MANAGER' => ['buyergateway.access.view', 'buyergateway.access.manage', 'buyergateway.visibility.view', 'buyergateway.visibility.manage', 'buyergateway.billing.view', 'buyergateway.billing.manage', 'buyergateway.engagement.view', 'buyergateway.engagement.manage', 'buyergateway.admin.view', 'buyergateway.admin.manage', 'aiimport.intake.view', 'aiimport.intake.manage', 'commandcenter.command.view', 'commandcenter.command.manage'], 'AUDITOR' => ['buyergateway.access.view', 'buyergateway.visibility.view', 'buyergateway.billing.view', 'buyergateway.engagement.view', 'buyergateway.admin.view', 'aiimport.intake.view', 'commandcenter.command.view']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // HR & Payroll masters (AnnotechERP 24/01): the factory admin keeps
        // them — pay elements, shifts, leave and OT rules, gazettes, pay
        // periods; the factory manager and the auditor read them.
        foreach (['FACTORY_ADMIN' => ['hrpayroll.master.view', 'hrpayroll.master.manage', 'hrpayroll.employee.view', 'hrpayroll.employee.manage', 'hrpayroll.biometric.view', 'hrpayroll.biometric.manage', 'settings.device.view', 'settings.device.manage', 'hrpayroll.attendance.view', 'hrpayroll.attendance.manage', 'hrpayroll.attendance.approve', 'hrpayroll.leave.view', 'hrpayroll.leave.manage', 'hrpayroll.ot.view', 'hrpayroll.ot.manage', 'hrpayroll.ot.approve', 'hrpayroll.salary.view', 'hrpayroll.salary.calculate', 'hrpayroll.salary.approve', 'hrpayroll.salary.finalize', 'hrpayroll.adjustment.manage', 'hrpayroll.adjustment.approve', 'hrpayroll.movement.view', 'hrpayroll.movement.manage', 'hrpayroll.movement.approve', 'hrpayroll.separation.view', 'hrpayroll.separation.manage', 'hrpayroll.separation.approve', 'hrpayroll.letter.view', 'hrpayroll.letter.template', 'hrpayroll.letter.generate', 'hrpayroll.dashboard.view', 'hrpayroll.report.view', 'hrpayroll.report.export'], 'FACTORY_MANAGER' => ['hrpayroll.master.view', 'hrpayroll.employee.view', 'hrpayroll.biometric.view', 'settings.device.view', 'hrpayroll.attendance.view', 'hrpayroll.attendance.approve', 'hrpayroll.leave.view', 'hrpayroll.ot.view', 'hrpayroll.ot.approve', 'hrpayroll.salary.view', 'hrpayroll.salary.approve', 'hrpayroll.adjustment.approve', 'hrpayroll.movement.view', 'hrpayroll.movement.approve', 'hrpayroll.separation.view', 'hrpayroll.separation.approve', 'hrpayroll.letter.view', 'hrpayroll.letter.template', 'hrpayroll.letter.generate', 'hrpayroll.dashboard.view', 'hrpayroll.report.view', 'hrpayroll.report.export'], 'AUDITOR' => ['hrpayroll.master.view', 'hrpayroll.employee.view', 'hrpayroll.biometric.view', 'settings.device.view', 'hrpayroll.attendance.view', 'hrpayroll.leave.view', 'hrpayroll.ot.view', 'hrpayroll.salary.view', 'hrpayroll.movement.view', 'hrpayroll.separation.view', 'hrpayroll.letter.view', 'hrpayroll.dashboard.view', 'hrpayroll.report.view']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // Finance & Ledger masters (AnnotechERP 23/01): kept at company level —
        // the owner and the company admin hold every permission — and read by
        // the auditor.
        foreach (['FACTORY_ADMIN' => ['accounting.voucher.view', 'accounting.voucher.manage', 'accounting.voucher.post', 'accounting.bill.view', 'accounting.bill.manage', 'accounting.period.view', 'accounting.period.manage', 'accounting.cheque.view', 'accounting.cheque.manage', 'accounting.report.view', 'accounting.report.manage'], 'FACTORY_MANAGER' => ['accounting.voucher.view', 'accounting.voucher.manage', 'accounting.voucher.post', 'accounting.bill.view', 'accounting.bill.manage', 'accounting.period.view', 'accounting.period.manage', 'accounting.cheque.view', 'accounting.cheque.manage', 'accounting.report.view', 'accounting.report.manage'], 'AUDITOR' => ['accounting.master.view', 'accounting.voucher.view', 'accounting.bill.view', 'accounting.period.view', 'accounting.cheque.view', 'accounting.report.view']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // Trade & Export masters (AnnotechERP 22/01): the factory admin and the
        // factory manager keep them; the auditor reads them.
        // Sales & Shipment → Operation (AnnotechERP 21/01): the factory admin
        // and the factory manager set the module's rules and prefixes; the
        // auditor reads them.
        // Order & Delivery (AnnotechERP 21/02): the factory admin and manager run the whole book (only the
        // admin closes a month over its exceptions); the store manager and storekeeper raise challans and
        // returns against the orders they read; the auditor reads everything.
        foreach (['FACTORY_ADMIN' => ['salesshipment.settings.view', 'salesshipment.settings.manage', 'salesshipment.order.view', 'salesshipment.order.manage', 'salesshipment.order.approve', 'salesshipment.delivery.view', 'salesshipment.delivery.manage', 'salesshipment.bill.view', 'salesshipment.bill.manage', 'salesshipment.bill.approve', 'salesshipment.close.manage', 'salesshipment.close.override', 'salesshipment.subcon.view', 'salesshipment.subcon.manage', 'salesshipment.subcon.reopen', 'salesshipment.shipment.view', 'salesshipment.shipment.manage', 'salesshipment.shipment.confirm', 'salesshipment.shipment.reopen', 'salesshipment.exportdoc.view', 'salesshipment.exportdoc.manage', 'salesshipment.report.view'], 'FACTORY_MANAGER' => ['salesshipment.settings.view', 'salesshipment.settings.manage', 'salesshipment.order.view', 'salesshipment.order.manage', 'salesshipment.order.approve', 'salesshipment.delivery.view', 'salesshipment.delivery.manage', 'salesshipment.bill.view', 'salesshipment.bill.manage', 'salesshipment.bill.approve', 'salesshipment.close.manage', 'salesshipment.subcon.view', 'salesshipment.subcon.manage', 'salesshipment.shipment.view', 'salesshipment.shipment.manage', 'salesshipment.shipment.confirm', 'salesshipment.exportdoc.view', 'salesshipment.exportdoc.manage', 'salesshipment.report.view'], 'STORE_MANAGER' => ['salesshipment.order.view', 'salesshipment.delivery.view', 'salesshipment.delivery.manage', 'salesshipment.subcon.view', 'salesshipment.subcon.manage', 'salesshipment.shipment.view', 'salesshipment.exportdoc.view', 'salesshipment.report.view'], 'STOREKEEPER' => ['salesshipment.order.view', 'salesshipment.delivery.view', 'salesshipment.delivery.manage', 'salesshipment.subcon.view', 'salesshipment.subcon.manage'], 'AUDITOR' => ['salesshipment.settings.view', 'salesshipment.order.view', 'salesshipment.delivery.view', 'salesshipment.bill.view', 'salesshipment.subcon.view', 'salesshipment.shipment.view', 'salesshipment.exportdoc.view', 'salesshipment.report.view']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        foreach (['FACTORY_ADMIN' => ['tradeexport.master.view', 'tradeexport.master.manage', 'tradeexport.export.view', 'tradeexport.export.manage', 'tradeexport.import.view', 'tradeexport.import.manage', 'tradeexport.report.view'], 'FACTORY_MANAGER' => ['tradeexport.master.view', 'tradeexport.master.manage', 'tradeexport.export.view', 'tradeexport.export.manage', 'tradeexport.import.view', 'tradeexport.import.manage', 'tradeexport.report.view'], 'AUDITOR' => ['tradeexport.master.view', 'tradeexport.export.view', 'tradeexport.import.view', 'tradeexport.report.view']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // Cutting Floor (AnnotechERP 19): the factory admin, the factory manager
        // and the line chief cut and print bundle cards; the store manager reads them.
        // Reports (19/04): all four read them.
        foreach (['FACTORY_ADMIN' => ['cutting.bundle.view', 'cutting.bundle.manage', 'cutting.input.manage', 'cutting.report.view'], 'FACTORY_MANAGER' => ['cutting.bundle.view', 'cutting.bundle.manage', 'cutting.input.manage', 'cutting.report.view'], 'LINE_CHIEF' => ['cutting.bundle.view', 'cutting.bundle.manage', 'cutting.report.view'], 'STORE_MANAGER' => ['cutting.bundle.view', 'cutting.report.view']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // Production Tracking (AnnotechERP 30), Brickland's "who may": everyone with
        // the module views; the factory and production managers prepare plans and
        // set TVs; the factory manager approves (never their own) and sets
        // capacity; the line chief keys Production Master Data.
        foreach (['FACTORY_ADMIN' => ['productiontracking.view', 'productiontracking.entry.manage', 'productiontracking.capacity.manage', 'productiontracking.plan.prepare', 'productiontracking.plan.approve', 'productiontracking.tv.manage', 'productiontracking.settings.manage'], 'FACTORY_MANAGER' => ['productiontracking.view', 'productiontracking.entry.manage', 'productiontracking.capacity.manage', 'productiontracking.plan.prepare', 'productiontracking.plan.approve', 'productiontracking.tv.manage'], 'LINE_CHIEF' => ['productiontracking.view', 'productiontracking.entry.manage'], 'MAINTENANCE_MANAGER' => ['productiontracking.view']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // Dev Sample Status (AnnotechERP 31): the factory admin does everything;
        // the factory manager reads every sample and runs the Pivot Report.
        foreach (['FACTORY_ADMIN' => ['devsample.view', 'devsample.view_all', 'devsample.create', 'devsample.update', 'devsample.delete', 'devsample.report', 'devsample.columns.manage'], 'FACTORY_MANAGER' => ['devsample.view', 'devsample.view_all', 'devsample.report']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // Export Statement (AnnotechERP 35): the factory admin does everything, its edits saved at once;
        // the factory manager reads the statements and exports them.
        foreach (['FACTORY_ADMIN' => ['exportstatement.view', 'exportstatement.create', 'exportstatement.update', 'exportstatement.delete', 'exportstatement.import', 'exportstatement.export', 'exportstatement.approve', 'exportstatement.columns.manage'], 'FACTORY_MANAGER' => ['exportstatement.view', 'exportstatement.export']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // Quality Tally (AnnotechERP 36): the factory admin and manager run it; a line chief reads sewing's.
        // (Nothing shows until the platform switches the module on — LicensedModules.)
        foreach (['FACTORY_ADMIN' => ['qtally.view', 'qtally.tally.create', 'qtally.job.manage', 'qtally.target.manage', 'qtally.inspector.manage', 'qtally.phone.manage', 'qtally.defect.manage', 'qtally.cutting.manage', 'qtally.sewing.manage', 'qtally.quality.manage'], 'FACTORY_MANAGER' => ['qtally.view', 'qtally.job.manage', 'qtally.target.manage', 'qtally.inspector.manage', 'qtally.phone.manage', 'qtally.cutting.manage', 'qtally.sewing.manage', 'qtally.quality.manage'], 'LINE_CHIEF' => ['qtally.sewing.view']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // Order Summary (AnnotechERP 32): the factory admin does everything; the
        // factory manager reads every order and runs the reports.
        foreach (['FACTORY_ADMIN' => ['ordersummary.view', 'ordersummary.view_all', 'ordersummary.create', 'ordersummary.update', 'ordersummary.delete', 'ordersummary.report', 'ordersummary.columns.manage', 'ordersummary.team.manage', 'ordersummary.mail.manage', 'ordersummary.rebudget'], 'FACTORY_MANAGER' => ['ordersummary.view', 'ordersummary.view_all', 'ordersummary.report']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // Pre-Budget (AnnotechERP 33/34): the factory admin does everything; the
        // factory manager reads every budget; Store fills the stock yarn use.
        foreach (['FACTORY_ADMIN' => ['prebudget.view', 'prebudget.view_all', 'prebudget.create', 'prebudget.update', 'prebudget.delete', 'prebudget.stock', 'prebudget.columns.manage', 'prebudget.master.view', 'prebudget.master.manage'], 'FACTORY_MANAGER' => ['prebudget.view', 'prebudget.view_all', 'prebudget.master.view'], 'STORE_MANAGER' => ['prebudget.stock'], 'STOREKEEPER' => ['prebudget.stock']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // Assembly Floor → Sewing (AnnotechERP 20/01): the factory admin, the
        // factory manager and the line chief scan bundles on and off the lines.
        foreach (['FACTORY_ADMIN' => ['assembly.sewing.view', 'assembly.sewing.manage'], 'FACTORY_MANAGER' => ['assembly.sewing.view', 'assembly.sewing.manage'], 'LINE_CHIEF' => ['assembly.sewing.view', 'assembly.sewing.manage']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // Assembly Floor → Finishing (AnnotechERP 20/02): the factory admin,
        // the factory manager and the line chief scan bundles on and off the
        // finishing lines; the admin and the manager supervise the rework.
        foreach (['FACTORY_ADMIN' => ['assembly.finishing.view', 'assembly.finishing.manage', 'assembly.finishing.supervise'], 'FACTORY_MANAGER' => ['assembly.finishing.view', 'assembly.finishing.manage', 'assembly.finishing.supervise'], 'LINE_CHIEF' => ['assembly.finishing.view', 'assembly.finishing.manage']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // Assembly Floor → Quality Management (AnnotechERP 20/03): the factory
        // admin, the factory manager and the line chief receive, inspect and
        // release bundles; the admin and the manager supervise the rework.
        foreach (['FACTORY_ADMIN' => ['assembly.quality.view', 'assembly.quality.manage', 'assembly.quality.supervise'], 'FACTORY_MANAGER' => ['assembly.quality.view', 'assembly.quality.manage', 'assembly.quality.supervise'], 'LINE_CHIEF' => ['assembly.quality.view', 'assembly.quality.manage']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // Assembly Floor → Packing and Packing Process (AnnotechERP 20/04–05):
        // the factory admin, the factory manager and the line chief pack in,
        // run the processes and pack out; the admin and the manager supervise
        // (remove a scan, reopen a process, unseal a carton).
        foreach (['FACTORY_ADMIN' => ['assembly.packing.view', 'assembly.packing.manage', 'assembly.packing.supervise'], 'FACTORY_MANAGER' => ['assembly.packing.view', 'assembly.packing.manage', 'assembly.packing.supervise'], 'LINE_CHIEF' => ['assembly.packing.view', 'assembly.packing.manage']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // Assembly Floor → Ready For Shipment (AnnotechERP 20/06): the factory
        // admin, the factory manager and the line chief read what is ready to
        // ship and the finished goods stock.
        foreach (['FACTORY_ADMIN' => ['assembly.ready.view'], 'FACTORY_MANAGER' => ['assembly.ready.view'], 'LINE_CHIEF' => ['assembly.ready.view']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // Assembly Floor → Shipment (AnnotechERP 20/07): the factory admin and
        // the factory manager enter, confirm, ship and correct shipments; the
        // line chief sees them.
        foreach (['FACTORY_ADMIN' => ['assembly.shipment.view', 'assembly.shipment.manage', 'assembly.shipment.supervise'], 'FACTORY_MANAGER' => ['assembly.shipment.view', 'assembly.shipment.manage', 'assembly.shipment.supervise'], 'LINE_CHIEF' => ['assembly.shipment.view']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // Assembly Floor → Accessories Store (AnnotechERP 20/08): the factory
        // admin, the factory manager and the store manager run the floor's
        // trims store and approve issues; the storekeeper writes the papers;
        // the line chief asks (writes issues) and sees the store.
        foreach (['FACTORY_ADMIN' => ['assembly.trims.view', 'assembly.trims.manage', 'assembly.trims.approve'], 'FACTORY_MANAGER' => ['assembly.trims.view', 'assembly.trims.manage', 'assembly.trims.approve'], 'STORE_MANAGER' => ['assembly.trims.view', 'assembly.trims.manage', 'assembly.trims.approve'], 'STOREKEEPER' => ['assembly.trims.view', 'assembly.trims.manage'], 'LINE_CHIEF' => ['assembly.trims.view', 'assembly.trims.manage']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // Assembly Floor → Reports (AnnotechERP 20/09): the factory admin, the
        // factory manager and the line chief run the Apparel Assembly Reports.
        foreach (['FACTORY_ADMIN' => ['assembly.report.view'], 'FACTORY_MANAGER' => ['assembly.report.view'], 'LINE_CHIEF' => ['assembly.report.view']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // Finishing Line masters (AnnotechERP 18/01): the factory admin and the
        // factory manager keep them; a line chief reads them. DF Production
        // (18/02): the admin, the manager and the line chief run the floor;
        // the store manager watches what reaches it. S/C Process (18/03): the
        // admin, the manager and the store (which sends the fabric out and
        // takes it back) keep it; the line chief reads it. QC Process (18/04):
        // the admin and the manager keep it; the line chief and the store read it.
        // Fabric Store (18/05): the admin, the manager and the store keep it;
        // the line chief reads it. Report (18/06): all four read it.
        foreach (['FACTORY_ADMIN' => ['finishing.master.view', 'finishing.master.manage', 'finishing.production.view', 'finishing.production.manage', 'finishing.sc.view', 'finishing.sc.manage', 'finishing.qc.view', 'finishing.qc.manage', 'finishing.store.view', 'finishing.store.manage', 'finishing.report.view'], 'FACTORY_MANAGER' => ['finishing.master.view', 'finishing.master.manage', 'finishing.production.view', 'finishing.production.manage', 'finishing.sc.view', 'finishing.sc.manage', 'finishing.qc.view', 'finishing.qc.manage', 'finishing.store.view', 'finishing.store.manage', 'finishing.report.view'], 'LINE_CHIEF' => ['finishing.master.view', 'finishing.production.view', 'finishing.production.manage', 'finishing.sc.view', 'finishing.qc.view', 'finishing.store.view', 'finishing.report.view'], 'STORE_MANAGER' => ['finishing.production.view', 'finishing.sc.view', 'finishing.sc.manage', 'finishing.qc.view', 'finishing.store.view', 'finishing.store.manage', 'finishing.report.view']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // Knit Production masters (AnnotechERP 15/01): the factory admin and the
        // factory manager keep them; a line chief reads them.
        foreach (['FACTORY_ADMIN' => ['knitting.master.view', 'knitting.master.manage', 'knitting.yarn.view', 'knitting.yarn.manage', 'knitting.yarn.approve', 'knitting.ystore.view', 'knitting.ystore.manage', 'knitting.production.view', 'knitting.production.manage', 'knitting.fstore.view', 'knitting.fstore.manage', 'knitting.needle.view', 'knitting.needle.manage', 'knitting.report.view', 'colourlab.view', 'colourlab.manage', 'colourlab.approve', 'colourlab.report.view'], 'FACTORY_MANAGER' => ['knitting.master.view', 'knitting.master.manage', 'knitting.yarn.view', 'knitting.yarn.manage', 'knitting.yarn.approve', 'knitting.ystore.view', 'knitting.ystore.manage', 'knitting.production.view', 'knitting.production.manage', 'knitting.fstore.view', 'knitting.fstore.manage', 'knitting.needle.view', 'knitting.needle.manage', 'knitting.report.view', 'colourlab.view', 'colourlab.approve', 'colourlab.report.view'], 'LINE_CHIEF' => ['knitting.master.view']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // Work Study masters (AnnotechERP 14/01): the factory admin and the
        // factory manager keep them; a line chief reads them.
        foreach (['FACTORY_ADMIN' => ['workstudy.master.view', 'workstudy.master.manage', 'workstudy.spec.view', 'workstudy.spec.manage', 'workstudy.costing.view', 'workstudy.costing.manage', 'workstudy.ot.view', 'workstudy.ot.manage', 'workstudy.target.view', 'workstudy.target.manage', 'workstudy.floorops.view', 'workstudy.floorops.manage', 'workstudy.profit.view', 'workstudy.profit.manage', 'workstudy.report.view'], 'FACTORY_MANAGER' => ['workstudy.master.view', 'workstudy.master.manage', 'workstudy.spec.view', 'workstudy.spec.manage', 'workstudy.costing.view', 'workstudy.costing.manage', 'workstudy.ot.view', 'workstudy.ot.manage', 'workstudy.target.view', 'workstudy.target.manage', 'workstudy.floorops.view', 'workstudy.floorops.manage', 'workstudy.profit.view', 'workstudy.profit.manage', 'workstudy.report.view'], 'LINE_CHIEF' => ['workstudy.master.view', 'workstudy.target.view', 'workstudy.floorops.view']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // Fabric Requirement masters (AnnotechERP 13/01): the factory admin
        // and the factory manager, who plan batches, keep them.
        foreach (['FACTORY_ADMIN' => ['fabricplanning.master.view', 'fabricplanning.master.manage', 'fabricplanning.plan.view', 'fabricplanning.plan.manage', 'fabricplanning.report.view'], 'FACTORY_MANAGER' => ['fabricplanning.master.view', 'fabricplanning.master.manage', 'fabricplanning.plan.view', 'fabricplanning.plan.manage', 'fabricplanning.report.view']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // Prototype Desk (AnnotechERP 07/03): the factory admin runs the
        // sample room's documents end to end; the factory manager reads them
        // and takes the approval levels.
        $proto = ['prototype.dashboard.view', 'prototype.sample360.view', 'prototype.alerts.view', 'prototype.requisition.view', 'prototype.development.view', 'prototype.revision.view', 'prototype.material.view', 'prototype.bundle.view', 'prototype.tracking.view', 'prototype.target.view', 'prototype.quality.view', 'prototype.approval.view', 'prototype.feedback.view', 'prototype.handover.view'];
        foreach (['FACTORY_ADMIN' => [...$proto, 'prototype.sample360.manage', 'prototype.alerts.manage', 'prototype.requisition.manage', 'prototype.requisition.approve', 'prototype.development.manage', 'prototype.revision.manage', 'prototype.revision.approve', 'prototype.material.manage', 'prototype.material.approve', 'prototype.material.issue', 'prototype.bundle.manage', 'prototype.tracking.manage', 'prototype.target.manage', 'prototype.quality.manage', 'prototype.approval.manage', 'prototype.feedback.manage', 'prototype.handover.manage'], 'FACTORY_MANAGER' => [...$proto, 'prototype.requisition.approve', 'prototype.revision.approve', 'prototype.material.approve']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // Dye House masters, Lab-Dip and Batch (Bulk) (AnnotechERP 17/01–17/03):
        // the factory admin and the factory manager keep them; the store manager,
        // who issues dyes, chemicals and the batch store's grey, reads them and
        // issues against a batch indent.
        foreach (['FACTORY_ADMIN' => ['dyehouse.master.view', 'dyehouse.master.manage', 'dyehouse.labdip.view', 'dyehouse.labdip.manage', 'dyehouse.batch.view', 'dyehouse.batch.manage', 'dyehouse.batch.issue', 'dyehouse.requisition.view', 'dyehouse.requisition.manage', 'dyehouse.requisition.approve', 'dyehouse.requisition.issue', 'dyehouse.chemstore.view', 'dyehouse.chemstore.manage', 'dyehouse.chemstore.issue', 'dyehouse.chemstore.approve', 'dyehouse.chemstore.close', 'dyehouse.production.view', 'dyehouse.production.manage', 'dyehouse.production.close', 'dyehouse.qc.view', 'dyehouse.qc.manage', 'dyehouse.sc.view', 'dyehouse.sc.manage', 'dyehouse.sc.issue'], 'FACTORY_MANAGER' => ['dyehouse.master.view', 'dyehouse.master.manage', 'dyehouse.labdip.view', 'dyehouse.labdip.manage', 'dyehouse.batch.view', 'dyehouse.batch.manage', 'dyehouse.requisition.view', 'dyehouse.requisition.manage', 'dyehouse.requisition.approve', 'dyehouse.chemstore.view', 'dyehouse.chemstore.manage', 'dyehouse.chemstore.approve', 'dyehouse.production.view', 'dyehouse.production.manage', 'dyehouse.production.close', 'dyehouse.qc.view', 'dyehouse.qc.manage', 'dyehouse.sc.view', 'dyehouse.sc.manage'], 'STORE_MANAGER' => ['dyehouse.master.view', 'dyehouse.labdip.view', 'dyehouse.batch.view', 'dyehouse.batch.issue', 'dyehouse.requisition.view', 'dyehouse.requisition.issue', 'dyehouse.chemstore.view', 'dyehouse.chemstore.manage', 'dyehouse.chemstore.issue', 'dyehouse.chemstore.close', 'dyehouse.sc.view', 'dyehouse.sc.issue']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // Capacity Planning's masters (AnnotechERP 12/01): the factory admin and
        // the factory manager, who plans the lines, keep them; line chiefs read
        // the standards their lines are planned on.
        foreach (['FACTORY_ADMIN' => ['planning.master.view', 'planning.master.manage'], 'FACTORY_MANAGER' => ['planning.master.view', 'planning.master.manage'], 'LINE_CHIEF' => ['planning.master.view']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // Production Plan (AnnotechERP 12/02): the factory manager plans and, as
        // planning manager, confirms and decides revisions, with the factory
        // admin; line chiefs follow the plans of their lines.
        $plans = ['planning.plan.view', 'planning.plan.manage', 'planning.plan.approve'];
        foreach (['FACTORY_ADMIN' => $plans, 'FACTORY_MANAGER' => $plans, 'LINE_CHIEF' => ['planning.plan.view']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // Gate Pass lists (AnnotechERP 11/01): the factory admin and the store
        // manager keep them; the factory manager and the storekeeper, who
        // raise passes, read them.
        foreach (['FACTORY_ADMIN' => ['gatepass.master.view', 'gatepass.master.manage'], 'STORE_MANAGER' => ['gatepass.master.view', 'gatepass.master.manage'], 'FACTORY_MANAGER' => ['gatepass.master.view'], 'STOREKEEPER' => ['gatepass.master.view']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // Gate passes (AnnotechERP 11/02–11/04): the factory admin does it all;
        // the factory manager prepares and approves; the store manager also
        // works the gate; the storekeeper prepares passes and works the gate.
        $passes = ['gatepass.view', 'gatepass.create'];
        foreach (['FACTORY_ADMIN' => [...$passes, 'gatepass.approve', 'gatepass.gate'], 'FACTORY_MANAGER' => [...$passes, 'gatepass.approve'], 'STORE_MANAGER' => [...$passes, 'gatepass.approve', 'gatepass.gate'], 'STOREKEEPER' => [...$passes, 'gatepass.gate']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // General Store's lists (AnnotechERP 10/01): the store manager and the
        // storekeeper keep them, with the factory admin; managers who draw on
        // the store read them.
        foreach (['FACTORY_ADMIN' => ['store.master.view', 'store.master.manage'], 'STORE_MANAGER' => ['store.master.view', 'store.master.manage'], 'STOREKEEPER' => ['store.master.view', 'store.master.manage'], 'FACTORY_MANAGER' => ['store.master.view'], 'MAINTENANCE_MANAGER' => ['store.master.view']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // General Store's stock (AnnotechERP 10/02–10/03): the storekeeper posts
        // documents; the store manager and the factory admin also cancel them;
        // managers who draw on the store see the stock and the ledger.
        // Requisition and Administration (10/04–10/07): anyone who draws on the
        // store raises requisitions; the store manager and the factory admin
        // approve them, read the audit log and keep the settings.
        $manage = ['store.requisition.create', 'store.requisition.approve', 'store.audit.view', 'store.settings.manage'];
        foreach (['FACTORY_ADMIN' => $manage, 'STORE_MANAGER' => $manage, 'STOREKEEPER' => ['store.requisition.create'], 'FACTORY_MANAGER' => ['store.requisition.create'], 'MAINTENANCE_MANAGER' => ['store.requisition.create']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }
        $inventory = ['store.inventory.view', 'store.inventory.post'];
        foreach (['FACTORY_ADMIN' => [...$inventory, 'store.inventory.cancel'], 'STORE_MANAGER' => [...$inventory, 'store.inventory.cancel'], 'STOREKEEPER' => $inventory, 'FACTORY_MANAGER' => ['store.inventory.view'], 'MAINTENANCE_MANAGER' => ['store.inventory.view']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // Material Procurement's catalogue and suppliers (AnnotechERP 08/01):
        // the factory admin and the store manager, who buys, keep them; the
        // factory manager and the storekeeper read them.
        // Purchase requisitions (08/03): raised by the factory admin and the
        // store manager; the factory manager and the storekeeper follow them.
        foreach (['FACTORY_ADMIN' => ['material.master.view', 'material.master.manage', 'material.requisition.view', 'material.requisition.manage', 'material.portal_access.manage', 'material.price.approve', 'material.followup.view', 'material.followup.monitor', 'material.audit.view'], 'STORE_MANAGER' => ['material.master.view', 'material.master.manage', 'material.requisition.view', 'material.requisition.manage', 'material.followup.view'], 'FACTORY_MANAGER' => ['material.master.view', 'material.requisition.view', 'material.followup.view', 'material.followup.monitor', 'material.audit.view'], 'STOREKEEPER' => ['material.master.view', 'material.requisition.view']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // Store & Material (09): the Store Manager reverses GRNs and approves adjustments; storekeepers receive, issue and count.
        $store = ['storematerial.view', 'storematerial.receive', 'storematerial.issue', 'storematerial.adjust'];
        foreach (['FACTORY_ADMIN' => [...$store, 'storematerial.manage'], 'STORE_MANAGER' => [...$store, 'storematerial.manage'], 'STOREKEEPER' => $store, 'FACTORY_MANAGER' => ['storematerial.view']] as $code => $extra) {
            if (isset($matrix[$code]) && is_array($matrix[$code]['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...$extra]));
            }
        }

        // Brickland's supplier portal account (08/07): an outside supplier who
        // signs in on the normal login page and works only in the Supplier
        // Portal — no request, no approval, nothing of the company's own.
        $matrix['SUPPLIER'] = ['name' => 'Supplier', 'scope' => 'COMPANY', 'permissions' => ['material.portal.view']];

        return self::byDepartment($matrix, $all);
    }

    /**
     * Roles by department and designation (user, 2026-11-22): each
     * department's Executive and Head with only the modules it works in; the
     * stores also keep the yarn, fabric, finished fabric and chemical stores;
     * Factory Manager becomes Management (GM / Director) — every module seen
     * and approved; and nobody but an admin keeps master data, company
     * settings or setup (RoleBlueprint::ADMIN_ONLY).
     *
     * @param  array<string, array{name: string, scope: string, permissions: list<string>|string}>  $matrix
     * @param  list<string>  $all
     * @return array<string, array{name: string, scope: string, permissions: list<string>|string, name_bn?: string, rank?: int}>
     */
    private static function byDepartment(array $matrix, array $all): array
    {
        $rank = 100;
        foreach (RoleBlueprint::departments() as $code => [$name, $nameBn, , $tier, $patterns]) {
            $matrix[$code] = ['name' => $name, 'name_bn' => $nameBn, 'scope' => 'FACTORY', 'rank' => $rank += 10,
                // Quality Tally's section roles see their own section's menu and nothing else (user, 2026-11-25).
                'permissions' => [...RoleBlueprint::expand($patterns, $tier, $all), ...(str_starts_with($code, 'QT_') ? [] : ['approval.ticket.create'])]];
        }
        $stores = ['knitting.ystore.*', 'knitting.fstore.*', 'knitting.needle.*', 'finishing.store.*', 'dyehouse.chemstore.*', 'assembly.trims.*', 'knitting.master.view', 'finishing.master.view', 'dyehouse.master.view'];
        foreach (['STOREKEEPER' => 'EXECUTIVE', 'STORE_MANAGER' => 'HEAD'] as $code => $tier) {
            $matrix[$code]['permissions'] = array_values(array_unique([...$matrix[$code]['permissions'], ...RoleBlueprint::expand($stores, $tier, $all)]));
        }
        $matrix['EMPLOYEE'] = ['name' => 'Employee', 'name_bn' => 'কর্মী', 'scope' => 'FACTORY', 'rank' => 900, 'permissions' => ['approval.ticket.create']];

        // Management sees every module and signs what reaches it.
        $oversee = array_filter($all, fn (string $c): bool => in_array(RoleBlueprint::verb($c), ['view', 'view_any', 'view_all', 'report', 'approve', 'reject', 'supervise', 'see_all', 'export'], true));
        $matrix['FACTORY_MANAGER']['name'] = 'Management (GM / Director)';
        $matrix['FACTORY_MANAGER']['name_bn'] = 'ম্যানেজমেন্ট (জিএম / ডিরেক্টর)';
        $matrix['FACTORY_MANAGER']['permissions'] = array_values(array_unique([...$matrix['FACTORY_MANAGER']['permissions'], ...$oversee]));

        foreach ($matrix as $code => $role) {
            if (! in_array($code, RoleBlueprint::ADMIN_ROLES, true) && is_array($role['permissions'])) {
                $matrix[$code]['permissions'] = array_values(array_filter($role['permissions'], fn (string $c): bool => ! RoleBlueprint::adminOnly($c)));
            }
        }

        return $matrix;
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
                ] + array_intersect_key($definition, array_flip(['name_bn', 'rank'])),
            );

            $ids = [];

            foreach ($definition['permissions'] as $permissionCode) {
                if (isset($permissionIds[$permissionCode])) {
                    $ids[] = $permissionIds[$permissionCode];
                }
            }

            $role->permissions()->sync(array_unique($ids));
        }

        // Each company's own (editable) copies follow the templates: a new
        // template is copied, a permission the template gained or lost is
        // carried over, and the company's own changes are kept.
        RoleProvisioner::syncAll();
    }
}
