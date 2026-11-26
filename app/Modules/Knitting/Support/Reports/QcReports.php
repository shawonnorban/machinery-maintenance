<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Support\Reports;

use App\Modules\Knitting\Models\KnitCard;
use App\Modules\Knitting\Models\KnitGreyReceipt;
use App\Modules\Knitting\Models\KnitGreyReceiptRoll;
use App\Modules\Knitting\Models\KnitProdOrder;
use App\Modules\Knitting\Models\KnitRoll;
use App\Modules\Knitting\Models\KnitRollInspection;
use App\Modules\Knitting\Models\KnitRollInspectionLine;
use App\Modules\Knitting\Models\KnitScGreyReceipt;
use App\Modules\Knitting\Models\KnitScGreyReceiptLine;
use App\Modules\Knitting\Models\KnitScGreyReturn;
use App\Modules\Knitting\Models\KnitScGreyReturnLine;
use Illuminate\Support\Collection;

/**
 * Grey quality: the four-point inspection sheet by roll, shift, order and
 * month, rolls nobody has inspected, the floor's challans to the grey store,
 * out-house fabric received against rejected, and QC deductions.
 */
trait QcReports
{
    /**
     * Inspections in the window, one row per roll, with the QC boxes applied:
     * Style/Order#, Color, QC Status, QC user, Knit Card#, Yarn Item's count,
     * QC Grade, Shift, Production Type, Fabric Type and Roll No.
     */
    private function inspectionRows(): Collection
    {
        $q = $this->q;
        [, $count] = $q->yarnItem();
        $count ??= $q->get('count');
        $docs = $q->window(KnitRollInspection::query(), 'inspected_on')->when($q->get('qc_status'), fn ($w, $v) => $w->where('status', $v))
            ->when($q->get('qc_user'), fn ($w, $v) => $w->where('qc_by', $v))->when($q->get('grade'), fn ($w, $v) => $w->where('grade', $v))
            ->when($q->get('shift'), fn ($w, $v) => $w->where('shift_id', $v))->orderBy('inspected_on')->orderBy('qci_no')->get();
        $rolls = KnitRoll::query()->whereIn('id', $docs->pluck('roll_id'))->get()->keyBy('id');
        $cards = KnitCard::query()->whereIn('id', $rolls->pluck('card_id'))->get()->keyBy('id');
        $orders = KnitProdOrder::query()->whereIn('id', $cards->pluck('prod_order_id'))->get()->keyBy('id');
        $defects = KnitRollInspectionLine::query()->whereIn('inspection_id', $docs->pluck('id'))->orderBy('sort_order')->get()->groupBy('inspection_id');
        $countName = $count ? ($q->yarnNames()['counts'][$count] ?? null) : null;

        return $docs->map(function (KnitRollInspection $i) use ($q, $rolls, $cards, $orders, $defects, $countName): ?array {
            $r = $rolls[$i->roll_id] ?? null;
            $c = $r ? ($cards[$r->card_id] ?? null) : null;
            $po = $c ? ($orders[$c->prod_order_id] ?? null) : null;
            $o = $q->order($po?->order_id);
            if (($q->get('order') && $po?->order_id !== $q->get('order')) || ($q->get('kc_no') && stripos((string) $c?->kc_no, $q->get('kc_no')) === false)
                || ($q->get('roll_no') && (string) $r?->roll_no !== $q->get('roll_no')) || ($q->get('prod_type') && $po?->order_kind !== $q->get('prod_type'))
                || ($q->get('fabric_type') && $po?->fabric_type_id !== $q->get('fabric_type')) || ($countName && stripos((string) $c?->yarn_details, $countName) === false) || ($q->get('colour') && $po?->colour_id !== $q->get('colour') && $c?->colour_name !== ($q->colours()[$q->get('colour')] ?? null))) {
                return null;
            }
            $kg = (float) ($r?->weight ?? 0);

            return ['inspected_on' => $i->inspected_on->toDateString(), 'month' => $i->inspected_on->format('Y-m'), 'qci_no' => $i->qci_no, 'shift' => $q->shifts()[$i->shift_id] ?? null,
                'qc_user' => $q->employees()[$i->qc_by] ?? null, 'floor' => $q->floors()[$c?->floor_id] ?? null, 'machine' => $q->machines()[$r?->machine_id] ?? null, 'buyer' => $q->buyers()[$po?->buyer_id] ?? null,
                'order_no' => $o ? trim(($o->work_style_no ?: $o->style_no ?: $o->job_no).' / '.$o->order_no, ' /') : null, 'colour' => $c?->colour_name, 'fabric' => $po?->fabric_desc,
                'fabric_type' => $q->fabricTypes()[$po?->fabric_type_id] ?? null, 'finish_dia' => $c?->finish_dia, 'kc_no' => $c?->kc_no, 'roll_no' => $r?->roll_no, 'rolls' => 1,
                'roll_qty' => round($kg, 3), 'deduct' => self::r3($i->deduct_weight), 'points' => (int) $i->total_points, 'point_per_100' => round((float) $i->grade_point, 2), 'grade' => $i->grade,
                'status' => $i->status, 'defects' => ($defects[$i->id] ?? collect())->map(fn ($d) => "{$d->defect} ({$d->count}×{$d->points_each})")->implode(', ') ?: null, 'comments' => $i->comments,
                'deduct_pct' => self::pct((float) $i->deduct_weight, $kg)];
        })->filter()->values();
    }

    private const QC_STATS = ['inspected' => 'roll_qty', 'deducted' => 'deduct', 'points' => 'points'];

    private function greyFabricRollInspection(): array
    {
        return $this->table(['inspected_on:date', 'qci_no', 'shift', 'qc_user', 'floor', 'machine', 'buyer', 'order_no', 'colour', 'fabric', 'fabric_type', 'finish_dia', 'kc_no', 'roll_no',
            'roll_qty:number', 'deduct:number', 'points:int', 'point_per_100:number', 'grade', 'status:status', 'defects', 'comments'], $this->inspectionRows(), self::QC_STATS);
    }

    /** Inspection summed over `keys`: points per 100 kg of what was left after deduction, and the deduction's share. */
    private function inspectionBy(array $spec): array
    {
        $keys = array_map(fn ($k) => explode(':', $k)[0], $spec);
        $rows = $this->group($this->inspectionRows(), $keys, ['rolls', 'roll_qty', 'deduct', 'points'], fn (array $r) => $r + [
            'points' => (int) $r['points'], 'point_per_100' => $r['roll_qty'] - $r['deduct'] > 0 ? round($r['points'] * 100 / ($r['roll_qty'] - $r['deduct']), 2) : null,
            'deduct_pct' => self::pct($r['deduct'], $r['roll_qty']),
        ]);

        return $this->table([...$spec, 'rolls:int', 'roll_qty:number', 'deduct:number', 'points:int', 'point_per_100:number', 'deduct_pct:pct'], $rows, self::QC_STATS);
    }

    private function shiftWiseGreyFabricRollInspection(): array
    {
        return $this->inspectionBy(['inspected_on:date', 'shift', 'qc_user']);
    }

    private function orderAndColorWiseInspectionReport(): array
    {
        return $this->inspectionBy(['buyer', 'order_no', 'colour']);
    }

    /** Rolls knitted in the window that no inspection has looked at yet. */
    private function rollListWithoutGreyQc(): array
    {
        $inspected = KnitRollInspection::query()->whereIn('roll_id', $this->rolls()->pluck('roll_id'))->pluck('roll_id')->flip();
        $rows = $this->rolls()->filter(fn ($r) => ! isset($inspected[$r['roll_id']]))->values();

        return $this->table(['prod_date:date', 'kc_no', 'roll_no', 'floor', 'machine', 'shift', 'buyer', 'order_no', 'colour', 'fabric', 'net:number', 'rolls:int', 'where:status'], $rows,
            ['waiting' => 'rolls', 'waiting_kg' => 'net']);
    }

    /**
     * Rolls by day (or month): knitted, passed, on hold, rejected, waiting for
     * QC and deducted, the kilograms by grade, and the share that passed.
     */
    private function qcStatus(string $by): array
    {
        $rows = $this->rolls()->groupBy(fn ($r) => $by === 'month' ? substr($r['prod_date'], 0, 7) : $r['prod_date'])->map(function (Collection $g, string $key): array {
            $kg = round((float) $g->sum('net'), 3);
            $passed = round((float) $g->where('qc_status', 'PASSED')->sum('net'), 3);
            $grade = fn (string $x) => round((float) $g->where('grade', $x)->sum('net'), 3);

            return ['period' => $key, 'rolls' => $g->count(), 'produced' => $kg, 'passed' => $passed, 'hold' => round((float) $g->where('qc_status', 'HOLD')->sum('net'), 3),
                'rejected' => round((float) $g->where('qc_status', 'REJECTED')->sum('net'), 3), 'qc_pending' => round((float) $g->where('qc_status', 'PENDING')->sum('net'), 3),
                'deducted' => round((float) $g->sum('deduct'), 3), 'grade_a' => $grade('A'), 'grade_b' => $grade('B'), 'grade_c' => $grade('C'), 'grade_d' => $grade('D'), 'pass_pct' => self::pct($passed, $kg)];
        })->sortKeys()->values();

        return $this->table([($by === 'month' ? 'period' : 'period:date'), 'rolls:int', 'produced:number', 'passed:number', 'hold:number', 'rejected:number', 'qc_pending:number', 'deducted:number',
            'grade_a:number', 'grade_b:number', 'grade_c:number', 'grade_d:number', 'pass_pct:pct'], $rows, ['produced' => 'produced', 'passed' => 'passed', 'pending' => 'qc_pending']);
    }

    private function dateWiseGreyFabricQcStatus(): array
    {
        return $this->qcStatus('day');
    }

    private function finishingProductionReport4a(): array
    {
        return $this->qcStatus('day');
    }

    private function monthlyGreyQcSummaryReport(): array
    {
        return $this->qcStatus('month');
    }

    private function monthlyQualitySummaryReportBeforeDyeing(): array
    {
        return $this->qcStatus('month');
    }

    /** The floor's grey receipt notes into the store, one row per note (or per roll). */
    private function storeReceiptRows(): Collection
    {
        $q = $this->q;
        $notes = $q->window(KnitGreyReceipt::query(), 'receive_date')->where('status', '!=', 'CANCELLED')->get()->keyBy('id');
        $rolls = KnitGreyReceiptRoll::query()->whereIn('receipt_id', $notes->keys())->get();
        $made = KnitRoll::query()->whereIn('id', $rolls->pluck('roll_id'))->get()->keyBy('id');
        $cards = KnitCard::query()->whereIn('id', $made->pluck('card_id'))->get()->keyBy('id');
        $orders = KnitProdOrder::query()->whereIn('id', $cards->pluck('prod_order_id'))->get()->keyBy('id');

        return $rolls->map(function (KnitGreyReceiptRoll $x) use ($q, $notes, $made, $cards, $orders): array {
            $n = $notes[$x->receipt_id];
            $r = $made[$x->roll_id] ?? null;
            $c = $r ? ($cards[$r->card_id] ?? null) : null;
            $po = $c ? ($orders[$c->prod_order_id] ?? null) : null;

            return ['receive_date' => $n->receive_date?->toDateString(), 'note_no' => $n->note_no, 'store' => $q->stores()[$n->store_id] ?? null, 'kc_no' => $c?->kc_no, 'roll_no' => $r?->roll_no,
                'buyer' => $q->buyers()[$po?->buyer_id] ?? null, 'order_no' => $q->orderNo($po?->order_id), 'colour' => $c?->colour_name, 'fabric' => $po?->fabric_desc, 'rolls' => 1,
                'received' => self::r3($x->weight), 'status' => $n->status];
        })->sortBy([['receive_date', 'asc'], ['note_no', 'asc'], ['roll_no', 'asc']])->values();
    }

    private function greyFabricDeliveryChallanToStore(): array
    {
        $rows = $this->group($this->storeReceiptRows(), ['receive_date', 'note_no', 'store', 'buyer', 'order_no', 'colour', 'fabric', 'status'], ['rolls', 'received']);

        return $this->table(['receive_date:date', 'note_no', 'store', 'buyer', 'order_no', 'colour', 'fabric', 'rolls:int', 'received:number', 'status:status'], $rows, ['rolls' => 'rolls', 'received' => 'received']);
    }

    private function rollWiseGreyFabricDeliveryChallanToStore(): array
    {
        return $this->table(['receive_date:date', 'note_no', 'store', 'kc_no', 'roll_no', 'buyer', 'order_no', 'colour', 'fabric', 'received:number', 'status:status'], $this->storeReceiptRows(),
            ['rolls' => 'rolls', 'received' => 'received']);
    }

    /** Fabric from outside knitters by month and party: received, and rejected back to them. */
    private function monthlyOutSideQualitySummary(): array
    {
        $q = $this->q;
        $docs = $q->window(KnitScGreyReceipt::query(), 'rcv_date')->whereNotIn('status', ['DRAFT', 'CANCELLED'])->get()->keyBy('id');
        $in = KnitScGreyReceiptLine::query()->whereIn('receipt_id', $docs->keys())->get()->map(fn ($l) => ['month' => $docs[$l->receipt_id]->rcv_date->format('Y-m'),
            'party' => $q->parties()[$docs[$l->receipt_id]->party_id] ?? null, 'received' => self::r3($l->qty), 'rejected' => 0.0]);
        $rets = $q->window(KnitScGreyReturn::query(), 'challan_date')->where('kind', 'OUT_HOUSE')->whereNotIn('status', ['DRAFT', 'CANCELLED'])->get()->keyBy('id');
        $out = KnitScGreyReturnLine::query()->whereIn('return_id', $rets->keys())->get()->map(fn ($l) => ['month' => $rets[$l->return_id]->challan_date->format('Y-m'),
            'party' => $q->parties()[$rets[$l->return_id]->party_id] ?? null, 'received' => 0.0, 'rejected' => self::r3($l->qty)]);
        $rows = $this->group($in->merge($out), ['month', 'party'], ['received', 'rejected'], fn ($r) => $r + ['reject_pct' => self::pct($r['rejected'], $r['received'])])->sortBy('month')->values();

        return $this->table(['month', 'party', 'entries:int', 'received:number', 'rejected:number', 'reject_pct:pct'], $rows, ['received' => 'received', 'rejected' => 'rejected']);
    }

    /**
     * Fabric QC took off rolls (deductions), day by day, and how much of it a
     * return challan has carried back. The mill keeps no return challan for
     * QC deductions yet, so every kilogram stands as challan pending.
     */
    private function dailyReturnChallanForGreyQcDeductionFabric(): array
    {
        $rows = $this->inspectionRows()->where('deduct', '>', 0)->map(fn ($r) => $r + ['on_challan' => 0.0, 'pending' => $r['deduct'], 'challan_no' => null])->values();

        return $this->table(['inspected_on:date', 'qci_no', 'shift', 'qc_user', 'buyer', 'order_no', 'colour', 'kc_no', 'roll_no', 'grade', 'defects', 'deduct:number', 'on_challan:number', 'pending:number', 'challan_no'],
            $rows, ['deducted' => 'deduct', 'no_challan' => 'pending']);
    }
}
