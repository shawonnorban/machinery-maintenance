<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Http\Controllers\Api;

use App\Modules\PreBudget\Http\Controllers\Api\Concerns\FindsBudgets;
use App\Modules\PreBudget\Models\PbBudget;
use App\Modules\PreBudget\Models\PbChange;
use App\Modules\PreBudget\Models\PbRequisition;
use App\Modules\PreBudget\Models\PbRequisitionDraft;
use App\Modules\PreBudget\Support\BomSheet;
use App\Shared\Formula\Formula;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Pre-Budget → Dashboard (user, 2026-11-21, laid out like Order Summary's):
 * totals over the requisitions this user reaches, the budgets by status,
 * the work still open (requisitions no budget holds, costing, stock and the
 * accessories budget), buyers and delivery months by value, and the latest
 * saves — each linking to the screen behind it.
 */
class DashboardApiController extends ApiController
{
    use FindsBudgets;

    public function __invoke(): JsonResponse
    {
        $this->allow('prebudget.view');
        // Each order's Order Qty and FOB Value once, however many parts (rows) it has.
        $reqs = \App\Modules\PreBudget\Support\OrderLink::countOnce($this->requisitions()->where('status', PbRequisition::COMPLETE)->get(['id', 'serial_no', 'os_order_id', 'entry_type', 'values']));
        $num = fn (PbRequisition $r, string $k) => Formula::num($r->values[$k] ?? null) ?? 0.0;
        $knit = $reqs->where('entry_type', 'KNIT');
        $woven = $reqs->where('entry_type', 'WOVEN');

        $budgets = $this->scoped()->whereNotIn('status', PbBudget::INACTIVE)->latest('updated_at')->limit(300)->get();
        $boms = new BomSheet;
        $accessories = $budgets->map(fn (PbBudget $b) => $boms->summary($b)['state'])->countBy();

        $me = $this->caller()->auditUserId();
        $drafts = PbRequisitionDraft::query()->when($me !== null && ! $this->caller()->can('prebudget.view_all'), fn ($q) => $q->where('user_id', $me))->count();
        $free = $this->requisitions()->where('status', PbRequisition::COMPLETE)
            ->whereNotExists(fn ($q) => $q->from('pb_budget_requisitions as br')->join('pb_budgets as b', 'b.id', '=', 'br.budget_id')
                ->whereColumn('br.requisition_id', 'pb_requisitions.id')->whereNull('b.deleted_at')->whereNotIn('b.status', PbBudget::INACTIVE))
            ->count();
        $users = DB::table('users')->pluck('name', 'id');

        return ApiResponse::ok([
            'totals' => [
                'requisitions' => $reqs->count(), 'knit' => $knit->count(), 'woven' => $woven->count(),
                'order_qty' => round($reqs->sum(fn ($r) => $num($r, 'order_qty'))), 'fob_value' => round($reqs->sum(fn ($r) => $num($r, 'fob_value')), 2),
                'req_kg' => round($knit->sum(fn ($r) => $num($r, 'total_req')), 2), 'req_yds' => round($woven->sum(fn ($r) => $num($r, 'total_req')), 2),
                'budgets' => $budgets->count(), 'drafts' => $drafts,
            ],
            'budget_status' => collect([PbBudget::DRAFT, PbBudget::PENDING, PbBudget::APPROVED, PbBudget::RETURNED])
                ->mapWithKeys(fn ($s) => [$s => $budgets->where('status', $s)->count()]),
            'pending' => [
                'free' => $free,
                'costing' => $budgets->whereNull('costing_done_at')->count(),
                'stock' => $budgets->whereNull('stock_done_at')->count(),
                'accessories' => (int) ($accessories['PENDING'] ?? 0) + (int) ($accessories['PARTIAL'] ?? 0),
            ],
            'by_buyer' => $reqs->groupBy(fn ($r) => Formula::text($r->values['buyer'] ?? ''))->map(fn ($g, $b) => [
                'buyer' => $b, 'requisitions' => $g->count(), 'qty' => round($g->sum(fn ($r) => $num($r, 'order_qty'))), 'value' => round($g->sum(fn ($r) => $num($r, 'fob_value')), 2),
            ])->sortByDesc('value')->values(),
            'by_month' => $reqs->groupBy(fn ($r) => substr((string) ($r->values['delivery_date'] ?? ''), 0, 7))->map(fn ($g, $m) => [
                'month' => $m, 'requisitions' => $g->count(), 'qty' => round($g->sum(fn ($r) => $num($r, 'order_qty'))), 'value' => round($g->sum(fn ($r) => $num($r, 'fob_value')), 2),
            ])->sortKeys()->values(),
            'recent' => PbChange::query()
                ->where(fn ($q) => $q->where(fn ($w) => $w->where('subject', 'REQUISITION')->whereIn('subject_id', $this->requisitions()->withTrashed()->select('id')))
                    ->orWhere(fn ($w) => $w->where('subject', 'BUDGET')->whereIn('subject_id', $this->scoped()->withTrashed()->select('id'))))
                ->where('kind', '!=', 'RECALC')->orderByDesc('changed_at')->orderByDesc('id')->limit(300)->get()->groupBy('batch')->take(8)->map(fn ($g) => [
                    'at' => $g->first()->changed_at?->toIso8601String(), 'kind' => $g->first()->kind, 'subject' => $g->first()->subject,
                    'by' => $users[$g->first()->changed_by] ?? null, 'rows' => $g->pluck('subject_id')->unique()->count(), 'cells' => $g->whereNotNull('column_key')->count(),
                ])->values(),
        ]);
    }

    /** The requisitions this user reaches: their buyers' and their own (All Requisition's scope). */
    private function requisitions(): Builder
    {
        $query = PbRequisition::query();
        $scope = $this->buyerScope();
        if ($scope !== null) {
            $me = $this->caller()->auditUserId();
            $query->where(fn ($q) => $q->whereIn('buyer_id', $scope)->orWhere('created_by', $me));
        }

        return $query;
    }
}
