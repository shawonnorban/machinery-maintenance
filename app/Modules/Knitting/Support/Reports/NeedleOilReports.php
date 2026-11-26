<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Support\Reports;

use App\Modules\Knitting\Models\KnitCapacityPlan;
use App\Modules\Knitting\Models\KnitMachine;
use App\Modules\Knitting\Models\KnitNeedleBroken;
use App\Modules\Knitting\Models\KnitNeedleBrokenLine;
use App\Modules\Knitting\Models\KnitNeedleIssue;
use App\Modules\Knitting\Models\KnitNeedleIssueLine;
use App\Modules\Knitting\Models\KnitNeedleLoanOpening;
use App\Modules\Knitting\Models\KnitNeedleLoanReceipt;
use App\Modules\Knitting\Models\KnitNeedleLoanReceiptLine;
use App\Modules\Knitting\Models\KnitNeedleLoanReq;
use App\Modules\Knitting\Models\KnitNeedleLoanReqLine;
use App\Modules\Knitting\Models\KnitNeedleLoanReturn;
use App\Modules\Knitting\Models\KnitNeedleLoanReturnLine;
use App\Modules\Knitting\Models\KnitNeedleMove;
use App\Modules\Knitting\Models\KnitNeedleReq;
use App\Modules\Knitting\Models\KnitNeedleReqLine;
use App\Modules\Knitting\Models\KnitOilConsumption;
use App\Modules\Knitting\Models\KnitOilConsumptionLine;
use App\Modules\Knitting\Models\KnitOperatorPosting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Needles and oil: breakage by machine and shift, indents by reason, the
 * stores' stock-taking sheets, oil burnt per machine against running hours
 * and kilograms knitted, the loan ledgers with parties, and each machine's
 * efficiency, downtime and running cost.
 */
trait NeedleOilReports
{
    /** Confirmed breakage in the window, one row per line. */
    private function brokenRows(): Collection
    {
        $q = $this->q;
        $docs = $q->window(KnitNeedleBroken::query(), 'broken_date')->where('status', '!=', 'CANCELLED')->get()->keyBy('id');

        return KnitNeedleBrokenLine::query()->whereIn('broken_id', $docs->keys())->orderBy('sort_order')->get()
            ->map(function (KnitNeedleBrokenLine $l) use ($q, $docs): array {
                $d = $docs[$l->broken_id];

                return ['broken_date' => $d->broken_date->toDateString(), 'ref_no' => $d->ref_no, 'shift' => $q->shifts()[$d->shift_id] ?? null, 'machine' => $q->machines()[$l->machine_id] ?? null,
                    'item' => $q->items()[$l->item_id] ?? null, 'uom' => $l->uom, 'broken' => self::r3($l->qty), 'rate' => round((float) $l->cost_price, 2), 'value' => round((float) $l->amount, 2),
                    'remarks' => $l->remarks, 'machine_id' => $l->machine_id];
            })->sortBy([['broken_date', 'asc'], ['machine', 'asc']])->values();
    }

    private function mcAndDateWiseNeedleBroken(): array
    {
        return $this->table(['broken_date:date', 'ref_no', 'machine', 'shift', 'item', 'uom', 'broken:number', 'rate:money', 'value:money', 'remarks'], $this->brokenRows(), ['broken' => 'broken', 'value' => 'value']);
    }

    private function dailyMcAndShiftWiseNeedleStatement(): array
    {
        $rows = $this->group($this->brokenRows(), ['broken_date', 'shift', 'machine'], ['broken', 'value'])
            ->map(fn (array $r) => $r + ['value' => round($r['value'], 2)]);

        return $this->table(['broken_date:date', 'shift', 'machine', 'entries:int', 'broken:number', 'value:money'], $rows, ['broken' => 'broken', 'value' => 'value']);
    }

    /** Machine-wise needle indents in the window by their reason: asked, issued, still to issue. */
    private function needleReasonTypeWiseSummary(): array
    {
        $reqs = $this->q->window(KnitNeedleReq::query(), 'req_date')->where('status', '!=', 'CANCELLED')->get()->keyBy('id');
        $lines = KnitNeedleReqLine::query()->whereIn('req_id', $reqs->keys())->get();
        $issued = KnitNeedleIssueLine::query()->whereIn('issue_id', KnitNeedleIssue::query()->where('status', '!=', 'CANCELLED')->select('id'))
            ->whereIn('req_line_id', $lines->pluck('id'))->groupBy('req_line_id')->selectRaw('req_line_id, sum(qty) as q')->pluck('q', 'req_line_id');
        $rows = $lines->groupBy(fn ($l) => $reqs[$l->req_id]->reason)->map(function (Collection $g, string $reason) use ($issued): array {
            $asked = round((float) $g->sum('req_qty'), 3);
            $out = round((float) $g->sum(fn ($l) => (float) ($issued[$l->id] ?? 0)), 3);

            return ['reason' => $reason, 'indents' => $g->pluck('req_id')->unique()->count(), 'lines' => $g->count(), 'requested' => $asked, 'issued' => $out, 'pending' => round(max(0, $asked - $out), 3)];
        })->sortBy('reason')->values();

        return $this->table(['reason:status', 'indents:int', 'lines:int', 'requested:number', 'issued:number', 'pending:number'], $rows, ['requested' => 'requested', 'issued' => 'issued', 'pending' => 'pending']);
    }

    private function needleStockTaking(): array
    {
        return $this->stockTaking('NEEDLE');
    }

    private function oilStockTaking(): array
    {
        return $this->stockTaking('OIL');
    }

    /**
     * A store's book stock per item, struck from the ledger: brought forward
     * to the From Date, then what came in and went out in the window, and the
     * book stock valued at its average rate.
     */
    private function stockTaking(string $book): array
    {
        $q = $this->q;
        $moves = KnitNeedleMove::query()->where('book', $book)->when($q->to(), fn ($w, $d) => $w->whereDate('move_date', '<=', $d))->get();
        $categories = DB::table('gs_items')->leftJoin('item_categories as gs_categories', 'gs_categories.id', '=', 'gs_items.gs_category_id')->where('gs_items.company_id', $q->company())->pluck('gs_categories.name', 'gs_items.id');
        $rows = $moves->groupBy(fn ($m) => $m->store_id.'|'.$m->item_id)->map(function (Collection $g) use ($q, $categories): array {
            $first = $g->first();
            $before = $g->filter(fn ($m) => $q->from() && $m->move_date->toDateString() < $q->from());
            $in = $g->diff($before);
            // Outflows sit in the ledger as negatives; the sheet shows each column as a quantity.
            $sum = fn (array $kinds) => round(abs((float) $in->whereIn('kind', $kinds)->sum('qty')), 3);
            $stock = round((float) $g->sum('qty'), 3);
            $value = (float) $g->sum('value');
            $rate = $stock != 0.0 && $value != 0.0 ? round($value / $stock, 2) : 0.0;

            return ['store' => $q->gsStores()[$first->store_id] ?? null, 'category' => $categories[$first->item_id] ?? null, 'item' => $q->items()[$first->item_id] ?? null,
                'opening' => round((float) $before->sum('qty') + (float) $in->where('kind', 'OPENING')->sum('qty'), 3), 'received' => $sum(['RECEIPT']), 'loan_in' => $sum(['LOAN_IN', 'LOAN_BACK']),
                'transfer_in' => $sum(['ISSUE_IN']), 'rcv_return' => $sum(['RETURN']), 'issued' => $sum(['ISSUE_OUT']), 'consumed' => $sum(['CONSUME']), 'loan_out' => $sum(['LOAN_OUT']),
                'book_stock' => $stock, 'rate' => $rate, 'stock_value' => round($stock * $rate, 2)];
        })->filter(fn ($r) => $r['book_stock'] != 0.0 || $r['received'] + $r['issued'] + $r['consumed'] > 0)->sortBy([['store', 'asc'], ['item', 'asc']])->values();

        return $this->table(['store', 'category', 'item', 'opening:number', 'received:number', 'loan_in:number', 'transfer_in:number', 'rcv_return:number', 'issued:number', 'consumed:number', 'loan_out:number',
            'book_stock:number', 'rate:money', 'stock_value:money'], $rows, ['book_stock' => 'book_stock', 'stock_value' => 'stock_value']);
    }

    // ── Oil burnt ─────────────────────────────────────────────────────────

    /** Finalized consumption sheets in the window, one row per machine line, with the operator posted on it that shift. */
    private function oilRows(): Collection
    {
        $q = $this->q;
        $sheets = $q->window(KnitOilConsumption::query(), 'cons_date')->where('status', 'FINALIZED')->get()->keyBy('id');
        $lines = KnitOilConsumptionLine::query()->whereIn('consumption_id', $sheets->keys())->orderBy('sort_order')->get();
        $postings = KnitOperatorPosting::query()->whereIn('machine_id', $lines->pluck('machine_id')->filter())->get()->groupBy('machine_id');

        return $lines->map(function (KnitOilConsumptionLine $l) use ($q, $sheets, $postings): array {
            $s = $sheets[$l->consumption_id];
            $day = $s->cons_date->toDateString();
            $post = ($postings[$l->machine_id] ?? collect())->first(fn ($p) => (! $p->shift_id || $p->shift_id === $s->shift_id)
                && $p->effective_at->toDateString() <= $day && (! $p->until_at || $p->until_at->toDateString() >= $day));

            return ['cons_date' => $day, 'cons_no' => $s->cons_no, 'shift' => $q->shifts()[$s->shift_id] ?? null, 'machine' => $q->machines()[$l->machine_id] ?? null,
                'operator' => $post ? ($q->users()[$post->user_id] ?? null) : null, 'item' => $q->items()[$l->item_id] ?? null, 'uom' => $l->uom, 'consumed' => self::r3($l->qty),
                'running_hrs' => self::r3($l->running_hrs), 'cost' => round((float) $l->qty * (float) $l->cost_price, 2), 'machine_id' => $l->machine_id, 'shift_id' => $s->shift_id];
        })->values();
    }

    /** Oil grouped, with the kilograms the machine knitted in the same window and shift: cost per running hour and per kg. */
    private function oilBy(array $keys): Collection
    {
        $made = $this->rolls()->where('qc_status', '!=', 'REJECTED');

        return $this->group($this->oilRows(), [...$keys, 'machine_id'], ['consumed', 'running_hrs', 'cost'], function (array $r, Collection $g) use ($made, $keys): array {
            $mine = $made->where('machine_id', $r['machine_id']);
            if (in_array('shift', $keys, true)) {
                $mine = $mine->where('shift_id', $g->first()['shift_id']);
            }
            if (in_array('cons_date', $keys, true)) {
                $mine = $mine->where('prod_date', $r['cons_date']);
            }
            $kg = round((float) $mine->sum('net'), 3);
            unset($r['machine_id']);

            return $r + ['knitted' => $kg, 'cost' => round($r['cost'], 2), 'cost_per_hr' => $r['running_hrs'] > 0 ? round($r['cost'] / $r['running_hrs'], 2) : null,
                'cost_per_kg' => $kg > 0 ? round($r['cost'] / $kg, 4) : null];
        });
    }

    private const OIL_SUMS = ['entries:int', 'consumed:number', 'cost:money', 'running_hrs:number', 'knitted:number', 'cost_per_hr:money', 'cost_per_kg:money'];

    private const OIL_STATS = ['consumed' => 'consumed', 'cost' => 'cost', 'running_hrs' => 'running_hrs'];

    private function mcAndOperatorWiseOilConsumptionUnitCost(): array
    {
        return $this->table(['machine', 'operator', ...self::OIL_SUMS], $this->oilBy(['machine', 'operator'])->sortBy('machine')->values(), self::OIL_STATS);
    }

    private function mcAndShiftWiseOilConsumptionUnitCost(): array
    {
        return $this->table(['machine', 'shift', ...self::OIL_SUMS], $this->oilBy(['machine', 'shift'])->sortBy('machine')->values(), self::OIL_STATS);
    }

    private function dateWiseMcOilConsumption(): array
    {
        return $this->table(['cons_date:date', 'cons_no', 'shift', 'machine', 'item', 'uom', 'consumed:number', 'cost:money', 'running_hrs:number'], $this->oilRows(), self::OIL_STATS);
    }

    // ── Loans with parties ────────────────────────────────────────────────

    /**
     * Every loan movement with a party, one row per item: openings, what we
     * borrowed (loan receipts) and gave back (loan return issues), what we
     * lent (loan issues) and got back (loan returns received). Receivable is
     * lent less got back; Payable is borrowed less given back.
     */
    private function loanRows(?string $book): Collection
    {
        $q = $this->q;
        $rows = collect();
        $add = function (string $date, string $bk, string $type, ?string $doc, ?string $party, ?string $item, ?string $uom, array $qty) use ($q, $rows): void {
            if (! $q->inWindow($date) && $q->from() !== null) {
                return;
            }
            $r = array_merge(['date' => $date, 'book' => $bk, 'type' => $type, 'doc_no' => $doc, 'party' => $q->parties()[$party] ?? null, 'item' => $q->items()[$item] ?? null, 'uom' => $uom,
                'borrowed' => 0.0, 'gave_back' => 0.0, 'lent' => 0.0, 'got_back' => 0.0, 'party_id' => $party, 'item_id' => $item], $qty);
            $r['receivable'] = round($r['lent'] - $r['got_back'], 3);
            $r['payable'] = round($r['borrowed'] - $r['gave_back'], 3);
            $rows->push($r);
        };
        $books = fn ($w) => $w->when($book, fn ($w) => $w->where('book', $book));
        foreach ($books(KnitNeedleLoanOpening::query())->get() as $o) {
            // An opening line carries no paper number; its Type says what it is.
            $add($o->ob_date->toDateString(), $o->book, 'OPENING', null, $o->party_id, $o->item_id, $o->uom, ['borrowed' => self::r3($o->loan_received), 'lent' => self::r3($o->loan_issued)]);
        }
        $receipts = $books(KnitNeedleLoanReceipt::query())->whereIn('status', KnitNeedleLoanReceipt::POSTED)->get()->keyBy('id');
        foreach (KnitNeedleLoanReceiptLine::query()->whereIn('receipt_id', $receipts->keys())->get() as $l) {
            $r = $receipts[$l->receipt_id];
            $add($r->mrr_date->toDateString(), $r->book, 'LOAN_RECEIPT', $r->mrr_no, $r->party_id, $l->item_id, $l->uom, ['borrowed' => self::r3($l->receive_qty)]);
        }
        $reqs = $books(KnitNeedleLoanReq::query())->whereIn('status', ['PARTLY_ISSUED', 'ISSUED', 'CLOSED'])->get()->keyBy('id');
        foreach (KnitNeedleLoanReqLine::query()->whereIn('req_id', $reqs->keys())->where('issue_qty', '>', 0)->get() as $l) {
            $r = $reqs[$l->req_id];
            $add($r->req_date->toDateString(), $r->book, $r->kind, $r->req_no, $r->party_id, $l->item_id, $l->uom, $r->kind === 'LOAN_ISSUE' ? ['lent' => self::r3($l->issue_qty)] : ['gave_back' => self::r3($l->issue_qty)]);
        }
        $returns = $books(KnitNeedleLoanReturn::query())->whereIn('status', ['RECEIVED', 'FINALIZED'])->get()->keyBy('id');
        foreach (KnitNeedleLoanReturnLine::query()->whereIn('return_id', $returns->keys())->where('received_qty', '>', 0)->get() as $l) {
            $r = $returns[$l->return_id];
            $add(($r->rcv_date ?? $r->ret_challan_date ?? $r->created_at)->toDateString(), $r->book, 'LOAN_BACK', $r->return_no, $r->party_id, $l->item_id, $l->uom, ['got_back' => self::r3($l->received_qty)]);
        }

        return $rows->sortBy([['date', 'asc'], ['doc_no', 'asc']])->values();
    }

    private const LOAN_COLS = ['borrowed:number', 'gave_back:number', 'lent:number', 'got_back:number', 'receivable:number', 'payable:number'];

    private const LOAN_STATS = ['receivable' => 'receivable', 'payable' => 'payable'];

    private function loanDetails(?string $book): array
    {
        return $this->table(['date:date', 'book:status', 'type:status', 'doc_no', 'party', 'item', 'uom', ...self::LOAN_COLS], $this->loanRows($book), self::LOAN_STATS);
    }

    private function loanSummary(?string $book): array
    {
        $rows = $this->group($this->loanRows($book), ['book', 'party'], ['borrowed', 'gave_back', 'lent', 'got_back', 'receivable', 'payable']);

        return $this->table(['book:status', 'party', 'entries:int', ...self::LOAN_COLS], $rows->sortBy('party')->values(), self::LOAN_STATS);
    }

    /** Where each party stands per item: what is still owed either way. */
    private function loanStatement(string $book): array
    {
        $rows = $this->group($this->loanRows($book), ['party', 'item', 'uom'], ['borrowed', 'gave_back', 'lent', 'got_back', 'receivable', 'payable']);

        return $this->table(['party', 'item', 'uom', 'entries:int', ...self::LOAN_COLS], $rows->sortBy([['party', 'asc'], ['item', 'asc']])->values(), self::LOAN_STATS);
    }

    private function knitLoanLedgerDetails(): array
    {
        return $this->loanDetails(null);
    }

    private function knitLoanLedgerSummary(): array
    {
        return $this->loanSummary(null);
    }

    private function knitNeedleLoanStatement(): array
    {
        return $this->loanStatement('NEEDLE');
    }

    private function knitOilLoanLedgerDetails(): array
    {
        return $this->loanDetails('OIL');
    }

    private function knitOilLoanLedgerSummary(): array
    {
        return $this->loanSummary('OIL');
    }

    private function knitOilLoanStatement(): array
    {
        return $this->loanStatement('OIL');
    }

    // ── Machines ──────────────────────────────────────────────────────────

    /**
     * Each machine over the window: what it knitted against its capacity
     * plan, the hours it ran (from the oil sheets) and was down (breakdowns
     * logged in Machinery Maintenance against the asset carrying its serial
     * number or code), and what running it cost in oil and broken needles.
     */
    private function mcWiseEfficiencyDowntimeAndRunningCost(): array
    {
        $q = $this->q;
        $made = $this->rolls()->where('qc_status', '!=', 'REJECTED')->groupBy('machine_id');
        $oil = $this->oilRows()->groupBy('machine_id');
        $needles = $this->brokenRows()->groupBy('machine_id');
        $days = $this->rolls()->pluck('prod_date')->merge($this->oilRows()->pluck('cons_date'))->unique()->values();
        $plans = KnitCapacityPlan::query()->where('active', true)->get()->groupBy('machine_id');
        $machines = KnitMachine::query()->orderBy('machine_no')->get();
        $down = $this->breakdownsFor($machines);
        $rows = $machines->map(function (KnitMachine $m) use ($q, $made, $oil, $needles, $days, $plans, $down): ?array {
            $kg = round((float) ($made[$m->id] ?? collect())->sum('net'), 3);
            $hrs = round((float) ($oil[$m->id] ?? collect())->sum('running_hrs'), 3);
            $planHrs = 0.0;
            $target = 0.0;
            foreach ($days as $day) {
                foreach (($plans[$m->id] ?? collect()) as $p) {
                    if ($p->effective_from->toDateString() <= $day && (! $p->effective_to || $p->effective_to->toDateString() >= $day)) {
                        $planHrs += (float) $p->plan_hours;
                        $target += (float) $p->capacity_per_hour * (float) $p->plan_hours;
                    }
                }
            }
            [$breakdowns, $downHrs] = $down[$m->id] ?? [0, 0.0];
            $oilCost = round((float) ($oil[$m->id] ?? collect())->sum('cost'), 2);
            $needleCost = round((float) ($needles[$m->id] ?? collect())->sum('value'), 2);
            if ($kg <= 0 && $hrs <= 0 && $breakdowns === 0 && $oilCost + $needleCost <= 0) {
                return null;
            }

            return ['machine' => $m->machine_no, 'floor' => $q->floors()[$m->floor_id] ?? null, 'produced' => $kg, 'target' => round($target, 3), 'efficiency' => self::pct($kg, $target),
                'plan_hrs' => round($planHrs, 2), 'running_hrs' => $hrs, 'breakdowns' => $breakdowns, 'downtime_hrs' => round($downHrs, 2), 'oil_cost' => $oilCost, 'needle_cost' => $needleCost,
                'running_cost' => round($oilCost + $needleCost, 2), 'cost_per_kg' => $kg > 0 ? round(($oilCost + $needleCost) / $kg, 4) : null];
        })->filter()->values();

        return $this->table(['machine', 'floor', 'produced:number', 'target:number', 'efficiency:pct', 'plan_hrs:number', 'running_hrs:number', 'breakdowns:int', 'downtime_hrs:number', 'oil_cost:money',
            'needle_cost:money', 'running_cost:money', 'cost_per_kg:money'], $rows, ['produced' => 'produced', 'downtime_hrs' => 'downtime_hrs', 'breakdowns' => 'breakdowns']);
    }

    /** Breakdowns in the window per knit machine — matched to the asset by serial number, else by code — and the hours each kept it down. @return array<string, array{int, float}> */
    private function breakdownsFor(Collection $machines): array
    {
        $q = $this->q;
        $assets = DB::table('assets')->where('company_id', $q->company())->whereNull('deleted_at')->get(['id', 'asset_code', 'serial_number']);
        $out = [];
        foreach ($machines as $m) {
            $asset = $assets->first(fn ($a) => ($m->serial_no && $a->serial_number === $m->serial_no) || $a->asset_code === $m->machine_no);
            if (! $asset) {
                continue;
            }
            $logged = $q->window(DB::table('breakdowns')->where('asset_id', $asset->id)->whereNull('cancelled_at'), 'failure_at')->get(['failure_at', 'production_resumed_at', 'repair_completed_at', 'hold_minutes']);
            $hours = $logged->sum(function ($b): float {
                $end = $b->production_resumed_at ?? $b->repair_completed_at;

                return $end ? max(0, (strtotime($end) - strtotime($b->failure_at)) / 3600) : 0.0;
            });
            $out[$m->id] = [$logged->count(), (float) $hours];
        }

        return $out;
    }
}
