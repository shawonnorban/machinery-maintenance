<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Support\Reports;

use App\Modules\Knitting\Models\KnitCcChallan;
use App\Modules\Knitting\Models\KnitCcChallanLine;
use App\Modules\Knitting\Models\KnitCcProduction;
use App\Modules\Knitting\Models\KnitCcProgram;
use App\Modules\Knitting\Models\KnitCcProgramLine;
use App\Modules\Knitting\Models\KnitCcRequirement;
use App\Modules\Knitting\Models\KnitCcYarnIssue;
use App\Modules\Knitting\Models\KnitCcYarnReq;
use App\Modules\Knitting\Models\KnitCcYarnReqLine;
use App\Modules\Knitting\Support\KnitCollarCuff;
use App\Modules\Knitting\Support\KnitProduction;
use Illuminate\Support\Collection;

/**
 * Flat knit (collar and cuff): requirements against what was knitted here
 * and by outside knitters, pieces in hand and in store, the day's
 * production, challans to QC and the store, yarn indents and programmes.
 */
trait CcReports
{
    /**
     * Requirements (Production Type and Style/Order# applied) with their
     * pieces: required in-house and S/C, produced each way, rejects, sent to
     * the store, still to knit, and in hand on the floor.
     */
    private function ccRequirementRows(): Collection
    {
        $q = $this->q;
        $kind = match ($q->get('prod_type')) { 'BULK', 'YD', 'FLAT_BED_RIB' => 'BULK', 'SAMPLE' => 'SAMPLE', default => null };
        $reqs = KnitCcRequirement::query()->when($kind, fn ($w, $v) => $w->where('req_type', $v))->when($q->get('order'), fn ($w, $v) => $w->where('order_id', $v))->orderBy('req_no')->get();
        $fig = KnitCollarCuff::figures($reqs);
        $categories = collect(KnitProduction::categories())->pluck('label', 'value');

        return $reqs->map(function (KnitCcRequirement $r) use ($q, $fig, $categories): array {
            $f = $fig[$r->id];
            $total = (float) $r->req_in_house + (float) $r->req_sc;
            $made = $f['prod_in'] + $f['prod_sc'];

            return ['req_type' => $r->req_type, 'buyer' => $q->buyers()[$r->buyer_id] ?? null, 'order_no' => $q->orderNo($r->order_id), 'style' => $q->style($r->order_id), 'work_style' => $r->work_style_no,
                'category' => $categories[$r->category] ?? $r->category, 'ship_month' => $r->ship_month, 'item' => $r->item, 'description' => $r->description, 'colour' => $q->colours()[$r->colour_id] ?? null,
                'size' => $r->size_desc, 'gauge' => $r->gauge, 'req_in' => round((float) $r->req_in_house, 3), 'req_sc' => round((float) $r->req_sc, 3), 'req_total' => round($total, 3),
                'prod_in' => $f['prod_in'], 'prod_sc' => $f['prod_sc'], 'reject' => $f['reject'], 'prod_total' => round($made, 3), 'to_store' => $f['delivered'], 'balance' => round($total - $made, 3),
                'in_hand' => $f['open'], 'achieved' => self::pct($made, $total), 'uom' => $r->uom, 'status' => $r->status, 'lines' => 1];
        })->values();
    }

    private function orderWiseFlatKnitRequirement(): array
    {
        return $this->table(['req_type:status', 'buyer', 'order_no', 'style', 'work_style', 'category', 'ship_month', 'item:status', 'description', 'colour', 'size', 'gauge',
            'req_in:number@requirement', 'req_sc:number@requirement', 'req_total:number@requirement', 'prod_in:number@produced', 'prod_sc:number@produced', 'reject:number@produced',
            'to_store:number', 'balance:number', 'achieved:pct', 'uom', 'status:status'], $this->ccRequirementRows(), ['required' => 'req_total', 'in_house' => 'prod_in', 'balance' => 'balance']);
    }

    private const CC_STOCK_STATS = ['in_hand' => 'in_hand', 'in_store' => 'to_store'];

    private function flatKnitStockDetail(): array
    {
        return $this->table(['req_type:status', 'buyer', 'order_no', 'style', 'work_style', 'category', 'ship_month', 'item:status', 'description', 'colour', 'size', 'gauge', 'req_total:number@requirement',
            'prod_total:number@produced', 'prod_in:number@produced', 'prod_sc:number@produced', 'reject:number@produced', 'to_store:number', 'balance:number', 'uom', 'status:status', 'in_hand:number'],
            $this->ccRequirementRows(), self::CC_STOCK_STATS);
    }

    private function ccStockBy(array $keys): array
    {
        $rows = $this->group($this->ccRequirementRows(), $keys, ['lines', 'req_total', 'prod_total', 'prod_in', 'prod_sc', 'reject', 'to_store', 'balance', 'in_hand']);

        return $this->table([...$keys, 'lines:int', 'req_total:number@requirement', 'prod_total:number@produced', 'prod_in:number@produced', 'prod_sc:number@produced', 'reject:number@produced',
            'to_store:number', 'balance:number', 'in_hand:number'], $rows, self::CC_STOCK_STATS);
    }

    private function buyerAcWiseFlatKnitStock(): array
    {
        return $this->ccStockBy(['buyer', 'item']);
    }

    private function orderWiseCollarcuffStock(): array
    {
        return $this->ccStockBy(['order_no', 'item', 'colour']);
    }

    /** The day's collar/cuff entries, in-house and out-house. */
    private function ccProductionRows(): Collection
    {
        $q = $this->q;
        $made = $q->window(KnitCcProduction::query(), 'prod_date')->orderBy('prod_date')->get();
        $reqs = KnitCcRequirement::query()->whereIn('id', $made->pluck('requirement_id'))->get()->keyBy('id');
        $programs = KnitCcProgram::query()->whereIn('id', $made->pluck('program_id')->filter())->pluck('program_no', 'id');

        return $made->map(function (KnitCcProduction $p) use ($q, $reqs, $programs): array {
            $r = $reqs[$p->requirement_id] ?? null;

            return ['prod_date' => $p->prod_date->toDateString(), 'floor' => $q->floors()[$p->floor_id] ?? null, 'machine' => $q->machines()[$p->machine_id] ?? null, 'shift' => $q->shifts()[$p->shift_id] ?? null,
                'operator' => $q->employees()[$p->operator_id] ?? null, 'source' => $p->source, 'program_no' => $programs[$p->program_id] ?? null, 'buyer' => $q->buyers()[$r?->buyer_id] ?? null,
                'order_no' => $q->orderNo($r?->order_id), 'item' => $r?->item, 'description' => $r?->description, 'colour' => $q->colours()[$r?->colour_id] ?? null, 'size' => $r?->size_desc, 'gauge' => $r?->gauge,
                'qty' => round($p->qty, 3), 'reject' => round($p->reject_qty, 3), 'net' => round($p->qty - $p->reject_qty, 3), 'uom' => $r?->uom, 'remarks' => $p->remarks];
        })->values();
    }

    private function dailyCollarcuffProduction(): array
    {
        return $this->table(['prod_date:date', 'floor', 'machine', 'shift', 'operator', 'source:status', 'program_no', 'buyer', 'order_no', 'item:status', 'description', 'colour', 'size', 'gauge',
            'qty:number', 'reject:number', 'net:number', 'uom', 'remarks'], $this->ccProductionRows(), ['produced' => 'qty', 'rejected' => 'reject']);
    }

    private function dateAndMcWiseCollarcuffProduction(): array
    {
        $rows = $this->group($this->ccProductionRows(), ['prod_date', 'machine', 'shift'], ['qty', 'reject', 'net']);

        return $this->table(['prod_date:date', 'machine', 'shift', 'entries:int', 'qty:number', 'reject:number', 'net:number'], $rows, ['produced' => 'qty', 'rejected' => 'reject']);
    }

    /** Collar/cuff challans in the window, one row per line. @param list<string>|null $types */
    private function ccChallanRows(?array $types = null): Collection
    {
        $q = $this->q;
        $docs = $q->window(KnitCcChallan::query(), 'challan_date')->where('status', '!=', 'CANCELLED')->when($types, fn ($w) => $w->whereIn('challan_type', $types))->get()->keyBy('id');
        $reqs = KnitCcRequirement::query()->whereIn('id', KnitCcChallanLine::query()->whereIn('challan_id', $docs->keys())->select('requirement_id'))->get()->keyBy('id');
        $progLines = KnitCcProgramLine::query()->whereIn('id', KnitCcChallanLine::query()->whereIn('challan_id', $docs->keys())->select('program_line_id'))->get()->keyBy('id');
        $programs = KnitCcProgram::query()->whereIn('id', $progLines->pluck('program_id'))->get()->keyBy('id');

        return KnitCcChallanLine::query()->whereIn('challan_id', $docs->keys())->orderBy('sort_order')->get()->map(function (KnitCcChallanLine $l) use ($q, $docs, $reqs, $progLines, $programs): array {
            $d = $docs[$l->challan_id];
            $r = $reqs[$l->requirement_id] ?? null;
            $p = ($pl = $progLines[$l->program_line_id] ?? null) ? ($programs[$pl->program_id] ?? null) : null;
            $got = $d->status === 'RECEIVED' ? (float) $l->qty : 0.0;

            return ['challan_date' => $d->challan_date->toDateString(), 'challan_no' => $d->challan_no, 'movement' => $d->challan_type, 'kind' => $d->kind, 'from_floor' => $q->floors()[$d->from_floor_id] ?? null,
                'party' => $q->parties()[$p?->party_id] ?? null, 'store' => $q->stores()[$d->store_id] ?? null, 'program_no' => $p?->program_no, 'buyer' => $q->buyers()[$r?->buyer_id] ?? null,
                'order_no' => $q->orderNo($l->order_id ?? $r?->order_id), 'item' => $l->item, 'description' => $l->description, 'colour' => $q->colours()[$l->colour_id] ?? null, 'size' => $l->size_desc,
                'gauge' => $r?->gauge, 'qty' => round((float) $l->qty, 3), 'received' => round($got, 3), 'short' => round((float) $l->qty - $got, 3), 'uom' => $l->uom,
                'rcv_date' => $d->rcv_date?->toDateString(), 'status' => $d->status, 'lines' => 1];
        })->values();
    }

    private const CC_CHALLAN_COLS = ['challan_date:date', 'challan_no', 'movement:status', 'kind:status', 'from_floor', 'party', 'store', 'program_no', 'buyer', 'order_no', 'item:status', 'description',
        'colour', 'size', 'gauge', 'qty:number', 'received:number', 'short:number', 'uom', 'rcv_date:date', 'status:status'];

    private const CC_CHALLAN_STATS = ['on_challan' => 'qty', 'received' => 'received', 'short' => 'short'];

    private function dailyCollarcuffTransferToQcAndStore(): array
    {
        return $this->table(self::CC_CHALLAN_COLS, $this->ccChallanRows(), self::CC_CHALLAN_STATS);
    }

    /** Brickland reads its trims receipts from the collar/cuff challans. */
    private function dailyTrimsReceiveStatement(): array
    {
        return $this->dailyCollarcuffTransferToQcAndStore();
    }

    private function sampleGreyFabricCollarCulfReceiveAndDeliveryStatus(): array
    {
        return $this->dailyCollarcuffTransferToQcAndStore();
    }

    private function collarcuffChallanReceiveScOutHouse(): array
    {
        return $this->table(self::CC_CHALLAN_COLS, $this->ccChallanRows(['SC_RECEIVE']), self::CC_CHALLAN_STATS);
    }

    private function storeWiseCollarcuffStock(): array
    {
        $rows = $this->group($this->ccChallanRows(), ['store', 'item', 'colour'], ['lines', 'qty', 'received', 'short']);

        return $this->table(['store', 'item:status', 'colour', 'lines:int', 'qty:number', 'received:number', 'short:number'], $rows, self::CC_CHALLAN_STATS);
    }

    /** Collar/cuff yarn indents (Req. Type: solid or Y/D): wanted, issued, pending per line. */
    private function ccYarnReqRows(): Collection
    {
        $q = $this->q;
        $reqs = $q->window(KnitCcYarnReq::query(), 'req_date')->where('status', '!=', 'CANCELLED')->when($q->get('req_type'), fn ($w, $v) => $w->where('kind', $v))
            ->when($q->get('order'), fn ($w, $v) => $w->where('order_id', $v))->get()->keyBy('id');
        $lines = KnitCcYarnReqLine::query()->whereIn('req_id', $reqs->keys())->orderBy('sort_order')->get();
        $issued = KnitCollarCuff::issued($lines->pluck('id'));

        return $lines->map(function (KnitCcYarnReqLine $l) use ($q, $reqs, $issued): array {
            $r = $reqs[$l->req_id];
            $out = (float) ($issued[$l->id] ?? 0);

            return ['req_date' => $r->req_date->toDateString(), 'req_no' => $r->req_no, 'kind' => $r->kind, 'buyer' => $q->buyers()[$l->buyer_id ?? $r->buyer_id] ?? null,
                'order_no' => $q->orderNo($l->order_id ?? $r->order_id), 'work_style' => $r->work_style_no, 'yarn' => $q->yarn($l->composition_id, $l->yarn_count_id), 'colour' => $q->colours()[$l->colour_id] ?? null,
                'brand' => $l->brand, 'lot_no' => $l->lot_no, 'uom' => $l->uom, 'requested' => self::r3($l->req_qty), 'issued' => round($out, 3), 'pending' => round(max(0, (float) $l->req_qty - $out), 3), 'status' => $r->status];
        })->values();
    }

    private function yarnRequsitionStatementForCollarcuff(): array
    {
        return $this->table(['req_date:date', 'req_no', 'kind:status', 'buyer', 'order_no', 'work_style', 'yarn', 'colour', 'brand', 'lot_no', 'uom', 'requested:number', 'issued:number', 'pending:number', 'status:status'],
            $this->ccYarnReqRows(), ['requested' => 'requested', 'issued' => 'issued', 'pending' => 'pending']);
    }

    private function orderWiseYarnIssueRegisterForCollarCuffProduction(): array
    {
        $rows = $this->group($this->ccYarnReqRows(), ['buyer', 'order_no', 'yarn', 'lot_no'], ['requested', 'issued', 'pending']);

        return $this->table(['buyer', 'order_no', 'yarn', 'lot_no', 'entries:int', 'requested:number', 'issued:number', 'pending:number'], $rows, ['requested' => 'requested', 'issued' => 'issued', 'pending' => 'pending']);
    }

    /** Collar/cuff programmes placed with outside knitters: pieces placed, received back, outstanding. */
    private function flatKnitProductionStatement(): array
    {
        $q = $this->q;
        $programs = $q->window(KnitCcProgram::query(), 'program_date')->where('status', '!=', 'CANCELLED')->when($q->get('order'), fn ($w, $v) => $w->where('order_id', $v))->get()->keyBy('id');
        $lines = KnitCcProgramLine::query()->whereIn('program_id', $programs->keys())->orderBy('sort_order')->get();
        $got = KnitCollarCuff::received($lines->pluck('id'));
        $rows = $lines->map(function (KnitCcProgramLine $l) use ($q, $programs, $got): array {
            $p = $programs[$l->program_id];
            $in = (float) ($got[$l->id] ?? 0);

            return ['program_date' => $p->program_date->toDateString(), 'program_no' => $p->program_no, 'party' => $q->parties()[$p->party_id] ?? null, 'buyer' => $q->buyers()[$p->buyer_id] ?? null,
                'order_no' => $q->orderNo($p->order_id), 'item' => $l->item, 'description' => $l->description, 'colour' => $q->colours()[$l->colour_id] ?? null, 'size' => $l->size_desc, 'gauge' => $l->gauge,
                'cpi' => $p->cpi, 'delivery_date' => $p->delivery_date?->toDateString(), 'qty' => round((float) $l->qty, 3), 'received' => round($in, 3), 'balance' => round((float) $l->qty - $in, 3),
                'rate' => round((float) $l->rate, 2), 'value' => round((float) $l->amount, 2), 'uom' => $l->uom, 'status' => $p->status];
        })->values();

        return $this->table(['program_date:date', 'program_no', 'party', 'buyer', 'order_no', 'item:status', 'description', 'colour', 'size', 'gauge', 'cpi', 'delivery_date:date', 'qty:number', 'received:number',
            'balance:number', 'rate:money', 'value:money', 'uom', 'status:status'], $rows, ['placed' => 'qty', 'received' => 'received', 'outstanding' => 'balance']);
    }
}
