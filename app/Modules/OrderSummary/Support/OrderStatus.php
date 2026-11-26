<?php

declare(strict_types=1);

namespace App\Modules\OrderSummary\Support;

use App\Modules\OrderSummary\Models\OsOrder;
use Illuminate\Support\Facades\DB;

/**
 * Order Summary's Budget Status column (user, 2026-11-24): where an order
 * stands in Pre-Budget, worked out each time from its requisition and the
 * budget that holds it — never stored, so it cannot fall behind. Blank (no
 * requisition yet), Requisition (completed, in no budget), then the newest
 * budget's state: Budget Draft, Budget Submitted (waiting for approval),
 * Budget Returned, Budget Approved, Budget Cancelled (its order changed or
 * dropped — the revision made in its place, when it holds the order, is the
 * newer one). A budget replaced by its revision (REVISED) gives way to it.
 */
final class OrderStatus
{
    public const KEY = 'budget_status';

    public const LABELS = ['DRAFT' => 'Budget Draft', 'PENDING' => 'Budget Submitted', 'RETURNED' => 'Budget Returned', 'APPROVED' => 'Budget Approved', 'CANCELLED' => 'Budget Cancelled'];

    /** The status as SQL text for the row of os_orders in scope (header filters, sort, the list's own select). */
    public static function sql(): string
    {
        $case = 'case b.status '.implode(' ', array_map(fn ($s, $l) => "when '{$s}' then '{$l}'", array_keys(self::LABELS), self::LABELS)).' end';

        return "coalesce((select {$case} from pb_requisitions r join pb_budget_requisitions br on br.requisition_id = r.id"
            ." join pb_budgets b on b.id = br.budget_id and b.deleted_at is null and b.status <> 'REVISED'"
            .' where r.os_order_id = os_orders.id and r.deleted_at is null order by b.created_at desc, b.revision_no desc limit 1),'
            ." (select 'Requisition' where exists (select 1 from pb_requisitions r where r.os_order_id = os_orders.id and r.deleted_at is null and r.status = 'COMPLETE')))";
    }

    /** One order's status (a row read without the list's select: a save's answer). */
    public static function of(OsOrder $o): ?string
    {
        if (array_key_exists(self::KEY, $o->getAttributes())) {
            return $o->getAttribute(self::KEY);
        }

        return DB::table('os_orders')->where('id', $o->id)->selectRaw(self::sql().' as s')->value('s');
    }
}
