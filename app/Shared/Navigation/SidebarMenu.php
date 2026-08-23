<?php

declare(strict_types=1);

namespace App\Shared\Navigation;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * The sidebar tree (Frontend 4.1).
 *
 * Rendered from a definition rather than hard-coded in the layout, so an item
 * the user cannot use is not rendered at all. Hiding a link is usability, not
 * security; the route still enforces its own permission (Handbook 2.6 rule 2).
 *
 * A section with no visible children disappears with them. A heading over an
 * empty list is worse than no heading.
 */
class SidebarMenu
{
    /**
     * @return list<array{type: string, label?: string, items?: list<array<string, mixed>>}>
     */
    public function build(): array
    {
        $tree = [
            [
                'label' => 'nav.dashboard',
                'icon' => 'speedometer',
                'route' => 'app.dashboard',
                'permission' => null,
            ],
            [
                'label' => 'nav.assets',
                'icon' => 'settings',
                'permission' => 'asset.asset.view_any',
                'children' => [
                    ['label' => 'nav.all_assets', 'route' => 'app.assets.index', 'permission' => 'asset.asset.view_any'],
                    ['label' => 'nav.asset_transfers', 'route' => 'app.assets.transfers', 'permission' => 'asset.transfer.create'],
                    ['label' => 'nav.print_labels', 'route' => 'app.assets.labels', 'permission' => 'asset.asset.view_any'],
                ],
            ],
            [
                'label' => 'nav.maintenance',
                'icon' => 'calendar',
                'permission' => 'maintenance.plan.view_any',
                'children' => [
                    ['label' => 'nav.plans', 'route' => 'app.maintenance.plans', 'permission' => 'maintenance.plan.view_any'],
                    ['label' => 'nav.schedule', 'route' => 'app.maintenance.schedule', 'permission' => 'maintenance.schedule.view_any'],
                    ['label' => 'nav.templates', 'route' => 'app.maintenance.templates', 'permission' => 'maintenance.template.view_any'],
                    ['label' => 'nav.meters', 'route' => 'app.meters.index', 'permission' => 'meter.reading.view_any'],
                ],
            ],
            [
                'label' => 'nav.work_orders',
                'icon' => 'list',
                'permission' => 'work_order.work_order.view_any',
                'children' => [
                    ['label' => 'nav.my_work', 'route' => 'app.my-work', 'permission' => 'work_order.work_order.view_any'],
                    ['label' => 'nav.all_work_orders', 'route' => 'app.work-orders.index', 'permission' => 'work_order.work_order.view_any'],
                    ['label' => 'nav.approvals', 'route' => 'app.approvals', 'permission' => 'approval.request.approve'],
                ],
            ],
            [
                'label' => 'nav.breakdowns',
                'icon' => 'warning',
                'permission' => 'breakdown.breakdown.view_any',
                'children' => [
                    ['label' => 'nav.active_breakdowns', 'route' => 'app.breakdowns.index', 'permission' => 'breakdown.breakdown.view_any'],
                    ['label' => 'nav.report_breakdown', 'route' => 'app.breakdowns.create', 'permission' => 'breakdown.breakdown.create'],
                ],
            ],
            [
                'label' => 'nav.inventory',
                'icon' => 'storage',
                'permission' => 'inventory.part.view_any',
                'children' => [
                    ['label' => 'nav.parts', 'route' => 'app.inventory.parts', 'permission' => 'inventory.part.view_any'],
                    ['label' => 'nav.stock', 'route' => 'app.inventory.stock', 'permission' => 'inventory.stock.view'],
                    ['label' => 'nav.issue_return', 'route' => 'app.inventory.issue', 'permission' => 'inventory.stock.issue'],
                    ['label' => 'nav.transfers', 'route' => 'app.inventory.transfers', 'permission' => 'inventory.transfer.create'],
                    ['label' => 'nav.low_stock', 'route' => 'app.inventory.low-stock', 'permission' => 'inventory.stock.view'],
                    ['label' => 'nav.part_requests', 'route' => 'app.inventory.requests', 'permission' => 'inventory.part.view_any'],
                ],
            ],
            [
                'label' => 'nav.technicians',
                'icon' => 'people',
                'permission' => 'technician.technician.manage',
                'children' => [
                    ['label' => 'nav.technicians', 'route' => 'app.technicians.index', 'permission' => 'technician.technician.manage'],
                    ['label' => 'nav.teams', 'route' => 'app.teams.index', 'permission' => 'admin.team.manage'],
                ],
            ],
            [
                'label' => 'nav.vendors',
                'icon' => 'building',
                'permission' => 'vendor.vendor.view_any',
                'children' => [
                    ['label' => 'nav.vendors', 'route' => 'app.vendors.index', 'permission' => 'vendor.vendor.view_any'],
                    ['label' => 'nav.warranties', 'route' => 'app.warranties.index', 'permission' => 'vendor.warranty.manage'],
                    ['label' => 'nav.service_contracts', 'route' => 'app.service-contracts.index', 'permission' => 'vendor.contract.manage'],
                ],
            ],
            [
                'label' => 'nav.reports',
                'icon' => 'chart',
                'route' => 'app.reports.index',
                'permission' => 'report.report.view',
            ],
            [
                'label' => 'nav.import_data',
                'icon' => 'cloud-upload',
                'route' => 'app.imports.index',
                'permission' => 'import.job.create',
            ],
            [
                'label' => 'nav.settings',
                'icon' => 'cog',
                'permission' => 'settings.company.manage',
                'children' => [
                    ['label' => 'nav.company', 'route' => 'app.settings.company', 'permission' => 'settings.company.manage'],
                    ['label' => 'nav.factories', 'route' => 'app.settings.factories', 'permission' => 'settings.factory.manage'],
                    ['label' => 'nav.locations', 'route' => 'app.settings.locations', 'permission' => 'masterdata.manage'],
                    ['label' => 'nav.calendar_shifts', 'route' => 'app.settings.calendar', 'permission' => 'settings.calendar.manage'],
                    ['label' => 'nav.master_data', 'route' => 'app.settings.master-data', 'permission' => 'masterdata.manage'],
                    ['label' => 'nav.approval_workflows', 'route' => 'app.settings.approval-workflows', 'permission' => 'settings.company.manage'],
                    ['label' => 'nav.escalations', 'route' => 'app.settings.escalations', 'permission' => 'settings.company.manage'],
                    ['label' => 'nav.users', 'route' => 'app.settings.users', 'permission' => 'admin.user.manage'],
                    ['label' => 'nav.roles', 'route' => 'app.settings.roles', 'permission' => 'admin.role.manage'],
                    ['label' => 'nav.webhooks', 'route' => 'app.webhooks.index', 'permission' => 'webhook.endpoint.manage'],
                ],
            ],
            [
                'label' => 'nav.audit_log',
                'icon' => 'shield-alt',
                'route' => 'app.audit-logs',
                'permission' => 'audit.log.view',
            ],
            [
                'label' => 'nav.billing',
                'icon' => 'credit-card',
                'route' => 'app.billing',
                'permission' => 'billing.subscription.manage',
            ],
        ];

        return $this->filter($tree);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function filter(array $items): array
    {
        $visible = [];

        foreach ($items as $item) {
            if (! $this->allowed($item['permission'] ?? null)) {
                continue;
            }

            if (isset($item['children'])) {
                $children = $this->filter($item['children']);

                // A heading over an empty list is worse than no heading.
                if ($children === []) {
                    continue;
                }

                $item['children'] = $children;
                $visible[] = $item;

                continue;
            }

            // Routes land as their modules are built. Until then the item is
            // simply not shown, rather than rendering a link that 404s.
            if (isset($item['route']) && ! Route::has($item['route'])) {
                continue;
            }

            $visible[] = $item;
        }

        return $visible;
    }

    private function allowed(?string $permission): bool
    {
        return $permission === null || Gate::allows($permission);
    }
}
