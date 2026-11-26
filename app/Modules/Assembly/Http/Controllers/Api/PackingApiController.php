<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Http\Controllers\Api;

use App\Modules\Assembly\Models\AsmFgLedger;
use App\Modules\Assembly\Models\AsmPackingBatch;
use App\Modules\Assembly\Models\AsmPackingCarton;
use App\Modules\Assembly\Models\AsmPackingCartonLine;
use App\Modules\Assembly\Models\AsmPackingIn;
use App\Modules\Assembly\Models\AsmPackingLabel;
use App\Modules\Assembly\Models\AsmPackingStep;
use App\Modules\Assembly\Models\AsmQualityIn;
use App\Modules\Assembly\Models\AsmQualityOut;
use App\Modules\Assembly\Models\AsmShipment;
use App\Modules\Assembly\Support\PackingBook;
use App\Modules\Assembly\Support\ReadyBook;
use App\Modules\Assembly\Support\SewingFloor;
use App\Modules\Cutting\Models\CutBundle;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Assembly Floor → Packing (AnnotechERP 20/04) and its Packing Process
 * (20/05), as Brickland keeps them under one row of buttons: Packing In — a
 * Quality-passed bundle scanned at a packing location (Section > Floor >
 * Packing Line / Location) with its order, PO, style, colour, size and QC
 * Pass quantity loaded, joining the open batch of its PO and colour (the
 * first scan opens one) until Folding starts, a supervisor removing a
 * mistaken scan before then; the seven processes in order — Folding and Poly
 * Packing with the verified quantity, Barcode & Label (one poly label per
 * size, printed and verified by scan), Assortment (the buyer's size ratio,
 * checked against what was packed in), Carton Packing (cartons with their
 * size break-up, whole ratio sets only), Carton Marking (destination and
 * shipping mark on every carton) and Carton Sealing (each carton scanned,
 * weighed and sealed); Packing Out — every process checked, the final packed
 * quantity confirmed and moved to Ready for Shipment; and the Packing
 * Dashboard with its flow, key figures, the floor, Ready for Shipment and a
 * code's whole history.
 */
class PackingApiController extends ApiController
{
    private ?PackingBook $book = null;

    /** What the screens pick from: the packing sections, floors and locations, the seven processes and the reports. */
    public function options(): JsonResponse
    {
        $this->allow('assembly.packing.view');
        $active = $this->book()->lines()->where('active', true);
        $filed = $active->filter(fn ($l) => $l->section_id && $l->floor_id);

        return ApiResponse::ok([
            'today' => PackingBook::today()->toDateString(),
            'sections' => $filed->groupBy('section_id')->map(fn ($g, $id) => ['value' => $id, 'label' => $g->first()->section])->sortBy('label')->values()->all(),
            'floors' => $filed->groupBy(fn ($l) => $l->section_id.'|'.$l->floor_id)->map(fn ($g) => ['value' => $g->first()->floor_id, 'label' => $g->first()->floor, 'section_id' => $g->first()->section_id])->sortBy('label')->values()->all(),
            'lines' => $filed->map(fn ($l) => ['value' => $l->id, 'label' => $l->label, 'section_id' => $l->section_id, 'floor_id' => $l->floor_id])->values()->all(),
            'unfiled' => $active->reject(fn ($l) => $l->section_id && $l->floor_id)->pluck('label')->values()->all(),
            'steps' => AsmPackingStep::STEPS,
            'reports' => PackingReportApiController::KINDS,
        ]);
    }

    /** The Packing In desk: the batches still taking bundles and today's scans. */
    public function inDesk(): JsonResponse
    {
        $this->allow('assembly.packing.view');
        $open = AsmPackingBatch::query()->where('status', 'PACKING_IN')->orderBy('created_at')->orderBy('id')->get();
        $today = PackingBook::today()->toDateString();
        $ins = AsmPackingIn::query()->whereDate('in_date', $today)->orderByDesc('created_at')->orderByDesc('id')->get();
        $batches = AsmPackingBatch::query()->whereIn('id', $ins->pluck('batch_id'))->get()->keyBy('id');
        $cards = CutBundle::query()->whereIn('id', $ins->pluck('bundle_id'))->get()->keyBy('id');

        return ApiResponse::ok([
            'date' => $today,
            'open' => $this->book()->presentAll($open),
            'recent' => $ins->map(fn (AsmPackingIn $i) => [
                'id' => $i->id, 'in_at' => PackingBook::at($i->created_at), 'bundle_no' => $cards[$i->bundle_id]->bundle_no, 'barcode' => $cards[$i->bundle_id]->barcode(),
                'packing_no' => $batches[$i->batch_id]->packing_no, 'po_no' => $batches[$i->batch_id]->po_no, 'colour' => $batches[$i->batch_id]->colour,
                'size' => $i->size, 'qty' => $i->qty, 'removable' => $batches[$i->batch_id]->status === 'PACKING_IN', 'by' => $this->book()->userName($i->created_by),
            ])->values()->all(),
        ]);
    }

    /** What a bundle scanned at Packing In loads — its order, QC Pass, what is packed and left, the batch it would join — and why it may not, if so. Nothing is saved. */
    public function previewIn(Request $request): JsonResponse
    {
        $this->allow('assembly.packing.view');
        $q = $request->validate(['code' => ['required', 'string', 'max:40'], 'line_id' => ['required', 'string', 'max:26']]);
        $this->pickLine($q['line_id']);
        $card = SewingFloor::card($q['code']) ?? throw ValidationException::withMessages(['code' => __('assembly.v.unknown', ['code' => $q['code']])]);
        [$qcOut, $packed, $blocker] = $this->inState($card);
        $join = $blocker ? null : $this->openBatch($card);
        $order = $this->book()->order($card->order_id);
        [$buyer, $buyerCode] = $this->book()->buyer($card->buyer_id);
        $pass = (int) ($qcOut?->pass_qty ?? 0);

        return ApiResponse::ok([
            'ok' => $blocker === null, 'message' => $blocker,
            'card' => [
                'bundle_no' => $card->bundle_no, 'barcode' => $card->barcode(), 'buyer' => $buyer, 'buyer_code' => $buyerCode, 'order_no' => $order?->order_no, 'po_no' => $card->po_no,
                'style' => $card->style, 'style_name' => $order?->style_name, 'colour' => $card->colour, 'size' => $card->size,
                'order_qty' => $this->book()->orderQty($card->order_id, $card->colour, $card->size), 'qc_pass_qty' => $pass, 'already_packed_qty' => $packed,
                'balance_qty' => max(0, $pass - $packed), 'ship_date' => $order?->ship_date, 'factory' => $order?->factory,
                'qc_reference' => $qcOut ? 'QC-'.$card->bundle_no : null, 'qc_passed_at' => PackingBook::at($qcOut?->created_at),
            ],
            'joins' => $join ? ['packing_no' => $join->packing_no, 'packing_in_qty' => (int) AsmPackingIn::query()->where('batch_id', $join->id)->sum('qty'),
                'bundles' => AsmPackingIn::query()->where('batch_id', $join->id)->distinct()->count('bundle_id')] : null,
        ]);
    }

    /** Pack a Quality-passed bundle in — all that is left of it, or part — into the open batch of its PO and colour, opening one if none is open. */
    public function confirmIn(Request $request): JsonResponse
    {
        $this->allow('assembly.packing.manage');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'], 'section_id' => ['required', 'string', 'max:26'], 'floor_id' => ['required', 'string', 'max:26'],
            'line_id' => ['required', 'string', 'max:26'], 'qty' => ['nullable', 'integer', 'min:1', 'max:999999'],
        ], ['section_id.required' => __('assembly.v.section'), 'floor_id.required' => __('assembly.v.floor'), 'line_id.required' => __('assembly.v.pk_line')]);
        $line = $this->book()->lines()->get($data['line_id']);
        if (! $line || ! $line->active || $line->section_id !== $data['section_id'] || $line->floor_id !== $data['floor_id']) {
            throw ValidationException::withMessages(['line_id' => __('assembly.v.pk_line')]);
        }

        [$in, $batch, $opened] = DB::transaction(function () use ($data, $line) {
            $card = SewingFloor::card($data['code']) ?? throw ValidationException::withMessages(['code' => __('assembly.v.unknown', ['code' => $data['code']])]);
            $card = CutBundle::query()->whereKey($card->id)->lockForUpdate()->firstOrFail();
            [$qcOut, $packed, $blocker] = $this->inState($card);
            if ($blocker) {
                throw ValidationException::withMessages(['code' => $blocker]);
            }
            $balance = $qcOut->pass_qty - $packed;
            $qty = (int) ($data['qty'] ?? $balance);
            if ($qty > $balance) {
                throw ValidationException::withMessages(['qty' => __('assembly.v.pk_over_balance', ['qty' => $qty, 'balance' => $balance])]);
            }
            $batch = $this->openBatch($card, true);
            $opened = false;
            if (! $batch) {
                $batch = AsmPackingBatch::query()->create([
                    'packing_no' => $this->book()->nextNo('PKG', 'asm_packing_batches', 'packing_no'), 'order_id' => $card->order_id, 'po_no' => $card->po_no,
                    'buyer_id' => $card->buyer_id, 'style' => $card->style, 'colour' => $card->colour, 'section_id' => $line->section_id, 'floor_id' => $line->floor_id,
                    'line_id' => $line->id, 'status' => 'PACKING_IN', 'created_by' => $this->caller()->auditUserId(),
                ]);
                foreach (AsmPackingStep::STEPS as $i => $step) {
                    AsmPackingStep::query()->create(['batch_id' => $batch->id, 'step' => $step, 'seq' => $i + 1, 'status' => 'PENDING']);
                }
                $opened = true;
            }
            $in = AsmPackingIn::query()->create([
                'batch_id' => $batch->id, 'bundle_id' => $card->id, 'quality_out_id' => $qcOut->id, 'in_date' => PackingBook::today()->toDateString(),
                'size' => (string) $card->size, 'qty' => $qty, 'created_by' => $this->caller()->auditUserId(),
            ]);

            return [$in, $batch, $opened];
        });

        return ApiResponse::created(['id' => $in->id, 'bundle_no' => CutBundle::query()->whereKey($in->bundle_id)->value('bundle_no'), 'qty' => $in->qty,
            'packing_no' => $batch->packing_no, 'opened' => $opened]);
    }

    /** Remove a mistaken Packing In scan — a supervisor's call, only while its batch is still at Packing In. */
    public function removeIn(string $id): JsonResponse
    {
        $this->allow('assembly.packing.supervise');
        $no = DB::transaction(function () use ($id) {
            $in = AsmPackingIn::query()->whereKey($id)->lockForUpdate()->first() ?? throw ValidationException::withMessages(['id' => __('assembly.v.pk_no_scan')]);
            $batch = AsmPackingBatch::query()->whereKey($in->batch_id)->lockForUpdate()->firstOrFail();
            $no = CutBundle::query()->whereKey($in->bundle_id)->value('bundle_no');
            if ($batch->status !== 'PACKING_IN') {
                throw ValidationException::withMessages(['id' => __('assembly.v.pk_remove_late', ['no' => $no, 'batch' => $batch->packing_no])]);
            }
            $in->delete();
            if (! AsmPackingIn::query()->where('batch_id', $batch->id)->exists()) {
                $batch->update(['status' => 'CANCELLED']);
            }

            return $no;
        });

        return ApiResponse::ok(['bundle_no' => $no]);
    }

    /** A process desk: the batches waiting at or in this process, and those it completed today. */
    public function processDesk(Request $request): JsonResponse
    {
        $this->allow('assembly.packing.view');
        $q = $request->validate(['step' => ['required', Rule::in(AsmPackingStep::STEPS)]]);
        $step = $q['step'];
        $prev = AsmPackingStep::STEPS[array_search($step, AsmPackingStep::STEPS, true) - 1] ?? null;
        $active = AsmPackingBatch::query()->whereIn('status', ['PACKING_IN', 'IN_PROGRESS'])->orderBy('created_at')->orderBy('id')->get();
        $steps = AsmPackingStep::query()->whereIn('batch_id', $active->pluck('id'))->get()->groupBy('batch_id');
        $queue = $active->filter(function ($b) use ($steps, $step, $prev) {
            $mine = $steps->get($b->id, collect())->keyBy('step');
            $s = $mine->get($step);

            return $s && ($s->status === 'IN_PROGRESS' || ($s->status === 'PENDING' && ($prev === null || $mine->get($prev)?->status === 'COMPLETED')));
        })->values();
        $today = PackingBook::today();
        $doneIds = AsmPackingStep::query()->where('step', $step)->where('status', 'COMPLETED')
            ->whereBetween('completed_at', [$today->setTimezone('UTC'), $today->addDay()->setTimezone('UTC')])->pluck('batch_id');
        $done = AsmPackingBatch::query()->whereIn('id', $doneIds)->where('status', '!=', 'CANCELLED')->orderByDesc('updated_at')->get();

        return ApiResponse::ok(['step' => $step, 'queue' => $this->book()->presentAll($queue), 'done_today' => $this->book()->presentAll($done)]);
    }

    /** The batch a scanned code names, in full. */
    public function find(Request $request): JsonResponse
    {
        $this->allow('assembly.packing.view');
        $q = $request->validate(['code' => ['required', 'string', 'max:40']]);
        $batch = $this->book()->find($q['code']) ?? throw ValidationException::withMessages(['code' => __('assembly.v.pk_unknown', ['code' => $q['code']])]);

        return ApiResponse::ok($this->book()->present($batch));
    }

    public function show(string $id): JsonResponse
    {
        $this->allow('assembly.packing.view');

        return ApiResponse::ok($this->book()->present(AsmPackingBatch::query()->where('status', '!=', 'CANCELLED')->findOrFail($id)));
    }

    /** Start a process once the one before it is completed; Folding closes the batch to new bundles, Barcode & Label generates one poly label per size. */
    public function startStep(string $id, string $step): JsonResponse
    {
        $this->allow('assembly.packing.manage');
        $this->assertStep($step);
        $batch = DB::transaction(function () use ($id, $step) {
            [$batch, $steps] = $this->lockBatch($id);
            $s = $steps[$step];
            if ($s->status !== 'PENDING') {
                throw ValidationException::withMessages(['step' => __('assembly.v.pk_started', ['step' => __('assembly.pk.step.'.$step)])]);
            }
            $prev = AsmPackingStep::STEPS[$s->seq - 2] ?? null;
            if ($prev && $steps[$prev]->status !== 'COMPLETED') {
                throw ValidationException::withMessages(['step' => __('assembly.v.pk_prev', ['prev' => __('assembly.pk.step.'.$prev)])]);
            }
            $sizes = AsmPackingIn::query()->where('batch_id', $batch->id)->selectRaw('size, sum(qty) as qty')->groupBy('size')->pluck('qty', 'size');
            if ($sizes->sum() < 1) {
                throw ValidationException::withMessages(['step' => __('assembly.v.pk_nothing_in')]);
            }
            $s->update(['status' => 'IN_PROGRESS', 'started_at' => now(), 'started_by' => $this->caller()->auditUserId()]);
            if ($step === 'FOLDING' && $batch->status === 'PACKING_IN') {
                $batch->update(['status' => 'IN_PROGRESS']);
            }
            if ($step === 'LABEL') {
                $have = AsmPackingLabel::query()->where('batch_id', $batch->id)->pluck('size')->all();
                $serial = count($have);
                $stem = 'LBL'.str_replace(['PKG', '-'], '', $batch->packing_no);
                foreach ($sizes->sortBy(fn ($q, $size) => [PackingBook::sizeRank((string) $size), (string) $size]) as $size => $qty) {
                    if (! in_array((string) $size, $have, true)) {
                        AsmPackingLabel::query()->create(['batch_id' => $batch->id, 'size' => (string) $size, 'qty' => (int) $qty, 'barcode' => $stem.str_pad((string) ++$serial, 2, '0', STR_PAD_LEFT)]);
                    }
                }
            }

            return $batch;
        });

        return ApiResponse::ok($this->book()->present($batch->refresh()));
    }

    /**
     * Complete a process: Folding, Poly Packing and Assortment with the
     * verified quantity (at most what the step before handed on), Assortment
     * with the size ratio; Barcode & Label once every label is verified,
     * Carton Packing once a carton exists, Carton Marking once every carton is
     * marked, Carton Sealing once every carton is sealed.
     */
    public function completeStep(Request $request, string $id, string $step): JsonResponse
    {
        $this->allow('assembly.packing.manage');
        $this->assertStep($step);
        $data = $request->validate(['qty' => ['nullable', 'integer', 'min:1', 'max:999999'], 'ratio' => ['nullable', 'string', 'max:200'], 'remarks' => ['nullable', 'string', 'max:300']]);

        $batch = DB::transaction(function () use ($id, $step, $data) {
            [$batch, $steps] = $this->lockBatch($id);
            $s = $steps[$step];
            if ($s->status !== 'IN_PROGRESS') {
                throw ValidationException::withMessages(['step' => __('assembly.v.pk_not_started', ['step' => __('assembly.pk.step.'.$step)])]);
            }
            $sizes = AsmPackingIn::query()->where('batch_id', $batch->id)->selectRaw('size, sum(qty) as qty')->groupBy('size')->pluck('qty', 'size');
            $cartons = AsmPackingCarton::query()->where('batch_id', $batch->id)->get();
            $max = match ($step) {
                'FOLDING' => (int) $sizes->sum(),
                'POLY' => (int) $steps['FOLDING']->qty,
                'ASSORTMENT' => (int) $steps['POLY']->qty,
                default => null,
            };
            $qty = $max;
            if ($max !== null) {
                $qty = (int) ($data['qty'] ?? $max);
                if ($qty > $max) {
                    throw ValidationException::withMessages(['qty' => __('assembly.v.pk_over_max', ['qty' => $qty, 'max' => $max])]);
                }
            }
            if ($step === 'ASSORTMENT') {
                $batch->assort_ratio = $this->ratio($data['ratio'] ?? null, $sizes->keys()->map(fn ($k) => (string) $k)->all());
                $batch->save();
            }
            if ($step === 'LABEL') {
                $labels = AsmPackingLabel::query()->where('batch_id', $batch->id)->get();
                $left = $labels->whereNull('verified_at')->count();
                if ($labels->isEmpty() || $left > 0) {
                    throw ValidationException::withMessages(['step' => __('assembly.v.pk_labels_left', ['n' => $left])]);
                }
                $qty = (int) $labels->sum('qty');
            }
            if ($step === 'CARTON') {
                if ($cartons->isEmpty()) {
                    throw ValidationException::withMessages(['step' => __('assembly.v.pk_no_carton')]);
                }
                $qty = (int) $cartons->sum('qty');
            }
            if ($step === 'MARKING') {
                if (! $batch->shipping_mark || $cartons->where('status', 'OPEN')->isNotEmpty()) {
                    throw ValidationException::withMessages(['step' => __('assembly.v.pk_not_marked')]);
                }
                $qty = (int) $cartons->sum('qty');
            }
            if ($step === 'SEALING') {
                $left = $cartons->where('status', '!=', 'SEALED')->count();
                if ($left > 0) {
                    throw ValidationException::withMessages(['step' => __('assembly.v.pk_unsealed', ['n' => $left])]);
                }
                $qty = (int) $cartons->sum('qty');
            }
            $remarks = isset($data['remarks']) && trim($data['remarks']) !== '' ? trim($data['remarks']) : null;
            $s->update(['status' => 'COMPLETED', 'qty' => $qty, 'remarks' => $remarks, 'completed_at' => now(), 'completed_by' => $this->caller()->auditUserId()]);

            return $batch;
        });

        return ApiResponse::ok($this->book()->present($batch->refresh()));
    }

    /** Reopen a completed process — a supervisor's call with the reason, before the next process starts and before Packing Out. */
    public function reopenStep(Request $request, string $id, string $step): JsonResponse
    {
        $this->allow('assembly.packing.supervise');
        $this->assertStep($step);
        $data = $request->validate(['reason' => ['required', 'string', 'max:300']]);
        $batch = DB::transaction(function () use ($id, $step, $data) {
            [$batch, $steps] = $this->lockBatch($id);
            $s = $steps[$step];
            if ($s->status !== 'COMPLETED') {
                throw ValidationException::withMessages(['step' => __('assembly.v.pk_reopen_open', ['step' => __('assembly.pk.step.'.$step)])]);
            }
            $next = AsmPackingStep::STEPS[$s->seq] ?? null;
            if ($next && $steps[$next]->status !== 'PENDING') {
                throw ValidationException::withMessages(['step' => __('assembly.v.pk_reopen_next', ['next' => __('assembly.pk.step.'.$next)])]);
            }
            $s->update(['status' => 'IN_PROGRESS', 'completed_at' => null, 'completed_by' => null, 'reopen_reason' => trim($data['reason']), 'reopened_at' => now(), 'reopened_by' => $this->caller()->auditUserId()]);

            return $batch;
        });

        return ApiResponse::ok($this->book()->present($batch->refresh()));
    }

    /** Count a print (or reprint) of the batch's poly labels. */
    public function printLabels(string $id): JsonResponse
    {
        $this->allow('assembly.packing.manage');
        $batch = DB::transaction(function () use ($id) {
            [$batch, $steps] = $this->lockBatch($id);
            $this->inStep($steps, 'LABEL');
            AsmPackingLabel::query()->where('batch_id', $batch->id)->update(['print_count' => DB::raw('print_count + 1'), 'printed_at' => now()]);

            return $batch;
        });

        return ApiResponse::ok($this->book()->present($batch));
    }

    /** Verify a poly label by scanning it — this batch's, and once only. */
    public function verifyLabel(Request $request, string $id): JsonResponse
    {
        $this->allow('assembly.packing.manage');
        $data = $request->validate(['barcode' => ['required', 'string', 'max:40']]);
        [$batch, $label] = DB::transaction(function () use ($id, $data) {
            [$batch, $steps] = $this->lockBatch($id);
            $this->inStep($steps, 'LABEL');
            $code = strtoupper(str_replace('-', '', trim($data['barcode'])));
            $label = AsmPackingLabel::query()->where('barcode', $code)->lockForUpdate()->first();
            if (! $label || $label->batch_id !== $batch->id) {
                throw ValidationException::withMessages(['barcode' => __('assembly.v.pk_label_other', ['code' => $code, 'batch' => $batch->packing_no])]);
            }
            if ($label->print_count < 1) {
                throw ValidationException::withMessages(['barcode' => __('assembly.v.pk_label_unprinted', ['code' => $code])]);
            }
            if ($label->verified_at) {
                throw ValidationException::withMessages(['barcode' => __('assembly.v.pk_label_dup', ['code' => $code, 'at' => PackingBook::at($label->verified_at)])]);
            }
            $label->update(['verified_at' => now(), 'verified_by' => $this->caller()->auditUserId()]);

            return [$batch, $label];
        });

        return ApiResponse::ok(['size' => $label->size, 'batch' => $this->book()->present($batch)]);
    }

    /** Create carton(s) with their size break-up — never more of a size than is packed in, and whole sets of the ratio when the batch has one. */
    public function addCartons(Request $request, string $id): JsonResponse
    {
        $this->allow('assembly.packing.manage');
        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1', 'max:40'], 'lines.*.size' => ['required', 'string', 'max:20'], 'lines.*.qty' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'carton_type' => ['nullable', 'string', 'max:40'], 'carton_size' => ['nullable', 'string', 'max:40'],
            'gross_weight' => ['nullable', 'numeric', 'min:0', 'max:999999'], 'net_weight' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'copies' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);
        $lines = collect($data['lines'])->map(fn ($l) => ['size' => trim($l['size']), 'qty' => (int) ($l['qty'] ?? 0)])->filter(fn ($l) => $l['qty'] > 0)->keyBy('size');
        $copies = (int) ($data['copies'] ?? 1);
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('assembly.v.pk_carton_empty')]);
        }
        if (isset($data['gross_weight'], $data['net_weight']) && (float) $data['net_weight'] > (float) $data['gross_weight']) {
            throw ValidationException::withMessages(['net_weight' => __('assembly.v.pk_net_gross')]);
        }

        [$batch, $made] = DB::transaction(function () use ($id, $data, $lines, $copies) {
            [$batch, $steps] = $this->lockBatch($id);
            $this->inStep($steps, 'CARTON');
            $sizes = AsmPackingIn::query()->where('batch_id', $batch->id)->selectRaw('size, sum(qty) as qty')->groupBy('size')->pluck('qty', 'size');
            $inCartons = AsmPackingCartonLine::query()->whereIn('carton_id', AsmPackingCarton::query()->where('batch_id', $batch->id)->select('id'))
                ->selectRaw('size, sum(qty) as qty')->groupBy('size')->pluck('qty', 'size');
            foreach ($lines as $size => $l) {
                if (! $sizes->has($size)) {
                    throw ValidationException::withMessages(['lines' => __('assembly.v.pk_size_not_in', ['size' => $size])]);
                }
                $left = (int) $sizes[$size] - (int) ($inCartons[$size] ?? 0);
                if ($left < $l['qty'] * $copies) {
                    throw ValidationException::withMessages(['lines' => __('assembly.v.pk_size_over', ['size' => $size, 'qty' => $l['qty'] * $copies, 'left' => $left])]);
                }
            }
            if ($batch->assort_ratio) {
                $this->assertWholeSets($batch->assort_ratio, $lines->map(fn ($l) => $l['qty'])->all());
            }
            $serial = (int) AsmPackingCarton::query()->where('batch_id', $batch->id)->max('serial');
            $made = [];
            for ($n = 0; $n < $copies; $n++) {
                $carton = AsmPackingCarton::query()->create([
                    'batch_id' => $batch->id, 'serial' => ++$serial, 'carton_no' => $this->book()->nextNo('CTN', 'asm_packing_cartons', 'carton_no'),
                    'carton_type' => $this->text($data['carton_type'] ?? null), 'carton_size' => $this->text($data['carton_size'] ?? null),
                    'gross_weight' => $data['gross_weight'] ?? null, 'net_weight' => $data['net_weight'] ?? null, 'qty' => (int) $lines->sum('qty'),
                    'status' => 'OPEN', 'created_by' => $this->caller()->auditUserId(),
                ]);
                foreach ($lines as $l) {
                    AsmPackingCartonLine::query()->create(['carton_id' => $carton->id, 'size' => $l['size'], 'qty' => $l['qty']]);
                }
                $made[] = $carton->carton_no;
            }

            return [$batch, $made];
        });

        return ApiResponse::created(['cartons' => $made, 'batch' => $this->book()->present($batch)]);
    }

    /** Remove an unsealed carton while Carton Packing is open; the cartons after it close up. */
    public function deleteCarton(string $id): JsonResponse
    {
        $this->allow('assembly.packing.manage');
        [$batch, $no] = DB::transaction(function () use ($id) {
            $carton = AsmPackingCarton::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            [$batch, $steps] = $this->lockBatch($carton->batch_id);
            $this->inStep($steps, 'CARTON');
            if ($carton->status === 'SEALED') {
                throw ValidationException::withMessages(['id' => __('assembly.v.pk_carton_sealed', ['no' => $carton->carton_no])]);
            }
            $no = $carton->carton_no;
            $carton->delete();
            foreach (AsmPackingCarton::query()->where('batch_id', $batch->id)->where('serial', '>', $carton->serial)->orderBy('serial')->get() as $c) {
                $c->update(['serial' => $c->serial - 1]);
            }

            return [$batch, $no];
        });

        return ApiResponse::ok(['carton_no' => $no, 'batch' => $this->book()->present($batch)]);
    }

    /** Save the destination and shipping mark; every carton not yet marked is marked. */
    public function saveMarking(Request $request, string $id): JsonResponse
    {
        $this->allow('assembly.packing.manage');
        $data = $request->validate([
            'destination' => ['required', 'string', 'max:120'], 'country' => ['nullable', 'string', 'max:80'], 'shipping_mark' => ['required', 'string', 'max:200'],
            'special_instruction' => ['nullable', 'string', 'max:300'],
        ], ['destination.required' => __('assembly.v.pk_destination'), 'shipping_mark.required' => __('assembly.v.pk_shipping_mark')]);
        $batch = DB::transaction(function () use ($id, $data) {
            [$batch, $steps] = $this->lockBatch($id);
            $this->inStep($steps, 'MARKING');
            $batch->update(['destination' => trim($data['destination']), 'country' => $this->text($data['country'] ?? null), 'shipping_mark' => trim($data['shipping_mark']),
                'special_instruction' => $this->text($data['special_instruction'] ?? null)]);
            AsmPackingCarton::query()->where('batch_id', $batch->id)->where('status', 'OPEN')->update(['status' => 'MARKED']);

            return $batch;
        });

        return ApiResponse::ok($this->book()->present($batch->refresh()));
    }

    /** Count a print (or reprint) of the carton marking. */
    public function printMarking(string $id): JsonResponse
    {
        $this->allow('assembly.packing.manage');
        $batch = DB::transaction(function () use ($id) {
            [$batch, $steps] = $this->lockBatch($id);
            $this->inStep($steps, 'MARKING');
            if (! $batch->shipping_mark || AsmPackingCarton::query()->where('batch_id', $batch->id)->where('status', 'OPEN')->exists()) {
                throw ValidationException::withMessages(['step' => __('assembly.v.pk_not_marked')]);
            }
            $batch->increment('marking_print_count');

            return $batch;
        });

        return ApiResponse::ok($this->book()->present($batch->refresh()));
    }

    /** Seal a scanned carton once its quantity, barcode, weights and marking check out; weights keyed here are taken as weighed. */
    public function sealCarton(Request $request, string $id): JsonResponse
    {
        $this->allow('assembly.packing.manage');
        $data = $request->validate(['barcode' => ['required', 'string', 'max:40'], 'gross_weight' => ['nullable', 'numeric', 'min:0', 'max:999999'], 'net_weight' => ['nullable', 'numeric', 'min:0', 'max:999999']]);
        [$batch, $no] = DB::transaction(function () use ($id, $data) {
            [$batch, $steps] = $this->lockBatch($id);
            $this->inStep($steps, 'SEALING');
            $flat = strtoupper(str_replace('-', '', trim($data['barcode'])));
            $carton = AsmPackingCarton::query()->whereRaw("replace(carton_no, '-', '') = ?", [$flat])->lockForUpdate()->first();
            if (! $carton || $carton->batch_id !== $batch->id) {
                throw ValidationException::withMessages(['barcode' => __('assembly.v.pk_carton_other', ['code' => $data['barcode'], 'batch' => $batch->packing_no])]);
            }
            if ($carton->status === 'SEALED') {
                throw ValidationException::withMessages(['barcode' => __('assembly.v.pk_carton_dup', ['no' => $carton->carton_no])]);
            }
            if ($carton->status !== 'MARKED' || ! $batch->shipping_mark) {
                throw ValidationException::withMessages(['barcode' => __('assembly.v.pk_carton_unmarked', ['no' => $carton->carton_no])]);
            }
            if ($carton->qty < 1) {
                throw ValidationException::withMessages(['barcode' => __('assembly.v.pk_carton_empty_qty', ['no' => $carton->carton_no])]);
            }
            $gross = isset($data['gross_weight']) ? (float) $data['gross_weight'] : ($carton->gross_weight !== null ? (float) $carton->gross_weight : null);
            $net = isset($data['net_weight']) ? (float) $data['net_weight'] : ($carton->net_weight !== null ? (float) $carton->net_weight : null);
            if (! $gross) {
                throw ValidationException::withMessages(['gross_weight' => __('assembly.v.pk_gross', ['no' => $carton->carton_no])]);
            }
            if (! $net) {
                throw ValidationException::withMessages(['net_weight' => __('assembly.v.pk_net', ['no' => $carton->carton_no])]);
            }
            if ($net > $gross) {
                throw ValidationException::withMessages(['net_weight' => __('assembly.v.pk_net_gross')]);
            }
            $carton->update(['gross_weight' => $gross, 'net_weight' => $net, 'status' => 'SEALED', 'sealed_at' => now(), 'sealed_by' => $this->caller()->auditUserId()]);

            return [$batch, $carton->carton_no];
        });

        return ApiResponse::ok(['carton_no' => $no, 'batch' => $this->book()->present($batch)]);
    }

    /** Unseal a carton — a supervisor's call with the reason, while Carton Sealing is still open. */
    public function unsealCarton(Request $request, string $id): JsonResponse
    {
        $this->allow('assembly.packing.supervise');
        $data = $request->validate(['reason' => ['required', 'string', 'max:300']]);
        [$batch, $no] = DB::transaction(function () use ($id, $data) {
            $carton = AsmPackingCarton::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            [$batch, $steps] = $this->lockBatch($carton->batch_id);
            $this->inStep($steps, 'SEALING');
            if ($carton->status !== 'SEALED') {
                throw ValidationException::withMessages(['id' => __('assembly.v.pk_not_sealed', ['no' => $carton->carton_no])]);
            }
            $carton->update(['status' => 'MARKED', 'sealed_at' => null, 'sealed_by' => null, 'unseal_reason' => trim($data['reason'])]);

            return [$batch, $carton->carton_no];
        });

        return ApiResponse::ok(['carton_no' => $no, 'batch' => $this->book()->present($batch)]);
    }

    /** The Packing Out desk: batches with every process completed, and what was packed out today. */
    public function outDesk(): JsonResponse
    {
        $this->allow('assembly.packing.view');
        $active = AsmPackingBatch::query()->where('status', 'IN_PROGRESS')->orderBy('created_at')->orderBy('id')->get();
        $done = AsmPackingStep::query()->whereIn('batch_id', $active->pluck('id'))->get()->groupBy('batch_id')
            ->filter(fn ($g) => $g->every(fn ($s) => $s->status === 'COMPLETED'))->keys();
        $today = AsmPackingBatch::query()->where('status', 'PACKED_OUT')->whereDate('out_date', PackingBook::today()->toDateString())->orderByDesc('packed_out_at')->get();

        return ApiResponse::ok(['waiting' => $this->book()->presentAll($active->whereIn('id', $done)->values()), 'today' => $this->book()->presentAll($today)]);
    }

    /** What Packing Out would do with a scanned packing number, carton or bundle — the batch, every check, and whether it may go out. */
    public function previewOut(Request $request): JsonResponse
    {
        $this->allow('assembly.packing.view');
        $q = $request->validate(['code' => ['required', 'string', 'max:40']]);
        $batch = $this->book()->find($q['code']) ?? throw ValidationException::withMessages(['code' => __('assembly.v.pk_unknown', ['code' => $q['code']])]);
        $p = $this->book()->present($batch);
        $checks = PackingBook::checks($p);
        $ok = $batch->status === 'IN_PROGRESS' && collect($checks)->every(fn ($c) => $c['ok']);
        $why = match (true) {
            $batch->status === 'PACKED_OUT' => 'packed_out',
            $batch->status === 'PACKING_IN' => 'not_started',
            ! $ok => 'checks',
            default => null,
        };

        return ApiResponse::ok(['ok' => $ok, 'reason' => $why, 'batch' => $p, 'checks' => $checks, 'sealed_qty' => $p['sealed_qty']]);
    }

    /** Pack a batch out: every check passes, the final packed quantity (at most what the sealed cartons hold) moves to Ready for Shipment and is booked into finished goods stock. */
    public function confirmOut(Request $request): JsonResponse
    {
        $this->allow('assembly.packing.manage');
        $data = $request->validate(['packing_no' => ['required', 'string', 'max:20'], 'final_qty' => ['required', 'integer', 'min:1', 'max:999999']], ['final_qty.required' => __('assembly.v.pk_final')]);
        $batch = DB::transaction(function () use ($data) {
            $batch = AsmPackingBatch::query()->where('packing_no', strtoupper(trim($data['packing_no'])))->lockForUpdate()->first()
                ?? throw ValidationException::withMessages(['packing_no' => __('assembly.v.pk_unknown', ['code' => $data['packing_no']])]);
            if ($batch->status === 'PACKED_OUT') {
                throw ValidationException::withMessages(['packing_no' => __('assembly.v.pk_already_out', ['batch' => $batch->packing_no, 'at' => PackingBook::at($batch->packed_out_at)])]);
            }
            $p = $this->book()->present($batch);
            $failed = collect(PackingBook::checks($p))->first(fn ($c) => ! $c['ok']);
            if ($batch->status !== 'IN_PROGRESS' || $failed) {
                throw ValidationException::withMessages(['packing_no' => __('assembly.v.pk_out_checks', ['batch' => $batch->packing_no])]);
            }
            if ((int) $data['final_qty'] > $p['sealed_qty']) {
                throw ValidationException::withMessages(['final_qty' => __('assembly.v.pk_final_over', ['qty' => $data['final_qty'], 'sealed' => $p['sealed_qty']])]);
            }
            $batch->update(['status' => 'PACKED_OUT', 'final_packed_qty' => (int) $data['final_qty'], 'out_date' => PackingBook::today()->toDateString(),
                'packed_out_at' => now(), 'packed_out_by' => $this->caller()->auditUserId()]);
            PackingBook::bookIn($batch, $this->caller()->auditUserId());

            return $batch;
        });

        return ApiResponse::ok(['packing_no' => $batch->packing_no, 'final_packed_qty' => $batch->final_packed_qty]);
    }

    /** The Packing Dashboard: the flow from QC Pass to Shipment, the key figures, the floor, and Ready for Shipment. */
    public function dashboard(): JsonResponse
    {
        $this->allow('assembly.packing.view');
        $today = PackingBook::today()->toDateString();
        $batches = AsmPackingBatch::query()->where('status', '!=', 'CANCELLED')->get();
        $ins = AsmPackingIn::query()->whereIn('batch_id', $batches->pluck('id'))->get();
        $inQty = $ins->groupBy('batch_id')->map(fn ($g) => (int) $g->sum('qty'));
        $steps = AsmPackingStep::query()->whereIn('batch_id', $batches->pluck('id'))->get();
        $active = $batches->whereIn('status', ['PACKING_IN', 'IN_PROGRESS']);
        $activeSteps = $steps->whereIn('batch_id', $active->pluck('id'));
        $out = $batches->where('status', 'PACKED_OUT');
        $passed = (int) AsmQualityOut::query()->sum('pass_qty');
        $pending = $this->pendingQty();
        $stage = fn (string $s) => match (true) {
            $activeSteps->where('step', $s)->where('status', 'IN_PROGRESS')->isNotEmpty() => 'IN_PROGRESS',
            $activeSteps->where('step', $s)->where('status', 'PENDING')->isNotEmpty() => 'PENDING',
            default => 'COMPLETED',
        };
        $packingIn = (int) $active->where('status', 'PACKING_IN')->sum(fn ($b) => $inQty[$b->id] ?? 0);
        $pipeline = [
            ['key' => 'qc', 'qty' => $passed, 'status' => $pending > 0 ? 'PENDING' : 'COMPLETED'],
            ['key' => 'in', 'qty' => $packingIn, 'status' => $packingIn > 0 ? 'IN_PROGRESS' : 'COMPLETED'],
        ];
        foreach (AsmPackingStep::STEPS as $s) {
            $pipeline[] = ['key' => $s, 'qty' => (int) $activeSteps->where('step', $s)->where('status', 'COMPLETED')->sum('qty'), 'status' => $stage($s)];
        }
        $readyQty = (int) $out->sum('final_packed_qty');
        $pipeline[] = ['key' => 'out', 'qty' => (int) $out->filter(fn ($b) => $b->out_date?->toDateString() === $today)->sum('final_packed_qty'), 'status' => 'COMPLETED'];
        $shippedQty = (int) AsmFgLedger::query()->whereIn('doc_type', ['SHIPMENT', 'SHIPMENT_REVERSAL'])->get()->sum(fn ($l) => $l->qty_out - $l->qty_in);
        $pipeline[] = ['key' => 'ready', 'qty' => $readyQty - $shippedQty, 'status' => 'READY'];
        $pipeline[] = ['key' => 'shipment', 'qty' => $shippedQty, 'status' => 'SHIPPED'];
        $cartons = AsmPackingCarton::query()->whereIn('batch_id', $batches->pluck('id'))->get(['batch_id', 'status']);
        $book = $this->book();
        $ready = (new ReadyBook($book))->rows()->where('ready_qty', '>', 0)->map(fn ($r) => collect($r)->only(['key', 'buyer', 'po_no', 'style', 'colour', 'ready_qty', 'cartons', 'ship_date', 'batches', 'status'])->all())->values();

        return ApiResponse::ok([
            'today' => $today,
            'pipeline' => $pipeline,
            'kpis' => [
                'today_packing' => (int) $ins->filter(fn ($i) => $i->in_date->toDateString() === $today)->sum('qty'), 'packing_in' => $packingIn,
                'in_progress' => (int) $active->where('status', 'IN_PROGRESS')->sum(fn ($b) => $inQty[$b->id] ?? 0), 'completed' => $readyQty,
                'packing_out_today' => (int) $out->filter(fn ($b) => $b->out_date?->toDateString() === $today)->sum('final_packed_qty'),
                'total_cartons' => $cartons->count(), 'pending_packing' => $pending, 'ready_for_shipment' => $readyQty - $shippedQty, 'total_shipped' => $shippedQty, 'total_packing_qty' => (int) $ins->sum('qty'),
            ],
            'active' => $book->presentAll($active->sortBy([['created_at', 'asc'], ['id', 'asc']])->values()),
            'ready' => $ready->all(),
            'shipments' => AsmShipment::query()->whereNotIn('status', ['CANCELLED', 'REVERSED'])->orderByDesc('ship_date')->orderByDesc('created_at')->limit(10)->get()
                ->map(fn ($s) => (new ReadyBook($book))->shipment($s))->all(),
        ]);
    }

    /** A bundle's, batch's, carton's or label's whole history through packing. */
    public function trace(Request $request): JsonResponse
    {
        $this->allow('assembly.packing.view');
        $q = $request->validate(['code' => ['required', 'string', 'max:40']]);
        $code = strtoupper(trim($q['code']));
        $flat = str_replace('-', '', $code);
        $book = $this->book();
        $card = SewingFloor::card($code);
        $carton = $card ? null : AsmPackingCarton::query()->whereRaw("replace(carton_no, '-', '') = ?", [$flat])->first();
        $label = $card || $carton ? null : AsmPackingLabel::query()->where('barcode', $flat)->first();
        $batch = $book->find($code);
        if (! $card && ! $batch) {
            throw ValidationException::withMessages(['code' => __('assembly.v.pk_unknown', ['code' => $q['code']])]);
        }
        $kind = $card ? 'BUNDLE' : ($carton ? 'CARTON' : ($label ? 'LABEL' : 'PACKING'));
        $events = [];
        if ($card) {
            $qcOut = PackingBook::qualityOut($card);
            if ($qcOut) {
                $events[] = ['stage' => 'QC_PASS', 'params' => ['pass' => $qcOut->pass_qty, 'reject' => $qcOut->reject_qty], 'at' => PackingBook::at($qcOut->created_at)];
            }
            foreach (AsmPackingIn::query()->where('bundle_id', $card->id)->orderBy('created_at')->get() as $i) {
                $events[] = ['stage' => 'PACKING_IN', 'params' => ['qty' => $i->qty, 'batch' => AsmPackingBatch::query()->whereKey($i->batch_id)->value('packing_no'), 'by' => $book->userName($i->created_by)], 'at' => PackingBook::at($i->created_at)];
            }
        }
        if ($batch) {
            if (! $card) {
                $ins = AsmPackingIn::query()->where('batch_id', $batch->id)->orderBy('created_at')->get();
                $events[] = ['stage' => 'BATCH_IN', 'params' => ['qty' => (int) $ins->sum('qty'), 'bundles' => $ins->pluck('bundle_id')->unique()->count(), 'line' => $book->lines()->get($batch->line_id)?->label], 'at' => PackingBook::at($ins->first()?->created_at)];
            }
            foreach (AsmPackingStep::query()->where('batch_id', $batch->id)->orderBy('seq')->get() as $s) {
                if ($s->started_at) {
                    $events[] = ['stage' => 'STEP_START', 'params' => ['step' => $s->step, 'by' => $book->userName($s->started_by)], 'at' => PackingBook::at($s->started_at)];
                }
                if ($s->reopened_at) {
                    $events[] = ['stage' => 'STEP_REOPEN', 'params' => ['step' => $s->step, 'reason' => $s->reopen_reason, 'by' => $book->userName($s->reopened_by)], 'at' => PackingBook::at($s->reopened_at)];
                }
                if ($s->completed_at) {
                    $events[] = ['stage' => 'STEP_DONE', 'params' => ['step' => $s->step, 'qty' => $s->qty, 'by' => $book->userName($s->completed_by)], 'at' => PackingBook::at($s->completed_at)];
                }
            }
            if ($label) {
                if ($label->printed_at) {
                    $events[] = ['stage' => 'LABEL_PRINT', 'params' => ['size' => $label->size, 'n' => $label->print_count], 'at' => PackingBook::at($label->printed_at)];
                }
                if ($label->verified_at) {
                    $events[] = ['stage' => 'LABEL_VERIFY', 'params' => ['size' => $label->size, 'by' => $book->userName($label->verified_by)], 'at' => PackingBook::at($label->verified_at)];
                }
            }
            if ($carton) {
                $events[] = ['stage' => 'CARTON_MADE', 'params' => ['no' => $carton->carton_no, 'qty' => $carton->qty, 'by' => $book->userName($carton->created_by)], 'at' => PackingBook::at($carton->created_at)];
                if ($carton->sealed_at) {
                    $events[] = ['stage' => 'CARTON_SEALED', 'params' => ['no' => $carton->carton_no, 'gross' => (float) $carton->gross_weight, 'net' => (float) $carton->net_weight, 'by' => $book->userName($carton->sealed_by)], 'at' => PackingBook::at($carton->sealed_at)];
                }
            }
            if ($batch->packed_out_at) {
                $events[] = ['stage' => 'PACKED_OUT', 'params' => ['qty' => $batch->final_packed_qty, 'by' => $book->userName($batch->packed_out_by)], 'at' => PackingBook::at($batch->packed_out_at)];
            }
        }
        usort($events, fn ($a, $b) => strcmp((string) $a['at'], (string) $b['at']));
        [$buyer] = $book->buyer($batch?->buyer_id ?? $card?->buyer_id);

        return ApiResponse::ok([
            'kind' => $kind, 'code' => $card?->bundle_no ?? $carton?->carton_no ?? $label?->barcode ?? $batch->packing_no,
            'headline' => ['batch' => $batch?->packing_no, 'buyer' => $buyer, 'po_no' => $batch?->po_no ?? $card?->po_no, 'style' => $batch?->style ?? $card?->style,
                'colour' => $batch?->colour ?? $card?->colour, 'stage' => $batch ? $book->stage($batch, AsmPackingStep::query()->where('batch_id', $batch->id)->get()) : null],
            'events' => $events,
        ]);
    }

    /** QC Pass not packed in yet, over every bundle that left Quality with something passed. */
    private function pendingQty(): int
    {
        $passed = AsmQualityOut::query()->where('pass_qty', '>', 0)->get(['id', 'pass_qty']);
        $packed = AsmPackingIn::query()->whereIn('quality_out_id', $passed->pluck('id'))->selectRaw('quality_out_id, sum(qty) as qty')->groupBy('quality_out_id')->pluck('qty', 'quality_out_id');

        return (int) $passed->sum(fn ($o) => max(0, $o->pass_qty - (int) ($packed[$o->id] ?? 0)));
    }

    /**
     * A bundle at Packing In: the Quality OUT it left with, what it has packed
     * in already, and why it may not pack in now (or null).
     *
     * @return array{0: ?AsmQualityOut, 1: int, 2: ?string}
     */
    private function inState(CutBundle $card): array
    {
        $qcOut = PackingBook::qualityOut($card);
        $packed = PackingBook::packedQty($card->id);
        $why = match (true) {
            $card->status === 'CANCELLED' => __('assembly.v.cancelled', ['no' => $card->bundle_no]),
            ! AsmQualityIn::query()->where('bundle_id', $card->id)->exists() => __('assembly.v.pk_no_quality', ['no' => $card->bundle_no]),
            ! $qcOut => __('assembly.v.pk_not_qc_out', ['no' => $card->bundle_no]),
            $qcOut->pass_qty < 1 => __('assembly.v.pk_no_pass', ['no' => $card->bundle_no]),
            $packed >= $qcOut->pass_qty => __('assembly.v.pk_fully_packed', ['no' => $card->bundle_no, 'qty' => $packed]),
            default => null,
        };

        return [$qcOut, $packed, $why];
    }

    /** The batch of this bundle's PO and colour still taking bundles (Folding not started), or null. */
    private function openBatch(CutBundle $card, bool $lock = false): ?AsmPackingBatch
    {
        $q = AsmPackingBatch::query()->where('status', 'PACKING_IN')->where('colour', $card->colour)
            ->where(fn ($w) => $card->order_id ? $w->where('order_id', $card->order_id) : $w->whereNull('order_id'))
            ->where(fn ($w) => $card->po_no ? $w->where('po_no', $card->po_no) : $w->whereNull('po_no'))
            ->orderBy('created_at');

        return ($lock ? $q->lockForUpdate() : $q)->first();
    }

    /**
     * Parse an assortment ratio — "S:1, M:2, L:2, XL:1" — into its canonical
     * form; blank is solid-size packing. Every ratio size must be packed in.
     */
    private function ratio(?string $raw, array $sizes): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        $parts = [];
        $packed = array_map('strtoupper', $sizes);
        foreach (preg_split('/[,;]/', $raw) as $part) {
            if (trim($part) === '') {
                continue;
            }
            if (! preg_match('/^\s*([^:\s][^:]*?)\s*:\s*(\d{1,4})\s*$/', $part, $m) || (int) $m[2] < 1) {
                throw ValidationException::withMessages(['ratio' => __('assembly.v.pk_ratio_format')]);
            }
            $i = array_search(strtoupper($m[1]), $packed, true);
            if ($i === false) {
                throw ValidationException::withMessages(['ratio' => __('assembly.v.pk_ratio_size', ['size' => $m[1]])]);
            }
            if (isset($parts[$sizes[$i]])) {
                throw ValidationException::withMessages(['ratio' => __('assembly.v.pk_ratio_twice', ['size' => $sizes[$i]])]);
            }
            $parts[$sizes[$i]] = (int) $m[2];
        }

        return $parts ? implode(', ', array_map(fn ($s, $n) => $s.':'.$n, array_keys($parts), $parts)) : null;
    }

    /** A ratio-packed carton holds whole sets: the same multiple of every ratio size, and nothing else. */
    private function assertWholeSets(string $ratio, array $lines): void
    {
        $want = [];
        foreach (explode(',', $ratio) as $p) {
            [$s, $n] = array_map('trim', explode(':', $p));
            $want[$s] = (int) $n;
        }
        $k = null;
        foreach ($want as $s => $n) {
            $got = (int) ($lines[$s] ?? 0);
            if ($got % $n !== 0 || ($k !== null && intdiv($got, $n) !== $k)) {
                $k = -1;
                break;
            }
            $k = intdiv($got, $n);
        }
        if ($k === null || $k < 1 || array_diff(array_keys($lines), array_keys($want))) {
            throw ValidationException::withMessages(['lines' => __('assembly.v.pk_whole_sets', ['ratio' => $ratio])]);
        }
    }

    /** @return array{0: AsmPackingBatch, 1: Collection<string, AsmPackingStep>} the batch and its steps, locked; packed-out and cancelled batches refuse. */
    private function lockBatch(string $id): array
    {
        $batch = AsmPackingBatch::query()->whereKey($id)->lockForUpdate()->firstOrFail();
        if ($batch->status === 'PACKED_OUT') {
            throw ValidationException::withMessages(['batch' => __('assembly.v.pk_already_out', ['batch' => $batch->packing_no, 'at' => PackingBook::at($batch->packed_out_at)])]);
        }
        if ($batch->status === 'CANCELLED') {
            throw ValidationException::withMessages(['batch' => __('assembly.v.pk_cancelled', ['batch' => $batch->packing_no])]);
        }

        return [$batch, AsmPackingStep::query()->where('batch_id', $batch->id)->lockForUpdate()->get()->keyBy('step')];
    }

    private function inStep(Collection $steps, string $step): void
    {
        if ($steps[$step]->status !== 'IN_PROGRESS') {
            throw ValidationException::withMessages(['step' => __('assembly.v.pk_not_started', ['step' => __('assembly.pk.step.'.$step)])]);
        }
    }

    private function assertStep(string $step): void
    {
        if (! in_array($step, AsmPackingStep::STEPS, true)) {
            abort(404);
        }
    }

    private function pickLine(string $id): object
    {
        $line = $this->book()->lines()->get($id);
        if (! $line || ! $line->active) {
            throw ValidationException::withMessages(['line_id' => __('assembly.v.pk_line')]);
        }

        return $line;
    }

    private function text(?string $v): ?string
    {
        return $v !== null && trim($v) !== '' ? trim($v) : null;
    }

    private function book(): PackingBook
    {
        return $this->book ??= new PackingBook($this->caller()->companyId);
    }
}
