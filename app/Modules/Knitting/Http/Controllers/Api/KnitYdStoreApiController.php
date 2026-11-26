<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\Knitting\Models\KnitYdLossLine;
use App\Modules\Knitting\Models\KnitYdLossMemo;
use App\Modules\Knitting\Models\KnitYdProgram;
use App\Modules\Knitting\Models\KnitYdProgramLine;
use App\Modules\Knitting\Models\KnitYdReceipt;
use App\Modules\Knitting\Models\KnitYdReceiptLine;
use App\Modules\Knitting\Models\KnitYdReprocess;
use App\Modules\Knitting\Models\KnitYdReprocessLine;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Knit Production → Yarn Store (AnnotechERP 15/03), the dye house's side of
 * a Y/D programme, as Brickland works it:
 *
 * - In-House Dyed Yarn Receipt (YDR-…): shades back from the dye house. A
 *   draft moves nothing; Confirm (on its list) books the weight against the
 *   programme and takes the cones onto the store, one stock line per dyed
 *   lot; Cancel takes them off again while the store still holds them.
 * - Yarn Dyeing Loss Adjustment (YDL-…): a dye house's shrinkage written
 *   off per shade (Issued − Received); only Approve moves the programme.
 * - YD Re-process, In-house or S/C Knitting to YD Factory (YRP-…): dyed yarn
 *   going back to be run again, a reason on every lot. Issue is when the
 *   lorry leaves — in-house yarn leaves the store then; recovered S/C yarn
 *   was never on it. The factory then accepts or rejects it; a rejection or
 *   a cancelled issue puts in-house yarn back.
 */
class KnitYdStoreApiController extends ApiController
{
    /* ---------------- Dyed Yarn Receive ---------------- */

    public function receipts(): JsonResponse
    {
        $this->allow('knitting.ystore.view');
        $rows = KnitYdReceipt::query()->orderByDesc('receive_date')->orderByDesc('receive_no')->limit(500)->get();
        $qty = KnitYdReceiptLine::query()->whereIn('receipt_id', $rows->pluck('id'))->groupBy('receipt_id')->selectRaw('receipt_id, sum(qty) as q')->pluck('q', 'receipt_id');
        $programs = KnitYdProgram::query()->whereIn('id', $rows->pluck('program_id')->unique())->get()->keyBy('id');
        $names = $this->names($rows->pluck('supplier_id'), $rows->pluck('store_id'), $programs);

        return ApiResponse::ok($rows->map(fn (KnitYdReceipt $r) => [
            'receive_no' => $r->receive_no, 'receive_date' => $r->receive_date->toDateString(), 'program_no' => $programs[$r->program_id]->program_no,
            'supplier_id' => $r->supplier_id, 'party' => $names['parties'][$r->supplier_id] ?? null, 'store' => $names['stores'][$r->store_id] ?? null,
            'working_ref' => $r->working_ref, 'challan_no' => $r->challan_no, 'challan_date' => $r->challan_date?->toDateString(),
            'qty' => round((float) ($qty[$r->id] ?? 0), 4), 'status' => $r->status,
        ] + $names['program'](($programs[$r->program_id])))->values()->all());
    }

    public function showReceipt(string $no): JsonResponse
    {
        $this->allow('knitting.ystore.view');

        return ApiResponse::ok($this->presentReceipt($this->find(KnitYdReceipt::class, 'receive_no', $no)));
    }

    public function saveReceipt(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.ystore.manage');
        $row = $no ? $this->find(KnitYdReceipt::class, 'receive_no', $no) : null;
        if ($row) {
            $this->assertStatus($row, 'receive_no', ['DRAFT'], 'save', 'receipt_status');
        }
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'receive_date' => ['required', 'date'], 'program_no' => ['required', 'string'], 'working_ref' => ['nullable', 'string', 'max:40'],
            'challan_no' => ['nullable', 'string', 'max:40'], 'challan_date' => ['nullable', 'date'],
            'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $company)], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:100'], 'lines.*.program_line_id' => ['required', 'string'], 'lines.*.lot_no' => ['required', 'string', 'max:40'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'lines.*.uom' => ['required', 'string', 'max:16', UomCodes::rule($company)],
            'lines.*.cones' => ['nullable', 'integer', 'min:0', 'max:9999999'], 'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'receive_date.required' => __('knitting.yd.v.receive_date'), 'program_no.required' => __('knitting.yd.v.program'), 'store_id.required' => __('knitting.yd.v.store'),
            'lines.*.program_line_id.required' => __('knitting.yd.v.shade'), 'lines.*.lot_no.required' => __('knitting.yd.v.lot'),
            'lines.*.qty.required' => __('knitting.yd.v.qty'), 'lines.*.qty.gt' => __('knitting.yd.v.qty'), 'lines.*.uom.required' => __('knitting.yd.v.uom'),
        ]);
        $program = $this->openProgram($data['program_no']);
        $lines = $this->ownLines($program, YarnStore::inOrder($data['lines']));
        $row = DB::transaction(function () use ($row, $data, $lines, $program): KnitYdReceipt {
            $head = collect($data)->only(['receive_date', 'working_ref', 'challan_no', 'challan_date', 'store_id', 'remarks'])->map(fn ($v) => $v === '' ? null : $v)->all()
                + ['program_id' => $program->id, 'supplier_id' => $program->supplier_id];
            $row ??= KnitYdReceipt::query()->create($head + [
                'receive_no' => KnitProduction::nextNumber('YDR', 'knit_yd_receipts', 'receive_no', $data['receive_date']), 'created_by' => $this->caller()->auditUserId(),
            ]);
            $row->update($head);
            KnitYdReceiptLine::query()->where('receipt_id', $row->id)->delete();
            foreach ($lines as $i => $l) {
                KnitYdReceiptLine::query()->create(['company_id' => $row->company_id, 'receipt_id' => $row->id, 'sort_order' => $i + 1, 'program_line_id' => $l['program_line_id'],
                    'lot_no' => mb_strtoupper(trim($l['lot_no'])), 'qty' => (float) $l['qty'], 'uom' => UomCodes::normalize($l['uom']),
                    'cones' => $l['cones'] ?? null, 'remarks' => ($l['remarks'] ?? '') ?: null]);
            }

            return $row;
        });

        return $no ? ApiResponse::ok($this->presentReceipt($row->refresh())) : ApiResponse::created($this->presentReceipt($row->refresh()));
    }

    public function receiptTransition(string $no, string $action): JsonResponse
    {
        $this->allow('knitting.ystore.manage');
        $row = $this->find(KnitYdReceipt::class, 'receive_no', $no);
        [$from, $to] = match ($action) {
            'confirm' => [['DRAFT'], 'CONFIRMED'],
            'cancel' => [['DRAFT', 'CONFIRMED'], 'CANCELLED'],
            default => abort(404),
        };
        $this->assertStatus($row, 'receive_no', $from, $action, 'receipt_status');
        DB::transaction(function () use ($row, $action, $to): void {
            $lines = KnitYdReceiptLine::query()->where('receipt_id', $row->id)->orderBy('sort_order')->get();
            $date = $row->receive_date->toDateString();
            if ($action === 'confirm') {
                if ($lines->isEmpty()) {
                    throw ValidationException::withMessages(['lines' => __('knitting.yd.v.no_lines')]);
                }
                YarnStore::assertOpen($date, 'receive_date');
                $program = KnitYdProgram::query()->findOrFail($row->program_id);
                $shades = KnitYdProgramLine::query()->whereIn('id', $lines->pluck('program_line_id'))->get()->keyBy('id');
                $entries = [];
                foreach ($lines as $l) {
                    $s = $shades[$l->program_line_id];
                    $lot = YarnStore::lotFor([
                        'store_id' => $row->store_id, 'composition_id' => $s->composition_id, 'yarn_count_id' => $s->yarn_count_id,
                        'colour' => $s->colour_group ?: 'Yarn Dyed', 'lot_no' => $l->lot_no, 'supplier_id' => $row->supplier_id,
                        'buyer_id' => $program->buyer_id, 'order_id' => $program->order_id, 'uom' => $l->uom,
                        'source' => $program->yd_type === 'IN_HOUSE' ? 'INHOUSE_DYED' : 'YARN_DYED',
                    ], $date, (float) ($s->rate ?? 0));
                    $l->update(['lot_id' => $lot->id]);
                    $entries[] = ['lot_id' => $lot->id, 'qty' => (float) $l->qty, 'value' => (float) $l->qty * (float) ($s->rate ?? 0), 'doc_line_id' => $l->id];
                }
                YarnStore::post('YD_RECEIVE', $row->receive_no, $date, $entries);
            }
            if ($action === 'cancel' && $row->status === 'CONFIRMED') {
                YarnStore::assertOpen($date, 'receive_date');
                foreach ($lines->groupBy('lot_id') as $lotId => $mine) {
                    if ($lotId && YarnStore::balance($lotId) + 0.00005 < (float) $mine->sum('qty')) {
                        throw ValidationException::withMessages(['status' => __('knitting.yd.v.receipt_used', ['no' => $row->receive_no, 'lot' => $mine->first()->lot_no])]);
                    }
                }
                YarnStore::unpost('YD_RECEIVE', $row->receive_no);
            }
            $row->update(['status' => $to]);
        });

        return ApiResponse::ok($this->presentReceipt($row->refresh()));
    }

    /* ---------------- Yarn Dyeing Loss Adjustment ---------------- */

    public function memos(): JsonResponse
    {
        $this->allow('knitting.ystore.view');
        $rows = KnitYdLossMemo::query()->orderByDesc('memo_date')->orderByDesc('memo_no')->limit(500)->get();
        $sums = KnitYdLossLine::query()->whereIn('memo_id', $rows->pluck('id'))->groupBy('memo_id')
            ->selectRaw('memo_id, sum(issued_qty) as i, sum(received_qty) as r')->get()->keyBy('memo_id');
        $programs = KnitYdProgram::query()->whereIn('id', $rows->pluck('program_id')->unique())->get()->keyBy('id');
        $names = $this->names($rows->pluck('supplier_id'), collect(), $programs);

        return ApiResponse::ok($rows->map(function (KnitYdLossMemo $m) use ($sums, $programs, $names) {
            $issued = round((float) ($sums[$m->id]->i ?? 0), 4);
            $loss = round($issued - (float) ($sums[$m->id]->r ?? 0), 4);

            return [
                'memo_no' => $m->memo_no, 'memo_date' => $m->memo_date->toDateString(), 'program_no' => $programs[$m->program_id]->program_no,
                'supplier_id' => $m->supplier_id, 'party' => $names['parties'][$m->supplier_id] ?? null, 'issued' => $issued,
                'received' => round((float) ($sums[$m->id]->r ?? 0), 4), 'loss' => $loss, 'loss_pct' => $issued > 0 ? round($loss / $issued * 100, 2) : null,
                'remarks' => $m->remarks, 'status' => $m->status,
            ] + $names['program']($programs[$m->program_id]);
        })->values()->all());
    }

    public function showMemo(string $no): JsonResponse
    {
        $this->allow('knitting.ystore.view');

        return ApiResponse::ok($this->presentMemo($this->find(KnitYdLossMemo::class, 'memo_no', $no)));
    }

    public function saveMemo(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.ystore.manage');
        $row = $no ? $this->find(KnitYdLossMemo::class, 'memo_no', $no) : null;
        if ($row) {
            $this->assertStatus($row, 'memo_no', ['DRAFT'], 'save', 'memo_status');
        }
        $data = $request->validate([
            'memo_date' => ['required', 'date'], 'program_no' => ['required', 'string'], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:100'], 'lines.*.program_line_id' => ['required', 'string'],
            'lines.*.colour_group' => ['nullable', Rule::in(YarnProcurement::COLOURS)], 'lines.*.lot_no' => ['nullable', 'string', 'max:40'],
            'lines.*.issued_qty' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'lines.*.received_qty' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'memo_date.required' => __('knitting.yd.v.memo_date'), 'program_no.required' => __('knitting.yd.v.program'), 'lines.*.program_line_id.required' => __('knitting.yd.v.shade'),
            'lines.*.issued_qty.required' => __('knitting.yd.v.issued'), 'lines.*.issued_qty.gt' => __('knitting.yd.v.issued'), 'lines.*.received_qty.required' => __('knitting.yd.v.received'),
        ]);
        $program = $this->openProgram($data['program_no']);
        $lines = $this->ownLines($program, YarnStore::inOrder($data['lines']));
        foreach ($lines as $i => $l) {
            if ((float) $l['received_qty'] > (float) $l['issued_qty']) {
                throw ValidationException::withMessages(["lines.{$i}.received_qty" => __('knitting.yd.v.received_over', ['n' => $i + 1])]);
            }
        }
        $row = DB::transaction(function () use ($row, $data, $lines, $program): KnitYdLossMemo {
            $head = ['memo_date' => $data['memo_date'], 'remarks' => ($data['remarks'] ?? '') ?: null, 'program_id' => $program->id, 'supplier_id' => $program->supplier_id];
            $row ??= KnitYdLossMemo::query()->create($head + [
                'memo_no' => KnitProduction::nextNumber('YDL', 'knit_yd_loss_memos', 'memo_no', $data['memo_date']), 'created_by' => $this->caller()->auditUserId(),
            ]);
            $row->update($head);
            KnitYdLossLine::query()->where('memo_id', $row->id)->delete();
            foreach ($lines as $i => $l) {
                KnitYdLossLine::query()->create(['company_id' => $row->company_id, 'memo_id' => $row->id, 'sort_order' => $i + 1, 'program_line_id' => $l['program_line_id'],
                    'colour_group' => ($l['colour_group'] ?? '') ?: null, 'lot_no' => ($l['lot_no'] ?? '') ?: null, 'issued_qty' => (float) $l['issued_qty'],
                    'received_qty' => (float) $l['received_qty'], 'remarks' => ($l['remarks'] ?? '') ?: null]);
            }

            return $row;
        });

        return $no ? ApiResponse::ok($this->presentMemo($row->refresh())) : ApiResponse::created($this->presentMemo($row->refresh()));
    }

    public function memoTransition(string $no, string $action): JsonResponse
    {
        $this->allow('knitting.ystore.manage');
        $row = $this->find(KnitYdLossMemo::class, 'memo_no', $no);
        [$from, $to] = match ($action) {
            'approve' => [['DRAFT'], 'APPROVED'],
            'cancel' => [['DRAFT', 'APPROVED'], 'CANCELLED'],
            default => abort(404),
        };
        $this->assertStatus($row, 'memo_no', $from, $action, 'memo_status');
        $row->update(['status' => $to]);

        return ApiResponse::ok($this->presentMemo($row->refresh()));
    }

    /* ---------------- YD Re-process ---------------- */

    public function reprocesses(string $kind): JsonResponse
    {
        $this->allow('knitting.ystore.view');
        $kind = $this->kind($kind);
        $rows = KnitYdReprocess::query()->where('kind', $kind)->orderByDesc('ref_date')->orderByDesc('ref_no')->limit(500)->get();
        $qty = KnitYdReprocessLine::query()->whereIn('reprocess_id', $rows->pluck('id'))->groupBy('reprocess_id')->selectRaw('reprocess_id, sum(qty) as q')->pluck('q', 'reprocess_id');
        $programs = KnitYdProgram::query()->whereIn('id', $rows->pluck('program_id')->filter()->unique())->pluck('program_no', 'id');
        $names = $this->names($rows->pluck('supplier_id')->merge($rows->pluck('party_id')->filter()), $rows->pluck('store_id'), collect());

        return ApiResponse::ok($rows->map(fn (KnitYdReprocess $r) => [
            'ref_no' => $r->ref_no, 'ref_date' => $r->ref_date->toDateString(), 'supplier_id' => $r->supplier_id, 'factory' => $names['parties'][$r->supplier_id] ?? null,
            'party_id' => $r->party_id, 'party' => $names['parties'][$r->party_id] ?? null, 'bill_no' => $r->bill_no, 'challan_no' => $r->challan_no,
            'challan_date' => $r->challan_date?->toDateString(), 'program_no' => $programs[$r->program_id] ?? null, 'store' => $names['stores'][$r->store_id] ?? null,
            'qty' => round((float) ($qty[$r->id] ?? 0), 4), 'status' => $r->status,
        ])->values()->all());
    }

    public function showReprocess(string $kind, string $no): JsonResponse
    {
        $this->allow('knitting.ystore.view');

        return ApiResponse::ok($this->presentReprocess($this->reprocess($kind, $no)));
    }

    public function saveReprocess(Request $request, string $kind, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.ystore.manage');
        $row = $no ? $this->reprocess($kind, $no) : null;
        $kind = $this->kind($kind);
        if ($row) {
            $this->assertStatus($row, 'ref_no', ['DRAFT'], 'save', 'reprocess_status');
        }
        $company = app(TenantContext::class)->companyId();
        $inHouse = $kind === 'IN_HOUSE';
        $data = $request->validate([
            'ref_date' => ['required', 'date'], 'supplier_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'party_id' => [$inHouse ? 'nullable' : 'required', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'bill_no' => ['nullable', 'string', 'max:40'], 'challan_no' => ['nullable', 'string', 'max:40'], 'challan_date' => ['nullable', 'date'],
            'program_no' => ['nullable', 'string'], 'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $company)],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:100'], 'lines.*.lot_id' => [$inHouse ? 'required' : 'nullable', 'string'],
            'lines.*.composition_id' => ['nullable', Rule::exists('fiber_compositions', 'id')->where('company_id', $company)],
            'lines.*.yarn_count_id' => ['nullable', Rule::exists('knit_yarn_counts', 'id')->where('company_id', $company)],
            'lines.*.colour_group' => ['nullable', Rule::in(YarnProcurement::COLOURS)], 'lines.*.lot_no' => ['nullable', 'string', 'max:40'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'lines.*.uom' => ['required', 'string', 'max:16', UomCodes::rule($company)],
            'lines.*.reason' => ['required', 'string', 'max:120'], 'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'ref_date.required' => __('knitting.yd.v.ref_date'), 'supplier_id.required' => __('knitting.yd.v.factory'), 'party_id.required' => __('knitting.yd.v.sc_party'),
            'store_id.required' => __('knitting.yd.v.store'), 'lines.*.lot_id.required' => __('knitting.yd.v.rp_lot'), 'lines.*.qty.required' => __('knitting.yd.v.qty'),
            'lines.*.qty.gt' => __('knitting.yd.v.qty'), 'lines.*.uom.required' => __('knitting.yd.v.uom'), 'lines.*.reason.required' => __('knitting.yd.v.reason_line'),
        ]);
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('knitting.yd.v.no_lines')]);
        }
        $program = null;
        if (! empty($data['program_no'])) {
            $program = $this->openProgram($data['program_no']);
            if ($program->yd_type !== $kind) {
                throw ValidationException::withMessages(['program_no' => __('knitting.yd.v.program_kind', ['no' => $program->program_no, 'type' => __("knitting.yd.type.{$program->yd_type}")])]);
            }
        }
        foreach ($lines as $i => &$l) {
            if (! $inHouse) {
                if (empty($l['composition_id']) && empty($l['yarn_count_id'])) {
                    throw ValidationException::withMessages(["lines.{$i}.composition_id" => __('knitting.yd.v.rp_yarn', ['n' => $i + 1])]);
                }
                $l['lot_id'] = null;

                continue;
            }
            $lot = YarnLot::query()->whereKey($l['lot_id'])->where('store_id', $data['store_id'])->first()
                ?? throw ValidationException::withMessages(["lines.{$i}.lot_id" => __('knitting.yd.v.rp_lot_store', ['n' => $i + 1])]);
            $l = ['composition_id' => $lot->composition_id, 'yarn_count_id' => $lot->yarn_count_id, 'lot_no' => $lot->lot_no, 'uom' => $lot->uom,
                'colour_group' => ($l['colour_group'] ?? '') ?: (in_array($lot->colour, YarnProcurement::COLOURS, true) ? $lot->colour : null)] + $l;
        }
        unset($l);
        $row = DB::transaction(function () use ($row, $data, $lines, $program, $kind): KnitYdReprocess {
            $head = collect($data)->only(['ref_date', 'supplier_id', 'party_id', 'bill_no', 'challan_no', 'challan_date', 'store_id', 'remarks'])->map(fn ($v) => $v === '' ? null : $v)->all()
                + ['party_id' => null, 'program_id' => $program?->id];
            $row ??= KnitYdReprocess::query()->create($head + [
                'kind' => $kind, 'ref_no' => KnitProduction::nextNumber('YRP', 'knit_yd_reprocesses', 'ref_no', $data['ref_date']), 'created_by' => $this->caller()->auditUserId(),
            ]);
            $row->update($head);
            KnitYdReprocessLine::query()->where('reprocess_id', $row->id)->delete();
            foreach ($lines as $i => $l) {
                KnitYdReprocessLine::query()->create(['company_id' => $row->company_id, 'reprocess_id' => $row->id, 'sort_order' => $i + 1, 'qty' => (float) $l['qty'],
                    'uom' => UomCodes::normalize($l['uom'])]
                    + collect($l)->only(['lot_id', 'composition_id', 'yarn_count_id', 'colour_group', 'lot_no', 'reason', 'remarks'])->map(fn ($v) => $v === '' ? null : $v)->all());
            }

            return $row;
        });

        return $no ? ApiResponse::ok($this->presentReprocess($row->refresh())) : ApiResponse::created($this->presentReprocess($row->refresh()));
    }

    public function reprocessTransition(string $kind, string $no, string $action): JsonResponse
    {
        $this->allow('knitting.ystore.manage');
        $row = $this->reprocess($kind, $no);
        [$from, $to] = match ($action) {
            'issue' => [['DRAFT'], 'ISSUED'],
            'accept' => [['ISSUED'], 'ACCEPTED'],
            'reject' => [['ISSUED'], 'REJECTED'],
            'cancel' => [['DRAFT', 'ISSUED'], 'CANCELLED'],
            default => abort(404),
        };
        $this->assertStatus($row, 'ref_no', $from, $action, 'reprocess_status');
        DB::transaction(function () use ($row, $action, $to): void {
            $date = $row->ref_date->toDateString();
            if ($row->kind === 'IN_HOUSE' && $action === 'issue') {
                YarnStore::assertOpen($date, 'ref_date');
                $lines = KnitYdReprocessLine::query()->where('reprocess_id', $row->id)->orderBy('sort_order')->get();
                foreach ($lines->groupBy('lot_id') as $lotId => $mine) {
                    $have = YarnStore::balance($lotId);
                    if ($have + 0.00005 < (float) $mine->sum('qty')) {
                        throw ValidationException::withMessages(['lines' => __('knitting.yd.v.rp_short', ['lot' => $mine->first()->lot_no ?? '—', 'have' => round($have, 3)])]);
                    }
                }
                YarnStore::post('YD_REPROCESS', $row->ref_no, $date, $lines->map(fn ($l) => [
                    'lot_id' => $l->lot_id, 'qty' => -(float) $l->qty, 'value' => -(float) $l->qty * (float) YarnLot::query()->whereKey($l->lot_id)->value('rate'), 'doc_line_id' => $l->id,
                ])->all());
            }
            if ($row->kind === 'IN_HOUSE' && in_array($action, ['reject', 'cancel'], true) && $row->status === 'ISSUED') {
                YarnStore::assertOpen($date, 'ref_date');
                YarnStore::unpost('YD_REPROCESS', $row->ref_no);
            }
            $row->update(['status' => $to]);
        });

        return ApiResponse::ok($this->presentReprocess($row->refresh()));
    }

    /* ---------------- helpers ---------------- */

    private function find(string $model, string $column, string $no): Model
    {
        return $model::query()->where($column, $no)->firstOrFail();
    }

    private function kind(string $slug): string
    {
        return match ($slug) {
            'in-house' => 'IN_HOUSE',
            'sc-knitting' => 'SUB_CONTRACT',
            default => abort(404),
        };
    }

    private function reprocess(string $kind, string $no): KnitYdReprocess
    {
        return KnitYdReprocess::query()->where('kind', $this->kind($kind))->where('ref_no', $no)->firstOrFail();
    }

    private function assertStatus(Model $row, string $column, array $from, string $action, string $group): void
    {
        if (! in_array($row->status, $from, true)) {
            throw ValidationException::withMessages(['status' => __('knitting.yd.v.cannot', [
                'no' => $row->{$column}, 'status' => __("knitting.yd.{$group}.{$row->status}"), 'action' => __("knitting.yd.action.{$action}"),
            ])]);
        }
    }

    /** A programme yarn may still be booked against: not closed, not cancelled. */
    private function openProgram(string $no): KnitYdProgram
    {
        $p = KnitYdProgram::query()->where('program_no', $no)->first()
            ?? throw ValidationException::withMessages(['program_no' => __('knitting.yd.v.program')]);
        if (in_array($p->status, ['CLOSED', 'CANCELLED'], true)) {
            throw ValidationException::withMessages(['program_no' => __('knitting.yd.v.program_shut', ['no' => $p->program_no, 'status' => __("knitting.yd.status.{$p->status}")])]);
        }

        return $p;
    }

    /** At least one line, and every line one of this programme's shades. */
    private function ownLines(KnitYdProgram $p, array $lines): array
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('knitting.yd.v.no_lines')]);
        }
        $mine = KnitYdProgramLine::query()->where('program_id', $p->id)->pluck('id')->all();
        foreach ($lines as $i => $l) {
            if (! in_array($l['program_line_id'], $mine, true)) {
                throw ValidationException::withMessages(["lines.{$i}.program_line_id" => __('knitting.yd.v.shade_other', ['n' => $i + 1, 'no' => $p->program_no])]);
            }
        }

        return $lines;
    }

    /** Party and store names, and a programme's buyer and order, for list rows. */
    private function names($supplierIds, $storeIds, $programs): array
    {
        $parties = DB::table('mp_suppliers')->whereIn('id', collect($supplierIds)->filter()->unique()->values())->pluck('name', 'id');
        $stores = DB::table('stores')->whereIn('id', collect($storeIds)->unique()->values())->pluck('name', 'id');
        $buyers = DB::table('buyers')->whereIn('id', $programs->pluck('buyer_id')->filter()->unique()->values())->get(['id', 'name', 'short_name'])->keyBy('id');
        $orders = Order::query()->whereIn('id', $programs->pluck('order_id')->filter()->unique()->values())->get(['id', 'order_no', 'style_no', 'work_style_no'])->keyBy('id');

        return ['parties' => $parties, 'stores' => $stores, 'program' => function (KnitYdProgram $p) use ($buyers, $orders) {
            $b = $buyers[$p->buyer_id] ?? null;
            $o = $orders[$p->order_id] ?? null;

            return ['buyer_id' => $p->buyer_id, 'buyer' => $b ? ($b->short_name ?: $b->name) : null, 'order_id' => $p->order_id,
                'order' => $o ? trim(($o->work_style_no ?: $o->style_no).' · '.$o->order_no, ' ·') : null];
        }];
    }

    /** @return array<string, mixed> */
    private function head(Model $r, KnitYdProgram $p): array
    {
        $names = $this->names([$r->supplier_id], [], collect([$p]));

        return ['program_no' => $p->program_no, 'yd_type' => $p->yd_type, 'supplier_id' => $r->supplier_id, 'party' => $names['parties'][$r->supplier_id] ?? null,
            'party_address' => DB::table('mp_suppliers')->where('id', $r->supplier_id)->value('address'),
            'company' => DB::table('companies')->where('id', $r->company_id)->value('name'), 'status' => $r->status, 'remarks' => $r->remarks] + $names['program']($p);
    }

    /** @return array<string, mixed> */
    private function presentReceipt(KnitYdReceipt $r): array
    {
        $p = KnitYdProgram::query()->findOrFail($r->program_id);
        $shades = KnitYdProgramLine::query()->where('program_id', $p->id)->get()->keyBy('id');
        $names = YarnProcurement::names();

        return ['id' => $r->id, 'receive_no' => $r->receive_no, 'receive_date' => $r->receive_date->toDateString(), 'working_ref' => $r->working_ref,
            'challan_no' => $r->challan_no, 'challan_date' => $r->challan_date?->toDateString(), 'store_id' => $r->store_id,
            'store' => DB::table('stores')->where('id', $r->store_id)->value('name')] + $this->head($r, $p) + [
                'lines' => KnitYdReceiptLine::query()->where('receipt_id', $r->id)->orderBy('sort_order')->get()->map(fn (KnitYdReceiptLine $l) => collect($l->only([
                    'program_line_id', 'lot_no', 'qty', 'uom', 'cones', 'remarks',
                ]))->all() + ['shade' => isset($shades[$l->program_line_id]) ? YarnDyeing::shadeLabel($shades[$l->program_line_id], $names) : null,
                    'colour_name' => $shades[$l->program_line_id]->colour_name ?? null])->values()->all(),
            ];
    }

    /** @return array<string, mixed> */
    private function presentMemo(KnitYdLossMemo $m): array
    {
        $p = KnitYdProgram::query()->findOrFail($m->program_id);
        $shades = KnitYdProgramLine::query()->where('program_id', $p->id)->get()->keyBy('id');
        $names = YarnProcurement::names();

        return ['id' => $m->id, 'memo_no' => $m->memo_no, 'memo_date' => $m->memo_date->toDateString()] + $this->head($m, $p) + [
            'lines' => KnitYdLossLine::query()->where('memo_id', $m->id)->orderBy('sort_order')->get()->map(fn (KnitYdLossLine $l) => collect($l->only([
                'program_line_id', 'colour_group', 'lot_no', 'issued_qty', 'received_qty', 'remarks',
            ]))->all() + ['shade' => isset($shades[$l->program_line_id]) ? YarnDyeing::shadeLabel($shades[$l->program_line_id], $names) : null,
                'loss' => round($l->issued_qty - $l->received_qty, 4), 'loss_pct' => $l->issued_qty > 0 ? round(($l->issued_qty - $l->received_qty) / $l->issued_qty * 100, 2) : null,
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentReprocess(KnitYdReprocess $r): array
    {
        $names = YarnProcurement::names();
        $parties = DB::table('mp_suppliers')->whereIn('id', array_filter([$r->supplier_id, $r->party_id]))->get(['id', 'name', 'address'])->keyBy('id');

        return [
            'id' => $r->id, 'ref_no' => $r->ref_no, 'kind' => $r->kind, 'ref_date' => $r->ref_date->toDateString(), 'supplier_id' => $r->supplier_id,
            'factory' => $parties[$r->supplier_id]->name ?? null, 'factory_address' => $parties[$r->supplier_id]->address ?? null,
            'party_id' => $r->party_id, 'party' => $r->party_id ? ($parties[$r->party_id]->name ?? null) : null, 'bill_no' => $r->bill_no, 'challan_no' => $r->challan_no,
            'challan_date' => $r->challan_date?->toDateString(), 'program_no' => $r->program_id ? KnitYdProgram::query()->whereKey($r->program_id)->value('program_no') : null,
            'store_id' => $r->store_id, 'store' => DB::table('stores')->where('id', $r->store_id)->value('name'),
            'company' => DB::table('companies')->where('id', $r->company_id)->value('name'), 'status' => $r->status, 'remarks' => $r->remarks,
            'lines' => KnitYdReprocessLine::query()->where('reprocess_id', $r->id)->orderBy('sort_order')->get()->map(fn (KnitYdReprocessLine $l) => collect($l->only([
                'lot_id', 'composition_id', 'yarn_count_id', 'colour_group', 'lot_no', 'qty', 'uom', 'reason', 'remarks',
            ]))->all() + ['yarn' => trim(($names['compositions'][$l->composition_id] ?? '').' '.($names['counts'][$l->yarn_count_id] ?? '')) ?: null])->values()->all(),
        ];
    }
}
