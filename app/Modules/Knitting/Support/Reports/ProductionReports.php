<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Support\Reports;

use App\Modules\Knitting\Models\KnitCapacityPlan;
use App\Modules\Knitting\Models\KnitCard;
use App\Modules\Knitting\Models\KnitGreyReceipt;
use App\Modules\Knitting\Models\KnitGreyReceiptRoll;
use App\Modules\Knitting\Models\KnitProdOrder;
use App\Modules\Knitting\Models\KnitProgram;
use App\Modules\Knitting\Models\KnitRoll;
use App\Modules\Knitting\Models\KnitRollInspection;
use App\Modules\Knitting\Models\KnitSamplePlan;
use App\Modules\Knitting\Models\KnitSamplePlanLine;
use App\Modules\Knitting\Models\KnitScGreyReceipt;
use App\Modules\Knitting\Models\KnitScGreyReceiptLine;
use App\Modules\Knitting\Models\KnitScGreyReturn;
use App\Modules\Knitting\Models\KnitScGreyReturnLine;
use App\Modules\Knitting\Models\KnitScProgramLine;
use App\Modules\Knitting\Models\YarnIssueLine;
use App\Modules\Knitting\Models\YarnIssueReturnLine;
use App\Modules\Knitting\Support\KnitProduction;
use App\Modules\Knitting\Support\KnittingLabels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The floor's reports: rolls knitted (by day, machine, shift, order and
 * colour), programmes and knit cards against what they were given, and
 * machines against their capacity plans.
 */
trait ProductionReports
{
    /** @var Collection<int, array<string, mixed>>|null */
    private ?Collection $rollRows = null;

    /**
     * Every roll in the window as one row: where and when it was knitted, the
     * order, colour and construction its card carries, its weights and where
     * it now is. `kinds` narrows to production types (BULK, YD, SAMPLE …).
     *
     * @param  list<string>|null  $kinds
     * @return Collection<int, array<string, mixed>>
     */
    private function rolls(?array $kinds = null): Collection
    {
        $this->rollRows ??= (function (): Collection {
            $q = $this->q;
            $rolls = $q->window(KnitRoll::query(), 'roll_at')->orderBy('roll_at')->orderBy('roll_no')->get();
            $cards = KnitCard::query()->whereIn('id', $rolls->pluck('card_id'))->get()->keyBy('id');
            $orders = KnitProdOrder::query()->whereIn('id', $cards->pluck('prod_order_id'))->get()->keyBy('id');
            $programs = KnitProgram::query()->whereIn('id', $cards->pluck('program_id')->filter())->pluck('kp_no', 'id');
            $grades = KnitRollInspection::query()->whereIn('roll_id', $rolls->pluck('id'))->where('status', '!=', 'DRAFT')->pluck('grade', 'roll_id');
            $received = KnitGreyReceiptRoll::query()->whereIn('roll_id', $rolls->pluck('id'))->pluck('receipt_id', 'roll_id');
            $notes = KnitGreyReceipt::query()->whereIn('id', $received->values())->pluck('note_no', 'id');
            $categories = collect(KnitProduction::categories())->pluck('label', 'value');

            return $rolls->map(function (KnitRoll $r) use ($q, $cards, $orders, $programs, $grades, $received, $notes, $categories): array {
                $c = $cards[$r->card_id] ?? null;
                $po = $c ? ($orders[$c->prod_order_id] ?? null) : null;
                $net = round((float) $r->weight - (float) $r->deducted, 3);

                return [
                    'prod_date' => $r->roll_at->toDateString(), 'floor' => $q->floors()[$c?->floor_id] ?? null, 'machine' => $q->machines()[$r->machine_id] ?? null,
                    'shift' => $q->shifts()[$r->shift_id] ?? null, 'operator' => $q->employees()[$r->operator_id] ?? null, 'sc_party' => $q->parties()[$r->sc_party_id] ?? null,
                    'kind' => $po?->order_kind, 'category' => $po ? ($categories[$po->category_code] ?? $po->category_code) : null, 'buyer' => $q->buyers()[$po?->buyer_id] ?? null,
                    'job' => $q->job($po?->order_id), 'order_no' => $q->orderNo($po?->order_id), 'style' => $q->style($po?->order_id),
                    'colour' => $c?->colour_name ?: $po?->colour_name, 'fabric' => $po?->fabric_desc, 'finish_dia' => $c?->finish_dia ?: $po?->finish_dia, 'gsm' => $po?->gsm,
                    'machine_dia' => $c?->machine_dia, 'gauge' => $c?->gauge, 'yarn' => $c?->yarn_details, 'kc_no' => $c?->kc_no, 'kp_no' => $programs[$c?->program_id] ?? null,
                    'roll_no' => $r->roll_no, 'label' => $r->label_info, 'gross' => self::r3($r->weight), 'deduct' => self::r3($r->deducted), 'net' => $net, 'rolls' => 1,
                    'grade' => $grades[$r->id] ?? null, 'qc_status' => $r->quality, 'where' => $r->movement, 'store_receipt' => isset($received[$r->id]) ? ($notes[$received[$r->id]] ?? null) : null,
                    'roll_id' => $r->id, 'prod_order_id' => $po?->id, 'order_id' => $po?->order_id, 'card_id' => $r->card_id, 'shift_id' => $r->shift_id, 'machine_id' => $r->machine_id,
                    'passed' => $r->quality === 'PASSED' ? $net : 0.0, 'rejected' => $r->quality === 'REJECTED' ? $net : 0.0,
                    'in_store' => in_array($r->movement, ['GREY_STORE', 'BATCH_STORE'], true) ? $net : 0.0, 'delivered' => $r->movement === 'DELIVERED' ? $net : 0.0,
                ];
            });
        })();

        return $kinds === null ? $this->rollRows : $this->rollRows->whereIn('kind', $kinds)->values();
    }

    /** Production: the rolls a group knitted, rejected rolls aside (knitted, but not production). */
    private function produced(array $keys, ?array $kinds = null): Collection
    {
        return $this->group($this->rolls($kinds)->where('qc_status', '!=', 'REJECTED'), $keys, ['gross', 'deduct', 'net', 'rolls']);
    }

    private const PROD_SUMS = ['entries:int', 'gross:number', 'deduct:number', 'net:number', 'rolls:int'];

    private const PROD_STATS = ['rolls' => 'rolls', 'production' => 'net', 'deducted' => 'deduct'];

    private function orderAndColorWiseFabricProductionStatement(): array
    {
        return $this->table(['buyer', 'order_no', 'colour', 'fabric', ...self::PROD_SUMS], $this->produced(['buyer', 'order_no', 'colour', 'fabric']), self::PROD_STATS);
    }

    private function dailyMcAndShiftWiseKnittingStatement(): array
    {
        return $this->table(['prod_date:date', 'floor', 'machine', 'shift', ...self::PROD_SUMS], $this->produced(['prod_date', 'floor', 'machine', 'shift']), self::PROD_STATS);
    }

    private function dailyOrderAndColorWiseKnittingProduction(): array
    {
        return $this->table(['prod_date:date', 'buyer', 'order_no', 'colour', ...self::PROD_SUMS], $this->produced(['prod_date', 'buyer', 'order_no', 'colour']), self::PROD_STATS);
    }

    /** By day, order and colour, valued at the order's knitting rate (its S/C programme rate, where it has one). */
    private function dailyKnittingProductionWithValue(): array
    {
        $rates = $this->knitRates();
        $rows = $this->produced(['prod_date', 'buyer', 'order_no', 'colour', 'order_id'])->map(function (array $r) use ($rates): array {
            $rate = (float) ($rates[$r['order_id']] ?? 0);

            return $r + ['rate' => round($rate, 2), 'value' => round($rate * $r['net'], 2)];
        });

        return $this->table(['prod_date:date', 'buyer', 'order_no', 'colour', ...self::PROD_SUMS, 'rate:money', 'value:money'], $rows, ['production' => 'net', 'value' => 'value']);
    }

    /** Every roll as it was labelled: who knitted it, the card's construction, its weights, grade and where it is. */
    private function dailyRollLabellingStatementScOutHouse(): array
    {
        return $this->table(['prod_date:date', 'floor', 'machine', 'shift', 'operator', 'sc_party', 'kind:status', 'category', 'buyer', 'job', 'order_no', 'colour', 'fabric', 'finish_dia', 'gsm', 'machine_dia',
            'yarn', 'kc_no', 'roll_no', 'label', 'gross:number', 'deduct:number', 'net:number', 'rolls:int', 'grade', 'qc_status:status', 'where:status', 'store_receipt'], $this->rolls(), self::PROD_STATS);
    }

    /** Per order and colour: booked, knitted, passed, rejected, in the grey store, delivered, and what is left to knit. */
    private function orderAndColorWiseKnittingStatementNew(): array
    {
        $orders = KnitProdOrder::query()->whereIn('id', $this->rolls()->pluck('prod_order_id')->filter()->unique())->get()->keyBy('id');
        $rows = $this->group($this->rolls(), ['prod_order_id', 'buyer', 'order_no', 'style', 'kind', 'colour', 'fabric'], ['net', 'rolls', 'passed', 'rejected', 'in_store', 'delivered'], function (array $r) use ($orders): array {
            $booked = (float) ($orders[$r['prod_order_id']]->req_qty ?? 0);

            return ['buyer' => $r['buyer'], 'order_no' => trim(($r['style'] ? $r['style'].' / ' : '').($r['order_no'] ?? '')) ?: null, 'kind' => $r['kind'], 'colour' => $r['colour'], 'fabric' => $r['fabric'],
                'rolls' => $r['rolls'], 'booked' => self::r3($booked), 'knitted' => $r['net'], 'passed' => $r['passed'], 'rejected' => $r['rejected'], 'in_store' => $r['in_store'], 'delivered' => $r['delivered'],
                'balance' => self::r3($booked - $r['net']), 'achieved' => self::pct($r['net'], $booked)];
        });

        return $this->table(['buyer', 'order_no', 'kind:status', 'colour', 'fabric', 'rolls:int', 'booked:number', 'knitted:number', 'passed:number', 'rejected:number', 'in_store:number', 'delivered:number', 'balance:number', 'achieved:pct'],
            $rows, ['booked' => 'booked', 'knitted' => 'knitted', 'balance' => 'balance']);
    }

    /** Knitting programmes in the window against their booking, cards and what the floor has knitted. */
    private function programRows(?array $kinds = null): Collection
    {
        $q = $this->q;
        $programs = $q->window(KnitProgram::query(), 'program_date')->where('status', '!=', 'CANCELLED')->orderBy('program_date')->orderBy('kp_no')->get();
        $orders = KnitProdOrder::query()->whereIn('id', $programs->pluck('prod_order_id'))->get()->keyBy('id');
        if ($kinds !== null) {
            $programs = $programs->filter(fn ($p) => in_array($orders[$p->prod_order_id]?->order_kind, $kinds, true))->values();
        }
        $cards = KnitCard::query()->whereIn('program_id', $programs->pluck('id'))->where('status', '!=', 'CANCELLED')->get()->groupBy('program_id');
        $rolled = KnitRoll::query()->whereIn('card_id', $cards->flatten()->pluck('id'))->where('quality', '!=', 'REJECTED')->groupBy('card_id')->selectRaw('card_id, sum(weight - deducted) as q')->pluck('q', 'card_id');
        $categories = collect(KnitProduction::categories())->pluck('label', 'value');

        return $programs->map(function (KnitProgram $p) use ($q, $orders, $cards, $rolled, $categories): array {
            $po = $orders[$p->prod_order_id] ?? null;
            $mine = $cards[$p->id] ?? collect();
            $produced = round((float) $mine->sum(fn ($c) => (float) ($rolled[$c->id] ?? 0)), 3);
            $o = $q->order($po?->order_id);

            return ['program_id' => $p->id, 'program_date' => $p->program_date->toDateString(), 'kp_no' => $p->kp_no, 'floor' => $q->floors()[$p->floor_id] ?? null, 'kind' => $po?->order_kind,
                'category' => $po ? ($categories[$po->category_code] ?? $po->category_code) : null, 'buyer' => $q->buyers()[$po?->buyer_id] ?? null, 'job' => $o?->job_no, 'order_no' => $o?->order_no,
                'style' => $o ? ($o->work_style_no ?: $o->style_no) : null, 'colour' => $po?->colour_name, 'fabric' => $po?->fabric_desc, 'ship_month' => $po?->ship_month,
                'booking_qty' => self::r3($po?->req_qty), 'prog_qty' => self::r3($p->qty), 'cards' => $mine->count(), 'card_assigned' => self::r3($mine->sum('assigned_qty')),
                'produced' => $produced, 'balance' => self::r3((float) $p->qty - $produced), 'achieved' => self::pct($produced, (float) $p->qty), 'status' => $p->status];
        });
    }

    private const PROGRAM_COLS = ['program_date:date', 'kp_no', 'floor', 'kind:status', 'category', 'buyer', 'job', 'order_no', 'style', 'colour', 'fabric', 'ship_month', 'booking_qty:number', 'prog_qty:number',
        'cards:int', 'card_assigned:number', 'produced:number', 'balance:number', 'achieved:pct', 'status:status'];

    private const PROGRAM_STATS = ['programmed' => 'prog_qty', 'produced' => 'produced', 'balance' => 'balance'];

    private function knittingDepartmentFollowupReport(): array
    {
        return $this->table(self::PROGRAM_COLS, $this->programRows(), self::PROGRAM_STATS);
    }

    private function jobWiseKnittingProgram(): array
    {
        return $this->table(self::PROGRAM_COLS, $this->programRows()->sortBy([['job', 'asc'], ['program_date', 'asc']])->values(), self::PROGRAM_STATS);
    }

    private function knittingProgramForSample(): array
    {
        return $this->table(self::PROGRAM_COLS, $this->programRows(['SAMPLE']), self::PROGRAM_STATS);
    }

    /** Knit cards opened in the window: assigned, knitted, rolls, QC, in store and left. */
    private function cardRows(): Collection
    {
        $q = $this->q;
        $cards = $q->window(KnitCard::query(), 'card_date')->where('status', '!=', 'CANCELLED')->orderBy('card_date')->orderBy('kc_no')->get();
        $orders = KnitProdOrder::query()->whereIn('id', $cards->pluck('prod_order_id'))->get()->keyBy('id');
        $programs = KnitProgram::query()->whereIn('id', $cards->pluck('program_id')->filter())->pluck('kp_no', 'id');
        $rolls = KnitRoll::query()->whereIn('card_id', $cards->pluck('id'))->get()->groupBy('card_id');

        return $cards->map(function (KnitCard $c) use ($q, $orders, $programs, $rolls): array {
            $po = $orders[$c->prod_order_id] ?? null;
            $mine = $rolls[$c->id] ?? collect();
            $net = fn ($r) => (float) $r->weight - (float) $r->deducted;
            $produced = round($mine->where('quality', '!=', 'REJECTED')->sum($net), 3);
            $o = $q->order($po?->order_id);

            return ['card_date' => $c->card_date->toDateString(), 'kc_no' => $c->kc_no, 'kp_no' => $programs[$c->program_id] ?? null, 'floor' => $q->floors()[$c->floor_id] ?? null,
                'machine' => $q->machines()[$c->machine_id] ?? null, 'kind' => $po?->order_kind, 'buyer' => $q->buyers()[$po?->buyer_id] ?? null, 'job' => $o?->job_no, 'order_no' => $o?->order_no,
                'style' => $o ? ($o->work_style_no ?: $o->style_no) : null, 'colour' => $c->colour_name, 'fabric' => $po?->fabric_desc, 'finish_dia' => $c->finish_dia ?: $po?->finish_dia, 'gsm' => $po?->gsm,
                'machine_dia' => trim(($c->machine_dia ?? '').($c->gauge ? ' x '.$c->gauge : '')) ?: null, 'yarn' => $c->yarn_details, 'assigned' => self::r3($c->assigned_qty), 'produced' => $produced,
                'rolls' => $mine->where('quality', '!=', 'REJECTED')->count(), 'passed' => round($mine->where('quality', 'PASSED')->sum($net), 3), 'qc_pending' => round($mine->where('quality', 'PENDING')->sum($net), 3),
                'in_store' => round($mine->whereIn('movement', ['GREY_STORE', 'BATCH_STORE'])->sum($net), 3), 'balance' => self::r3((float) $c->assigned_qty - $produced),
                'start_date' => $mine->min('roll_at')?->toDateString(), 'end_date' => $mine->max('roll_at')?->toDateString(), 'status' => $c->status, 'cards' => 1];
        });
    }

    private const CARD_STATS = ['assigned' => 'assigned', 'produced' => 'produced', 'balance' => 'balance'];

    private function dateWiseKnitCardReport(): array
    {
        return $this->table(['card_date:date', 'kc_no', 'kp_no', 'floor', 'machine', 'kind:status', 'buyer', 'job', 'order_no', 'style', 'colour', 'fabric', 'finish_dia', 'gsm', 'machine_dia', 'yarn',
            'assigned:number', 'produced:number', 'rolls:int', 'passed:number', 'qc_pending:number', 'in_store:number', 'balance:number', 'start_date:date', 'end_date:date', 'status:status'],
            $this->cardRows(), self::CARD_STATS);
    }

    private function knitCardWiseQualityCheckStatus(): array
    {
        $rows = $this->group($this->cardRows(), ['kc_no', 'buyer', 'order_no'], ['cards', 'assigned', 'produced', 'rolls', 'passed', 'qc_pending', 'in_store', 'balance']);

        return $this->table(['kc_no', 'buyer', 'order_no', 'cards:int', 'assigned:number', 'produced:number', 'rolls:int', 'passed:number', 'qc_pending:number', 'in_store:number', 'balance:number'],
            $rows, self::CARD_STATS);
    }

    /** Each machine's planned kilograms over the window's running days against what it knitted. */
    private function mcWiseKnittingProductionCapacityVsAchievement(): array
    {
        $q = $this->q;
        $byMachine = $this->rolls()->where('qc_status', '!=', 'REJECTED')->groupBy('machine_id');
        $days = $this->rolls()->pluck('prod_date')->unique()->values();
        $plans = KnitCapacityPlan::query()->where('active', true)->get()->groupBy('machine_id');
        $rows = collect($q->machines())->map(function (string $no, string $id) use ($q, $byMachine, $days, $plans): ?array {
            $mine = $byMachine[$id] ?? collect();
            $target = 0.0;
            foreach ($days as $day) {
                foreach (($plans[$id] ?? collect()) as $p) {
                    if ($p->effective_from->toDateString() <= $day && (! $p->effective_to || $p->effective_to->toDateString() >= $day)) {
                        $target += (float) $p->capacity_per_hour * (float) $p->plan_hours;
                    }
                }
            }
            if ($mine->isEmpty() && $target <= 0) {
                return null;
            }
            $achieved = round($mine->sum('net'), 3);

            return ['machine' => $no, 'floor' => $q->floors()[collect(KnittingLabels::machines())[$id]['floor_id'] ?? null] ?? null, 'days' => $mine->pluck('prod_date')->unique()->count(),
                'rolls' => $mine->count(), 'target' => round($target, 3), 'achieved' => $achieved, 'variance' => round($achieved - $target, 3), 'achieved_pct' => self::pct($achieved, $target)];
        })->filter()->sortBy('machine')->values();

        return $this->table(['machine', 'floor', 'days:int', 'rolls:int', 'target:number', 'achieved:number', 'variance:number', 'achieved_pct:pct'], $rows, ['target' => 'target', 'achieved' => 'achieved', 'variance' => 'variance']);
    }

    private function orderAndColorWiseSummery(): array
    {
        return $this->orderAndColorWiseKnittingStatementNew();
    }

    /**
     * Yarn issued to the floor per order (or per programme number) over all
     * time — kilograms, value at the lots' rates, and kilograms from lots
     * with no rate — and what came back on accepted issue returns.
     *
     * @return array{issued: Collection, returned: Collection}
     */
    private function yarnTo(string $by): array
    {
        $col = $by === 'order' ? 'order_id' : 'program_no';
        $issued = YarnIssueLine::query()->join('knit_yarn_issue_req_lines', 'knit_yarn_issue_req_lines.id', '=', 'knit_yarn_issue_lines.req_line_id')
            ->join('knit_yarn_issue_reqs', 'knit_yarn_issue_reqs.id', '=', 'knit_yarn_issue_req_lines.req_id')->join('knit_yarn_lots', 'knit_yarn_lots.id', '=', 'knit_yarn_issue_lines.lot_id')
            ->whereNotNull("knit_yarn_issue_reqs.$col")->groupBy("knit_yarn_issue_reqs.$col")
            ->selectRaw("knit_yarn_issue_reqs.$col as k, sum(knit_yarn_issue_lines.qty) as q, sum(knit_yarn_issue_lines.qty * coalesce(knit_yarn_lots.rate, 0)) as v, "
                .'sum(case when coalesce(knit_yarn_lots.rate, 0) = 0 then knit_yarn_issue_lines.qty else 0 end) as u')
            ->get()->keyBy('k');
        $returned = YarnIssueReturnLine::query()->join('knit_yarn_issue_returns', 'knit_yarn_issue_returns.id', '=', 'knit_yarn_issue_return_lines.return_id')
            ->where('knit_yarn_issue_returns.status', 'ACCEPTED')->join('knit_yarn_issue_reqs', 'knit_yarn_issue_reqs.id', '=', 'knit_yarn_issue_returns.req_id')
            ->whereNotNull("knit_yarn_issue_reqs.$col")->groupBy("knit_yarn_issue_reqs.$col")->selectRaw("knit_yarn_issue_reqs.$col as k, sum(knit_yarn_issue_return_lines.recv_qty) as q")->pluck('q', 'k');

        return ['issued' => $issued, 'returned' => $returned];
    }

    /**
     * Per production order: booked, yarn held for it and issued, what the
     * floor knitted, passed, holds and delivered, what is left, how much of
     * the yarn became fabric, and the booked fabric no yarn was needed for.
     */
    private function yarnToFinishRows(): Collection
    {
        $q = $this->q;
        $ids = $this->rolls()->pluck('prod_order_id')->filter()->merge($q->window(KnitProdOrder::query(), 'created_at')->pluck('id'))->unique();
        $orders = KnitProdOrder::query()->whereIn('id', $ids)->where('status', '!=', 'CANCELLED')->orderBy('po_no')->get();
        $yarn = $this->yarnTo('order');
        $rolls = KnitRoll::query()->join('knit_cards', 'knit_cards.id', '=', 'knit_rolls.card_id')->whereIn('knit_cards.prod_order_id', $orders->pluck('id'))->where('knit_rolls.quality', '!=', 'REJECTED')
            ->get(['knit_rolls.*', 'knit_cards.prod_order_id'])->groupBy('prod_order_id');

        return $orders->map(function (KnitProdOrder $po) use ($q, $yarn, $rolls): array {
            $mine = $rolls[$po->id] ?? collect();
            $net = fn ($r) => (float) $r->weight - (float) $r->deducted;
            $knitted = round($mine->sum($net), 3);
            $issued = round((float) ($yarn['issued'][$po->order_id]->q ?? 0) - (float) ($yarn['returned'][$po->order_id] ?? 0), 3);
            $booked = (float) $po->req_qty;

            return ['buyer' => $q->buyers()[$po->buyer_id] ?? null, 'job' => $q->job($po->order_id), 'order_no' => $q->orderNo($po->order_id), 'kind' => $po->order_kind, 'colour' => $po->colour_name,
                'fabric' => $po->fabric_desc, 'ship_month' => $po->ship_month, 'booked' => round($booked, 3), 'yarn_held' => round((float) $po->yarn_stock_qty, 3), 'yarn_issued' => $issued,
                'knitted' => $knitted, 'passed' => round($mine->where('quality', 'PASSED')->sum($net), 3), 'in_store' => round($mine->whereIn('movement', ['GREY_STORE', 'BATCH_STORE'])->sum($net), 3),
                'delivered' => round($mine->where('movement', 'DELIVERED')->sum($net), 3), 'balance' => round($booked - $knitted, 3), 'yarn_to_fabric' => self::pct($knitted, $issued) ?? 0.0,
                'saving_target' => round((float) $po->saving_target_qty, 3), 'saved' => round(max(0, $booked - $issued), 3), 'status' => $po->status];
        })->values();
    }

    private function yarnToFinishFabricStatus(): array
    {
        return $this->table(['buyer', 'job', 'order_no', 'kind:status', 'colour', 'fabric', 'ship_month', 'booked:number', 'yarn_held:number', 'yarn_issued:number', 'knitted:number', 'passed:number',
            'in_store:number', 'delivered:number', 'balance:number', 'yarn_to_fabric:pct', 'saving_target:number', 'saved:number', 'status:status'], $this->yarnToFinishRows(),
            ['yarn_issued' => 'yarn_issued', 'knitted' => 'knitted', 'saved' => 'saved']);
    }

    private function saveFabricQuantity(): array
    {
        return $this->yarnToFinishFabricStatus();
    }

    /** Sample knit plans in the window, one row per line. */
    private function sampleFabricProgressReport(): array
    {
        $q = $this->q;
        $plans = $q->window(KnitSamplePlan::query(), 'plan_date')->where('status', '!=', 'CANCELLED')->get()->keyBy('id');
        $rows = KnitSamplePlanLine::query()->whereIn('plan_id', $plans->keys())->orderBy('sort_order')->get()->map(function (KnitSamplePlanLine $l) use ($q, $plans): array {
            $p = $plans[$l->plan_id];

            return ['plan_date' => $p->plan_date->toDateString(), 'plan_no' => $p->plan_no, 'kind' => $p->kind, 'req_type' => $p->req_type, 'store' => $q->stores()[$p->store_id] ?? null,
                'buyer' => $q->buyers()[$l->buyer_id ?? $p->buyer_id] ?? null, 'order_no' => $q->orderNo($l->order_id), 'style' => $l->style_text, 'fabric_type' => $q->fabricTypes()[$l->fabric_type_id] ?? null,
                'colour' => $l->colour_name, 'yarn_item' => $q->yarn($l->composition_id, $l->yarn_count_id), 'yarn' => $l->yarn_details, 'finish_dia' => $l->finish_dia, 'gsm' => $l->gsm,
                'machine_dia' => trim(($l->machine_dia ?? '').($l->gauge ? ' x '.$l->gauge : '')) ?: null, 'qty' => self::r3($l->qty), 'uom' => $l->uom, 'submitted' => (bool) $p->issue_req_id,
                'status' => $p->status, 'remarks' => $l->remarks];
        })->values();

        return $this->table(['plan_date:date', 'plan_no', 'kind:status', 'req_type:status', 'store', 'buyer', 'order_no', 'style', 'fabric_type', 'colour', 'yarn_item', 'yarn', 'finish_dia', 'gsm:number',
            'machine_dia', 'qty:number', 'uom', 'submitted:yesno', 'status:status', 'remarks'], $rows, ['planned' => 'qty']);
    }

    /** Out-house fabric per order and colour: received from the knitters, rejected back, kept. */
    private function orderAndColorWiseProductionStatusScOutHouse(): array
    {
        $q = $this->q;
        $docs = $q->window(KnitScGreyReceipt::query(), 'rcv_date')->whereNotIn('status', ['DRAFT', 'CANCELLED'])->get()->keyBy('id');
        $in = KnitScGreyReceiptLine::query()->whereIn('receipt_id', $docs->keys())->get()->map(fn ($l) => ['party' => $q->parties()[$docs[$l->receipt_id]->party_id] ?? null,
            'order_no' => $q->orderNo($docs[$l->receipt_id]->order_id), 'colour' => $l->colour_name, 'fabric' => $l->description, 'rolls' => (int) $l->r_qty, 'received' => self::r3($l->qty), 'rejected' => 0.0]);
        $rets = $q->window(KnitScGreyReturn::query(), 'challan_date')->where('kind', 'OUT_HOUSE')->whereNotIn('status', ['DRAFT', 'CANCELLED'])->get()->keyBy('id');
        $out = KnitScGreyReturnLine::query()->whereIn('return_id', $rets->keys())->get()->map(fn ($l) => ['party' => $q->parties()[$rets[$l->return_id]->party_id] ?? null,
            'order_no' => $q->orderNo($rets[$l->return_id]->order_id), 'colour' => $l->colour_name, 'fabric' => $l->description, 'rolls' => 0, 'received' => 0.0, 'rejected' => self::r3($l->qty)]);
        $rows = $this->group($in->merge($out), ['party', 'order_no', 'colour', 'fabric'], ['rolls', 'received', 'rejected'], fn ($r) => $r + ['net' => round($r['received'] - $r['rejected'], 3)]);

        return $this->table(['party', 'order_no', 'colour', 'fabric', 'rolls:int', 'received:number', 'rejected:number', 'net:number'], $rows, ['received' => 'received', 'rejected' => 'rejected']);
    }

    /**
     * Per programme in the window: yarn issued to it, taken back and used, the
     * kilograms knitted (gross, as weighed), and the loss between them.
     */
    private function programLossRows(): Collection
    {
        $yarn = $this->yarnTo('program');
        $programs = $this->programRows();
        $weighed = KnitRoll::query()->join('knit_cards', 'knit_cards.id', '=', 'knit_rolls.card_id')->whereIn('knit_cards.program_id', $programs->pluck('program_id'))
            ->groupBy('knit_cards.program_id')->selectRaw('knit_cards.program_id as p, sum(knit_rolls.weight) as w, count(*) as n')->get()->keyBy('p');

        return $programs->map(function (array $p) use ($yarn, $weighed): array {
            $issued = (float) ($yarn['issued'][$p['kp_no']]->q ?? 0);
            $back = (float) ($yarn['returned'][$p['kp_no']] ?? 0);
            $used = $issued - $back;
            $gross = round((float) ($weighed[$p['program_id']]->w ?? 0), 3);

            return $p + ['issued' => round($issued, 3), 'returned' => round($back, 3), 'used' => round($used, 3), 'knitted' => $gross, 'roll_count' => (int) ($weighed[$p['program_id']]->n ?? 0),
                'loss' => round($used - $gross, 3), 'loss_pct' => self::pct($used - $gross, $used), 'achv' => self::pct($gross, (float) $p['prog_qty']),
                'yarn_value' => round((float) ($yarn['issued'][$p['kp_no']]->v ?? 0), 2), 'unpriced' => round((float) ($yarn['issued'][$p['kp_no']]->u ?? 0), 3),
                'loss_status' => $used <= 0 ? 'NO_YARN' : (($used - $gross) / $used * 100 > 3 ? 'OVER_3' : 'OK')];
        });
    }

    private function programWiseProcessLossYarnVsFabric(): array
    {
        $rows = $this->programLossRows();
        $used = (float) $rows->sum('used');

        return $this->table(['kp_no', 'program_date:date', 'buyer', 'order_no', 'style', 'colour', 'fabric', 'prog_qty:number', 'issued:number@yarn_kg', 'returned:number@yarn_kg', 'used:number@yarn_kg',
            'knitted:number', 'roll_count:int', 'loss:number', 'loss_pct:pct', 'achv:pct', 'loss_status:status'], $rows,
            ['yarn_used' => 'used', 'knitted' => 'knitted', 'overall_loss' => [$used > 0 ? round(100 * ($used - (float) $rows->sum('knitted')) / $used, 2) : null, 'pct'],
                'over_3' => [$rows->where('loss_status', 'OVER_3')->count(), 'int']]);
    }

    /**
     * Per programme: what its kilograms cost — the yarn issued to it at its
     * lots' rates, and the machines' running cost (oil and broken needles in
     * the window, spread over each machine's kilograms) — in all and per kg.
     */
    private function programWiseKnittingCostPerKg(): array
    {
        $machineKg = $this->rolls()->where('qc_status', '!=', 'REJECTED')->groupBy('machine_id')->map(fn ($g) => (float) $g->sum('net'));
        $machineCost = $this->oilRows()->groupBy('machine_id')->map(fn ($g) => (float) $g->sum('cost'));
        foreach ($this->brokenRows()->groupBy('machine_id') as $id => $g) {
            $machineCost[$id] = (float) ($machineCost[$id] ?? 0) + (float) $g->sum('value');
        }
        $perKg = $machineKg->map(fn ($kg, $id) => $kg > 0 ? (float) ($machineCost[$id] ?? 0) / $kg : 0.0);
        $byProgram = $this->rolls()->where('qc_status', '!=', 'REJECTED')->groupBy('kp_no');
        $rows = $this->programLossRows()->map(function (array $p) use ($byProgram, $perKg): array {
            $mine = $byProgram[$p['kp_no']] ?? collect();
            $kg = (float) $p['knitted'];
            $mc = round((float) $mine->sum(fn ($r) => (float) $r['net'] * (float) ($perKg[$r['machine_id']] ?? 0)), 2);
            $yarn = $p['yarn_value'];

            return ['kp_no' => $p['kp_no'], 'buyer' => $p['buyer'], 'order_no' => $p['order_no'], 'fabric' => $p['fabric'], 'knitted' => $kg, 'cost_yarn' => $yarn, 'cost_mc' => $mc,
                'cost_total' => round($yarn + $mc, 2), 'kg_yarn' => $kg > 0 ? round($yarn / $kg, 2) : 0.0, 'kg_mc' => $kg > 0 ? round($mc / $kg, 2) : 0.0,
                'kg_total' => $kg > 0 ? round(($yarn + $mc) / $kg, 2) : 0.0, 'loss_pct' => $p['loss_pct'] ?? 0.0, 'unpriced' => $p['unpriced']];
        });
        $kg = (float) $rows->sum('knitted');

        return $this->table(['kp_no', 'buyer', 'order_no', 'fabric', 'knitted:number', 'cost_yarn:money@cost', 'cost_mc:money@cost', 'cost_total:money@cost', 'kg_yarn:money@per_kg', 'kg_mc:money@per_kg',
            'kg_total:money@per_kg', 'loss_pct:pct', 'unpriced:number'], $rows,
            ['knitted' => 'knitted', 'total_cost' => 'cost_total', 'avg_cost' => [$kg > 0 ? round((float) $rows->sum('cost_total') / $kg, 2) : 0.0, 'money']]);
    }

    // ── Daily knit output ─────────────────────────────────────────────────

    /**
     * The day's production sheet: one line per knit card and machine, its
     * order and construction, booked / programmed / yarn received, the day
     * (08:30–20:30) and night kilograms and rolls, QC and store standing.
     * Day or night follows the start of the shift a roll was logged under,
     * else the time on the scale. A rejected roll is knitted but not
     * production, so it is left out of both.
     */
    private function outputRows(): Collection
    {
        $q = $this->q;
        $rolls = $this->rolls()->where('qc_status', '!=', 'REJECTED')
            ->when($q->get('floor'), fn ($c, $v) => $c->where('floor', $q->floors()[$v] ?? '?'))->when($q->get('shift'), fn ($c, $v) => $c->where('shift_id', $v))
            ->when($q->get('order'), fn ($c, $v) => $c->where('order_id', $v));
        $times = KnitRoll::query()->whereIn('id', $rolls->pluck('roll_id'))->get(['id', 'roll_at', 'shift_id'])->keyBy('id');
        $starts = DB::table('hr_shifts')->pluck('start_time', 'id');
        $orders = KnitProdOrder::query()->whereIn('id', $rolls->pluck('prod_order_id')->filter())->get()->keyBy('id');
        $cards = KnitCard::query()->whereIn('id', $rolls->pluck('card_id'))->get()->keyBy('id');
        $programs = KnitProgram::query()->whereIn('id', $cards->pluck('program_id')->filter())->get()->keyBy('id');
        $yarn = $this->yarnTo('program');
        $day = function (array $r) use ($times, $starts): bool {
            $t = $times[$r['roll_id']] ?? null;
            $at = $t && $t->shift_id && isset($starts[$t->shift_id]) ? substr((string) $starts[$t->shift_id], 0, 5) : $t?->roll_at->format('H:i');

            return $at !== null && $at >= '08:30' && $at < '20:30';
        };

        return $rolls->groupBy(fn ($r) => $r['card_id'].'|'.$r['machine_id'])->map(function (Collection $g) use ($q, $orders, $cards, $programs, $yarn, $day): array {
            $f = $g->first();
            $po = $orders[$f['prod_order_id']] ?? null;
            $card = $cards[$f['card_id']] ?? null;
            $prog = $card?->program_id ? ($programs[$card->program_id] ?? null) : null;
            [$d, $n] = $g->partition($day);
            $sum = fn (Collection $c) => round((float) $c->sum('net'), 3);

            return ['floor' => $f['floor'], 'machine' => $f['machine'], 'category' => $f['category'], 'buyer' => $f['buyer'], 'job' => $f['job'], 'order_no' => $f['order_no'], 'colour' => $f['colour'],
                'fabric_type' => $q->fabricTypes()[$po?->fabric_type_id] ?? null, 'finish_dia' => $f['finish_dia'], 'gsm' => $f['gsm'], 'machine_dia' => $f['machine_dia'],
                'yarn' => $f['yarn'] ?: $f['fabric'], 'booking_qty' => self::r3($po?->req_qty), 'prog_qty' => self::r3($prog?->qty),
                'yarn_rcvd' => round((float) ($yarn['issued'][$prog?->kp_no ?? '']->q ?? 0), 3),
                'day_qty' => $sum($d), 'day_roll' => $d->count(), 'night_qty' => $sum($n), 'night_roll' => $n->count(), 'total_qty' => $sum($g), 'total_roll' => $g->count(),
                'start_date' => $g->min('prod_date'), 'end_date' => $g->max('prod_date'),
                'qc_qty' => $sum($g->where('qc_status', 'PASSED')), 'qc_roll' => $g->where('qc_status', 'PASSED')->count(),
                'hold_qty' => $sum($g->where('qc_status', 'HOLD')), 'hold_roll' => $g->where('qc_status', 'HOLD')->count(),
                'pending_qty' => $sum($g->where('qc_status', 'PENDING')), 'pending_roll' => $g->where('qc_status', 'PENDING')->count(),
                'store_qty' => $sum($g->whereNotNull('store_receipt')), 'store_roll' => $g->whereNotNull('store_receipt')->count(),
                'store_pending_qty' => $sum($g->whereNull('store_receipt')), 'store_pending_roll' => $g->whereNull('store_receipt')->count()];
        })->values();
    }

    /** @return list<string> */
    private static function outputColumns(): array
    {
        return ['floor', 'machine', 'category', 'buyer', 'job', 'order_no', 'colour', 'fabric_type', 'finish_dia', 'gsm', 'machine_dia', 'yarn', 'booking_qty:number', 'prog_qty:number', 'yarn_rcvd:number',
            'day_qty:number@day', 'day_roll:int@day', 'night_qty:number@night', 'night_roll:int@night', 'total_qty:number@total', 'total_roll:int@total', 'start_date:date', 'end_date:date',
            'qc_qty:number@total_qc', 'qc_roll:int@total_qc', 'hold_qty:number@total_hold', 'hold_roll:int@total_hold', 'pending_qty:number@qc_pending', 'pending_roll:int@qc_pending',
            'store_qty:number@total_str_rcv', 'store_roll:int@total_str_rcv', 'store_pending_qty:number@str_rcv_pending', 'store_pending_roll:int@str_rcv_pending'];
    }

    /** Daily Knit Output Report (its own screen): Company, Prod. Flr, Shift, From/To Date and Job. */
    public function dailyOutput(): array
    {
        return $this->table(self::outputColumns(), $this->outputRows(),
            ['total_qty' => 'total_qty', 'total_roll' => 'total_roll', 'qc_pending' => 'pending_qty', 'str_rcv_pending' => 'store_pending_qty']);
    }

    private function productionReportWithTargetQty(): array
    {
        return $this->table(self::outputColumns(), $this->outputRows(), ['production' => 'total_qty', 'rolls' => 'total_roll', 'qc_pending' => 'pending_qty']);
    }

    /** An order's knitting rate per kg: its approved S/C programme's rate, where it was put out. @return array<string, float> */
    private function knitRates(): array
    {
        return KnitScProgramLine::query()->join('knit_sc_programs', 'knit_sc_programs.id', '=', 'knit_sc_program_lines.program_id')
            ->where('knit_sc_programs.status', 'APPROVED')->whereNotNull('knit_sc_programs.order_id')
            ->groupBy('knit_sc_programs.order_id')->selectRaw('knit_sc_programs.order_id as o, avg(knit_sc_program_lines.rate) as r')->pluck('r', 'o')->map(fn ($r) => (float) $r)->all();
    }
}
