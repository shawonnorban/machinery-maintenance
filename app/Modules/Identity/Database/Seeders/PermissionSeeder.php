<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Seeders;

use App\Modules\Identity\Models\Permission;
use Illuminate\Database\Seeder;

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
     *         module => [code => [label, isElevated]]
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
                'technician.grade.manage' => ['Manage labor rate grades', true],
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
        foreach (self::catalog() as $module => $permissions) {
            foreach ($permissions as $code => $definition) {
                Permission::updateOrCreate(
                    ['code' => $code],
                    [
                        'module' => $module,
                        'name' => $definition[0],
                        'is_elevated' => $definition[1] ?? false,
                    ]
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
