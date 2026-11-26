<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Support\Reports;

use App\Modules\Knitting\Models\KnitSamplePlan;
use App\Modules\Knitting\Models\KnitYdDyedReceipt;
use App\Modules\Knitting\Models\KnitYdDyedReceiptLine;
use App\Modules\Knitting\Models\YarnIssue;
use App\Modules\Knitting\Models\YarnIssueLine;
use App\Modules\Knitting\Models\YarnIssueReq;
use App\Modules\Knitting\Models\YarnIssueReqLine;
use App\Modules\Knitting\Models\YarnIssueReturn;
use App\Modules\Knitting\Models\YarnIssueReturnLine;
use App\Modules\Knitting\Models\YarnLot;
use App\Modules\Knitting\Models\YarnMove;
use App\Modules\Knitting\Models\YarnPartyChallan;
use App\Modules\Knitting\Models\YarnPartyChallanLine;
use App\Modules\Knitting\Models\YarnPeriodLine;
use App\Modules\Knitting\Models\YarnPurchaseOrder;
use App\Modules\Knitting\Models\YarnPurchaseOrderLine;
use App\Modules\Knitting\Models\YarnRack;
use App\Modules\Knitting\Models\YarnReceipt;
use App\Modules\Knitting\Models\YarnReceiptLine;
use App\Modules\Knitting\Models\YarnRequisition;
use App\Modules\Knitting\Models\YarnRequisitionLine;
use App\Modules\Knitting\Models\YarnReturn;
use App\Modules\Knitting\Models\YarnReturnLine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The yarn store's reports: lots on hand (physical, what open indents hold,
 * what is free, its value and age), receipts, issues and their excess,
 * returns both ways, purchase requisitions and orders, and dyed yarn in.
 */
trait YarnReports
{
    /** @var Collection<int, array<string, mixed>>|null */
    private ?Collection $lotCache = null;

    /**
     * Every lot's standing now: physical (its ledger balance), allocated
     * (what open indents still want of it), free, value and age. As Brickland
     * says, a stock report ignores the dates — a balance is as at now rather
     * than over a period. Yarn Item narrows to one composition and count.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function lotRows(): Collection
    {
        return $this->lotCache ??= (function (): Collection {
            $q = $this->q;
            [$comp, $count] = $q->yarnItem();
            $asOf = CarbonImmutable::now('Asia/Dhaka')->toDateString();
            $lots = YarnLot::query()->when($comp, fn ($w) => $w->where('composition_id', $comp))->when($count, fn ($w) => $w->where('yarn_count_id', $count))->get();
            $balances = YarnMove::query()->whereIn('lot_id', $lots->pluck('id'))->whereDate('move_date', '<=', $asOf)->groupBy('lot_id')->selectRaw('lot_id, sum(qty) as q')->pluck('q', 'lot_id');
            $open = YarnIssueReq::query()->whereIn('status', YarnIssueReq::ISSUABLE)->select('id');
            $alloc = YarnIssueReqLine::query()->whereIn('req_id', $open)->get()->groupBy(fn ($l) => $l->composition_id.'|'.$l->yarn_count_id.'|'.$l->lot_no)
                ->map(fn ($g) => (float) $g->sum(fn ($l) => max(0, (float) $l->req_qty - (float) $l->issued_qty)));
            $taken = [];

            return $lots->map(function (YarnLot $l) use ($q, $balances, $alloc, $asOf, &$taken): array {
                $physical = round((float) ($balances[$l->id] ?? 0), 3);
                // An indent's want is held against the lots carrying its lot number, the first ones first.
                $key = $l->composition_id.'|'.$l->yarn_count_id.'|'.$l->lot_no;
                $held = round(min(max(0, $physical), max(0, (float) ($alloc[$key] ?? 0) - ($taken[$key] ?? 0))), 3);
                $taken[$key] = ($taken[$key] ?? 0) + $held;

                return ['store' => $q->stores()[$l->store_id] ?? null, 'yarn' => $q->yarn($l->composition_id, $l->yarn_count_id), 'colour' => $l->colour, 'brand' => $l->brand, 'lot_no' => $l->lot_no,
                    'supplier' => $q->parties()[$l->supplier_id] ?? null, 'buyer' => $q->buyers()[$l->buyer_id] ?? null, 'order_no' => $q->orderNo($l->order_id), 'source' => $l->source, 'uom' => $l->uom,
                    'first_date' => $l->first_date?->toDateString(), 'age' => $l->first_date ? (int) $l->first_date->diffInDays(CarbonImmutable::parse($asOf)) : null,
                    'physical' => $physical, 'allocated' => $held, 'free' => round($physical - $held, 3), 'rate' => round((float) $l->rate, 2), 'value' => round($physical * (float) $l->rate, 2),
                    'lot_id' => $l->id, 'order_id' => $l->order_id, 'is_buffer' => (bool) $l->is_buffer, 'composition_id' => $l->composition_id, 'yarn_count_id' => $l->yarn_count_id];
            })->filter(fn ($r) => $r['physical'] != 0.0)->sortBy([['store', 'asc'], ['yarn', 'asc'], ['lot_no', 'asc']])->values();
        })();
    }

    private const LOT_COLS = ['store', 'yarn', 'colour', 'brand', 'lot_no', 'uom', 'physical:number', 'allocated:number', 'free:number'];

    private const LOT_STATS = ['physical' => 'physical', 'free' => 'free'];

    private const LOT_VALUE_STATS = ['physical' => 'physical', 'free' => 'free', 'value' => 'value'];

    private function lotTable(Collection $rows, bool $value = false, array $lead = []): array
    {
        return $this->table([...$lead, ...self::LOT_COLS, ...($value ? ['rate:money', 'value:money'] : [])], $rows, $value ? self::LOT_VALUE_STATS : self::LOT_STATS);
    }

    /** Lots not tied to an order: loose yarn. */
    private function lotWiseLooseYarnStatement(): array
    {
        return $this->lotTable($this->lotRows()->whereNull('order_id')->values());
    }

    private function lotWiseYarnStatementReport(): array
    {
        return $this->lotTable($this->lotRows(), true, ['supplier']);
    }

    private function lotAndCountWiseLooseYarnStock(): array
    {
        $rows = $this->group($this->lotRows()->whereNull('order_id'), ['yarn', 'lot_no', 'uom'], ['physical', 'allocated', 'free']);

        return $this->table(['yarn', 'lot_no', 'uom', 'entries:int', 'physical:number', 'allocated:number', 'free:number'], $rows->sortBy('yarn')->values(), self::LOT_STATS);
    }

    /** Yarn bought ahead of any order (buffer lots). */
    private function advanceYarnReport(): array
    {
        return $this->lotTable($this->lotRows()->where('is_buffer', true)->values(), false, ['supplier', 'first_date:date']);
    }

    private function physicalYarnInventoryReport(): array
    {
        return $this->lotTable($this->lotRows()->whereNotIn('source', ['YARN_DYED', 'INHOUSE_DYED'])->values(), false, ['source:status']);
    }

    private function physicalDyedYarnInventoryReport(): array
    {
        return $this->lotTable($this->lotRows()->whereIn('source', ['YARN_DYED', 'INHOUSE_DYED'])->values(), false, ['source:status']);
    }

    private function buyerWiseYarnInHandStatus(): array
    {
        $rows = $this->group($this->lotRows(), ['buyer', 'order_no', 'yarn'], ['physical', 'allocated', 'free', 'value'])->map(fn ($r) => $r + ['value' => round($r['value'], 2)]);

        return $this->table(['buyer', 'order_no', 'yarn', 'entries:int', 'physical:number', 'allocated:number', 'free:number', 'value:money'], $rows->sortBy('buyer')->values(), self::LOT_VALUE_STATS);
    }

    private function yarnStockWithValue(): array
    {
        return $this->lotTable($this->lotRows(), true, ['supplier']);
    }

    private function yarnStockWithSummaryValue(): array
    {
        $rows = $this->group($this->lotRows(), ['store', 'yarn', 'uom'], ['physical', 'allocated', 'free', 'value'])->map(fn ($r) => $r + ['value' => round($r['value'], 2)]);

        return $this->table(['store', 'yarn', 'uom', 'entries:int', 'physical:number', 'allocated:number', 'free:number', 'value:money'], $rows, self::LOT_VALUE_STATS);
    }

    private function yarnClosingStockSummary(): array
    {
        return $this->yarnStockWithSummaryValue();
    }

    /** Lots on hand by how long they have sat since they first came in. */
    private function yarnInventoryAging(): array
    {
        $rows = $this->lotRows()->map(fn ($r) => $r + ['bucket' => match (true) {
            $r['age'] === null => null, $r['age'] <= 30 => '0-30', $r['age'] <= 60 => '31-60', $r['age'] <= 90 => '61-90', $r['age'] <= 180 => '91-180', default => '180+',
        }, 'stock' => $r['physical']])->sortByDesc('age')->values();

        return $this->table(['store', 'yarn', 'colour', 'brand', 'lot_no', 'first_date:date', 'age:int', 'bucket', 'stock:number', 'rate:money', 'value:money'], $rows, ['stock' => 'stock', 'value' => 'value']);
    }

    /** The book against the count: this period's finalized stock-count adjustments per lot. */
    private function stockTakingRows(): Collection
    {
        $period = CarbonImmutable::now('Asia/Dhaka')->format('Y-m');
        $adj = YarnPeriodLine::query()->where('period', $period)->get()->groupBy('lot_id')->map(fn ($g) => (float) $g->sum('adjustment'));

        return $this->lotRows()->map(fn ($r) => $r + ['adjustment' => round((float) ($adj[$r['lot_id']] ?? 0), 3), 'counted' => round($r['physical'] + (float) ($adj[$r['lot_id']] ?? 0), 3)]);
    }

    private function yarnStockTakingReport(): array
    {
        return $this->table([...self::LOT_COLS, 'adjustment:number', 'counted:number'], $this->stockTakingRows(), self::LOT_STATS);
    }

    private function buyerWiseYarnStockTakingReport(): array
    {
        return $this->table(['buyer', 'order_no', ...self::LOT_COLS, 'adjustment:number', 'counted:number'], $this->stockTakingRows()->sortBy('buyer')->values(), self::LOT_STATS);
    }

    /** What sits in racks per lot, and what of the lot is still to be racked. */
    private function rackWiseStockReport(): array
    {
        $racked = YarnRack::query()->get()->groupBy('lot_id');
        $rows = $this->lotRows()->map(function ($r) use ($racked): array {
            $mine = $racked[$r['lot_id']] ?? collect();
            $in = round((float) $mine->sum('qty'), 3);

            return ['racks' => $mine->pluck('rack_no')->unique()->implode(', ') ?: null] + $r + ['racked' => $in, 'pending' => round(max(0, $r['physical'] - $in), 3)];
        });

        return $this->table(['racks', 'store', 'yarn', 'colour', 'brand', 'lot_no', 'uom', 'physical:number', 'racked:number', 'pending:number'], $rows, ['racked' => 'racked', 'rack_pending' => 'pending']);
    }

    /** Lots by colour with what open indents hold and what went to the floor in the window. */
    private function colorWiseYarnLotAlocationAndLotWiseKnittingProduction(): array
    {
        $issued = $this->q->window(YarnMove::query()->where('kind', 'ISSUE'), 'move_date')->groupBy('lot_id')->selectRaw('lot_id, sum(qty) as q')->pluck('q', 'lot_id');
        $rows = $this->lotRows()->map(fn ($r) => $r + ['issued' => round(abs((float) ($issued[$r['lot_id']] ?? 0)), 3)])->sortBy([['colour', 'asc'], ['lot_no', 'asc']])->values();

        return $this->table(['colour', 'yarn', 'brand', 'lot_no', 'store', 'uom', 'physical:number', 'allocated:number', 'free:number', 'issued:number'], $rows, self::LOT_STATS);
    }

    /** Yarn sent out to sub-contract knitters on party challans, still with them. */
    private function looseYarnStatementScOutHouse(): array
    {
        $q = $this->q;
        $challans = YarnPartyChallan::query()->whereIn('status', ['ISSUED', 'ACCEPTED'])->when($q->to(), fn ($w, $d) => $w->whereDate('challan_date', '<=', $d))->get()->keyBy('id');
        $lots = YarnLot::query()->get()->keyBy('id');
        $rows = YarnPartyChallanLine::query()->whereIn('challan_id', $challans->keys())->get()->groupBy(fn ($l) => $challans[$l->challan_id]->party_id.'|'.$l->lot_id)
            ->map(function (Collection $g) use ($q, $challans, $lots): array {
                $l = $lots[$g->first()->lot_id] ?? null;
                $qty = round((float) $g->sum('qty'), 3);

                return ['party' => $q->parties()[$challans[$g->first()->challan_id]->party_id] ?? null, 'yarn' => $l ? $q->yarn($l->composition_id, $l->yarn_count_id) : null, 'colour' => $l?->colour,
                    'brand' => $l?->brand, 'lot_no' => $l?->lot_no, 'challans' => $g->pluck('challan_id')->unique()->count(), 'physical' => $qty, 'allocated' => 0.0, 'free' => $qty];
            })->sortBy('party')->values();

        return $this->table(['party', 'yarn', 'colour', 'brand', 'lot_no', 'challans:int', 'physical:number', 'allocated:number', 'free:number'], $rows, self::LOT_STATS);
    }

    // ── Movements ─────────────────────────────────────────────────────────

    private function yarnReceiveStatement(): array
    {
        $q = $this->q;
        $receipts = $q->window(YarnReceipt::query(), 'mrr_date')->where('status', '!=', 'CANCELLED')->get()->keyBy('id');
        $pos = YarnPurchaseOrder::query()->whereIn('id', $receipts->pluck('po_id')->filter())->pluck('po_no', 'id');
        $rows = YarnReceiptLine::query()->whereIn('receipt_id', $receipts->keys())->orderBy('sort_order')->get()->map(function (YarnReceiptLine $l) use ($q, $receipts, $pos): array {
            $r = $receipts[$l->receipt_id];

            return ['mrr_date' => $r->mrr_date->toDateString(), 'mrr_no' => $r->mrr_no, 'store' => $q->stores()[$r->store_id] ?? null, 'supplier' => $q->parties()[$r->supplier_id] ?? null,
                'po_no' => $pos[$r->po_id] ?? null, 'challan_no' => $r->challan_no, 'buyer' => $q->buyers()[$r->buyer_id] ?? null, 'order_no' => $q->orderNo($r->order_id),
                'yarn' => $q->yarn($l->composition_id, $l->yarn_count_id), 'colour' => $l->colour, 'brand' => $l->brand, 'lot_no' => $l->lot_no, 'received' => self::r3($l->recv_qty),
                'rate' => round((float) $l->rate, 2), 'value' => round((float) $l->recv_qty * (float) $l->rate, 2), 'status' => $r->status];
        })->sortBy([['mrr_date', 'asc'], ['mrr_no', 'asc']])->values();

        return $this->table(['mrr_date:date', 'mrr_no', 'store', 'supplier', 'po_no', 'challan_no', 'buyer', 'order_no', 'yarn', 'colour', 'brand', 'lot_no', 'received:number', 'rate:money', 'value:money', 'status:status'],
            $rows, ['received' => 'received', 'value' => 'value']);
    }

    /**
     * Issue lines in the window, each with its indent's want and the excess
     * it took past that want (what an indent line got beyond its quantity,
     * counted on the issue that crossed it). Unwindowed, every issue up to
     * the To Date.
     */
    private function issueRows(bool $windowed = true): Collection
    {
        $q = $this->q;
        $all = YarnIssueLine::query()->join('knit_yarn_issues', 'knit_yarn_issues.id', '=', 'knit_yarn_issue_lines.issue_id')
            ->orderBy('knit_yarn_issues.issue_date')->orderBy('knit_yarn_issues.issue_no')->orderBy('knit_yarn_issue_lines.sort_order')->get(['knit_yarn_issue_lines.*', 'knit_yarn_issues.issue_date as on']);
        $issues = YarnIssue::query()->whereIn('id', $all->pluck('issue_id')->unique())->get()->keyBy('id');
        $reqLines = YarnIssueReqLine::query()->whereIn('id', $all->pluck('req_line_id')->filter())->get()->keyBy('id');
        $reqs = YarnIssueReq::query()->whereIn('id', $reqLines->pluck('req_id')->unique())->get()->keyBy('id');
        $lots = YarnLot::query()->whereIn('id', $all->pluck('lot_id'))->get()->keyBy('id');
        $cum = [];
        $rows = collect();
        foreach ($all as $l) {
            $rl = $reqLines[$l->req_line_id] ?? null;
            $want = (float) ($rl?->req_qty ?? 0);
            $before = $cum[$l->req_line_id] ?? 0.0;
            $cum[$l->req_line_id] = $before + (float) $l->qty;
            $excess = $rl ? max(0, $cum[$l->req_line_id] - $want) - max(0, $before - $want) : 0.0;
            $i = $issues[$l->issue_id];
            if ($windowed ? ! $q->inWindow($i->issue_date->toDateString()) : $i->issue_date->toDateString() > $q->asOf()) {
                continue;
            }
            $req = $rl ? ($reqs[$rl->req_id] ?? null) : null;
            $lot = $lots[$l->lot_id] ?? null;
            $rows->push(['issue_date' => $i->issue_date->toDateString(), 'issue_no' => $i->issue_no, 'req_no' => $req?->req_no, 'req_type' => $req?->req_type, 'buyer' => $q->buyers()[$req?->buyer_id] ?? null,
                'order_no' => $q->orderNo($req?->order_id), 'program_no' => $req?->program_no, 'party' => $q->parties()[$req?->party_id] ?? null,
                'yarn' => $lot ? $q->yarn($lot->composition_id, $lot->yarn_count_id) : null, 'colour' => $lot?->colour, 'brand' => $lot?->brand, 'lot_no' => $lot?->lot_no,
                'req_qty' => self::r3($want), 'issued' => self::r3($l->qty), 'excess' => round($excess, 3), 'rate' => round((float) ($lot?->rate ?? 0), 2),
                'value' => round((float) $l->qty * (float) ($lot?->rate ?? 0), 2), 'order_id' => $req?->order_id, 'lot_id' => $l->lot_id]);
        }

        return $rows;
    }

    private function yarnIssueStatement(): array
    {
        return $this->table(['issue_date:date', 'issue_no', 'req_no', 'req_type:status', 'buyer', 'order_no', 'program_no', 'yarn', 'colour', 'brand', 'lot_no', 'issued:number', 'excess:number'],
            $this->issueRows(), ['issued' => 'issued', 'excess' => 'excess']);
    }

    private function orderWiseYarnIssueRegister(): array
    {
        $rows = $this->group($this->issueRows(), ['buyer', 'order_no', 'yarn', 'lot_no'], ['issued', 'excess']);

        return $this->table(['buyer', 'order_no', 'yarn', 'lot_no', 'entries:int', 'issued:number', 'excess:number'], $rows->sortBy([['buyer', 'asc'], ['order_no', 'asc']])->values(), ['issued' => 'issued', 'excess' => 'excess']);
    }

    private function orderWiseYarnIssueRegisterWithValue(): array
    {
        $rows = $this->group($this->issueRows(), ['buyer', 'order_no', 'yarn', 'lot_no'], ['issued', 'value'])->map(fn ($r) => $r + ['value' => round($r['value'], 2)]);

        return $this->table(['buyer', 'order_no', 'yarn', 'lot_no', 'entries:int', 'issued:number', 'value:money'], $rows->sortBy([['buyer', 'asc'], ['order_no', 'asc']])->values(), ['issued' => 'issued', 'issue_value' => 'value']);
    }

    /** By department — the indent's type (bulk, sample, development, yarn dyeing). */
    private function departmentWiseExcessYarnSummery(): array
    {
        $rows = $this->group($this->issueRows(), ['req_type'], ['req_qty', 'issued', 'excess'])->map(fn ($r) => $r + ['excess_pct' => self::pct($r['excess'], $r['issued'])]);

        return $this->table(['req_type:status', 'entries:int', 'req_qty:number', 'issued:number', 'excess:number', 'excess_pct:pct'], $rows, ['issued' => 'issued', 'excess' => 'excess']);
    }

    /** Issues that went past their indent. */
    private function excessYarnStatement(): array
    {
        return $this->table(['issue_date:date', 'issue_no', 'req_no', 'req_type:status', 'buyer', 'order_no', 'yarn', 'lot_no', 'req_qty:number', 'issued:number', 'excess:number'],
            $this->issueRows()->where('excess', '>', 0)->values(), ['issued' => 'issued', 'excess' => 'excess']);
    }

    /** Per indent: wanted, issued, taken back, used (issued less taken back), and what was used past the want. */
    private function excessYarnUsageStatement(): array
    {
        $back = YarnIssueReturnLine::query()->join('knit_yarn_issue_returns', 'knit_yarn_issue_returns.id', '=', 'knit_yarn_issue_return_lines.return_id')
            ->where('knit_yarn_issue_returns.status', 'ACCEPTED')->join('knit_yarn_issue_reqs', 'knit_yarn_issue_reqs.id', '=', 'knit_yarn_issue_returns.req_id')
            ->groupBy('knit_yarn_issue_reqs.req_no')->selectRaw('knit_yarn_issue_reqs.req_no as n, sum(knit_yarn_issue_return_lines.recv_qty) as q')->pluck('q', 'n');
        $want = YarnIssueReqLine::query()->join('knit_yarn_issue_reqs', 'knit_yarn_issue_reqs.id', '=', 'knit_yarn_issue_req_lines.req_id')
            ->groupBy('knit_yarn_issue_reqs.req_no')->selectRaw('knit_yarn_issue_reqs.req_no as n, sum(knit_yarn_issue_req_lines.req_qty) as q')->pluck('q', 'n');
        $rows = $this->group($this->issueRows(), ['req_no', 'req_type', 'buyer', 'order_no', 'program_no'], ['issued'], function (array $r) use ($back, $want): array {
            $asked = (float) ($want[$r['req_no']] ?? 0);
            $used = $r['issued'] - (float) ($back[$r['req_no']] ?? 0);

            return $r + ['requested' => round($asked, 3), 'taken_back' => round((float) ($back[$r['req_no']] ?? 0), 3), 'used' => round($used, 3), 'excess' => round(max(0, $used - $asked), 3)];
        });

        return $this->table(['req_no', 'req_type:status', 'buyer', 'order_no', 'program_no', 'requested:number', 'issued:number', 'taken_back:number', 'used:number', 'excess:number'], $rows, ['issued' => 'issued', 'excess' => 'excess']);
    }

    /** Excess yarn allowed on an indent before it counts against the target (Brickland's process-loss line: 3%). */
    private const EXCESS_TARGET_PCT = 3.0;

    /** Per order: wanted, issued, the excess, its share, and whether it went past the target. */
    private function excessYarnTargetReport(): array
    {
        $rows = $this->group($this->issueRows(), ['buyer', 'order_no'], ['req_qty', 'issued', 'excess'], fn (array $r) => $r + [
            'excess_pct' => self::pct($r['excess'], $r['req_qty']), 'target_pct' => self::EXCESS_TARGET_PCT,
            'over_target' => (self::pct($r['excess'], $r['req_qty']) ?? 0) > self::EXCESS_TARGET_PCT,
        ]);

        return $this->table(['buyer', 'order_no', 'entries:int', 'req_qty:number', 'issued:number', 'excess:number', 'excess_pct:pct', 'target_pct:pct', 'over_target:yesno'], $rows, ['issued' => 'issued', 'excess' => 'excess']);
    }

    private function partyWiseYarnIssueStatement(): array
    {
        $q = $this->q;
        $challans = $q->window(YarnPartyChallan::query(), 'challan_date')->whereIn('status', ['ISSUED', 'ACCEPTED'])->get()->keyBy('id');
        $lots = YarnLot::query()->get()->keyBy('id');
        $rows = YarnPartyChallanLine::query()->whereIn('challan_id', $challans->keys())->orderBy('sort_order')->get()->map(function (YarnPartyChallanLine $l) use ($q, $challans, $lots): array {
            $c = $challans[$l->challan_id];
            $lot = $lots[$l->lot_id] ?? null;

            return ['party' => $q->parties()[$c->party_id] ?? null, 'challan_date' => $c->challan_date->toDateString(), 'challan_no' => $c->challan_no, 'yarn' => $lot ? $q->yarn($lot->composition_id, $lot->yarn_count_id) : null,
                'colour' => $lot?->colour, 'lot_no' => $lot?->lot_no, 'cones' => (int) $l->cones, 'qty' => self::r3($l->qty), 'rate' => round((float) $l->rate, 2), 'value' => round((float) $l->qty * (float) $l->rate, 2), 'status' => $c->status];
        })->sortBy([['party', 'asc'], ['challan_date', 'asc']])->values();

        return $this->table(['party', 'challan_date:date', 'challan_no', 'yarn', 'colour', 'lot_no', 'cones:int', 'qty:number', 'rate:money', 'value:money', 'status:status'], $rows, ['issued_to_party' => 'qty', 'value' => 'value']);
    }

    private function yarnIssueReturnStatement(): array
    {
        $q = $this->q;
        $returns = $q->window(YarnIssueReturn::query(), 'return_date')->where('status', '!=', 'CANCELLED')->get()->keyBy('id');
        $reqs = YarnIssueReq::query()->whereIn('id', $returns->pluck('req_id')->filter())->pluck('req_no', 'id');
        $rows = YarnIssueReturnLine::query()->whereIn('return_id', $returns->keys())->orderBy('sort_order')->get()->map(function (YarnIssueReturnLine $l) use ($q, $returns, $reqs): array {
            $r = $returns[$l->return_id];
            $taken = $r->status === 'ACCEPTED' ? (float) $l->recv_qty : 0.0;

            return ['return_date' => $r->return_date->toDateString(), 'return_no' => $r->return_no, 'req_type' => $r->req_type, 'req_no' => $reqs[$r->req_id] ?? null, 'store' => $q->stores()[$r->store_id] ?? null,
                'yarn' => $q->yarn($l->composition_id, $l->yarn_count_id), 'colour' => $l->colour, 'brand' => $l->brand, 'lot_no' => $l->lot_no, 'returned' => self::r3($l->ret_qty),
                'taken_back' => round($taken, 3), 'short' => round(max(0, (float) $l->ret_qty - $taken), 3), 'status' => $r->status];
        })->values();

        return $this->table(['return_date:date', 'return_no', 'req_type:status', 'req_no', 'store', 'yarn', 'colour', 'brand', 'lot_no', 'returned:number', 'taken_back:number', 'short:number', 'status:status'],
            $rows, ['taken_back' => 'taken_back', 'short' => 'short']);
    }

    private function deliveryChallanForYarnReturnToSupplier(): array
    {
        $q = $this->q;
        $returns = $q->window(YarnReturn::query(), 'return_date')->get()->keyBy('id');
        $lines = YarnReturnLine::query()->whereIn('return_id', $returns->keys())->get();
        $recv = YarnReceiptLine::query()->whereIn('id', $lines->pluck('receipt_line_id'))->get()->keyBy('id');
        $mrr = YarnReceipt::query()->whereIn('id', $recv->pluck('receipt_id')->unique())->pluck('mrr_no', 'id');
        $rows = $lines->map(function (YarnReturnLine $l) use ($q, $returns, $recv, $mrr): array {
            $r = $returns[$l->return_id];
            $rl = $recv[$l->receipt_line_id] ?? null;

            return ['return_date' => $r->return_date->toDateString(), 'return_no' => $r->return_no, 'supplier' => $q->parties()[$r->supplier_id] ?? null, 'mrr_no' => $rl ? ($mrr[$rl->receipt_id] ?? null) : null,
                'yarn' => $rl ? $q->yarn($rl->composition_id, $rl->yarn_count_id) : null, 'lot_no' => $rl?->lot_no, 'returned' => self::r3($l->qty), 'rate' => round((float) ($rl?->rate ?? 0), 2),
                'value' => round((float) $l->qty * (float) ($rl?->rate ?? 0), 2), 'reason' => $r->reason, 'finalized' => (bool) $r->finalized];
        })->sortBy('return_date')->values();

        return $this->table(['return_date:date', 'return_no', 'supplier', 'mrr_no', 'yarn', 'lot_no', 'returned:number', 'rate:money', 'value:money', 'reason', 'finalized:yesno'], $rows, ['returned' => 'returned', 'value' => 'value']);
    }

    /** Day by day in the window: what came in, went out, and the store's closing kilograms and value. */
    private function dailyAndMonthlyYarnOperationStockAndValueReport(): array
    {
        $q = $this->q;
        $moves = YarnMove::query()->when($q->to(), fn ($w, $d) => $w->whereDate('move_date', '<=', $d))->get();
        $rates = YarnLot::query()->pluck('rate', 'id');
        $running = (float) $moves->filter(fn ($m) => $q->from() && $m->move_date->toDateString() < $q->from())->sum('qty');
        $value = (float) $moves->filter(fn ($m) => $q->from() && $m->move_date->toDateString() < $q->from())->sum(fn ($m) => (float) $m->qty * (float) ($rates[$m->lot_id] ?? 0));
        $rows = [];
        foreach ($moves->filter(fn ($m) => $q->inWindow($m->move_date->toDateString()))->groupBy(fn ($m) => $m->move_date->toDateString())->sortKeys() as $day => $g) {
            $in = (float) $g->where('qty', '>', 0)->sum('qty');
            $out = abs((float) $g->where('qty', '<', 0)->sum('qty'));
            $opening = $running;
            $running += $in - $out;
            $value += (float) $g->sum(fn ($m) => (float) $m->qty * (float) ($rates[$m->lot_id] ?? 0));
            $rows[] = ['day' => $day, 'month' => substr($day, 0, 7), 'opening' => round($opening, 3), 'received' => round($in, 3), 'issued' => round($out, 3), 'closing' => round($running, 3), 'value' => round($value, 2)];
        }
        $last = end($rows) ?: null;

        return $this->table(['day:date', 'month', 'opening:number', 'received:number', 'issued:number', 'closing:number', 'value:money'], $rows,
            ['received' => 'received', 'issued' => 'issued', 'closing' => [$last['closing'] ?? 0, 'number']]);
    }

    private function dyedYarnReceiveChallanStatement(): array
    {
        $q = $this->q;
        $docs = $q->window(KnitYdDyedReceipt::query(), 'challan_date')->where('status', '!=', 'CANCELLED')->get()->keyBy('id');
        $rows = KnitYdDyedReceiptLine::query()->whereIn('receipt_id', $docs->keys())->orderBy('sort_order')->get()->map(function (KnitYdDyedReceiptLine $l) use ($q, $docs): array {
            $d = $docs[$l->receipt_id];

            return ['challan_date' => $d->challan_date->toDateString(), 'challan_no' => $d->challan_no, 'party' => $q->parties()[$d->party_id] ?? null, 'buyer' => $q->buyers()[$d->buyer_id] ?? null,
                'order_no' => $q->orderNo($d->order_id), 'store' => $q->stores()[$d->store_id] ?? null, 'yarn' => $q->yarn($l->composition_id, $l->yarn_count_id), 'colour' => $l->colour_name,
                'lot_no' => $l->lot_no, 'uom' => $l->uom, 'qty' => self::r3($l->qty), 'status' => $d->status];
        })->values();

        return $this->table(['challan_date:date', 'challan_no', 'party', 'buyer', 'order_no', 'store', 'yarn', 'colour', 'lot_no', 'uom', 'qty:number', 'status:status'], $rows, ['dyed_in' => 'qty']);
    }

    // ── Purchase ──────────────────────────────────────────────────────────

    private function yarnPurchaseOrderStatement(): array
    {
        $q = $this->q;
        [$comp, $count] = $q->yarnItem();
        $pos = $q->window(YarnPurchaseOrder::query(), 'po_date')->where('status', '!=', 'CANCELLED')->get()->keyBy('id');
        $rows = YarnPurchaseOrderLine::query()->whereIn('po_id', $pos->keys())->when($comp, fn ($w) => $w->where('composition_id', $comp))->when($count, fn ($w) => $w->where('yarn_count_id', $count))
            ->orderBy('sort_order')->get()->map(function (YarnPurchaseOrderLine $l) use ($q, $pos): array {
                $p = $pos[$l->po_id];

                return ['po_date' => $p->po_date->toDateString(), 'po_no' => $p->po_no, 'supplier' => $q->parties()[$p->supplier_id] ?? null, 'buyer' => $q->buyers()[$p->buyer_id] ?? null,
                    'order_no' => $q->orderNo($p->order_id), 'yarn' => $q->yarn($l->composition_id, $l->yarn_count_id), 'colour' => $l->colour, 'brand' => $l->brand, 'uom' => $l->uom,
                    'ordered' => self::r3($l->qty), 'received' => self::r3($l->received_qty), 'pending' => round(max(0, (float) $l->qty - (float) $l->received_qty), 3), 'rate' => round((float) $l->rate, 2),
                    'value' => round((float) $l->qty * (float) $l->rate, 2), 'delivery_date' => $p->delivery_date?->toDateString(), 'status' => $p->status];
            })->sortBy([['po_date', 'asc'], ['po_no', 'asc']])->values();

        return $this->table(['po_date:date', 'po_no', 'supplier', 'buyer', 'order_no', 'yarn', 'colour', 'brand', 'uom', 'ordered:number', 'received:number', 'pending:number', 'rate:money', 'value:money', 'delivery_date:date', 'status:status'],
            $rows, ['ordered' => 'ordered', 'pending' => 'pending', 'po_value' => 'value']);
    }

    /** Proforma invoices for yarn belong to the commercial desk, not built yet: an empty statement. */
    private function yarnProformaInvoiceStatement(): array
    {
        return $this->table(['pi_date:date', 'pi_no', 'supplier', 'po_no', 'yarn', 'qty:number', 'rate:money', 'value:money', 'lc_no', 'status:status'], [], ['pi_value' => 'value']);
    }

    /**
     * Purchase requisition lines, with YR Approve? (approved or not) and Date
     * Type (the document's date, the date it was decided, or the date the
     * yarn is wanted) applied.
     */
    private function requisitionRows(): Collection
    {
        $q = $this->q;
        [$comp, $count] = $q->yarnItem();
        $col = match ($q->get('date_type')) { 'APPROVED' => 'decided_at', 'DELIVERY' => 'required_date', default => 'requisition_date' };
        $approved = ['APPROVED', 'ORDERED', 'CLOSED'];
        $reqs = $q->window(YarnRequisition::query(), $col)->where('status', '!=', 'CANCELLED')
            ->when($q->get('yr_approve') === 'YES', fn ($w) => $w->whereIn('status', $approved))->when($q->get('yr_approve') === 'NO', fn ($w) => $w->whereNotIn('status', $approved))->get()->keyBy('id');
        $ordered = YarnPurchaseOrderLine::query()->whereIn('po_id', YarnPurchaseOrder::query()->whereIn('requisition_id', $reqs->keys())->where('status', '!=', 'CANCELLED')->select('id'))
            ->join('knit_yarn_pos', 'knit_yarn_pos.id', '=', 'knit_yarn_po_lines.po_id')->get(['knit_yarn_po_lines.*', 'knit_yarn_pos.requisition_id'])
            ->groupBy(fn ($l) => $l->requisition_id.'|'.$l->composition_id.'|'.$l->yarn_count_id)->map(fn ($g) => (float) $g->sum('qty'));

        return YarnRequisitionLine::query()->whereIn('requisition_id', $reqs->keys())->when($comp, fn ($w) => $w->where('composition_id', $comp))->when($count, fn ($w) => $w->where('yarn_count_id', $count))
            ->orderBy('sort_order')->get()->map(function (YarnRequisitionLine $l) use ($q, $reqs, $ordered): array {
                $r = $reqs[$l->requisition_id];
                $qty = (float) $l->qty_local + (float) $l->qty_import;

                return ['requisition_date' => $r->requisition_date->toDateString(), 'requisition_no' => $r->requisition_no, 'store' => $q->stores()[$r->store_id] ?? null, 'buyer' => $q->buyers()[$r->buyer_id] ?? null,
                    'order_no' => $q->orderNo($r->order_id), 'yarn' => $q->yarn($l->composition_id, $l->yarn_count_id), 'colour' => $l->colour, 'brand' => $l->brand, 'uom' => $l->uom,
                    'qty_local' => self::r3($l->qty_local), 'qty_import' => self::r3($l->qty_import), 'requisitioned' => round($qty, 3),
                    'ordered' => round((float) ($ordered[$r->id.'|'.$l->composition_id.'|'.$l->yarn_count_id] ?? 0), 3), 'required_date' => $r->required_date?->toDateString(),
                    'decided_at' => $r->decided_at?->toDateString(), 'status' => $r->status, 'order_id' => $r->order_id];
            })->sortBy([['requisition_date', 'asc'], ['requisition_no', 'asc']])->values();
    }

    private function yarnPurchaseRequisitionStatement(): array
    {
        return $this->table(['requisition_date:date', 'requisition_no', 'store', 'buyer', 'order_no', 'yarn', 'colour', 'brand', 'uom', 'qty_local:number', 'qty_import:number', 'requisitioned:number',
            'required_date:date', 'decided_at:date', 'status:status'], $this->requisitionRows(), ['requisitioned' => 'requisitioned']);
    }

    private function yarnPurchaseRequisitionSummery(): array
    {
        $rows = $this->group($this->requisitionRows(), ['requisition_date', 'requisition_no', 'store', 'buyer', 'order_no', 'required_date', 'status'], ['requisitioned', 'ordered']);

        return $this->table(['requisition_date:date', 'requisition_no', 'store', 'buyer', 'order_no', 'entries:int', 'requisitioned:number', 'ordered:number', 'required_date:date', 'status:status'], $rows, ['requisitioned' => 'requisitioned']);
    }

    /**
     * Yarn indents for knitting by type: wanted, issued and pending per line.
     * Sample indents are told apart by the sample knit plan that raised them —
     * a Y/D plan's, or (any other) solid.
     *
     * @param  list<string>  $types
     */
    private function issueReqRows(array $types, ?string $sampleKind = null): Collection
    {
        $q = $this->q;
        [$comp, $count] = $q->yarnItem();
        $reqs = $q->window(YarnIssueReq::query(), 'req_date')->whereIn('req_type', $types)->where('status', '!=', 'CANCELLED')->get()->keyBy('id');
        if ($sampleKind !== null) {
            $yd = KnitSamplePlan::query()->where('kind', 'YD')->whereNotNull('issue_req_id')->pluck('issue_req_id')->all();
            $reqs = $reqs->filter(fn ($r) => $sampleKind === 'YD' ? in_array($r->id, $yd, true) : ! in_array($r->id, $yd, true));
        }

        return YarnIssueReqLine::query()->whereIn('req_id', $reqs->keys())->when($comp, fn ($w) => $w->where('composition_id', $comp))->when($count, fn ($w) => $w->where('yarn_count_id', $count))
            ->orderBy('sort_order')->get()->map(function (YarnIssueReqLine $l) use ($q, $reqs): array {
                $r = $reqs[$l->req_id];

                return ['req_date' => $r->req_date->toDateString(), 'req_no' => $r->req_no, 'req_type' => $r->req_type, 'store' => $q->stores()[$r->store_id] ?? null, 'buyer' => $q->buyers()[$r->buyer_id] ?? null,
                    'order_no' => $q->orderNo($r->order_id), 'program_no' => $r->program_no, 'yarn' => $q->yarn($l->composition_id, $l->yarn_count_id), 'colour' => $l->colour, 'brand' => $l->brand,
                    'lot_no' => $l->lot_no, 'uom' => $l->uom, 'requested' => self::r3($l->req_qty), 'issued' => self::r3($l->issued_qty),
                    'pending' => round(max(0, (float) $l->req_qty - (float) $l->issued_qty), 3), 'status' => $r->status];
            })->sortBy([['req_date', 'asc'], ['req_no', 'asc']])->values();
    }

    private function issueReqTable(Collection $rows): array
    {
        return $this->table(['req_date:date', 'req_no', 'req_type:status', 'store', 'buyer', 'order_no', 'program_no', 'yarn', 'colour', 'brand', 'lot_no', 'uom', 'requested:number', 'issued:number', 'pending:number', 'status:status'],
            $rows, ['requested' => 'requested', 'issued' => 'issued', 'pending' => 'pending']);
    }

    private function yarnRequisitionStatementForKnittingSampleYdByDate(): array
    {
        return $this->issueReqTable($this->issueReqRows(['KNIT_SAMPLE', 'KNIT_DEVELOPMENT'], 'YD'));
    }

    private function yarnRequisitionStatementForKnittingSampleSolidByDate(): array
    {
        return $this->issueReqTable($this->issueReqRows(['KNIT_SAMPLE', 'KNIT_DEVELOPMENT'], 'SOLID'));
    }

    private function yarnRequsitionStatmentForKnittingBulkByDate(): array
    {
        return $this->issueReqTable($this->issueReqRows(['KNIT_BULK']));
    }

    /** Per order: yarn asked for on purchase requisitions, received into the store, and issued to the floor. */
    private function orderWiseRequisitionReceiveAndIssueReport(): array
    {
        $q = $this->q;
        $req = $this->requisitionRows()->groupBy('order_id')->map(fn ($g) => (float) $g->sum('requisitioned'));
        $recv = YarnReceiptLine::query()->join('knit_yarn_receipts', 'knit_yarn_receipts.id', '=', 'knit_yarn_receipt_lines.receipt_id')->where('knit_yarn_receipts.status', '!=', 'CANCELLED')
            ->when($q->from(), fn ($w, $d) => $w->whereDate('knit_yarn_receipts.mrr_date', '>=', $d))->when($q->to(), fn ($w, $d) => $w->whereDate('knit_yarn_receipts.mrr_date', '<=', $d))
            ->groupBy('knit_yarn_receipts.order_id')->selectRaw('knit_yarn_receipts.order_id as o, sum(knit_yarn_receipt_lines.recv_qty) as q')->pluck('q', 'o');
        $issued = $this->issueRows()->groupBy('order_id')->map(fn ($g) => (float) $g->sum('issued'));
        $rows = $req->keys()->merge($recv->keys())->merge($issued->keys())->filter()->unique()->map(function ($id) use ($q, $req, $recv, $issued): array {
            $o = $q->order($id);

            return ['buyer' => $q->buyers()[$o?->buyer_id] ?? null, 'order_no' => $o?->order_no, 'style' => $o ? ($o->work_style_no ?: $o->style_no) : null,
                'requisitioned' => round((float) ($req[$id] ?? 0), 3), 'received' => round((float) ($recv[$id] ?? 0), 3), 'issued' => round((float) ($issued[$id] ?? 0), 3),
                'balance' => round((float) ($recv[$id] ?? 0) - (float) ($issued[$id] ?? 0), 3)];
        })->sortBy('order_no')->values();

        return $this->table(['buyer', 'order_no', 'style', 'requisitioned:number', 'received:number', 'issued:number', 'balance:number'], $rows, ['requisitioned' => 'requisitioned', 'received' => 'received', 'issued' => 'issued']);
    }

    // ── Yarn Stock Position (its own screen) ──────────────────────────────

    /**
     * Yarn on hand per lot on the To Date — physical, what open indents hold
     * (Alloc), what is left free (Stock) and what was issued past an indent's
     * want (Adv. Issue) — with the screen's boxes applied: Count, Composition,
     * Spin/Cotton Type, Slub?, Mellange?, Solid?, Y/D?, S/C-IN?, Brand, Lot,
     * Store, Shade, Buyer, Job#/W.Style, the purchase order and Category
     * (the lot's source). Spin Type, Cotton Type, Slub and Mellange are read
     * from the composition's words, the only place the yarn masters keep them.
     */
    public function yarnPosition(): array
    {
        $q = $this->q;
        $compositions = $q->yarnNames()['compositions'];
        $words = fn (array $r) => strtolower((string) ($compositions[$r['composition_id']] ?? ''));
        $has = fn (array $r, string $w) => str_contains($words($r), strtolower($w));
        $dyed = ['YARN_DYED', 'INHOUSE_DYED'];
        $poOf = YarnReceiptLine::query()->join('knit_yarn_receipts', 'knit_yarn_receipts.id', '=', 'knit_yarn_receipt_lines.receipt_id')->whereNotNull('knit_yarn_receipts.po_id')
            ->whereNotNull('knit_yarn_receipt_lines.lot_id')->pluck('knit_yarn_receipts.po_id', 'knit_yarn_receipt_lines.lot_id');
        $poNos = YarnPurchaseOrder::query()->pluck('po_no', 'id');
        $excess = $this->issueRows(false)->groupBy('lot_id')->map(fn ($g) => (float) $g->sum('excess'));
        $rows = $this->lotRows()->filter(fn (array $r) => (! $q->get('count') || $r['yarn_count_id'] === $q->get('count'))
            && (! $q->get('composition') || $r['composition_id'] === $q->get('composition'))
            && (! $q->get('spin') || $has($r, $q->get('spin'))) && (! $q->get('cotton') || $has($r, $q->get('cotton')))
            && (! $q->get('slub') || $has($r, 'slub')) && (! $q->get('mellange') || $has($r, 'melange') || $has($r, 'mellange'))
            && (! $q->get('solid') || ! in_array($r['source'], $dyed, true)) && (! $q->get('yd') || in_array($r['source'], $dyed, true))
            && (! $q->get('sc_in') || $r['source'] === 'SC_PARTY') && (! $q->get('brand') || stripos((string) $r['brand'], $q->get('brand')) !== false)
            && (! $q->get('lot') || stripos((string) $r['lot_no'], $q->get('lot')) !== false) && (! $q->get('store') || ($q->stores()[$q->get('store')] ?? null) === $r['store'])
            && (! $q->get('shade') || strcasecmp((string) $r['colour'], $q->get('shade')) === 0) && (! $q->get('buyer') || ($q->buyers()[$q->get('buyer')] ?? null) === $r['buyer'])
            && (! $q->get('order') || $r['order_id'] === $q->get('order')) && (! $q->get('po') || ($poOf[$r['lot_id']] ?? null) === $q->get('po'))
            && (! $q->get('category') || $r['source'] === $q->get('category')))
            ->map(fn (array $r) => $r + ['count' => $q->yarnNames()['counts'][$r['yarn_count_id']] ?? null, 'composition' => $compositions[$r['composition_id']] ?? null,
                'po_no' => $poNos[$poOf[$r['lot_id']] ?? ''] ?? null, 'adv_issue' => round((float) ($excess[$r['lot_id']] ?? 0), 3)])->values();

        return $this->table(['store', 'count', 'composition', 'colour', 'brand', 'lot_no', 'supplier', 'buyer', 'order_no', 'po_no', 'source:status', 'uom', 'first_date:date', 'age:int',
            'physical:number', 'allocated:number', 'free:number', 'adv_issue:number', 'rate:money', 'value:money'], $rows, ['physical' => 'physical', 'stock' => 'free', 'adv_issue' => 'adv_issue']);
    }
}
