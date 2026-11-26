<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Http\Controllers\Api;

use App\Modules\SalesShipment\Models\SsMonthClose;
use App\Modules\SalesShipment\Support\SalesBook;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sales & Shipment → Order & Delivery → Sales Order Month Close (AnnotechERP
 * 21/02): the months closed against sales papers, what in a month would stop
 * its close (undelivered orders, open challans, bills not yet approved,
 * draft challan and S/C returns, amendments not applied), the close itself — refused
 * while any stand unless an administrator closes over them, which is
 * recorded — and the reopen. A closed month takes no paper dated inside it.
 */
class MonthCloseApiController extends ApiController
{
    public function index(): JsonResponse
    {
        $this->allow('salesshipment.close.manage');
        $b = new SalesBook($this->caller()->companyId);

        return ApiResponse::ok(SsMonthClose::query()->orderByDesc('period')->get()->map(fn (SsMonthClose $m) => [
            'period' => $m->period, 'label' => CarbonImmutable::parse($m->period.'-01')->format('M/Y'), 'closed_at' => $m->closed_at->toIso8601String(), 'closed_by' => $b->user($m->closed_by),
            'orders' => $m->orders_count, 'overridden_by' => $b->user($m->overridden_by), 'remarks' => $m->remarks, 'exceptions' => $m->exceptions,
        ])->values()->all());
    }

    /** Show Exception: everything in the month that would stop its close. */
    public function exceptions(Request $request): JsonResponse
    {
        $this->allow('salesshipment.close.manage');

        return ApiResponse::ok(['period' => $p = $this->period($request), 'label' => CarbonImmutable::parse($p.'-01')->format('M/Y'), 'items' => $this->open($p)]);
    }

    public function close(Request $request): JsonResponse
    {
        $this->allow('salesshipment.close.manage');
        $p = $this->period($request);
        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:2000'], 'override' => ['boolean']]);
        if (SsMonthClose::query()->where('period', $p)->exists()) {
            throw ValidationException::withMessages(['period' => __('salesshipment.v.mc_closed', ['period' => CarbonImmutable::parse($p.'-01')->format('M/Y')])]);
        }
        if ($p > CarbonImmutable::now('Asia/Dhaka')->format('Y-m')) {
            throw ValidationException::withMessages(['period' => __('salesshipment.v.mc_future')]);
        }
        $open = $this->open($p);
        $override = ! empty($data['override']);
        if ($open && ! $override) {
            throw ValidationException::withMessages(['period' => __('salesshipment.v.mc_exceptions', ['n' => count($open)])]);
        }
        if ($open && ! $this->caller()->can('salesshipment.close.override')) {
            throw ValidationException::withMessages(['override' => __('salesshipment.v.mc_admin')]);
        }
        $by = $this->caller()->auditUserId();
        $row = SsMonthClose::query()->create([
            'period' => $p, 'remarks' => $data['remarks'] ?? null, 'closed_by' => $by, 'closed_at' => now(), 'exceptions' => $open ?: null, 'overridden_by' => $open ? $by : null,
            'orders_count' => DB::table('ss_sales_orders')->where('company_id', $this->caller()->companyId)->whereRaw("to_char(receive_date, 'YYYY-MM') = ?", [$p])->count(),
        ]);

        return ApiResponse::created(['period' => $row->period, 'orders' => $row->orders_count, 'overridden' => (bool) $open]);
    }

    public function reopen(string $period): JsonResponse
    {
        $this->allow('salesshipment.close.manage');
        SsMonthClose::query()->where('period', $period)->firstOrFail()->delete();

        return ApiResponse::ok(['reopened' => $period]);
    }

    private function period(Request $request): string
    {
        $request->validate(['period' => ['required', 'string', 'max:10']], ['period.required' => __('salesshipment.v.mc_period')]);
        $p = SalesBook::period((string) $request->input('period'));
        if (! $p) {
            throw ValidationException::withMessages(['period' => __('salesshipment.v.mc_period')]);
        }

        return $p;
    }

    /**
     * What in the month would stop its close.
     *
     * @return list<array{kind: string, no: string, date: string, detail: string}>
     */
    private function open(string $p): array
    {
        $c = $this->caller()->companyId;
        $b = new SalesBook($c);
        $in = fn (string $col) => [DB::raw("to_char({$col}, 'YYYY-MM')"), '=', $p];
        $out = [];
        $orders = DB::table('ss_sales_orders')->where('company_id', $c)->where('approval_status', 'CONFIRMED')->where('state', 'OPEN')->where(...$in('delivery_target_date'))->get(['id', 'so_no', 'delivery_target_date']);
        $fig = $b->sizeFigures($orders->pluck('id')->all());
        $sizes = DB::table('ss_sales_order_sizes')->whereIn('order_id', $orders->pluck('id'))->get(['id', 'order_id', 'qty'])->groupBy('order_id');
        foreach ($orders as $o) {
            $bal = (float) $sizes->get($o->id, collect())->sum(fn ($s) => $s->qty - ($fig[$s->id]['net'] ?? 0));
            if ($bal > 0.00005) {
                $out[] = ['kind' => 'UNDELIVERED', 'no' => $o->so_no, 'date' => $o->delivery_target_date, 'detail' => rtrim(rtrim(number_format($bal, 3, '.', ''), '0'), '.')];
            }
        }
        foreach (DB::table('ss_shipments')->where('company_id', $c)->whereIn('stage', ['PLANNING', 'BOOKED', 'DOCUMENTATION', 'READY'])->where(...$in('shipment_date'))->orderBy('shipment_no')->get(['shipment_no', 'shipment_date', 'stage']) as $r) {
            $out[] = ['kind' => 'SHIPMENT_PENDING', 'no' => $r->shipment_no, 'date' => $r->shipment_date, 'detail' => $r->stage];
        }
        foreach (DB::table('ss_challans')->where('company_id', $c)->whereIn('state', ['DRAFT', 'SUBMITTED'])->where(...$in('chalan_date'))->orderBy('chalan_no')->get(['chalan_no', 'chalan_date', 'state']) as $r) {
            $out[] = ['kind' => 'OPEN_CHALLAN', 'no' => $r->chalan_no, 'date' => $r->chalan_date, 'detail' => $r->state];
        }
        foreach (DB::table('ss_bills')->where('company_id', $c)->whereIn('state', ['DRAFT', 'WAITING_FOR_APPROVAL', 'VERIFIED', 'REJECTED'])->where(...$in('bill_date'))->orderBy('bill_no')->get(['bill_no', 'bill_date', 'state']) as $r) {
            $out[] = ['kind' => 'BILL_NOT_APPROVED', 'no' => $r->bill_no, 'date' => $r->bill_date, 'detail' => $r->state];
        }
        foreach (DB::table('ss_challan_returns')->where('company_id', $c)->where('state', 'DRAFT')->where(...$in('return_date'))->orderBy('return_no')->get(['return_no', 'return_date']) as $r) {
            $out[] = ['kind' => 'DRAFT_RETURN', 'no' => $r->return_no, 'date' => $r->return_date, 'detail' => 'DRAFT'];
        }
        foreach (DB::table('ss_sc_returns')->where('company_id', $c)->where('state', 'DRAFT')->where(...$in('return_date'))->orderBy('return_no')->get(['return_no', 'return_date']) as $r) {
            $out[] = ['kind' => 'DRAFT_SC_RETURN', 'no' => $r->return_no, 'date' => $r->return_date, 'detail' => 'DRAFT'];
        }
        foreach (DB::table('ss_amendments')->where('company_id', $c)->whereIn('state', ['DRAFT', 'SUBMITTED', 'APPROVED'])->where(...$in('amend_date'))->orderBy('amend_no')->get(['amend_no', 'amend_date', 'state']) as $r) {
            $out[] = ['kind' => 'AMENDMENT_NOT_APPLIED', 'no' => $r->amend_no, 'date' => $r->amend_date, 'detail' => $r->state];
        }

        return $out;
    }
}
