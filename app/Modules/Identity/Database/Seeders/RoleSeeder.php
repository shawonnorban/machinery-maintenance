<?php

declare(strict_types=1);

namespace App\Modules\Identity\Database\Seeders;

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use Illuminate\Database\Seeder;

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
            'breakdown.breakdown.create', 'breakdown.breakdown.acknowledge',
            'breakdown.breakdown.repair',
            'inventory.part.view_any', 'inventory.stock.view',
        ];

        $engineer = array_merge($technician, [
            'asset.asset.create', 'asset.asset.update', 'asset.status.update',
            'asset.document.manage', 'asset.transfer.create',
            'maintenance.plan.view_any', 'maintenance.plan.create',
            'maintenance.plan.update', 'maintenance.plan.activate',
            'maintenance.template.view_any', 'maintenance.template.create',
            'maintenance.template.update', 'maintenance.template.publish',
            'maintenance.schedule.reschedule',
            'meter.meter.manage',
            'work_order.work_order.create', 'work_order.work_order.update',
            'work_order.work_order.assign', 'work_order.work_order.verify',
            'work_order.cost.view',
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
        ]);

        $storekeeper = [
            'asset.asset.view_any', 'asset.asset.view',
            'inventory.part.view_any', 'inventory.stock.view',
            'inventory.stock.receive', 'inventory.stock.issue',
            'inventory.stock.return', 'inventory.reservation.manage',
            'inventory.transfer.receive',
            'work_order.work_order.view_any', 'work_order.work_order.view',
            'dashboard.store.view',
        ];

        $storeManager = array_merge($storekeeper, [
            'inventory.part.create', 'inventory.part.update',
            'inventory.adjustment.create',
            'inventory.transfer.create', 'inventory.transfer.approve',
            'inventory.transfer.dispatch',
            'vendor.vendor.view_any', 'vendor.vendor.create', 'vendor.vendor.update',
            'cost.entry.view',
            'report.report.view', 'report.report.export',
            'import.job.create', 'export.job.create',
        ]);

        $factoryManager = array_merge($maintenanceManager, [
            'asset.transfer.approve', 'asset.transfer.receive',
            'asset.asset.delete',
            'work_order.work_order.reopen',
            'inventory.adjustment.create',
            'settings.factory.manage', 'settings.calendar.manage',
            'masterdata.manage',
            'dashboard.store.view',
            'vendor.warranty.manage', 'vendor.contract.manage',
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
        $permissionIds = Permission::pluck('id', 'code');

        foreach (self::matrix() as $code => $definition) {
            $role = Role::updateOrCreate(
                ['company_id' => null, 'code' => $code],
                [
                    'name' => $definition['name'],
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
