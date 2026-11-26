<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Support;

use App\Modules\Assembly\Models\AsmFgLedger;
use App\Modules\Assembly\Models\AsmPackingBatch;
use App\Modules\Assembly\Models\AsmPackingCarton;
use App\Modules\Assembly\Models\AsmPackingCartonLine;
use App\Modules\Assembly\Models\AsmPackingIn;
use App\Modules\Assembly\Models\AsmPackingLabel;
use App\Modules\Assembly\Models\AsmPackingStep;
use App\Modules\Assembly\Models\AsmQualityIn;
use App\Modules\Assembly\Models\AsmQualityOut;
use App\Modules\Cutting\Models\CutBundle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Assembly Floor → Packing (AnnotechERP 20/04) and its Packing Process
 * (20/05), what the desks, Packing Out, the dashboard and the reports share:
 * the packing locations (Company Management's FINISHING lines filed under a
 * section and a floor — Brickland packs in its Finishing section), a batch
 * as the screens show it (its order, sizes, seven processes, labels and
 * cartons), the batch a scanned code names, what a bundle still has to pack,
 * the Packing Out checks, and the numbers a batch and a carton are given.
 */
final class PackingBook
{
    private ?Collection $lines = null;

    private ?Collection $buyers = null;

    private array $orders = [];

    private array $users = [];

    private array $orderQty = [];

    public function __construct(private readonly string $companyId) {}

    public static function today(): CarbonImmutable
    {
        return FinishingFloor::today();
    }

    /** A timestamp on the Dhaka clock. */
    public static function at(?\DateTimeInterface $ts): ?string
    {
        return $ts ? CarbonImmutable::instance($ts)->setTimezone('Asia/Dhaka')->format('Y-m-d H:i') : null;
    }

    public static function day(?\DateTimeInterface $ts): ?string
    {
        return $ts ? CarbonImmutable::instance($ts)->setTimezone('Asia/Dhaka')->toDateString() : null;
    }

    /** The packing locations by id. */
    public function lines(): Collection
    {
        return $this->lines ??= FinishingFloor::lines($this->companyId);
    }

    /** @return array{0: ?string, 1: ?string} the buyer's short name and code */
    public function buyer(?string $id): array
    {
        $this->buyers ??= DB::table('buyers')->where('company_id', $this->companyId)->get(['id', 'short_name', 'name', 'code'])->keyBy('id');
        $b = $id ? $this->buyers->get($id) : null;

        return [$b ? ($b->short_name ?: $b->name) : null, $b?->code];
    }

    /** The order behind a batch: its number, style, ship date and factory. */
    public function order(?string $id): ?object
    {
        if (! $id) {
            return null;
        }
        if (! array_key_exists($id, $this->orders)) {
            $this->orders[$id] = DB::table('merch_orders as o')->leftJoin('merch_styles as s', 's.id', '=', 'o.style_id')->leftJoin('factories as f', 'f.id', '=', 'o.factory_id')
                ->where('o.id', $id)->first(['o.id', 'o.order_no', 'o.style_no', 'o.ship_date', 's.description as style_name', 'f.name as factory']);
        }

        return $this->orders[$id];
    }

    /** The order quantity of a colour (and size), from the order's colour-size break-up. */
    public function orderQty(?string $orderId, ?string $colour, ?string $size = null): ?int
    {
        if (! $orderId) {
            return null;
        }
        $key = $orderId.'|'.$colour.'|'.$size;
        if (! array_key_exists($key, $this->orderQty)) {
            $q = DB::table('merch_order_sizes')->where('order_id', $orderId)->where('colour', $colour);
            if ($size !== null) {
                $q->where('size', $size);
            }
            $this->orderQty[$key] = $q->exists() ? (int) $q->sum('qty') : null;
        }

        return $this->orderQty[$key];
    }

    public function userName(?string $id): ?string
    {
        if (! $id) {
            return null;
        }

        return $this->users[$id] ??= DB::table('users')->where('id', $id)->value('name');
    }

    /** The Quality OUT a bundle left with, or null. */
    public static function qualityOut(CutBundle $card): ?AsmQualityOut
    {
        $qin = AsmQualityIn::query()->where('bundle_id', $card->id)->first();

        return $qin ? AsmQualityOut::query()->where('quality_in_id', $qin->id)->first() : null;
    }

    /** What a bundle has already packed in, over every batch it joined. */
    public static function packedQty(string $bundleId): int
    {
        return (int) AsmPackingIn::query()->where('bundle_id', $bundleId)->sum('qty');
    }

    /** The batch a scan names: its packing number, a carton (number or barcode), a poly label, or a bundle packed into it. */
    public function find(string $code): ?AsmPackingBatch
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }
        $flat = str_replace('-', '', $code);
        $batch = AsmPackingBatch::query()->where('status', '!=', 'CANCELLED')
            ->where(fn ($w) => $w->where('packing_no', $code)->orWhereRaw("replace(packing_no, '-', '') = ?", [$flat]))->first();
        if ($batch) {
            return $batch;
        }
        $carton = AsmPackingCarton::query()->where(fn ($w) => $w->where('carton_no', $code)->orWhereRaw("replace(carton_no, '-', '') = ?", [$flat]))->first();
        if ($carton) {
            return AsmPackingBatch::query()->find($carton->batch_id);
        }
        $label = AsmPackingLabel::query()->where('barcode', $flat)->first();
        if ($label) {
            return AsmPackingBatch::query()->find($label->batch_id);
        }
        $card = SewingFloor::card($code);
        if ($card) {
            $ids = AsmPackingIn::query()->where('bundle_id', $card->id)->pluck('batch_id');

            // The batch still being worked on first, else the newest.
            return AsmPackingBatch::query()->whereIn('id', $ids)->where('status', '!=', 'CANCELLED')
                ->orderByRaw("case when status = 'PACKED_OUT' then 1 else 0 end")->orderByDesc('created_at')->orderByDesc('id')->first();
        }

        return null;
    }

    /** One batch as the process desks and Packing Out show it, with its labels and cartons. */
    public function present(AsmPackingBatch $batch): array
    {
        return $this->presentAll(collect([$batch]), true)[0];
    }

    /**
     * Batches as the screens list them; $full adds the poly labels and every
     * carton with its size break-up.
     *
     * @return list<array<string, mixed>>
     */
    public function presentAll(Collection $batches, bool $full = false): array
    {
        if ($batches->isEmpty()) {
            return [];
        }
        $ids = $batches->pluck('id');
        $ins = AsmPackingIn::query()->whereIn('batch_id', $ids)->orderBy('created_at')->orderBy('id')->get()->groupBy('batch_id');
        $steps = AsmPackingStep::query()->whereIn('batch_id', $ids)->orderBy('seq')->get()->groupBy('batch_id');
        $cartons = AsmPackingCarton::query()->whereIn('batch_id', $ids)->orderBy('serial')->get()->groupBy('batch_id');
        $labels = $full ? AsmPackingLabel::query()->whereIn('batch_id', $ids)->get()->groupBy('batch_id') : collect();
        $cLines = $full ? AsmPackingCartonLine::query()->whereIn('carton_id', $cartons->flatten()->pluck('id'))->get()->groupBy('carton_id') : collect();
        $qcOuts = AsmQualityOut::query()->whereIn('id', $ins->flatten()->pluck('quality_out_id')->unique())->pluck('pass_qty', 'id');
        $sizeOrder = fn ($size) => self::sizeRank((string) $size);

        return $batches->map(function (AsmPackingBatch $b) use ($ins, $steps, $cartons, $labels, $cLines, $qcOuts, $full, $sizeOrder) {
            $bIns = $ins->get($b->id, collect());
            $bSteps = $steps->get($b->id, collect());
            $bCartons = $cartons->get($b->id, collect());
            [$buyer, $buyerCode] = $this->buyer($b->buyer_id);
            $order = $this->order($b->order_id);
            $line = $this->lines()->get($b->line_id);
            $sizes = $bIns->groupBy('size')->map(fn ($g, $size) => ['size' => (string) $size, 'qty' => (int) $g->sum('qty')])
                ->sortBy(fn ($s) => [$sizeOrder($s['size']), $s['size']])->values()->all();
            $sealed = $bCartons->where('status', 'SEALED');
            $stepRows = $bSteps->map(fn (AsmPackingStep $s) => [
                'step' => $s->step, 'seq' => $s->seq, 'status' => $s->status, 'qty' => $s->qty, 'remarks' => $s->remarks,
                'by' => $this->userName($s->completed_by ?? $s->started_by), 'started_at' => self::at($s->started_at), 'completed_at' => self::at($s->completed_at),
                'reopen_reason' => $s->reopen_reason,
            ])->values()->all();
            $done = $bSteps->where('status', 'COMPLETED')->count();
            $current = $bSteps->first(fn ($s) => $s->status !== 'COMPLETED');
            $packingIn = (int) $bIns->sum('qty');
            $out = [
                'id' => $b->id, 'packing_no' => $b->packing_no, 'status' => $b->status, 'stage' => $this->stage($b, $bSteps),
                'current_step' => $current?->step, 'buyer' => $buyer, 'buyer_code' => $buyerCode, 'order_no' => $order?->order_no, 'po_no' => $b->po_no,
                'style' => $b->style, 'style_name' => $order?->style_name, 'colour' => $b->colour, 'ship_date' => $order?->ship_date, 'factory' => $order?->factory,
                'order_qty' => $this->orderQty($b->order_id, $b->colour), 'qc_pass_qty' => (int) $bIns->pluck('quality_out_id')->unique()->sum(fn ($id) => (int) ($qcOuts[$id] ?? 0)),
                'packing_in_qty' => $packingIn, 'sizes' => $sizes, 'bundles' => $bIns->pluck('bundle_id')->unique()->count(),
                'location' => $line?->label, 'section' => $line?->section, 'floor' => $line?->floor, 'steps' => $stepRows, 'progress_pct' => (int) round($done / count(AsmPackingStep::STEPS) * 100),
                'cartons' => $bCartons->count(), 'sealed_cartons' => $sealed->count(), 'carton_qty' => (int) $bCartons->sum('qty'), 'sealed_qty' => (int) $sealed->sum('qty'),
                'gross_weight' => round((float) $bCartons->sum(fn ($c) => (float) $c->gross_weight), 2), 'net_weight' => round((float) $bCartons->sum(fn ($c) => (float) $c->net_weight), 2),
                'assort_ratio' => $b->assort_ratio, 'destination' => $b->destination, 'country' => $b->country, 'shipping_mark' => $b->shipping_mark,
                'special_instruction' => $b->special_instruction, 'marking_print_count' => $b->marking_print_count, 'final_packed_qty' => $b->final_packed_qty,
                'started_at' => self::at($bSteps->firstWhere('step', 'FOLDING')?->started_at), 'operator' => $this->userName($b->created_by),
                'in_at' => self::at($bIns->first()?->created_at), 'packed_out_at' => self::at($b->packed_out_at), 'packed_out_by' => $this->userName($b->packed_out_by),
            ];
            if ($full) {
                $out['labels'] = $labels->get($b->id, collect())->sortBy(fn ($l) => [$sizeOrder($l->size), $l->size])->map(fn (AsmPackingLabel $l) => [
                    'id' => $l->id, 'size' => $l->size, 'qty' => $l->qty, 'barcode' => $l->barcode, 'print_count' => $l->print_count, 'verified_at' => self::at($l->verified_at),
                ])->values()->all();
                $out['carton_list'] = $bCartons->map(fn (AsmPackingCarton $c) => [
                    'id' => $c->id, 'serial' => $c->serial, 'carton_no' => $c->carton_no, 'barcode' => $c->barcode(), 'carton_type' => $c->carton_type, 'carton_size' => $c->carton_size,
                    'gross_weight' => $c->gross_weight !== null ? (float) $c->gross_weight : null, 'net_weight' => $c->net_weight !== null ? (float) $c->net_weight : null,
                    'qty' => $c->qty, 'status' => $c->status, 'sealed_at' => self::at($c->sealed_at),
                    'lines' => $cLines->get($c->id, collect())->sortBy(fn ($l) => [$sizeOrder($l->size), $l->size])->map(fn ($l) => ['size' => $l->size, 'qty' => $l->qty])->values()->all(),
                ])->values()->all();
            }

            return $out;
        })->values()->all();
    }

    /** A size's place in the usual run (XS, S, M, L, XL …); any other size after them. */
    public static function sizeRank(string $size): int
    {
        $i = array_search(strtoupper(trim($size)), ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '2XL', 'XXXL', '3XL', '4XL', '5XL'], true);

        return $i === false ? 99 : $i;
    }

    /** Where a batch stands: taking bundles, in a process, ready for Packing Out, packed out or cancelled. */
    public function stage(AsmPackingBatch $b, Collection $steps): string
    {
        return match (true) {
            $b->status === 'PACKED_OUT', $b->status === 'CANCELLED', $b->status === 'PACKING_IN' => $b->status,
            $steps->isNotEmpty() && $steps->every(fn ($s) => $s->status === 'COMPLETED') => 'READY_OUT',
            default => 'IN_PROGRESS',
        };
    }

    /**
     * What Packing Out checks on a presented batch: every process completed,
     * every carton sealed, every poly label verified.
     *
     * @return list<array{key: string, ok: bool, params: array<string, mixed>}>
     */
    public static function checks(array $batch): array
    {
        $checks = [];
        foreach ($batch['steps'] as $s) {
            $checks[] = ['key' => 'step', 'step' => $s['step'], 'ok' => $s['status'] === 'COMPLETED', 'params' => ['status' => $s['status'], 'qty' => $s['qty']]];
        }
        $checks[] = ['key' => 'sealed', 'step' => null, 'ok' => $batch['cartons'] > 0 && $batch['sealed_cartons'] === $batch['cartons'], 'params' => ['n' => $batch['sealed_cartons'], 'of' => $batch['cartons']]];
        $labels = $batch['labels'] ?? [];
        $verified = count(array_filter($labels, fn ($l) => $l['verified_at'] !== null));
        $checks[] = ['key' => 'labels', 'step' => null, 'ok' => count($labels) > 0 && $verified === count($labels), 'params' => ['n' => $verified, 'of' => count($labels)]];

        return $checks;
    }

    /**
     * Book a packed-out batch into finished goods stock (20/06): one ledger
     * line per size from its sealed cartons; when the final packed quantity is
     * below what the cartons hold, the shortfall comes off the last sizes.
     */
    public static function bookIn(AsmPackingBatch $batch, ?string $userId): void
    {
        $sizes = AsmPackingCartonLine::query()->whereIn('carton_id', AsmPackingCarton::query()->where('batch_id', $batch->id)->where('status', 'SEALED')->select('id'))
            ->selectRaw('size, sum(qty) as qty')->groupBy('size')->pluck('qty', 'size')->map(fn ($q) => (int) $q)->all();
        uksort($sizes, fn ($x, $y) => [self::sizeRank((string) $x), (string) $x] <=> [self::sizeRank((string) $y), (string) $y]);
        $short = array_sum($sizes) - (int) $batch->final_packed_qty;
        foreach (array_reverse(array_keys($sizes)) as $size) {
            $take = max(0, min($short, $sizes[$size]));
            $sizes[$size] -= $take;
            $short -= $take;
        }
        foreach ($sizes as $size => $qty) {
            if ($qty > 0) {
                AsmFgLedger::query()->create([
                    'order_id' => $batch->order_id, 'po_no' => $batch->po_no, 'buyer_id' => $batch->buyer_id, 'style' => $batch->style, 'colour' => $batch->colour,
                    'size' => (string) $size, 'batch_id' => $batch->id, 'doc_type' => 'PACKING_OUT', 'doc_no' => $batch->packing_no, 'doc_id' => $batch->id,
                    'qty_in' => $qty, 'qty_out' => 0, 'posted_at' => $batch->packed_out_at ?? now(), 'created_by' => $userId,
                ]);
            }
        }
    }

    /** The next number of a kind for today: PKG-20261107-0001, CTN-20261107-0001. */
    public function nextNo(string $prefix, string $table, string $column): string
    {
        $stem = $prefix.'-'.self::today()->format('Ymd').'-';
        $last = DB::table($table)->where('company_id', $this->companyId)->where($column, 'like', $stem.'%')->orderByDesc($column)->value($column);
        $n = $last ? (int) substr($last, strlen($stem)) + 1 : 1;

        return $stem.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
