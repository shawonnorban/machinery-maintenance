<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/**
 * The permission catalog: Handbook Section 2.
 *
 * Codes follow {module}.{resource}.{action} (SRS 5.2). A permission absent
 * from this catalog cannot be granted, and a permission granted to no seeded
 * role is dead code that a seed test fails on (Seed Catalog 13).
 */
class PermissionSeeder extends Seeder
{
    /**
     * @return array<string, array<string, array{0: string, 1?: bool}>>
     *                                                                  module => [code => [label, isElevated]]
     */
    public static function catalog(): array
    {
        return [
            'asset' => [
                'asset.asset.view_any' => ['View assets'],
                'asset.asset.view' => ['View an asset'],
                'asset.asset.create' => ['Create assets'],
                'asset.asset.update' => ['Update assets'],
                'asset.asset.delete' => ['Archive assets'],
                'asset.status.update' => ['Change asset status'],
                'asset.transfer.create' => ['Request asset transfer'],
                'asset.transfer.approve' => ['Approve asset transfer'],
                'asset.transfer.receive' => ['Receive transferred asset'],
                'asset.document.manage' => ['Manage asset documents'],
                'asset.qr.regenerate' => ['Regenerate asset QR token', true],
                'asset.financial.view' => ['View asset cost and book value'],
            ],
            'maintenance' => [
                'maintenance.plan.view_any' => ['View maintenance plans'],
                'maintenance.plan.create' => ['Create maintenance plans'],
                'maintenance.plan.update' => ['Update maintenance plans'],
                'maintenance.plan.delete' => ['Delete maintenance plans'],
                'maintenance.plan.activate' => ['Activate or deactivate plans'],
                'maintenance.template.view_any' => ['View templates'],
                'maintenance.template.create' => ['Create templates'],
                'maintenance.template.update' => ['Update templates'],
                'maintenance.template.publish' => ['Publish a template version'],
                'maintenance.schedule.view_any' => ['View maintenance schedules'],
                'maintenance.schedule.reschedule' => ['Reschedule maintenance'],
                'maintenance.schedule.skip' => ['Skip scheduled maintenance'],
            ],
            'metering' => [
                'meter.reading.view_any' => ['View meter readings'],
                'meter.reading.create' => ['Record meter readings'],
                'meter.meter.manage' => ['Manage asset meters'],
                'meter.meter.reset' => ['Reset a meter', true],
            ],
            'work_order' => [
                'work_order.work_order.view_any' => ['View work orders'],
                'work_order.work_order.view' => ['View a work order'],
                'work_order.work_order.create' => ['Create work orders'],
                'work_order.work_order.update' => ['Update work orders'],
                'work_order.work_order.assign' => ['Assign work orders'],
                'work_order.work_order.start' => ['Start, hold and resume work'],
                'work_order.work_order.complete' => ['Complete work orders'],
                'work_order.work_order.verify' => ['Verify completed work'],
                'work_order.work_order.close' => ['Close work orders'],
                'work_order.work_order.cancel' => ['Cancel work orders'],
                'work_order.work_order.reopen' => ['Reopen a closed work order', true],
                'work_order.labor.manage' => ['Record labor time'],
                'work_order.part.request' => ['Request parts on a work order'],
                'work_order.cost.view' => ['View work order costs'],
            ],
            'breakdown' => [
                'breakdown.breakdown.view_any' => ['View breakdowns'],
                'breakdown.breakdown.view' => ['View a breakdown'],
                'breakdown.breakdown.create' => ['Report a breakdown'],
                'breakdown.breakdown.acknowledge' => ['Acknowledge a breakdown'],
                'breakdown.breakdown.assign' => ['Assign a breakdown'],
                'breakdown.breakdown.repair' => ['Record repair progress'],
                'breakdown.breakdown.verify' => ['Verify a repaired breakdown'],
                'breakdown.breakdown.close' => ['Close a breakdown'],
            ],
            'inventory' => [
                'inventory.part.view_any' => ['View spare parts'],
                'inventory.part.create' => ['Create spare parts'],
                'inventory.part.update' => ['Update spare parts'],
                'inventory.stock.view' => ['View stock balances and valuation'],
                'inventory.stock.receive' => ['Receive stock'],
                'inventory.stock.issue' => ['Issue and consume stock'],
                'inventory.stock.return' => ['Return stock'],
                'inventory.reservation.manage' => ['Reserve and release stock'],
                'inventory.adjustment.create' => ['Adjust stock', true],
                'inventory.transfer.create' => ['Request stock transfer'],
                'inventory.transfer.approve' => ['Approve stock transfer'],
                'inventory.transfer.dispatch' => ['Dispatch stock transfer'],
                'inventory.transfer.receive' => ['Receive stock transfer'],
            ],
            'cost' => [
                'cost.entry.view' => ['View cost entries'],
                'cost.entry.create' => ['Create cost entries'],
                'cost.entry.reverse' => ['Reverse a posted cost entry', true],
            ],
            'itemmaster' => [
                'itemmaster.item.view_any' => ['View the item library'],
                'itemmaster.item.manage' => ['Maintain the item library'],
                'itemmaster.roll.view_any' => ['View fabric rolls'],
                'itemmaster.roll.inspect' => ['Inspect fabric rolls at receiving'],
                'itemmaster.roll.split' => ['Split and merge fabric rolls'],
                'itemmaster.inspection.approve' => ['Accept or reject an inspected fabric lot', true],
                'itemmaster.subcontract.view_any' => ['View subcontract challans'],
                'itemmaster.subcontract.manage' => ['Send fabric out for subcontract and take it back'],
                'itemmaster.count.view_any' => ['View stock counts'],
                'itemmaster.count.create' => ['Open stock counts and record what was found'],
                'itemmaster.count.post' => ['Post a count\'s adjustment to the ledger', true],
                'itemmaster.reservation.view_any' => ['See who is holding stock'],
                'itemmaster.reservation.manage' => ['Hold stock by hand and release it'],
            ],
            'procurement' => [
                'procurement.requisition.view_any' => ['View purchase requisitions'],
                'procurement.requisition.create' => ['Raise and submit purchase requisitions'],
                'procurement.order.view_any' => ['View purchase orders'],
                'procurement.order.create' => ['Raise, revise and close purchase orders'],
                'procurement.receipt.view_any' => ['View goods receipts'],
                'procurement.receipt.create' => ['Draft goods receipts'],
                'procurement.receipt.complete' => ['Complete goods receipts and post stock'],
                'procurement.booking.view_any' => ['View fabric bookings'],
                'procurement.booking.manage' => ['Raise, confirm and convert fabric bookings'],
                'procurement.landed_cost.view_any' => ['View landed cost vouchers'],
                'procurement.landed_cost.manage' => ['Draft landed cost vouchers'],
                'procurement.landed_cost.post' => ['Post landed cost and re-value stock', true],
                'procurement.return.view_any' => ['View purchase returns'],
                'procurement.return.create' => ['Raise purchase returns'],
                'procurement.return.dispatch' => ['Send returned goods out and raise the debit note', true],
                'procurement.claim.view_any' => ['View supplier claims'],
                'procurement.claim.manage' => ['Raise and settle supplier claims'],
                'procurement.advance.view_any' => ['View supplier advances'],
                'procurement.advance.manage' => ['Record and adjust supplier advances'],
                'procurement.scorecard.view_any' => ['View supplier scorecards'],
                'procurement.scorecard.calculate' => ['Re-score suppliers and move their rating', true],
                'procurement.contract.view_any' => ['View rate contracts'],
                'procurement.contract.manage' => ['Agree and activate rate contracts', true],
                'procurement.import.view_any' => ['View proforma invoices, letters of credit and shipments'],
                'procurement.import.manage' => ['Maintain import documents and shipment tracking'],
            ],
            'finance' => [
                'finance.gl.view_any' => ['View GL postings and the event mapping'],
                'finance.gl.manage' => ['Point business events at accounts in the chart', true],
            ],
            'compliance' => [
                'compliance.bond.view_any' => ['View bond licences and utilisation declarations'],
                'compliance.bond.manage' => ['Maintain bond licences and declarations', true],
                'compliance.duty.assess' => ['Assess duty on goods leaving the bond', true],
            ],
            'production' => [
                'production.order.view_any' => ['View orders, styles and bills of materials'],
                'production.order.manage' => ['Maintain orders, styles, BOMs and cutting programmes'],
                'production.issue.create' => ['Issue materials to a cutting programme'],
            ],
            'requisition' => [
                'requisition.floor.view_any' => ['View every floor requisition'],
                'requisition.floor.create' => ['Raise floor requisitions'],
                'requisition.floor.issue' => ['Review, issue and close floor requisitions'],
            ],
            'vendor' => [
                'vendor.vendor.view_any' => ['View vendors'],
                'vendor.vendor.create' => ['Create vendors'],
                'vendor.vendor.update' => ['Update vendors'],
                'vendor.warranty.manage' => ['Manage warranties and claims'],
                'vendor.contract.manage' => ['Manage service contracts'],
            ],
            'technician' => [
                'technician.technician.manage' => ['Manage technicians'],
                'technician.performance.view' => ['View individual technician KPIs'],
            ],
            'admin' => [
                'admin.user.manage' => ['Manage users'],
                'admin.role.manage' => ['Manage roles and assignments'],
                'admin.team.manage' => ['Manage teams'],
                'admin.api_client.manage' => ['Manage API clients', true],
            ],
            'settings' => [
                'settings.company.manage' => ['Manage company settings'],
                'settings.factory.manage' => ['Manage factory settings'],
                'settings.calendar.manage' => ['Manage shifts, holidays and calendars'],
                'settings.numbering.manage' => ['Manage document numbering'],
            ],
            'masterdata' => [
                'masterdata.manage' => ['Manage master data'],
            ],
            'custom_field' => [
                'custom_field.definition.manage' => ['Manage custom field definitions'],
            ],
            'report' => [
                'report.report.view' => ['View reports'],
                'report.report.export' => ['Export reports and data'],
            ],
            'dashboard' => [
                'dashboard.management.view' => ['View management dashboard'],
                'dashboard.maintenance.view' => ['View maintenance dashboard'],
                'dashboard.store.view' => ['View store dashboard'],
            ],
            'approval' => [
                'approval.request.approve' => ['Approve requests'],
                'approval.request.reject' => ['Reject requests'],
            ],
            'audit' => [
                'audit.log.view' => ['View the audit log'],
            ],
            'billing' => [
                'billing.subscription.manage' => ['Manage subscription', true],
                'billing.payment.manage' => ['Record payments and refunds', true],
            ],
            'webhook' => [
                'webhook.endpoint.manage' => ['Manage webhook endpoints', true],
            ],
            'import' => [
                'import.job.create' => ['Run bulk imports'],
                'export.job.create' => ['Run bulk exports'],
            ],
        ];
    }

    public function run(): void
    {
        // Spatie's `name` is the permission code (its own uniqueness key);
        // the human-readable label lives in `description` instead — see the
        // migration note in 2026_09_03_083841_create_permission_tables.php.
        foreach (self::catalog() as $module => $permissions) {
            foreach ($permissions as $code => $definition) {
                Permission::updateOrCreate(
                    ['name' => $code, 'guard_name' => 'web'],
                    [
                        'module' => $module,
                        'description' => $definition[0],
                        'is_elevated' => $definition[1] ?? false,
                    ],
                );
            }
        }
    }

    /**
     * @return list<string>
     */
    public static function allCodes(): array
    {
        $codes = [];

        foreach (self::catalog() as $permissions) {
            foreach (array_keys($permissions) as $code) {
                $codes[] = $code;
            }
        }

        return $codes;
    }
}
