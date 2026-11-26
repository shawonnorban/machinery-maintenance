<?php

declare(strict_types=1);

namespace App\Modules\Cutting\Http\Controllers\Api;

use App\Modules\Cutting\Models\CutBundle;
use App\Modules\Cutting\Models\CutInput;
use App\Modules\Cutting\Support\CutCards;
use App\Modules\Cutting\Support\CutFabric;
use App\Modules\Cutting\Support\CutTotals;
use App\Modules\DyeHouse\Support\DyeBatches;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Cutting Floor (AnnotechERP 19/01–19/03), as Brickland keeps it: a PO and
 * style picked off the merchandise orders (buyer, colour and order quantity
 * come with them), then bundle cards added one serial run at a time — the
 * card numbered BND-YYYYMMDD-####, its quantity the run's length, never
 * overlapping another live card of the same order, colour, size and part.
 * What is cut beyond the colour's order quantity (per part) is additional.
 * Cards are printed, reprinted or cancelled — never deleted. The cutting list
 * and the dashboard are counted from the cards, nothing typed.
 */
class CutBundleApiController extends ApiController
{
    /** @var \Illuminate\Support\Collection<string, string>|null */
    private ?Collection $buyerNames = null;

    /** What the bundle entry picks from, and the number the next card will take. */
    public function options(): JsonResponse
    {
        $this->allow('cutting.bundle.view');
        $c = $this->caller()->companyId;
        $orders = DB::table('merch_orders')->where('company_id', $c)->whereNotNull('po_no')->where('po_no', '!=', '')->whereNotIn('status', ['CANCELLED'])
            ->orderBy('po_no')->get(['id', 'po_no', 'style_no', 'work_style_no', 'buyer_id', 'item', 'gmt_type', 'total_qty']);
        $sizes = DB::table('merch_order_sizes')->whereIn('order_id', $orders->pluck('id'))->orderBy('sort_order')->get(['order_id', 'colour', 'size', 'qty'])->groupBy('order_id');
        $buyers = DB::table('buyers')->where('company_id', $c)->get(['id', 'short_name', 'name'])->mapWithKeys(fn ($b) => [$b->id => $b->short_name ?: $b->name]);
        $prior = DB::table('cut_bundles')->where('company_id', $c);
        $distinct = fn (string $col, array $seed = []) => collect($seed)->merge((clone $prior)->whereNotNull($col)->distinct()->pluck($col))->filter()->unique()->sort()->values()->all();

        return ApiResponse::ok([
            'today' => CarbonImmutable::now('Asia/Dhaka')->toDateString(),
            'next_bundle_no' => $this->peekNumber(),
            'orders' => $orders->map(function ($o) use ($sizes, $buyers) {
                $mine = $sizes[$o->id] ?? collect();
                $colours = $mine->groupBy('colour')->map(fn ($g, $colour) => ['colour' => $colour, 'qty' => (int) $g->sum('qty'), 'sizes' => $g->pluck('size')->values()->all()])->values()->all();

                return ['value' => $o->id, 'po_no' => $o->po_no, 'style' => $o->work_style_no ?: $o->style_no, 'buyer_id' => $o->buyer_id, 'buyer' => $buyers[$o->buyer_id] ?? null,
                    'garment' => $o->item ?: $o->gmt_type, 'total_qty' => (int) $o->total_qty, 'colours' => $colours];
            })->values()->all(),
            'garments' => $distinct('garment', DB::table('merch_orders')->where('company_id', $c)->whereNotNull('item')->distinct()->pluck('item')->all()),
            'fabric_types' => $distinct('fabric_type', ['1x1 Rib', '2x2 Rib', 'Fleece', 'French Terry', 'Interlock', 'Lycra Single Jersey', 'Mesh', 'POPLIN', 'Pique / Lacoste', 'Single Jersey']),
            'gsm' => $distinct('gsm'),
            'dia_width' => $distinct('dia_width'),
            'parts' => $distinct('parts_name', CutBundle::PARTS),
            'batches' => $distinct('batch_no', DB::table('dye_batches')->where('company_id', $c)->orderByDesc('batch_date')->limit(200)->pluck('batch_no')->all()),
        ]);
    }

    /** Today's cutting and bundles, the balance still to cut, cards waiting to print, and the latest cards. */
    public function dashboard(): JsonResponse
    {
        $this->allow('cutting.bundle.view');
        $today = CarbonImmutable::now('Asia/Dhaka')->toDateString();
        $live = CutBundle::query()->where('status', '!=', 'CANCELLED');
        $todays = (clone $live)->whereDate('cut_date', $today);

        return ApiResponse::ok([
            'today_cut' => (int) (clone $todays)->sum('qty'),
            'balance' => (int) collect($this->cuttingRows())->sum('balance'),
            'today_bundles' => (clone $todays)->count(),
            'today_qty' => (int) (clone $todays)->sum('qty'),
            'pending_print' => (clone $live)->where('status', 'CREATED')->count(),
            'printed' => (clone $live)->whereIn('status', ['PRINTED', 'REPRINTED'])->count(),
            'latest' => CutBundle::query()->orderByDesc('cut_date')->orderByDesc('bundle_no')->limit(10)->get()->map(fn ($b) => $this->present($b))->all(),
        ]);
    }

    /** The bundle list: the date window, a search across number, PO, style, size and serial, status and cutting status. */
    public function index(Request $request): JsonResponse
    {
        $this->allow('cutting.bundle.view');
        $q = $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'q' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::in(CutBundle::STATUSES)], 'cutting_status' => ['nullable', Rule::in([...CutBundle::CUTTING_STATUSES, 'ANY_ADDITIONAL'])],
        ]);
        $rows = CutBundle::query()
            ->when($q['from'] ?? null, fn ($w, $d) => $w->whereDate('cut_date', '>=', $d))->when($q['to'] ?? null, fn ($w, $d) => $w->whereDate('cut_date', '<=', $d))
            ->when($q['status'] ?? null, fn ($w, $s) => $w->where('status', $s))
            ->when($q['cutting_status'] ?? null, fn ($w, $s) => $s === 'ANY_ADDITIONAL' ? $w->where('cutting_status', '!=', 'REGULAR') : $w->where('cutting_status', $s))
            ->when(trim((string) ($q['q'] ?? '')), function ($w, $text) {
                $like = '%'.$text.'%';
                $w->where(function ($x) use ($like, $text) {
                    $x->where('bundle_no', 'ilike', $like)->orWhere('po_no', 'ilike', $like)->orWhere('style', 'ilike', $like)->orWhere('size', 'ilike', $like)->orWhere('colour', 'ilike', $like);
                    if (ctype_digit($text)) {
                        $x->orWhere('cutting_no', (int) $text)->orWhere(fn ($y) => $y->where('serial_from', '<=', (int) $text)->where('serial_to', '>=', (int) $text));
                    }
                });
            })
            ->orderByDesc('cut_date')->orderByDesc('bundle_no')->get();
        $live = $rows->where('status', '!=', 'CANCELLED');

        return ApiResponse::ok(['rows' => $rows->map(fn ($b) => $this->present($b))->all(), 'bundles' => $live->count(), 'pcs' => (int) $live->sum('qty'), 'additional' => (int) $live->sum('additional_qty')]);
    }

    public function show(string $no): JsonResponse
    {
        $this->allow('cutting.bundle.view');

        return ApiResponse::ok($this->present(CutBundle::query()->where('bundle_no', $no)->firstOrFail()));
    }

    /** Add a card to the list: it is numbered here and its quantity counted from its serial run. */
    public function store(Request $request): JsonResponse
    {
        $this->allow('cutting.bundle.manage');
        $data = $this->validated($request, null);
        $bundle = DB::transaction(function () use ($data) {
            $date = $data['cut_date'];

            return CutBundle::query()->create($data + ['bundle_no' => DyeBatches::nextNumber('BND-', 'cut_bundles', 'bundle_no', $date, 'Ymd-'), 'status' => 'CREATED', 'created_by' => $this->caller()->auditUserId()]);
        });

        return ApiResponse::created($this->present($bundle));
    }

    /**
     * Where a PO's colour stands for New Cutting and Bundle Entry: the order and
     * the cut per size, the next cutting number (over the cuttings laid and the
     * cards cut), the fabric (CutFabric), the cards per cutting, and the serial
     * each size carries on from per part. Cancelled cards never count.
     */
    public function context(Request $request): JsonResponse
    {
        $this->allow('cutting.bundle.view');
        $c = $this->caller()->companyId;
        $q = $request->validate(['order_id' => ['required', Rule::exists('merch_orders', 'id')->where('company_id', $c)], 'colour' => ['required', 'string', 'max:64']]);
        $colour = mb_strtolower(trim($q['colour']));
        $order = DB::table('merch_orders')->where('id', $q['order_id'])->first(['id', 'total_qty']);
        $sizes = DB::table('merch_order_sizes')->where('order_id', $order->id)->orderBy('sort_order')->get(['colour', 'size', 'qty'])
            ->filter(fn ($r) => mb_strtolower((string) $r->colour) === $colour)->groupBy(fn ($r) => mb_strtoupper(trim($r->size)))->map(fn ($g) => (int) $g->sum('qty'));
        $cards = CutBundle::query()->where('order_id', $order->id)->where('status', '!=', 'CANCELLED')->whereRaw('lower(colour) = ?', [$colour])->get();
        $totals = CutTotals::forOrders(collect([$order->id]))[CutTotals::key($order->id, $colour)] ?? null;
        $orderQty = $sizes->isNotEmpty() ? (int) $sizes->sum() : (int) $order->total_qty;
        // A size is cut as far as its part cut most.
        $cutBySize = $cards->groupBy('size')->map(fn ($g) => (int) $g->groupBy('parts_name')->map(fn ($p) => $p->sum('qty'))->max());

        return ApiResponse::ok([
            'order_qty' => $orderQty, 'cut' => $totals['cut'] ?? 0, 'balance' => max(0, $orderQty - ($totals['cut'] ?? 0)),
            'next_cutting_no' => 1 + max((int) $cards->max('cutting_no'), (int) CutInput::query()->where('order_id', $order->id)->where('status', '!=', 'CANCELLED')->whereRaw('lower(colour) = ?', [$colour])->max('cutting_no')),
            'fabric' => CutFabric::of($order->id, $colour),
            'sizes' => $sizes->keys()->merge($cutBySize->keys())->unique()->values()->map(fn ($s) => ['size' => $s, 'order_qty' => $sizes[$s] ?? null, 'cut' => $cutBySize[$s] ?? 0])->all(),
            'cuttings' => $cards->groupBy(fn ($b) => $b->cutting_no.'|'.$b->parts_name.'|'.$b->batch_no)->map(fn ($g) => [
                'cutting_no' => $g->first()->cutting_no, 'parts_name' => $g->first()->parts_name, 'batch_no' => $g->first()->batch_no,
                'bundles' => $g->count(), 'qty' => (int) $g->sum('qty'), 'last_cut' => $g->max(fn ($b) => $b->cut_date->toDateString()),
            ])->sortByDesc(fn ($r) => sprintf('%06d|%s', $r['cutting_no'], $r['parts_name']))->values()->all(),
            'next_serial' => $cards->groupBy('parts_name')->map(fn ($p) => $p->groupBy('size')->map(fn ($s) => 1 + (int) $s->max('serial_to')))->all(),
        ]);
    }

    /** Correct a card — not one that is cancelled. Its number stays. */
    public function update(Request $request, string $no): JsonResponse
    {
        $this->allow('cutting.bundle.manage');
        $bundle = CutBundle::query()->where('bundle_no', $no)->firstOrFail();
        if ($bundle->status === 'CANCELLED') {
            throw ValidationException::withMessages(['bundle_no' => __('cutting.v.cancelled', ['no' => $bundle->bundle_no])]);
        }
        $bundle->update($this->validated($request, $bundle));

        return ApiResponse::ok($this->present($bundle->refresh()));
    }

    /** Print new cards (or, with `reprint`, print printed ones again): each is stamped and counted, and its card comes back to print. */
    public function print(Request $request): JsonResponse
    {
        $this->allow('cutting.bundle.manage');
        $data = $request->validate(['bundles' => ['required', 'array', 'min:1', 'max:500'], 'bundles.*' => ['string', 'max:24'], 'reprint' => ['sometimes', 'boolean']],
            ['bundles.required' => __('cutting.v.pick'), 'bundles.min' => __('cutting.v.pick')]);
        $reprint = (bool) ($data['reprint'] ?? false);
        $bundles = CutBundle::query()->whereIn('bundle_no', $data['bundles'])->orderBy('bundle_no')->get();
        foreach ($bundles as $b) {
            $ok = $reprint ? in_array($b->status, ['PRINTED', 'REPRINTED'], true) : $b->status === 'CREATED';
            if (! $ok) {
                throw ValidationException::withMessages(['bundles' => __($reprint ? 'cutting.v.not_printed' : 'cutting.v.printed_already', ['no' => $b->bundle_no])]);
            }
        }
        DB::transaction(function () use ($bundles, $reprint): void {
            foreach ($bundles as $b) {
                $b->update(['status' => $reprint ? 'REPRINTED' : 'PRINTED', 'print_count' => $b->print_count + 1, 'printed_at' => now()]);
            }
        });

        return ApiResponse::ok(['cards' => $bundles->map(fn ($b) => $this->present($b->refresh()))->all()]);
    }

    /** Cancel a card: it stays on file, out of every count. */
    public function cancel(string $no): JsonResponse
    {
        $this->allow('cutting.bundle.manage');
        $bundle = CutBundle::query()->where('bundle_no', $no)->firstOrFail();
        if ($bundle->status === 'CANCELLED') {
            throw ValidationException::withMessages(['bundle_no' => __('cutting.v.cancelled', ['no' => $bundle->bundle_no])]);
        }
        $bundle->update(['status' => 'CANCELLED', 'cutting_status' => 'REGULAR', 'additional_qty' => 0]);

        return ApiResponse::ok($this->present($bundle->refresh()));
    }

    /** Every PO / style / colour cut so far: order quantity, already cut, balance and what was cut beyond it. */
    public function cuttingList(): JsonResponse
    {
        $this->allow('cutting.bundle.view');

        return ApiResponse::ok($this->cuttingRows());
    }

    /** Which card a serial number is on (for a PO and style if given). */
    public function serial(Request $request): JsonResponse
    {
        $this->allow('cutting.bundle.view');
        $q = $request->validate(['serial' => ['required', 'integer', 'min:1'], 'order_id' => ['nullable', 'string', 'max:40'], 'colour' => ['nullable', 'string', 'max:64'], 'size' => ['nullable', 'string', 'max:32']]);
        $rows = CutBundle::query()->where('status', '!=', 'CANCELLED')->where('serial_from', '<=', $q['serial'])->where('serial_to', '>=', $q['serial'])
            ->when($q['order_id'] ?? null, fn ($w, $o) => $w->where('order_id', $o))->when($q['colour'] ?? null, fn ($w, $c) => $w->where('colour', $c))->when($q['size'] ?? null, fn ($w, $s) => $w->where('size', $s))
            ->orderBy('bundle_no')->get();

        return ApiResponse::ok($rows->map(fn ($b) => $this->present($b))->all());
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?CutBundle $bundle): array
    {
        $c = $this->caller()->companyId;
        $data = $request->validate([
            'order_id' => ['required', Rule::exists('merch_orders', 'id')->where('company_id', $c)],
            'colour' => ['required', 'string', 'max:64'],
            'garment' => ['nullable', 'string', 'max:120'], 'fabric_type' => ['nullable', 'string', 'max:60'], 'gsm' => ['nullable', 'string', 'max:16'], 'dia_width' => ['nullable', 'string', 'max:16'],
            'cutting_no' => ['required', 'integer', 'min:1', 'max:99999'],
            'size' => ['required', 'string', 'max:32'],
            'serial_from' => ['required', 'integer', 'min:1', 'max:9999999'],
            'serial_to' => ['required', 'integer', 'gte:serial_from', 'max:9999999'],
            'parts_name' => ['required', 'string', 'max:60'],
            'batch_no' => ['required', 'string', 'max:40'],
            'cut_date' => ['nullable', 'date'],
        ], [
            'order_id.required' => __('cutting.v.po'), 'colour.required' => __('cutting.v.colour'), 'cutting_no.required' => __('cutting.v.cutting_no'), 'size.required' => __('cutting.v.size'),
            'serial_from.required' => __('cutting.v.serial_from'), 'serial_to.required' => __('cutting.v.serial_to'), 'serial_to.gte' => __('cutting.v.serial_order'),
            'parts_name.required' => __('cutting.v.parts'), 'batch_no.required' => __('cutting.v.batch'),
        ]);

        return $this->prepare($data, $bundle);
    }

    /**
     * A card's facts from the order, its quantity from its serial run, a serial
     * clash refused, and whether it is cut past the order — for one card or
     * each card of a whole cutting.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepare(array $data, ?CutBundle $bundle): array
    {
        return CutCards::prepare($data, $bundle);
    }

    /** @return list<array<string, mixed>> */
    private function cuttingRows(): array
    {
        $c = $this->caller()->companyId;
        $bundles = CutBundle::query()->where('status', '!=', 'CANCELLED')->get(['order_id', 'po_no', 'style', 'buyer_id', 'colour', 'parts_name', 'qty', 'cut_date']);
        $totals = CutTotals::forOrders($bundles->pluck('order_id'));
        $buyers = DB::table('buyers')->where('company_id', $c)->get(['id', 'short_name', 'name'])->mapWithKeys(fn ($b) => [$b->id => $b->short_name ?: $b->name]);

        return $bundles->groupBy(fn ($b) => CutTotals::key($b->order_id, (string) $b->colour))->map(function (Collection $g, string $key) use ($totals, $buyers) {
            $first = $g->first();
            // A garment is cut once whatever its parts: the part cut most stands for it (CutTotals).
            ['order_qty' => $orderQty, 'cut' => $cut, 'additional' => $additional, 'additional_pct' => $pct] = $totals[$key];

            return ['order_id' => $first->order_id, 'po_no' => $first->po_no, 'style' => $first->style, 'buyer' => $buyers[$first->buyer_id] ?? null, 'colour' => $first->colour, 'order_qty' => $orderQty,
                'already_cut' => $cut, 'balance' => max(0, $orderQty - $cut), 'additional' => $additional, 'additional_pct' => $pct ?? 0.0,
                'bundles' => $g->count(), 'last_cut' => $g->max(fn ($b) => $b->cut_date->toDateString())];
        })->sortByDesc('last_cut')->values()->all();
    }

    /** The number the next card cut today would take (shown, not reserved). */
    private function peekNumber(): string
    {
        $head = 'BND-'.CarbonImmutable::now('Asia/Dhaka')->format('Ymd-');
        $n = 1 + (int) CutBundle::query()->where('bundle_no', 'like', $head.'%')->pluck('bundle_no')->map(fn ($v) => (int) substr($v, strlen($head)))->max();

        return $head.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    /** @return array<string, mixed> */
    private function present(CutBundle $b): array
    {
        $buyers = $this->buyerNames ??= DB::table('buyers')->where('company_id', $this->caller()->companyId)->get(['id', 'short_name', 'name'])->mapWithKeys(fn ($x) => [$x->id => $x->short_name ?: $x->name]);

        return ['id' => $b->id, 'bundle_no' => $b->bundle_no, 'barcode' => $b->barcode(), 'cut_date' => $b->cut_date->toDateString(), 'order_id' => $b->order_id, 'po_no' => $b->po_no, 'style' => $b->style,
            'buyer' => $buyers[$b->buyer_id] ?? null, 'colour' => $b->colour, 'garment' => $b->garment, 'fabric_type' => $b->fabric_type, 'gsm' => $b->gsm, 'dia_width' => $b->dia_width, 'cutting_no' => $b->cutting_no,
            'size' => $b->size, 'serial_from' => $b->serial_from, 'serial_to' => $b->serial_to, 'qty' => $b->qty, 'parts_name' => $b->parts_name, 'batch_no' => $b->batch_no, 'cutting_status' => $b->cutting_status,
            'additional_qty' => $b->additional_qty, 'status' => $b->status, 'print_count' => $b->print_count, 'printed_at' => $b->printed_at?->toIso8601String()];
    }
}
