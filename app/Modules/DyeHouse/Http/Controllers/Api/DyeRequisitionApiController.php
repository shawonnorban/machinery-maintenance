<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Http\Controllers\Api;

use App\Modules\DyeHouse\Models\DyeBatch;
use App\Modules\DyeHouse\Models\DyeCheckRollReturn;
use App\Modules\DyeHouse\Models\DyeChemRequisition;
use App\Modules\DyeHouse\Models\DyeChemRequisitionLine;
use App\Modules\DyeHouse\Support\ChemStore;
use App\Modules\DyeHouse\Support\DyeBatches;
use App\Modules\Inventory\Support\UomCodes;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Dye House → Requisition (AnnotechERP 17/05): dye and chemical requisitions
 * on a chemical sub-store — one paper told apart by what it is raised for
 * (Batch Dye Chemical Indent, Machine Wash and Finishing Process Chemical
 * Indent, Re-Process Update Entry, and the Print Chemical Indent desk that
 * raises any of them). The header is the document and the lines what it asks
 * for; the requisitioned total is the lines added up. A re-process is raised
 * waiting on the approval desk, and the store cannot issue against it until
 * it says yes. 10-Issue releases every line in full, off the chemical
 * store's stock. A requisition is addressed by its number (IR-DC-YYYYMM####).
 */
class DyeRequisitionApiController extends ApiController
{
    /** The store's own wording for each kind, printed verbatim on the register. */
    public const REQ_TYPES = [
        'BATCH_PROGRAM' => 'Store Requisition for Batch Program', 'MC_WASH' => 'Store Requisition for M/C Wash',
        'FINISHING' => 'Store Requisition for Finishing Process', 'REPROCESS' => 'Store Requisition for Re-Process',
    ];

    public function index(Request $request): JsonResponse
    {
        $this->allow('dyehouse.requisition.view');
        $f = $request->validate(['for' => ['nullable', Rule::in(DyeChemRequisition::KINDS)]]);
        $rows = DyeChemRequisition::query()->when($f['for'] ?? null, fn ($q, $k) => $q->where('req_for', $k))->orderByDesc('req_date')->orderByDesc('req_no')->get();

        return ApiResponse::ok($this->rows($rows));
    }

    public function show(string $no): JsonResponse
    {
        $this->allow('dyehouse.requisition.view');

        return ApiResponse::ok($this->present($this->requisition($no)));
    }

    /** A requisition and its lines together: new, or rewritten while nothing has been issued against it. */
    public function save(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('dyehouse.requisition.manage');
        $row = $no ? $this->requisition($no) : null;
        if ($row && ($row->status !== 'WAITING' || $row->approval === 'REJECTED')) {
            throw ValidationException::withMessages(['req_no' => __('dyehouse.req.v.closed', ['no' => $row->req_no])]);
        }
        $c = DyeBatches::company();
        $data = $request->validate([
            'req_for' => [$row ? 'prohibited' : 'required', Rule::in(DyeChemRequisition::KINDS)],
            'req_date' => ['required', 'date'],
            'req_type' => ['required', 'string', 'max:120'],
            'process_type' => ['nullable', 'required_if:req_for,REPROCESS', Rule::in(DyeChemRequisition::PROCESS_TYPES)],
            'party_id' => ['nullable', Rule::in([$c])],
            'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $c)],
            'batch_id' => ['nullable', Rule::exists('dye_batches', 'id')->where('company_id', $c)],
            'machine_id' => ['nullable', Rule::exists('dye_machines', 'id')->where('company_id', $c)],
            'colour_id' => ['nullable', Rule::exists('colours', 'id')->where('company_id', $c)],
            'buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $c)],
            'order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $c)],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.item_id' => ['required', Rule::exists('items', 'id')->where('company_id', $c)],
            'lines.*.required_qty' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'lines.*.uom' => ['nullable', 'string', 'max:16', UomCodes::rule($c)],
            'lines.*.remarks' => ['nullable', 'string', 'max:255'],
        ], [
            'req_for.required' => __('dyehouse.req.v.req_for'), 'req_date.required' => __('dyehouse.req.v.req_date'), 'req_type.required' => __('dyehouse.req.v.req_type'),
            'process_type.required_if' => __('dyehouse.req.v.process_type'), 'store_id.required' => __('dyehouse.req.v.store'),
            'lines.required' => __('dyehouse.req.v.lines'), 'lines.min' => __('dyehouse.req.v.lines'),
            'lines.*.item_id.required' => __('dyehouse.req.v.line_item'), 'lines.*.required_qty.required' => __('dyehouse.req.v.line_qty'), 'lines.*.required_qty.gt' => __('dyehouse.req.v.line_qty'),
        ]);
        ksort($data['lines']);
        $kind = $row?->req_for ?? $data['req_for'];
        $head = ['req_date' => $data['req_date'], 'req_type' => trim($data['req_type']), 'process_type' => $kind === 'REPROCESS' ? ($data['process_type'] ?? null) : null,
            'party_id' => $data['party_id'] ?? null, 'store_id' => $data['store_id'], 'batch_id' => $data['batch_id'] ?? null, 'dye_machine_id' => $data['machine_id'] ?? null,
            'colour_id' => $data['colour_id'] ?? null, 'buyer_id' => $data['buyer_id'] ?? null, 'order_id' => $data['order_id'] ?? null, 'remarks' => $data['remarks'] ?? null];
        // A batch names its shade, buyer and order when they are not given.
        if ($head['batch_id']) {
            $b = DyeBatch::query()->findOrFail($head['batch_id']);
            $head['colour_id'] ??= $b->colour_id;
            $head['buyer_id'] ??= $b->buyer_id;
            $head['order_id'] ??= $b->order_id;
            $head['dye_machine_id'] ??= $b->dye_machine_id;
        }
        $row = DB::transaction(function () use ($row, $head, $kind, $data): DyeChemRequisition {
            if ($row) {
                $row->update($head);
                DyeChemRequisitionLine::query()->where('requisition_id', $row->id)->delete();
            } else {
                $row = DyeChemRequisition::query()->create($head + [
                    'req_no' => DyeBatches::nextNumber('IR-DC-', 'dye_chem_requisitions', 'req_no', $data['req_date'], 'Ym'), 'req_for' => $kind,
                    'approval' => $kind === 'REPROCESS' ? 'PENDING' : 'NOT_REQUIRED', 'status' => 'WAITING', 'created_by' => $this->caller()->auditUserId(),
                ]);
            }
            foreach (array_values($data['lines']) as $i => $l) {
                DyeChemRequisitionLine::query()->create(['requisition_id' => $row->id, 'item_id' => $l['item_id'], 'uom' => UomCodes::normalize($l['uom'] ?? null),
                    'required_qty' => $l['required_qty'], 'remarks' => ($l['remarks'] ?? '') ?: null, 'sort_order' => ($i + 1) * 10]);
            }

            return $row;
        });

        return ApiResponse::ok($this->present($row->refresh()), [], $no ? 200 : 201);
    }

    public function destroy(string $no): JsonResponse
    {
        $this->allow('dyehouse.requisition.manage');
        $row = $this->requisition($no);
        if ($row->status !== 'WAITING' || (float) DyeChemRequisitionLine::query()->where('requisition_id', $row->id)->sum('issued_qty') > 0) {
            throw ValidationException::withMessages(['req_no' => __('dyehouse.req.v.issued_stays', ['no' => $row->req_no])]);
        }
        $row->delete();

        return ApiResponse::ok(['deleted' => $no]);
    }

    /** The approval desk's say on a re-process: approved, it may be issued; rejected, it is cancelled. */
    public function decide(string $no, string $decision): JsonResponse
    {
        $this->allow('dyehouse.requisition.approve');
        abort_unless(in_array($decision, ['approve', 'reject'], true), 404);
        $row = $this->requisition($no);
        if ($row->approval !== 'PENDING') {
            throw ValidationException::withMessages(['approval' => __('dyehouse.req.v.not_pending', ['no' => $row->req_no])]);
        }
        $row->update(['approval' => $decision === 'approve' ? 'APPROVED' : 'REJECTED', 'status' => $decision === 'approve' ? $row->status : 'CANCELLED',
            'decided_by' => $this->caller()->auditUserId(), 'decided_at' => now()]);

        return ApiResponse::ok($this->present($row->refresh()));
    }

    /**
     * 10-Issue: the store releases the full requisitioned quantity on every
     * line, off the chemical store's stock (17/06) — refused, naming what is
     * there, when the sub-store holds less. Once only, and a re-process only
     * once approved.
     */
    public function issue(string $no): JsonResponse
    {
        $this->allowEither('dyehouse.requisition.issue', 'dyehouse.requisition.manage');
        $row = $this->requisition($no);
        if (in_array($row->approval, ['PENDING', 'REJECTED'], true)) {
            throw ValidationException::withMessages(['approval' => __('dyehouse.req.v.awaiting_approval', ['no' => $row->req_no])]);
        }
        if (in_array($row->status, ['ISSUED', 'CANCELLED'], true)) {
            throw ValidationException::withMessages(['status' => __('dyehouse.req.v.already_issued', ['no' => $row->req_no])]);
        }
        $by = $this->caller()->auditUserId();
        $date = now('Asia/Dhaka')->toDateString();
        ChemStore::assertOpen($date, 'status');
        DB::transaction(function () use ($row, $by, $date): void {
            // The chemical store's stock (17/06) gives each line from the item's lots, the soonest to expire first.
            foreach (DyeChemRequisitionLine::query()->where('requisition_id', $row->id)->orderBy('sort_order')->get() as $l) {
                ChemStore::take($row->store_id, $l->item_id, null, (float) $l->required_qty - (float) $l->issued_qty, 'REQUISITION', $row->req_no, $l->id, $date, $by, 'status');
            }
            DyeChemRequisitionLine::query()->where('requisition_id', $row->id)->update(['issued_qty' => DB::raw('required_qty')]);
            $row->update(['status' => 'ISSUED', 'issued_by' => $by, 'issued_at' => now()]);
        });

        return ApiResponse::ok($this->present($row->refresh()));
    }

    /** Dye Batch Re-Process's Back: the returned roll is sent back for re-processing, and its return is no longer edited. */
    public function sendBack(string $no): JsonResponse
    {
        $this->allow('dyehouse.requisition.manage');
        $row = DyeCheckRollReturn::query()->where('return_no', $no)->firstOrFail();
        if ($row->sent_back_at) {
            throw ValidationException::withMessages(['return_no' => __('dyehouse.req.v.sent_back', ['no' => $row->return_no])]);
        }
        $row->update(['sent_back_at' => now()]);

        return ApiResponse::ok(['return_no' => $row->return_no, 'sent_back' => true]);
    }

    private function requisition(string $no): DyeChemRequisition
    {
        return DyeChemRequisition::query()->where('req_no', $no)->firstOrFail();
    }

    /**
     * The registers' rows, with what was asked, issued and is left.
     *
     * @param  Collection<int, DyeChemRequisition>  $rows
     * @return list<array<string, mixed>>
     */
    private function rows(Collection $rows): array
    {
        $n = DyeBatches::names();
        $sums = DyeChemRequisitionLine::query()->whereIn('requisition_id', $rows->pluck('id'))->groupBy('requisition_id')
            ->selectRaw('requisition_id, sum(required_qty) as req, sum(issued_qty) as iss, count(*) as items')->get()->keyBy('requisition_id');
        $batches = DyeBatch::query()->whereIn('id', $rows->pluck('batch_id')->filter())->get(['id', 'batch_no', 'lot_no', 'card_qty'])->keyBy('id');

        return $rows->map(function (DyeChemRequisition $r) use ($n, $sums, $batches) {
            $order = $r->order_id ? ($n['orders'][$r->order_id] ?? null) : null;
            $batch = $batches[$r->batch_id] ?? null;
            $req = round((float) ($sums[$r->id]->req ?? 0), 3);
            $iss = round((float) ($sums[$r->id]->iss ?? 0), 3);

            return [
                'id' => $r->id, 'req_no' => $r->req_no, 'req_date' => $r->req_date->toDateString(), 'req_for' => $r->req_for, 'req_type' => $r->req_type, 'process_type' => $r->process_type,
                'company' => $n['companies'][$r->party_id ?? DyeBatches::company()] ?? null, 'party_id' => $r->party_id, 'store_id' => $r->store_id, 'store' => $n['stores'][$r->store_id] ?? null,
                'batch_id' => $r->batch_id, 'batch_no' => $batch?->batch_no, 'machine_id' => $r->dye_machine_id, 'machine' => $n['machines'][$r->dye_machine_id] ?? null,
                'colour_id' => $r->colour_id, 'colour' => $n['colours'][$r->colour_id] ?? null, 'buyer_id' => $r->buyer_id, 'buyer' => $n['buyers'][$r->buyer_id] ?? null, 'order_id' => $r->order_id,
                'order_info' => $order ? trim(($n['buyers'][$r->buyer_id] ?? '').' · '.($order->work_style_no ?: $order->style_no).' · '.$order->order_no, ' ·') : ($n['buyers'][$r->buyer_id] ?? null),
                'batch_info' => $batch ? trim($batch->batch_no.' · '.($batch->lot_no ?? '').' · '.(float) $batch->card_qty.' kg', ' ·') : null,
                'remarks' => $r->remarks, 'approval' => $r->approval, 'status' => $r->status,
                'required_qty' => $req, 'issued_qty' => $iss, 'balance_qty' => round($req - $iss, 3), 'items' => (int) ($sums[$r->id]->items ?? 0),
            ];
        })->values()->all();
    }

    /** @return array<string, mixed> */
    private function present(DyeChemRequisition $r): array
    {
        $items = DB::table('items')->whereIn('id', DyeChemRequisitionLine::query()->where('requisition_id', $r->id)->pluck('item_id'))->get(['id', 'item_code', 'name'])->keyBy('id');

        return $this->rows(collect([$r]))[0] + [
            'lines' => DyeChemRequisitionLine::query()->where('requisition_id', $r->id)->orderBy('sort_order')->get()->map(fn (DyeChemRequisitionLine $l) => [
                'item_id' => $l->item_id, 'item' => isset($items[$l->item_id]) ? "{$items[$l->item_id]->name} ({$items[$l->item_id]->item_code})" : null, 'uom' => $l->uom,
                'required_qty' => (float) $l->required_qty, 'issued_qty' => (float) $l->issued_qty, 'remarks' => $l->remarks,
            ])->values()->all(),
        ];
    }
}
