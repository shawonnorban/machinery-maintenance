<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\Knitting\Models\KnitNeedleAssignment;
use App\Modules\Knitting\Models\KnitNeedleBroken;
use App\Modules\Knitting\Models\KnitNeedleBrokenLine;
use App\Modules\Knitting\Models\KnitNeedleIssue;
use App\Modules\Knitting\Models\KnitNeedleIssueLine;
use App\Modules\Knitting\Models\KnitNeedleLoanReq;
use App\Modules\Knitting\Models\KnitNeedleLoanReqLine;
use App\Modules\Knitting\Models\KnitNeedleLoanReturn;
use App\Modules\Knitting\Models\KnitNeedleReq;
use App\Modules\Knitting\Models\KnitNeedleReqLine;
use App\Modules\Knitting\Support\KnitProduction;
use App\Modules\Knitting\Support\NeedleStore;
use App\Modules\Knitting\Support\YarnStore;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Knit Production → Needle Store (AnnotechERP 15/07), the floor's side, as
 * Brickland works it:
 *
 * - Machine-Wise Needle Assignment: a unit's machines with the item they
 *   are fitted with per category; Save & Finalize locks the fit.
 * - Machine-Wise Broken Needle (NBR-…): a shift's breakage per machine;
 *   Confirm is the supervisor signing the count.
 * - Machine-Wise Needle Indent (NRQ-…): against a confirmed breakage record;
 *   Submit → Verify (or Submit & Verify), then Partly Issued / Issued as the
 *   issue desk answers it; Close what is left, Cancel while nothing is out.
 * - Machine-Wise Needle Issue (NIS-…): always answers a verified indent, no
 *   more than is owing nor than the releasing store holds; saving posts it
 *   (out of the store asked, into the store asking) and Withdraw takes it back.
 * - Needle Loan Indent (NLR-…): needles lent to a party or sent back; the
 *   same standing, and once verified only Issue Qty is written, posted out
 *   of the store as it is.
 */
class KnitNeedleFloorApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');

        return ApiResponse::ok(NeedleStore::options() + [
            'broken_statuses' => KnitNeedleBroken::STATUSES, 'req_statuses' => KnitNeedleReq::STATUSES, 'reasons' => KnitNeedleReq::REASONS,
            'loan_kinds' => KnitNeedleLoanReq::KINDS, 'loan_return_statuses' => KnitNeedleLoanReturn::STATUSES, 'issue_statuses' => KnitNeedleIssue::STATUSES,
            'confirmed' => KnitNeedleBroken::query()->where('status', 'CONFIRMED')->orderByDesc('broken_date')->limit(300)->get()
                ->map(fn (KnitNeedleBroken $b) => ['value' => $b->ref_no, 'label' => NeedleStore::brokenLabel($b), 'office_id' => $b->office_id])->values()->all(),
        ]);
    }

    /* ---------------- assignment ---------------- */

    public function assignments(): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');
        $rows = KnitNeedleAssignment::query()->groupBy('office_id', 'category_id')
            ->selectRaw('office_id, category_id, count(*) as n, sum(qty) as q, bool_and(finalized) as fin, max(updated_at) as at')->get();
        $offices = DB::table('factories')->whereIn('id', $rows->pluck('office_id'))->pluck('name', 'id');
        $cats = DB::table('item_categories')->whereIn('id', $rows->pluck('category_id'))->pluck('name', 'id');

        return ApiResponse::ok($rows->map(fn ($r) => ['office_id' => $r->office_id, 'office' => $offices[$r->office_id] ?? null, 'category_id' => $r->category_id,
            'category' => $cats[$r->category_id] ?? null, 'machines' => (int) $r->n, 'qty' => round((float) $r->q, 3), 'finalized' => (bool) $r->fin,
            'updated_at' => (string) $r->at])->values()->all());
    }

    public function assignmentSheet(Request $request): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');
        $f = $this->sheetInput($request);

        return ApiResponse::ok($this->fits($f['office_id'], $f['category_id']));
    }

    public function saveAssignment(Request $request): JsonResponse
    {
        $this->allow('knitting.needle.manage');
        $company = app(TenantContext::class)->companyId();
        $f = $this->sheetInput($request);
        $data = $request->validate(['finalize' => ['boolean'], 'rows' => ['present', 'array', 'max:1000'], 'rows.*.machine_id' => ['required', 'string'],
            'rows.*.item_id' => ['nullable', Rule::exists('gs_items', 'id')->where('company_id', $company)], 'rows.*.qty' => ['nullable', 'numeric', 'min:0', 'max:99999999']]);
        $sheet = KnitNeedleAssignment::query()->where('office_id', $f['office_id'])->where('category_id', $f['category_id']);
        if ((clone $sheet)->where('finalized', true)->exists()) {
            throw ValidationException::withMessages(['category_id' => __('knitting.nd.v.fit_final')]);
        }
        $machines = NeedleStore::machineOffices()->filter(fn ($o) => $o === $f['office_id'])->keys()->all();
        $inCat = DB::table('gs_items')->where('gs_category_id', $f['category_id'])->pluck('id')->all();
        DB::transaction(function () use ($data, $f, $sheet, $machines, $inCat): void {
            foreach (YarnStore::inOrder($data['rows']) as $i => $r) {
                if (! in_array($r['machine_id'], $machines, true)) {
                    throw ValidationException::withMessages(["rows.{$i}.machine_id" => __('knitting.nd.v.fit_machine', ['n' => $i + 1])]);
                }
                $row = (clone $sheet)->where('machine_id', $r['machine_id'])->first();
                $qty = round((float) ($r['qty'] ?? 0), 4);
                // Clearing the box is how a fit is taken off.
                if (empty($r['item_id']) || $qty <= 0) {
                    $row?->delete();

                    continue;
                }
                if (! in_array($r['item_id'], $inCat, true)) {
                    throw ValidationException::withMessages(["rows.{$i}.item_id" => __('knitting.nd.v.fit_item', ['n' => $i + 1])]);
                }
                $vals = ['item_id' => $r['item_id'], 'qty' => $qty];
                $row ? $row->update($vals) : KnitNeedleAssignment::query()->create($vals + ['office_id' => $f['office_id'], 'category_id' => $f['category_id'], 'machine_id' => $r['machine_id']]);
            }
            if (! empty($data['finalize'])) {
                if (! (clone $sheet)->exists()) {
                    throw ValidationException::withMessages(['rows' => __('knitting.nd.v.fit_empty')]);
                }
                (clone $sheet)->update(['finalized' => true]);
            }
        });

        return ApiResponse::ok($this->fits($f['office_id'], $f['category_id']));
    }

    /* ---------------- broken needles ---------------- */

    public function brokens(): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');
        $rows = KnitNeedleBroken::query()->orderByDesc('broken_date')->orderByDesc('ref_no')->limit(500)->get();
        $sums = KnitNeedleBrokenLine::query()->whereIn('broken_id', $rows->pluck('id'))->groupBy('broken_id')
            ->selectRaw('broken_id, count(distinct machine_id) as m, sum(qty) as q, sum(amount) as a')->get()->keyBy('broken_id');
        $shifts = DB::table('hr_shifts')->whereIn('id', $rows->pluck('shift_id'))->pluck('name', 'id');
        $offices = DB::table('factories')->whereIn('id', $rows->pluck('office_id')->filter())->pluck('name', 'id');
        $types = DB::table('knit_ref_entries')->whereIn('id', $rows->pluck('machine_type_id')->filter())->pluck('name', 'id');

        return ApiResponse::ok($rows->map(fn (KnitNeedleBroken $b) => [
            'ref_no' => $b->ref_no, 'broken_date' => $b->broken_date->toDateString(), 'shift_id' => $b->shift_id, 'shift' => $shifts[$b->shift_id] ?? null,
            'office_id' => $b->office_id, 'office' => $offices[$b->office_id] ?? null, 'machine_type_id' => $b->machine_type_id, 'machine_type' => $types[$b->machine_type_id] ?? null,
            'machines' => (int) ($sums[$b->id]->m ?? 0), 'qty' => round((float) ($sums[$b->id]->q ?? 0), 3), 'amount' => round((float) ($sums[$b->id]->a ?? 0), 2), 'status' => $b->status,
        ])->values()->all());
    }

    public function showBroken(string $no): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');

        return ApiResponse::ok($this->presentBroken(KnitNeedleBroken::query()->where('ref_no', $no)->firstOrFail()));
    }

    public function saveBroken(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.needle.manage');
        $row = $no ? KnitNeedleBroken::query()->where('ref_no', $no)->firstOrFail() : null;
        $row && $this->assertStatus($row->ref_no, $row->status, ['DRAFT'], 'save', 'broken_status');
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'broken_date' => ['required', 'date'], 'shift_id' => ['required', Rule::exists('hr_shifts', 'id')->where('company_id', $company)],
            'office_id' => ['nullable', Rule::exists('factories', 'id')->where('company_id', $company)],
            'machine_type_id' => ['nullable', Rule::exists('knit_ref_entries', 'id')->where('company_id', $company)], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:300'], 'lines.*.machine_id' => ['required', Rule::exists('knit_machines', 'id')->where('company_id', $company)],
            'lines.*.item_id' => ['required', Rule::exists('gs_items', 'id')->where('company_id', $company)], 'lines.*.uom' => ['nullable', 'string', 'max:16', UomCodes::rule($company)],
            'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'lines.*.cost_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'broken_date.required' => __('knitting.nd.v.date'), 'shift_id.required' => __('knitting.nd.v.shift'), 'lines.*.machine_id.required' => __('knitting.nd.v.line_machine'),
            'lines.*.item_id.required' => __('knitting.nd.v.line_item'), 'lines.*.qty.required' => __('knitting.nd.v.line_qty'), 'lines.*.qty.gt' => __('knitting.nd.v.line_qty'),
        ]);
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('knitting.nd.v.no_broken_lines')]);
        }
        $this->assertMachines($lines, $data['office_id'] ?? null);
        $row = DB::transaction(function () use ($row, $data, $lines): KnitNeedleBroken {
            $head = collect($data)->except('lines')->map(fn ($v) => $v === '' ? null : $v)->all() + ['office_id' => null, 'machine_type_id' => null, 'remarks' => null];
            $row ??= KnitNeedleBroken::query()->create($head + ['ref_no' => KnitProduction::nextNumber('NBR', 'knit_needle_brokens', 'ref_no', $data['broken_date']),
                'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            $row->update($head);
            KnitNeedleBrokenLine::query()->where('broken_id', $row->id)->delete();
            foreach ($lines as $i => $l) {
                $qty = round((float) $l['qty'], 4);
                $cost = round((float) ($l['cost_price'] ?? 0), 4);
                KnitNeedleBrokenLine::query()->create(['company_id' => $row->company_id, 'broken_id' => $row->id, 'sort_order' => $i + 1, 'machine_id' => $l['machine_id'],
                    'item_id' => $l['item_id'], 'uom' => UomCodes::normalize($l['uom'] ?? null) ?: null, 'qty' => $qty, 'cost_price' => $cost, 'amount' => round($qty * $cost, 4),
                    'remarks' => ($l['remarks'] ?? '') ?: null]);
            }

            return $row;
        });

        return $no ? ApiResponse::ok($this->presentBroken($row->refresh())) : ApiResponse::created($this->presentBroken($row->refresh()));
    }

    public function brokenTransition(string $no, string $action): JsonResponse
    {
        $this->allow('knitting.needle.manage');
        $row = KnitNeedleBroken::query()->where('ref_no', $no)->firstOrFail();
        [$from, $to] = match ($action) {
            'confirm' => [['DRAFT'], 'CONFIRMED'],
            'cancel' => [['DRAFT', 'CONFIRMED'], 'CANCELLED'],
            default => abort(404),
        };
        $this->assertStatus($row->ref_no, $row->status, $from, $action, 'broken_status');
        if ($action === 'cancel' && KnitNeedleReq::query()->where('broken_id', $row->id)->where('status', '!=', 'CANCELLED')->exists()) {
            throw ValidationException::withMessages(['status' => __('knitting.nd.v.broken_used', ['no' => $row->ref_no])]);
        }
        $row->update(['status' => $to]);

        return ApiResponse::ok($this->presentBroken($row->refresh()));
    }

    /* ---------------- machine-wise indents ---------------- */

    public function reqs(): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');
        $rows = KnitNeedleReq::query()->orderByDesc('req_date')->orderByDesc('req_no')->limit(500)->get();

        return ApiResponse::ok($this->reqRows($rows));
    }

    public function showReq(string $no): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');

        return ApiResponse::ok($this->presentReq(KnitNeedleReq::query()->where('req_no', $no)->firstOrFail()));
    }

    public function saveReq(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.needle.manage');
        $row = $no ? KnitNeedleReq::query()->where('req_no', $no)->firstOrFail() : null;
        $row && $this->assertStatus($row->req_no, $row->status, ['DRAFT'], 'save', 'req_status');
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'req_date' => ['required', 'date'], 'reason' => ['required', Rule::in(KnitNeedleReq::REASONS)], 'broken_no' => ['nullable', 'string', 'max:24'],
            'office_id' => ['nullable', Rule::exists('factories', 'id')->where('company_id', $company)],
            'from_store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $company)],
            'to_store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $company)], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:300'], 'lines.*.machine_id' => ['nullable', Rule::exists('knit_machines', 'id')->where('company_id', $company)],
            'lines.*.item_id' => ['required', Rule::exists('gs_items', 'id')->where('company_id', $company)], 'lines.*.uom' => ['nullable', 'string', 'max:16', UomCodes::rule($company)],
            'lines.*.req_qty' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'req_date.required' => __('knitting.nd.v.req_date'), 'reason.required' => __('knitting.nd.v.reason'), 'from_store_id.required' => __('knitting.nd.v.from_store'),
            'to_store_id.required' => __('knitting.nd.v.to_store'), 'lines.*.item_id.required' => __('knitting.nd.v.line_item'),
            'lines.*.req_qty.required' => __('knitting.nd.v.line_qty'), 'lines.*.req_qty.gt' => __('knitting.nd.v.line_qty'),
        ]);
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('knitting.nd.v.no_req_lines')]);
        }
        $broken = null;
        if (! empty($data['broken_no'])) {
            $broken = KnitNeedleBroken::query()->where('ref_no', $data['broken_no'])->where('status', 'CONFIRMED')->first();
            if (! $broken) {
                throw ValidationException::withMessages(['broken_no' => __('knitting.nd.v.broken_confirmed')]);
            }
        }
        $this->assertMachines(array_filter($lines, fn ($l) => ! empty($l['machine_id'])), $data['office_id'] ?? null);
        $row = DB::transaction(function () use ($row, $data, $lines, $broken): KnitNeedleReq {
            $head = collect($data)->except(['lines', 'broken_no'])->map(fn ($v) => $v === '' ? null : $v)->all() + ['office_id' => null, 'remarks' => null, 'broken_id' => $broken?->id];
            $row ??= KnitNeedleReq::query()->create($head + ['req_no' => KnitProduction::nextNumber('NRQ', 'knit_needle_reqs', 'req_no', $data['req_date']),
                'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            $row->update($head);
            KnitNeedleReqLine::query()->where('req_id', $row->id)->delete();
            foreach ($lines as $i => $l) {
                KnitNeedleReqLine::query()->create(['company_id' => $row->company_id, 'req_id' => $row->id, 'sort_order' => $i + 1, 'machine_id' => ($l['machine_id'] ?? '') ?: null,
                    'item_id' => $l['item_id'], 'uom' => UomCodes::normalize($l['uom'] ?? null) ?: null, 'req_qty' => round((float) $l['req_qty'], 4),
                    'rate' => round((float) ($l['rate'] ?? 0), 4), 'remarks' => ($l['remarks'] ?? '') ?: null]);
            }

            return $row;
        });

        return $no ? ApiResponse::ok($this->presentReq($row->refresh())) : ApiResponse::created($this->presentReq($row->refresh()));
    }

    public function reqTransition(string $no, string $action): JsonResponse
    {
        $this->allow('knitting.needle.manage');
        $row = KnitNeedleReq::query()->where('req_no', $no)->firstOrFail();
        [$from, $to] = $this->standing($action);
        $this->assertStatus($row->req_no, $row->status, $from, $action, 'req_status');
        if ($action === 'cancel' && NeedleStore::issued(KnitNeedleReqLine::query()->where('req_id', $row->id)->pluck('id'))->sum() > 0) {
            throw ValidationException::withMessages(['status' => __('knitting.nd.v.req_issued', ['no' => $row->req_no])]);
        }
        $row->update(['status' => $to] + (in_array($action, ['verify', 'submit-verify'], true) ? ['verified_at' => now(), 'verified_by' => $this->caller()->auditUserId()] : []));

        return ApiResponse::ok($this->presentReq($row->refresh()));
    }

    /* ---------------- machine-wise issues ---------------- */

    public function issues(): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');
        $rows = KnitNeedleIssue::query()->orderByDesc('issue_date')->orderByDesc('issue_no')->limit(500)->get();
        $sums = KnitNeedleIssueLine::query()->whereIn('issue_id', $rows->pluck('id'))->groupBy('issue_id')->selectRaw('issue_id, count(*) as n, sum(qty) as q')->get()->keyBy('issue_id');
        $reqs = KnitNeedleReq::query()->whereIn('id', $rows->pluck('req_id'))->get()->keyBy('id');
        [, $stores] = NeedleStore::names();

        return ApiResponse::ok($rows->map(fn (KnitNeedleIssue $s) => [
            'issue_no' => $s->issue_no, 'issue_date' => $s->issue_date->toDateString(), 'req_no' => $reqs[$s->req_id]->req_no ?? null,
            'req_status' => $reqs[$s->req_id]->status ?? null, 'from_store_id' => $s->from_store_id, 'from_store' => $stores[$s->from_store_id] ?? null,
            'to_store_id' => $s->to_store_id, 'to_store' => $stores[$s->to_store_id] ?? null, 'lines' => (int) ($sums[$s->id]->n ?? 0),
            'qty' => round((float) ($sums[$s->id]->q ?? 0), 3), 'status' => $s->status,
        ])->values()->all());
    }

    /** The Requisition List beside the issue desk: verified indents still owing, line by line. */
    public function owing(): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');
        $rows = KnitNeedleReq::query()->whereIn('status', KnitNeedleReq::ISSUABLE)->orderBy('req_date')->get();

        return ApiResponse::ok(collect($this->reqRows($rows))->map(fn ($r) => $r + ['owing_lines' => $this->owingLines(KnitNeedleReq::query()->where('req_no', $r['req_no'])->first())])
            ->filter(fn ($r) => $r['balance'] > 0)->values()->all());
    }

    public function showIssue(string $no): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');

        return ApiResponse::ok($this->presentIssue(KnitNeedleIssue::query()->where('issue_no', $no)->firstOrFail()));
    }

    public function saveIssue(Request $request): JsonResponse
    {
        $this->allow('knitting.needle.manage');
        $data = $request->validate([
            'issue_date' => ['required', 'date'], 'req_no' => ['required', 'string', 'max:24'], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:300'], 'lines.*.req_line_id' => ['required', 'string'], 'lines.*.qty' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], ['issue_date.required' => __('knitting.nd.v.issue_date'), 'req_no.required' => __('knitting.nd.v.issue_req')]);
        $req = KnitNeedleReq::query()->where('req_no', $data['req_no'])->first();
        if (! $req || ! in_array($req->status, KnitNeedleReq::ISSUABLE, true)) {
            throw ValidationException::withMessages(['req_no' => __('knitting.nd.v.issue_req')]);
        }
        $date = $data['issue_date'];
        NeedleStore::assertOpen($req->to_store_id, $date, 'issue_date');
        NeedleStore::assertOpen($req->from_store_id, $date, 'issue_date');
        $mine = KnitNeedleReqLine::query()->where('req_id', $req->id)->get()->keyBy('id');
        $had = NeedleStore::issued($mine->keys());
        [$items] = NeedleStore::names();
        $out = [];
        $drawing = [];
        foreach (YarnStore::inOrder($data['lines']) as $i => $l) {
            $line = $mine[$l['req_line_id']] ?? null;
            if (! $line) {
                throw ValidationException::withMessages(["lines.{$i}.req_line_id" => __('knitting.nd.v.issue_line', ['n' => $i + 1])]);
            }
            $qty = round((float) ($l['qty'] ?? 0), 4);
            // A line set to zero is left owing.
            if ($qty <= 0) {
                continue;
            }
            $left = round($line->req_qty - (float) ($had[$line->id] ?? 0), 4);
            if ($qty > $left + 0.00005) {
                throw ValidationException::withMessages(["lines.{$i}.qty" => __('knitting.nd.v.issue_over', ['n' => $i + 1, 'left' => $left])]);
            }
            $drawing[$line->item_id] = ($drawing[$line->item_id] ?? 0) + $qty;
            $have = NeedleStore::balance($req->to_store_id, $line->item_id);
            if ($drawing[$line->item_id] > $have + 0.00005) {
                throw ValidationException::withMessages(["lines.{$i}.qty" => __('knitting.nd.v.issue_stock', ['n' => $i + 1, 'item' => $items[$line->item_id] ?? '', 'have' => $have])]);
            }
            $out[] = ['line' => $line, 'qty' => $qty, 'remarks' => ($l['remarks'] ?? '') ?: null];
        }
        if ($out === []) {
            throw ValidationException::withMessages(['lines' => __('knitting.nd.v.issue_none')]);
        }
        $issue = DB::transaction(function () use ($req, $data, $out, $date): KnitNeedleIssue {
            $issue = KnitNeedleIssue::query()->create(['issue_no' => KnitProduction::nextNumber('NIS', 'knit_needle_issues', 'issue_no', $date), 'issue_date' => $date,
                'req_id' => $req->id, 'from_store_id' => $req->from_store_id, 'to_store_id' => $req->to_store_id, 'remarks' => ($data['remarks'] ?? '') ?: null,
                'status' => 'ISSUED', 'created_by' => $this->caller()->auditUserId()]);
            $outMoves = [];
            $inMoves = [];
            foreach ($out as $i => $o) {
                $l = KnitNeedleIssueLine::query()->create(['company_id' => $issue->company_id, 'issue_id' => $issue->id, 'sort_order' => $i + 1, 'req_line_id' => $o['line']->id,
                    'qty' => $o['qty'], 'remarks' => $o['remarks']]);
                $outMoves[] = ['store_id' => $req->to_store_id, 'item_id' => $o['line']->item_id, 'qty' => -$o['qty'], 'value' => -$o['qty'] * $o['line']->rate, 'doc_line_id' => $l->id];
                $inMoves[] = ['store_id' => $req->from_store_id, 'item_id' => $o['line']->item_id, 'qty' => $o['qty'], 'value' => $o['qty'] * $o['line']->rate, 'doc_line_id' => $l->id];
            }
            // Out of the store asked; into the store asking, when it is another one.
            NeedleStore::post('ISSUE_OUT', $issue->issue_no, $date, $outMoves);
            NeedleStore::post('ISSUE_IN', $issue->issue_no, $date, $req->from_store_id !== $req->to_store_id ? $inMoves : []);
            NeedleStore::syncReq($req);

            return $issue;
        });

        return ApiResponse::created($this->presentIssue($issue->refresh()));
    }

    public function withdrawIssue(string $no): JsonResponse
    {
        $this->allow('knitting.needle.manage');
        $issue = KnitNeedleIssue::query()->where('issue_no', $no)->firstOrFail();
        if ($issue->status !== 'ISSUED') {
            throw ValidationException::withMessages(['status' => __('knitting.nd.v.cannot', ['no' => $no, 'status' => __("knitting.nd.issue_status.{$issue->status}"),
                'action' => __('knitting.nd.action.withdraw')])]);
        }
        $date = $issue->issue_date->toDateString();
        NeedleStore::assertOpen($issue->to_store_id, $date, 'status');
        NeedleStore::assertOpen($issue->from_store_id, $date, 'status');
        $req = KnitNeedleReq::query()->findOrFail($issue->req_id);
        if (! in_array($req->status, ['VERIFIED', 'PARTLY_ISSUED', 'ISSUED'], true)) {
            throw ValidationException::withMessages(['status' => __('knitting.nd.v.withdraw_req', ['no' => $req->req_no])]);
        }
        DB::transaction(function () use ($issue, $req): void {
            NeedleStore::unpost('ISSUE_OUT', $issue->issue_no);
            NeedleStore::unpost('ISSUE_IN', $issue->issue_no);
            $issue->update(['status' => 'WITHDRAWN']);
            NeedleStore::syncReq($req);
        });

        return ApiResponse::ok($this->presentIssue($issue->refresh()));
    }

    /* ---------------- loan indents ---------------- */

    public function loanReqs(Request $request): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');
        $f = $request->validate(['book' => ['nullable', Rule::in(NeedleStore::BOOKS)], 'kind' => ['nullable', Rule::in(KnitNeedleLoanReq::KINDS)]]);
        $rows = KnitNeedleLoanReq::query()->where('book', $f['book'] ?? 'NEEDLE')->when($f['kind'] ?? null, fn ($q, $k) => $q->where('kind', $k))
            ->orderByDesc('req_date')->orderByDesc('req_no')->limit(500)->get();
        $sums = KnitNeedleLoanReqLine::query()->whereIn('req_id', $rows->pluck('id'))->groupBy('req_id')->selectRaw('req_id, count(*) as n, sum(required_qty) as r, sum(issue_qty) as i')->get()->keyBy('req_id');
        $lines = KnitNeedleLoanReqLine::query()->whereIn('req_id', $rows->pluck('id'))->get(['id', 'req_id']);
        $back = NeedleStore::loanReturned($lines->pluck('id'));
        $backBy = $lines->groupBy('req_id')->map(fn ($ls) => round((float) $ls->sum(fn ($l) => $back[$l->id] ?? 0), 3));
        $parties = DB::table('mp_suppliers')->whereIn('id', $rows->pluck('party_id'))->pluck('name', 'id');
        [, $stores] = NeedleStore::names();

        return ApiResponse::ok($rows->map(fn (KnitNeedleLoanReq $r) => [
            'req_no' => $r->req_no, 'req_date' => $r->req_date->toDateString(), 'kind' => $r->kind, 'party_id' => $r->party_id, 'party' => $parties[$r->party_id] ?? null,
            'store_id' => $r->store_id, 'store' => $stores[$r->store_id] ?? null, 'reason' => $r->reason, 'lines' => (int) ($sums[$r->id]->n ?? 0),
            'required' => round((float) ($sums[$r->id]->r ?? 0), 3), 'issued' => round((float) ($sums[$r->id]->i ?? 0), 3),
            'balance' => round((float) ($sums[$r->id]->r ?? 0) - (float) ($sums[$r->id]->i ?? 0), 3), 'returned' => $backBy[$r->id] ?? 0.0,
            'owes' => round((float) ($sums[$r->id]->i ?? 0) - ($backBy[$r->id] ?? 0), 3), 'status' => $r->status,
        ])->values()->all());
    }

    public function showLoanReq(string $no): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');

        return ApiResponse::ok($this->presentLoan(KnitNeedleLoanReq::query()->where('req_no', $no)->firstOrFail()));
    }

    public function saveLoanReq(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.needle.manage');
        $row = $no ? KnitNeedleLoanReq::query()->where('req_no', $no)->firstOrFail() : null;
        if ($row && in_array($row->status, ['VERIFIED', 'PARTLY_ISSUED', 'ISSUED'], true)) {
            return $this->writeIssueQty($request, $row);
        }
        $row && $this->assertStatus($row->req_no, $row->status, ['DRAFT'], 'save', 'req_status');
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'book' => ['nullable', Rule::in(NeedleStore::BOOKS)],
            'req_date' => ['required', 'date'], 'kind' => ['required', Rule::in(KnitNeedleLoanReq::KINDS)], 'req_type' => ['nullable', Rule::in(YarnStore::REQ_TYPES)],
            'office_id' => ['nullable', Rule::exists('factories', 'id')->where('company_id', $company)],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('company_id', $company)],
            'party_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'store_id' => ['nullable', Rule::exists('stores', 'id')->where('company_id', $company)], 'reason' => ['nullable', 'string', 'max:300'],
            'remarks' => ['nullable', 'string', 'max:2000'], 'lines' => ['present', 'array', 'max:300'],
            'lines.*.item_id' => ['required', Rule::exists('gs_items', 'id')->where('company_id', $company)], 'lines.*.uom' => ['nullable', 'string', 'max:16', UomCodes::rule($company)],
            'lines.*.required_qty' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'req_date.required' => __('knitting.nd.v.req_date'), 'kind.required' => __('knitting.nd.v.kind'), 'party_id.required' => __('knitting.nd.v.party'),
            'lines.*.item_id.required' => __('knitting.nd.v.line_item'), 'lines.*.required_qty.required' => __('knitting.nd.v.line_qty'),
            'lines.*.required_qty.gt' => __('knitting.nd.v.line_qty'),
        ]);
        if ($row && $row->kind !== $data['kind']) {
            throw ValidationException::withMessages(['kind' => __('knitting.nd.v.kind_fixed')]);
        }
        $book = $row?->book ?? ($data['book'] ?? 'NEEDLE');
        unset($data['book']);
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __($book === 'OIL' ? 'knitting.nd.v.no_oil_lines' : 'knitting.nd.v.no_loan_lines')]);
        }
        $row = DB::transaction(function () use ($row, $data, $lines, $book): KnitNeedleLoanReq {
            $head = collect($data)->except('lines')->map(fn ($v) => $v === '' ? null : $v)->all()
                + ['req_type' => null, 'office_id' => null, 'department_id' => null, 'store_id' => null, 'reason' => null, 'remarks' => null];
            $row ??= KnitNeedleLoanReq::query()->create($head + ['book' => $book,
                'req_no' => KnitProduction::nextNumber($book === 'OIL' ? 'OLR' : 'NLR', 'knit_needle_loan_reqs', 'req_no', $data['req_date']),
                'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            $row->update($head);
            KnitNeedleLoanReqLine::query()->where('req_id', $row->id)->delete();
            foreach ($lines as $i => $l) {
                KnitNeedleLoanReqLine::query()->create(['company_id' => $row->company_id, 'req_id' => $row->id, 'sort_order' => $i + 1, 'item_id' => $l['item_id'],
                    'uom' => UomCodes::normalize($l['uom'] ?? null) ?: null, 'required_qty' => round((float) $l['required_qty'], 4), 'issue_qty' => 0,
                    'rate' => round((float) ($l['rate'] ?? 0), 4), 'remarks' => ($l['remarks'] ?? '') ?: null]);
            }

            return $row;
        });

        return $no ? ApiResponse::ok($this->presentLoan($row->refresh())) : ApiResponse::created($this->presentLoan($row->refresh()));
    }

    public function loanTransition(string $no, string $action): JsonResponse
    {
        $this->allow('knitting.needle.manage');
        $row = KnitNeedleLoanReq::query()->where('req_no', $no)->firstOrFail();
        [$from, $to] = $this->standing($action);
        $this->assertStatus($row->req_no, $row->status, $from, $action, 'req_status');
        if ($action === 'cancel' && KnitNeedleLoanReqLine::query()->where('req_id', $row->id)->sum('issue_qty') > 0) {
            throw ValidationException::withMessages(['status' => __('knitting.nd.v.req_issued', ['no' => $row->req_no])]);
        }
        $row->update(['status' => $to] + (in_array($action, ['verify', 'submit-verify'], true) ? ['verified_at' => now(), 'verified_by' => $this->caller()->auditUserId()] : []));

        return ApiResponse::ok($this->presentLoan($row->refresh()));
    }

    /* ---------------- helpers ---------------- */

    /** @return array{0: list<string>, 1: string} */
    private function standing(string $action): array
    {
        return match ($action) {
            'submit' => [['DRAFT'], 'WAITING'],
            'verify' => [['WAITING'], 'VERIFIED'],
            'submit-verify' => [['DRAFT'], 'VERIFIED'],
            'close' => [['VERIFIED', 'PARTLY_ISSUED'], 'CLOSED'],
            'cancel' => [['DRAFT', 'WAITING', 'VERIFIED'], 'CANCELLED'],
            default => abort(404),
        };
    }

    /** Once verified, only Issue Qty is written on a loan indent: posted out of the store as it is. */
    private function writeIssueQty(Request $request, KnitNeedleLoanReq $row): JsonResponse
    {
        $data = $request->validate(['lines' => ['present', 'array', 'max:300'], 'lines.*.id' => ['required', 'string'],
            'lines.*.issue_qty' => ['nullable', 'numeric', 'min:0', 'max:99999999'], 'issue_date' => ['nullable', 'date']]);
        if (! $row->store_id) {
            throw ValidationException::withMessages(['store_id' => __('knitting.nd.v.loan_store')]);
        }
        $date = ($data['issue_date'] ?? null) ?: now()->toDateString();
        NeedleStore::assertOpen($row->store_id, $date, 'issue_date', $row->book);
        $mine = KnitNeedleLoanReqLine::query()->where('req_id', $row->id)->get()->keyBy('id');
        [$items] = NeedleStore::names();
        $want = [];
        foreach (YarnStore::inOrder($data['lines']) as $i => $l) {
            $line = $mine[$l['id']] ?? null;
            if (! $line) {
                throw ValidationException::withMessages(["lines.{$i}.id" => __('knitting.nd.v.issue_line', ['n' => $i + 1])]);
            }
            $qty = round((float) ($l['issue_qty'] ?? 0), 4);
            if ($qty > $line->required_qty + 0.00005) {
                throw ValidationException::withMessages(["lines.{$i}.issue_qty" => __('knitting.nd.v.loan_over', ['n' => $i + 1, 'qty' => round($line->required_qty, 3)])]);
            }
            $want[$line->id] = $qty;
        }
        if ($row->kind === 'LOAN_RETURN') {
            $borrowed = NeedleStore::borrowed($row->book, $row->party_id);
            $others = NeedleStore::returnedTo($row->book, $row->party_id, $row->id);
            foreach ($mine->groupBy('item_id') as $item => $ls) {
                $total = (float) $ls->sum(fn ($l) => $want[$l->id] ?? $l->issue_qty);
                $left = round((float) ($borrowed[$item] ?? 0) - (float) ($others[$item] ?? 0), 4);
                if ($total > $left + 0.00005) {
                    throw ValidationException::withMessages(['lines' => __('knitting.nd.v.return_over_borrowed', ['item' => $items[$item] ?? '', 'left' => max(0, $left)])]);
                }
            }
        }
        $back = NeedleStore::loanReturned(array_keys($want), null, false);
        foreach ($want as $id => $qty) {
            if ($qty + 0.00005 < (float) ($back[$id] ?? 0)) {
                throw ValidationException::withMessages(['lines' => __('knitting.nd.v.loan_below_back', ['qty' => (float) $back[$id]])]);
            }
        }
        foreach ($mine->groupBy('item_id') as $item => $ls) {
            $total = (float) $ls->sum(fn ($l) => $want[$l->id] ?? $l->issue_qty);
            $have = NeedleStore::balance($row->store_id, $item, null, $row->req_no);
            if ($total > $have + 0.00005) {
                throw ValidationException::withMessages(['lines' => __('knitting.nd.v.stock_short', ['item' => $items[$item] ?? '', 'have' => $have])]);
            }
        }
        DB::transaction(function () use ($row, $mine, $want, $date): void {
            foreach ($want as $id => $qty) {
                $mine[$id]->update(['issue_qty' => $qty]);
            }
            $lines = KnitNeedleLoanReqLine::query()->where('req_id', $row->id)->get();
            NeedleStore::post('LOAN_OUT', $row->req_no, $date, $lines->map(fn (KnitNeedleLoanReqLine $l) => [
                'store_id' => $row->store_id, 'item_id' => $l->item_id, 'qty' => -$l->issue_qty, 'value' => -$l->issue_qty * $l->rate, 'doc_line_id' => $l->id])->all(), $row->book);
            $asked = (float) $lines->sum('required_qty');
            $out = (float) $lines->sum('issue_qty');
            $row->update(['status' => $out <= 0 ? 'VERIFIED' : ($out + 0.00005 >= $asked ? 'ISSUED' : 'PARTLY_ISSUED')]);
        });

        return ApiResponse::ok($this->presentLoan($row->refresh()));
    }

    private function assertStatus(string $no, string $status, array $from, string $action, string $group): void
    {
        if (! in_array($status, $from, true)) {
            throw ValidationException::withMessages(['status' => __('knitting.nd.v.cannot', [
                'no' => $no, 'status' => __("knitting.nd.{$group}.{$status}"), 'action' => __("knitting.nd.action.{$action}"),
            ])]);
        }
    }

    /** A sheet's machines must be the unit's own. */
    private function assertMachines(array $lines, ?string $officeId): void
    {
        if (! $officeId) {
            return;
        }
        $offices = NeedleStore::machineOffices();
        foreach (array_values($lines) as $i => $l) {
            if (($offices[$l['machine_id']] ?? null) !== $officeId) {
                throw ValidationException::withMessages(["lines.{$i}.machine_id" => __('knitting.nd.v.machine_office', ['n' => $i + 1])]);
            }
        }
    }

    /** @return array{office_id: string, category_id: string} */
    private function sheetInput(Request $request): array
    {
        $company = app(TenantContext::class)->companyId();

        return $request->validate([
            'office_id' => ['required', Rule::exists('factories', 'id')->where('company_id', $company)],
            'category_id' => ['required', Rule::exists('item_categories', 'id')->where('company_id', $company)],
        ], ['office_id.required' => __('knitting.nd.v.office'), 'category_id.required' => __('knitting.nd.v.category')]);
    }

    /** @return array<string, mixed> */
    private function fits(string $officeId, string $categoryId): array
    {
        $machines = NeedleStore::machineOffices()->filter(fn ($o) => $o === $officeId)->keys();
        $have = KnitNeedleAssignment::query()->where('office_id', $officeId)->where('category_id', $categoryId)->get()->keyBy('machine_id');
        $rows = DB::table('knit_machines')->whereIn('id', $machines)->orderBy('machine_no')->get(['id', 'machine_no', 'machine_type_id', 'gauge_id', 'dia_id']);
        $refs = DB::table('knit_ref_entries')->whereIn('id', $rows->pluck('machine_type_id')->merge($rows->pluck('gauge_id'))->merge($rows->pluck('dia_id'))->filter())->pluck('name', 'id');

        return ['office_id' => $officeId, 'category_id' => $categoryId, 'finalized' => $have->contains(fn ($a) => $a->finalized),
            'rows' => $rows->map(fn ($m) => ['machine_id' => $m->id, 'machine_no' => $m->machine_no, 'machine_type' => $refs[$m->machine_type_id] ?? null,
                'dia' => $refs[$m->dia_id] ?? null, 'gauge' => $refs[$m->gauge_id] ?? null, 'item_id' => $have[$m->id]->item_id ?? null,
                'qty' => isset($have[$m->id]) ? round($have[$m->id]->qty, 3) : 0.0])->values()->all()];
    }

    /** @return list<array<string, mixed>> */
    private function reqRows($rows): array
    {
        $lines = KnitNeedleReqLine::query()->whereIn('req_id', collect($rows)->pluck('id'))->get();
        $issued = NeedleStore::issued($lines->pluck('id'));
        $byReq = $lines->groupBy('req_id');
        [, $stores] = NeedleStore::names();
        $broken = KnitNeedleBroken::query()->whereIn('id', collect($rows)->pluck('broken_id')->filter())->pluck('ref_no', 'id');

        return collect($rows)->map(function (KnitNeedleReq $r) use ($byReq, $issued, $stores, $broken) {
            $mine = $byReq[$r->id] ?? collect();
            $out = (float) $mine->sum(fn ($l) => $issued[$l->id] ?? 0);

            return ['req_no' => $r->req_no, 'req_date' => $r->req_date->toDateString(), 'reason' => $r->reason, 'broken_no' => $broken[$r->broken_id] ?? null,
                'office_id' => $r->office_id, 'from_store_id' => $r->from_store_id, 'from_store' => $stores[$r->from_store_id] ?? null, 'to_store_id' => $r->to_store_id,
                'to_store' => $stores[$r->to_store_id] ?? null, 'lines' => $mine->count(), 'req_qty' => round((float) $mine->sum('req_qty'), 3), 'issued' => round($out, 3),
                'balance' => round(max(0, (float) $mine->sum('req_qty') - $out), 3), 'status' => $r->status];
        })->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function owingLines(KnitNeedleReq $req): array
    {
        $lines = KnitNeedleReqLine::query()->where('req_id', $req->id)->orderBy('sort_order')->get();
        $issued = NeedleStore::issued($lines->pluck('id'));
        [$items] = NeedleStore::names();
        $machines = DB::table('knit_machines')->whereIn('id', $lines->pluck('machine_id')->filter())->pluck('machine_no', 'id');

        return $lines->map(fn (KnitNeedleReqLine $l) => ['req_line_id' => $l->id, 'machine' => $machines[$l->machine_id] ?? null, 'item_id' => $l->item_id, 'item' => $items[$l->item_id] ?? null,
            'uom' => $l->uom, 'req_qty' => round($l->req_qty, 3), 'issued' => round((float) ($issued[$l->id] ?? 0), 3),
            'owing' => round(max(0, $l->req_qty - (float) ($issued[$l->id] ?? 0)), 3)])->values()->all();
    }

    /** @return array<string, mixed> */
    private function presentBroken(KnitNeedleBroken $b): array
    {
        $lines = KnitNeedleBrokenLine::query()->where('broken_id', $b->id)->orderBy('sort_order')->get();
        [$items] = NeedleStore::names();
        $cats = DB::table('gs_items')->whereIn('id', $lines->pluck('item_id'))->pluck('gs_category_id', 'id');
        $machines = DB::table('knit_machines')->whereIn('id', $lines->pluck('machine_id'))->pluck('machine_no', 'id');

        return collect($b->only(['id', 'ref_no', 'shift_id', 'office_id', 'machine_type_id', 'remarks', 'status']))->all() + [
            'broken_date' => $b->broken_date->toDateString(), 'shift' => DB::table('hr_shifts')->where('id', $b->shift_id)->value('name'),
            'office' => $b->office_id ? DB::table('factories')->where('id', $b->office_id)->value('name') : null,
            'machine_type' => $b->machine_type_id ? DB::table('knit_ref_entries')->where('id', $b->machine_type_id)->value('name') : null,
            'company' => DB::table('companies')->where('id', $b->company_id)->value('name'),
            'lines' => $lines->map(fn (KnitNeedleBrokenLine $l) => collect($l->only(['machine_id', 'item_id', 'uom', 'remarks']))->all() + [
                'category_id' => $cats[$l->item_id] ?? null, 'machine' => $machines[$l->machine_id] ?? null, 'item' => $items[$l->item_id] ?? null,
                'qty' => round($l->qty, 3), 'cost_price' => round($l->cost_price, 4), 'amount' => round($l->amount, 2)])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentReq(KnitNeedleReq $r): array
    {
        $lines = KnitNeedleReqLine::query()->where('req_id', $r->id)->orderBy('sort_order')->get();
        $issued = NeedleStore::issued($lines->pluck('id'));
        [$items, $stores] = NeedleStore::names();
        $cats = DB::table('gs_items')->whereIn('id', $lines->pluck('item_id'))->pluck('gs_category_id', 'id');
        $machines = DB::table('knit_machines')->whereIn('id', $lines->pluck('machine_id')->filter())->pluck('machine_no', 'id');

        return collect($r->only(['id', 'req_no', 'reason', 'office_id', 'from_store_id', 'to_store_id', 'remarks', 'status']))->all() + [
            'req_date' => $r->req_date->toDateString(), 'broken_no' => $r->broken_id ? KnitNeedleBroken::query()->whereKey($r->broken_id)->value('ref_no') : null,
            'office' => $r->office_id ? DB::table('factories')->where('id', $r->office_id)->value('name') : null, 'from_store' => $stores[$r->from_store_id] ?? null,
            'to_store' => $stores[$r->to_store_id] ?? null, 'company' => DB::table('companies')->where('id', $r->company_id)->value('name'),
            'lines' => $lines->map(fn (KnitNeedleReqLine $l) => collect($l->only(['machine_id', 'item_id', 'uom', 'remarks']))->all() + [
                'category_id' => $cats[$l->item_id] ?? null, 'machine' => $machines[$l->machine_id] ?? null, 'item' => $items[$l->item_id] ?? null,
                'req_qty' => round($l->req_qty, 3), 'rate' => round($l->rate, 4), 'issued' => round((float) ($issued[$l->id] ?? 0), 3)])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentIssue(KnitNeedleIssue $s): array
    {
        $lines = KnitNeedleIssueLine::query()->where('issue_id', $s->id)->orderBy('sort_order')->get();
        $reqLines = KnitNeedleReqLine::query()->whereIn('id', $lines->pluck('req_line_id'))->get()->keyBy('id');
        $issued = NeedleStore::issued($lines->pluck('req_line_id'), $s->id);
        [$items, $stores] = NeedleStore::names();
        $machines = DB::table('knit_machines')->whereIn('id', $reqLines->pluck('machine_id')->filter())->pluck('machine_no', 'id');
        $req = KnitNeedleReq::query()->find($s->req_id);

        return ['id' => $s->id, 'issue_no' => $s->issue_no, 'issue_date' => $s->issue_date->toDateString(), 'req_no' => $req?->req_no, 'req_status' => $req?->status,
            'from_store_id' => $s->from_store_id, 'from_store' => $stores[$s->from_store_id] ?? null, 'to_store_id' => $s->to_store_id, 'to_store' => $stores[$s->to_store_id] ?? null,
            'remarks' => $s->remarks, 'status' => $s->status, 'company' => DB::table('companies')->where('id', $s->company_id)->value('name'),
            'lines' => $lines->map(function (KnitNeedleIssueLine $l) use ($reqLines, $issued, $items, $machines) {
                $r = $reqLines[$l->req_line_id] ?? null;

                return ['req_line_id' => $l->req_line_id, 'machine' => $machines[$r?->machine_id] ?? null, 'item' => $items[$r?->item_id] ?? null, 'uom' => $r?->uom,
                    'req_qty' => round((float) $r?->req_qty, 3), 'issued' => round((float) ($issued[$l->req_line_id] ?? 0), 3), 'qty' => round($l->qty, 3), 'remarks' => $l->remarks];
            })->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentLoan(KnitNeedleLoanReq $r): array
    {
        $lines = KnitNeedleLoanReqLine::query()->where('req_id', $r->id)->orderBy('sort_order')->get();
        $back = NeedleStore::loanReturned($lines->pluck('id'));
        [$items, $stores] = NeedleStore::names();
        $cats = DB::table('gs_items')->whereIn('id', $lines->pluck('item_id'))->pluck('gs_category_id', 'id');
        $party = DB::table('mp_suppliers')->where('id', $r->party_id)->first(['name', 'address']);

        return collect($r->only(['id', 'book', 'req_no', 'kind', 'req_type', 'office_id', 'department_id', 'party_id', 'store_id', 'reason', 'remarks', 'status']))->all() + [
            'req_date' => $r->req_date->toDateString(), 'party' => $party?->name, 'party_address' => $party?->address, 'store' => $stores[$r->store_id] ?? null,
            'office' => $r->office_id ? DB::table('factories')->where('id', $r->office_id)->value('name') : null,
            'department' => $r->department_id ? DB::table('departments')->where('id', $r->department_id)->value('name') : null,
            'company' => DB::table('companies')->where('id', $r->company_id)->value('name'),
            'lines' => $lines->map(fn (KnitNeedleLoanReqLine $l) => collect($l->only(['id', 'item_id', 'uom', 'remarks']))->all() + [
                'category_id' => $cats[$l->item_id] ?? null, 'item' => $items[$l->item_id] ?? null, 'required_qty' => round($l->required_qty, 3),
                'issue_qty' => round($l->issue_qty, 3), 'rate' => round($l->rate, 4), 'returned' => round((float) ($back[$l->id] ?? 0), 3),
                'owes' => round($l->issue_qty - (float) ($back[$l->id] ?? 0), 3)])->values()->all(),
        ];
    }
}
