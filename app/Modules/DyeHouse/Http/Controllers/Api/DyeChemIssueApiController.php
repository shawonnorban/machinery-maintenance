<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Http\Controllers\Api;

use App\Modules\DyeHouse\Models\DyeBatch;
use App\Modules\DyeHouse\Models\DyeChemIssue;
use App\Modules\DyeHouse\Models\DyeChemIssueLine;
use App\Modules\DyeHouse\Support\ChemStore;
use App\Modules\DyeHouse\Support\DyeBatches;
use App\Modules\Inventory\Support\UomCodes;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Dye House → Chemical Store (AnnotechERP 17/06), stock going out: Chemical
 * Store Transfer Issue (REQ-ST-), Print Chemical Issue Note (IS-PR-), Machine
 * Wash & Finishing Issue (IS-MW-), Batch Programme Chemical Issue (IS-BP-),
 * Dye & Chemical Sales (IS-SL-), Chemical Loan Issue Indent (IS-LN-) and
 * Chemical Loan Return Indent (IS-LR-). The header says what the chemicals
 * are for, the lines what is asked — the required total their sum. Nothing
 * leaves stock until the store issues against it on the register, line by
 * line, from the named lot or the soonest to expire; what is issued may fall
 * short of what was asked (Partial Issued). A transfer is verified before it
 * is issued, and lands as stock in the receiving store. A loan goes back to a
 * supplier only as far as we owe them.
 */
class DyeChemIssueApiController extends ApiController
{
    /** Who the paper names: Sales To, the loan's party, Return To. */
    private const PARTY = ['SALE', 'LOAN_OUT', 'LOAN_RETURN'];

    public function index(Request $request): JsonResponse
    {
        $this->allow('dyehouse.chemstore.view');
        $f = $request->validate(['kind' => ['required', Rule::in(DyeChemIssue::KINDS)]]);

        return ApiResponse::ok($this->rows(DyeChemIssue::query()->where('kind', $f['kind'])->orderByDesc('issue_date')->orderByDesc('issue_no')->get()));
    }

    public function show(string $no): JsonResponse
    {
        $this->allow('dyehouse.chemstore.view');

        return ApiResponse::ok($this->present($this->issue($no)));
    }

    /** An issue paper and its lines together: new, or rewritten while nothing has been verified or issued against it. */
    public function save(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('dyehouse.chemstore.manage');
        $row = $no ? $this->issue($no) : null;
        if ($row && ! in_array($row->status, ['WAITING_VERIFICATION', 'WAITING'], true)) {
            throw ValidationException::withMessages(['issue_no' => __('dyehouse.chem.v.issue_closed', ['no' => $row->issue_no])]);
        }
        $c = DyeBatches::company();
        $kind = $row?->kind ?? $request->input('kind');
        $data = $request->validate([
            'kind' => [$row ? 'prohibited' : 'required', Rule::in(DyeChemIssue::KINDS)],
            'issue_date' => ['required', 'date'],
            'req_type' => ['required', 'string', 'max:120'],
            'party_id' => ['nullable', Rule::in([$c])],
            'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $c)],
            'to_store_id' => [$kind === 'TRANSFER' ? 'required' : 'prohibited', 'nullable', 'different:store_id', Rule::exists('stores', 'id')->where('company_id', $c)],
            'gate_pass' => ['nullable', 'string', 'max:40'],
            'buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $c)],
            'order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $c)],
            'print_type' => ['nullable', 'string', 'max:60'],
            'batch_id' => ['nullable', Rule::exists('dye_batches', 'id')->where('company_id', $c)],
            'machine_id' => ['nullable', Rule::exists('dye_machines', 'id')->where('company_id', $c)],
            'process_type' => ['nullable', 'string', 'max:60'],
            'is_reprocess' => ['nullable', 'boolean'],
            'supplier_id' => [in_array($kind, self::PARTY, true) ? 'required' : 'prohibited', 'nullable', Rule::exists('mp_suppliers', 'id')->where('company_id', $c)],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.item_id' => ['required', Rule::exists('items', 'id')->where('company_id', $c)],
            'lines.*.lot_no' => ['nullable', 'string', 'max:40'],
            'lines.*.required_qty' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'lines.*.uom' => ['nullable', 'string', 'max:16', UomCodes::rule($c)],
            'lines.*.rate_bdt' => [$kind === 'SALE' ? 'required' : 'nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.remarks' => ['nullable', 'string', 'max:255'],
        ], [
            'kind.required' => __('dyehouse.chem.v.kind'), 'issue_date.required' => __('dyehouse.chem.v.req_date'), 'req_type.required' => __('dyehouse.chem.v.req_type'),
            'store_id.required' => __('dyehouse.chem.v.from_store'), 'to_store_id.required' => __('dyehouse.chem.v.to_store'), 'to_store_id.different' => __('dyehouse.chem.v.same_store'),
            'supplier_id.required' => __("dyehouse.chem.v.party_{$kind}"),
            'lines.required' => __('dyehouse.chem.v.lines'), 'lines.min' => __('dyehouse.chem.v.lines'), 'lines.*.item_id.required' => __('dyehouse.chem.v.line_item'),
            'lines.*.required_qty.required' => __('dyehouse.chem.v.line_req'), 'lines.*.required_qty.gt' => __('dyehouse.chem.v.line_req'), 'lines.*.rate_bdt.required' => __('dyehouse.chem.v.line_rate'),
        ]);
        ksort($data['lines']);
        ChemStore::assertOpen($data['issue_date'], 'issue_date');
        $lines = array_values($data['lines']);
        if ($kind === 'LOAN_RETURN') {
            $this->assertWeOwe($data['supplier_id'], $lines, $row?->id);
        }
        $head = [
            'issue_date' => $data['issue_date'], 'req_type' => trim($data['req_type']), 'party_id' => $data['party_id'] ?? null, 'store_id' => $data['store_id'],
            'to_store_id' => $kind === 'TRANSFER' ? $data['to_store_id'] : null, 'gate_pass' => $kind === 'TRANSFER' ? (($data['gate_pass'] ?? '') ?: null) : null,
            'buyer_id' => $kind === 'PRINT' ? ($data['buyer_id'] ?? null) : null, 'order_id' => $kind === 'PRINT' ? ($data['order_id'] ?? null) : null,
            'print_type' => $kind === 'PRINT' ? (($data['print_type'] ?? '') ?: null) : null,
            'batch_id' => $kind === 'BATCH' ? ($data['batch_id'] ?? null) : null, 'dye_machine_id' => $kind === 'BATCH' ? ($data['machine_id'] ?? null) : null,
            'process_type' => $kind === 'BATCH' ? (($data['process_type'] ?? '') ?: null) : null, 'is_reprocess' => $kind === 'BATCH' && ($data['is_reprocess'] ?? false),
            'supplier_id' => in_array($kind, self::PARTY, true) ? $data['supplier_id'] : null, 'remarks' => ($data['remarks'] ?? '') ?: null,
        ];
        // A batch names its machine when none is given.
        if ($head['batch_id'] && ! $head['dye_machine_id']) {
            $head['dye_machine_id'] = DyeBatch::query()->whereKey($head['batch_id'])->value('dye_machine_id');
        }
        $row = DB::transaction(function () use ($row, $head, $kind, $lines, $data): DyeChemIssue {
            if ($row) {
                $row->update($head);
                DyeChemIssueLine::query()->where('issue_id', $row->id)->delete();
            } else {
                $row = DyeChemIssue::query()->create($head + ['kind' => $kind, 'status' => $kind === 'TRANSFER' ? 'WAITING_VERIFICATION' : 'WAITING', 'created_by' => $this->caller()->auditUserId(),
                    'issue_no' => DyeBatches::nextNumber(DyeChemIssue::PREFIX[$kind], 'dye_chem_issues', 'issue_no', $data['issue_date'], 'Ym')]);
            }
            foreach ($lines as $i => $l) {
                $lot = trim((string) ($l['lot_no'] ?? ''));
                DyeChemIssueLine::query()->create(['issue_id' => $row->id, 'item_id' => $l['item_id'], 'lot_no' => $lot === '' ? null : ChemStore::lotNo($lot), 'required_qty' => $l['required_qty'],
                    'uom' => UomCodes::normalize($l['uom'] ?? null), 'rate_bdt' => isset($l['rate_bdt']) ? (float) $l['rate_bdt'] : null, 'remarks' => ($l['remarks'] ?? '') ?: null, 'sort_order' => ($i + 1) * 10]);
            }

            return $row;
        });

        return ApiResponse::ok($this->present($row->refresh()), [], $no ? 200 : 201);
    }

    /** A transfer is verified before the store gives anything against it. */
    public function verify(string $no): JsonResponse
    {
        $this->allowEither('dyehouse.chemstore.issue', 'dyehouse.chemstore.approve');
        $row = $this->issue($no);
        if ($row->kind !== 'TRANSFER' || $row->status !== 'WAITING_VERIFICATION') {
            throw ValidationException::withMessages(['status' => __('dyehouse.chem.v.cannot_verify', ['no' => $row->issue_no])]);
        }
        $row->update(['status' => 'VERIFIED', 'verified_at' => now(), 'verified_by' => $this->caller()->auditUserId()]);

        return ApiResponse::ok($this->present($row->refresh()));
    }

    /**
     * Issue: what the store gives against each line now, at most what is still
     * asked, taken off stock — and, on a transfer, landed in the receiving store.
     */
    public function issueStock(Request $request, string $no): JsonResponse
    {
        $this->allow('dyehouse.chemstore.issue');
        $row = $this->issue($no);
        if (! in_array($row->status, DyeChemIssue::ISSUABLE, true)) {
            throw ValidationException::withMessages(['status' => __($row->status === 'WAITING_VERIFICATION' ? 'dyehouse.chem.v.verify_first' : 'dyehouse.chem.v.cannot_issue', ['no' => $row->issue_no])]);
        }
        $data = $request->validate([
            'issue_date' => ['nullable', 'date'],
            'gate_pass' => ['nullable', 'string', 'max:40'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['required', 'string'],
            'lines.*.qty' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ], ['lines.required' => __('dyehouse.chem.v.issue_nothing')]);
        $date = $data['issue_date'] ?? CarbonImmutable::now('Asia/Dhaka')->toDateString();
        if ($date < $row->issue_date->toDateString()) {
            throw ValidationException::withMessages(['issue_date' => __('dyehouse.chem.v.issue_before', ['no' => $row->issue_no])]);
        }
        ChemStore::assertOpen($date, 'issue_date');
        $lines = DyeChemIssueLine::query()->where('issue_id', $row->id)->orderBy('sort_order')->get()->keyBy('id');
        $give = [];
        foreach ($data['lines'] as $k => $l) {
            $line = $lines[$l['id']] ?? null;
            if (! $line) {
                throw ValidationException::withMessages(["lines.{$k}.id" => __('dyehouse.chem.v.not_on_issue', ['no' => $row->issue_no])]);
            }
            $left = round((float) $line->required_qty - (float) $line->issued_qty, 4);
            if ((float) $l['qty'] > $left + 0.00005) {
                throw ValidationException::withMessages(["lines.{$k}.qty" => __('dyehouse.chem.v.issue_more', ['left' => ChemStore::qty(max(0, $left))])]);
            }
            if ((float) $l['qty'] > 0) {
                $give[$line->id] = (float) $l['qty'];
            }
        }
        if (! $give) {
            throw ValidationException::withMessages(['lines' => __('dyehouse.chem.v.issue_nothing')]);
        }
        $by = $this->caller()->auditUserId();
        DB::transaction(function () use ($row, $lines, $give, $date, $by, $data): void {
            $move = $row->kind === 'TRANSFER' ? 'TRANSFER_OUT' : $row->kind;
            foreach ($give as $id => $qty) {
                $line = $lines[$id];
                $taken = ChemStore::take($row->store_id, $line->item_id, $line->lot_no, $qty, $move, $row->issue_no, $line->id, $date, $by);
                if ($row->kind === 'TRANSFER') {
                    foreach ($taken as $t) {
                        $src = $t['lot'];
                        $to = ChemStore::lot($row->to_store_id, $line->item_id, $src->lot_no, ['exp_date' => $src->exp_date?->toDateString(), 'uom' => $src->uom, 'rate_bdt' => $src->rate_bdt, 'source' => $src->source]);
                        ChemStore::add($to, 'TRANSFER_IN', $row->issue_no, $line->id, $date, $t['qty'], $t['qty'] * (float) $src->rate_bdt, $by);
                    }
                }
                $line->update(['issued_qty' => round((float) $line->issued_qty + $qty, 4)]);
            }
            $asked = (float) $lines->sum('required_qty');
            $out = (float) DyeChemIssueLine::query()->where('issue_id', $row->id)->sum('issued_qty');
            $row->update(['status' => $out + 0.00005 >= $asked ? 'ISSUED' : 'PARTIAL', 'issued_at' => now(), 'issued_by' => $by]
                + ($row->kind === 'TRANSFER' && ($data['gate_pass'] ?? '') !== '' ? ['gate_pass' => trim((string) $data['gate_pass'])] : []));
        });

        return ApiResponse::ok($this->present($row->refresh()));
    }

    /** Cancel: what is still asked is dropped; anything already issued stays issued. */
    public function cancel(string $no): JsonResponse
    {
        $this->allowEither('dyehouse.chemstore.manage', 'dyehouse.chemstore.issue');
        $row = $this->issue($no);
        if (in_array($row->status, ['ISSUED', 'CANCELLED'], true)) {
            throw ValidationException::withMessages(['status' => __('dyehouse.chem.v.cannot_cancel', ['no' => $row->issue_no])]);
        }
        $row->update(['status' => 'CANCELLED']);

        return ApiResponse::ok($this->present($row->refresh()));
    }

    private function issue(string $no): DyeChemIssue
    {
        return DyeChemIssue::query()->where('issue_no', $no)->firstOrFail();
    }

    /**
     * We send back no more of an item than we owe the supplier.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    private function assertWeOwe(string $supplierId, array $lines, ?string $exceptId): void
    {
        $owed = ChemStore::weOwe($supplierId, $exceptId);
        $asked = [];
        foreach ($lines as $l) {
            $asked[$l['item_id']] = round(($asked[$l['item_id']] ?? 0) + (float) $l['required_qty'], 4);
        }
        $names = ChemStore::itemNames(collect(array_keys($asked)));
        foreach ($asked as $item => $q) {
            if ($q > ($owed[$item] ?? 0) + 0.00005) {
                throw ValidationException::withMessages(['lines' => __('dyehouse.chem.v.we_owe', ['supplier' => DB::table('mp_suppliers')->where('id', $supplierId)->value('name'),
                    'item' => $names[$item] ?? $item, 'owed' => ChemStore::qty(max(0, (float) ($owed[$item] ?? 0)))])]);
            }
        }
    }

    /**
     * @param  Collection<int, DyeChemIssue>  $rows
     * @return list<array<string, mixed>>
     */
    private function rows(Collection $rows): array
    {
        $n = DyeBatches::names();
        $sums = DyeChemIssueLine::query()->whereIn('issue_id', $rows->pluck('id'))->groupBy('issue_id')
            ->selectRaw('issue_id, count(*) as items, sum(required_qty) as req, sum(issued_qty) as iss, sum(issued_qty * coalesce(rate_bdt, 0)) as amount')->get()->keyBy('issue_id');
        $batches = DyeBatch::query()->whereIn('id', $rows->pluck('batch_id')->filter())->pluck('batch_no', 'id');

        return $rows->map(function (DyeChemIssue $r) use ($n, $sums, $batches) {
            $req = round((float) ($sums[$r->id]->req ?? 0), 3);
            $iss = round((float) ($sums[$r->id]->iss ?? 0), 3);
            $order = $r->order_id ? ($n['orders'][$r->order_id] ?? null) : null;

            return [
                'id' => $r->id, 'kind' => $r->kind, 'issue_no' => $r->issue_no, 'issue_date' => $r->issue_date->toDateString(), 'req_type' => $r->req_type,
                'party_id' => $r->party_id, 'company' => $n['companies'][$r->party_id ?? DyeBatches::company()] ?? null,
                'store_id' => $r->store_id, 'store' => ChemStore::store($r->store_id), 'to_store_id' => $r->to_store_id, 'to_store' => ChemStore::store($r->to_store_id),
                'gate_pass' => $r->gate_pass, 'buyer_id' => $r->buyer_id, 'buyer' => $n['buyers'][$r->buyer_id] ?? null, 'order_id' => $r->order_id,
                'style' => $order ? ($order->work_style_no ?: $order->style_no) : null, 'print_type' => $r->print_type,
                'batch_id' => $r->batch_id, 'batch_no' => $batches[$r->batch_id] ?? null, 'machine_id' => $r->dye_machine_id, 'machine' => $n['machines'][$r->dye_machine_id] ?? null,
                'process_type' => $r->process_type, 'is_reprocess' => (bool) $r->is_reprocess, 'supplier_id' => $r->supplier_id, 'supplier' => $n['suppliers'][$r->supplier_id] ?? null,
                'remarks' => $r->remarks, 'status' => $r->status, 'issued_at' => $r->issued_at?->toIso8601String(),
                'required_qty' => $req, 'issued_qty' => $iss, 'balance_qty' => $r->status === 'CANCELLED' ? 0.0 : round($req - $iss, 3), 'items' => (int) ($sums[$r->id]->items ?? 0),
                'amount' => round((float) ($sums[$r->id]->amount ?? 0), 2),
            ];
        })->values()->all();
    }

    /** @return array<string, mixed> */
    private function present(DyeChemIssue $r): array
    {
        $lines = DyeChemIssueLine::query()->where('issue_id', $r->id)->orderBy('sort_order')->get();
        $names = ChemStore::itemNames($lines->pluck('item_id'));
        // What the issuing store holds of each item, for the Issue dialog.
        $stock = [];
        foreach ($lines as $l) {
            $stock[$l->id] = ChemStore::itemBalance($r->store_id, $l->item_id);
        }

        return $this->rows(collect([$r]))[0] + ['lines' => $lines->map(fn (DyeChemIssueLine $l) => [
            'id' => $l->id, 'item_id' => $l->item_id, 'item' => $names[$l->item_id] ?? null, 'lot_no' => $l->lot_no, 'required_qty' => (float) $l->required_qty,
            'issued_qty' => (float) $l->issued_qty, 'balance_qty' => round((float) $l->required_qty - (float) $l->issued_qty, 4), 'uom' => $l->uom,
            'rate_bdt' => $l->rate_bdt === null ? null : (float) $l->rate_bdt, 'remarks' => $l->remarks, 'in_store' => $stock[$l->id],
        ])->values()->all()];
    }
}
