<?php

declare(strict_types=1);

use App\Modules\PreBudget\Models\PbRequisition;
use App\Modules\PreBudget\Support\OrderLink;
use App\Modules\PreBudget\Support\RequisitionSheet;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A style's quantity and price on its first row only (user, 2026-11-24):
 * the requisition rows made from one order (its parts) all carried the
 * order's Quantity (Ticket), PAC and FOB Price, so a budget added them up
 * once per part. Each order's rows after its first (lowest serial) now
 * carry none; Order Qty and FOB Value are worked out again.
 */
return new class extends Migration
{
    public function up(): void
    {
        $orders = DB::table('pb_requisitions')->whereNotNull('os_order_id')->whereNull('deleted_at')
            ->groupBy('company_id', 'os_order_id')->havingRaw('count(*) > 1')->get(['company_id', 'os_order_id']);
        $sheets = [];
        foreach ($orders as $o) {
            $sheet = $sheets[$o->company_id] ??= new RequisitionSheet($o->company_id);
            $rows = PbRequisition::withoutGlobalScopes()->where('company_id', $o->company_id)->where('os_order_id', $o->os_order_id)->whereNull('deleted_at')
                ->orderBy('serial_no')->orderBy('created_at')->get();
            foreach ($rows->slice(1) as $r) {
                $values = $r->values ?? [];
                foreach (OrderLink::QTY as $key) {
                    unset($values[$key]);
                }
                $values = $sheet->formulas->apply($values, RequisitionSheet::today());
                $values = array_filter($values, fn ($v) => ! \App\Shared\Formula\Formula::blank($v));
                DB::table('pb_requisitions')->where('id', $r->id)->update(['values' => json_encode($values, JSON_UNESCAPED_UNICODE), 'version' => $r->version + 1]);
            }
        }
    }

    public function down(): void
    {
        // The quantities are on each order's first row and in Order Summary.
    }
};
