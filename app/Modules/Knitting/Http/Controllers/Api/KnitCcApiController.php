<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\Knitting\Models\KnitCcChallan;
use App\Modules\Knitting\Models\KnitCcChallanLine;
use App\Modules\Knitting\Models\KnitCcProduction;
use App\Modules\Knitting\Models\KnitCcProgram;
use App\Modules\Knitting\Models\KnitCcProgramLine;
use App\Modules\Knitting\Models\KnitCcRequirement;
use App\Modules\Knitting\Support\KnitCollarCuff;
use App\Modules\Knitting\Support\KnitProduction;
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
 * Knit Production → Collar/Cuff (AnnotechERP 15/06), as Brickland works it:
 *
 * - Collar & Cuff Output Entry: the Flat Knit Requirement (CCR-…) a job
 *   raises — in-house and sub-contract pieces asked apart, at least one of
 *   them — and the production desk beside it, one line per day and source,
 *   saved as it is added and corrected in place; Close is the merchandiser
 *   saying no more of that part will be made.
 * - Out-House Collar & Cuff Programme (CCP-…): parts placed on an outside
 *   party. Draft → Submit → Approve (or Send Back) → Start → Close, or
 *   Cancel while nothing has been received; received is a join on the S/C
 *   receive challans the store has counted in.
 * - Bulk / Sample Collar & Cuff Store Receipt (CCB- / CCS-…): pieces moved
 *   between the floor and the store against open requirements. Draft →
 *   Issue → Receive at Store (dated today), Re-open a step back, Cancel
 *   before the store has counted them.
 */
class KnitCcApiController extends ApiController
{
    private const CHALLAN_KINDS = ['bulk' => 'BULK', 'sample' => 'SAMPLE'];

    /** The requirement types each store receipt moves: develop pieces travel with the samples. */
    private const KIND_TYPES = ['BULK' => ['BULK'], 'SAMPLE' => ['SAMPLE', 'DEVELOP']];

    public function options(): JsonResponse
    {
        $this->allowEither('knitting.production.view', 'knitting.fstore.view');

        return ApiResponse::ok(KnitCollarCuff::options());
    }

    /* ---------------- flat knit requirements ---------------- */

    public function requirements(): JsonResponse
    {
        $this->allowEither('knitting.production.view', 'knitting.fstore.view');
        $rows = KnitCcRequirement::query()->orderByDesc('req_no')->limit(1000)->get();

        return ApiResponse::ok($this->presentRequirements($rows));
    }

    public function showRequirement(string $no): JsonResponse
    {
        $this->allowEither('knitting.production.view', 'knitting.fstore.view');

        return ApiResponse::ok($this->presentRequirements(collect([$this->requirement($no)]))[0]);
    }

    public function saveRequirement(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $row = $no ? $this->requirement($no) : null;
        if ($row && $row->status !== 'OPEN') {
            throw ValidationException::withMessages(['status' => __('knitting.cc.v.req_closed', ['no' => $row->req_no])]);
        }
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'req_type' => ['required', Rule::in(KnitCcRequirement::TYPES)], 'category' => ['nullable', 'string', 'max:40'],
            'buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $company)],
            'work_style_no' => ['nullable', 'string', 'max:60'], 'ship_month' => ['nullable', 'date_format:Y-m'],
            'item' => ['required', Rule::in(KnitCcRequirement::ITEMS)], 'description' => ['required', 'string', 'max:200'],
            'colour_id' => ['nullable', Rule::exists('colours', 'id')->where('company_id', $company)],
            'size_desc' => ['nullable', 'string', 'max:80'], 'gauge' => ['nullable', 'string', 'max:40'],
            'req_in_house' => ['nullable', 'numeric', 'min:0', 'max:99999999'], 'req_sc' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'uom' => ['nullable', 'string', 'max:16', UomCodes::rule($company)], 'remarks' => ['nullable', 'string', 'max:2000'],
        ], [
            'req_type.required' => __('knitting.cc.v.type'), 'item.required' => __('knitting.cc.v.item'), 'description.required' => __('knitting.cc.v.description'),
        ]);
        $in = round((float) ($data['req_in_house'] ?? 0), 4);
        $sc = round((float) ($data['req_sc'] ?? 0), 4);
        if ($in <= 0 && $sc <= 0) {
            throw ValidationException::withMessages(['req_in_house' => __('knitting.cc.v.req_qty')]);
        }
        if (! empty($data['order_id'])) {
            $data['buyer_id'] ??= DB::table('merch_orders')->where('id', $data['order_id'])->value('buyer_id');
        }
        $head = collect($data)->except(['req_in_house', 'req_sc', 'uom'])->map(fn ($v) => $v === '' ? null : $v)->all()
            + ['category' => null, 'buyer_id' => null, 'order_id' => null, 'work_style_no' => null, 'ship_month' => null, 'colour_id' => null, 'size_desc' => null, 'gauge' => null, 'remarks' => null]
            + ['req_in_house' => $in, 'req_sc' => $sc, 'uom' => UomCodes::normalize($data['uom'] ?? null) ?: 'PCS'];
        $row = DB::transaction(function () use ($row, $head): KnitCcRequirement {
            if ($row) {
                $row->update($head);

                return $row;
            }

            return KnitCcRequirement::query()->create($head + ['req_no' => KnitProduction::nextNumber('CCR', 'knit_cc_requirements', 'req_no', now()->toDateString()),
                'status' => 'OPEN', 'created_by' => $this->caller()->auditUserId()]);
        });
        $out = $this->presentRequirements(collect([$row->refresh()]))[0];

        return $no ? ApiResponse::ok($out) : ApiResponse::created($out);
    }

    public function closeRequirement(string $no): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $row = $this->requirement($no);
        if ($row->status !== 'OPEN') {
            throw ValidationException::withMessages(['status' => __('knitting.cc.v.req_closed', ['no' => $row->req_no])]);
        }
        $row->update(['status' => 'CLOSED', 'closed_at' => now()]);

        return ApiResponse::ok($this->presentRequirements(collect([$row->refresh()]))[0]);
    }

    /* ---------------- production desk ---------------- */

    public function productions(string $no): JsonResponse
    {
        $this->allowEither('knitting.production.view', 'knitting.fstore.view');

        return ApiResponse::ok($this->presentProductions($this->requirement($no)));
    }

    public function addProduction(Request $request, string $no): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $req = $this->openRequirement($no);
        $line = $this->productionLine($request->validate($this->productionRules(''), $this->productionMessages('')));
        KnitCcProduction::query()->create($line + ['requirement_id' => $req->id, 'created_by' => $this->caller()->auditUserId()]);

        return ApiResponse::created($this->presentProductions($req));
    }

    /** The desk's Save: every line as corrected in place. */
    public function saveProductions(Request $request, string $no): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $req = $this->openRequirement($no);
        $data = $request->validate(['lines' => ['present', 'array', 'max:500'], 'lines.*.id' => ['required', 'string']] + $this->productionRules('lines.*.'),
            $this->productionMessages('lines.*.'));
        $lines = YarnStore::inOrder($data['lines']);
        $mine = KnitCcProduction::query()->where('requirement_id', $req->id)->pluck('id')->all();
        DB::transaction(function () use ($lines, $mine, $req): void {
            foreach ($lines as $i => $l) {
                if (! in_array($l['id'], $mine, true)) {
                    throw ValidationException::withMessages(["lines.{$i}.id" => __('knitting.cc.v.prod_unknown', ['n' => $i + 1])]);
                }
                KnitCcProduction::query()->whereKey($l['id'])->first()?->update($this->productionLine($l));
            }
            $f = KnitCollarCuff::figures(collect([$req]))[$req->id];
            if ($f['delivered'] > $f['prod_in'] + $f['prod_sc'] + 0.0005) {
                throw ValidationException::withMessages(['lines' => __('knitting.cc.v.prod_delivered', ['qty' => $f['delivered']])]);
            }
        });

        return ApiResponse::ok($this->presentProductions($req));
    }

    public function removeProduction(string $no, string $id): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $req = $this->openRequirement($no);
        $line = KnitCcProduction::query()->where('requirement_id', $req->id)->whereKey($id)->firstOrFail();
        // Pieces already on a challan cannot be taken back off the desk.
        DB::transaction(function () use ($line, $req): void {
            $line->delete();
            $f = KnitCollarCuff::figures(collect([$req]))[$req->id];
            if ($f['delivered'] > $f['prod_in'] + $f['prod_sc'] + 0.0005) {
                throw ValidationException::withMessages(['lines' => __('knitting.cc.v.prod_delivered', ['qty' => $f['delivered']])]);
            }
        });

        return ApiResponse::ok($this->presentProductions($req));
    }

    /* ---------------- out-house programmes ---------------- */

    public function programs(): JsonResponse
    {
        $this->allowEither('knitting.production.view', 'knitting.fstore.view');
        $rows = KnitCcProgram::query()->orderByDesc('program_date')->orderByDesc('program_no')->limit(500)->get();
        $lines = KnitCcProgramLine::query()->whereIn('program_id', $rows->pluck('id'))->get();
        $received = KnitCollarCuff::received($lines->pluck('id'));
        $byProgram = $lines->groupBy('program_id');
        $parties = DB::table('mp_suppliers')->whereIn('id', $rows->pluck('party_id'))->pluck('name', 'id');

        return ApiResponse::ok($rows->map(function (KnitCcProgram $p) use ($byProgram, $received, $parties) {
            $mine = $byProgram[$p->id] ?? collect();

            return ['program_no' => $p->program_no, 'program_date' => $p->program_date->toDateString(), 'party_id' => $p->party_id, 'party' => $parties[$p->party_id] ?? null,
                'cpi' => $p->cpi, 'parts' => $mine->count(), 'qty' => round((float) $mine->sum('qty'), 3),
                'received' => round((float) $mine->sum(fn ($l) => $received[$l->id] ?? 0), 3), 'amount' => round((float) $mine->sum('amount'), 2),
                'remarks' => $p->remarks, 'status' => $p->status];
        })->values()->all());
    }

    public function showProgram(string $no): JsonResponse
    {
        $this->allowEither('knitting.production.view', 'knitting.fstore.view');

        return ApiResponse::ok($this->presentProgram($this->program($no)));
    }

    public function saveProgram(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $row = $no ? $this->program($no) : null;
        $row && $this->assertStatus($row->program_no, $row->status, ['DRAFT'], 'save', 'program_status');
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'program_date' => ['required', 'date'], 'delivery_date' => ['nullable', 'date', 'after_or_equal:program_date'],
            'party_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)], 'cpi' => ['nullable', 'string', 'max:24'],
            'buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $company)],
            'work_style_no' => ['nullable', 'string', 'max:60'], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:300'], 'lines.*.item' => ['required', Rule::in(KnitCcRequirement::ITEMS)],
            'lines.*.description' => ['nullable', 'string', 'max:200'], 'lines.*.colour_id' => ['nullable', Rule::exists('colours', 'id')->where('company_id', $company)],
            'lines.*.size_desc' => ['nullable', 'string', 'max:80'], 'lines.*.gauge' => ['nullable', 'string', 'max:40'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.uom' => ['nullable', 'string', 'max:16', UomCodes::rule($company)], 'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'program_date.required' => __('knitting.cc.v.date'), 'party_id.required' => __('knitting.cc.v.supplier'),
            'delivery_date.after_or_equal' => __('knitting.cc.v.delivery_date'), 'lines.*.item.required' => __('knitting.cc.v.line_item'),
            'lines.*.qty.required' => __('knitting.cc.v.line_qty'), 'lines.*.qty.gt' => __('knitting.cc.v.line_qty'),
        ]);
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('knitting.cc.v.no_parts')]);
        }
        if (! empty($data['order_id'])) {
            $data['buyer_id'] ??= DB::table('merch_orders')->where('id', $data['order_id'])->value('buyer_id');
        }
        $row = DB::transaction(function () use ($row, $data, $lines): KnitCcProgram {
            $head = collect($data)->except('lines')->map(fn ($v) => $v === '' ? null : $v)->all()
                + ['delivery_date' => null, 'cpi' => null, 'buyer_id' => null, 'order_id' => null, 'work_style_no' => null, 'remarks' => null];
            $row ??= KnitCcProgram::query()->create($head + ['program_no' => KnitProduction::nextNumber('CCP', 'knit_cc_programs', 'program_no', $data['program_date']),
                'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            $row->update($head);
            KnitCcProgramLine::query()->where('program_id', $row->id)->delete();
            foreach ($lines as $i => $l) {
                $qty = round((float) $l['qty'], 4);
                $rate = round((float) ($l['rate'] ?? 0), 4);
                KnitCcProgramLine::query()->create(collect($l)->only(['item', 'description', 'colour_id', 'size_desc', 'gauge', 'remarks'])->map(fn ($v) => $v === '' ? null : $v)->all() + [
                    'company_id' => $row->company_id, 'program_id' => $row->id, 'sort_order' => $i + 1, 'qty' => $qty, 'rate' => $rate,
                    'amount' => round($qty * $rate, 4), 'uom' => UomCodes::normalize($l['uom'] ?? null) ?: 'PCS',
                ]);
            }

            return $row;
        });

        return $no ? ApiResponse::ok($this->presentProgram($row->refresh())) : ApiResponse::created($this->presentProgram($row->refresh()));
    }

    public function programTransition(string $no, string $action): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $row = $this->program($no);
        [$from, $to] = match ($action) {
            'submit' => [['DRAFT'], 'SUBMITTED'],
            'approve' => [['SUBMITTED'], 'APPROVED'],
            'send-back' => [['SUBMITTED'], 'DRAFT'],
            'start' => [['APPROVED'], 'IN_PRODUCTION'],
            'close' => [['APPROVED', 'IN_PRODUCTION'], 'CLOSED'],
            'cancel' => [['DRAFT', 'SUBMITTED', 'APPROVED'], 'CANCELLED'],
            default => abort(404),
        };
        $this->assertStatus($row->program_no, $row->status, $from, $action, 'program_status');
        if ($action === 'cancel') {
            $lines = KnitCcProgramLine::query()->where('program_id', $row->id)->pluck('id');
            $booked = KnitCcChallanLine::query()->whereIn('program_line_id', $lines)
                ->whereIn('challan_id', KnitCcChallan::query()->where('status', '!=', 'CANCELLED')->select('id'))->exists();
            if ($booked) {
                throw ValidationException::withMessages(['status' => __('knitting.cc.v.program_received', ['no' => $row->program_no])]);
            }
        }
        $row->update(['status' => $to] + ($action === 'approve' ? ['approved_at' => now(), 'approved_by' => $this->caller()->auditUserId()] : []));

        return ApiResponse::ok($this->presentProgram($row->refresh()));
    }

    /* ---------------- store receipts ---------------- */

    public function challans(string $kind): JsonResponse
    {
        $this->allowEither('knitting.fstore.view', 'knitting.production.view');
        $rows = KnitCcChallan::query()->where('kind', $this->challanKind($kind))->orderByDesc('challan_date')->orderByDesc('challan_no')->limit(500)->get();
        $sums = KnitCcChallanLine::query()->whereIn('challan_id', $rows->pluck('id'))->groupBy('challan_id')->selectRaw('challan_id, count(*) as n, sum(qty) as q')->get()->keyBy('challan_id');
        $floors = DB::table('floors')->whereIn('id', $rows->pluck('from_floor_id')->filter())->pluck('name', 'id');

        return ApiResponse::ok($rows->map(fn (KnitCcChallan $c) => [
            'challan_no' => $c->challan_no, 'challan_type' => $c->challan_type, 'challan_date' => $c->challan_date->toDateString(), 'rcv_date' => $c->rcv_date?->toDateString(),
            'from' => $floors[$c->from_floor_id] ?? null, 'lines' => (int) ($sums[$c->id]->n ?? 0), 'qty' => round((float) ($sums[$c->id]->q ?? 0), 3), 'status' => $c->status,
        ])->values()->all());
    }

    /** The Open Flat Knit Requirement panel: what each job still has to move, part by part. */
    public function openRequirements(Request $request, string $kind): JsonResponse
    {
        $this->allowEither('knitting.fstore.view', 'knitting.production.view');
        $k = $this->challanKind($kind);
        $except = $request->query('challan') ? KnitCcChallan::query()->where('challan_no', $request->query('challan'))->value('id') : null;
        $rows = KnitCcRequirement::query()->where('status', 'OPEN')->whereIn('req_type', self::KIND_TYPES[$k])->orderBy('req_no')->get();
        $figures = KnitCollarCuff::figures($rows, $except);
        $orders = KnitCollarCuff::orderLabels($rows->pluck('order_id'));

        return ApiResponse::ok($rows->map(fn (KnitCcRequirement $r) => [
            'req_no' => $r->req_no, 'label' => trim(($orders[$r->order_id] ?? __('knitting.cc.no_order')).' · '.__("knitting.cc.item.{$r->item}").' · '.$r->description, ' ·'),
            'group' => $r->order_id ? ($orders[$r->order_id] ?? '') : ($r->work_style_no ?: __('knitting.cc.no_order')), 'order_id' => $r->order_id,
            'item' => $r->item, 'description' => $r->description, 'colour_id' => $r->colour_id, 'size_desc' => $r->size_desc, 'uom' => $r->uom,
            'open' => $figures[$r->id]['open'],
        ])->values()->all());
    }

    public function showChallan(string $kind, string $no): JsonResponse
    {
        $this->allowEither('knitting.fstore.view', 'knitting.production.view');

        return ApiResponse::ok($this->presentChallan($this->challan($kind, $no)));
    }

    public function saveChallan(Request $request, string $kind, ?string $no = null): JsonResponse
    {
        $this->allowEither('knitting.fstore.manage', 'knitting.production.manage');
        $k = $this->challanKind($kind);
        $row = $no ? $this->challan($kind, $no) : null;
        $row && $this->assertStatus($row->challan_no, $row->status, ['DRAFT'], 'save', 'challan_status');
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'challan_type' => ['required', Rule::in(KnitCcChallan::TYPES)], 'challan_date' => ['required', 'date'],
            'gate_pass_no' => ['nullable', 'string', 'max:40'], 'vehicle_no' => ['nullable', 'string', 'max:40'],
            'from_floor_id' => ['nullable', Rule::exists('floors', 'id')->where('company_id', $company)],
            'store_id' => ['nullable', Rule::exists('stores', 'id')->where('company_id', $company)], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:300'], 'lines.*.req_no' => ['nullable', 'string', 'max:24'], 'lines.*.item' => ['required', Rule::in(KnitCcRequirement::ITEMS)],
            'lines.*.description' => ['nullable', 'string', 'max:200'], 'lines.*.colour_id' => ['nullable', Rule::exists('colours', 'id')->where('company_id', $company)],
            'lines.*.size_desc' => ['nullable', 'string', 'max:80'], 'lines.*.order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $company)],
            'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'lines.*.uom' => ['nullable', 'string', 'max:16', UomCodes::rule($company)],
            'lines.*.remarks' => ['nullable', 'string', 'max:300'], 'lines.*.program_line_id' => ['nullable', 'string'],
        ], [
            'challan_type.required' => __('knitting.cc.v.challan_type'), 'challan_date.required' => __('knitting.cc.v.challan_date'),
            'lines.*.item.required' => __('knitting.cc.v.line_item'), 'lines.*.qty.required' => __('knitting.cc.v.line_qty'), 'lines.*.qty.gt' => __('knitting.cc.v.line_qty'),
        ]);
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('knitting.cc.v.no_challan_lines')]);
        }
        $reqs = KnitCcRequirement::query()->whereIn('req_no', array_filter(array_column($lines, 'req_no')))->get()->keyBy('req_no');
        $open = KnitCollarCuff::figures($reqs->values(), $row?->id);
        $liveLines = collect(KnitCollarCuff::livePrograms())->flatMap(fn ($p) => $p['lines'])->keyBy('value');
        $taking = [];
        $out = [];
        foreach ($lines as $i => $l) {
            $n = $i + 1;
            $r = null;
            if (! empty($l['req_no'])) {
                $r = $reqs[$l['req_no']] ?? null;
                if (! $r || ! in_array($r->req_type, self::KIND_TYPES[$k], true) || $r->status !== 'OPEN') {
                    throw ValidationException::withMessages(["lines.{$i}.req_no" => __('knitting.cc.v.line_req', ['n' => $n, 'no' => $l['req_no']])]);
                }
                $taking[$r->id] = ($taking[$r->id] ?? 0) + (float) $l['qty'];
                if ($taking[$r->id] > $open[$r->id]['open'] + 0.0005) {
                    throw ValidationException::withMessages(["lines.{$i}.qty" => __('knitting.cc.v.line_open', ['n' => $n, 'no' => $r->req_no, 'open' => $open[$r->id]['open']])]);
                }
            }
            $pl = null;
            if (! empty($l['program_line_id'])) {
                $pl = $liveLines[$l['program_line_id']] ?? null;
                if (! $pl || $data['challan_type'] !== 'SC_RECEIVE') {
                    throw ValidationException::withMessages(["lines.{$i}.program_line_id" => __('knitting.cc.v.line_program', ['n' => $n])]);
                }
            }
            $out[] = collect($l)->only(['item', 'description', 'colour_id', 'size_desc', 'order_id', 'remarks'])->map(fn ($v) => $v === '' ? null : $v)->all() + [
                'requirement_id' => $r?->id, 'program_line_id' => $pl ? $l['program_line_id'] : null, 'qty' => round((float) $l['qty'], 4),
                'uom' => UomCodes::normalize($l['uom'] ?? null) ?: 'PCS', 'order_id' => $l['order_id'] ?? $r?->order_id,
            ];
        }
        $row = DB::transaction(function () use ($row, $data, $out, $k): KnitCcChallan {
            $head = collect($data)->except('lines')->map(fn ($v) => $v === '' ? null : $v)->all()
                + ['gate_pass_no' => null, 'vehicle_no' => null, 'from_floor_id' => null, 'store_id' => null, 'remarks' => null];
            $row ??= KnitCcChallan::query()->create($head + ['kind' => $k, 'challan_no' => KnitProduction::nextNumber($k === 'BULK' ? 'CCB' : 'CCS', 'knit_cc_challans', 'challan_no', $data['challan_date']),
                'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            $row->update($head);
            KnitCcChallanLine::query()->where('challan_id', $row->id)->delete();
            foreach ($out as $i => $l) {
                KnitCcChallanLine::query()->create($l + ['company_id' => $row->company_id, 'challan_id' => $row->id, 'sort_order' => $i + 1]);
            }

            return $row;
        });

        return $no ? ApiResponse::ok($this->presentChallan($row->refresh())) : ApiResponse::created($this->presentChallan($row->refresh()));
    }

    public function challanTransition(string $kind, string $no, string $action): JsonResponse
    {
        $this->allowEither('knitting.fstore.manage', 'knitting.production.manage');
        $row = $this->challan($kind, $no);
        [$from, $to] = match ($action) {
            'issue' => [['DRAFT'], 'ISSUED'],
            'receive' => [['ISSUED'], 'RECEIVED'],
            'reopen' => [['ISSUED', 'RECEIVED'], $row->status === 'RECEIVED' ? 'ISSUED' : 'DRAFT'],
            'cancel' => [['DRAFT', 'ISSUED'], 'CANCELLED'],
            default => abort(404),
        };
        $this->assertStatus($row->challan_no, $row->status, $from, $action, 'challan_status');
        if ($action === 'receive') {
            // What an S/C receive books in may not take a programme part past its pieces.
            $lines = KnitCcChallanLine::query()->where('challan_id', $row->id)->whereNotNull('program_line_id')->get();
            if ($row->challan_type === 'SC_RECEIVE' && $lines->isNotEmpty()) {
                $had = KnitCollarCuff::received($lines->pluck('program_line_id'));
                $parts = KnitCcProgramLine::query()->whereIn('id', $lines->pluck('program_line_id'))->get()->keyBy('id');
                foreach ($lines->groupBy('program_line_id') as $id => $mine) {
                    $p = $parts[$id];
                    if ((float) ($had[$id] ?? 0) + (float) $mine->sum('qty') > $p->qty + 0.0005) {
                        throw ValidationException::withMessages(['status' => __('knitting.cc.v.over_received', [
                            'part' => __("knitting.cc.item.{$p->item}").($p->description ? " {$p->description}" : ''), 'qty' => round($p->qty, 3), 'had' => (float) ($had[$id] ?? 0),
                        ])]);
                    }
                }
            }
        }
        $row->update(['status' => $to, 'rcv_date' => $to === 'RECEIVED' ? now()->toDateString() : null]);

        return ApiResponse::ok($this->presentChallan($row->refresh()));
    }

    /* ---------------- helpers ---------------- */

    private function requirement(string $no): KnitCcRequirement
    {
        return KnitCcRequirement::query()->where('req_no', $no)->firstOrFail();
    }

    private function openRequirement(string $no): KnitCcRequirement
    {
        $req = $this->requirement($no);
        if ($req->status !== 'OPEN') {
            throw ValidationException::withMessages(['status' => __('knitting.cc.v.req_closed', ['no' => $req->req_no])]);
        }

        return $req;
    }

    private function program(string $no): KnitCcProgram
    {
        return KnitCcProgram::query()->where('program_no', $no)->firstOrFail();
    }

    private function challanKind(string $slug): string
    {
        return self::CHALLAN_KINDS[$slug] ?? abort(404);
    }

    private function challan(string $kind, string $no): KnitCcChallan
    {
        return KnitCcChallan::query()->where('kind', $this->challanKind($kind))->where('challan_no', $no)->firstOrFail();
    }

    private function assertStatus(string $no, string $status, array $from, string $action, string $group): void
    {
        if (! in_array($status, $from, true)) {
            throw ValidationException::withMessages(['status' => __('knitting.cc.v.cannot', [
                'no' => $no, 'status' => __("knitting.cc.{$group}.{$status}"), 'action' => __("knitting.cc.action.{$action}"),
            ])]);
        }
    }

    /** @return array<string, mixed> */
    private function productionRules(string $p): array
    {
        $company = app(TenantContext::class)->companyId();

        return [
            "{$p}prod_date" => ['required', 'date'], "{$p}source" => ['required', Rule::in(KnitCcProduction::SOURCES)],
            "{$p}floor_id" => ['nullable', Rule::exists('floors', 'id')->where('company_id', $company)],
            "{$p}machine_id" => ['nullable', Rule::exists('knit_machines', 'id')->where('company_id', $company)],
            "{$p}shift_id" => ['nullable', Rule::exists('hr_shifts', 'id')->where('company_id', $company)],
            "{$p}operator_id" => ['nullable', Rule::exists('hr_employees', 'id')->where('company_id', $company)],
            "{$p}program_no" => ['nullable', 'string', 'max:24'],
            "{$p}qty" => ['required', 'numeric', 'gt:0', 'max:99999999'], "{$p}reject_qty" => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            "{$p}remarks" => ['nullable', 'string', 'max:300'],
        ];
    }

    /** @return array<string, string> */
    private function productionMessages(string $p): array
    {
        return ["{$p}prod_date.required" => __('knitting.cc.v.prod_date'), "{$p}source.required" => __('knitting.cc.v.source'),
            "{$p}qty.required" => __('knitting.cc.v.prod_qty'), "{$p}qty.gt" => __('knitting.cc.v.prod_qty')];
    }

    /** @return array<string, mixed> */
    private function productionLine(array $l): array
    {
        $program = null;
        if (! empty($l['program_no'])) {
            $program = KnitCcProgram::query()->where('program_no', $l['program_no'])->whereIn('status', KnitCcProgram::LIVE)->first();
            if (! $program) {
                throw ValidationException::withMessages(['program_no' => __('knitting.cc.v.prod_program', ['no' => $l['program_no']])]);
            }
            if ($l['source'] !== 'SC_OUT_HOUSE') {
                throw ValidationException::withMessages(['program_no' => __('knitting.cc.v.prod_program_source')]);
            }
        }

        return collect($l)->only(['prod_date', 'source', 'floor_id', 'machine_id', 'shift_id', 'operator_id', 'remarks'])->map(fn ($v) => $v === '' ? null : $v)->all()
            + ['floor_id' => null, 'machine_id' => null, 'shift_id' => null, 'operator_id' => null, 'remarks' => null]
            + ['program_id' => $program?->id, 'qty' => round((float) $l['qty'], 4), 'reject_qty' => round((float) ($l['reject_qty'] ?? 0), 4)];
    }

    /** @return list<array<string, mixed>> */
    private function presentRequirements($rows): array
    {
        $figures = KnitCollarCuff::figures($rows);
        $buyers = DB::table('buyers')->whereIn('id', $rows->pluck('buyer_id')->filter())->get(['id', 'name', 'short_name'])->mapWithKeys(fn ($b) => [$b->id => $b->short_name ?: $b->name]);
        $orders = KnitCollarCuff::orderLabels($rows->pluck('order_id'));
        $colours = DB::table('colours')->whereIn('id', $rows->pluck('colour_id')->filter())->pluck('name', 'id');
        $categories = collect(KnitProduction::categories())->pluck('label', 'value');
        $counts = KnitCcProduction::query()->whereIn('requirement_id', $rows->pluck('id'))->groupBy('requirement_id')->selectRaw('requirement_id, count(*) as n')->pluck('n', 'requirement_id');

        return $rows->map(fn (KnitCcRequirement $r) => collect($r->only(['req_no', 'req_type', 'category', 'buyer_id', 'order_id', 'work_style_no', 'ship_month', 'item', 'description',
            'colour_id', 'size_desc', 'gauge', 'uom', 'remarks', 'status']))->all() + [
                'req_in_house' => round($r->req_in_house, 3), 'req_sc' => round($r->req_sc, 3), 'prod_lines' => (int) ($counts[$r->id] ?? 0),
                'buyer' => $buyers[$r->buyer_id] ?? null, 'order' => $orders[$r->order_id] ?? null,
                'colour' => $colours[$r->colour_id] ?? null, 'category_label' => $categories[$r->category] ?? $r->category,
            ] + $figures[$r->id])->values()->all();
    }

    /** @return array<string, mixed> */
    private function presentProductions(KnitCcRequirement $req): array
    {
        $lines = KnitCcProduction::query()->where('requirement_id', $req->id)->orderBy('prod_date')->orderBy('created_at')->get();
        $programs = KnitCcProgram::query()->whereIn('id', $lines->pluck('program_id')->filter())->pluck('program_no', 'id');

        return $this->presentRequirements(collect([$req->refresh()]))[0] + [
            'lines' => $lines->map(fn (KnitCcProduction $l) => collect($l->only(['id', 'source', 'floor_id', 'machine_id', 'shift_id', 'operator_id', 'remarks']))->all() + [
                'prod_date' => $l->prod_date->toDateString(), 'program_no' => $programs[$l->program_id] ?? null, 'qty' => round($l->qty, 3), 'reject_qty' => round($l->reject_qty, 3),
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentProgram(KnitCcProgram $p): array
    {
        $lines = KnitCcProgramLine::query()->where('program_id', $p->id)->orderBy('sort_order')->get();
        $received = KnitCollarCuff::received($lines->pluck('id'));
        $party = DB::table('mp_suppliers')->where('id', $p->party_id)->first(['name', 'address']);
        $colours = DB::table('colours')->whereIn('id', $lines->pluck('colour_id')->filter())->pluck('name', 'id');

        return ['id' => $p->id, 'program_no' => $p->program_no, 'program_date' => $p->program_date->toDateString(), 'delivery_date' => $p->delivery_date?->toDateString(),
            'party_id' => $p->party_id, 'party' => $party?->name, 'party_address' => $party?->address, 'cpi' => $p->cpi, 'buyer_id' => $p->buyer_id,
            'buyer' => $p->buyer_id ? DB::table('buyers')->where('id', $p->buyer_id)->value('name') : null, 'order_id' => $p->order_id,
            'order' => KnitCollarCuff::orderLabel($p->order_id), 'work_style_no' => $p->work_style_no, 'remarks' => $p->remarks, 'status' => $p->status,
            'company' => DB::table('companies')->where('id', $p->company_id)->value('name'),
            'lines' => $lines->map(fn (KnitCcProgramLine $l) => collect($l->only(['id', 'item', 'description', 'colour_id', 'size_desc', 'gauge', 'uom', 'remarks']))->all() + [
                'colour' => $colours[$l->colour_id] ?? null, 'qty' => round($l->qty, 3), 'rate' => round($l->rate, 4), 'amount' => round($l->amount, 2),
                'received' => $received[$l->id] ?? 0.0,
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentChallan(KnitCcChallan $c): array
    {
        $lines = KnitCcChallanLine::query()->where('challan_id', $c->id)->orderBy('sort_order')->get();
        $reqs = KnitCcRequirement::query()->whereIn('id', $lines->pluck('requirement_id')->filter())->get()->keyBy('id');
        $orders = KnitCollarCuff::orderLabels($lines->pluck('order_id'));
        $colours = DB::table('colours')->whereIn('id', $lines->pluck('colour_id')->filter())->pluck('name', 'id');
        $partLines = KnitCcProgramLine::query()->whereIn('id', $lines->pluck('program_line_id')->filter())->get()->keyBy('id');
        $programs = KnitCcProgram::query()->whereIn('id', $partLines->pluck('program_id'))->get()->keyBy('id');
        $first = $programs->first();

        return ['id' => $c->id, 'challan_no' => $c->challan_no, 'kind' => $c->kind, 'challan_type' => $c->challan_type, 'challan_date' => $c->challan_date->toDateString(),
            'rcv_date' => $c->rcv_date?->toDateString(), 'gate_pass_no' => $c->gate_pass_no, 'vehicle_no' => $c->vehicle_no, 'from_floor_id' => $c->from_floor_id,
            'from' => $c->from_floor_id ? DB::table('floors')->where('id', $c->from_floor_id)->value('name') : null, 'store_id' => $c->store_id,
            'store' => $c->store_id ? DB::table('stores')->where('id', $c->store_id)->value('name') : null, 'remarks' => $c->remarks, 'status' => $c->status,
            'company' => DB::table('companies')->where('id', $c->company_id)->value('name'),
            'program_no' => $programs->pluck('program_no')->unique()->implode(', ') ?: null,
            'party' => $first ? DB::table('mp_suppliers')->where('id', $first->party_id)->value('name') : null,
            'lines' => $lines->map(function (KnitCcChallanLine $l) use ($reqs, $orders, $colours, $partLines, $programs, $c) {
                $r = $reqs[$l->requirement_id] ?? null;
                $pl = $partLines[$l->program_line_id] ?? null;

                return collect($l->only(['item', 'description', 'colour_id', 'size_desc', 'order_id', 'uom', 'remarks', 'program_line_id']))->all() + [
                    'req_no' => $r?->req_no, 'work_style_no' => $r?->work_style_no, 'order' => $orders[$l->order_id] ?? null, 'colour' => $colours[$l->colour_id] ?? null,
                    'qty' => round($l->qty, 3), 'received' => $c->status === 'RECEIVED' ? round($l->qty, 3) : 0.0,
                    'program_part' => $pl ? trim(($programs[$pl->program_id]->program_no ?? '').' · '.__("knitting.cc.item.{$pl->item}").' · '.($pl->description ?? ''), ' ·') : null,
                ];
            })->values()->all(),
        ];
    }
}
