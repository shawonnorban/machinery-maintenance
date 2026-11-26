<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Inventory\Models\Store;
use App\Modules\Knitting\Models\YarnIssue;
use App\Modules\Knitting\Models\YarnIssueLine;
use App\Modules\Knitting\Models\YarnIssueReq;
use App\Modules\Knitting\Models\YarnIssueReqLine;
use App\Modules\Knitting\Models\YarnIssueReturn;
use App\Modules\Knitting\Models\YarnIssueReturnLine;
use App\Modules\Knitting\Models\YarnLot;
use App\Modules\Knitting\Models\YarnPartyChallan;
use App\Modules\Knitting\Models\YarnPartyChallanLine;
use App\Modules\Knitting\Models\YarnReplacement;
use App\Modules\Knitting\Models\YarnReplacementLine;
use App\Modules\Knitting\Support\YarnProcurement;
use App\Modules\Knitting\Support\YarnStore;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Knit Production → Yarn Store (AnnotechERP 15/03), the challans that move
 * yarn out and back, as Brickland works them:
 *
 * - Yarn Issue Return against Indent (YIRT-…): unused yarn back off the
 *   floor, each line naming the issue line it left on (or none, an
 *   unmatched return); Draft → Issued → Accepted, when the counted weight
 *   goes back into stock and the issue is credited — or Rejected.
 * - Party-Wise Yarn Issue Challan (YPC-…): yarn out to a sub-contract
 *   party; Issued takes it off the lot, Rejected brings it back, a wrong
 *   one is cancelled, never deleted.
 * - Supplier Yarn Replacement Register (YRP-…): yarn back to a spinner on a
 *   replacement claim; saved as a draft, Submitted (on the lorry), Accepted
 *   (the spinner's signature).
 *
 * Only a draft is corrected. Everything travels by number.
 */
class YarnOutwardApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allowEither('knitting.ystore.view', 'knitting.production.view');
        $nos = fn (string $model, string $col) => $model::query()->orderByDesc($col)->limit(300)->pluck($col)->map(fn ($n) => ['value' => $n, 'label' => $n])->values()->all();

        return ApiResponse::ok(YarnProcurement::options() + [
            'req_types' => YarnStore::REQ_TYPES, 'return_types' => YarnReplacement::TYPES,
            'return_statuses' => YarnIssueReturn::STATUSES, 'challan_statuses' => YarnPartyChallan::STATUSES, 'replacement_statuses' => YarnReplacement::STATUSES,
            'store_codes' => YarnStore::stores()->map(fn ($s) => ['value' => $s->code, 'label' => "{$s->name} ({$s->code})", 'id' => $s->id])->values()->all(),
            'reqs' => YarnIssueReq::query()->whereNotIn('status', ['CANCELLED'])->orderByDesc('req_no')->limit(300)->get(['req_no', 'req_type', 'store_id'])
                ->map(fn ($r) => ['value' => $r->req_no, 'label' => $r->req_no, 'req_type' => $r->req_type, 'store_id' => $r->store_id])->values()->all(),
            'returns' => $nos(YarnIssueReturn::class, 'return_no'), 'challans' => $nos(YarnPartyChallan::class, 'challan_no'), 'replacements' => $nos(YarnReplacement::class, 'challan_no'),
        ]);
    }

    /* ---------------- Yarn Issue Return against Indent ---------------- */

    public function returns(): JsonResponse
    {
        $this->allowEither('knitting.ystore.view', 'knitting.production.view');
        $rows = YarnIssueReturn::query()->orderByDesc('return_date')->orderByDesc('return_no')->limit(500)->get();
        $lines = YarnIssueReturnLine::query()->whereIn('return_id', $rows->pluck('id'))->get()->groupBy('return_id');
        $stores = Store::query()->whereIn('id', $rows->pluck('store_id'))->pluck('name', 'id');
        $company = $this->companyName();

        return ApiResponse::ok($rows->map(fn (YarnIssueReturn $r) => [
            'return_no' => $r->return_no, 'return_date' => $r->return_date->toDateString(), 'req_type' => $r->req_type, 'company' => $company, 'store' => $stores[$r->store_id] ?? null,
            'return_by' => $r->return_by, 'ret' => round((float) ($lines[$r->id] ?? collect())->sum('ret_qty'), 4),
            'recv' => round((float) ($lines[$r->id] ?? collect())->sum(fn ($l) => $l->recv_qty ?? $l->ret_qty), 4), 'status' => $r->status,
        ])->values()->all());
    }

    /** The issue lines a requisition's yarn left on, with what each still has out on the floor. */
    public function returnSource(string $req): JsonResponse
    {
        $this->allowEither('knitting.ystore.view', 'knitting.production.view');
        $row = YarnIssueReq::query()->where('req_no', $req)->firstOrFail();

        return ApiResponse::ok(['req_no' => $row->req_no, 'req_type' => $row->req_type, 'store_id' => $row->store_id, 'issue_lines' => $this->issueLinesOf($row->id)]);
    }

    public function showReturn(string $return): JsonResponse
    {
        $this->allowEither('knitting.ystore.view', 'knitting.production.view');

        return ApiResponse::ok($this->presentReturn($this->find(YarnIssueReturn::class, 'return_no', $return)));
    }

    public function saveReturn(Request $request, ?string $return = null): JsonResponse
    {
        $this->allowEither('knitting.ystore.manage', 'knitting.production.manage');
        $row = $return ? $this->find(YarnIssueReturn::class, 'return_no', $return) : null;
        if ($row) {
            $this->assertStatus($row, 'return_no', ['DRAFT'], 'save', 'ret_status');
        }
        $company = app(TenantContext::class)->companyId();
        $qty = ['nullable', 'numeric', 'min:0', 'max:9999999999'];
        $data = $request->validate([
            'return_date' => ['required', 'date'], 'req_type' => ['required', Rule::in(YarnStore::REQ_TYPES)],
            'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $company)], 'req_no' => ['nullable', 'string'],
            'return_by' => ['required', 'string', 'max:120'], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            ...YarnProcurement::lineRules('lines.*'),
            'lines.*.issue_line_id' => ['nullable', 'string'], 'lines.*.lot_no' => ['nullable', 'string', 'max:40'], 'lines.*.ret_qty' => $qty, 'lines.*.recv_qty' => $qty,
        ], [
            'return_date.required' => __('knitting.ystore.v.challan_date'), 'req_type.required' => __('knitting.ystore.v.req_type'), 'store_id.required' => __('knitting.ystore.v.store'),
            'return_by.required' => __('knitting.ystore.v.return_by'), 'lines.required' => __('knitting.ystore.v.ret_no_lines'), 'lines.min' => __('knitting.ystore.v.ret_no_lines'),
        ]);
        $req = ! empty($data['req_no']) ? YarnIssueReq::query()->where('req_no', $data['req_no'])->first() : null;
        if (! empty($data['req_no']) && ! $req) {
            throw ValidationException::withMessages(['req_no' => __('knitting.ystore.v.issue_req')]);
        }
        $own = $req ? collect($this->issueLinesOf($req->id))->keyBy('issue_line_id') : collect();
        $data['lines'] = YarnStore::inOrder($data['lines']);
        $errors = [];
        foreach ($data['lines'] as $i => &$l) {
            $n = $i + 1;
            if (! empty($l['issue_line_id'])) {
                $il = $own->get($l['issue_line_id']);
                if (! $il) {
                    $errors["lines.{$i}.issue_line_id"] = __('knitting.ystore.v.ret_issue_line', ['n' => $n]);

                    continue;
                }
                // The yarn is the issue line's own.
                $l = ['composition_id' => $il['composition_id'], 'yarn_count_id' => $il['yarn_count_id'], 'colour' => $il['colour'], 'brand' => $il['brand'], 'lot_no' => $il['lot_no'], 'uom' => 'kg'] + $l;
            } elseif (empty($l['composition_id']) && empty($l['yarn_count_id'])) {
                $errors["lines.{$i}.composition_id"] = __('knitting.yarn.v.line_yarn', ['n' => $n]);
            }
            if ((float) ($l['ret_qty'] ?? 0) <= 0) {
                $errors["lines.{$i}.ret_qty"] = __('knitting.ystore.v.line_qty', ['n' => $n]);
            }
        }
        unset($l);
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        $row = DB::transaction(function () use ($row, $data, $req): YarnIssueReturn {
            $head = collect($data)->only(['return_date', 'req_type', 'store_id', 'return_by', 'remarks'])->all() + ['req_id' => $req?->id];
            $row ??= YarnIssueReturn::query()->create($head + [
                'return_no' => YarnStore::nextNumber('YIRT', 'knit_yarn_issue_returns', 'return_no', $data['return_date']), 'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId(),
            ]);
            $row->update($head);
            YarnIssueReturnLine::query()->where('return_id', $row->id)->delete();
            foreach ($data['lines'] as $i => $l) {
                YarnIssueReturnLine::query()->create(collect($l)->only(['issue_line_id', 'composition_id', 'yarn_count_id', 'colour', 'brand', 'remarks'])->all() + [
                    'company_id' => $row->company_id, 'return_id' => $row->id, 'sort_order' => $i + 1, 'lot_no' => isset($l['lot_no']) ? (mb_strtoupper(trim((string) $l['lot_no'])) ?: null) : null,
                    'uom' => $l['uom'] ?? 'kg', 'ret_qty' => (float) $l['ret_qty'], 'recv_qty' => isset($l['recv_qty']) ? (float) $l['recv_qty'] : null,
                ]);
            }

            return $row;
        });

        return $return ? ApiResponse::ok($this->presentReturn($row->refresh())) : ApiResponse::created($this->presentReturn($row));
    }

    /** The register: Issue (sent), Accept (the counted weight back in stock, the issue credited), Reject, Cancel. */
    public function returnTransition(string $return, string $action): JsonResponse
    {
        $this->allowEither('knitting.ystore.manage', 'knitting.production.manage');
        // Accepting puts the yarn back in stock: the store's word, not the floor's.
        if (in_array($action, ['accept', 'reject'], true)) {
            $this->allow('knitting.ystore.manage');
        }
        $row = $this->find(YarnIssueReturn::class, 'return_no', $return);
        [$from, $to] = match ($action) {
            'issue' => [['DRAFT'], 'ISSUED'],
            'accept' => [['ISSUED'], 'ACCEPTED'],
            'reject' => [['ISSUED'], 'REJECTED'],
            'cancel' => [['DRAFT', 'ISSUED'], 'CANCELLED'],
            default => abort(404),
        };
        $this->assertStatus($row, 'return_no', $from, $action, 'ret_status');
        DB::transaction(function () use ($row, $action, $to): void {
            $credit = [];
            if ($action === 'accept') {
                YarnStore::assertOpen($row->return_date->toDateString(), 'return_date');
                $credit = $this->acceptReturn($row);
            }
            $row->update(['status' => $to]);
            // Credited once the challan stands accepted.
            foreach ($credit as $reqId) {
                YarnStore::syncIssueReq($reqId);
            }
        });

        return ApiResponse::ok($this->presentReturn($row->refresh()));
    }

    /* ---------------- Party-Wise Yarn Issue Challan ---------------- */

    public function challans(): JsonResponse
    {
        $this->allow('knitting.ystore.view');
        $rows = YarnPartyChallan::query()->orderByDesc('challan_date')->orderByDesc('challan_no')->limit(500)->get();
        $lines = YarnPartyChallanLine::query()->whereIn('challan_id', $rows->pluck('id'))->get()->groupBy('challan_id');
        $stores = Store::query()->whereIn('id', $rows->pluck('store_id'))->pluck('name', 'id');
        $parties = MaterialSupplier::query()->whereIn('id', $rows->pluck('party_id'))->pluck('name', 'id');
        $company = $this->companyName();

        return ApiResponse::ok($rows->map(fn (YarnPartyChallan $r) => [
            'challan_no' => $r->challan_no, 'challan_date' => $r->challan_date->toDateString(), 'company' => $company, 'party' => $parties[$r->party_id] ?? null,
            'store' => $stores[$r->store_id] ?? null, 'gate_pass_no' => $r->gate_pass_no, 'qty' => round((float) ($lines[$r->id] ?? collect())->sum('qty'), 4), 'status' => $r->status,
        ])->values()->all());
    }

    public function showChallan(string $challan): JsonResponse
    {
        $this->allow('knitting.ystore.view');

        return ApiResponse::ok($this->presentChallan($this->find(YarnPartyChallan::class, 'challan_no', $challan)));
    }

    public function saveChallan(Request $request, ?string $challan = null): JsonResponse
    {
        $this->allow('knitting.ystore.manage');
        $row = $challan ? $this->find(YarnPartyChallan::class, 'challan_no', $challan) : null;
        if ($row) {
            $this->assertStatus($row, 'challan_no', ['DRAFT'], 'save', 'pc_status');
        }
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'challan_date' => ['required', 'date'], 'party_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $company)], 'gate_pass_no' => ['nullable', 'string', 'max:40'],
            'vehicle_no' => ['nullable', 'string', 'max:40'], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'], 'lines.*.lot_id' => ['required', 'string'], 'lines.*.qty' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:9999999999'], 'lines.*.cones' => ['nullable', 'integer', 'min:0', 'max:99999999'], 'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'challan_date.required' => __('knitting.ystore.v.challan_date'), 'party_id.required' => __('knitting.ystore.v.pc_party'), 'store_id.required' => __('knitting.ystore.v.store'),
            'lines.required' => __('knitting.ystore.v.pc_no_lines'), 'lines.min' => __('knitting.ystore.v.pc_no_lines'), 'lines.*.lot_id.required' => __('knitting.ystore.v.issue_lot_pick'),
        ]);
        $data['lines'] = $this->checkLots($data['lines'], $data['store_id'], null);
        $row = DB::transaction(function () use ($row, $data): YarnPartyChallan {
            $head = collect($data)->only(['challan_date', 'party_id', 'store_id', 'gate_pass_no', 'vehicle_no', 'remarks'])->all();
            $row ??= YarnPartyChallan::query()->create($head + [
                'challan_no' => YarnStore::nextNumber('YPC', 'knit_yarn_party_challans', 'challan_no', $data['challan_date']), 'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId(),
            ]);
            $row->update($head);
            YarnPartyChallanLine::query()->where('challan_id', $row->id)->delete();
            foreach ($data['lines'] as $i => $l) {
                YarnPartyChallanLine::query()->create(['company_id' => $row->company_id, 'challan_id' => $row->id, 'sort_order' => $i + 1, 'lot_id' => $l['lot_id'], 'qty' => (float) $l['qty'],
                    'rate' => isset($l['rate']) ? (float) $l['rate'] : null, 'cones' => $l['cones'] ?? null, 'remarks' => $l['remarks'] ?? null]);
            }

            return $row;
        });

        return $challan ? ApiResponse::ok($this->presentChallan($row->refresh())) : ApiResponse::created($this->presentChallan($row));
    }

    /** Issue takes the yarn off its lots; Accept is the party's receipt; Reject and Cancel bring it back. */
    public function challanTransition(string $challan, string $action): JsonResponse
    {
        $this->allow('knitting.ystore.manage');
        $row = $this->find(YarnPartyChallan::class, 'challan_no', $challan);
        [$from, $to] = match ($action) {
            'issue' => [['DRAFT'], 'ISSUED'],
            'accept' => [['ISSUED'], 'ACCEPTED'],
            'reject' => [['ISSUED'], 'REJECTED'],
            'cancel' => [['DRAFT', 'ISSUED'], 'CANCELLED'],
            default => abort(404),
        };
        $this->assertStatus($row, 'challan_no', $from, $action, 'pc_status');
        DB::transaction(function () use ($row, $action, $to): void {
            $lines = YarnPartyChallanLine::query()->where('challan_id', $row->id)->orderBy('sort_order')->get();
            if ($action === 'issue') {
                YarnStore::assertOpen($row->challan_date->toDateString(), 'challan_date');
                $this->checkLots($lines->map(fn ($l) => ['lot_id' => $l->lot_id, 'qty' => $l->qty])->all(), $row->store_id, null);
                YarnStore::post('PARTY_ISSUE', $row->challan_no, $row->challan_date->toDateString(), $lines->map(fn ($l) => [
                    'lot_id' => $l->lot_id, 'qty' => -(float) $l->qty, 'value' => -(float) $l->qty * (float) ($l->rate ?? YarnLot::query()->whereKey($l->lot_id)->value('rate')), 'doc_line_id' => $l->id,
                ])->all());
            }
            if (in_array($action, ['reject', 'cancel'], true) && $row->status === 'ISSUED') {
                YarnStore::assertOpen($row->challan_date->toDateString(), 'challan_date');
                YarnStore::unpost('PARTY_ISSUE', $row->challan_no);
            }
            $row->update(['status' => $to]);
        });

        return ApiResponse::ok($this->presentChallan($row->refresh()));
    }

    /* ---------------- Supplier Yarn Replacement Register ---------------- */

    public function replacements(): JsonResponse
    {
        $this->allow('knitting.ystore.view');
        $rows = YarnReplacement::query()->orderByDesc('challan_date')->orderByDesc('challan_no')->limit(500)->get();
        $lines = YarnReplacementLine::query()->whereIn('replacement_id', $rows->pluck('id'))->get()->groupBy('replacement_id');
        $stores = Store::query()->whereIn('id', $rows->pluck('store_id'))->pluck('name', 'id');
        $suppliers = MaterialSupplier::query()->whereIn('id', $rows->pluck('supplier_id'))->pluck('name', 'id');
        $company = $this->companyName();

        return ApiResponse::ok($rows->map(fn (YarnReplacement $r) => [
            'challan_no' => $r->challan_no, 'challan_date' => $r->challan_date->toDateString(), 'company' => $company, 'store' => $stores[$r->store_id] ?? null,
            'supplier_id' => $r->supplier_id, 'supplier' => $suppliers[$r->supplier_id] ?? null, 'return_type' => $r->return_type, 'lc_no' => $r->lc_no, 'lot_ref' => $r->lot_ref,
            'claim_ref' => $r->claim_ref, 'cartons' => (int) ($lines[$r->id] ?? collect())->sum('cartons'), 'qty' => round((float) ($lines[$r->id] ?? collect())->sum('qty'), 4), 'status' => $r->status,
        ])->values()->all());
    }

    public function showReplacement(string $replacement): JsonResponse
    {
        $this->allow('knitting.ystore.view');

        return ApiResponse::ok($this->presentReplacement($this->find(YarnReplacement::class, 'challan_no', $replacement)));
    }

    public function saveReplacement(Request $request, ?string $replacement = null): JsonResponse
    {
        $this->allow('knitting.ystore.manage');
        $row = $replacement ? $this->find(YarnReplacement::class, 'challan_no', $replacement) : null;
        if ($row) {
            $this->assertStatus($row, 'challan_no', ['DRAFT'], 'save', 'rp_status');
        }
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'challan_date' => ['required', 'date'], 'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $company)],
            'supplier_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)], 'claim_ref' => ['nullable', 'string', 'max:40'],
            'return_type' => ['required', Rule::in(YarnReplacement::TYPES)], 'lc_no' => ['nullable', 'string', 'max:40'], 'lot_ref' => ['nullable', 'string', 'max:40'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'], 'lines.*.lot_id' => ['required', 'string'], 'lines.*.qty' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'lines.*.cartons' => ['nullable', 'integer', 'min:0', 'max:99999999'], 'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'challan_date.required' => __('knitting.ystore.v.challan_date'), 'store_id.required' => __('knitting.ystore.v.store'), 'supplier_id.required' => __('knitting.ystore.v.rp_supplier'),
            'return_type.required' => __('knitting.ystore.v.rp_type'), 'lines.required' => __('knitting.ystore.v.rp_no_lines'), 'lines.min' => __('knitting.ystore.v.rp_no_lines'),
            'lines.*.lot_id.required' => __('knitting.ystore.v.issue_lot_pick'),
        ]);
        $data['lines'] = $this->checkLots($data['lines'], $data['store_id'], $data['supplier_id']);
        $row = DB::transaction(function () use ($row, $data): YarnReplacement {
            $head = collect($data)->only(['challan_date', 'store_id', 'supplier_id', 'claim_ref', 'return_type', 'lc_no', 'remarks'])->all()
                + ['lot_ref' => isset($data['lot_ref']) ? (mb_strtoupper(trim($data['lot_ref'])) ?: null) : null];
            $row ??= YarnReplacement::query()->create($head + [
                'challan_no' => YarnStore::nextNumber('YRP', 'knit_yarn_replacements', 'challan_no', $data['challan_date']), 'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId(),
            ]);
            $row->update($head);
            YarnReplacementLine::query()->where('replacement_id', $row->id)->delete();
            foreach ($data['lines'] as $i => $l) {
                YarnReplacementLine::query()->create(['company_id' => $row->company_id, 'replacement_id' => $row->id, 'sort_order' => $i + 1, 'lot_id' => $l['lot_id'],
                    'cartons' => $l['cartons'] ?? null, 'qty' => (float) $l['qty'], 'remarks' => $l['remarks'] ?? null]);
            }

            return $row;
        });

        return $replacement ? ApiResponse::ok($this->presentReplacement($row->refresh())) : ApiResponse::created($this->presentReplacement($row));
    }

    /** Submit puts the consignment on the lorry (off the lots); Accept is the spinner's signature; Cancel brings it back. */
    public function replacementTransition(string $replacement, string $action): JsonResponse
    {
        $this->allow('knitting.ystore.manage');
        $row = $this->find(YarnReplacement::class, 'challan_no', $replacement);
        [$from, $to] = match ($action) {
            'submit' => [['DRAFT'], 'SUBMITTED'],
            'accept' => [['SUBMITTED'], 'ACCEPTED'],
            'cancel' => [['DRAFT', 'SUBMITTED'], 'CANCELLED'],
            default => abort(404),
        };
        $this->assertStatus($row, 'challan_no', $from, $action, 'rp_status');
        DB::transaction(function () use ($row, $action, $to): void {
            $lines = YarnReplacementLine::query()->where('replacement_id', $row->id)->orderBy('sort_order')->get();
            if ($action === 'submit') {
                YarnStore::assertOpen($row->challan_date->toDateString(), 'challan_date');
                $this->checkLots($lines->map(fn ($l) => ['lot_id' => $l->lot_id, 'qty' => $l->qty])->all(), $row->store_id, $row->supplier_id);
                YarnStore::post('REPLACE', $row->challan_no, $row->challan_date->toDateString(), $lines->map(fn ($l) => [
                    'lot_id' => $l->lot_id, 'qty' => -(float) $l->qty, 'value' => -(float) $l->qty * (float) YarnLot::query()->whereKey($l->lot_id)->value('rate'), 'doc_line_id' => $l->id,
                ])->all());
            }
            if ($action === 'cancel' && $row->status === 'SUBMITTED') {
                YarnStore::assertOpen($row->challan_date->toDateString(), 'challan_date');
                YarnStore::unpost('REPLACE', $row->challan_no);
            }
            $row->update(['status' => $to]);
        });

        return ApiResponse::ok($this->presentReplacement($row->refresh()));
    }

    /* ---------------- helpers ---------------- */

    /**
     * @template T of Model
     *
     * @param  class-string<T>  $model
     * @return T
     */
    private function find(string $model, string $column, string $no): Model
    {
        return $model::query()->where($column, $no)->firstOrFail();
    }

    private function assertStatus(Model $row, string $column, array $from, string $action, string $group): void
    {
        if (! in_array($row->status, $from, true)) {
            throw ValidationException::withMessages(['status' => __('knitting.ystore.v.doc_cannot', [
                'no' => $row->{$column}, 'status' => __("knitting.ystore.{$group}.{$row->status}"), 'action' => __("knitting.ystore.doc_action.{$action}"),
            ])]);
        }
    }

    private function companyName(): ?string
    {
        return DB::table('companies')->where('id', app(TenantContext::class)->companyId())->value('name');
    }

    /**
     * Lines off a store's lots: each lot in the store (and the spinner's own,
     * given one), with a quantity, and never more than the lot holds.
     *
     * @return list<array<string, mixed>>
     */
    private function checkLots(array $lines, string $storeId, ?string $supplierId): array
    {
        $lines = YarnStore::inOrder($lines);
        $errors = [];
        $taking = [];
        foreach ($lines as $i => $l) {
            $n = $i + 1;
            $lot = YarnLot::query()->whereKey($l['lot_id'])->where('store_id', $storeId)->first();
            if (! $lot) {
                $errors["lines.{$i}.lot_id"] = __('knitting.ystore.v.issue_lot', ['n' => $n]);

                continue;
            }
            if ($supplierId && $lot->supplier_id && $lot->supplier_id !== $supplierId) {
                $errors["lines.{$i}.lot_id"] = __('knitting.ystore.v.rp_lot_supplier', ['n' => $n]);

                continue;
            }
            $qty = (float) ($l['qty'] ?? 0);
            if ($qty <= 0) {
                $errors["lines.{$i}.qty"] = __('knitting.ystore.v.line_qty', ['n' => $n]);

                continue;
            }
            $taking[$lot->id] = ($taking[$lot->id] ?? 0) + $qty;
            $held = YarnStore::balance($lot->id);
            if ($taking[$lot->id] > $held + 0.00005) {
                $errors["lines.{$i}.qty"] = __('knitting.ystore.v.issue_stock', ['n' => $n, 'left' => $held]);
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $lines;
    }

    /** @return list<array<string, mixed>> */
    private function issueLinesOf(string $reqId): array
    {
        $names = YarnProcurement::names();
        $issues = YarnIssue::query()->where('req_id', $reqId)->pluck('issue_no', 'id');
        $lines = YarnIssueLine::query()->whereIn('issue_id', $issues->keys())->orderBy('created_at')->orderBy('sort_order')->get();
        $lots = YarnLot::query()->whereIn('id', $lines->pluck('lot_id'))->get()->keyBy('id');

        return $lines->map(function (YarnIssueLine $l) use ($issues, $lots, $names) {
            $lot = $lots[$l->lot_id];
            $back = YarnStore::returnedToStore($l->id);

            return [
                'issue_line_id' => $l->id, 'issue_no' => $issues[$l->issue_id], 'composition_id' => $lot->composition_id, 'yarn_count_id' => $lot->yarn_count_id,
                'colour' => $lot->colour, 'brand' => $lot->brand, 'lot_no' => $lot->lot_no, 'issued' => (float) $l->qty, 'returned' => $back,
                'out' => round(max(0, (float) $l->qty - $back), 4),
                'label' => $issues[$l->issue_id].' · '.YarnStore::describe($lot, $names)['label'],
            ];
        })->values()->all();
    }

    /**
     * Accepting a return: each line's counted weight back onto its issue
     * line's lot (or the matching stock line).
     *
     * @return list<string> the requisitions whose issues it credits
     */
    private function acceptReturn(YarnIssueReturn $row): array
    {
        $lines = YarnIssueReturnLine::query()->where('return_id', $row->id)->orderBy('sort_order')->get();
        $entries = [];
        $errors = [];
        foreach ($lines as $i => $l) {
            $recv = (float) ($l->recv_qty ?? $l->ret_qty);
            if ($l->issue_line_id) {
                $il = YarnIssueLine::query()->find($l->issue_line_id);
                $out = $il ? (float) $il->qty - YarnStore::returnedToStore($il->id) : 0;
                if (! $il || $recv > $out + 0.00005) {
                    $errors["lines.{$i}.recv_qty"] = __('knitting.ystore.v.ret_over', ['n' => $i + 1, 'left' => round(max(0, $out), 4)]);

                    continue;
                }
                $lotId = $il->lot_id;
            } else {
                // An unmatched return credits nothing; its yarn goes to the store's own buffer line.
                $lotId = YarnStore::lotFor([
                    'store_id' => $row->store_id, 'composition_id' => $l->composition_id, 'yarn_count_id' => $l->yarn_count_id, 'colour' => $l->colour, 'brand' => $l->brand,
                    'lot_no' => $l->lot_no, 'is_buffer' => true, 'uom' => $l->uom,
                ], $row->return_date->toDateString())->id;
            }
            $l->update(['recv_qty' => $recv, 'lot_id' => $lotId]);
            $entries[] = ['lot_id' => $lotId, 'qty' => $recv, 'value' => $recv * (float) YarnLot::query()->whereKey($lotId)->value('rate'), 'doc_line_id' => $l->id];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        YarnStore::post('ISSUE_RETURN', $row->return_no, $row->return_date->toDateString(), $entries);

        return YarnIssueReqLine::query()->whereIn('id', YarnIssueLine::query()->whereIn('id', $lines->pluck('issue_line_id')->filter())->select('req_line_id'))->distinct()->pluck('req_id')->all();
    }

    /** @return array<string, mixed> */
    private function presentReturn(YarnIssueReturn $row): array
    {
        $names = YarnProcurement::names();
        $req = $row->req_id ? YarnIssueReq::query()->find($row->req_id) : null;

        return [
            'return_no' => $row->return_no, 'return_date' => $row->return_date->toDateString(), 'req_type' => $row->req_type, 'store_id' => $row->store_id,
            'req_no' => $req?->req_no, 'return_by' => $row->return_by, 'remarks' => $row->remarks, 'status' => $row->status, 'editable' => $row->status === 'DRAFT',
            'issue_lines' => $req ? $this->issueLinesOf($req->id) : [],
            'lines' => YarnIssueReturnLine::query()->where('return_id', $row->id)->orderBy('sort_order')->get()->map(fn (YarnIssueReturnLine $l) => [
                'issue_line_id' => $l->issue_line_id, 'composition_id' => $l->composition_id, 'yarn_count_id' => $l->yarn_count_id, 'colour' => $l->colour, 'brand' => $l->brand,
                'lot_no' => $l->lot_no, 'uom' => $l->uom, 'ret_qty' => (float) $l->ret_qty, 'recv_qty' => $l->recv_qty, 'remarks' => $l->remarks,
                'yarn' => trim(implode(' ', array_filter([$names['compositions'][$l->composition_id] ?? null, $names['counts'][$l->yarn_count_id] ?? null, $l->colour]))),
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function lotLines(string $model, string $fk, string $id, array $extra): array
    {
        $names = YarnProcurement::names();

        return $model::query()->where($fk, $id)->orderBy('sort_order')->get()->map(function ($l) use ($names, $extra) {
            $lot = YarnLot::query()->findOrFail($l->lot_id);

            return ['lot_id' => $l->lot_id, 'qty' => (float) $l->qty, 'remarks' => $l->remarks, 'lot' => YarnStore::describe($lot, $names)['label'], 'balance' => YarnStore::balance($lot->id)]
                + collect($extra)->mapWithKeys(fn ($k) => [$k => $l->{$k}])->all();
        })->values()->all();
    }

    /** @return array<string, mixed> */
    private function presentChallan(YarnPartyChallan $row): array
    {
        return [
            'challan_no' => $row->challan_no, 'challan_date' => $row->challan_date->toDateString(), 'party_id' => $row->party_id, 'store_id' => $row->store_id,
            'gate_pass_no' => $row->gate_pass_no, 'vehicle_no' => $row->vehicle_no, 'remarks' => $row->remarks, 'status' => $row->status, 'editable' => $row->status === 'DRAFT',
            'lines' => $this->lotLines(YarnPartyChallanLine::class, 'challan_id', $row->id, ['rate', 'cones']),
        ];
    }

    /** @return array<string, mixed> */
    private function presentReplacement(YarnReplacement $row): array
    {
        return [
            'challan_no' => $row->challan_no, 'challan_date' => $row->challan_date->toDateString(), 'store_id' => $row->store_id, 'supplier_id' => $row->supplier_id,
            'claim_ref' => $row->claim_ref, 'return_type' => $row->return_type, 'lc_no' => $row->lc_no, 'lot_ref' => $row->lot_ref, 'remarks' => $row->remarks,
            'status' => $row->status, 'editable' => $row->status === 'DRAFT',
            'lines' => $this->lotLines(YarnReplacementLine::class, 'replacement_id', $row->id, ['cartons']),
        ];
    }
}
