<?php

declare(strict_types=1);

namespace App\Modules\Approval\Support;

use App\Modules\Approval\Models\ApprovalRequestType;

/**
 * The request types a company starts with (Approval Center → Request Types &
 * Routing, AnnotechERP 04/03), as Brickland ships them. Created the first time
 * a company opens the screen or the request form, so a new company is never
 * left with an empty request form; after that they are the company's to edit.
 */
final class RequestTypeDefaults
{
    /** code => [name, module, priority, due days] */
    public const TYPES = [
        'FABRIC-BOOKING' => ['Fabric Booking', 'Material Planning', 'HIGH', 1],
        'ACCESSORIES-BOOKING' => ['Accessories Booking', 'Merchandising', 'HIGH', 2],
        'PURCHASE-REQUISITION' => ['Purchase Requisition', 'Purchase', 'MEDIUM', 2],
        'PURCHASE-ORDER' => ['Purchase Order', 'Purchase', 'MEDIUM', 2],
        'SAMPLE-APPROVAL' => ['Sample Approval', 'Sample', 'MEDIUM', 3],
        'COSTING-APPROVAL' => ['Costing Approval', 'Costing', 'HIGH', 2],
        'ORDER-CONFIRMATION' => ['Order Confirmation', 'Order Management', 'HIGH', 1],
        'BUDGET-APPROVAL' => ['Budget Approval', 'Accounting', 'MEDIUM', 3],
        'LC-APPROVAL' => ['LC Approval', 'Commercial', 'HIGH', 2],
        'PAYMENT-APPROVAL' => ['Payment Approval', 'Accounting', 'HIGH', 1],
        'PRODUCTION-APPROVAL' => ['Production Approval', 'Production', 'MEDIUM', 1],
        'SHIPMENT-APPROVAL' => ['Shipment Approval', 'Shipment', 'HIGH', 1],
        'MATERIAL-ISSUE' => ['Material Issue Approval', 'Inventory', 'MEDIUM', 1],
        'GENERAL' => ['General Request', 'General', 'MEDIUM', 2],
    ];

    /** Creates the defaults for the current company if it has no types at all. */
    public static function ensure(string $companyId): void
    {
        if (ApprovalRequestType::query()->exists()) {
            return;
        }
        $order = 0;
        foreach (self::TYPES as $code => [$name, $module, $priority, $days]) {
            $order += 10;
            ApprovalRequestType::query()->create([
                'company_id' => $companyId, 'code' => $code, 'name' => $name, 'module' => $module,
                'default_priority' => $priority, 'due_days' => $days, 'sort_order' => $order, 'active' => true,
            ]);
        }
    }
}
