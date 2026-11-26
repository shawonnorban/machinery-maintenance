<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Inventory\Models\Store;
use App\Modules\Knitting\Models\YarnIssue;
use App\Modules\Knitting\Models\YarnIssueLine;
use App\Modules\Knitting\Models\YarnIssueReq;
use App\Modules\Knitting\Models\YarnIssueReqLine;
use App\Modules\Knitting\Models\YarnIssueReturnLine;
use App\Modules\Knitting\Models\YarnLot;
use App\Modules\Knitting\Models\YarnMove;
use App\Modules\Knitting\Support\YarnProcurement;
use App\Modules\Knitting\Support\YarnStore;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\Buyer;
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
 * Knit Production → Yarn Store (AnnotechERP 15/03) → Yarn Issue against
 * Indent, as Brickland works it: the Yarn Issue List (the requisitions, with
 * TTL Req. Qty, TTL Issue Qty and Balance), the Yarn Requisition Entry —
 * what the floor asks for, one count per line, numbered YIR-2026100001 —
 * and the Yarn Issue Entry, which takes each requisition line off a lot of
 * the requisition's store (YIS-…), posted on save. A requisition with yarn
 * already out cannot have its lines rewritten; it is closed (a decision) or
 * cancelled (only before anything left). Everything travels by number.
 */
class YarnIssueApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allowEither('knitting.ystore.view', 'knitting.production.view');

        return ApiResponse::ok(YarnProcurement::options() + [
            'req_types' => YarnStore::REQ_TYPES, 'statuses' => YarnIssueReq::STATUSES,
            'open_reqs' => YarnIssueReq::query()->whereIn('status', YarnIssueReq::ISSUABLE)->orderByDesc('req_no')->limit(300)->pluck('req_no')->map(fn ($n) => ['value' => $n, 'label' => $n])->values()->all(),
            'reqs' => YarnIssueReq::query()->orderByDesc('req_no')->limit(300)->pluck('req_no')->map(fn ($n) => ['value' => $n, 'label' => $n])->values()->all(),
            'issues' => YarnIssue::query()->orderByDesc('issue_no')->limit(300)->pluck('issue_no')->map(fn ($n) => ['value' => $n, 'label' => $n])->values()->all(),
            // What General Yarn Issue Indent (15/04) picks Yarn and Brand from.
            'lots' => array_map(fn (array $l) => collect($l)->only(['store_id', 'yarn', 'composition_id', 'yarn_count_id', 'brand', 'lot_no'])->all(), YarnStore::lots(null, false)),
            'yarn_names' => collect(YarnProcurement::names())->only(['compositions', 'counts'])->all(),
        ]);
    }

    /** The Yarn Issue List: the requisitions; every box narrows the query, blank shows the most recent. */
    public function index(Request $request): JsonResponse
    {
        $this->allowEither('knitting.ystore.view', 'knitting.production.view');
        $f = $request->validate([
            'no' => ['nullable', 'string', 'max:24'], 'date' => ['nullable', 'date'], 'type' => ['nullable', Rule::in(YarnStore::REQ_TYPES)],
            'status' => ['nullable', Rule::in(YarnIssueReq::STATUSES)], 'buyer' => ['nullable', 'string'], 'order' => ['nullable', 'string'],
            'party' => ['nullable', 'string'], 'count' => ['nullable', 'string'],
        ]);
        $rows = YarnIssueReq::query()
            ->when(! empty($f['no']), fn ($q) => $q->where('req_no', 'ilike', '%'.addcslashes($f['no'], '%_\\').'%'))
            ->when(! empty($f['date']), fn ($q) => $q->whereDate('req_date', $f['date']))
            ->when(! empty($f['type']), fn ($q) => $q->where('req_type', $f['type']))
            ->when(! empty($f['status']), fn ($q) => $q->where('status', $f['status']))
            ->when(! empty($f['buyer']), fn ($q) => $q->where('buyer_id', $f['buyer']))
            ->when(! empty($f['order']), fn ($q) => $q->where('order_id', $f['order']))
            ->when(! empty($f['party']), fn ($q) => $q->where('party_id', $f['party']))
            ->when(! empty($f['count']), fn ($q) => $q->whereIn('id', YarnIssueReqLine::query()->where('yarn_count_id', $f['count'])->select('req_id')))
            ->orderByDesc('req_date')->orderByDesc('req_no')->limit(200)->get();
        $lines = YarnIssueReqLine::query()->whereIn('req_id', $rows->pluck('id'))->get()->groupBy('req_id');
        $stores = Store::query()->whereIn('id', $rows->pluck('store_id'))->pluck('name', 'id');
        $buyers = Buyer::query()->whereIn('id', $rows->pluck('buyer_id')->filter())->get()->mapWithKeys(fn (Buyer $b) => [$b->id => $b->short_name ?: $b->name]);
        $parties = MaterialSupplier::query()->whereIn('id', $rows->pluck('party_id')->filter())->pluck('name', 'id');
        $orderRows = Order::query()->whereIn('id', $rows->pluck('order_id')->filter())->get()->keyBy('id');
        $orders = $orderRows->map(fn (Order $o) => trim(($o->work_style_no ?: $o->style_no ?: $o->job_no).' · '.$o->order_no, ' ·'));

        return ApiResponse::ok($rows->map(function (YarnIssueReq $r) use ($lines, $stores, $buyers, $parties, $orders, $orderRows) {
            $l = $lines[$r->id] ?? collect();
            $req = round((float) $l->sum('req_qty'), 4);
            $issued = round((float) $l->sum('issued_qty'), 4);

            return [
                'req_no' => $r->req_no, 'req_date' => $r->req_date->toDateString(), 'req_type' => $r->req_type, 'store' => $stores[$r->store_id] ?? null,
                'buyer' => $buyers[$r->buyer_id] ?? null, 'order' => $orders[$r->order_id] ?? null, 'party' => $parties[$r->party_id] ?? null, 'program_no' => $r->program_no,
                'order_id' => $r->order_id, 'order_no' => $orderRows[$r->order_id]->order_no ?? null,
                'style_no' => isset($orderRows[$r->order_id]) ? ($orderRows[$r->order_id]->work_style_no ?: $orderRows[$r->order_id]->style_no) : null,
                'lines' => $l->count(), 'req_qty' => $req, 'issued_qty' => $issued, 'balance' => round(max(0, $req - $issued), 4), 'status' => $r->status,
                'editable' => $r->status === 'OPEN' && $issued <= 0,
            ];
        })->values()->all());
    }

    public function show(string $req): JsonResponse
    {
        $this->allowEither('knitting.ystore.view', 'knitting.production.view');

        return ApiResponse::ok($this->present($this->findReq($req)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allowEither('knitting.ystore.manage', 'knitting.production.manage');
        $data = $this->validatedReq($request);
        $row = DB::transaction(function () use ($data): YarnIssueReq {
            $row = YarnIssueReq::query()->create($this->reqHead($data) + [
                'req_no' => YarnStore::nextNumber('YIR', 'knit_yarn_issue_reqs', 'req_no', $data['req_date']), 'status' => 'OPEN', 'created_by' => $this->caller()->auditUserId(),
            ]);
            $this->writeReqLines($row, $data['lines']);

            return $row;
        });

        return ApiResponse::created($this->present($row));
    }

    public function update(Request $request, string $req): JsonResponse
    {
        $this->allowEither('knitting.ystore.manage', 'knitting.production.manage');
        $row = $this->findReq($req);
        if ($row->status !== 'OPEN' || YarnIssue::query()->where('req_id', $row->id)->exists()) {
            throw ValidationException::withMessages(['status' => __('knitting.ystore.v.req_locked', ['no' => $row->req_no])]);
        }
        $data = $this->validatedReq($request);
        DB::transaction(function () use ($row, $data): void {
            $row->update($this->reqHead($data));
            YarnIssueReqLine::query()->where('req_id', $row->id)->delete();
            $this->writeReqLines($row, $data['lines']);
        });

        return ApiResponse::ok($this->present($row->refresh()));
    }

    /** Close (a requisition no more will be issued against) or Cancel (only before anything has left). */
    public function transition(string $req, string $action): JsonResponse
    {
        $this->allowEither('knitting.ystore.manage', 'knitting.production.manage');
        $row = $this->findReq($req);
        $issued = YarnIssue::query()->where('req_id', $row->id)->exists();
        $ok = match ($action) {
            'close' => in_array($row->status, YarnIssueReq::ISSUABLE, true),
            'cancel' => $row->status === 'OPEN' && ! $issued,
            default => abort(404),
        };
        if (! $ok) {
            throw ValidationException::withMessages(['status' => __('knitting.ystore.v.doc_cannot', [
                'no' => $row->req_no, 'status' => __("knitting.ystore.req_status.{$row->status}"), 'action' => __("knitting.ystore.req_action.{$action}"),
            ])]);
        }
        $row->update(['status' => $action === 'close' ? 'CLOSED' : 'CANCELLED']);

        return ApiResponse::ok($this->present($row->refresh()));
    }

    /** The Yarn Issue Entry's source: the requisition's lines with what each still owes, and the store's lots of that yarn. */
    public function source(string $req): JsonResponse
    {
        $this->allow('knitting.ystore.view');
        $row = $this->findReq($req);
        $lots = collect(YarnStore::lots($row->store_id));
        $p = $this->present($row);

        return ApiResponse::ok([
            'lines' => array_map(fn ($l) => $l + [
                'lots' => $lots->filter(fn ($x) => (! $l['composition_id'] || $x['composition_id'] === $l['composition_id']) && (! $l['yarn_count_id'] || $x['yarn_count_id'] === $l['yarn_count_id']))->values()->all(),
            ], $p['lines']),
        ] + $p);
    }

    /* ---------------- Yarn Issue Entry ---------------- */

    public function showIssue(string $issue): JsonResponse
    {
        $this->allow('knitting.ystore.view');

        return ApiResponse::ok($this->presentIssue($this->findIssue($issue)));
    }

    public function storeIssue(Request $request): JsonResponse
    {
        $this->allow('knitting.ystore.manage');
        [$data, $req] = $this->validatedIssue($request, null);
        $row = DB::transaction(function () use ($data, $req): YarnIssue {
            $row = YarnIssue::query()->create(collect($data)->only(['issue_date', 'gate_pass_no', 'remarks'])->all() + [
                'issue_no' => YarnStore::nextNumber('YIS', 'knit_yarn_issues', 'issue_no', $data['issue_date']), 'req_id' => $req->id, 'store_id' => $req->store_id,
                'created_by' => $this->caller()->auditUserId(),
            ]);
            $this->writeIssueLines($row, $data['lines']);
            YarnStore::syncIssueReq($req->id);

            return $row;
        });

        return ApiResponse::created($this->presentIssue($row));
    }

    public function updateIssue(Request $request, string $issue): JsonResponse
    {
        $this->allow('knitting.ystore.manage');
        $row = $this->findIssue($issue);
        YarnStore::assertOpen($row->issue_date->toDateString(), 'issue_date');
        // Yarn credited back against a line means correcting it is a return, not an edit.
        if (YarnIssueReturnLine::query()->whereIn('issue_line_id', YarnIssueLine::query()->where('issue_id', $row->id)->select('id'))->exists()) {
            throw ValidationException::withMessages(['issue_no' => __('knitting.ystore.v.issue_returned', ['no' => $row->issue_no])]);
        }
        [$data, $req] = $this->validatedIssue($request, $row);
        DB::transaction(function () use ($row, $data, $req): void {
            $old = $row->req_id;
            $row->update(collect($data)->only(['issue_date', 'gate_pass_no', 'remarks'])->all() + ['req_id' => $req->id, 'store_id' => $req->store_id]);
            YarnIssueLine::query()->where('issue_id', $row->id)->delete();
            $this->writeIssueLines($row, $data['lines']);
            YarnStore::syncIssueReq($req->id);
            if ($old !== $req->id) {
                YarnStore::syncIssueReq($old);
            }
        });

        return ApiResponse::ok($this->presentIssue($row->refresh()));
    }

    /* ---------------- helpers ---------------- */

    private function findReq(string $no): YarnIssueReq
    {
        return YarnIssueReq::query()->where('req_no', $no)->firstOrFail();
    }

    private function findIssue(string $no): YarnIssue
    {
        return YarnIssue::query()->where('issue_no', $no)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function validatedReq(Request $request): array
    {
        $company = app(TenantContext::class)->companyId();
        $qty = ['nullable', 'numeric', 'min:0', 'max:9999999999'];
        $data = $request->validate([
            'req_date' => ['required', 'date'], 'req_type' => ['required', Rule::in(YarnStore::REQ_TYPES)],
            'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $company)],
            'buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $company)],
            'party_id' => ['nullable', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'program_no' => ['nullable', 'string', 'max:40'], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            ...YarnProcurement::lineRules('lines.*'),
            'lines.*.lot_no' => ['nullable', 'string', 'max:40'], 'lines.*.req_qty' => $qty, 'lines.*.kp_alloc' => $qty,
        ], [
            'req_date.required' => __('knitting.ystore.v.req_date'), 'req_type.required' => __('knitting.ystore.v.req_type'), 'store_id.required' => __('knitting.ystore.v.store'),
            'lines.required' => __('knitting.ystore.v.req_no_lines'), 'lines.min' => __('knitting.ystore.v.req_no_lines'),
        ]);
        $data['lines'] = YarnStore::inOrder($data['lines']);
        $errors = [];
        foreach ($data['lines'] as $i => $l) {
            if (empty($l['composition_id']) && empty($l['yarn_count_id'])) {
                $errors["lines.{$i}.composition_id"] = __('knitting.yarn.v.line_yarn', ['n' => $i + 1]);
            }
            if ((float) ($l['req_qty'] ?? 0) <= 0) {
                $errors["lines.{$i}.req_qty"] = __('knitting.ystore.v.line_qty', ['n' => $i + 1]);
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function reqHead(array $data): array
    {
        return collect($data)->only(['req_date', 'req_type', 'store_id', 'buyer_id', 'order_id', 'party_id', 'program_no', 'remarks'])->all();
    }

    private function writeReqLines(YarnIssueReq $row, array $lines): void
    {
        foreach ($lines as $i => $l) {
            YarnIssueReqLine::query()->create(collect($l)->only(['composition_id', 'yarn_count_id', 'colour', 'brand', 'remarks'])->all() + [
                'company_id' => $row->company_id, 'req_id' => $row->id, 'sort_order' => $i + 1, 'lot_no' => isset($l['lot_no']) ? (mb_strtoupper(trim($l['lot_no'])) ?: null) : null,
                'uom' => $l['uom'] ?? 'kg', 'req_qty' => (float) $l['req_qty'], 'kp_alloc' => isset($l['kp_alloc']) ? (float) $l['kp_alloc'] : null, 'issued_qty' => 0,
            ]);
        }
    }

    /** @return array{0: array<string, mixed>, 1: YarnIssueReq} */
    private function validatedIssue(Request $request, ?YarnIssue $row): array
    {
        $data = $request->validate([
            'issue_date' => ['required', 'date'], 'req_no' => ['required', 'string'], 'gate_pass_no' => ['nullable', 'string', 'max:40'], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'], 'lines.*.req_line_id' => ['required', 'string'], 'lines.*.lot_id' => ['required', 'string'],
            'lines.*.qty' => ['nullable', 'numeric', 'min:0', 'max:9999999999'], 'lines.*.cones' => ['nullable', 'integer', 'min:0', 'max:99999999'], 'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'issue_date.required' => __('knitting.ystore.v.issue_date'), 'req_no.required' => __('knitting.ystore.v.issue_req'),
            'lines.required' => __('knitting.ystore.v.issue_no_lines'), 'lines.min' => __('knitting.ystore.v.issue_no_lines'),
            'lines.*.req_line_id.required' => __('knitting.ystore.v.issue_line'), 'lines.*.lot_id.required' => __('knitting.ystore.v.issue_lot_pick'),
        ]);
        YarnStore::assertOpen($data['issue_date'], 'issue_date');
        $req = YarnIssueReq::query()->where('req_no', $data['req_no'])->first();
        // A requisition already answered by this issue stays issuable to it.
        if (! $req || (! in_array($req->status, YarnIssueReq::ISSUABLE, true) && ! ($row && $row->req_id === $req->id && $req->status === 'FULLY_ISSUED'))) {
            throw ValidationException::withMessages(['req_no' => __('knitting.ystore.v.issue_req')]);
        }
        $reqLines = YarnIssueReqLine::query()->where('req_id', $req->id)->get()->keyBy('id');
        $mineByLine = $row ? YarnIssueLine::query()->where('issue_id', $row->id)->get()->groupBy('req_line_id')->map(fn ($g) => (float) $g->sum('qty')) : collect();
        // What this issue already took off each lot comes back before it is checked again.
        $mineByLot = $row ? YarnMove::query()->where('kind', 'ISSUE')->where('doc_no', $row->issue_no)->get()->groupBy('lot_id')->map(fn ($g) => -(float) $g->sum('qty')) : collect();
        $data['lines'] = YarnStore::inOrder($data['lines']);
        $byLine = [];
        $byLot = [];
        $errors = [];
        foreach ($data['lines'] as $i => $l) {
            $n = $i + 1;
            $rl = $reqLines->get($l['req_line_id']);
            $lot = YarnLot::query()->whereKey($l['lot_id'])->where('store_id', $req->store_id)->first();
            $qty = (float) ($l['qty'] ?? 0);
            if (! $rl) {
                $errors["lines.{$i}.req_line_id"] = __('knitting.ystore.v.issue_line');

                continue;
            }
            if (! $lot) {
                $errors["lines.{$i}.lot_id"] = __('knitting.ystore.v.issue_lot', ['n' => $n]);

                continue;
            }
            if (($rl->composition_id && $lot->composition_id !== $rl->composition_id) || ($rl->yarn_count_id && $lot->yarn_count_id !== $rl->yarn_count_id)) {
                $errors["lines.{$i}.lot_id"] = __('knitting.ystore.v.issue_lot_match', ['n' => $n]);

                continue;
            }
            if ($qty <= 0) {
                $errors["lines.{$i}.qty"] = __('knitting.ystore.v.line_qty', ['n' => $n]);

                continue;
            }
            $byLine[$rl->id] = ($byLine[$rl->id] ?? 0) + $qty;
            $owed = (float) $rl->req_qty - (float) $rl->issued_qty + (float) ($mineByLine[$rl->id] ?? 0);
            if ($byLine[$rl->id] > $owed + 0.00005) {
                $errors["lines.{$i}.qty"] = __('knitting.ystore.v.issue_over', ['n' => $n, 'left' => round(max(0, $owed), 4)]);
            }
            $byLot[$lot->id] = ($byLot[$lot->id] ?? 0) + $qty;
            $held = YarnStore::balance($lot->id) + (float) ($mineByLot[$lot->id] ?? 0);
            if ($byLot[$lot->id] > $held + 0.00005) {
                $errors["lines.{$i}.qty"] = __('knitting.ystore.v.issue_stock', ['n' => $n, 'left' => round($held, 4)]);
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return [$data, $req];
    }

    private function writeIssueLines(YarnIssue $row, array $lines): void
    {
        $entries = [];
        foreach ($lines as $i => $l) {
            $line = YarnIssueLine::query()->create([
                'company_id' => $row->company_id, 'issue_id' => $row->id, 'sort_order' => $i + 1, 'req_line_id' => $l['req_line_id'], 'lot_id' => $l['lot_id'],
                'qty' => (float) $l['qty'], 'cones' => $l['cones'] ?? null, 'remarks' => $l['remarks'] ?? null,
            ]);
            $rate = (float) YarnLot::query()->whereKey($l['lot_id'])->value('rate');
            $entries[] = ['lot_id' => $l['lot_id'], 'qty' => -(float) $l['qty'], 'value' => -(float) $l['qty'] * $rate, 'doc_line_id' => $line->id];
        }
        YarnStore::post('ISSUE', $row->issue_no, $row->issue_date->toDateString(), $entries);
    }

    /** @return array<string, mixed> */
    private function present(YarnIssueReq $row): array
    {
        $names = YarnProcurement::names();
        $lines = YarnIssueReqLine::query()->where('req_id', $row->id)->orderBy('sort_order')->get();
        $issued = YarnIssue::query()->where('req_id', $row->id)->exists();

        return [
            'req_no' => $row->req_no, 'req_date' => $row->req_date->toDateString(), 'req_type' => $row->req_type, 'store_id' => $row->store_id, 'buyer_id' => $row->buyer_id,
            'order_id' => $row->order_id, 'party_id' => $row->party_id, 'program_no' => $row->program_no, 'remarks' => $row->remarks, 'status' => $row->status,
            'editable' => $row->status === 'OPEN' && ! $issued,
            'lines' => $lines->map(fn (YarnIssueReqLine $l) => [
                'id' => $l->id, 'composition_id' => $l->composition_id, 'yarn_count_id' => $l->yarn_count_id, 'colour' => $l->colour, 'brand' => $l->brand, 'lot_no' => $l->lot_no,
                'uom' => $l->uom, 'req_qty' => (float) $l->req_qty, 'kp_alloc' => $l->kp_alloc, 'issued_qty' => (float) $l->issued_qty,
                'balance' => round(max(0, (float) $l->req_qty - (float) $l->issued_qty), 4), 'remarks' => $l->remarks,
                'label' => trim(implode(' ', array_filter([$names['compositions'][$l->composition_id] ?? null, $names['counts'][$l->yarn_count_id] ?? null, $l->colour]))),
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentIssue(YarnIssue $row): array
    {
        $names = YarnProcurement::names();
        $req = YarnIssueReq::query()->find($row->req_id);

        return [
            'issue_no' => $row->issue_no, 'issue_date' => $row->issue_date->toDateString(), 'req_no' => $req?->req_no, 'store_id' => $row->store_id,
            'gate_pass_no' => $row->gate_pass_no, 'remarks' => $row->remarks, 'editable' => ! YarnIssueReturnLine::query()->whereIn('issue_line_id', YarnIssueLine::query()->where('issue_id', $row->id)->select('id'))->exists(),
            'lines' => YarnIssueLine::query()->where('issue_id', $row->id)->orderBy('sort_order')->get()->map(fn (YarnIssueLine $l) => [
                'id' => $l->id, 'req_line_id' => $l->req_line_id, 'lot_id' => $l->lot_id, 'qty' => (float) $l->qty, 'cones' => $l->cones, 'remarks' => $l->remarks,
                'lot' => YarnStore::describe(YarnLot::query()->findOrFail($l->lot_id), $names)['label'],
            ])->values()->all(),
        ];
    }
}
