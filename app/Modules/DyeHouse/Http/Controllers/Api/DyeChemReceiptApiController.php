<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Http\Controllers\Api;

use App\Modules\DyeHouse\Models\DyeChemReceipt;
use App\Modules\DyeHouse\Models\DyeChemReceiptLine;
use App\Modules\DyeHouse\Models\DyeChemReturn;
use App\Modules\DyeHouse\Models\DyeChemReturnLine;
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
 * Dye House → Chemical Store (AnnotechERP 17/06), stock coming in and going
 * back: Daily Dye & Chemical Receipt (MRR-YYYYMM####), Dye Chemical Loan
 * Receipt (MRR-DC-…) and Chemical Loan Return from Party (RTN-LP-…) — one
 * paper told apart by its kind, the challan on the header and the lots it
 * brings underneath, counted into stock only once the store finalises it on
 * the register — and the Receipt Return (RTN-…), lots going back to the
 * supplier against a receipt, off stock once finalised. Each is addressed by
 * its number.
 */
class DyeChemReceiptApiController extends ApiController
{
    private const PREFIX = ['PURCHASE' => 'MRR-', 'LOAN_IN' => 'MRR-DC-', 'LOAN_BACK' => 'RTN-LP-'];

    private const MOVE = ['PURCHASE' => 'RECEIPT', 'LOAN_IN' => 'LOAN_IN', 'LOAN_BACK' => 'LOAN_BACK'];

    public function index(Request $request): JsonResponse
    {
        $this->allow('dyehouse.chemstore.view');
        $f = $request->validate(['kind' => ['required', Rule::in(DyeChemReceipt::KINDS)]]);

        return ApiResponse::ok($this->rows(DyeChemReceipt::query()->where('kind', $f['kind'])->orderByDesc('doc_date')->orderByDesc('doc_no')->get()));
    }

    public function show(string $no): JsonResponse
    {
        $this->allow('dyehouse.chemstore.view');

        return ApiResponse::ok($this->present($this->receipt($no)));
    }

    /** A receipt and its lots together: new, or rewritten while still a draft. */
    public function save(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('dyehouse.chemstore.manage');
        $row = $no ? $this->receipt($no) : null;
        if ($row && $row->status !== 'DRAFT') {
            throw ValidationException::withMessages(['doc_no' => __('dyehouse.chem.v.not_draft', ['no' => $row->doc_no])]);
        }
        $c = DyeBatches::company();
        $kind = $row?->kind ?? $request->input('kind');
        $data = $request->validate([
            'kind' => [$row ? 'prohibited' : 'required', Rule::in(DyeChemReceipt::KINDS)],
            'doc_date' => ['required', 'date'],
            'party_id' => ['nullable', Rule::in([$c])],
            'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $c)],
            'supplier_id' => [in_array($kind, ['LOAN_IN', 'LOAN_BACK'], true) ? 'required' : 'nullable', Rule::exists('mp_suppliers', 'id')->where('company_id', $c)],
            'source' => [$kind === 'LOAN_BACK' ? 'nullable' : 'required', Rule::in(DyeChemReceipt::SOURCES)],
            'challan_no' => ['nullable', 'string', 'max:60'], 'lc_no' => ['nullable', 'string', 'max:60'],
            'pr_no' => ['nullable', 'string', 'max:40'], 'po_no' => ['nullable', 'string', 'max:40'],
            'req_type' => ['nullable', 'string', 'max:80'], 'pay_method' => ['nullable', 'string', 'max:60'],
            'return_type' => [$kind === 'LOAN_BACK' ? 'required' : 'nullable', Rule::in(DyeChemReceipt::RETURN_TYPES)],
            'received_by' => ['nullable', 'string', 'max:80'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.item_id' => ['required', Rule::exists('items', 'id')->where('company_id', $c)],
            'lines.*.lot_no' => ['required', 'string', 'max:40'],
            'lines.*.exp_date' => ['nullable', 'date'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'lines.*.uom' => ['nullable', 'string', 'max:16', UomCodes::rule($c)],
            'lines.*.rate_bdt' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.remarks' => ['nullable', 'string', 'max:255'],
        ], $this->messages());
        ksort($data['lines']);
        ChemStore::assertOpen($data['doc_date'], 'doc_date');
        $lines = array_values($data['lines']);
        if ($kind === 'LOAN_BACK') {
            $this->assertOwed($data['supplier_id'], $lines, $row?->id);
        }
        $head = collect($data)->only(['doc_date', 'party_id', 'store_id', 'supplier_id', 'source', 'challan_no', 'lc_no', 'pr_no', 'po_no', 'req_type', 'pay_method', 'return_type', 'received_by', 'remarks'])
            ->map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v)->all();
        $row = DB::transaction(function () use ($row, $head, $kind, $lines, $data): DyeChemReceipt {
            if ($row) {
                $row->update($head);
                DyeChemReceiptLine::query()->where('receipt_id', $row->id)->delete();
            } else {
                $row = DyeChemReceipt::query()->create($head + ['kind' => $kind, 'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId(),
                    'doc_no' => DyeBatches::nextNumber(self::PREFIX[$kind], 'dye_chem_receipts', 'doc_no', $data['doc_date'], 'Ym')]);
            }
            foreach ($lines as $i => $l) {
                DyeChemReceiptLine::query()->create(['receipt_id' => $row->id, 'item_id' => $l['item_id'], 'lot_no' => ChemStore::lotNo($l['lot_no']), 'exp_date' => $l['exp_date'] ?? null,
                    'qty' => $l['qty'], 'uom' => UomCodes::normalize($l['uom'] ?? null), 'rate_bdt' => (float) ($l['rate_bdt'] ?? 0), 'remarks' => ($l['remarks'] ?? '') ?: null, 'sort_order' => ($i + 1) * 10]);
            }

            return $row;
        });

        return ApiResponse::ok($this->present($row->refresh()), [], $no ? 200 : 201);
    }

    /**
     * The register's steps: Finalise counts the lots into stock (Received);
     * Verify is accounts agreeing the receipt (Verified); Cancel drops a draft.
     */
    public function transition(string $no, string $action): JsonResponse
    {
        abort_unless(in_array($action, ['finalise', 'verify', 'cancel'], true), 404);
        $action === 'verify' ? $this->allow('dyehouse.chemstore.approve') : $this->allowEither('dyehouse.chemstore.issue', 'dyehouse.chemstore.manage');
        $row = $this->receipt($no);
        $from = ['finalise' => 'DRAFT', 'verify' => 'RECEIVED', 'cancel' => 'DRAFT'][$action];
        if ($row->status !== $from) {
            throw ValidationException::withMessages(['status' => __("dyehouse.chem.v.cannot_{$action}", ['no' => $row->doc_no])]);
        }
        $by = $this->caller()->auditUserId();
        DB::transaction(function () use ($row, $action, $by): void {
            if ($action === 'finalise') {
                ChemStore::assertOpen($row->doc_date->toDateString(), 'doc_date');
                $lines = DyeChemReceiptLine::query()->where('receipt_id', $row->id)->orderBy('sort_order')->get();
                if ($row->kind === 'LOAN_BACK') {
                    $this->assertOwed($row->supplier_id, $lines->map(fn ($l) => ['item_id' => $l->item_id, 'qty' => $l->qty])->all(), $row->id);
                }
                foreach ($lines as $l) {
                    $lot = ChemStore::lot($row->store_id, $l->item_id, $l->lot_no, ['exp_date' => $l->exp_date?->toDateString(), 'uom' => $l->uom, 'rate_bdt' => $l->rate_bdt, 'source' => $row->source]);
                    ChemStore::add($lot, self::MOVE[$row->kind], $row->doc_no, $l->id, $row->doc_date->toDateString(), (float) $l->qty, (float) $l->qty * ((float) $l->rate_bdt ?: (float) $lot->rate_bdt), $by);
                    $l->update(['lot_id' => $lot->id]);
                }
                $row->update(['status' => 'RECEIVED', 'finalized_at' => now(), 'finalized_by' => $by]);
            } elseif ($action === 'verify') {
                $row->update(['status' => 'VERIFIED', 'verified_at' => now(), 'verified_by' => $by]);
            } else {
                $row->update(['status' => 'CANCELLED']);
            }
        });

        return ApiResponse::ok($this->present($row->refresh()));
    }

    public function returns(): JsonResponse
    {
        $this->allow('dyehouse.chemstore.view');

        return ApiResponse::ok($this->returnRows(DyeChemReturn::query()->orderByDesc('return_date')->orderByDesc('return_no')->get()));
    }

    public function showReturn(string $no): JsonResponse
    {
        $this->allow('dyehouse.chemstore.view');

        return ApiResponse::ok($this->presentReturn($this->return($no)));
    }

    /** The lots a counted purchase receipt brought in, with what is still there to send back: what a return is written against. */
    public function returnSource(Request $request, string $no): JsonResponse
    {
        $this->allow('dyehouse.chemstore.view');
        $rec = $this->receipt($no);
        $except = $request->query('except') ? DyeChemReturn::query()->where('return_no', $request->query('except'))->value('id') : null;

        return ApiResponse::ok($this->sourceOf($rec, $except));
    }

    /** A return and its lots together: new, or rewritten while still a draft. */
    public function saveReturn(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('dyehouse.chemstore.manage');
        $row = $no ? $this->return($no) : null;
        if ($row && $row->status !== 'DRAFT') {
            throw ValidationException::withMessages(['return_no' => __('dyehouse.chem.v.not_draft', ['no' => $row->return_no])]);
        }
        $c = DyeBatches::company();
        $data = $request->validate([
            'return_date' => ['required', 'date'],
            'mrr_no' => ['required', 'string'],
            'return_type' => ['required', Rule::in(DyeChemReturn::TYPES)],
            'party_id' => ['nullable', Rule::in([$c])],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.receipt_line_id' => ['required', 'string'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'lines.*.remarks' => ['nullable', 'string', 'max:255'],
        ], [
            'return_date.required' => __('dyehouse.chem.v.return_date'), 'mrr_no.required' => __('dyehouse.chem.v.mrr'), 'return_type.required' => __('dyehouse.chem.v.return_type'),
            'lines.required' => __('dyehouse.chem.v.lines'), 'lines.min' => __('dyehouse.chem.v.lines'), 'lines.*.qty.gt' => __('dyehouse.chem.v.line_qty'), 'lines.*.qty.required' => __('dyehouse.chem.v.line_qty'),
        ]);
        ksort($data['lines']);
        $rec = DyeChemReceipt::query()->where('doc_no', $data['mrr_no'])->first();
        if (! $rec || $rec->kind !== 'PURCHASE' || ! in_array($rec->status, DyeChemReceipt::POSTED, true)) {
            throw ValidationException::withMessages(['mrr_no' => __('dyehouse.chem.v.mrr_not_counted', ['no' => $data['mrr_no']])]);
        }
        if ($data['return_date'] < $rec->doc_date->toDateString()) {
            throw ValidationException::withMessages(['return_date' => __('dyehouse.chem.v.return_before', ['no' => $rec->doc_no])]);
        }
        ChemStore::assertOpen($data['return_date'], 'return_date');
        $source = collect($this->sourceOf($rec, $row?->id)['lines'])->keyBy('id');
        $asked = [];
        foreach ($data['lines'] as $k => $l) {
            $src = $source[$l['receipt_line_id']] ?? null;
            if (! $src) {
                throw ValidationException::withMessages(["lines.{$k}.receipt_line_id" => __('dyehouse.chem.v.not_on_mrr', ['no' => $rec->doc_no])]);
            }
            $asked[$src['id']] = round(($asked[$src['id']] ?? 0) + (float) $l['qty'], 4);
            if ($asked[$src['id']] > $src['returnable'] + 0.00005) {
                throw ValidationException::withMessages(["lines.{$k}.qty" => __('dyehouse.chem.v.return_more', ['item' => $src['item'], 'lot' => $src['lot_no'], 'left' => ChemStore::qty($src['returnable'])])]);
            }
        }
        $row = DB::transaction(function () use ($row, $data, $rec, $source): DyeChemReturn {
            $head = ['return_date' => $data['return_date'], 'receipt_id' => $rec->id, 'return_type' => $data['return_type'], 'party_id' => $data['party_id'] ?? null,
                'supplier_id' => $rec->supplier_id, 'store_id' => $rec->store_id, 'remarks' => $data['remarks'] ?? null];
            if ($row) {
                $row->update($head);
                DyeChemReturnLine::query()->where('return_id', $row->id)->delete();
            } else {
                $row = DyeChemReturn::query()->create($head + ['status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId(),
                    'return_no' => DyeBatches::nextNumber('RTN-', 'dye_chem_returns', 'return_no', $data['return_date'], 'Ym')]);
            }
            foreach (array_values($data['lines']) as $i => $l) {
                $src = $source[$l['receipt_line_id']];
                DyeChemReturnLine::query()->create(['return_id' => $row->id, 'receipt_line_id' => $src['id'], 'item_id' => $src['item_id'], 'lot_no' => $src['lot_no'], 'qty' => $l['qty'],
                    'uom' => $src['uom'], 'remarks' => ($l['remarks'] ?? '') ?: null, 'sort_order' => ($i + 1) * 10]);
            }

            return $row;
        });

        return ApiResponse::ok($this->presentReturn($row->refresh()), [], $no ? 200 : 201);
    }

    /** Finalise takes the lots off the store's stock — raising the challan is not the same as the lorry leaving; Cancel drops a draft. */
    public function returnTransition(string $no, string $action): JsonResponse
    {
        abort_unless(in_array($action, ['finalise', 'cancel'], true), 404);
        $this->allowEither('dyehouse.chemstore.issue', 'dyehouse.chemstore.manage');
        $row = $this->return($no);
        if ($row->status !== 'DRAFT') {
            throw ValidationException::withMessages(['status' => __("dyehouse.chem.v.cannot_{$action}", ['no' => $row->return_no])]);
        }
        $by = $this->caller()->auditUserId();
        DB::transaction(function () use ($row, $action, $by): void {
            if ($action === 'cancel') {
                $row->update(['status' => 'CANCELLED']);

                return;
            }
            ChemStore::assertOpen($row->return_date->toDateString(), 'return_date');
            foreach (DyeChemReturnLine::query()->where('return_id', $row->id)->orderBy('sort_order')->get() as $l) {
                ChemStore::take($row->store_id, $l->item_id, $l->lot_no, (float) $l->qty, 'RETURN', $row->return_no, $l->id, $row->return_date->toDateString(), $by);
            }
            $row->update(['status' => 'FINALIZED', 'finalized_at' => now(), 'finalized_by' => $by]);
        });

        return ApiResponse::ok($this->presentReturn($row->refresh()));
    }

    private function receipt(string $no): DyeChemReceipt
    {
        return DyeChemReceipt::query()->where('doc_no', $no)->firstOrFail();
    }

    private function return(string $no): DyeChemReturn
    {
        return DyeChemReturn::query()->where('return_no', $no)->firstOrFail();
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'kind.required' => __('dyehouse.chem.v.kind'), 'doc_date.required' => __('dyehouse.chem.v.doc_date'), 'store_id.required' => __('dyehouse.chem.v.store'),
            'supplier_id.required' => __('dyehouse.chem.v.supplier'), 'source.required' => __('dyehouse.chem.v.source'), 'return_type.required' => __('dyehouse.chem.v.loan_back_type'),
            'lines.required' => __('dyehouse.chem.v.lines'), 'lines.min' => __('dyehouse.chem.v.lines'),
            'lines.*.item_id.required' => __('dyehouse.chem.v.line_item'), 'lines.*.lot_no.required' => __('dyehouse.chem.v.line_lot'),
            'lines.*.qty.required' => __('dyehouse.chem.v.line_qty'), 'lines.*.qty.gt' => __('dyehouse.chem.v.line_qty'),
        ];
    }

    /**
     * A party sends back no more of an item than it owes us.
     *
     * @param  list<array{item_id: string, qty: mixed}>  $lines
     */
    private function assertOwed(string $supplierId, array $lines, ?string $exceptId): void
    {
        $owed = ChemStore::theyOwe($supplierId, $exceptId);
        $asked = [];
        foreach ($lines as $l) {
            $asked[$l['item_id']] = round(($asked[$l['item_id']] ?? 0) + (float) $l['qty'], 4);
        }
        $names = ChemStore::itemNames(collect(array_keys($asked)));
        foreach ($asked as $item => $q) {
            if ($q > ($owed[$item] ?? 0) + 0.00005) {
                throw ValidationException::withMessages(['lines' => __('dyehouse.chem.v.they_owe', ['supplier' => DB::table('mp_suppliers')->where('id', $supplierId)->value('name'),
                    'item' => $names[$item] ?? $item, 'owed' => ChemStore::qty(max(0, (float) ($owed[$item] ?? 0)))])]);
            }
        }
    }

    /**
     * A receipt's lots, each with what live returns have claimed and what is left to send back.
     *
     * @return array<string, mixed>
     */
    private function sourceOf(DyeChemReceipt $rec, ?string $exceptReturnId): array
    {
        $lines = DyeChemReceiptLine::query()->where('receipt_id', $rec->id)->orderBy('sort_order')->get();
        $live = DyeChemReturn::query()->where('receipt_id', $rec->id)->where('status', '!=', 'CANCELLED')->when($exceptReturnId, fn ($q) => $q->whereKeyNot($exceptReturnId))->select('id');
        $claimed = DyeChemReturnLine::query()->whereIn('return_id', $live)->groupBy('receipt_line_id')->selectRaw('receipt_line_id, sum(qty) as q')->pluck('q', 'receipt_line_id');
        $names = ChemStore::itemNames($lines->pluck('item_id'));
        $n = DyeBatches::names();

        return [
            'mrr_no' => $rec->doc_no, 'mrr_date' => $rec->doc_date->toDateString(), 'status' => $rec->status, 'store_id' => $rec->store_id, 'store' => ChemStore::store($rec->store_id),
            'supplier_id' => $rec->supplier_id, 'supplier' => $n['suppliers'][$rec->supplier_id] ?? null, 'party_id' => $rec->party_id,
            'lines' => $lines->map(fn (DyeChemReceiptLine $l) => [
                'id' => $l->id, 'item_id' => $l->item_id, 'item' => $names[$l->item_id] ?? null, 'lot_no' => $l->lot_no, 'uom' => $l->uom, 'received' => (float) $l->qty,
                'returned' => round((float) ($claimed[$l->id] ?? 0), 4), 'returnable' => round((float) $l->qty - (float) ($claimed[$l->id] ?? 0), 4),
            ])->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, DyeChemReceipt>  $rows
     * @return list<array<string, mixed>>
     */
    private function rows(Collection $rows): array
    {
        $n = DyeBatches::names();
        $sums = DyeChemReceiptLine::query()->whereIn('receipt_id', $rows->pluck('id'))->groupBy('receipt_id')
            ->selectRaw('receipt_id, count(*) as items, sum(qty) as qty, sum(qty * rate_bdt) as value')->get()->keyBy('receipt_id');
        $users = DB::table('users')->whereIn('id', $rows->pluck('finalized_by')->filter()->unique())->pluck('name', 'id');

        return $rows->map(fn (DyeChemReceipt $r) => [
            'id' => $r->id, 'kind' => $r->kind, 'doc_no' => $r->doc_no, 'doc_date' => $r->doc_date->toDateString(), 'party_id' => $r->party_id,
            'company' => $n['companies'][$r->party_id ?? DyeBatches::company()] ?? null, 'store_id' => $r->store_id, 'store' => ChemStore::store($r->store_id),
            'supplier_id' => $r->supplier_id, 'supplier' => $n['suppliers'][$r->supplier_id] ?? null, 'source' => $r->source, 'challan_no' => $r->challan_no, 'lc_no' => $r->lc_no,
            'pr_no' => $r->pr_no, 'po_no' => $r->po_no, 'req_type' => $r->req_type, 'pay_method' => $r->pay_method, 'return_type' => $r->return_type, 'received_by' => $r->received_by,
            'remarks' => $r->remarks, 'status' => $r->status, 'finalized' => in_array($r->status, DyeChemReceipt::POSTED, true), 'accounted' => $r->status === 'VERIFIED',
            'finalized_by' => $users[$r->finalized_by] ?? null, 'items' => (int) ($sums[$r->id]->items ?? 0), 'qty' => round((float) ($sums[$r->id]->qty ?? 0), 3),
            'value' => round((float) ($sums[$r->id]->value ?? 0), 2),
        ])->values()->all();
    }

    /** @return array<string, mixed> */
    private function present(DyeChemReceipt $r): array
    {
        $lines = DyeChemReceiptLine::query()->where('receipt_id', $r->id)->orderBy('sort_order')->get();
        $names = ChemStore::itemNames($lines->pluck('item_id'));

        return $this->rows(collect([$r]))[0] + ['lines' => $lines->map(fn (DyeChemReceiptLine $l) => [
            'item_id' => $l->item_id, 'item' => $names[$l->item_id] ?? null, 'lot_no' => $l->lot_no, 'exp_date' => $l->exp_date?->toDateString(), 'qty' => (float) $l->qty,
            'uom' => $l->uom, 'rate_bdt' => (float) $l->rate_bdt, 'remarks' => $l->remarks,
        ])->values()->all()];
    }

    /**
     * @param  Collection<int, DyeChemReturn>  $rows
     * @return list<array<string, mixed>>
     */
    private function returnRows(Collection $rows): array
    {
        $n = DyeBatches::names();
        $mrr = DyeChemReceipt::query()->whereIn('id', $rows->pluck('receipt_id'))->pluck('doc_no', 'id');
        $sums = DyeChemReturnLine::query()->whereIn('return_id', $rows->pluck('id'))->groupBy('return_id')->selectRaw('return_id, count(*) as items, sum(qty) as qty')->get()->keyBy('return_id');

        return $rows->map(fn (DyeChemReturn $r) => [
            'id' => $r->id, 'return_no' => $r->return_no, 'return_date' => $r->return_date->toDateString(), 'mrr_no' => $mrr[$r->receipt_id] ?? null, 'return_type' => $r->return_type,
            'party_id' => $r->party_id, 'company' => $n['companies'][$r->party_id ?? DyeBatches::company()] ?? null, 'supplier_id' => $r->supplier_id, 'supplier' => $n['suppliers'][$r->supplier_id] ?? null,
            'store_id' => $r->store_id, 'store' => ChemStore::store($r->store_id), 'remarks' => $r->remarks, 'status' => $r->status, 'finalized' => $r->status === 'FINALIZED',
            'items' => (int) ($sums[$r->id]->items ?? 0), 'qty' => round((float) ($sums[$r->id]->qty ?? 0), 3),
        ])->values()->all();
    }

    /** @return array<string, mixed> */
    private function presentReturn(DyeChemReturn $r): array
    {
        $lines = DyeChemReturnLine::query()->where('return_id', $r->id)->orderBy('sort_order')->get();
        $names = ChemStore::itemNames($lines->pluck('item_id'));

        return $this->returnRows(collect([$r]))[0] + ['lines' => $lines->map(fn (DyeChemReturnLine $l) => [
            'receipt_line_id' => $l->receipt_line_id, 'item_id' => $l->item_id, 'item' => $names[$l->item_id] ?? null, 'lot_no' => $l->lot_no, 'qty' => (float) $l->qty, 'uom' => $l->uom, 'remarks' => $l->remarks,
        ])->values()->all()];
    }
}
