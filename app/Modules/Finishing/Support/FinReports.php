<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Finishing Line → Fabric Finishing Reports (AnnotechERP 18/06): every report
 * Brickland's report screen lists, read from the Finishing Line's own tables
 * at the moment Preview is pressed. A report answers its columns
 * ("key:type"), its rows and the stat tiles above the grid. The boxes it
 * reads: Store, S/C Party, From Date, To Date.
 */
final class FinReports
{
    /** Brickland's reports, in its order (its radio values). */
    public const REPORTS = [
        'sc-program-delivery', 'daily-production', 'machine-shift-production', 'mc-production-vs-target', 'floor-status',
        'sc-party-production-summary', 'sc-delivery-receive-ledger', 'sc-bill-register', 'sc-bill-outstanding', 'hold-list',
        'hold-list-summary', 'four-point-register', 'shade-approval-status', 'delivery-to-store', 'waiting-for-delivery',
        'issue-register', 'monthly-production', 'mc-reprocess-with-reason', 'current-stock', 'stock-details-ledger',
        'process-type-register', 'machine-process-register', 'sc-party-production-register', 'wet-hold-summary', 'production-4a',
        'order-monitoring', 'sc-aop-ledger', 'stock-ledger-sample', 'waiting-for-delivery-sample', 'delivery-to-store-sample',
        'day-night-incentive', 'shift-incentive', 'wet-dry-hold-24hr', 'left-over-stock', 'left-over-utilization',
        'fiber-colour-booking', 'daily-sc-aop-batch',
    ];

    /** Reports whose data lives in a module not built yet. */
    public const PENDING = ['sc-aop-ledger' => 'aop', 'daily-sc-aop-batch' => 'aop', 'fiber-colour-booking' => 'booking'];

    private const WET = ['DEWATERING', 'DRYING', 'DYEING_RUNNING', 'AWAITING_LOAD'];

    /** @var array<string, mixed> */
    private array $cache = [];

    /** @param array<string, ?string> $f */
    public function __construct(private readonly array $f) {}

    /** @return array{columns: list<array<string, string>>, rows: list<array<string, mixed>>, stats: list<array<string, mixed>>} */
    public function run(string $report): array
    {
        if (isset(self::PENDING[$report])) {
            return $this->table(['batch_no', 'order_no', 'colour', 'qty_kg:number'], []);
        }
        $method = lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $report))));

        return $this->{$method}();
    }

    // ── Production ───────────────────────────────────────────────────

    private function dailyProduction(): array
    {
        $rows = $this->lines()->map(fn ($l) => array_intersect_key($l, array_flip(['production_date', 'sheet_no', 'line', 'shift', 'batch_no', 'colour', 'machine', 'process', 'input_kg', 'output_kg', 'loss_kg', 'loss_pct', 'fin_gsm', 'fin_dia'])));

        return $this->table(['production_date:date', 'sheet_no', 'line:status', 'shift', 'batch_no', 'colour', 'machine', 'process', 'input_kg:number', 'output_kg:number', 'loss_kg:number', 'loss_pct:pct', 'fin_gsm:number', 'fin_dia:number'], $rows,
            ['input' => 'input_kg', 'output' => 'output_kg', 'loss' => 'loss_kg']);
    }

    private function machineShiftProduction(): array
    {
        $rows = $this->group($this->lines(), ['machine', 'shift'], ['input_kg', 'output_kg', 'loss_kg'], fn ($r, $g) => $r + ['batches' => $g->pluck('batch_no')->unique()->count(), 'loss_pct' => self::pct($r['loss_kg'], $r['input_kg'])]);

        return $this->table(['machine', 'shift', 'batches:int', 'entries:int', 'input_kg:number', 'output_kg:number', 'loss_kg:number', 'loss_pct:pct'], $rows->sortBy(['machine', 'shift'])->values(),
            ['input' => 'input_kg', 'output' => 'output_kg']);
    }

    private function mcProductionVsTarget(): array
    {
        $lines = $this->lines();
        $targets = DB::table('fin_machine_targets')->where('company_id', $this->company())->where('active', true)->orderBy('effective_from')->get();
        $rows = $this->group($lines, ['machine_id', 'machine'], ['output_kg'], function ($r, $g) use ($targets) {
            $shifts = $g->map(fn ($l) => $l['production_date'].'|'.$l['shift'])->unique()->count();
            $t = $targets->where('machine_id', $r['machine_id'])->filter(fn ($x) => (string) $x->effective_from <= (string) ($this->f['to'] ?? '9999'))->last();
            $target = $t ? round((float) $t->target_qty * $shifts, 3) : null;

            return ['machine' => $r['machine'], 'shifts' => $shifts, 'target_per_shift' => $t ? (float) $t->target_qty : null, 'target_kg' => $target, 'output_kg' => $r['output_kg'],
                'variance_kg' => $target === null ? null : round($r['output_kg'] - $target, 3), 'achievement_pct' => $target ? self::pct($r['output_kg'], $target) : null];
        });

        return $this->table(['machine', 'shifts:int', 'target_per_shift:number', 'target_kg:number', 'output_kg:number', 'variance_kg:number', 'achievement_pct:pct'], $rows->sortBy('machine')->values(),
            ['target' => 'target_kg', 'output' => 'output_kg']);
    }

    private function monthlyProduction(): array
    {
        $rows = $this->group($this->lines()->map(fn ($l) => $l + ['month' => substr($l['production_date'], 0, 7)]), ['month'], ['input_kg', 'output_kg', 'loss_kg'],
            fn ($r, $g) => $r + ['batches' => $g->pluck('batch_no')->unique()->count(), 'loss_pct' => self::pct($r['loss_kg'], $r['input_kg'])]);

        return $this->table(['month', 'batches:int', 'entries:int', 'input_kg:number', 'output_kg:number', 'loss_kg:number', 'loss_pct:pct'], $rows->sortBy('month')->values(), ['input' => 'input_kg', 'output' => 'output_kg', 'loss' => 'loss_kg']);
    }

    /** Production 4A: each batch's output by the process group it went through. */
    private function production4a(): array
    {
        $rows = $this->lines()->groupBy('batch_no')->map(function (Collection $g) {
            $by = fn (array $codes) => round($g->filter(fn ($l) => in_array($l['process_code'], $codes, true))->sum('output_kg'), 3);

            return ['batch_no' => $g->first()['batch_no'], 'buyer' => $g->first()['buyer'], 'order_no' => $g->first()['order_no'], 'colour' => $g->first()['colour'],
                'wet_kg' => $by(['DF-DW']), 'dry_kg' => $by(['DF-DRY', 'DF-HS', 'DF-ST']), 'compact_kg' => $by(['DF-CMP']), 'surface_kg' => $by(['DF-CAL', 'DF-BR', 'DF-PCH']),
                'input_kg' => round($g->sum('input_kg'), 3), 'output_kg' => round($g->sum('output_kg'), 3)];
        })->values();

        return $this->table(['batch_no', 'buyer', 'order_no', 'colour', 'wet_kg:number@process', 'dry_kg:number@process', 'compact_kg:number@process', 'surface_kg:number@process', 'input_kg:number', 'output_kg:number'], $rows,
            ['input' => 'input_kg', 'output' => 'output_kg']);
    }

    private function dayNightIncentive(): array
    {
        $rows = $this->group($this->lines()->map(fn ($l) => $l + ['period' => $l['unload_hour'] === null ? 'DAY' : ($l['unload_hour'] >= 6 && $l['unload_hour'] < 18 ? 'DAY' : 'NIGHT')]),
            ['production_date', 'period', 'machine'], ['output_kg']);

        return $this->table(['production_date:date', 'period:status', 'machine', 'entries:int', 'output_kg:number'], $rows->sortBy(['production_date', 'period', 'machine'])->values(), ['output' => 'output_kg']);
    }

    private function shiftIncentive(): array
    {
        $rows = $this->group($this->lines(), ['shift', 'machine'], ['input_kg', 'output_kg'], fn ($r) => $r + ['loss_pct' => self::pct($r['input_kg'] - $r['output_kg'], $r['input_kg'])]);

        return $this->table(['shift', 'machine', 'entries:int', 'input_kg:number', 'output_kg:number', 'loss_pct:pct'], $rows->sortBy(['shift', 'machine'])->values(), ['output' => 'output_kg']);
    }

    private function mcReprocessWithReason(): array
    {
        $m = $this->names();
        $advices = DB::table('fin_reprocess_advices')->where('company_id', $this->company())->when($this->f['from'] ?? null, fn ($q, $d) => $q->where('advice_date', '>=', $d))->when($this->f['to'] ?? null, fn ($q, $d) => $q->where('advice_date', '<=', $d))->get();
        $returns = DB::table('fin_reprocess_returns')->where('company_id', $this->company())->when($this->f['from'] ?? null, fn ($q, $d) => $q->where('return_date', '>=', $d))->when($this->f['to'] ?? null, fn ($q, $d) => $q->where('return_date', '<=', $d))->get();
        $rows = collect();
        foreach ($advices as $a) {
            $rows->push(['date' => (string) $a->advice_date, 'source' => 'QC_ADVICE', 'doc_no' => $a->advice_no, 'batch_no' => $this->batchOf($a->receipt_id)?->batch_no, 'machine' => $m['machines'][$a->machine_id] ?? null,
                'process' => $m['processes'][$a->process_id] ?? null, 'reason' => $a->reason, 'rolls' => (int) $a->rejected_rolls, 'qty_kg' => self::r3($a->rejected_kg)]);
        }
        foreach ($returns as $r) {
            $rows->push(['date' => (string) $r->return_date, 'source' => 'DYE_RETURN', 'doc_no' => $r->return_no, 'batch_no' => $this->batchOf($r->receipt_id)?->batch_no, 'machine' => null, 'process' => null,
                'reason' => $r->reason, 'rolls' => (int) $r->rolls, 'qty_kg' => self::r3($r->qty_kg)]);
        }

        return $this->table(['date:date', 'source:status', 'doc_no', 'batch_no', 'machine', 'process', 'reason:status', 'rolls:int', 'qty_kg:number'], $rows->sortBy('date')->values(), ['rolls' => 'rolls', 'qty' => 'qty_kg']);
    }

    // ── The floor ────────────────────────────────────────────────────

    private function floorStatus(): array
    {
        $rows = $this->cards()->filter(fn ($c) => $c['stage'] !== 'DELIVERED')->map(fn ($c) => array_intersect_key($c, array_flip(['batch_no', 'receipt_no', 'buyer', 'order_no', 'colour', 'dia_type', 'stage', 'last_process', 'last_machine', 'recv_qty_kg', 'spent_hours'])));

        return $this->table(['batch_no', 'receipt_no', 'buyer', 'order_no', 'colour', 'dia_type:status', 'stage:status', 'last_process', 'last_machine', 'recv_qty_kg:number', 'spent_hours:number'], $rows->values(), ['qty' => 'recv_qty_kg']);
    }

    private function holdList(): array
    {
        $insp = DB::table('fin_inspections')->where('company_id', $this->company())->where('status', 'POSTED')->orderBy('posted_at')->get()->keyBy('receipt_id');
        $rows = $this->cards()->where('stage', 'ON_HOLD')->map(fn ($c) => ['batch_no' => $c['batch_no'], 'receipt_no' => $c['receipt_no'], 'buyer' => $c['buyer'], 'order_no' => $c['order_no'], 'colour' => $c['colour'],
            'held_since' => $c['stage_at'], 'ref_no' => $insp[$c['id']]->ref_no ?? null, 'verdict' => $insp[$c['id']]->verdict ?? null, 'recv_qty_kg' => $c['recv_qty_kg'], 'rolls' => $c['recv_rolls']]);

        return $this->table(['batch_no', 'receipt_no', 'buyer', 'order_no', 'colour', 'held_since:date', 'ref_no', 'verdict:status', 'recv_qty_kg:number', 'rolls:int'], $rows->values(), ['qty' => 'recv_qty_kg']);
    }

    private function holdListSummary(): array
    {
        $rows = $this->group($this->cards()->where('stage', 'ON_HOLD'), ['buyer'], ['recv_qty_kg']);

        return $this->table(['buyer', 'entries:int', 'recv_qty_kg:number'], $rows->sortBy('buyer')->values(), ['qty' => 'recv_qty_kg']);
    }

    private function wetHoldSummary(): array
    {
        $rows = $this->group($this->cards()->filter(fn ($c) => in_array($c['stage'], [...self::WET, 'ON_HOLD'], true)), ['stage'], ['recv_qty_kg']);

        return $this->table(['stage:status', 'entries:int', 'recv_qty_kg:number'], $rows->values(), ['qty' => 'recv_qty_kg']);
    }

    private function wetDryHold24hr(): array
    {
        $rows = $this->cards()->filter(fn ($c) => ($c['stage'] === 'ON_HOLD' || in_array($c['stage'], self::WET, true)) && $c['stage_hours'] !== null && $c['stage_hours'] >= 24)
            ->map(fn ($c) => ['batch_no' => $c['batch_no'], 'buyer' => $c['buyer'], 'order_no' => $c['order_no'], 'colour' => $c['colour'], 'stage' => $c['stage'], 'kind' => $c['stage'] === 'ON_HOLD' ? 'DRY_HOLD' : 'WET',
                'since' => $c['stage_at'], 'hours' => $c['stage_hours'], 'recv_qty_kg' => $c['recv_qty_kg']]);

        return $this->table(['batch_no', 'buyer', 'order_no', 'colour', 'stage:status', 'kind:status', 'since:date', 'hours:number', 'recv_qty_kg:number'], $rows->values(), ['qty' => 'recv_qty_kg']);
    }

    private function orderMonitoring(): array
    {
        $cards = $this->cards();
        $produced = $this->lines()->groupBy('receipt_id')->map(fn ($g) => $g->sum('output_kg'));
        $toStore = DB::table('fin_store_delivery_lines as l')->join('fin_store_deliveries as d', 'd.id', '=', 'l.delivery_id')->where('d.company_id', $this->company())->where('d.status', '!=', 'DRAFT')->groupBy('l.receipt_id')->selectRaw('l.receipt_id, sum(l.finish_qty) as kg')->pluck('kg', 'receipt_id');
        $issued = DB::table('fin_store_moves as m')->join('fin_store_lots as l', 'l.id', '=', 'm.lot_id')->where('l.company_id', $this->company())->where('m.kind', 'ISSUE')->groupBy('l.receipt_id')->selectRaw('l.receipt_id, -sum(m.qty_kg) as kg')->pluck('kg', 'receipt_id');
        $rows = $this->group($cards->map(fn ($c) => $c + ['produced_kg' => self::r3($produced[$c['id']] ?? 0), 'store_kg' => self::r3($toStore[$c['id']] ?? 0), 'issued_kg' => self::r3($issued[$c['id']] ?? 0),
            'passed' => in_array($c['stage'], ['READY_FOR_DELIVERY', 'DELIVERED'], true) ? $c['recv_qty_kg'] : 0]), ['order_no', 'buyer'], ['recv_qty_kg', 'produced_kg', 'passed', 'store_kg', 'issued_kg']);

        return $this->table(['order_no', 'buyer', 'entries:int', 'recv_qty_kg:number', 'produced_kg:number', 'passed:number', 'store_kg:number', 'issued_kg:number'], $rows->sortBy('order_no')->values(),
            ['received' => 'recv_qty_kg', 'store' => 'store_kg', 'issued' => 'issued_kg']);
    }

    // ── Sub-contract ─────────────────────────────────────────────────

    private function scProgramDelivery(): array
    {
        return $this->table(['challan_date:date', 'challan_no', 'supplier', 'gate_pass_no', 'batch_no', 'order_no', 'colour', 'process', 'lot_no', 'qty_kg:number', 'rolls:int', 'rate:money', 'value:money'], $this->scLines(),
            ['qty' => 'qty_kg', 'value' => 'value']);
    }

    private function scDeliveryReceiveLedger(): array
    {
        return $this->table(['challan_date:date', 'challan_no', 'supplier', 'batch_no', 'process', 'qty_kg:number', 'received_kg:number', 'short_kg:number', 'balance_kg:number'], $this->scLines(),
            ['sent' => 'qty_kg', 'received' => 'received_kg', 'balance' => 'balance_kg']);
    }

    private function scPartyProductionSummary(): array
    {
        $rows = $this->group($this->scLines(), ['supplier'], ['qty_kg', 'received_kg', 'short_kg', 'balance_kg'], fn ($r, $g) => $r + ['challans' => $g->pluck('challan_no')->unique()->count()]);

        return $this->table(['supplier', 'challans:int', 'entries:int', 'qty_kg:number', 'received_kg:number', 'short_kg:number', 'balance_kg:number'], $rows->sortBy('supplier')->values(),
            ['sent' => 'qty_kg', 'received' => 'received_kg', 'balance' => 'balance_kg']);
    }

    private function scPartyProductionRegister(): array
    {
        $n = $this->names();
        $rows = DB::table('fin_sc_receipt_lines as l')->join('fin_sc_receipts as r', 'r.id', '=', 'l.receipt_id')->leftJoin('fin_sc_challan_lines as cl', 'cl.id', '=', 'l.challan_line_id')->leftJoin('fin_sc_challans as c', 'c.id', '=', 'r.challan_id')
            ->where('r.company_id', $this->company())->where('r.status', 'POSTED')->when($this->f['party'] ?? null, fn ($q, $p) => $q->where('r.supplier_id', $p))->when($this->f['store'] ?? null, fn ($q, $s) => $q->where('r.store_id', $s))
            ->when($this->f['from'] ?? null, fn ($q, $d) => $q->where('r.receive_date', '>=', $d))->when($this->f['to'] ?? null, fn ($q, $d) => $q->where('r.receive_date', '<=', $d))
            ->orderBy('r.receive_date')->get(['r.receive_date', 'r.mrr_no', 'r.supplier_id', 'c.challan_no', 'cl.receipt_id', 'cl.process_id', 'l.lot_no', 'l.recv_kg', 'l.short_kg', 'l.rolls'])
            ->map(fn ($x) => ['receive_date' => (string) $x->receive_date, 'mrr_no' => $x->mrr_no, 'supplier' => $n['suppliers'][$x->supplier_id] ?? null, 'challan_no' => $x->challan_no, 'batch_no' => $x->receipt_id ? $this->batchOf($x->receipt_id)?->batch_no : null,
                'process' => $n['processes'][$x->process_id] ?? null, 'lot_no' => $x->lot_no, 'recv_kg' => self::r3($x->recv_kg), 'short_kg' => self::r3($x->short_kg), 'rolls' => (int) $x->rolls]);

        return $this->table(['receive_date:date', 'mrr_no', 'supplier', 'challan_no', 'batch_no', 'process', 'lot_no', 'recv_kg:number', 'short_kg:number', 'rolls:int'], $rows, ['received' => 'recv_kg', 'short' => 'short_kg']);
    }

    private function scBillRegister(): array
    {
        $n = $this->names();
        $rows = $this->billLines()->map(fn ($x) => ['bill_date' => (string) $x->bill_date, 'bill_no' => $x->bill_no, 'supplier' => $n['suppliers'][$x->supplier_id] ?? null, 'challan_no' => $x->challan_no,
            'process' => $n['processes'][$x->process_id] ?? null, 'qty_kg' => self::r3($x->qty_kg), 'rate' => round((float) $x->rate, 2), 'amount' => round((float) $x->amount, 2), 'status' => $x->status]);

        return $this->table(['bill_date:date', 'bill_no', 'supplier', 'challan_no', 'process', 'qty_kg:number', 'rate:money', 'amount:money', 'status:status'], $rows, ['qty' => 'qty_kg', 'amount' => 'amount']);
    }

    private function scBillOutstanding(): array
    {
        $n = $this->names();
        $rows = $this->billLines()->groupBy('supplier_id')->map(function (Collection $g, $sid) use ($n) {
            $billed = round($g->sum(fn ($x) => (float) $x->amount), 2);
            $done = round($g->where('status', 'COMPLETED')->sum(fn ($x) => (float) $x->amount), 2);

            return ['supplier' => $n['suppliers'][$sid] ?? null, 'bills' => $g->pluck('bill_no')->unique()->count(), 'billed' => $billed, 'completed' => $done, 'outstanding' => round($billed - $done, 2)];
        })->values();

        return $this->table(['supplier', 'bills:int', 'billed:money', 'completed:money', 'outstanding:money'], $rows->sortBy('supplier')->values(), ['billed' => 'billed', 'outstanding' => 'outstanding']);
    }

    // ── Quality ──────────────────────────────────────────────────────

    private function fourPointRegister(): array
    {
        $rows = DB::table('fin_inspections')->where('company_id', $this->company())->when($this->f['from'] ?? null, fn ($q, $d) => $q->where('insp_date', '>=', $d))->when($this->f['to'] ?? null, fn ($q, $d) => $q->where('insp_date', '<=', $d))
            ->orderBy('insp_date')->get()->map(function ($i) {
                $b = $this->batchOf($i->receipt_id);

                return ['insp_date' => (string) $i->insp_date, 'ref_no' => $i->ref_no, 'batch_no' => $b?->batch_no, 'buyer' => $b?->buyer, 'colour' => $b?->colour, 'shift' => $i->shift, 'roll_count' => (int) $i->roll_count,
                    'total_kg' => self::r3($i->total_kg), 'total_points' => (int) $i->total_points, 'shrinkage_l' => $i->shrinkage_l === null ? null : (float) $i->shrinkage_l, 'shrinkage_w' => $i->shrinkage_w === null ? null : (float) $i->shrinkage_w,
                    'verdict' => $i->verdict, 'status' => $i->status];
            });

        return $this->table(['insp_date:date', 'ref_no', 'batch_no', 'buyer', 'colour', 'shift', 'roll_count:int', 'total_kg:number', 'total_points:int', 'shrinkage_l:number', 'shrinkage_w:number', 'verdict:status', 'status:status'], $rows,
            ['rolls' => 'roll_count', 'qty' => 'total_kg', 'points' => 'total_points']);
    }

    private function shadeApprovalStatus(): array
    {
        $users = DB::table('users')->pluck('name', 'id');
        $rows = DB::table('fin_shade_decisions')->where('company_id', $this->company())->orderByRaw("case when status = 'PENDING' then 0 else 1 end")->orderBy('created_at')->get()->map(function ($d) use ($users) {
            $b = $this->batchOf($d->receipt_id);

            return ['batch_no' => $b?->batch_no, 'buyer' => $b?->buyer, 'order_no' => $b?->order_no, 'colour' => $d->colour, 'batch_qty' => self::r3($d->batch_qty), 'status' => $d->status,
                'decided_by' => $users[$d->decided_by] ?? null, 'decided_at' => $d->decided_at ? substr((string) $d->decided_at, 0, 10) : null];
        });

        return $this->table(['batch_no', 'buyer', 'order_no', 'colour', 'batch_qty:number', 'status:status', 'decided_by', 'decided_at:date'], $rows, ['qty' => 'batch_qty']);
    }

    private function waitingForDelivery(): array
    {
        return $this->waiting('BULK');
    }

    private function waitingForDeliverySample(): array
    {
        return $this->waiting('SAMPLE');
    }

    private function waiting(string $scope): array
    {
        $insp = DB::table('fin_inspections')->where('company_id', $this->company())->where('status', 'POSTED')->where('verdict', 'PASS')->orderBy('posted_at')->get()->keyBy('receipt_id');
        $rows = $this->cards()->where('stage', 'READY_FOR_DELIVERY')->where('scope', $scope)->map(fn ($c) => ['batch_no' => $c['batch_no'], 'buyer' => $c['buyer'], 'order_no' => $c['order_no'], 'colour' => $c['colour'],
            'ready_since' => $c['stage_at'], 'ref_no' => $insp[$c['id']]->ref_no ?? null, 'roll_count' => (int) ($insp[$c['id']]->roll_count ?? 0), 'total_points' => (int) ($insp[$c['id']]->total_points ?? 0), 'recv_qty_kg' => $c['recv_qty_kg']]);

        return $this->table(['batch_no', 'buyer', 'order_no', 'colour', 'ready_since:date', 'ref_no', 'roll_count:int', 'total_points:int', 'recv_qty_kg:number'], $rows->values(), ['qty' => 'recv_qty_kg']);
    }

    // ── The store ────────────────────────────────────────────────────

    private function deliveryToStore(): array
    {
        return $this->toStore('BULK');
    }

    private function deliveryToStoreSample(): array
    {
        return $this->toStore('SAMPLE');
    }

    private function toStore(string $scope): array
    {
        $stores = $this->names()['stores'];
        $rows = DB::table('fin_store_delivery_lines as l')->join('fin_store_deliveries as d', 'd.id', '=', 'l.delivery_id')->where('d.company_id', $this->company())->where('d.status', '!=', 'DRAFT')
            ->when($this->f['store'] ?? null, fn ($q, $s) => $q->where('d.store_id', $s))->when($this->f['from'] ?? null, fn ($q, $x) => $q->where('d.receive_date', '>=', $x))->when($this->f['to'] ?? null, fn ($q, $x) => $q->where('d.receive_date', '<=', $x))
            ->orderBy('d.receive_date')->get(['d.receive_date', 'd.delivery_no', 'd.store_id', 'd.status', 'l.receipt_id', 'l.fabric_type', 'l.colour', 'l.lot_no', 'l.shade_no', 'l.fin_gsm', 'l.grey_qty', 'l.finish_qty', 'l.rolls'])
            ->map(fn ($x) => ['batch' => $this->batchOf($x->receipt_id), 'x' => $x])->filter(fn ($p) => ($p['batch']->scope ?? 'BULK') === $scope)
            ->map(fn ($p) => ['receive_date' => (string) $p['x']->receive_date, 'delivery_no' => $p['x']->delivery_no, 'store' => $stores[$p['x']->store_id] ?? null, 'batch_no' => $p['batch']?->batch_no, 'order_no' => $p['batch']?->order_no,
                'fabric_type' => $p['x']->fabric_type, 'colour' => $p['x']->colour, 'lot_no' => $p['x']->lot_no, 'shade_no' => $p['x']->shade_no, 'grey_qty' => self::r3($p['x']->grey_qty), 'finish_qty' => self::r3($p['x']->finish_qty),
                'yield_pct' => self::pct((float) $p['x']->finish_qty, (float) $p['x']->grey_qty), 'rolls' => (int) $p['x']->rolls, 'status' => $p['x']->status])->values();

        return $this->table(['receive_date:date', 'delivery_no', 'store', 'batch_no', 'order_no', 'fabric_type', 'colour', 'lot_no', 'shade_no', 'grey_qty:number', 'finish_qty:number', 'yield_pct:pct', 'rolls:int', 'status:status'], $rows,
            ['grey' => 'grey_qty', 'finish' => 'finish_qty']);
    }

    private function issueRegister(): array
    {
        $stores = $this->names()['stores'];
        $rows = DB::table('fin_cut_requisition_lines as l')->join('fin_cut_requisitions as r', 'r.id', '=', 'l.requisition_id')->join('fin_store_lots as lot', 'lot.id', '=', 'l.lot_id')->where('r.company_id', $this->company())
            ->whereIn('r.status', ['PARTIAL', 'COMPLETE'])->when($this->f['store'] ?? null, fn ($q, $s) => $q->where('r.store_id', $s))
            ->when($this->f['from'] ?? null, fn ($q, $x) => $q->where('r.req_date', '>=', $x))->when($this->f['to'] ?? null, fn ($q, $x) => $q->where('r.req_date', '<=', $x))
            ->orderBy('r.req_date')->get(['r.req_date', 'r.req_no', 'r.cut_prog_ref', 'r.store_id', 'r.status', 'lot.lot_code', 'lot.colour', 'l.lot_no', 'l.req_qty', 'l.issue_qty', 'l.rolls'])
            ->map(fn ($x) => ['req_date' => (string) $x->req_date, 'req_no' => $x->req_no, 'cut_prog_ref' => $x->cut_prog_ref, 'store' => $stores[$x->store_id] ?? null, 'lot_code' => $x->lot_code, 'lot_no' => $x->lot_no, 'colour' => $x->colour,
                'req_qty' => self::r3($x->req_qty), 'issue_qty' => self::r3($x->issue_qty), 'rolls' => (int) $x->rolls, 'status' => $x->status]);

        return $this->table(['req_date:date', 'req_no', 'cut_prog_ref', 'store', 'lot_code', 'lot_no', 'colour', 'req_qty:number', 'issue_qty:number', 'rolls:int', 'status:status'], $rows, ['asked' => 'req_qty', 'issued' => 'issue_qty']);
    }

    private function currentStock(): array
    {
        $rows = $this->lots()->filter(fn ($l) => $l['balance_kg'] > 0.0005);

        return $this->table(['lot_code', 'store', 'batch_no', 'order_no', 'colour', 'fabric_type', 'lot_no', 'shade_no', 'received_on:date', 'received_kg:number', 'balance_kg:number', 'balance_rolls:int'], $rows->values(),
            ['received' => 'received_kg', 'balance' => 'balance_kg']);
    }

    private function stockLedgerSample(): array
    {
        $rows = $this->lots()->where('scope', 'SAMPLE');

        return $this->table(['lot_code', 'store', 'batch_no', 'order_no', 'colour', 'received_on:date', 'received_kg:number', 'issued_kg:number', 'returned_kg:number', 'transferred_kg:number', 'balance_kg:number'], $rows->values(),
            ['received' => 'received_kg', 'balance' => 'balance_kg']);
    }

    private function stockDetailsLedger(): array
    {
        $lots = $this->lots()->keyBy('id');
        $moves = DB::table('fin_store_moves')->whereIn('lot_id', $lots->keys())->orderBy('move_date')->orderBy('created_at')->get();
        $running = [];
        $rows = collect();
        foreach ($moves as $m) {
            $running[$m->lot_id] = round(($running[$m->lot_id] ?? 0) + (float) $m->qty_kg, 3);
            if (($this->f['from'] ?? null) && (string) $m->move_date < $this->f['from'] || ($this->f['to'] ?? null) && (string) $m->move_date > $this->f['to']) {
                continue;
            }
            $l = $lots[$m->lot_id];
            $rows->push(['move_date' => (string) $m->move_date, 'lot_code' => $l['lot_code'], 'store' => $l['store'], 'colour' => $l['colour'], 'kind' => $m->kind, 'doc_no' => $m->doc_no,
                'in_kg' => (float) $m->qty_kg > 0 ? self::r3($m->qty_kg) : 0.0, 'out_kg' => (float) $m->qty_kg < 0 ? self::r3(-$m->qty_kg) : 0.0, 'balance_kg' => $running[$m->lot_id]]);
        }

        return $this->table(['move_date:date', 'lot_code', 'store', 'colour', 'kind:status', 'doc_no', 'in_kg:number', 'out_kg:number', 'balance_kg:number'], $rows, ['in' => 'in_kg', 'out' => 'out_kg']);
    }

    /** Left-over: what is still on a lot once cutting has drawn on it and has nothing open against it. */
    private function leftOverStock(): array
    {
        $open = DB::table('fin_cut_requisition_lines as l')->join('fin_cut_requisitions as r', 'r.id', '=', 'l.requisition_id')->where('r.company_id', $this->company())->whereIn('r.status', ['DRAFT', 'PARTIAL'])->pluck('l.lot_id')->unique()->all();
        $rows = $this->lots()->filter(fn ($l) => $l['balance_kg'] > 0.0005 && $l['issued_kg'] > 0 && ! in_array($l['id'], $open, true));

        return $this->table(['lot_code', 'store', 'batch_no', 'order_no', 'colour', 'fabric_type', 'received_kg:number', 'issued_kg:number', 'balance_kg:number'], $rows->values(), ['balance' => 'balance_kg']);
    }

    private function leftOverUtilization(): array
    {
        $lots = $this->lots()->keyBy('id');
        $rows = DB::table('fin_store_returns')->where('company_id', $this->company())->whereIn('lot_id', $lots->keys())
            ->when($this->f['from'] ?? null, fn ($q, $x) => $q->where('return_date', '>=', $x))->when($this->f['to'] ?? null, fn ($q, $x) => $q->where('return_date', '<=', $x))->orderBy('return_date')->get()
            ->map(fn ($r) => ['return_date' => (string) $r->return_date, 'return_no' => $r->return_no, 'kind' => $r->kind, 'lot_code' => $lots[$r->lot_id]['lot_code'], 'colour' => $lots[$r->lot_id]['colour'],
                'to_unit' => $r->to_unit, 'reason' => $r->reason, 'qty_kg' => self::r3($r->qty_kg), 'rolls' => (int) $r->rolls]);

        return $this->table(['return_date:date', 'return_no', 'kind:status', 'lot_code', 'colour', 'to_unit', 'reason', 'qty_kg:number', 'rolls:int'], $rows, ['qty' => 'qty_kg']);
    }

    // ── Masters ──────────────────────────────────────────────────────

    private function processTypeRegister(): array
    {
        $groups = DB::table('fin_process_groups')->where('company_id', $this->company())->pluck('name', 'id');
        $subs = DB::table('fin_process_subgroups')->where('company_id', $this->company())->pluck('name', 'id');
        $rows = DB::table('fin_process_types')->where('company_id', $this->company())->orderBy('display_order')->get()->map(fn ($p) => ['code' => $p->code, 'name' => $p->name, 'short_name' => $p->short_name,
            'group' => $groups[$p->group_id] ?? null, 'subgroup' => $subs[$p->subgroup_id] ?? null, 'wip_stage' => $p->wip_stage, 'rate' => round((float) $p->rate, 2), 'loss_pct' => (float) $p->loss_pct, 'active' => (bool) $p->active]);

        return $this->table(['code', 'name', 'short_name', 'group', 'subgroup', 'wip_stage:status', 'rate:money', 'loss_pct:pct', 'active:yesno'], $rows);
    }

    private function machineProcessRegister(): array
    {
        $n = $this->names();
        $rows = DB::table('fin_machine_processes')->where('company_id', $this->company())->orderBy('sequence')->get()->map(fn ($m) => ['machine' => $n['machines'][$m->machine_id] ?? null, 'process' => $n['processes'][$m->process_id] ?? null,
            'dia_type' => $m->dia_type, 'sequence' => (int) $m->sequence, 'batch_status' => $m->batch_status, 'next_status' => $m->next_status, 'is_default' => (bool) $m->is_default, 'active' => (bool) $m->active]);

        return $this->table(['machine', 'process', 'dia_type:status', 'sequence:int', 'batch_status:status', 'next_status:status', 'is_default:yesno', 'active:yesno'], $rows->sortBy(['machine', 'sequence'])->values());
    }

    // ── Reading ──────────────────────────────────────────────────────

    /** Posted production lines in the window. @return Collection<int, array<string, mixed>> */
    private function lines(): Collection
    {
        return $this->cache['lines'] ??= (function () {
            $n = $this->names();
            $codes = DB::table('fin_process_types')->where('company_id', $this->company())->pluck('code', 'id');

            return DB::table('fin_production_lines as l')->join('fin_production_sheets as s', 's.id', '=', 'l.sheet_id')->where('s.company_id', $this->company())->where('s.status', 'POSTED')
                ->when($this->f['from'] ?? null, fn ($q, $d) => $q->where('s.production_date', '>=', $d))->when($this->f['to'] ?? null, fn ($q, $d) => $q->where('s.production_date', '<=', $d))
                ->orderBy('s.production_date')->orderBy('s.sheet_no')->orderBy('l.sort_order')
                ->get(['s.production_date', 's.sheet_no', 's.line', 's.shift', 'l.receipt_id', 'l.machine_id', 'l.process_id', 'l.input_kg', 'l.output_kg', 'l.fin_gsm', 'l.fin_dia', 'l.unload_time'])
                ->map(function ($x) use ($n, $codes) {
                    $b = $this->batchOf($x->receipt_id);
                    $in = self::r3($x->input_kg);
                    $out = self::r3($x->output_kg);

                    return ['production_date' => (string) $x->production_date, 'sheet_no' => $x->sheet_no, 'line' => $x->line, 'shift' => $x->shift, 'receipt_id' => $x->receipt_id, 'batch_no' => $b?->batch_no, 'buyer' => $b?->buyer,
                        'order_no' => $b?->order_no, 'colour' => $b?->colour, 'machine_id' => $x->machine_id, 'machine' => $n['machines'][$x->machine_id] ?? null, 'process' => $n['processes'][$x->process_id] ?? null,
                        'process_code' => $codes[$x->process_id] ?? null, 'input_kg' => $in, 'output_kg' => $out, 'loss_kg' => round($in - $out, 3), 'loss_pct' => self::pct($in - $out, $in),
                        'fin_gsm' => $x->fin_gsm === null ? null : (float) $x->fin_gsm, 'fin_dia' => $x->fin_dia === null ? null : (float) $x->fin_dia,
                        'unload_hour' => $x->unload_time ? (int) CarbonImmutable::parse($x->unload_time, 'UTC')->setTimezone('Asia/Dhaka')->format('G') : null];
                })->values();
        })();
    }

    /** Every finishing card on the floor, as the floor reads it. @return Collection<int, array<string, mixed>> */
    private function cards(): Collection
    {
        return $this->cache['cards'] ??= (function () {
            $n = $this->names();
            $now = CarbonImmutable::now();

            return DB::table('fin_batch_receipts')->where('company_id', $this->company())->where('status', 'ON_FLOOR')->orderBy('receipt_no')->get()->map(function ($c) use ($n, $now) {
                $b = $this->batchOf($c->id);
                $stageAt = $c->stage_at ? CarbonImmutable::parse($c->stage_at, 'UTC') : null;
                $load = $c->last_load_at ? CarbonImmutable::parse($c->last_load_at, 'UTC') : null;

                return ['id' => $c->id, 'receipt_no' => $c->receipt_no, 'batch_no' => $b?->batch_no, 'scope' => $b?->scope, 'buyer' => $b?->buyer, 'order_no' => $b?->order_no, 'colour' => $b?->colour, 'dia_type' => $c->dia_type,
                    'stage' => $c->stage, 'stage_at' => $stageAt?->setTimezone('Asia/Dhaka')->toDateString(), 'stage_hours' => $stageAt ? round($stageAt->diffInMinutes($now) / 60, 1) : null,
                    'last_process' => $n['processes'][$c->last_process_id] ?? null, 'last_machine' => $n['machines'][$c->last_machine_id] ?? null, 'recv_qty_kg' => self::r3($c->recv_qty_kg), 'recv_rolls' => (int) $c->recv_rolls,
                    'spent_hours' => $load ? round($load->diffInMinutes($now) / 60, 1) : null];
            })->values();
        })();
    }

    /** Posted S/C challan lines in the window, with what came back. @return Collection<int, array<string, mixed>> */
    private function scLines(): Collection
    {
        return $this->cache['sc'] ??= (function () {
            $n = $this->names();
            $lines = DB::table('fin_sc_challan_lines as l')->join('fin_sc_challans as c', 'c.id', '=', 'l.challan_id')->where('c.company_id', $this->company())->where('c.status', 'POSTED')
                ->when($this->f['party'] ?? null, fn ($q, $p) => $q->where('c.supplier_id', $p))->when($this->f['store'] ?? null, fn ($q, $s) => $q->where('c.store_id', $s))
                ->when($this->f['from'] ?? null, fn ($q, $d) => $q->where('c.challan_date', '>=', $d))->when($this->f['to'] ?? null, fn ($q, $d) => $q->where('c.challan_date', '<=', $d))
                ->orderBy('c.challan_date')->orderBy('c.challan_no')->orderBy('l.sort_order')->get(['l.id', 'c.challan_date', 'c.challan_no', 'c.supplier_id', 'c.gate_pass_no', 'l.receipt_id', 'l.order_id', 'l.colour', 'l.process_id', 'l.lot_no', 'l.qty_kg', 'l.rolls', 'l.rate']);
            $back = FinSc::returned($lines->pluck('id')->all());
            $orders = DB::table('merch_orders')->whereIn('id', $lines->pluck('order_id')->filter())->pluck('order_no', 'id');

            return $lines->map(function ($x) use ($n, $back, $orders) {
                $qty = self::r3($x->qty_kg);
                $r = $back[$x->id] ?? ['recv' => 0.0, 'short' => 0.0];

                return ['challan_date' => (string) $x->challan_date, 'challan_no' => $x->challan_no, 'supplier' => $n['suppliers'][$x->supplier_id] ?? null, 'gate_pass_no' => $x->gate_pass_no,
                    'batch_no' => $x->receipt_id ? $this->batchOf($x->receipt_id)?->batch_no : null, 'order_no' => $orders[$x->order_id] ?? null, 'colour' => $x->colour, 'process' => $n['processes'][$x->process_id] ?? null,
                    'lot_no' => $x->lot_no, 'qty_kg' => $qty, 'rolls' => (int) $x->rolls, 'rate' => $x->rate === null ? null : round((float) $x->rate, 2), 'value' => round($qty * (float) ($x->rate ?? 0), 2),
                    'received_kg' => $r['recv'], 'short_kg' => $r['short'], 'balance_kg' => round($qty - $r['recv'] - $r['short'], 3)];
            })->values();
        })();
    }

    /** Non-cancelled bill lines in the window. @return Collection<int, object> */
    private function billLines(): Collection
    {
        return DB::table('fin_sc_bill_lines as l')->join('fin_sc_bills as b', 'b.id', '=', 'l.bill_id')->join('fin_sc_challans as c', 'c.id', '=', 'l.challan_id')->where('b.company_id', $this->company())->where('b.status', '!=', 'CANCELLED')
            ->when($this->f['party'] ?? null, fn ($q, $p) => $q->where('b.supplier_id', $p))->when($this->f['from'] ?? null, fn ($q, $d) => $q->where('b.bill_date', '>=', $d))->when($this->f['to'] ?? null, fn ($q, $d) => $q->where('b.bill_date', '<=', $d))
            ->orderBy('b.bill_date')->get(['b.bill_date', 'b.bill_no', 'b.supplier_id', 'b.status', 'c.challan_no', 'l.process_id', 'l.qty_kg', 'l.rate', 'l.amount']);
    }

    /** The store's lots (Store box honoured) with their balances and moves summed. @return Collection<int, array<string, mixed>> */
    private function lots(): Collection
    {
        return $this->cache['lots'] ??= (function () {
            $stores = $this->names()['stores'];
            $lots = DB::table('fin_store_lots')->where('company_id', $this->company())->when($this->f['store'] ?? null, fn ($q, $s) => $q->where('store_id', $s))->orderBy('lot_code')->get();
            $sums = DB::table('fin_store_moves')->whereIn('lot_id', $lots->pluck('id'))->groupBy('lot_id', 'kind')->selectRaw('lot_id, kind, sum(qty_kg) as kg, sum(rolls) as rolls')->get()->groupBy('lot_id');
            $orders = DB::table('merch_orders')->whereIn('id', $lots->pluck('order_id')->filter())->pluck('order_no', 'id');

            return $lots->map(function ($l) use ($stores, $sums, $orders) {
                $s = $sums[$l->id] ?? collect();
                $kind = fn (string $k) => round((float) ($s->firstWhere('kind', $k)->kg ?? 0), 3);
                $b = $this->batchOf($l->receipt_id);

                return ['id' => $l->id, 'lot_code' => $l->lot_code, 'store' => $stores[$l->store_id] ?? null, 'batch_no' => $b?->batch_no, 'scope' => $b?->scope, 'order_no' => $orders[$l->order_id] ?? null, 'colour' => $l->colour,
                    'fabric_type' => $l->fabric_type, 'lot_no' => $l->lot_no, 'shade_no' => $l->shade_no, 'received_on' => (string) $l->received_on, 'received_kg' => self::r3($l->received_kg),
                    'issued_kg' => -$kind('ISSUE'), 'returned_kg' => $kind('RETURN'), 'transferred_kg' => -$kind('TRANSFER'), 'balance_kg' => round($s->sum(fn ($x) => (float) $x->kg), 3), 'balance_rolls' => (int) $s->sum(fn ($x) => (int) $x->rolls)];
            })->values();
        })();
    }

    /** A card's batch: number, buyer, order, colour, scope. */
    private function batchOf(?string $cardId): ?object
    {
        if (! $cardId) {
            return null;
        }
        $this->cache['batchOf'] ??= (function () {
            $cards = DB::table('fin_batch_receipts')->where('company_id', $this->company())->pluck('batch_id', 'id');
            $batches = FinFloor::batches($cards->values()->unique()->all());

            return $cards->map(fn ($b) => $batches[$b] ?? null)->all();
        })();

        return $this->cache['batchOf'][$cardId] ?? null;
    }

    /** @return array<string, array<string, string>> */
    private function names(): array
    {
        return $this->cache['names'] ??= (function () {
            [$machines, $processes] = FinFloor::machineAndProcessNames();
            $c = $this->company();

            return ['machines' => $machines, 'processes' => $processes, 'suppliers' => DB::table('mp_suppliers')->where('company_id', $c)->pluck('name', 'id')->all(),
                'stores' => DB::table('stores')->where('company_id', $c)->get(['id', 'name', 'code'])->mapWithKeys(fn ($s) => [$s->id => "{$s->name} ({$s->code})"])->all()];
        })();
    }

    private function company(): string
    {
        return FinFloor::company();
    }

    // ── Shaping (as Knit Production's reports) ───────────────────────

    /** @param list<string> $spec @param iterable<array<string, mixed>> $rows @param array<string, string> $stats */
    private function table(array $spec, iterable $rows, array $stats = []): array
    {
        $columns = array_map(function (string $s): array {
            [$s, $group] = array_pad(explode('@', $s, 2), 2, null);
            [$key, $type] = array_pad(explode(':', $s, 2), 2, 'text');

            return ['key' => $key, 'type' => $type] + ($group ? ['group' => $group] : []);
        }, $spec);
        $types = array_column($columns, 'type', 'key');
        $rows = collect($rows)->values();
        $out = [['key' => 'rows', 'value' => (float) $rows->count(), 'type' => 'int']];
        foreach ($stats as $stat => $col) {
            $type = $types[$col] ?? 'number';
            $out[] = ['key' => $stat, 'value' => round($rows->sum(fn ($r) => (float) ($r[$col] ?? 0)), $type === 'money' ? 2 : 3), 'type' => in_array($type, ['money', 'int'], true) ? $type : 'number'];
        }

        return ['columns' => $columns, 'rows' => $rows->all(), 'stats' => $out];
    }

    /** @param list<string> $keys @param list<string> $sums */
    private function group(Collection $rows, array $keys, array $sums, ?callable $extra = null): Collection
    {
        return $rows->groupBy(fn ($r) => implode('|', array_map(fn ($k) => (string) ($r[$k] ?? ''), $keys)))
            ->map(function (Collection $g) use ($keys, $sums, $extra): array {
                $row = array_intersect_key($g->first(), array_flip($keys)) + ['entries' => $g->count()];
                foreach ($sums as $s) {
                    $row[$s] = round($g->sum(fn ($r) => (float) ($r[$s] ?? 0)), 3);
                }

                return $extra ? $extra($row, $g) : $row;
            })->values();
    }

    private static function pct(float $part, float $whole): ?float
    {
        return $whole > 0 ? round(100 * $part / $whole, 2) : null;
    }

    private static function r3(mixed $v): float
    {
        return round((float) $v, 3);
    }
}
