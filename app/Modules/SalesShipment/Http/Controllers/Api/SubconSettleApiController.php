<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Http\Controllers\Api;

use App\Modules\SalesShipment\Models\SsScDelivery;
use App\Modules\SalesShipment\Models\SsScDeliveryLine;
use App\Modules\SalesShipment\Models\SsScReceipt;
use App\Modules\SalesShipment\Models\SsScReconciliation;
use App\Modules\SalesShipment\Models\SsScReconciliationLine;
use App\Modules\SalesShipment\Models\SsScReturn;
use App\Modules\SalesShipment\Models\SsScReturnLine;
use App\Modules\SalesShipment\Support\SalesBook;
use App\Modules\SalesShipment\Support\SsSettings;
use App\Modules\SalesShipment\Support\SubconBook;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Sales & Shipment → Sub-Contract (AnnotechERP 21/03): the Sub-Contract
 * Return — received goods sent back to the party (never more than came in
 * on that receipt line, less other returns), moving the chalan's balance
 * once confirmed; and the Sub-Contract Reconciliation — a chalan squared
 * line by line (delivered, received, rejected and returned read off it,
 * short agreed, balance worked out: pending, matched, short or excess),
 * reconciled to close it, reopened only by an administrator.
 */
class SubconSettleApiController extends ApiController
{
    private ?SalesBook $book = null;

    /* ── Returns ───────────────────────────────────────────────────── */

    public function returns(): JsonResponse
    {
        $this->allow('salesshipment.subcon.view');
        $b = $this->book();
        $rows = SsScReturn::query()->orderByDesc('return_date')->orderByDesc('return_no')->get();
        $receipts = DB::table('ss_sc_receipts as r')->join('ss_sc_deliveries as d', 'd.id', '=', 'r.delivery_id')->whereIn('r.id', $rows->pluck('receipt_id'))
            ->get(['r.id', 'r.mrr_no', 'd.chalan_no', 'd.customer_id'])->keyBy('id');
        $qty = DB::table('ss_sc_return_lines')->whereIn('return_id', $rows->pluck('id'))->groupBy('return_id')->selectRaw('return_id, sum(qty) as q')->pluck('q', 'return_id');
        $names = $b->customerNames();

        return ApiResponse::ok($rows->map(fn (SsScReturn $t) => [
            'return_no' => $t->return_no, 'return_date' => $t->return_date->toDateString(), 'mrr_no' => $receipts[$t->receipt_id]->mrr_no ?? '—', 'chalan_no' => $receipts[$t->receipt_id]->chalan_no ?? '—',
            'party' => $names[$receipts[$t->receipt_id]->customer_id ?? ''] ?? '—', 'customer_id' => $receipts[$t->receipt_id]->customer_id ?? null, 'reason' => $t->reason,
            'total_qty' => (float) ($qty[$t->id] ?? 0), 'confirmed_by' => $b->trail($t->confirmed_by, $t->confirmed_at), 'state' => $t->state,
        ])->values()->all());
    }

    public function returnShow(string $no): JsonResponse
    {
        $this->allow('salesshipment.subcon.view');

        return ApiResponse::ok($this->presentReturn($this->findReturn($no)));
    }

    /** The S/C Receive List on the left: receipts still open, with what can still go back. */
    public function receipts(): JsonResponse
    {
        $this->allow('salesshipment.subcon.view');
        $rows = SsScReceipt::query()->where('state', 'RECEIVED')->orderByDesc('mrr_date')->orderByDesc('mrr_no')->get();
        $deliveries = DB::table('ss_sc_deliveries')->whereIn('id', $rows->pluck('delivery_id'))->get(['id', 'chalan_no', 'closed'])->keyBy('id');
        $names = $this->book()->customerNames();

        return ApiResponse::ok($rows->filter(fn ($r) => ! ($deliveries[$r->delivery_id]->closed ?? false))->map(fn (SsScReceipt $r) => [
            'mrr_no' => $r->mrr_no, 'chalan_no' => $deliveries[$r->delivery_id]->chalan_no ?? '—', 'party' => $names[$r->customer_id] ?? '—', 'customer_id' => $r->customer_id,
            'returnable' => round((float) collect($this->returnSourceOf($r, null)['lines'])->sum('returnable'), 4),
        ])->values()->all());
    }

    public function returnSource(Request $request, string $mrr): JsonResponse
    {
        $this->allow('salesshipment.subcon.view');
        $r = SsScReceipt::query()->where('mrr_no', $mrr)->firstOrFail();
        $except = $request->query('except') ? SsScReturn::query()->where('return_no', $request->query('except'))->value('id') : null;

        return ApiResponse::ok($this->returnSourceOf($r, $except));
    }

    public function saveReturn(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('salesshipment.subcon.manage');
        $row = $no ? $this->findReturn($no) : null;
        if ($row && $row->state !== 'DRAFT') {
            throw ValidationException::withMessages(['return_no' => __('salesshipment.v.locked', ['no' => $row->return_no, 'status' => __('salesshipment.sct.state.'.$row->state)])]);
        }
        $data = $request->validate([
            'mrr_no' => ['required', 'string', 'max:24'], 'return_date' => ['required', 'date'], 'reason' => ['required', Rule::in(SsScReturn::REASONS)], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['nullable', 'array'], 'lines.*.receipt_line_id' => ['required', 'string', 'max:26', 'distinct'], 'lines.*.qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], ['reason.required' => __('salesshipment.v.sct_reason'), 'lines.*.receipt_line_id.distinct' => __('salesshipment.v.line_twice')]);
        $r = SsScReceipt::query()->where('mrr_no', $data['mrr_no'])->firstOrFail();
        if ($row && $row->receipt_id !== $r->id) {
            throw ValidationException::withMessages(['mrr_no' => __('salesshipment.v.sct_receipt_fixed', ['no' => $row->return_no])]);
        }
        if ($r->state !== 'RECEIVED' || DB::table('ss_sc_deliveries')->where('id', $r->delivery_id)->value('closed')) {
            throw ValidationException::withMessages(['mrr_no' => __('salesshipment.v.sct_receipt_open', ['no' => $r->mrr_no])]);
        }
        if ($data['return_date'] < $r->mrr_date->toDateString()) {
            throw ValidationException::withMessages(['return_date' => __('salesshipment.v.sct_before', ['date' => $r->mrr_date->format('d M Y')])]);
        }
        $this->book()->assertOpen($data['return_date'], 'return_date');
        $lines = collect(array_values($data['lines'] ?? []))->filter(fn ($l) => (float) ($l['qty'] ?? 0) > 0)->values();
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('salesshipment.v.sct_lines')]);
        }
        $src = collect($this->returnSourceOf($r, $row?->id)['lines'])->keyBy('receipt_line_id');
        foreach ($lines as $i => $l) {
            $s = $src[$l['receipt_line_id']] ?? null;
            if (! $s) {
                throw ValidationException::withMessages(["lines.{$i}.receipt_line_id" => __('salesshipment.v.sct_line_unknown', ['no' => $r->mrr_no])]);
            }
            if ((float) $l['qty'] > $s['returnable'] + 0.00005) {
                throw ValidationException::withMessages(["lines.{$i}.qty" => __('salesshipment.v.sct_over', ['line' => $s['label'], 'max' => $this->num($s['returnable'])])]);
            }
        }
        $head = ['receipt_id' => $r->id, 'return_date' => $data['return_date'], 'reason' => $data['reason'], 'remarks' => $data['remarks'] ?? null];
        $row = DB::transaction(function () use ($row, $head, $lines, $data) {
            if ($row) {
                $row->update($head);
                SsScReturnLine::query()->where('return_id', $row->id)->delete();
            } else {
                $row = SsScReturn::query()->create($head + ['return_no' => SsSettings::nextNo('prefix_sc_return', 'ss_sc_returns', 'return_no', $data['return_date'], $this->caller()->companyId),
                    'state' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            }
            foreach ($lines as $l) {
                SsScReturnLine::query()->create(['return_id' => $row->id, 'receipt_line_id' => $l['receipt_line_id'], 'qty' => $l['qty'], 'remarks' => $l['remarks'] ?? null]);
            }

            return $row;
        });

        return ApiResponse::ok($this->presentReturn($row->refresh()), [], $no ? 200 : 201);
    }

    /** Confirm puts the quantity back out with the party; Cancel withdraws it. */
    public function returnAct(string $no, string $action): JsonResponse
    {
        $from = ['confirm' => ['DRAFT'], 'cancel' => ['DRAFT', 'CONFIRMED']];
        abort_unless(isset($from[$action]), 404);
        $this->allow('salesshipment.subcon.manage');
        $row = DB::transaction(function () use ($no, $action, $from) {
            $row = SsScReturn::query()->where('return_no', $no)->lockForUpdate()->firstOrFail();
            $closed = DB::table('ss_sc_receipts as r')->join('ss_sc_deliveries as d', 'd.id', '=', 'r.delivery_id')->where('r.id', $row->receipt_id)->value('d.closed');
            if (! in_array($row->state, $from[$action], true) || $closed) {
                throw ValidationException::withMessages(['status' => __('salesshipment.v.step', ['no' => $row->return_no, 'status' => $closed ? __('salesshipment.scd.state.CLOSED') : __('salesshipment.sct.state.'.$row->state)])]);
            }
            $this->book()->assertOpen($row->return_date, 'status');
            $by = $this->caller()->auditUserId();
            $action === 'confirm'
                ? $row->update(['state' => 'CONFIRMED', 'confirmed_at' => now(), 'confirmed_by' => $by])
                : $row->update(['state' => 'CANCELLED', 'cancelled_at' => now(), 'cancelled_by' => $by]);

            return $row;
        });

        return ApiResponse::ok($this->presentReturn($row->refresh()));
    }

    public function returnDestroy(string $no): JsonResponse
    {
        $this->allow('salesshipment.subcon.manage');
        $row = $this->findReturn($no);
        if ($row->state !== 'DRAFT') {
            throw ValidationException::withMessages(['status' => __('salesshipment.v.delete_draft', ['no' => $row->return_no])]);
        }
        $row->delete();

        return ApiResponse::ok(['deleted' => $no]);
    }

    /* ── Reconciliations ───────────────────────────────────────────── */

    public function recons(): JsonResponse
    {
        $this->allow('salesshipment.subcon.view');
        $b = $this->book();
        $rows = SsScReconciliation::query()->orderByDesc('recon_date')->orderByDesc('recon_no')->get();
        $deliveries = DB::table('ss_sc_deliveries')->whereIn('id', $rows->pluck('delivery_id'))->get(['id', 'chalan_no', 'customer_id'])->keyBy('id');
        $lines = DB::table('ss_sc_reconciliation_lines')->whereIn('reconciliation_id', $rows->pluck('id'))->get()->groupBy('reconciliation_id');
        $names = $b->customerNames();

        return ApiResponse::ok($rows->map(function (SsScReconciliation $r) use ($deliveries, $lines, $names, $b) {
            $mine = $lines->get($r->id, collect());
            $sum = fn (string $k) => (float) $mine->sum($k);

            return [
                'recon_no' => $r->recon_no, 'recon_date' => $r->recon_date->toDateString(), 'chalan_no' => $deliveries[$r->delivery_id]->chalan_no ?? '—',
                'party' => $names[$deliveries[$r->delivery_id]->customer_id ?? ''] ?? '—', 'customer_id' => $deliveries[$r->delivery_id]->customer_id ?? null,
                'delivered' => $sum('delivered'), 'received' => $sum('received'), 'rejected' => $sum('rejected'), 'returned' => $sum('returned'), 'short' => $sum('short_qty'),
                'excess' => (float) $mine->sum(fn ($l) => max(0.0, -((float) $l->balance))), 'balance' => (float) $mine->sum(fn ($l) => max(0.0, (float) $l->balance)),
                'reconciled_by' => $b->trail($r->reconciled_by, $r->reconciled_at), 'state' => $r->state,
            ];
        })->values()->all());
    }

    public function recon(string $no): JsonResponse
    {
        $this->allow('salesshipment.subcon.view');

        return ApiResponse::ok($this->presentRecon($this->findRecon($no)));
    }

    public function reconSource(string $chalan): JsonResponse
    {
        $this->allow('salesshipment.subcon.view');

        return ApiResponse::ok($this->reconSourceOf(SsScDelivery::query()->where('chalan_no', $chalan)->firstOrFail()));
    }

    /** Saves the short agreed per line; the figures are read off the chalan and the state worked out. */
    public function saveRecon(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('salesshipment.subcon.manage');
        $row = $no ? $this->findRecon($no) : null;
        if ($row && $row->state === 'RECONCILED') {
            throw ValidationException::withMessages(['recon_no' => __('salesshipment.v.scc_locked', ['no' => $row->recon_no])]);
        }
        $data = $request->validate([
            'chalan_no' => ['required', 'string', 'max:24'], 'recon_date' => ['required', 'date'], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['nullable', 'array'], 'lines.*.delivery_line_id' => ['required', 'string', 'max:26', 'distinct'], 'lines.*.short_qty' => ['nullable', 'numeric', 'min:0'],
        ], ['lines.*.delivery_line_id.distinct' => __('salesshipment.v.line_twice')]);
        $d = SsScDelivery::query()->where('chalan_no', $data['chalan_no'])->firstOrFail();
        if ($row && $row->delivery_id !== $d->id) {
            throw ValidationException::withMessages(['chalan_no' => __('salesshipment.v.scc_chalan_fixed', ['no' => $row->recon_no])]);
        }
        if ($d->state !== 'DELIVERED' || $d->closed) {
            throw ValidationException::withMessages(['chalan_no' => __('salesshipment.v.scr_not_out', ['no' => $d->chalan_no])]);
        }
        $open = SsScReconciliation::query()->where('delivery_id', $d->id)->where('state', '!=', 'RECONCILED')->when($row, fn ($q) => $q->where('id', '!=', $row->id))->value('recon_no');
        if ($open) {
            throw ValidationException::withMessages(['chalan_no' => __('salesshipment.v.scc_open', ['no' => $d->chalan_no, 'recon' => $open])]);
        }
        $this->book()->assertOpen($data['recon_date'], 'recon_date');
        $short = collect($data['lines'] ?? [])->mapWithKeys(fn ($l) => [$l['delivery_line_id'] => (float) ($l['short_qty'] ?? 0)]);
        $src = collect($this->reconSourceOf($d)['lines'])->keyBy('delivery_line_id');
        foreach ($short as $id => $q) {
            if (! isset($src[$id])) {
                throw ValidationException::withMessages(['lines' => __('salesshipment.v.scr_line_unknown', ['no' => $d->chalan_no])]);
            }
            if ($q > max(0.0, $src[$id]['out']) + 0.00005) {
                throw ValidationException::withMessages(['lines' => __('salesshipment.v.scc_short_over', ['line' => $src[$id]['label'], 'max' => $this->num(max(0.0, $src[$id]['out']))])]);
            }
        }
        $row = DB::transaction(function () use ($row, $d, $data, $short, $src) {
            $head = ['delivery_id' => $d->id, 'recon_date' => $data['recon_date'], 'remarks' => $data['remarks'] ?? null];
            if ($row) {
                $row->update($head);
                SsScReconciliationLine::query()->where('reconciliation_id', $row->id)->delete();
            } else {
                $row = SsScReconciliation::query()->create($head + ['recon_no' => SsSettings::nextNo('prefix_sc_reconciliation', 'ss_sc_reconciliations', 'recon_no', $data['recon_date'], $this->caller()->companyId),
                    'state' => 'PENDING', 'created_by' => $this->caller()->auditUserId()]);
            }
            $this->writeLines($row, $src, $short);

            return $row;
        });

        return ApiResponse::ok($this->presentRecon($row->refresh()), [], $no ? 200 : 201);
    }

    /** Reconcile signs the chalan off and closes it; Reopen (administrator) un-closes it. */
    public function reconAct(string $no, string $action): JsonResponse
    {
        abort_unless(in_array($action, ['reconcile', 'reopen'], true), 404);
        $action === 'reopen' ? $this->allow('salesshipment.subcon.reopen') : $this->allow('salesshipment.subcon.manage');
        $row = DB::transaction(function () use ($no, $action) {
            $row = SsScReconciliation::query()->where('recon_no', $no)->lockForUpdate()->firstOrFail();
            $d = SsScDelivery::query()->lockForUpdate()->findOrFail($row->delivery_id);
            $this->book()->assertOpen($row->recon_date, 'status');
            $by = $this->caller()->auditUserId();
            if ($action === 'reconcile') {
                if (! in_array($row->state, ['MATCHED', 'SHORT', 'EXCESS'], true)) {
                    throw ValidationException::withMessages(['status' => __('salesshipment.v.scc_not_squared', ['no' => $row->recon_no])]);
                }
                // Re-read the chalan: anything booked since the save must still square.
                $short = SsScReconciliationLine::query()->where('reconciliation_id', $row->id)->pluck('short_qty', 'delivery_line_id')->map(fn ($v) => (float) $v);
                $this->writeLines($row, collect($this->reconSourceOf($d)['lines'])->keyBy('delivery_line_id'), $short);
                if ($row->refresh()->state === 'PENDING') {
                    throw ValidationException::withMessages(['status' => __('salesshipment.v.scc_not_squared', ['no' => $row->recon_no])]);
                }
                $row->update(['state' => 'RECONCILED', 'reconciled_at' => now(), 'reconciled_by' => $by]);
                $d->update(['closed' => true]);
            } else {
                if ($row->state !== 'RECONCILED') {
                    throw ValidationException::withMessages(['status' => __('salesshipment.v.step', ['no' => $row->recon_no, 'status' => __('salesshipment.scc.state.'.$row->state)])]);
                }
                $d->update(['closed' => false]);
                $short = SsScReconciliationLine::query()->where('reconciliation_id', $row->id)->pluck('short_qty', 'delivery_line_id')->map(fn ($v) => (float) $v);
                $this->writeLines($row, collect($this->reconSourceOf($d)['lines'])->keyBy('delivery_line_id'), $short);
                $row->update(['reopened_at' => now(), 'reopened_by' => $by]);
            }

            return $row;
        });

        return ApiResponse::ok($this->presentRecon($row->refresh()));
    }

    public function reconDestroy(string $no): JsonResponse
    {
        $this->allow('salesshipment.subcon.manage');
        $row = $this->findRecon($no);
        if ($row->state === 'RECONCILED') {
            throw ValidationException::withMessages(['status' => __('salesshipment.v.scc_locked', ['no' => $row->recon_no])]);
        }
        $row->delete();

        return ApiResponse::ok(['deleted' => $no]);
    }

    /* ── Helpers ───────────────────────────────────────────────────── */

    private function book(): SalesBook
    {
        return $this->book ??= new SalesBook($this->caller()->companyId);
    }

    private function findReturn(string $no): SsScReturn
    {
        return SsScReturn::query()->where('return_no', $no)->firstOrFail();
    }

    private function findRecon(string $no): SsScReconciliation
    {
        return SsScReconciliation::query()->where('recon_no', $no)->firstOrFail();
    }

    private function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');
    }

    /** Writes every chalan line with its figures, the short and the balance, and works out the state. */
    private function writeLines(SsScReconciliation $row, $src, $short): void
    {
        SsScReconciliationLine::query()->where('reconciliation_id', $row->id)->delete();
        $balances = [];
        $anyShort = false;
        foreach ($src as $id => $s) {
            $sh = (float) ($short[$id] ?? 0);
            $anyShort = $anyShort || $sh > 0.00005;
            $balances[] = $bal = round($s['out'] - $sh, 4);
            SsScReconciliationLine::query()->create(['reconciliation_id' => $row->id, 'delivery_line_id' => $id, 'delivered' => $s['sent'], 'received' => $s['received'], 'rejected' => $s['rejected'],
                'returned' => $s['returned'], 'short_qty' => $sh, 'balance' => $bal]);
        }
        $state = collect($balances)->contains(fn ($b) => $b > 0.00005) ? 'PENDING' : ($anyShort ? 'SHORT' : (collect($balances)->contains(fn ($b) => $b < -0.00005) ? 'EXCESS' : 'MATCHED'));
        $row->update(['state' => $state]);
    }

    /** @return array<string, mixed> */
    private function returnSourceOf(SsScReceipt $r, ?string $exceptReturn): array
    {
        $lines = DB::table('ss_sc_receipt_lines')->where('receipt_id', $r->id)->orderBy('sort_order')->get(['id', 'delivery_line_id', 'qty', 'rejected_qty']);
        $back = DB::table('ss_sc_return_lines as tl')->join('ss_sc_returns as t', 't.id', '=', 'tl.return_id')->whereIn('tl.receipt_line_id', $lines->pluck('id'))->where('t.state', '!=', 'CANCELLED')
            ->when($exceptReturn, fn ($q) => $q->where('t.id', '!=', $exceptReturn))->groupBy('tl.receipt_line_id')->selectRaw('tl.receipt_line_id, sum(tl.qty) as q')->pluck('q', 'receipt_line_id');
        $plines = DB::table('ss_sc_delivery_lines')->whereIn('id', $lines->pluck('delivery_line_id'))->pluck('programme_line_id', 'id');
        $labels = SubconBook::labels($this->book(), $plines->values()->all());
        $d = DB::table('ss_sc_deliveries')->where('id', $r->delivery_id)->first(['chalan_no', 'customer_id']);

        return [
            'mrr_no' => $r->mrr_no, 'chalan_no' => $d->chalan_no, 'party' => $this->book()->customerNames()[$d->customer_id] ?? '—',
            'lines' => $lines->map(fn ($l) => ['receipt_line_id' => $l->id, 'label' => $labels[$plines[$l->delivery_line_id]] ?? '—', 'received' => (float) $l->qty, 'rejected' => (float) $l->rejected_qty,
                'prev' => (float) ($back[$l->id] ?? 0), 'returnable' => max(0.0, round($l->qty + $l->rejected_qty - (float) ($back[$l->id] ?? 0), 4))])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function reconSourceOf(SsScDelivery $d): array
    {
        $lines = SsScDeliveryLine::query()->where('delivery_id', $d->id)->orderBy('sort_order')->get();
        $fig = SubconBook::lines($lines->pluck('id')->all());
        $labels = SubconBook::labels($this->book(), $lines->pluck('programme_line_id')->all());

        return [
            'chalan_no' => $d->chalan_no, 'party' => $this->book()->customerNames()[$d->customer_id] ?? '—',
            'lines' => $lines->map(fn ($l) => ['delivery_line_id' => $l->id, 'label' => $labels[$l->programme_line_id] ?? '—', ...$fig[$l->id]])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentReturn(SsScReturn $t): array
    {
        $b = $this->book();
        $r = SsScReceipt::query()->findOrFail($t->receipt_id);
        $src = $this->returnSourceOf($r, $t->id);
        $by = collect($src['lines'])->keyBy('receipt_line_id');
        $lines = SsScReturnLine::query()->where('return_id', $t->id)->get();

        return [
            'return_no' => $t->return_no, 'mrr_no' => $r->mrr_no, 'chalan_no' => $src['chalan_no'], 'party' => $src['party'], 'return_date' => $t->return_date->toDateString(), 'reason' => $t->reason,
            'remarks' => $t->remarks, 'state' => $t->state, 'confirmed' => $b->trail($t->confirmed_by, $t->confirmed_at), 'company' => $b->company(),
            'lines' => $lines->map(fn ($l) => ['receipt_line_id' => $l->receipt_line_id, 'label' => $by[$l->receipt_line_id]['label'] ?? '—', 'qty' => (float) $l->qty, 'remarks' => $l->remarks])->values()->all(),
            'source' => $src,
        ];
    }

    /** @return array<string, mixed> */
    private function presentRecon(SsScReconciliation $r): array
    {
        $b = $this->book();
        $d = SsScDelivery::query()->findOrFail($r->delivery_id);
        $labels = collect($this->reconSourceOf($d)['lines'])->pluck('label', 'delivery_line_id');
        $lines = SsScReconciliationLine::query()->where('reconciliation_id', $r->id)->get();

        return [
            'recon_no' => $r->recon_no, 'chalan_no' => $d->chalan_no, 'party' => $b->customerNames()[$d->customer_id] ?? '—', 'recon_date' => $r->recon_date->toDateString(), 'remarks' => $r->remarks,
            'state' => $r->state, 'reconciled' => $b->trail($r->reconciled_by, $r->reconciled_at), 'company' => $b->company(),
            'lines' => $lines->map(fn ($l) => ['delivery_line_id' => $l->delivery_line_id, 'label' => $labels[$l->delivery_line_id] ?? '—', 'sent' => (float) $l->delivered, 'received' => (float) $l->received,
                'rejected' => (float) $l->rejected, 'returned' => (float) $l->returned, 'short_qty' => (float) $l->short_qty, 'balance' => (float) $l->balance])->values()->all(),
        ];
    }
}
