<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\Knitting\Models\KnitScProgram;
use App\Modules\Knitting\Models\KnitYarnProgTransfer;
use App\Modules\Knitting\Models\KnitYarnProgTransferLine;
use App\Modules\Knitting\Models\KnitYdDyedReceipt;
use App\Modules\Knitting\Models\KnitYdDyedReceiptLine;
use App\Modules\Knitting\Models\KnitYdDyedTransfer;
use App\Modules\Knitting\Models\KnitYdDyedTransferLine;
use App\Modules\Knitting\Models\KnitYdGreyTransfer;
use App\Modules\Knitting\Models\KnitYdGreyTransferLine;
use App\Modules\Knitting\Models\KnitYdProgram;
use App\Modules\Knitting\Models\KnitYdProgramLine;
use App\Modules\Knitting\Models\YarnLot;
use App\Modules\Knitting\Support\KnitProduction;
use App\Modules\Knitting\Support\YarnDyeing;
use App\Modules\Knitting\Support\YarnProcurement;
use App\Modules\Knitting\Support\YarnStore;
use App\Modules\Merchandise\Models\Order;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Knit Production → Fabric Store (AnnotechERP 15/05), the yarn papers around
 * sub-contract and yarn-dyed work, as Brickland works them — each Draft →
 * Issued → Finalized, or Cancelled before it is finalised:
 *
 * - Out-House Yarn Transfer (OYT-…): an approved S/C programme and the yarn
 *   the knitter holds against it, handed to another outside knitter.
 *   Finalize moves the programme onto the receiving party. The yarn left
 *   the store when it first went out, so nothing posts.
 * - Grey Yarn Transfer for Dyeing (GYT-…): greige to a dye house against a
 *   Y/D programme's shades (or none, raised at the gate). Issue takes the
 *   cones off their stock lines; it counts in the programme's Issue.
 * - Dyed Yarn Receipt Challan (DYR-…): dyed yarn back against the grey
 *   challan it answers (G.Yarn Qty). Finalize is the store signing for the
 *   count — the cones go onto the yarn store, a stock line per dyed lot.
 * - Out-House Dyed Yarn Transfer Challan (DYT-…): finalised dyed yarn
 *   forwarded to an outside knitter, every line off one receive line and
 *   never more than that line still holds unforwarded. Issue takes it off.
 */
class KnitYarnTransferApiController extends ApiController
{
    private const STATUSES = ['DRAFT', 'ISSUED', 'FINALIZED', 'CANCELLED'];

    public function options(): JsonResponse
    {
        $this->allowEither('knitting.fstore.view', 'knitting.ystore.view');
        $o = YarnProcurement::options();
        $suppliers = collect($o['suppliers'])->pluck('label', 'value');
        $names = YarnProcurement::names();
        $programs = KnitYdProgram::query()->whereNotIn('status', ['CANCELLED', 'CLOSED'])->orderByDesc('program_date')->limit(500)->get();
        $figures = YarnDyeing::figures($programs);
        $lines = KnitYdProgramLine::query()->whereIn('program_id', $programs->pluck('id'))->orderBy('sort_order')->get()->groupBy('program_id');
        $ship = Order::query()->whereIn('id', $programs->pluck('order_id')->filter())->pluck('ship_date', 'id');

        return ApiResponse::ok(collect($o)->only(['company', 'stores', 'buyers', 'orders', 'suppliers', 'uoms', 'compositions', 'counts'])->all() + [
            'colours' => KnitProduction::options()['colours'], 'colour_groups' => YarnProcurement::COLOURS, 'yarn_names' => $names, 'statuses' => self::STATUSES,
            // Approved (running) S/C programmes: what a programme transfer hands over, and what a dyed yarn line is knitted against.
            'sc_programs' => KnitScProgram::query()->where('status', 'APPROVED')->orderByDesc('program_no')->limit(500)->get(['id', 'program_no', 'party_id'])
                ->map(fn ($p) => ['value' => $p->program_no, 'id' => $p->id, 'label' => $p->program_no.' — '.($suppliers[$p->party_id] ?? ''), 'party_id' => $p->party_id])->values()->all(),
            // Y/D programmes with what is still to send — the grey challan's list.
            'yd_programs' => $programs->map(fn (KnitYdProgram $p) => [
                'value' => $p->program_no, 'label' => $p->program_no.' — '.($suppliers[$p->supplier_id] ?? ''), 'party_id' => $p->supplier_id, 'party' => $suppliers[$p->supplier_id] ?? null,
                'ship_month' => isset($ship[$p->order_id]) ? $ship[$p->order_id]?->format('M Y') : null, 'buyer_id' => $p->buyer_id, 'order_id' => $p->order_id,
                'to_send' => round(max(0, $figures['programs'][$p->id]['qty'] - $figures['programs'][$p->id]['issue']), 4),
                'lines' => ($lines[$p->id] ?? collect())->map(fn (KnitYdProgramLine $l) => [
                    'value' => $l->id, 'label' => YarnDyeing::shadeLabel($l, $names), 'composition_id' => $l->composition_id, 'yarn_count_id' => $l->yarn_count_id,
                    'colour_id' => $l->colour_id, 'colour_name' => $l->colour_name, 'qty' => (float) $l->qty,
                    'to_send' => round(max(0, (float) $l->qty - ($figures['lines'][$l->id]['issued_share'] ?? 0)), 4),
                ])->values()->all(),
            ])->values()->all(),
            // Grey challans out and not yet answered in full — the dyed receipt desk's list, with their shades.
            'grey_challans' => $this->greyChallans($suppliers, $names),
            // Every stock line the yarn stores hold, for the grey challan's Yarn Item.
            'lots' => YarnStore::lots(null, true),
        ]);
    }

    /* ---------------- Out-House Yarn Transfer ---------------- */

    public function progTransfers(): JsonResponse
    {
        $this->allowEither('knitting.fstore.view', 'knitting.ystore.view');
        $rows = KnitYarnProgTransfer::query()->orderByDesc('challan_date')->orderByDesc('challan_no')->limit(500)->get();
        $qty = KnitYarnProgTransferLine::query()->whereIn('transfer_id', $rows->pluck('id'))->groupBy('transfer_id')->selectRaw('transfer_id, sum(qty) as q, sum(bags) as b')->get()->keyBy('transfer_id');
        $parties = $this->parties($rows->pluck('from_party_id')->merge($rows->pluck('to_party_id')));
        $progs = KnitScProgram::query()->whereIn('id', $rows->pluck('sc_program_id'))->pluck('program_no', 'id');

        return ApiResponse::ok($rows->map(fn (KnitYarnProgTransfer $t) => [
            'challan_no' => $t->challan_no, 'challan_date' => $t->challan_date->toDateString(), 'program_no' => $progs[$t->sc_program_id] ?? null,
            'from_party_id' => $t->from_party_id, 'from_party' => $parties[$t->from_party_id] ?? null, 'to_party_id' => $t->to_party_id, 'to_party' => $parties[$t->to_party_id] ?? null,
            'bags' => (int) ($qty[$t->id]->b ?? 0), 'qty' => round((float) ($qty[$t->id]->q ?? 0), 3), 'gate_pass_no' => $t->gate_pass_no, 'status' => $t->status,
        ])->values()->all());
    }

    public function showProgTransfer(string $no): JsonResponse
    {
        $this->allowEither('knitting.fstore.view', 'knitting.ystore.view');

        return ApiResponse::ok($this->presentProg($this->find(KnitYarnProgTransfer::class, $no)));
    }

    public function saveProgTransfer(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.fstore.manage');
        $row = $no ? $this->find(KnitYarnProgTransfer::class, $no) : null;
        $row && $this->assertStatus($row, ['DRAFT'], 'save');
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'challan_date' => ['required', 'date'], 'program_no' => ['required', 'string'],
            'to_party_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'store_id' => ['nullable', Rule::exists('stores', 'id')->where('company_id', $company)], 'gate_pass_no' => ['nullable', 'string', 'max:40'],
            'vehicle_no' => ['nullable', 'string', 'max:40'], 'note' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:200'], 'lines.*.composition_id' => ['nullable', Rule::exists('fiber_compositions', 'id')->where('company_id', $company)],
            'lines.*.yarn_count_id' => ['nullable', Rule::exists('knit_yarn_counts', 'id')->where('company_id', $company)],
            'lines.*.colour_group' => ['nullable', Rule::in(YarnProcurement::COLOURS)], 'lines.*.shade' => ['nullable', 'string', 'max:40'], 'lines.*.lot_no' => ['nullable', 'string', 'max:40'],
            'lines.*.bags' => ['nullable', 'integer', 'min:0', 'max:99999'], 'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'lines.*.uom' => ['required', 'string', 'max:16', UomCodes::rule($company)], 'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'challan_date.required' => __('knitting.ytr.v.date'), 'program_no.required' => __('knitting.ytr.v.sc_program'), 'to_party_id.required' => __('knitting.ytr.v.to_party'),
            'lines.*.qty.required' => __('knitting.ytr.v.qty'), 'lines.*.qty.gt' => __('knitting.ytr.v.qty'), 'lines.*.uom.required' => __('knitting.ytr.v.uom'),
        ]);
        $program = KnitScProgram::query()->where('program_no', $data['program_no'])->first()
            ?? throw ValidationException::withMessages(['program_no' => __('knitting.ytr.v.sc_program')]);
        if ($program->status !== 'APPROVED') {
            throw ValidationException::withMessages(['program_no' => __('knitting.ytr.v.program_running', ['no' => $program->program_no])]);
        }
        if ($program->party_id === $data['to_party_id']) {
            throw ValidationException::withMessages(['to_party_id' => __('knitting.ytr.v.same_party')]);
        }
        $lines = YarnStore::inOrder($data['lines']);
        foreach ($lines as $i => $l) {
            if (empty($l['composition_id']) && empty($l['yarn_count_id'])) {
                throw ValidationException::withMessages(["lines.{$i}.composition_id" => __('knitting.ytr.v.yarn', ['n' => $i + 1])]);
            }
        }
        $row = DB::transaction(function () use ($row, $data, $lines, $program): KnitYarnProgTransfer {
            $head = collect($data)->only(['challan_date', 'to_party_id', 'store_id', 'gate_pass_no', 'vehicle_no', 'note'])->map(fn ($v) => $v === '' ? null : $v)->all()
                + ['store_id' => null, 'gate_pass_no' => null, 'vehicle_no' => null, 'note' => null] + ['sc_program_id' => $program->id, 'from_party_id' => $program->party_id];
            $row ??= KnitYarnProgTransfer::query()->create($head + ['challan_no' => $this->next('OYT', 'knit_yarn_prog_transfers', $data['challan_date']), 'created_by' => $this->caller()->auditUserId()]);
            $row->update($head);
            KnitYarnProgTransferLine::query()->where('transfer_id', $row->id)->delete();
            foreach ($lines as $i => $l) {
                KnitYarnProgTransferLine::query()->create(['company_id' => $row->company_id, 'transfer_id' => $row->id, 'sort_order' => $i + 1, 'qty' => (float) $l['qty'],
                    'uom' => UomCodes::normalize($l['uom'])] + collect($l)->only(['composition_id', 'yarn_count_id', 'colour_group', 'shade', 'lot_no', 'bags', 'remarks'])->map(fn ($v) => $v === '' ? null : $v)->all());
            }

            return $row;
        });

        return $this->saved($no, $this->presentProg($row->refresh()));
    }

    public function progTransition(string $no, string $action): JsonResponse
    {
        $this->allow('knitting.fstore.manage');
        $row = $this->find(KnitYarnProgTransfer::class, $no);
        $to = $this->move($row, $action);
        DB::transaction(function () use ($row, $action, $to): void {
            if ($action === 'finalize') {
                $program = KnitScProgram::query()->lockForUpdate()->findOrFail($row->sc_program_id);
                if ($program->party_id !== $row->from_party_id) {
                    throw ValidationException::withMessages(['status' => __('knitting.ytr.v.program_moved', ['no' => $program->program_no])]);
                }
                $program->update(['party_id' => $row->to_party_id]);
            }
            $row->update(['status' => $to]);
        });

        return ApiResponse::ok($this->presentProg($row->refresh()));
    }

    /* ---------------- Grey Yarn Transfer for Dyeing ---------------- */

    public function greyTransfers(): JsonResponse
    {
        $this->allowEither('knitting.fstore.view', 'knitting.ystore.view');
        $rows = KnitYdGreyTransfer::query()->orderByDesc('challan_date')->orderByDesc('challan_no')->limit(500)->get();
        $qty = KnitYdGreyTransferLine::query()->whereIn('transfer_id', $rows->pluck('id'))->groupBy('transfer_id')->selectRaw('transfer_id, sum(qty) as q')->pluck('q', 'transfer_id');
        $parties = $this->parties($rows->pluck('party_id'));
        $progs = KnitYdProgram::query()->whereIn('id', $rows->pluck('program_id')->filter())->pluck('program_no', 'id');
        $stores = DB::table('stores')->whereIn('id', $rows->pluck('store_id'))->pluck('name', 'id');

        return ApiResponse::ok($rows->map(fn (KnitYdGreyTransfer $t) => [
            'challan_no' => $t->challan_no, 'challan_date' => $t->challan_date->toDateString(), 'party_id' => $t->party_id, 'party' => $parties[$t->party_id] ?? null,
            'program_no' => $progs[$t->program_id] ?? null, 'store' => $stores[$t->store_id] ?? null, 'qty' => round((float) ($qty[$t->id] ?? 0), 3),
            'gate_pass_no' => $t->gate_pass_no, 'status' => $t->status,
        ])->values()->all());
    }

    public function showGreyTransfer(string $no): JsonResponse
    {
        $this->allowEither('knitting.fstore.view', 'knitting.ystore.view');

        return ApiResponse::ok($this->presentGrey($this->find(KnitYdGreyTransfer::class, $no)));
    }

    public function saveGreyTransfer(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.fstore.manage');
        $row = $no ? $this->find(KnitYdGreyTransfer::class, $no) : null;
        $row && $this->assertStatus($row, ['DRAFT'], 'save');
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'challan_date' => ['required', 'date'], 'gate_pass_no' => ['nullable', 'string', 'max:40'], 'vehicle_no' => ['nullable', 'string', 'max:40'],
            'party_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)], 'program_no' => ['nullable', 'string'],
            'store_id' => ['nullable', Rule::exists('stores', 'id')->where('company_id', $company)], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:200'], 'lines.*.program_line_id' => ['nullable', 'string'], 'lines.*.lot_id' => ['required', 'string'],
            'lines.*.colour_id' => ['nullable', Rule::exists('colours', 'id')->where('company_id', $company)], 'lines.*.colour_name' => ['nullable', 'string', 'max:80'],
            'lines.*.pack_qty' => ['nullable', 'integer', 'min:0', 'max:99999'], 'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'lines.*.uom' => ['required', 'string', 'max:16', UomCodes::rule($company)], 'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'challan_date.required' => __('knitting.ytr.v.date'), 'party_id.required' => __('knitting.ytr.v.party'),
            'lines.*.lot_id.required' => __('knitting.ytr.v.lot'), 'lines.*.qty.required' => __('knitting.ytr.v.qty'), 'lines.*.qty.gt' => __('knitting.ytr.v.qty'),
            'lines.*.uom.required' => __('knitting.ytr.v.uom'),
        ]);
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('knitting.ytr.v.no_shades')]);
        }
        $program = null;
        if (! empty($data['program_no'])) {
            $program = KnitYdProgram::query()->where('program_no', $data['program_no'])->whereNotIn('status', ['CANCELLED', 'CLOSED'])->first()
                ?? throw ValidationException::withMessages(['program_no' => __('knitting.ytr.v.yd_program')]);
        }
        $shades = $program ? KnitYdProgramLine::query()->where('program_id', $program->id)->pluck('id')->all() : [];
        // Left blank, the store is the one the first line's cones sit in; every line leaves from it.
        $data['store_id'] = ($data['store_id'] ?? null) ?: YarnLot::query()->whereKey($lines[0]['lot_id'])->value('store_id');
        $out = [];
        foreach ($lines as $i => $l) {
            $n = $i + 1;
            if (! empty($l['program_line_id']) && ! in_array($l['program_line_id'], $shades, true)) {
                throw ValidationException::withMessages(["lines.{$i}.program_line_id" => __('knitting.ytr.v.shade', ['n' => $n])]);
            }
            $lot = YarnLot::query()->whereKey($l['lot_id'])->where('store_id', $data['store_id'])->first()
                ?? throw ValidationException::withMessages(["lines.{$i}.lot_id" => __('knitting.ytr.v.lot_store', ['n' => $n])]);
            $out[] = ['program_line_id' => ($l['program_line_id'] ?? '') ?: null, 'lot_id' => $lot->id, 'colour_id' => ($l['colour_id'] ?? null) ?: null,
                'colour_name' => (($l['colour_name'] ?? '') ?: $lot->colour) ?: null, 'lot_no' => $lot->lot_no, 'pack_qty' => $l['pack_qty'] ?? null,
                'qty' => round((float) $l['qty'], 4), 'uom' => UomCodes::normalize($l['uom']), 'remarks' => ($l['remarks'] ?? '') ?: null];
        }
        $this->checkLots(collect($out), 'lot_id');
        $row = DB::transaction(function () use ($row, $data, $out, $program): KnitYdGreyTransfer {
            $head = collect($data)->only(['challan_date', 'gate_pass_no', 'vehicle_no', 'party_id', 'store_id', 'remarks'])->map(fn ($v) => $v === '' ? null : $v)->all()
                + ['gate_pass_no' => null, 'vehicle_no' => null, 'remarks' => null] + ['program_id' => $program?->id];
            $row ??= KnitYdGreyTransfer::query()->create($head + ['challan_no' => $this->next('GYT', 'knit_yd_grey_transfers', $data['challan_date']), 'created_by' => $this->caller()->auditUserId()]);
            $row->update($head);
            KnitYdGreyTransferLine::query()->where('transfer_id', $row->id)->delete();
            foreach ($out as $i => $l) {
                KnitYdGreyTransferLine::query()->create($l + ['company_id' => $row->company_id, 'transfer_id' => $row->id, 'sort_order' => $i + 1]);
            }

            return $row;
        });

        return $this->saved($no, $this->presentGrey($row->refresh()));
    }

    public function greyTransition(string $no, string $action): JsonResponse
    {
        $this->allow('knitting.fstore.manage');
        $row = $this->find(KnitYdGreyTransfer::class, $no);
        $to = $this->move($row, $action);
        DB::transaction(function () use ($row, $action, $to): void {
            $date = $row->challan_date->toDateString();
            $lines = KnitYdGreyTransferLine::query()->where('transfer_id', $row->id)->get();
            if ($action === 'issue') {
                YarnStore::assertOpen($date, 'challan_date');
                $this->checkLots($lines, 'lot_id');
                YarnStore::post('YD_GREY_OUT', $row->challan_no, $date, $lines->map(fn ($l) => [
                    'lot_id' => $l->lot_id, 'qty' => -(float) $l->qty, 'value' => -(float) $l->qty * (float) YarnLot::query()->whereKey($l->lot_id)->value('rate'), 'doc_line_id' => $l->id,
                ])->values()->all());
            }
            if ($action === 'cancel' && $row->status === 'ISSUED') {
                YarnStore::assertOpen($date, 'challan_date');
                YarnStore::unpost('YD_GREY_OUT', $row->challan_no);
            }
            $row->update(['status' => $to]);
        });

        return ApiResponse::ok($this->presentGrey($row->refresh()));
    }

    /* ---------------- Dyed Yarn Receipt Challan ---------------- */

    public function dyedReceipts(): JsonResponse
    {
        $this->allowEither('knitting.fstore.view', 'knitting.ystore.view');
        $rows = KnitYdDyedReceipt::query()->orderByDesc('challan_date')->orderByDesc('challan_no')->limit(500)->get();
        $lines = KnitYdDyedReceiptLine::query()->whereIn('receipt_id', $rows->pluck('id'))->get();
        $forwarded = $this->forwarded($lines->pluck('id'));
        $byReceipt = $lines->groupBy('receipt_id');
        $grey = $this->greyQty($rows->pluck('grey_transfer_id'));
        $parties = $this->parties($rows->pluck('party_id'));
        $progs = KnitYdProgram::query()->whereIn('id', $rows->pluck('program_id')->filter())->pluck('program_no', 'id');
        $greyNo = KnitYdGreyTransfer::query()->whereIn('id', $rows->pluck('grey_transfer_id')->filter())->pluck('challan_no', 'id');
        $orders = Order::query()->whereIn('id', $rows->pluck('order_id')->filter())->get(['id', 'order_no', 'style_no', 'work_style_no'])->keyBy('id');

        return ApiResponse::ok($rows->map(function (KnitYdDyedReceipt $r) use ($byReceipt, $forwarded, $grey, $parties, $progs, $greyNo, $orders) {
            $mine = $byReceipt[$r->id] ?? collect();
            $qty = round((float) $mine->sum('qty'), 3);
            $fwd = round((float) $mine->sum(fn ($l) => $forwarded[$l->id] ?? 0), 3);
            $o = $orders[$r->order_id] ?? null;

            return [
                'challan_no' => $r->challan_no, 'challan_date' => $r->challan_date->toDateString(), 'party_id' => $r->party_id, 'party' => $parties[$r->party_id] ?? null,
                'grey_challan' => $greyNo[$r->grey_transfer_id] ?? null, 'g_yarn_qty' => round((float) ($grey[$r->grey_transfer_id] ?? 0), 3), 'program_no' => $progs[$r->program_id] ?? null,
                'buyer_id' => $r->buyer_id, 'order_id' => $r->order_id, 'order' => $o ? trim(($o->work_style_no ?: $o->style_no).' · '.$o->order_no, ' ·') : null,
                'qty' => $qty, 'forwarded' => $fwd, 'transferred' => $qty > 0 && $fwd >= $qty - 0.0005, 'status' => $r->status,
            ];
        })->values()->all());
    }

    public function showDyedReceipt(string $no): JsonResponse
    {
        $this->allowEither('knitting.fstore.view', 'knitting.ystore.view');

        return ApiResponse::ok($this->presentDyed($this->find(KnitYdDyedReceipt::class, $no)));
    }

    public function saveDyedReceipt(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.fstore.manage');
        $row = $no ? $this->find(KnitYdDyedReceipt::class, $no) : null;
        $row && $this->assertStatus($row, ['DRAFT'], 'save');
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'challan_date' => ['required', 'date'], 'gate_pass_no' => ['nullable', 'string', 'max:40'],
            'party_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)], 'grey_challan' => ['nullable', 'string'],
            'program_no' => ['nullable', 'string'], 'buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $company)],
            'store_id' => ['nullable', Rule::exists('stores', 'id')->where('company_id', $company)], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:200'], 'lines.*.knit_prog' => ['nullable', 'string'], 'lines.*.collar_prog' => ['nullable', 'string'],
            'lines.*.composition_id' => ['nullable', Rule::exists('fiber_compositions', 'id')->where('company_id', $company)],
            'lines.*.yarn_count_id' => ['nullable', Rule::exists('knit_yarn_counts', 'id')->where('company_id', $company)],
            'lines.*.colour_id' => ['nullable', Rule::exists('colours', 'id')->where('company_id', $company)], 'lines.*.colour_name' => ['nullable', 'string', 'max:80'],
            'lines.*.lot_no' => ['required', 'string', 'max:40'], 'lines.*.pack_qty' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'lines.*.uom' => ['required', 'string', 'max:16', UomCodes::rule($company)],
            'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'challan_date.required' => __('knitting.ytr.v.date'), 'party_id.required' => __('knitting.ytr.v.party'), 'lines.*.lot_no.required' => __('knitting.ytr.v.lot_no'),
            'lines.*.qty.required' => __('knitting.ytr.v.qty'), 'lines.*.qty.gt' => __('knitting.ytr.v.qty'), 'lines.*.uom.required' => __('knitting.ytr.v.uom'),
        ]);
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('knitting.ytr.v.no_shades')]);
        }
        $grey = null;
        if (! empty($data['grey_challan'])) {
            $grey = KnitYdGreyTransfer::query()->where('challan_no', $data['grey_challan'])->whereIn('status', ['ISSUED', 'FINALIZED'])->first();
            if (! $grey || $grey->party_id !== $data['party_id']) {
                throw ValidationException::withMessages(['grey_challan' => __('knitting.ytr.v.grey_party')]);
            }
        }
        $program = ! empty($data['program_no']) ? KnitYdProgram::query()->where('program_no', $data['program_no'])->first() : ($grey?->program_id ? KnitYdProgram::query()->find($grey->program_id) : null);
        $progs = KnitScProgram::query()->pluck('id', 'program_no');
        $out = [];
        foreach ($lines as $i => $l) {
            if (empty($l['composition_id']) && empty($l['yarn_count_id'])) {
                throw ValidationException::withMessages(["lines.{$i}.composition_id" => __('knitting.ytr.v.yarn', ['n' => $i + 1])]);
            }
            $out[] = ['knit_prog_id' => $progs[$l['knit_prog'] ?? ''] ?? null, 'collar_prog_id' => $progs[$l['collar_prog'] ?? ''] ?? null, 'qty' => round((float) $l['qty'], 4),
                'uom' => UomCodes::normalize($l['uom']), 'lot_no' => mb_strtoupper(trim($l['lot_no']))]
                + collect($l)->only(['composition_id', 'yarn_count_id', 'colour_id', 'colour_name', 'pack_qty', 'remarks'])->map(fn ($v) => $v === '' ? null : $v)->all();
        }
        $row = DB::transaction(function () use ($row, $data, $out, $grey, $program): KnitYdDyedReceipt {
            $head = collect($data)->only(['challan_date', 'gate_pass_no', 'party_id', 'buyer_id', 'order_id', 'store_id', 'remarks'])->map(fn ($v) => $v === '' ? null : $v)->all()
                + ['gate_pass_no' => null, 'buyer_id' => $program?->buyer_id, 'order_id' => $program?->order_id, 'store_id' => null, 'remarks' => null]
                + ['grey_transfer_id' => $grey?->id, 'program_id' => $program?->id];
            $row ??= KnitYdDyedReceipt::query()->create($head + ['challan_no' => $this->next('DYR', 'knit_yd_dyed_receipts', $data['challan_date']), 'created_by' => $this->caller()->auditUserId()]);
            $row->update($head);
            KnitYdDyedReceiptLine::query()->where('receipt_id', $row->id)->delete();
            foreach ($out as $i => $l) {
                KnitYdDyedReceiptLine::query()->create($l + ['company_id' => $row->company_id, 'receipt_id' => $row->id, 'sort_order' => $i + 1]);
            }

            return $row;
        });

        return $this->saved($no, $this->presentDyed($row->refresh()));
    }

    public function dyedTransition(string $no, string $action): JsonResponse
    {
        $this->allow('knitting.fstore.manage');
        $row = $this->find(KnitYdDyedReceipt::class, $no);
        $to = $this->move($row, $action);
        DB::transaction(function () use ($row, $action, $to): void {
            if ($action === 'finalize') {
                if (! $row->store_id) {
                    throw ValidationException::withMessages(['store_id' => __('knitting.ytr.v.store_first')]);
                }
                $date = $row->challan_date->toDateString();
                YarnStore::assertOpen($date, 'challan_date');
                $entries = [];
                foreach (KnitYdDyedReceiptLine::query()->where('receipt_id', $row->id)->orderBy('sort_order')->get() as $l) {
                    $lot = YarnStore::lotFor([
                        'store_id' => $row->store_id, 'composition_id' => $l->composition_id, 'yarn_count_id' => $l->yarn_count_id, 'colour' => $l->colour_name ?: 'Yarn Dyed',
                        'lot_no' => $l->lot_no, 'supplier_id' => $row->party_id, 'buyer_id' => $row->buyer_id, 'order_id' => $row->order_id, 'uom' => $l->uom, 'source' => 'YARN_DYED',
                    ], $date);
                    $l->update(['lot_id' => $lot->id]);
                    $entries[] = ['lot_id' => $lot->id, 'qty' => (float) $l->qty, 'doc_line_id' => $l->id];
                }
                YarnStore::post('YD_DYED_IN', $row->challan_no, $date, $entries);
            }
            $row->update(['status' => $to]);
        });

        return ApiResponse::ok($this->presentDyed($row->refresh()));
    }

    /* ---------------- Out-House Dyed Yarn Transfer Challan ---------------- */

    /** Finalised dyed receipts still holding unforwarded yarn — the transfer desk's list, with their open lines. */
    public function forwardable(): JsonResponse
    {
        $this->allowEither('knitting.fstore.view', 'knitting.ystore.view');
        $rows = KnitYdDyedReceipt::query()->where('status', 'FINALIZED')->orderByDesc('challan_date')->limit(300)->get();
        $lines = KnitYdDyedReceiptLine::query()->whereIn('receipt_id', $rows->pluck('id'))->orderBy('sort_order')->get();
        $forwarded = $this->forwarded($lines->pluck('id'));
        $names = YarnProcurement::names();
        $progs = KnitScProgram::query()->pluck('program_no', 'id');
        $parties = $this->parties($rows->pluck('party_id'));

        return ApiResponse::ok($rows->map(function (KnitYdDyedReceipt $r) use ($lines, $forwarded, $names, $progs, $parties) {
            $open = $lines->where('receipt_id', $r->id)->map(fn ($l) => [
                'value' => $l->id, 'label' => $r->challan_no.' · '.$this->yarnName($l, $names).' · Lot '.$l->lot_no, 'yarn' => $this->yarnName($l, $names), 'colour_name' => $l->colour_name,
                'lot_no' => $l->lot_no, 'uom' => $l->uom, 'knit_prog' => $progs[$l->knit_prog_id] ?? null, 'collar_prog' => $progs[$l->collar_prog_id] ?? null,
                'left' => round((float) $l->qty - ($forwarded[$l->id] ?? 0), 4), 'pack_qty' => $l->pack_qty,
            ])->filter(fn ($x) => $x['left'] > 0.00005)->values();

            return ['value' => $r->challan_no, 'challan_date' => $r->challan_date->toDateString(), 'party_id' => $r->party_id, 'party' => $parties[$r->party_id] ?? null, 'store_id' => $r->store_id,
                'qty' => round((float) $open->sum('left'), 3), 'lines' => $open->all()];
        })->filter(fn ($x) => $x['lines'] !== [])->values()->all());
    }

    public function dyedTransfers(): JsonResponse
    {
        $this->allowEither('knitting.fstore.view', 'knitting.ystore.view');
        $rows = KnitYdDyedTransfer::query()->orderByDesc('challan_date')->orderByDesc('challan_no')->limit(500)->get();
        $qty = KnitYdDyedTransferLine::query()->whereIn('transfer_id', $rows->pluck('id'))->groupBy('transfer_id')->selectRaw('transfer_id, sum(qty) as q')->pluck('q', 'transfer_id');
        $parties = $this->parties($rows->pluck('party_id'));
        $stores = DB::table('stores')->whereIn('id', $rows->pluck('store_id')->filter())->pluck('name', 'id');

        return ApiResponse::ok($rows->map(fn (KnitYdDyedTransfer $t) => [
            'challan_no' => $t->challan_no, 'challan_date' => $t->challan_date->toDateString(), 'party_id' => $t->party_id, 'party' => $parties[$t->party_id] ?? null,
            'store' => $stores[$t->store_id] ?? null, 'qty' => round((float) ($qty[$t->id] ?? 0), 3), 'gate_pass_no' => $t->gate_pass_no, 'status' => $t->status,
        ])->values()->all());
    }

    public function showDyedTransfer(string $no): JsonResponse
    {
        $this->allowEither('knitting.fstore.view', 'knitting.ystore.view');

        return ApiResponse::ok($this->presentDyedTransfer($this->find(KnitYdDyedTransfer::class, $no)));
    }

    public function saveDyedTransfer(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.fstore.manage');
        $row = $no ? $this->find(KnitYdDyedTransfer::class, $no) : null;
        $row && $this->assertStatus($row, ['DRAFT'], 'save');
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'challan_date' => ['required', 'date'], 'gate_pass_no' => ['nullable', 'string', 'max:40'], 'vehicle_no' => ['nullable', 'string', 'max:40'],
            'party_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'store_id' => ['nullable', Rule::exists('stores', 'id')->where('company_id', $company)], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:200'], 'lines.*.receipt_line_id' => ['required', 'string'], 'lines.*.knit_prog' => ['nullable', 'string'],
            'lines.*.collar_prog' => ['nullable', 'string'], 'lines.*.pack_qty' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'lines.*.uom' => ['required', 'string', 'max:16', UomCodes::rule($company)],
            'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'challan_date.required' => __('knitting.ytr.v.date'), 'party_id.required' => __('knitting.ytr.v.knitter'), 'lines.*.receipt_line_id.required' => __('knitting.ytr.v.receive_line'),
            'lines.*.qty.required' => __('knitting.ytr.v.qty'), 'lines.*.qty.gt' => __('knitting.ytr.v.qty'), 'lines.*.uom.required' => __('knitting.ytr.v.uom'),
        ]);
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('knitting.ytr.v.no_lines')]);
        }
        $source = KnitYdDyedReceiptLine::query()->whereIn('id', collect($lines)->pluck('receipt_line_id'))->get()->keyBy('id');
        $final = KnitYdDyedReceipt::query()->whereIn('id', $source->pluck('receipt_id'))->where('status', 'FINALIZED')->get()->keyBy('id');
        $left = $this->forwarded($source->keys(), $row?->id);
        $progs = KnitScProgram::query()->pluck('id', 'program_no');
        $taking = [];
        $out = [];
        foreach ($lines as $i => $l) {
            $n = $i + 1;
            $src = $source[$l['receipt_line_id']] ?? null;
            if (! $src || ! isset($final[$src->receipt_id])) {
                throw ValidationException::withMessages(["lines.{$i}.receipt_line_id" => __('knitting.ytr.v.receive_final', ['n' => $n])]);
            }
            $taking[$src->id] = ($taking[$src->id] ?? 0) + (float) $l['qty'];
            $open = (float) $src->qty - ($left[$src->id] ?? 0);
            if ($taking[$src->id] > $open + 0.00005) {
                throw ValidationException::withMessages(["lines.{$i}.qty" => __('knitting.ytr.v.forward_over', ['n' => $n, 'left' => round($open, 3)])]);
            }
            $out[] = ['receipt_line_id' => $src->id, 'knit_prog_id' => $progs[$l['knit_prog'] ?? ''] ?? $src->knit_prog_id, 'collar_prog_id' => $progs[$l['collar_prog'] ?? ''] ?? $src->collar_prog_id,
                'pack_qty' => $l['pack_qty'] ?? null, 'qty' => round((float) $l['qty'], 4), 'uom' => UomCodes::normalize($l['uom']), 'remarks' => ($l['remarks'] ?? '') ?: null];
        }
        $row = DB::transaction(function () use ($row, $data, $out): KnitYdDyedTransfer {
            $head = collect($data)->only(['challan_date', 'gate_pass_no', 'vehicle_no', 'party_id', 'store_id', 'remarks'])->map(fn ($v) => $v === '' ? null : $v)->all()
                + ['gate_pass_no' => null, 'vehicle_no' => null, 'store_id' => null, 'remarks' => null];
            $row ??= KnitYdDyedTransfer::query()->create($head + ['challan_no' => $this->next('DYT', 'knit_yd_dyed_transfers', $data['challan_date']), 'created_by' => $this->caller()->auditUserId()]);
            $row->update($head);
            KnitYdDyedTransferLine::query()->where('transfer_id', $row->id)->delete();
            foreach ($out as $i => $l) {
                KnitYdDyedTransferLine::query()->create($l + ['company_id' => $row->company_id, 'transfer_id' => $row->id, 'sort_order' => $i + 1]);
            }

            return $row;
        });

        return $this->saved($no, $this->presentDyedTransfer($row->refresh()));
    }

    public function dyedTransferTransition(string $no, string $action): JsonResponse
    {
        $this->allow('knitting.fstore.manage');
        $row = $this->find(KnitYdDyedTransfer::class, $no);
        $to = $this->move($row, $action);
        DB::transaction(function () use ($row, $action, $to): void {
            $date = $row->challan_date->toDateString();
            if ($action === 'issue') {
                YarnStore::assertOpen($date, 'challan_date');
                $lines = KnitYdDyedTransferLine::query()->where('transfer_id', $row->id)->get();
                $lots = KnitYdDyedReceiptLine::query()->whereIn('id', $lines->pluck('receipt_line_id'))->pluck('lot_id', 'id');
                $entries = $lines->map(fn ($l) => ['lot_id' => $lots[$l->receipt_line_id], 'qty' => -(float) $l->qty, 'doc_line_id' => $l->id]);
                $this->checkLots($entries->map(fn ($e) => (object) ['lot_id' => $e['lot_id'], 'qty' => -$e['qty']]), 'lot_id');
                YarnStore::post('YD_DYED_OUT', $row->challan_no, $date, $entries->values()->all());
            }
            if ($action === 'cancel' && $row->status === 'ISSUED') {
                YarnStore::assertOpen($date, 'challan_date');
                YarnStore::unpost('YD_DYED_OUT', $row->challan_no);
            }
            $row->update(['status' => $to]);
        });

        return ApiResponse::ok($this->presentDyedTransfer($row->refresh()));
    }

    /* ---------------- helpers ---------------- */

    private function find(string $model, string $no): Model
    {
        return $model::query()->where('challan_no', $no)->firstOrFail();
    }

    private function next(string $prefix, string $table, string $date): string
    {
        return KnitProduction::nextNumber($prefix, $table, 'challan_no', $date);
    }

    private function saved(?string $no, array $body): JsonResponse
    {
        return $no ? ApiResponse::ok($body) : ApiResponse::created($body);
    }

    private function assertStatus(Model $row, array $from, string $action): void
    {
        if (! in_array($row->status, $from, true)) {
            throw ValidationException::withMessages(['status' => __('knitting.ytr.v.cannot', [
                'no' => $row->challan_no, 'status' => __("knitting.ytr.status.{$row->status}"), 'action' => __("knitting.ytr.action.{$action}"),
            ])]);
        }
    }

    /** The status a move lands on, after checking the paper may take it. */
    private function move(Model $row, string $action): string
    {
        [$from, $to] = match ($action) {
            'issue' => [['DRAFT'], 'ISSUED'],
            'finalize' => [['ISSUED'], 'FINALIZED'],
            'cancel' => [['DRAFT', 'ISSUED'], 'CANCELLED'],
            default => abort(404),
        };
        $this->assertStatus($row, $from, $action);

        return $to;
    }

    /** Never more off a yarn stock line than it holds. */
    private function checkLots(Collection $lines, string $key): void
    {
        foreach ($lines->groupBy($key) as $lotId => $mine) {
            $have = YarnStore::balance($lotId);
            if ($have + 0.00005 < (float) $mine->sum('qty')) {
                throw ValidationException::withMessages(['lines' => __('knitting.ytr.v.short', ['lot' => YarnLot::query()->whereKey($lotId)->value('lot_no') ?? '—', 'have' => round($have, 3)])]);
            }
        }
    }

    /** What has gone out on (non-cancelled) dyed transfers against each receive line. */
    private function forwarded($lineIds, ?string $except = null): array
    {
        $live = KnitYdDyedTransfer::query()->where('status', '!=', 'CANCELLED')->when($except, fn ($q) => $q->whereKeyNot($except))->pluck('id');

        return KnitYdDyedTransferLine::query()->whereIn('transfer_id', $live)->whereIn('receipt_line_id', collect($lineIds)->values())->groupBy('receipt_line_id')
            ->selectRaw('receipt_line_id, sum(qty) as q')->pluck('q', 'receipt_line_id')->map(fn ($q) => (float) $q)->all();
    }

    /**
     * Issued / finalised grey challans, with each shade's yarn and lot, and
     * the G.Yarn Qty still unanswered by (non-cancelled) dyed receipts.
     *
     * @return list<array<string, mixed>>
     */
    private function greyChallans(Collection $suppliers, array $names): array
    {
        $rows = KnitYdGreyTransfer::query()->whereIn('status', ['ISSUED', 'FINALIZED'])->orderByDesc('challan_date')->limit(300)->get();
        $lines = KnitYdGreyTransferLine::query()->whereIn('transfer_id', $rows->pluck('id'))->orderBy('sort_order')->get()->groupBy('transfer_id');
        $lots = YarnLot::query()->whereIn('id', $lines->flatten()->pluck('lot_id'))->get()->keyBy('id');
        $receipts = KnitYdDyedReceipt::query()->whereIn('grey_transfer_id', $rows->pluck('id'))->where('status', '!=', 'CANCELLED')->pluck('grey_transfer_id', 'id');
        $answered = [];
        foreach (KnitYdDyedReceiptLine::query()->whereIn('receipt_id', $receipts->keys())->get(['receipt_id', 'qty']) as $l) {
            $answered[$receipts[$l->receipt_id]] = ($answered[$receipts[$l->receipt_id]] ?? 0) + (float) $l->qty;
        }
        $programs = KnitYdProgram::query()->whereIn('id', $rows->pluck('program_id')->filter())->get()->keyBy('id');

        return $rows->map(function (KnitYdGreyTransfer $t) use ($lines, $lots, $answered, $programs, $suppliers, $names) {
            $mine = $lines[$t->id] ?? collect();
            $p = $programs[$t->program_id] ?? null;

            return [
                'value' => $t->challan_no, 'label' => $t->challan_no, 'challan_date' => $t->challan_date->toDateString(), 'party_id' => $t->party_id,
                'party' => $suppliers[$t->party_id] ?? null, 'qty' => round((float) $mine->sum('qty'), 3), 'received' => round((float) ($answered[$t->id] ?? 0), 3),
                'program_no' => $p?->program_no, 'buyer_id' => $p?->buyer_id, 'order_id' => $p?->order_id,
                'lines' => $mine->map(fn (KnitYdGreyTransferLine $l) => [
                    'composition_id' => $lots[$l->lot_id]?->composition_id, 'yarn_count_id' => $lots[$l->lot_id]?->yarn_count_id,
                    'yarn' => isset($lots[$l->lot_id]) ? $this->yarnName($lots[$l->lot_id], $names) : null,
                    'colour_id' => $l->colour_id, 'colour_name' => $l->colour_name, 'lot_no' => $l->lot_no, 'pack_qty' => $l->pack_qty, 'qty' => (float) $l->qty, 'uom' => $l->uom,
                ])->values()->all(),
            ];
        })->values()->all();
    }

    /** The greige weight on each grey transfer challan — a receipt's G.Yarn Qty. */
    private function greyQty($ids): array
    {
        return KnitYdGreyTransferLine::query()->whereIn('transfer_id', collect($ids)->filter()->values())->groupBy('transfer_id')
            ->selectRaw('transfer_id, sum(qty) as q')->pluck('q', 'transfer_id')->all();
    }

    private function parties($ids): Collection
    {
        return DB::table('mp_suppliers')->whereIn('id', collect($ids)->filter()->unique()->values())->pluck('name', 'id');
    }

    private function yarnName(Model $l, array $names): string
    {
        return trim(($names['compositions'][$l->composition_id] ?? '').' '.($names['counts'][$l->yarn_count_id] ?? ''));
    }

    /** @return array<string, mixed> */
    private function head(Model $r, string $partyKey = 'party_id'): array
    {
        $party = DB::table('mp_suppliers')->where('id', $r->{$partyKey})->first(['name', 'address']);

        return ['company' => DB::table('companies')->where('id', $r->company_id)->value('name'), 'party' => $party?->name, 'party_address' => $party?->address,
            'store_id' => $r->store_id, 'store' => $r->store_id ? DB::table('stores')->where('id', $r->store_id)->value('name') : null, 'status' => $r->status];
    }

    /** @return array<string, mixed> */
    private function presentProg(KnitYarnProgTransfer $t): array
    {
        $names = YarnProcurement::names();

        return ['id' => $t->id, 'challan_no' => $t->challan_no, 'challan_date' => $t->challan_date->toDateString(),
            'program_no' => KnitScProgram::query()->whereKey($t->sc_program_id)->value('program_no'), 'from_party_id' => $t->from_party_id,
            'from_party' => DB::table('mp_suppliers')->where('id', $t->from_party_id)->value('name'), 'to_party_id' => $t->to_party_id,
            'gate_pass_no' => $t->gate_pass_no, 'vehicle_no' => $t->vehicle_no, 'note' => $t->note] + $this->head($t, 'to_party_id') + [
                'lines' => KnitYarnProgTransferLine::query()->where('transfer_id', $t->id)->orderBy('sort_order')->get()->map(fn ($l) => collect($l->only([
                    'composition_id', 'yarn_count_id', 'colour_group', 'shade', 'lot_no', 'bags', 'qty', 'uom', 'remarks',
                ]))->all() + ['yarn' => $this->yarnName($l, $names) ?: null])->values()->all(),
            ];
    }

    /** @return array<string, mixed> */
    private function presentGrey(KnitYdGreyTransfer $t): array
    {
        $names = YarnProcurement::names();
        $shades = KnitYdProgramLine::query()->where('program_id', $t->program_id)->get()->keyBy('id');
        $lots = YarnLot::query()->whereIn('id', KnitYdGreyTransferLine::query()->where('transfer_id', $t->id)->pluck('lot_id'))->get()->keyBy('id');

        return ['id' => $t->id, 'challan_no' => $t->challan_no, 'challan_date' => $t->challan_date->toDateString(), 'gate_pass_no' => $t->gate_pass_no,
            'vehicle_no' => $t->vehicle_no, 'party_id' => $t->party_id, 'program_no' => $t->program_id ? KnitYdProgram::query()->whereKey($t->program_id)->value('program_no') : null,
            'remarks' => $t->remarks] + $this->head($t) + [
                'lines' => KnitYdGreyTransferLine::query()->where('transfer_id', $t->id)->orderBy('sort_order')->get()->map(fn ($l) => collect($l->only([
                    'program_line_id', 'lot_id', 'colour_id', 'colour_name', 'lot_no', 'pack_qty', 'qty', 'uom', 'remarks',
                ]))->all() + [
                    'shade' => isset($shades[$l->program_line_id]) ? YarnDyeing::shadeLabel($shades[$l->program_line_id], $names) : null,
                    'yarn' => isset($lots[$l->lot_id]) ? YarnStore::describe($lots[$l->lot_id], $names)['yarn'] : null,
                ])->values()->all(),
            ];
    }

    /** @return array<string, mixed> */
    private function presentDyed(KnitYdDyedReceipt $r): array
    {
        $names = YarnProcurement::names();
        $progs = KnitScProgram::query()->pluck('program_no', 'id');
        $lines = KnitYdDyedReceiptLine::query()->where('receipt_id', $r->id)->orderBy('sort_order')->get();
        $forwarded = $this->forwarded($lines->pluck('id'));

        return ['id' => $r->id, 'challan_no' => $r->challan_no, 'challan_date' => $r->challan_date->toDateString(), 'gate_pass_no' => $r->gate_pass_no,
            'party_id' => $r->party_id, 'grey_challan' => $r->grey_transfer_id ? KnitYdGreyTransfer::query()->whereKey($r->grey_transfer_id)->value('challan_no') : null,
            'g_yarn_qty' => round((float) ($this->greyQty([$r->grey_transfer_id])[$r->grey_transfer_id] ?? 0), 3),
            'program_no' => $r->program_id ? KnitYdProgram::query()->whereKey($r->program_id)->value('program_no') : null, 'buyer_id' => $r->buyer_id, 'order_id' => $r->order_id,
            'remarks' => $r->remarks] + $this->head($r) + [
                'lines' => $lines->map(fn ($l) => collect($l->only(['composition_id', 'yarn_count_id', 'colour_id', 'colour_name', 'lot_no', 'pack_qty', 'qty', 'uom', 'remarks']))->all() + [
                    'knit_prog' => $progs[$l->knit_prog_id] ?? null, 'collar_prog' => $progs[$l->collar_prog_id] ?? null, 'yarn' => $this->yarnName($l, $names) ?: null,
                    'forwarded' => round($forwarded[$l->id] ?? 0, 4),
                ])->values()->all(),
            ];
    }

    /** @return array<string, mixed> */
    private function presentDyedTransfer(KnitYdDyedTransfer $t): array
    {
        $names = YarnProcurement::names();
        $progs = KnitScProgram::query()->pluck('program_no', 'id');
        $lines = KnitYdDyedTransferLine::query()->where('transfer_id', $t->id)->orderBy('sort_order')->get();
        $src = KnitYdDyedReceiptLine::query()->whereIn('id', $lines->pluck('receipt_line_id'))->get()->keyBy('id');
        $rcv = KnitYdDyedReceipt::query()->whereIn('id', $src->pluck('receipt_id'))->pluck('challan_no', 'id');

        return ['id' => $t->id, 'challan_no' => $t->challan_no, 'challan_date' => $t->challan_date->toDateString(), 'gate_pass_no' => $t->gate_pass_no,
            'vehicle_no' => $t->vehicle_no, 'party_id' => $t->party_id, 'remarks' => $t->remarks] + $this->head($t) + [
                'lines' => $lines->map(function ($l) use ($src, $rcv, $progs, $names) {
                    $s = $src[$l->receipt_line_id];

                    return collect($l->only(['receipt_line_id', 'pack_qty', 'qty', 'uom', 'remarks']))->all() + [
                        'receipt_no' => $rcv[$s->receipt_id] ?? null, 'knit_prog' => $progs[$l->knit_prog_id] ?? null, 'collar_prog' => $progs[$l->collar_prog_id] ?? null,
                        'colour_name' => $s->colour_name, 'yarn' => $this->yarnName($s, $names) ?: null, 'lot_no' => $s->lot_no,
                    ];
                })->values()->all(),
            ];
    }
}
