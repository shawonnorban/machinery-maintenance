<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\Knitting\Models\KnitTwistBill;
use App\Modules\Knitting\Models\KnitTwistBillLine;
use App\Modules\Knitting\Models\KnitTwistProgram;
use App\Modules\Knitting\Models\KnitTwistProgramLine;
use App\Modules\Knitting\Models\KnitTwistReceive;
use App\Modules\Knitting\Models\KnitTwistReceiveLine;
use App\Modules\Knitting\Support\KnitProduction;
use App\Modules\Knitting\Support\YarnProcurement;
use App\Modules\Knitting\Support\YarnStore;
use App\Modules\Knitting\Support\YarnTesting;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Knit Production → Production (AnnotechERP 15/04) → Yarn Twisting Entry, as
 * Brickland works it, in three papers:
 *
 * - Twist Program (TWP-…): a twisting job placed on a party, one line per
 *   yarn going out single and coming back twisted (Grey(Kg), TPI, ply, rate).
 *   Its status is set on the form. Once anything has been received against
 *   it, its yarn lines are not rewritten — what the party was sent is what
 *   they were sent.
 * - Twist Yarn Receive (TWR-…): twisted yarn back against a programme's
 *   lines — Grey(Kg) accounted for, Receive(Kg) arrived (the difference is
 *   the twisting loss), cones. Never more grey than a line sent out.
 *   Finalized fixes the weights so a bill may be raised; Transfer records
 *   that the yarn was taken onto the store, a separate later decision.
 * - Yarn Twist Bill (TWB-…): what the twister is owed — each line prices one
 *   finalised challan, or is an agreed adjustment that says so in its
 *   particulars; the amount is qty × rate, worked out here.
 */
class KnitTwistApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('knitting.production.view');
        $o = YarnTesting::options();
        $names = YarnProcurement::names();
        $programs = KnitTwistProgram::query()->orderByDesc('program_date')->orderByDesc('program_no')->limit(500)->get();
        $lines = KnitTwistProgramLine::query()->whereIn('program_id', $programs->pluck('id'))->orderBy('sort_order')->get()->groupBy('program_id');
        $receives = KnitTwistReceive::query()->orderByDesc('receive_date')->orderByDesc('challan_no')->limit(500)->get();
        $recvLines = KnitTwistReceiveLine::query()->whereIn('receive_id', $receives->pluck('id'))->get()->groupBy('receive_id');
        $programNos = $programs->pluck('program_no', 'id');

        return ApiResponse::ok(collect($o)->only(['company', 'stores', 'suppliers', 'colour_groups', 'uoms', 'lots', 'yarn_names'])->all() + [
            'statuses' => KnitTwistProgram::STATUSES, 'bill_statuses' => KnitTwistBill::STATUSES,
            // A programme as Program Ref# names it, with its lines for the receive desk.
            'programs' => $programs->map(fn (KnitTwistProgram $p) => [
                'value' => $p->program_no, 'label' => $p->program_no.' — '.$p->program_date->format('d M Y'), 'supplier_id' => $p->supplier_id, 'store_id' => $p->store_id,
                'status' => $p->status,
                'lines' => ($lines[$p->id] ?? collect())->map(fn (KnitTwistProgramLine $l) => [
                    'value' => $l->id, 'label' => trim(implode(' · ', array_filter([trim(($names['compositions'][$l->composition_id] ?? '').' '.($names['counts'][$l->yarn_count_id] ?? '')), $l->colour_group, $l->brand, $l->lot_no]))),
                    'colour_group' => $l->colour_group, 'brand' => $l->brand, 'lot_no' => $l->lot_no, 'uom' => $l->uom, 'grey_qty' => $l->grey_qty, 'received' => $l->received_qty,
                    'composition_id' => $l->composition_id, 'yarn_count_id' => $l->yarn_count_id, 'rate' => $l->rate,
                ])->values()->all(),
            ])->values()->all(),
            // Finalised challans, for the bill's lines.
            'challans' => $receives->map(fn (KnitTwistReceive $r) => [
                'value' => $r->challan_no, 'label' => $r->challan_no.' — '.$r->receive_date->format('d M Y'), 'supplier_id' => $r->supplier_id, 'program' => $programNos[$r->program_id] ?? null,
                'finalized' => $r->finalized, 'qty' => round((float) ($recvLines[$r->id] ?? collect())->sum('receive_qty'), 3),
            ])->values()->all(),
            'bills' => KnitTwistBill::query()->orderByDesc('bill_date')->orderByDesc('bill_no')->limit(500)->get(['bill_no', 'bill_date'])
                ->map(fn (KnitTwistBill $b) => ['value' => $b->bill_no, 'label' => $b->bill_no.' — '.$b->bill_date->format('d M Y')])->values()->all(),
        ]);
    }

    /* ---------------- Twist Program ---------------- */

    public function programs(): JsonResponse
    {
        $this->allow('knitting.production.view');
        $rows = KnitTwistProgram::query()->orderByDesc('program_date')->orderByDesc('program_no')->limit(500)->get();
        $lines = KnitTwistProgramLine::query()->whereIn('program_id', $rows->pluck('id'))->get()->groupBy('program_id');
        $suppliers = DB::table('mp_suppliers')->whereIn('id', $rows->pluck('supplier_id')->unique())->pluck('name', 'id');

        return ApiResponse::ok($rows->map(function (KnitTwistProgram $p) use ($lines, $suppliers) {
            $l = $lines[$p->id] ?? collect();
            $grey = round((float) $l->sum('grey_qty'), 3);
            $recv = round((float) $l->sum('received_qty'), 3);

            return [
                'program_no' => $p->program_no, 'program_date' => $p->program_date->toDateString(), 'target_date' => $p->target_date?->toDateString(),
                'supplier_id' => $p->supplier_id, 'supplier' => $suppliers[$p->supplier_id] ?? null, 'grey' => $grey, 'received' => $recv,
                'balance' => round(max(0, $grey - $recv), 3), 'remarks' => $p->remarks, 'status' => $p->status,
            ];
        })->values()->all());
    }

    public function showProgram(string $no): JsonResponse
    {
        $this->allow('knitting.production.view');

        return ApiResponse::ok($this->presentProgram($this->program($no)));
    }

    public function saveProgram(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $row = $no ? $this->program($no) : null;
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'program_date' => ['required', 'date'], 'target_date' => ['nullable', 'date', 'after_or_equal:program_date'],
            'supplier_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'store_id' => ['nullable', Rule::exists('stores', 'id')->where('company_id', $company)],
            'status' => ['required', Rule::in(KnitTwistProgram::STATUSES)], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:100'],
            'lines.*.id' => ['nullable', 'string'],
            'lines.*.composition_id' => ['nullable', Rule::exists('fiber_compositions', 'id')->where('company_id', $company)],
            'lines.*.yarn_count_id' => ['nullable', Rule::exists('knit_yarn_counts', 'id')->where('company_id', $company)],
            'lines.*.colour_group' => ['nullable', Rule::in(YarnProcurement::COLOURS)], 'lines.*.brand' => ['nullable', 'string', 'max:80'],
            'lines.*.lot_no' => ['nullable', 'string', 'max:40'], 'lines.*.grey_qty' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'lines.*.tpi' => ['nullable', 'numeric', 'min:0', 'max:999'], 'lines.*.ply' => ['nullable', 'integer', 'min:1', 'max:20'],
            'lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:99999999'], 'lines.*.uom' => ['required', 'string', 'max:16', UomCodes::rule($company)],
            'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'program_date.required' => __('knitting.tw.v.program_date'), 'supplier_id.required' => __('knitting.tw.v.supplier'), 'status.required' => __('knitting.tw.v.status'),
            'lines.*.grey_qty.required' => __('knitting.tw.v.grey'), 'lines.*.grey_qty.gt' => __('knitting.tw.v.grey'), 'lines.*.uom.required' => __('knitting.tw.v.uom'),
        ]);
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('knitting.tw.v.no_lines')]);
        }
        $received = $row && KnitTwistReceive::query()->where('program_id', $row->id)->exists();
        if ($received && $this->linesChanged($row, $lines)) {
            throw ValidationException::withMessages(['lines' => __('knitting.tw.v.received', ['no' => $row->program_no])]);
        }
        $row = DB::transaction(function () use ($row, $data, $lines, $received): KnitTwistProgram {
            $head = collect($data)->only(['program_date', 'target_date', 'supplier_id', 'store_id', 'status', 'remarks'])->map(fn ($v) => $v === '' ? null : $v)->all()
                + ['target_date' => null, 'store_id' => null, 'remarks' => null];
            $row ??= KnitTwistProgram::query()->create($head + [
                'program_no' => KnitProduction::nextNumber('TWP', 'knit_twist_programs', 'program_no', $data['program_date']), 'created_by' => $this->caller()->auditUserId(),
            ]);
            $row->update($head);
            if (! $received) {
                KnitTwistProgramLine::query()->where('program_id', $row->id)->delete();
                foreach ($lines as $i => $l) {
                    KnitTwistProgramLine::query()->create(['company_id' => $row->company_id, 'program_id' => $row->id, 'sort_order' => $i + 1, 'grey_qty' => (float) $l['grey_qty'],
                        'uom' => UomCodes::normalize($l['uom'])]
                        + collect($l)->only(['composition_id', 'yarn_count_id', 'colour_group', 'brand', 'lot_no', 'tpi', 'ply', 'rate', 'remarks'])->map(fn ($v) => $v === '' ? null : $v)->all());
                }
            }

            return $row;
        });

        return $no ? ApiResponse::ok($this->presentProgram($row->refresh())) : ApiResponse::created($this->presentProgram($row->refresh()));
    }

    /* ---------------- Twist Yarn Receive ---------------- */

    public function receives(): JsonResponse
    {
        $this->allow('knitting.production.view');
        $rows = KnitTwistReceive::query()->orderByDesc('receive_date')->orderByDesc('challan_no')->limit(500)->get();
        $lines = KnitTwistReceiveLine::query()->whereIn('receive_id', $rows->pluck('id'))->get()->groupBy('receive_id');
        $suppliers = DB::table('mp_suppliers')->whereIn('id', $rows->pluck('supplier_id')->unique())->pluck('name', 'id');
        $programs = KnitTwistProgram::query()->whereIn('id', $rows->pluck('program_id')->unique())->pluck('program_no', 'id');

        return ApiResponse::ok($rows->map(fn (KnitTwistReceive $r) => [
            'challan_no' => $r->challan_no, 'receive_date' => $r->receive_date->toDateString(), 'gate_pass_no' => $r->gate_pass_no, 'supplier_id' => $r->supplier_id,
            'party' => $suppliers[$r->supplier_id] ?? null, 'program_no' => $programs[$r->program_id] ?? null,
            'grey' => round((float) ($lines[$r->id] ?? collect())->sum('grey_qty'), 3), 'received' => round((float) ($lines[$r->id] ?? collect())->sum('receive_qty'), 3),
            'finalized' => $r->finalized, 'transferred' => $r->transferred,
        ])->values()->all());
    }

    public function showReceive(string $no): JsonResponse
    {
        $this->allow('knitting.production.view');

        return ApiResponse::ok($this->presentReceive($this->receive($no)));
    }

    public function saveReceive(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $row = $no ? $this->receive($no) : null;
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'receive_date' => ['required', 'date'], 'program_no' => ['required', 'string'], 'gate_pass_no' => ['nullable', 'string', 'max:40'],
            'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $company)], 'finalized' => ['boolean'], 'transferred' => ['boolean'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:100'],
            'lines.*.program_line_id' => ['required', 'string'], 'lines.*.colour_group' => ['nullable', Rule::in(YarnProcurement::COLOURS)],
            'lines.*.brand' => ['nullable', 'string', 'max:80'], 'lines.*.lot_no' => ['nullable', 'string', 'max:40'],
            'lines.*.grey_qty' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'lines.*.receive_qty' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.cones' => ['nullable', 'integer', 'min:0', 'max:999999'], 'lines.*.uom' => ['required', 'string', 'max:16', UomCodes::rule($company)],
            'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'receive_date.required' => __('knitting.tw.v.receive_date'), 'program_no.required' => __('knitting.tw.v.program'), 'store_id.required' => __('knitting.tw.v.store'),
            'lines.*.program_line_id.required' => __('knitting.tw.v.yarn'), 'lines.*.grey_qty.required' => __('knitting.tw.v.grey'), 'lines.*.grey_qty.gt' => __('knitting.tw.v.grey'),
            'lines.*.receive_qty.required' => __('knitting.tw.v.receive'), 'lines.*.uom.required' => __('knitting.tw.v.uom'),
        ]);
        $program = KnitTwistProgram::query()->where('program_no', $data['program_no'])->first();
        if (! $program) {
            throw ValidationException::withMessages(['program_no' => __('knitting.tw.v.program')]);
        }
        if ($row && $row->finalized) {
            // A finalised challan keeps its lines and weights; only Transfer may still be set.
            $row->update(['transferred' => $request->boolean('transferred', $row->transferred)]);

            return ApiResponse::ok($this->presentReceive($row->refresh()));
        }
        // A new challan needs a programme that is out with the party; one already written may still be put right.
        if (! $row && in_array($program->status, ['DRAFT', 'CANCELLED', 'CLOSED'], true)) {
            throw ValidationException::withMessages(['program_no' => __('knitting.tw.v.program_status', ['no' => $program->program_no, 'status' => __("knitting.tw.status.{$program->status}")])]);
        }
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('knitting.tw.v.no_receive_lines')]);
        }
        $programLines = KnitTwistProgramLine::query()->where('program_id', $program->id)->get()->keyBy('id');
        $before = $row ? KnitTwistReceiveLine::query()->where('receive_id', $row->id)->get()->groupBy('program_line_id')->map(fn ($g) => (float) $g->sum('grey_qty')) : collect();
        $taken = KnitTwistReceiveLine::query()->whereIn('program_line_id', $programLines->keys())->groupBy('program_line_id')->selectRaw('program_line_id, sum(grey_qty) as q')->pluck('q', 'program_line_id');
        $asked = [];
        foreach ($lines as $i => $l) {
            $pl = $programLines[$l['program_line_id']] ?? null;
            if (! $pl) {
                throw ValidationException::withMessages(["lines.{$i}.program_line_id" => __('knitting.tw.v.not_on_program', ['n' => $i + 1, 'no' => $program->program_no])]);
            }
            $asked[$pl->id] = ($asked[$pl->id] ?? 0) + (float) $l['grey_qty'];
            $left = (float) $pl->grey_qty - ((float) ($taken[$pl->id] ?? 0) - (float) ($before[$pl->id] ?? 0));
            if ($asked[$pl->id] > $left + 0.0005) {
                throw ValidationException::withMessages(["lines.{$i}.grey_qty" => __('knitting.tw.v.over', ['n' => $i + 1, 'left' => rtrim(rtrim(number_format(max(0, $left), 3, '.', ''), '0'), '.')])]);
            }
            if ((float) $l['receive_qty'] > (float) $l['grey_qty'] + 0.0005) {
                throw ValidationException::withMessages(["lines.{$i}.receive_qty" => __('knitting.tw.v.gain', ['n' => $i + 1])]);
            }
        }
        $row = DB::transaction(function () use ($row, $data, $lines, $program, $request): KnitTwistReceive {
            $head = ['receive_date' => $data['receive_date'], 'program_id' => $program->id, 'gate_pass_no' => $data['gate_pass_no'] ?? null, 'supplier_id' => $program->supplier_id,
                'store_id' => $data['store_id'], 'finalized' => $request->boolean('finalized'), 'transferred' => $request->boolean('transferred'), 'remarks' => $data['remarks'] ?? null];
            $row ??= KnitTwistReceive::query()->create($head + [
                'challan_no' => KnitProduction::nextNumber('TWR', 'knit_twist_receives', 'challan_no', $data['receive_date']), 'created_by' => $this->caller()->auditUserId(),
            ]);
            $old = KnitTwistReceiveLine::query()->where('receive_id', $row->id)->pluck('program_line_id');
            $row->update($head);
            KnitTwistReceiveLine::query()->where('receive_id', $row->id)->delete();
            foreach ($lines as $i => $l) {
                KnitTwistReceiveLine::query()->create(['company_id' => $row->company_id, 'receive_id' => $row->id, 'sort_order' => $i + 1, 'program_line_id' => $l['program_line_id'],
                    'grey_qty' => (float) $l['grey_qty'], 'receive_qty' => (float) $l['receive_qty'], 'uom' => UomCodes::normalize($l['uom'])]
                    + collect($l)->only(['colour_group', 'brand', 'lot_no', 'cones', 'remarks'])->map(fn ($v) => $v === '' ? null : $v)->all());
            }
            $this->syncReceived($old->merge(array_column($lines, 'program_line_id'))->unique());

            return $row;
        });

        return $no ? ApiResponse::ok($this->presentReceive($row->refresh())) : ApiResponse::created($this->presentReceive($row->refresh()));
    }

    /* ---------------- Yarn Twist Bill ---------------- */

    public function bills(): JsonResponse
    {
        $this->allow('knitting.production.view');
        $rows = KnitTwistBill::query()->orderByDesc('bill_date')->orderByDesc('bill_no')->limit(500)->get();
        $amounts = KnitTwistBillLine::query()->whereIn('bill_id', $rows->pluck('id'))->groupBy('bill_id')->selectRaw('bill_id, sum(amount) as a')->pluck('a', 'bill_id');
        $suppliers = DB::table('mp_suppliers')->whereIn('id', $rows->pluck('supplier_id')->unique())->pluck('name', 'id');

        return ApiResponse::ok($rows->map(fn (KnitTwistBill $b) => [
            'bill_no' => $b->bill_no, 'bill_date' => $b->bill_date->toDateString(), 'supplier_id' => $b->supplier_id, 'supplier' => $suppliers[$b->supplier_id] ?? null,
            'party_bill_no' => $b->party_bill_no, 'amount' => round((float) ($amounts[$b->id] ?? 0), 2), 'status' => $b->status,
        ])->values()->all());
    }

    public function showBill(string $no): JsonResponse
    {
        $this->allow('knitting.production.view');

        return ApiResponse::ok($this->presentBill($this->bill($no)));
    }

    public function saveBill(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.production.manage');
        $row = $no ? $this->bill($no) : null;
        if ($row && in_array($row->status, ['APPROVED', 'PAID'], true)) {
            throw ValidationException::withMessages(['status' => __('knitting.tw.v.bill_fixed', ['no' => $row->bill_no, 'status' => __("knitting.tw.bill_status.{$row->status}")])]);
        }
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'bill_date' => ['required', 'date'], 'supplier_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'program_no' => ['nullable', 'string'], 'party_bill_no' => ['nullable', 'string', 'max:40'], 'status' => ['required', Rule::in(KnitTwistBill::STATUSES)],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:100'],
            'lines.*.challan_no' => ['nullable', 'string'], 'lines.*.particulars' => ['nullable', 'string', 'max:200'],
            'lines.*.composition_id' => ['nullable', Rule::exists('fiber_compositions', 'id')->where('company_id', $company)],
            'lines.*.yarn_count_id' => ['nullable', Rule::exists('knit_yarn_counts', 'id')->where('company_id', $company)],
            'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'lines.*.rate' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.uom' => ['required', 'string', 'max:16', UomCodes::rule($company)], 'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'bill_date.required' => __('knitting.tw.v.bill_date'), 'supplier_id.required' => __('knitting.tw.v.supplier'), 'status.required' => __('knitting.tw.v.status'),
            'lines.*.qty.required' => __('knitting.tw.v.qty'), 'lines.*.qty.gt' => __('knitting.tw.v.qty'), 'lines.*.rate.required' => __('knitting.tw.v.rate'),
            'lines.*.uom.required' => __('knitting.tw.v.uom'),
        ]);
        $program = empty($data['program_no']) ? null : KnitTwistProgram::query()->where('program_no', $data['program_no'])->first();
        if (! empty($data['program_no']) && (! $program || $program->supplier_id !== $data['supplier_id'])) {
            throw ValidationException::withMessages(['program_no' => __('knitting.tw.v.program_party')]);
        }
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('knitting.tw.v.no_bill_lines')]);
        }
        $receives = [];
        foreach ($lines as $i => $l) {
            if (empty($l['challan_no'])) {
                if (trim((string) ($l['particulars'] ?? '')) === '') {
                    throw ValidationException::withMessages(["lines.{$i}.particulars" => __('knitting.tw.v.adjustment', ['n' => $i + 1])]);
                }

                continue;
            }
            $r = KnitTwistReceive::query()->where('challan_no', $l['challan_no'])->first();
            if (! $r || $r->supplier_id !== $data['supplier_id']) {
                throw ValidationException::withMessages(["lines.{$i}.challan_no" => __('knitting.tw.v.challan_party', ['n' => $i + 1])]);
            }
            if (! $r->finalized) {
                throw ValidationException::withMessages(["lines.{$i}.challan_no" => __('knitting.tw.v.not_final', ['no' => $r->challan_no])]);
            }
            $billed = KnitTwistBillLine::query()->where('receive_id', $r->id)->when($row, fn ($q) => $q->where('bill_id', '!=', $row->id))
                ->whereIn('bill_id', KnitTwistBill::query()->where('status', '!=', 'CANCELLED')->select('id'))->exists();
            if ($billed || in_array($r->id, $receives, true)) {
                throw ValidationException::withMessages(["lines.{$i}.challan_no" => __('knitting.tw.v.billed', ['no' => $r->challan_no])]);
            }
            $receives[$i] = $r->id;
        }
        $row = DB::transaction(function () use ($row, $data, $lines, $program, $receives): KnitTwistBill {
            $head = ['bill_date' => $data['bill_date'], 'supplier_id' => $data['supplier_id'], 'program_id' => $program?->id, 'party_bill_no' => $data['party_bill_no'] ?? null,
                'status' => $data['status'], 'remarks' => $data['remarks'] ?? null];
            $row ??= KnitTwistBill::query()->create($head + [
                'bill_no' => KnitProduction::nextNumber('TWB', 'knit_twist_bills', 'bill_no', $data['bill_date']), 'created_by' => $this->caller()->auditUserId(),
            ]);
            $row->update($head);
            KnitTwistBillLine::query()->where('bill_id', $row->id)->delete();
            foreach ($lines as $i => $l) {
                KnitTwistBillLine::query()->create([
                    'company_id' => $row->company_id, 'bill_id' => $row->id, 'sort_order' => $i + 1, 'receive_id' => $receives[$i] ?? null,
                    'particulars' => ($l['particulars'] ?? null) ?: null, 'composition_id' => ($l['composition_id'] ?? null) ?: null, 'yarn_count_id' => ($l['yarn_count_id'] ?? null) ?: null,
                    'qty' => (float) $l['qty'], 'rate' => (float) $l['rate'], 'amount' => round((float) $l['qty'] * (float) $l['rate'], 2),
                    'uom' => UomCodes::normalize($l['uom']), 'remarks' => ($l['remarks'] ?? null) ?: null,
                ]);
            }

            return $row;
        });

        return $no ? ApiResponse::ok($this->presentBill($row->refresh())) : ApiResponse::created($this->presentBill($row->refresh()));
    }

    /* ---------------- shared ---------------- */

    private function program(string $no): KnitTwistProgram
    {
        return KnitTwistProgram::query()->where('program_no', $no)->firstOrFail();
    }

    private function receive(string $no): KnitTwistReceive
    {
        return KnitTwistReceive::query()->where('challan_no', $no)->firstOrFail();
    }

    private function bill(string $no): KnitTwistBill
    {
        return KnitTwistBill::query()->where('bill_no', $no)->firstOrFail();
    }

    /** Whether the posted lines differ from what the party was sent. */
    private function linesChanged(KnitTwistProgram $p, array $lines): bool
    {
        $key = fn ($l) => implode('|', [$l['composition_id'] ?? '', $l['yarn_count_id'] ?? '', round((float) $l['grey_qty'], 3)]);
        $now = KnitTwistProgramLine::query()->where('program_id', $p->id)->orderBy('sort_order')->get()->map(fn ($l) => $key($l->toArray()))->all();

        return $now !== array_map($key, $lines);
    }

    /** A programme line's Received is what every receive challan has brought back against it. */
    private function syncReceived(Collection $lineIds): void
    {
        $sums = KnitTwistReceiveLine::query()->whereIn('program_line_id', $lineIds)->groupBy('program_line_id')->selectRaw('program_line_id, sum(receive_qty) as q')->pluck('q', 'program_line_id');
        foreach ($lineIds as $id) {
            KnitTwistProgramLine::query()->whereKey($id)->update(['received_qty' => round((float) ($sums[$id] ?? 0), 3)]);
        }
    }

    private function yarnName(?string $compositionId, ?string $countId, array $names): ?string
    {
        return trim(($names['compositions'][$compositionId] ?? '').' '.($names['counts'][$countId] ?? '')) ?: null;
    }

    /** @return array<string, mixed> */
    private function presentProgram(KnitTwistProgram $p): array
    {
        $names = YarnProcurement::names();
        $party = DB::table('mp_suppliers')->where('id', $p->supplier_id)->first(['name', 'address']);
        $lines = KnitTwistProgramLine::query()->where('program_id', $p->id)->orderBy('sort_order')->get();

        return [
            'id' => $p->id, 'program_no' => $p->program_no, 'program_date' => $p->program_date->toDateString(), 'target_date' => $p->target_date?->toDateString(),
            'supplier_id' => $p->supplier_id, 'supplier' => $party?->name, 'supplier_address' => $party?->address, 'store_id' => $p->store_id,
            'store' => $p->store_id ? DB::table('stores')->where('id', $p->store_id)->value('name') : null, 'status' => $p->status, 'remarks' => $p->remarks,
            'company' => DB::table('companies')->where('id', $p->company_id)->value('name'),
            'received' => KnitTwistReceive::query()->where('program_id', $p->id)->exists(),
            'lines' => $lines->map(fn (KnitTwistProgramLine $l) => collect($l->only(['id', 'composition_id', 'yarn_count_id', 'colour_group', 'brand', 'lot_no', 'grey_qty', 'tpi', 'ply', 'rate',
                'uom', 'received_qty', 'remarks']))->all() + ['yarn' => $this->yarnName($l->composition_id, $l->yarn_count_id, $names)])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentReceive(KnitTwistReceive $r): array
    {
        $names = YarnProcurement::names();
        $programLines = KnitTwistProgramLine::query()->where('program_id', $r->program_id)->get()->keyBy('id');

        return [
            'id' => $r->id, 'challan_no' => $r->challan_no, 'receive_date' => $r->receive_date->toDateString(),
            'program_no' => KnitTwistProgram::query()->whereKey($r->program_id)->value('program_no'), 'gate_pass_no' => $r->gate_pass_no,
            'supplier_id' => $r->supplier_id, 'party' => DB::table('mp_suppliers')->where('id', $r->supplier_id)->value('name'), 'store_id' => $r->store_id,
            'finalized' => $r->finalized, 'transferred' => $r->transferred, 'remarks' => $r->remarks,
            'lines' => KnitTwistReceiveLine::query()->where('receive_id', $r->id)->orderBy('sort_order')->get()->map(fn (KnitTwistReceiveLine $l) => collect($l->only(['program_line_id',
                'colour_group', 'brand', 'lot_no', 'grey_qty', 'receive_qty', 'cones', 'uom', 'remarks']))->all() + [
                    'yarn' => isset($programLines[$l->program_line_id]) ? $this->yarnName($programLines[$l->program_line_id]->composition_id, $programLines[$l->program_line_id]->yarn_count_id, $names) : null,
                ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentBill(KnitTwistBill $b): array
    {
        $names = YarnProcurement::names();
        $lines = KnitTwistBillLine::query()->where('bill_id', $b->id)->orderBy('sort_order')->get();
        $challans = KnitTwistReceive::query()->whereIn('id', $lines->pluck('receive_id')->filter())->pluck('challan_no', 'id');

        return [
            'id' => $b->id, 'bill_no' => $b->bill_no, 'bill_date' => $b->bill_date->toDateString(), 'supplier_id' => $b->supplier_id,
            'supplier' => DB::table('mp_suppliers')->where('id', $b->supplier_id)->value('name'),
            'program_no' => $b->program_id ? KnitTwistProgram::query()->whereKey($b->program_id)->value('program_no') : null,
            'party_bill_no' => $b->party_bill_no, 'status' => $b->status, 'remarks' => $b->remarks, 'amount' => round((float) $lines->sum('amount'), 2),
            'lines' => $lines->map(fn (KnitTwistBillLine $l) => [
                'challan_no' => $challans[$l->receive_id] ?? null, 'particulars' => $l->particulars, 'composition_id' => $l->composition_id, 'yarn_count_id' => $l->yarn_count_id,
                'yarn' => $this->yarnName($l->composition_id, $l->yarn_count_id, $names), 'qty' => $l->qty, 'rate' => $l->rate, 'amount' => $l->amount, 'uom' => $l->uom, 'remarks' => $l->remarks,
            ])->values()->all(),
        ];
    }
}
