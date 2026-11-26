<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\Knitting\Models\KnitScReceipt;
use App\Modules\Knitting\Models\KnitScReceiptFabric;
use App\Modules\Knitting\Models\KnitScYarnChallan;
use App\Modules\Knitting\Models\KnitScYarnChallanLine;
use App\Modules\Knitting\Support\KnitProduction;
use App\Modules\Knitting\Support\YarnProcurement;
use App\Modules\Knitting\Support\YarnStore;
use App\Modules\Merchandise\Models\Order;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Knit Production → Yarn Store (AnnotechERP 15/03) → In-House Sub-Contract
 * Yarn Receipt, as Brickland works it: yarn a sub-contract party sends in
 * for the mill to knit, always against one of its confirmed programme
 * receipts (SCR-…). The challan is the party's own number, or the store
 * issues one (SYC-…). Saved as a draft; Issue hands it to the store; the
 * store accepts it — which is what takes the cones onto the month's ledger,
 * one stock line per lot written on the cartons — or rejects it.
 */
class KnitScYarnChallanApiController extends ApiController
{
    public function index(): JsonResponse
    {
        $this->allow('knitting.ystore.view');
        $rows = KnitScYarnChallan::query()->orderByDesc('challan_date')->orderByDesc('challan_no')->limit(500)->get();
        $qty = KnitScYarnChallanLine::query()->whereIn('challan_id', $rows->pluck('id'))->groupBy('challan_id')->selectRaw('challan_id, sum(qty) as q')->pluck('q', 'challan_id');
        $receipts = KnitScReceipt::query()->whereIn('id', $rows->pluck('receipt_id')->unique())->get()->keyBy('id');
        $orders = $this->ordersOf($receipts->keys()->all());
        $parties = DB::table('mp_suppliers')->whereIn('id', $receipts->pluck('party_id')->unique())->pluck('name', 'id');
        $stores = DB::table('stores')->whereIn('id', $rows->pluck('store_id')->unique())->pluck('name', 'id');

        return ApiResponse::ok($rows->map(function (KnitScYarnChallan $c) use ($qty, $receipts, $orders, $parties, $stores) {
            $r = $receipts[$c->receipt_id];

            return [
                'challan_no' => $c->challan_no, 'challan_date' => $c->challan_date->toDateString(), 'program_no' => $r->program_no,
                'order_no' => $orders[$r->id]['order_no'] ?? null, 'style_no' => $orders[$r->id]['style_no'] ?? null, 'party_id' => $r->party_id,
                'supplier' => $parties[$r->party_id] ?? null, 'store' => $stores[$c->store_id] ?? null, 'qty' => round((float) ($qty[$c->id] ?? 0), 4),
                'remarks' => $c->remarks, 'status' => $c->status,
            ];
        })->values()->all());
    }

    public function show(string $no): JsonResponse
    {
        $this->allow('knitting.ystore.view');

        return ApiResponse::ok($this->present($this->challan($no)));
    }

    public function save(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.ystore.manage');
        $row = $no ? $this->challan($no) : null;
        if ($row && $row->status !== 'DRAFT') {
            throw ValidationException::withMessages(['status' => $this->cannot($row, 'save')]);
        }
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'program_no' => ['required', 'string'], 'challan_no' => ['nullable', 'string', 'max:40'], 'challan_date' => ['required', 'date'],
            'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $company)], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:100'],
            'lines.*.composition_id' => ['nullable', Rule::exists('fiber_compositions', 'id')->where('company_id', $company)],
            'lines.*.yarn_count_id' => ['nullable', Rule::exists('knit_yarn_counts', 'id')->where('company_id', $company)],
            'lines.*.colour_group' => ['nullable', Rule::in(YarnProcurement::COLOURS)], 'lines.*.brand' => ['nullable', 'string', 'max:80'],
            'lines.*.lot_no' => ['required', 'string', 'max:40'], 'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'lines.*.uom' => ['required', 'string', 'max:16', UomCodes::rule($company)], 'lines.*.cones' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'program_no.required' => __('knitting.yd.v.sc_program'), 'challan_date.required' => __('knitting.yd.v.challan_date'), 'store_id.required' => __('knitting.yd.v.store'),
            'lines.*.lot_no.required' => __('knitting.yd.v.lot'), 'lines.*.qty.required' => __('knitting.yd.v.qty'), 'lines.*.qty.gt' => __('knitting.yd.v.qty'),
            'lines.*.uom.required' => __('knitting.yd.v.uom'),
        ]);
        $receipt = KnitScReceipt::query()->where('program_no', $data['program_no'])->first()
            ?? throw ValidationException::withMessages(['program_no' => __('knitting.yd.v.sc_program')]);
        if (! in_array($receipt->status, ['CONFIRMED', 'IN_PRODUCTION'], true)) {
            throw ValidationException::withMessages(['program_no' => __('knitting.yd.v.sc_program_open', ['no' => $receipt->program_no, 'status' => __("knitting.sc.receipt_status.{$receipt->status}")])]);
        }
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('knitting.yd.v.no_lines')]);
        }
        foreach ($lines as $i => $l) {
            if (empty($l['composition_id']) && empty($l['yarn_count_id'])) {
                throw ValidationException::withMessages(["lines.{$i}.composition_id" => __('knitting.yd.v.rp_yarn', ['n' => $i + 1])]);
            }
        }
        $challanNo = trim((string) ($data['challan_no'] ?? '')) ?: ($row?->challan_no ?? null);
        if ($challanNo && KnitScYarnChallan::query()->where('challan_no', $challanNo)->when($row, fn ($q) => $q->whereKeyNot($row->id))->exists()) {
            throw ValidationException::withMessages(['challan_no' => __('knitting.yd.v.challan_taken', ['no' => $challanNo])]);
        }
        $row = DB::transaction(function () use ($row, $data, $lines, $receipt, $challanNo): KnitScYarnChallan {
            $head = ['receipt_id' => $receipt->id, 'challan_date' => $data['challan_date'], 'store_id' => $data['store_id'], 'remarks' => ($data['remarks'] ?? '') ?: null,
                'challan_no' => $challanNo ?? KnitProduction::nextNumber('SYC', 'knit_sc_yarn_challans', 'challan_no', $data['challan_date'])];
            $row ??= KnitScYarnChallan::query()->create($head + ['created_by' => $this->caller()->auditUserId()]);
            $row->update($head);
            KnitScYarnChallanLine::query()->where('challan_id', $row->id)->delete();
            foreach ($lines as $i => $l) {
                KnitScYarnChallanLine::query()->create(['company_id' => $row->company_id, 'challan_id' => $row->id, 'sort_order' => $i + 1, 'qty' => (float) $l['qty'],
                    'uom' => UomCodes::normalize($l['uom']), 'lot_no' => mb_strtoupper(trim($l['lot_no']))]
                    + collect($l)->only(['composition_id', 'yarn_count_id', 'colour_group', 'brand', 'cones', 'remarks'])->map(fn ($v) => $v === '' ? null : $v)->all());
            }

            return $row;
        });

        return $no ? ApiResponse::ok($this->present($row->refresh())) : ApiResponse::created($this->present($row->refresh()));
    }

    public function transition(string $no, string $action): JsonResponse
    {
        $this->allow('knitting.ystore.manage');
        $row = $this->challan($no);
        [$from, $to] = match ($action) {
            'issue' => [['DRAFT'], 'ISSUED'],
            'accept' => [['ISSUED'], 'ACCEPTED'],
            'reject' => [['ISSUED'], 'REJECTED'],
            'cancel' => [['DRAFT', 'ISSUED'], 'CANCELLED'],
            default => abort(404),
        };
        if (! in_array($row->status, $from, true)) {
            throw ValidationException::withMessages(['status' => $this->cannot($row, $action)]);
        }
        DB::transaction(function () use ($row, $action, $to): void {
            if ($action === 'accept') {
                $date = $row->challan_date->toDateString();
                YarnStore::assertOpen($date, 'challan_date');
                $receipt = KnitScReceipt::query()->findOrFail($row->receipt_id);
                $orderId = $this->ordersOf([$receipt->id])[$receipt->id]['order_id'] ?? null;
                $entries = [];
                foreach (KnitScYarnChallanLine::query()->where('challan_id', $row->id)->orderBy('sort_order')->get() as $l) {
                    $lot = YarnStore::lotFor([
                        'store_id' => $row->store_id, 'composition_id' => $l->composition_id, 'yarn_count_id' => $l->yarn_count_id, 'colour' => $l->colour_group,
                        'brand' => $l->brand, 'lot_no' => $l->lot_no, 'supplier_id' => $receipt->party_id, 'order_id' => $orderId, 'uom' => $l->uom, 'source' => 'SC_PARTY',
                    ], $date);
                    $l->update(['lot_id' => $lot->id]);
                    $entries[] = ['lot_id' => $lot->id, 'qty' => (float) $l->qty, 'doc_line_id' => $l->id];
                }
                YarnStore::post('SC_YARN_RECEIVE', $row->challan_no, $date, $entries);
            }
            $row->update(['status' => $to]);
        });

        return ApiResponse::ok($this->present($row->refresh()));
    }

    /* ---------------- helpers ---------------- */

    private function challan(string $no): KnitScYarnChallan
    {
        return KnitScYarnChallan::query()->where('challan_no', $no)->firstOrFail();
    }

    private function cannot(KnitScYarnChallan $row, string $action): string
    {
        return __('knitting.yd.v.cannot', ['no' => $row->challan_no, 'status' => __("knitting.yd.challan_status.{$row->status}"), 'action' => __("knitting.yd.action.{$action}")]);
    }

    /**
     * A programme receipt's order, from its first fabric serial that names one.
     *
     * @param  list<string>  $receiptIds
     * @return array<string, array{order_id: string, order_no: ?string, style_no: ?string}>
     */
    private function ordersOf(array $receiptIds): array
    {
        $of = KnitScReceiptFabric::query()->whereIn('receipt_id', $receiptIds)->whereNotNull('order_id')->orderBy('sl')->get(['receipt_id', 'order_id'])->unique('receipt_id')->pluck('order_id', 'receipt_id');
        $orders = Order::query()->whereIn('id', $of->values()->unique())->get(['id', 'order_no', 'style_no', 'work_style_no'])->keyBy('id');

        return $of->map(fn ($id) => ['order_id' => $id, 'order_no' => $orders[$id]->order_no ?? null, 'style_no' => isset($orders[$id]) ? ($orders[$id]->work_style_no ?: $orders[$id]->style_no) : null])->all();
    }

    /** @return array<string, mixed> */
    private function present(KnitScYarnChallan $c): array
    {
        $names = YarnProcurement::names();
        $r = KnitScReceipt::query()->findOrFail($c->receipt_id);
        $order = $this->ordersOf([$r->id])[$r->id] ?? null;
        $party = DB::table('mp_suppliers')->where('id', $r->party_id)->first(['name', 'address']);

        return [
            'id' => $c->id, 'challan_no' => $c->challan_no, 'challan_date' => $c->challan_date->toDateString(), 'program_no' => $r->program_no,
            'order_no' => $order['order_no'] ?? null, 'style_no' => $order['style_no'] ?? null, 'party_id' => $r->party_id, 'party' => $party?->name,
            'party_address' => $party?->address, 'store_id' => $c->store_id, 'store' => DB::table('stores')->where('id', $c->store_id)->value('name'),
            'company' => DB::table('companies')->where('id', $c->company_id)->value('name'), 'remarks' => $c->remarks, 'status' => $c->status,
            'lines' => KnitScYarnChallanLine::query()->where('challan_id', $c->id)->orderBy('sort_order')->get()->map(fn (KnitScYarnChallanLine $l) => collect($l->only([
                'composition_id', 'yarn_count_id', 'colour_group', 'brand', 'lot_no', 'qty', 'uom', 'cones', 'remarks',
            ]))->all() + ['yarn' => trim(($names['compositions'][$l->composition_id] ?? '').' '.($names['counts'][$l->yarn_count_id] ?? '')) ?: null])->values()->all(),
        ];
    }
}
