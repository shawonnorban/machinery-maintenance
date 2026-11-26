<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\Knitting\Models\KnitCcProgram;
use App\Modules\Knitting\Models\KnitCcRequirement;
use App\Modules\Knitting\Models\KnitCcYarnIssue;
use App\Modules\Knitting\Models\KnitCcYarnReq;
use App\Modules\Knitting\Models\KnitCcYarnReqLine;
use App\Modules\Knitting\Models\YarnLot;
use App\Modules\Knitting\Support\KnitCollarCuff;
use App\Modules\Knitting\Support\KnitProduction;
use App\Modules\Knitting\Support\YarnProcurement;
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
 * Knit Production → Collar/Cuff (AnnotechERP 15/06), the yarn the flat-bed
 * parts need: Solid / Yarn-Dyed Collar & Cuff Indent (CYS- / CYD-…), as
 * Brickland raises them —
 *
 * - an order's open flat-knit parts load into the grid (Load Plan), one
 *   line per yarn: item, count, brand, lot; a yarn-dyed line names its lot,
 *   because two lots of one shade are two shades on a collar;
 * - saving writes a draft; Submit sends it to the store, Approve lets yarn
 *   be issued; Cancel while nothing has gone out, Close what is left;
 * - Issue Against Requisition: what the store releases against a line,
 *   off a stock line in the indent's store (CYS-…/n), posted to the yarn
 *   ledger; the indent's standing follows — Partly Issued, Issued.
 */
class KnitCcYarnApiController extends ApiController
{
    private const KINDS = ['solid' => 'SOLID', 'yd' => 'YD'];

    private const PREFIX = ['SOLID' => 'CYS', 'YD' => 'CYD'];

    /** The ledger kind an indent's releases post under. */
    private const MOVE = 'CC_YARN_ISSUE';

    /** New Order/Kp: the orders with open flat-knit parts, and those parts. */
    public function plans(): JsonResponse
    {
        $this->allowEither('knitting.production.view', 'knitting.ystore.view');
        $rows = KnitCcRequirement::query()->where('status', 'OPEN')->whereNotNull('order_id')->orderBy('req_no')->get();
        $orders = DB::table('merch_orders')->whereIn('id', $rows->pluck('order_id'))->get(['id', 'order_no', 'job_no', 'style_no', 'work_style_no', 'buyer_id'])->keyBy('id');

        return ApiResponse::ok($rows->groupBy('order_id')->map(function ($mine, $orderId) use ($orders) {
            $o = $orders[$orderId] ?? null;

            return ['order_id' => $orderId, 'order_no' => $o?->order_no, 'style' => $o ? ($o->work_style_no ?: $o->style_no ?: $o->job_no) : null, 'buyer_id' => $o?->buyer_id,
                'parts' => $mine->count(), 'req' => round((float) $mine->sum(fn ($r) => $r->req_in_house + $r->req_sc), 3),
                'lines' => $mine->map(fn (KnitCcRequirement $r) => ['req_no' => $r->req_no, 'item' => $r->item, 'description' => $r->description, 'colour_id' => $r->colour_id,
                    'buyer_id' => $r->buyer_id ?? $o?->buyer_id, 'order_id' => $r->order_id])->values()->all()];
        })->values()->all());
    }

    /** Stock lines holding yarn (in a store, given): what a release is drawn off. */
    public function lots(Request $request): JsonResponse
    {
        $this->allowEither('knitting.production.view', 'knitting.ystore.view');

        return ApiResponse::ok(YarnStore::lots($request->query('store') ?: null));
    }

    public function reqs(string $kind): JsonResponse
    {
        $this->allowEither('knitting.production.view', 'knitting.ystore.view');
        $rows = KnitCcYarnReq::query()->where('kind', $this->kind($kind))->orderByDesc('req_date')->orderByDesc('req_no')->limit(500)->get();
        $lines = KnitCcYarnReqLine::query()->whereIn('req_id', $rows->pluck('id'))->get();
        $issued = KnitCollarCuff::issued($lines->pluck('id'));
        $byReq = $lines->groupBy('req_id');
        $orders = KnitCollarCuff::orderLabels($rows->pluck('order_id'));
        $programs = KnitCcProgram::query()->whereIn('id', $rows->pluck('program_id')->filter())->pluck('program_no', 'id');
        $stores = DB::table('stores')->whereIn('id', $rows->pluck('store_id')->filter())->pluck('name', 'id');

        return ApiResponse::ok($rows->map(function (KnitCcYarnReq $r) use ($byReq, $issued, $orders, $programs, $stores) {
            $mine = $byReq[$r->id] ?? collect();
            $out = (float) $mine->sum(fn ($l) => $issued[$l->id] ?? 0);

            return ['req_no' => $r->req_no, 'req_date' => $r->req_date->toDateString(), 'order_id' => $r->order_id, 'order' => $orders[$r->order_id] ?? null,
                'knit_plan_no' => $r->knit_plan_no, 'program_no' => $programs[$r->program_id] ?? null, 'store' => $stores[$r->store_id] ?? null,
                'lines' => $mine->count(), 'req_qty' => round((float) $mine->sum('req_qty'), 3), 'issued' => round($out, 3),
                'balance' => round(max(0, (float) $mine->sum('req_qty') - $out), 3), 'status' => $r->status];
        })->values()->all());
    }

    public function showReq(string $kind, string $no): JsonResponse
    {
        $this->allowEither('knitting.production.view', 'knitting.ystore.view');

        return ApiResponse::ok($this->present($this->req($kind, $no)));
    }

    public function saveReq(Request $request, string $kind, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $k = $this->kind($kind);
        $row = $no ? $this->req($kind, $no) : null;
        $row && $this->assertStatus($row->req_no, $row->status, ['DRAFT'], 'save');
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'req_date' => ['required', 'date'], 'knit_plan_no' => ['nullable', 'string', 'max:60'], 'program_no' => ['nullable', 'string', 'max:24'],
            'buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $company)], 'work_style_no' => ['nullable', 'string', 'max:60'],
            'store_id' => ['nullable', Rule::exists('stores', 'id')->where('company_id', $company)], 'req_type' => ['nullable', Rule::in(YarnStore::REQ_TYPES)],
            'remarks' => ['nullable', 'string', 'max:2000'], 'lines' => ['present', 'array', 'max:300'],
            'lines.*.req_no' => ['nullable', 'string', 'max:24'], 'lines.*.buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'lines.*.order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $company)],
            'lines.*.fabric_type_id' => ['nullable', Rule::exists('fabric_types', 'id')->where('company_id', $company)],
            'lines.*.colour_id' => ['nullable', Rule::exists('colours', 'id')->where('company_id', $company)],
            'lines.*.composition_id' => ['required', Rule::exists('fiber_compositions', 'id')->where('company_id', $company)],
            'lines.*.yarn_count_id' => ['nullable', Rule::exists('knit_yarn_counts', 'id')->where('company_id', $company)],
            'lines.*.brand' => ['nullable', 'string', 'max:80'], 'lines.*.lot_no' => [$k === 'YD' ? 'required' : 'nullable', 'string', 'max:40'],
            'lines.*.req_qty' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'lines.*.uom' => ['nullable', 'string', 'max:16', UomCodes::rule($company)],
            'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'req_date.required' => __('knitting.cc.v.req_date'), 'lines.*.composition_id.required' => __('knitting.cc.v.yarn'),
            'lines.*.lot_no.required' => __('knitting.cc.v.yd_lot'), 'lines.*.req_qty.required' => __('knitting.cc.v.req_line_qty'),
            'lines.*.req_qty.gt' => __('knitting.cc.v.req_line_qty'),
        ]);
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('knitting.cc.v.yarn')]);
        }
        $program = null;
        if (! empty($data['program_no'])) {
            $program = KnitCcProgram::query()->where('program_no', $data['program_no'])->whereIn('status', KnitCcProgram::LIVE)->first();
            if (! $program) {
                throw ValidationException::withMessages(['program_no' => __('knitting.cc.v.prod_program', ['no' => $data['program_no']])]);
            }
        }
        $reqs = KnitCcRequirement::query()->whereIn('req_no', array_filter(array_column($lines, 'req_no')))->pluck('id', 'req_no');
        if (! empty($data['order_id'])) {
            $data['buyer_id'] ??= DB::table('merch_orders')->where('id', $data['order_id'])->value('buyer_id');
        }
        $row = DB::transaction(function () use ($row, $data, $lines, $k, $program, $reqs): KnitCcYarnReq {
            $head = collect($data)->except(['lines', 'program_no'])->map(fn ($v) => $v === '' ? null : $v)->all()
                + ['knit_plan_no' => null, 'buyer_id' => null, 'order_id' => null, 'work_style_no' => null, 'store_id' => null, 'req_type' => null, 'remarks' => null]
                + ['program_id' => $program?->id];
            $row ??= KnitCcYarnReq::query()->create($head + ['kind' => $k, 'req_no' => KnitProduction::nextNumber(self::PREFIX[$k], 'knit_cc_yarn_reqs', 'req_no', $data['req_date']),
                'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            $row->update($head);
            KnitCcYarnReqLine::query()->where('req_id', $row->id)->delete();
            foreach ($lines as $i => $l) {
                KnitCcYarnReqLine::query()->create(collect($l)->only(['buyer_id', 'order_id', 'fabric_type_id', 'colour_id', 'composition_id', 'yarn_count_id', 'brand', 'remarks'])
                    ->map(fn ($v) => $v === '' ? null : $v)->all() + [
                        'company_id' => $row->company_id, 'req_id' => $row->id, 'sort_order' => $i + 1, 'requirement_id' => $reqs[$l['req_no'] ?? ''] ?? null,
                        'lot_no' => isset($l['lot_no']) ? (mb_strtoupper(trim((string) $l['lot_no'])) ?: null) : null,
                        'req_qty' => round((float) $l['req_qty'], 4), 'uom' => UomCodes::normalize($l['uom'] ?? null) ?: 'KG',
                    ]);
            }

            return $row;
        });

        return $no ? ApiResponse::ok($this->present($row->refresh())) : ApiResponse::created($this->present($row->refresh()));
    }

    public function transition(string $kind, string $no, string $action): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $row = $this->req($kind, $no);
        [$from, $to] = match ($action) {
            'submit' => [['DRAFT'], 'SUBMITTED'],
            'approve' => [['SUBMITTED'], 'APPROVED'],
            'close' => [['APPROVED', 'PARTLY_ISSUED'], 'CLOSED'],
            'cancel' => [['DRAFT', 'SUBMITTED', 'APPROVED'], 'CANCELLED'],
            default => abort(404),
        };
        $this->assertStatus($row->req_no, $row->status, $from, $action);
        if ($action === 'cancel' && KnitCcYarnIssue::query()->where('req_id', $row->id)->exists()) {
            throw ValidationException::withMessages(['status' => __('knitting.cc.v.req_issued', ['no' => $row->req_no])]);
        }
        $row->update(['status' => $to] + ($action === 'approve' ? ['approved_at' => now(), 'approved_by' => $this->caller()->auditUserId()] : []));

        return ApiResponse::ok($this->present($row->refresh()));
    }

    /** Issue Against Requisition: one release, a line at a time, each off a stock line. */
    public function issue(Request $request, string $kind, string $no): JsonResponse
    {
        $this->allowEither('knitting.ystore.manage', 'knitting.production.manage');
        $row = $this->req($kind, $no);
        if (! in_array($row->status, KnitCcYarnReq::ISSUABLE, true)) {
            throw ValidationException::withMessages(['status' => __('knitting.cc.v.not_issuable', ['no' => $row->req_no, 'status' => __("knitting.cc.yarn_status.{$row->status}")])]);
        }
        $data = $request->validate([
            'issue_date' => ['required', 'date'], 'lines' => ['present', 'array', 'max:300'], 'lines.*.req_line_id' => ['required', 'string'],
            'lines.*.lot_id' => ['required', 'string'], 'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'],
        ], [
            'issue_date.required' => __('knitting.cc.v.issue_date'), 'lines.*.lot_id.required' => __('knitting.cc.v.issue_lot'),
            'lines.*.qty.required' => __('knitting.cc.v.issue_qty'), 'lines.*.qty.gt' => __('knitting.cc.v.issue_qty'),
        ]);
        YarnStore::assertOpen($data['issue_date'], 'issue_date');
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('knitting.cc.v.issue_none')]);
        }
        $mine = KnitCcYarnReqLine::query()->where('req_id', $row->id)->get()->keyBy('id');
        $had = KnitCollarCuff::issued($mine->keys());
        $names = YarnProcurement::names();
        $taking = [];
        $drawing = [];
        foreach ($lines as $i => $l) {
            $n = $i + 1;
            $line = $mine[$l['req_line_id']] ?? null;
            $lot = YarnLot::query()->find($l['lot_id']);
            if (! $line) {
                throw ValidationException::withMessages(["lines.{$i}.req_line_id" => __('knitting.cc.v.issue_line', ['n' => $n])]);
            }
            if (! $lot || ($row->store_id && $lot->store_id !== $row->store_id) || $lot->composition_id !== $line->composition_id
                || ($line->yarn_count_id && $lot->yarn_count_id !== $line->yarn_count_id) || ($line->lot_no && mb_strtoupper((string) $lot->lot_no) !== $line->lot_no)) {
                throw ValidationException::withMessages(["lines.{$i}.lot_id" => __('knitting.cc.v.issue_lot_match', ['n' => $n,
                    'yarn' => trim(($names['compositions'][$line->composition_id] ?? '').' '.($names['counts'][$line->yarn_count_id] ?? ''))])]);
            }
            $taking[$line->id] = ($taking[$line->id] ?? 0) + (float) $l['qty'];
            if ((float) ($had[$line->id] ?? 0) + $taking[$line->id] > $line->req_qty + 0.00005) {
                throw ValidationException::withMessages(["lines.{$i}.qty" => __('knitting.cc.v.issue_over', ['n' => $n,
                    'left' => round(max(0, $line->req_qty - (float) ($had[$line->id] ?? 0)), 3)])]);
            }
            $drawing[$lot->id] = ($drawing[$lot->id] ?? 0) + (float) $l['qty'];
            $stock = YarnStore::balance($lot->id);
            if ($drawing[$lot->id] > $stock + 0.00005) {
                throw ValidationException::withMessages(["lines.{$i}.qty" => __('knitting.cc.v.issue_stock', ['n' => $n, 'stock' => $stock])]);
            }
        }
        DB::transaction(function () use ($row, $data, $lines): void {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ["knit_cc_yarn_issues:{$row->id}"]);
            $issueNo = $row->req_no.'/'.(1 + KnitCcYarnIssue::query()->where('req_id', $row->id)->distinct()->count('issue_no'));
            $entries = [];
            foreach ($lines as $l) {
                $issue = KnitCcYarnIssue::query()->create(['req_id' => $row->id, 'req_line_id' => $l['req_line_id'], 'issue_no' => $issueNo, 'issue_date' => $data['issue_date'],
                    'lot_id' => $l['lot_id'], 'qty' => round((float) $l['qty'], 4), 'created_by' => $this->caller()->auditUserId()]);
                $rate = (float) YarnLot::query()->whereKey($l['lot_id'])->value('rate');
                $entries[] = ['lot_id' => $l['lot_id'], 'qty' => -$issue->qty, 'value' => -$issue->qty * $rate, 'doc_line_id' => $issue->id];
            }
            YarnStore::post(self::MOVE, $issueNo, $data['issue_date'], $entries);
            KnitCollarCuff::syncIssued($row->refresh());
        });

        return ApiResponse::ok($this->present($row->refresh()));
    }

    /* ---------------- helpers ---------------- */

    private function kind(string $slug): string
    {
        return self::KINDS[$slug] ?? abort(404);
    }

    private function req(string $kind, string $no): KnitCcYarnReq
    {
        return KnitCcYarnReq::query()->where('kind', $this->kind($kind))->where('req_no', $no)->firstOrFail();
    }

    private function assertStatus(string $no, string $status, array $from, string $action): void
    {
        if (! in_array($status, $from, true)) {
            throw ValidationException::withMessages(['status' => __('knitting.cc.v.cannot', [
                'no' => $no, 'status' => __("knitting.cc.yarn_status.{$status}"), 'action' => __("knitting.cc.action.{$action}"),
            ])]);
        }
    }

    /** @return array<string, mixed> */
    private function present(KnitCcYarnReq $r): array
    {
        $lines = KnitCcYarnReqLine::query()->where('req_id', $r->id)->orderBy('sort_order')->get();
        $issued = KnitCollarCuff::issued($lines->pluck('id'));
        $names = YarnProcurement::names();
        $reqs = KnitCcRequirement::query()->whereIn('id', $lines->pluck('requirement_id')->filter())->pluck('req_no', 'id');
        $orders = KnitCollarCuff::orderLabels($lines->pluck('order_id')->push($r->order_id));
        $colours = DB::table('colours')->whereIn('id', $lines->pluck('colour_id')->filter())->pluck('name', 'id');
        $fabrics = DB::table('fabric_types')->whereIn('id', $lines->pluck('fabric_type_id')->filter())->pluck('name', 'id');
        $issues = KnitCcYarnIssue::query()->where('req_id', $r->id)->orderBy('created_at')->get();
        $lots = YarnLot::query()->whereIn('id', $issues->pluck('lot_id'))->get()->keyBy('id');
        $byLine = $lines->keyBy('id');

        return ['id' => $r->id, 'req_no' => $r->req_no, 'kind' => $r->kind, 'req_date' => $r->req_date->toDateString(), 'knit_plan_no' => $r->knit_plan_no,
            'program_no' => $r->program_id ? KnitCcProgram::query()->whereKey($r->program_id)->value('program_no') : null, 'buyer_id' => $r->buyer_id,
            'buyer' => $r->buyer_id ? DB::table('buyers')->where('id', $r->buyer_id)->value('name') : null, 'order_id' => $r->order_id, 'order' => $orders[$r->order_id] ?? null,
            'work_style_no' => $r->work_style_no, 'store_id' => $r->store_id, 'store' => $r->store_id ? DB::table('stores')->where('id', $r->store_id)->value('name') : null,
            'req_type' => $r->req_type, 'remarks' => $r->remarks, 'status' => $r->status, 'company' => DB::table('companies')->where('id', $r->company_id)->value('name'),
            'lines' => $lines->map(fn (KnitCcYarnReqLine $l) => collect($l->only(['id', 'buyer_id', 'order_id', 'fabric_type_id', 'colour_id', 'composition_id', 'yarn_count_id',
                'brand', 'lot_no', 'uom', 'remarks']))->all() + [
                    'req_no' => $reqs[$l->requirement_id] ?? null, 'order' => $orders[$l->order_id] ?? null, 'colour' => $colours[$l->colour_id] ?? null,
                    'fabric_type' => $fabrics[$l->fabric_type_id] ?? null, 'yarn' => $names['compositions'][$l->composition_id] ?? null,
                    'count' => $names['counts'][$l->yarn_count_id] ?? null, 'req_qty' => round($l->req_qty, 3), 'issued' => round((float) ($issued[$l->id] ?? 0), 3),
                ])->values()->all(),
            'issues' => $issues->map(fn (KnitCcYarnIssue $i) => ['issue_no' => $i->issue_no, 'issue_date' => $i->issue_date->toDateString(),
                'line' => $byLine[$i->req_line_id]?->sort_order, 'lot' => isset($lots[$i->lot_id]) ? YarnStore::describe($lots[$i->lot_id], $names)['label'] : null,
                'qty' => round($i->qty, 3)])->values()->all(),
        ];
    }
}
