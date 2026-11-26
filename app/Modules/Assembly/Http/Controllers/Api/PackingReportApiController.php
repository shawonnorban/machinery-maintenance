<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Http\Controllers\Api;

use App\Modules\Assembly\Models\AsmPackingBatch;
use App\Modules\Assembly\Models\AsmPackingCarton;
use App\Modules\Assembly\Models\AsmPackingCartonLine;
use App\Modules\Assembly\Models\AsmPackingIn;
use App\Modules\Assembly\Models\AsmPackingLabel;
use App\Modules\Assembly\Models\AsmPackingStep;
use App\Modules\Assembly\Models\AsmQualityIn;
use App\Modules\Assembly\Models\AsmQualityOut;
use App\Modules\Assembly\Support\PackingBook;
use App\Modules\Cutting\Models\CutBundle;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Assembly Floor → Packing Reports (AnnotechERP 20/04), Brickland's eleven:
 * Daily Packing, Packing In, Packing Out, Buyer-, PO- and Style-wise Packing,
 * Barcode (poly labels), Carton, Assortment, Pending Packing (QC Pass not
 * packed in) and Completed Packing (every process done) — each for a date
 * range, narrowed by buyer, PO or style, with its columns and totals.
 */
class PackingReportApiController extends ApiController
{
    public const KINDS = ['daily', 'in', 'out', 'buyer', 'po', 'style', 'barcode', 'carton', 'assortment', 'pending', 'completed'];

    private const MAX_RANGE_DAYS = 366;

    private ?PackingBook $book = null;

    public function run(Request $request): JsonResponse
    {
        $this->allow('assembly.packing.view');
        $q = $request->validate(['kind' => ['required', Rule::in(self::KINDS)], 'from' => ['required', 'date'], 'to' => ['required', 'date'], 'q' => ['nullable', 'string', 'max:60']]);
        $from = CarbonImmutable::parse($q['from'])->startOfDay();
        $to = CarbonImmutable::parse($q['to'])->startOfDay();
        if ($to < $from) {
            throw ValidationException::withMessages(['to' => __('assembly.v.range_order')]);
        }
        if ($from->diffInDays($to) + 1 > self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages(['to' => __('assembly.v.range_max', ['days' => self::MAX_RANGE_DAYS])]);
        }
        [$f, $t] = [$from->toDateString(), $to->toDateString()];
        $needle = isset($q['q']) ? mb_strtolower(trim($q['q'])) : '';

        [$columns, $rows] = $this->{'report'.ucfirst($q['kind'])}($f, $t);
        if ($needle !== '') {
            $rows = array_values(array_filter($rows, fn ($r) => str_contains(mb_strtolower(implode(' ', [$r['buyer'] ?? '', $r['po_no'] ?? '', $r['style'] ?? '', $r['order_no'] ?? ''])), $needle)));
        }
        $totals = [];
        foreach ($columns as $c) {
            if ($c['numeric']) {
                $totals[$c['key']] = round(array_sum(array_map(fn ($r) => (float) ($r[$c['key']] ?? 0), $rows)), 2);
            }
        }

        return ApiResponse::ok(['kind' => $q['kind'], 'from' => $f, 'to' => $t, 'columns' => $columns, 'rows' => $rows, 'totals' => $totals]);
    }

    private function reportDaily(string $f, string $t): array
    {
        $ins = AsmPackingIn::query()->whereBetween('in_date', [$f, $t])->get();
        $outs = AsmPackingBatch::query()->where('status', 'PACKED_OUT')->whereBetween('out_date', [$f, $t])->get();
        $sealed = AsmPackingCarton::query()->whereNotNull('sealed_at')->get()->filter(fn ($c) => ($d = PackingBook::day($c->sealed_at)) >= $f && $d <= $t);
        $days = collect($ins->map(fn ($i) => $i->in_date->toDateString()))->merge($outs->map(fn ($b) => $b->out_date->toDateString()))->merge($sealed->map(fn ($c) => PackingBook::day($c->sealed_at)))->unique()->sortDesc();
        $rows = $days->map(function ($d) use ($ins, $outs, $sealed) {
            $dIns = $ins->filter(fn ($i) => $i->in_date->toDateString() === $d);
            $dOuts = $outs->filter(fn ($b) => $b->out_date->toDateString() === $d);
            $dSealed = $sealed->filter(fn ($c) => PackingBook::day($c->sealed_at) === $d);

            return ['date' => $d, 'bundles' => $dIns->pluck('bundle_id')->unique()->count(), 'packing_in' => (int) $dIns->sum('qty'), 'batches_out' => $dOuts->count(),
                'packed_out' => (int) $dOuts->sum('final_packed_qty'), 'cartons_sealed' => $dSealed->count(), 'sealed_qty' => (int) $dSealed->sum('qty')];
        })->values()->all();

        return [$this->cols(['date', 'bundles#', 'packing_in#', 'batches_out#', 'packed_out#', 'cartons_sealed#', 'sealed_qty#']), $rows];
    }

    private function reportIn(string $f, string $t): array
    {
        $ins = AsmPackingIn::query()->whereBetween('in_date', [$f, $t])->orderByDesc('created_at')->orderByDesc('id')->get();
        $batches = AsmPackingBatch::query()->whereIn('id', $ins->pluck('batch_id'))->get()->keyBy('id');
        $cards = CutBundle::query()->whereIn('id', $ins->pluck('bundle_id'))->get()->keyBy('id');
        $rows = $ins->map(function ($i) use ($batches, $cards) {
            $b = $batches[$i->batch_id];

            return $this->base($b) + ['date' => $i->in_date->toDateString(), 'time' => PackingBook::at($i->created_at), 'bundle_no' => $cards[$i->bundle_id]->bundle_no,
                'size' => $i->size, 'qty' => $i->qty, 'location' => $this->book()->lines()->get($b->line_id)?->label, 'by' => $this->book()->userName($i->created_by)];
        })->values()->all();

        return [$this->cols(['time', 'packing_no', 'bundle_no', 'buyer', 'po_no', 'style', 'colour', 'size', 'qty#', 'location', 'by']), $rows];
    }

    private function reportOut(string $f, string $t): array
    {
        $batches = AsmPackingBatch::query()->where('status', 'PACKED_OUT')->whereBetween('out_date', [$f, $t])->orderByDesc('packed_out_at')->get();
        $rows = collect($this->book()->presentAll($batches))->map(fn ($p) => $this->pick($p) + [
            'date' => $p['packed_out_at'], 'packing_in' => $p['packing_in_qty'], 'final' => $p['final_packed_qty'], 'balance' => $p['packing_in_qty'] - (int) $p['final_packed_qty'],
            'cartons' => $p['cartons'], 'gross' => $p['gross_weight'], 'net' => $p['net_weight'], 'by' => $p['packed_out_by'],
        ])->all();

        return [$this->cols(['date', 'packing_no', 'buyer', 'po_no', 'style', 'colour', 'packing_in#', 'final#', 'balance#', 'cartons#', 'gross#', 'net#', 'by']), $rows];
    }

    private function reportBuyer(string $f, string $t): array
    {
        return $this->grouped($f, $t, fn ($p) => $p['buyer'] ?? '—', ['buyer']);
    }

    private function reportPo(string $f, string $t): array
    {
        return $this->grouped($f, $t, fn ($p) => ($p['order_no'] ?? '').'|'.($p['po_no'] ?? ''), ['buyer', 'order_no', 'po_no'], true);
    }

    private function reportStyle(string $f, string $t): array
    {
        return $this->grouped($f, $t, fn ($p) => ($p['buyer'] ?? '').'|'.($p['style'] ?? ''), ['buyer', 'style']);
    }

    private function reportBarcode(string $f, string $t): array
    {
        $labels = AsmPackingLabel::query()->whereNotNull('printed_at')->orderBy('barcode')->get()->filter(fn ($l) => ($d = PackingBook::day($l->printed_at)) >= $f && $d <= $t);
        $batches = AsmPackingBatch::query()->whereIn('id', $labels->pluck('batch_id'))->get()->keyBy('id');
        $rows = $labels->map(fn ($l) => $this->base($batches[$l->batch_id]) + ['barcode' => $l->barcode, 'size' => $l->size, 'qty' => $l->qty, 'printed' => $l->print_count,
            'printed_at' => PackingBook::at($l->printed_at), 'verified_at' => PackingBook::at($l->verified_at) ?? '—'])->values()->all();

        return [$this->cols(['packing_no', 'buyer', 'po_no', 'colour', 'size', 'barcode', 'qty#', 'printed#', 'printed_at', 'verified_at']), $rows];
    }

    private function reportCarton(string $f, string $t): array
    {
        $cartons = AsmPackingCarton::query()->orderBy('carton_no')->get()->filter(fn ($c) => ($d = PackingBook::day($c->created_at)) >= $f && $d <= $t);
        $batches = AsmPackingBatch::query()->whereIn('id', $cartons->pluck('batch_id'))->where('status', '!=', 'CANCELLED')->get()->keyBy('id');
        $lines = AsmPackingCartonLine::query()->whereIn('carton_id', $cartons->pluck('id'))->get()->groupBy('carton_id');
        $rows = $cartons->filter(fn ($c) => isset($batches[$c->batch_id]))->map(fn ($c) => $this->base($batches[$c->batch_id]) + [
            'carton_no' => $c->carton_no, 'barcode' => $c->barcode(), 'sizes' => $lines->get($c->id, collect())->map(fn ($l) => $l->size.':'.$l->qty)->implode(' '),
            'qty' => $c->qty, 'gross' => $c->gross_weight !== null ? (float) $c->gross_weight : 0, 'net' => $c->net_weight !== null ? (float) $c->net_weight : 0,
            'status' => $c->status, 'sealed_at' => PackingBook::at($c->sealed_at) ?? '—',
        ])->values()->all();

        return [$this->cols(['carton_no', 'barcode', 'packing_no', 'buyer', 'po_no', 'colour', 'sizes', 'qty#', 'gross#', 'net#', 'status', 'sealed_at']), $rows];
    }

    private function reportAssortment(string $f, string $t): array
    {
        $done = AsmPackingStep::query()->where('step', 'ASSORTMENT')->where('status', 'COMPLETED')->get()->filter(fn ($s) => ($d = PackingBook::day($s->completed_at)) >= $f && $d <= $t)->keyBy('batch_id');
        $batches = AsmPackingBatch::query()->whereIn('id', $done->keys())->where('status', '!=', 'CANCELLED')->orderBy('packing_no')->get();
        $rows = collect($this->book()->presentAll($batches))->map(fn ($p) => $this->pick($p) + [
            'ratio' => $p['assort_ratio'] ?? '—', 'sizes' => collect($p['sizes'])->map(fn ($s) => $s['size'].':'.$s['qty'])->implode(' '),
            'assorted' => (int) $done[$p['id']]->qty, 'cartons' => $p['cartons'], 'date' => PackingBook::at($done[$p['id']]->completed_at),
        ])->all();

        return [$this->cols(['date', 'packing_no', 'buyer', 'po_no', 'style', 'colour', 'ratio', 'sizes', 'assorted#', 'cartons#']), $rows];
    }

    private function reportPending(string $f, string $t): array
    {
        $outs = AsmQualityOut::query()->where('pass_qty', '>', 0)->whereBetween('out_date', [$f, $t])->orderBy('out_date')->orderBy('created_at')->orderBy('id')->get();
        $packed = AsmPackingIn::query()->whereIn('quality_out_id', $outs->pluck('id'))->selectRaw('quality_out_id, sum(qty) as qty')->groupBy('quality_out_id')->pluck('qty', 'quality_out_id');
        $qins = AsmQualityIn::query()->whereIn('id', $outs->pluck('quality_in_id'))->get()->keyBy('id');
        $cards = CutBundle::query()->whereIn('id', $qins->pluck('bundle_id'))->get()->keyBy('id');
        $rows = $outs->filter(fn ($o) => $o->pass_qty > (int) ($packed[$o->id] ?? 0))->map(function ($o) use ($packed, $qins, $cards) {
            $card = $cards[$qins[$o->quality_in_id]->bundle_id];

            return ['date' => $o->out_date->toDateString(), 'bundle_no' => $card->bundle_no, 'buyer' => $this->book()->buyer($card->buyer_id)[0], 'order_no' => $this->book()->order($card->order_id)?->order_no,
                'po_no' => $card->po_no, 'style' => $card->style, 'colour' => $card->colour, 'size' => $card->size, 'qc_pass' => $o->pass_qty,
                'packed' => (int) ($packed[$o->id] ?? 0), 'balance' => $o->pass_qty - (int) ($packed[$o->id] ?? 0)];
        })->values()->all();

        return [$this->cols(['date', 'bundle_no', 'buyer', 'po_no', 'style', 'colour', 'size', 'qc_pass#', 'packed#', 'balance#']), $rows];
    }

    private function reportCompleted(string $f, string $t): array
    {
        $done = AsmPackingStep::query()->where('step', 'SEALING')->where('status', 'COMPLETED')->get()->filter(fn ($s) => ($d = PackingBook::day($s->completed_at)) >= $f && $d <= $t)->keyBy('batch_id');
        $batches = AsmPackingBatch::query()->whereIn('id', $done->keys())->where('status', '!=', 'CANCELLED')->orderBy('packing_no')->get();
        $rows = collect($this->book()->presentAll($batches))->map(fn ($p) => $this->pick($p) + [
            'date' => PackingBook::at($done[$p['id']]->completed_at), 'packing_in' => $p['packing_in_qty'], 'sealed_qty' => $p['sealed_qty'], 'cartons' => $p['cartons'],
            'final' => (int) $p['final_packed_qty'], 'packed_out_at' => $p['packed_out_at'] ?? '—',
        ])->all();

        return [$this->cols(['date', 'packing_no', 'buyer', 'po_no', 'style', 'colour', 'packing_in#', 'sealed_qty#', 'cartons#', 'final#', 'packed_out_at']), $rows];
    }

    /** Buyer-, PO- or style-wise: what was packed in over the range and what was packed out, grouped. */
    private function grouped(string $f, string $t, callable $key, array $head, bool $withOrderQty = false): array
    {
        $ins = AsmPackingIn::query()->whereBetween('in_date', [$f, $t])->get();
        $outs = AsmPackingBatch::query()->where('status', 'PACKED_OUT')->whereBetween('out_date', [$f, $t])->get();
        $batches = AsmPackingBatch::query()->whereIn('id', $ins->pluck('batch_id')->merge($outs->pluck('id'))->unique())->where('status', '!=', 'CANCELLED')->get()->keyBy('id');
        $present = collect($this->book()->presentAll($batches->values()))->keyBy('id');
        $inBy = $ins->groupBy('batch_id')->map(fn ($g) => (int) $g->sum('qty'));
        $rows = $present->groupBy(fn ($p) => $key($p))->map(function (Collection $g) use ($head, $inBy, $outs, $withOrderQty, $batches) {
            $first = $g->first();
            $row = array_intersect_key($first, array_flip($head));
            $ids = $g->pluck('id');
            if ($withOrderQty) {
                $row['order_qty'] = (int) $g->unique(fn ($p) => $p['colour'])->sum(fn ($p) => (int) $this->book()->orderQty($batches[$p['id']]->order_id, $p['colour']));
            }

            return $row + ['batches' => $g->count(), 'packing_in' => (int) $ids->sum(fn ($id) => $inBy[$id] ?? 0),
                'packed_out' => (int) $outs->whereIn('id', $ids)->sum('final_packed_qty'), 'cartons' => (int) $g->sum('cartons')];
        })->sortBy(fn ($r) => implode('|', array_map(fn ($h) => $r[$h] ?? '', $head)))->values()->all();

        return [$this->cols([...$head, ...($withOrderQty ? ['order_qty#'] : []), 'batches#', 'packing_in#', 'packed_out#', 'cartons#']), $rows];
    }

    /** @return list<array{key: string, numeric: bool}> */
    private function cols(array $keys): array
    {
        return array_map(fn ($k) => ['key' => rtrim($k, '#'), 'numeric' => str_ends_with($k, '#')], $keys);
    }

    private function base(AsmPackingBatch $b): array
    {
        return ['packing_no' => $b->packing_no, 'buyer' => $this->book()->buyer($b->buyer_id)[0], 'order_no' => $this->book()->order($b->order_id)?->order_no,
            'po_no' => $b->po_no, 'style' => $b->style, 'colour' => $b->colour];
    }

    private function pick(array $p): array
    {
        return array_intersect_key($p, array_flip(['packing_no', 'buyer', 'order_no', 'po_no', 'style', 'colour']));
    }

    private function book(): PackingBook
    {
        return $this->book ??= new PackingBook($this->caller()->companyId);
    }
}
