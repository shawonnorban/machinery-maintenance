<?php

declare(strict_types=1);

namespace App\Modules\Cutting\Http\Controllers\Api;

use App\Modules\Cutting\Models\CutBundle;
use App\Modules\Cutting\Support\CutTotals;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Cutting Floor → Reports (AnnotechERP 19/04), as Brickland keeps them: one
 * filter (dates, PO, style, buyer, colour, size, a serial number, status)
 * read over the bundle cards — every card (Bundle Report) or the cards
 * counted per PO, per style or per size — with Total Bundle and Total Qty.
 * The grouped reports leave cancelled cards out unless asked for them.
 */
class CutReportApiController extends ApiController
{
    public const KINDS = ['bundle', 'po', 'style', 'size'];

    /** Sizes as a garment runs, letters first; anything else after, in number order. */
    private const SIZE_ORDER = ['XXXS', 'XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '2XL', 'XXXL', '3XL', '4XL', '5XL'];

    public function show(Request $request, string $kind): JsonResponse
    {
        $this->allow('cutting.report.view');
        abort_unless(in_array($kind, self::KINDS, true), 404);
        $f = $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'],
            'po' => ['nullable', 'string', 'max:64'], 'style' => ['nullable', 'string', 'max:120'], 'buyer' => ['nullable', 'string', 'max:120'],
            'colour' => ['nullable', 'string', 'max:64'], 'size' => ['nullable', 'string', 'max:32'], 'serial' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['ALL', 'ALL_BUT_CANCELLED', ...CutBundle::STATUSES])],
        ], ['to.after_or_equal' => __('cutting.v.window')]);
        $status = ($f['status'] ?? null) ?: ($kind === 'bundle' ? 'ALL' : 'ALL_BUT_CANCELLED');
        $buyers = DB::table('buyers')->where('company_id', $this->caller()->companyId)->get(['id', 'short_name', 'name'])->mapWithKeys(fn ($b) => [$b->id => $b->short_name ?: $b->name]);
        $like = fn (string $col, ?string $v) => fn ($q) => $q->where($col, 'ilike', '%'.trim((string) $v).'%');
        $cards = CutBundle::query()
            ->when($f['from'] ?? null, fn ($q, $d) => $q->whereDate('cut_date', '>=', $d))->when($f['to'] ?? null, fn ($q, $d) => $q->whereDate('cut_date', '<=', $d))
            ->when($f['po'] ?? null, fn ($q, $v) => $like('po_no', $v)($q))->when($f['style'] ?? null, fn ($q, $v) => $like('style', $v)($q))
            ->when($f['colour'] ?? null, fn ($q, $v) => $like('colour', $v)($q))->when($f['size'] ?? null, fn ($q, $v) => $q->where('size', mb_strtoupper(trim($v))))
            ->when($f['serial'] ?? null, fn ($q, $n) => $q->where('serial_from', '<=', $n)->where('serial_to', '>=', $n))
            ->when($status === 'ALL_BUT_CANCELLED', fn ($q) => $q->where('status', '!=', 'CANCELLED'))->when(in_array($status, CutBundle::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->orderByDesc('cut_date')->orderByDesc('bundle_no')->get()
            ->map(fn (CutBundle $b) => ['bundle_no' => $b->bundle_no, 'cut_date' => $b->cut_date->toDateString(), 'po_no' => $b->po_no, 'style' => $b->style, 'buyer' => $buyers[$b->buyer_id] ?? null,
                'colour' => $b->colour, 'size' => $b->size, 'serial_from' => $b->serial_from, 'serial_to' => $b->serial_to, 'qty' => $b->qty, 'status' => $b->status, 'printed_at' => $b->printed_at?->toIso8601String()]);
        if ($f['buyer'] ?? null) {
            $needle = mb_strtolower(trim($f['buyer']));
            $cards = $cards->filter(fn ($c) => str_contains(mb_strtolower((string) $c['buyer']), $needle))->values();
        }

        $rows = match ($kind) {
            'bundle' => $cards->values()->all(),
            'po' => $this->count($cards, ['po_no', 'style'])->sortBy('po_no')->values()->all(),
            'style' => $this->count($cards, ['style'])->sortBy('style')->values()->all(),
            'size' => $this->count($cards, ['size'])->sortBy(fn ($r) => $this->sizeKey($r['size']))->values()->all(),
        };

        return ApiResponse::ok(['kind' => $kind, 'status' => $status, 'rows' => $rows, 'total_bundles' => $cards->count(), 'total_qty' => (int) $cards->sum('qty')]);
    }

    /**
     * Additional Cutting List (19/04, as Brickland keeps it): the cards cut
     * past the buyer's order — Additional, or Partial Additional where the
     * order ran out inside the card — in the dates and filters asked, and per
     * PO / style / colour the order, the total cut and the additional with its
     * percentage (N/A without an order). Cancelled cards never count.
     */
    public function additional(Request $request): JsonResponse
    {
        $this->allow('cutting.report.view');
        $f = $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'],
            'po' => ['nullable', 'string', 'max:64'], 'style' => ['nullable', 'string', 'max:120'], 'colour' => ['nullable', 'string', 'max:64'],
            'cutting_no' => ['nullable', 'integer', 'min:1'], 'bundle_no' => ['nullable', 'string', 'max:40'], 'size' => ['nullable', 'string', 'max:32'],
            'batch_no' => ['nullable', 'string', 'max:40'], 'status' => ['nullable', Rule::in(['ANY_ADDITIONAL', 'ADDITIONAL', 'PARTIAL_ADDITIONAL'])],
        ], ['to.after_or_equal' => __('cutting.v.window')]);
        $today = CarbonImmutable::now('Asia/Dhaka');
        $from = $f['from'] ?? $today->subDays(29)->toDateString();
        $to = $f['to'] ?? $today->toDateString();
        $status = ($f['status'] ?? null) ?: 'ANY_ADDITIONAL';
        $like = fn (string $col, string $v) => fn ($q) => $q->where($col, 'ilike', '%'.trim($v).'%');

        $cards = CutBundle::query()->where('status', '!=', 'CANCELLED')
            ->when($status === 'ANY_ADDITIONAL', fn ($q) => $q->whereIn('cutting_status', ['ADDITIONAL', 'PARTIAL_ADDITIONAL']), fn ($q) => $q->where('cutting_status', $status))
            ->whereDate('cut_date', '>=', $from)->whereDate('cut_date', '<=', $to)
            ->when($f['po'] ?? null, fn ($q, $v) => $like('po_no', $v)($q))->when($f['style'] ?? null, fn ($q, $v) => $like('style', $v)($q))
            ->when($f['colour'] ?? null, fn ($q, $v) => $like('colour', $v)($q))->when($f['bundle_no'] ?? null, fn ($q, $v) => $like('bundle_no', $v)($q))
            ->when($f['batch_no'] ?? null, fn ($q, $v) => $like('batch_no', $v)($q))->when($f['cutting_no'] ?? null, fn ($q, $n) => $q->where('cutting_no', $n))
            ->when($f['size'] ?? null, fn ($q, $v) => $q->where('size', mb_strtoupper(trim($v))))
            ->orderByDesc('cut_date')->orderByDesc('bundle_no')->get();
        $totals = CutTotals::forOrders($cards->pluck('order_id'));
        $buyers = DB::table('buyers')->where('company_id', $this->caller()->companyId)->get(['id', 'short_name', 'name'])->mapWithKeys(fn ($b) => [$b->id => $b->short_name ?: $b->name]);
        $of = fn (CutBundle $b) => $totals[CutTotals::key($b->order_id, (string) $b->colour)] ?? ['order_qty' => 0, 'cut' => 0, 'additional' => 0, 'additional_pct' => null];

        $groups = $cards->groupBy(fn (CutBundle $b) => CutTotals::key($b->order_id, (string) $b->colour))
            ->map(fn (Collection $g) => ['po_no' => $g->first()->po_no, 'style' => $g->first()->style, 'buyer' => $buyers[$g->first()->buyer_id] ?? null, 'colour' => $g->first()->colour] + $of($g->first()))
            ->sortBy(fn ($r) => [$r['po_no'], $r['colour']])->values();
        $bundles = $cards->map(function (CutBundle $b) use ($of) {
            $t = $of($b);

            return ['cut_date' => $b->cut_date->toDateString(), 'po_no' => $b->po_no, 'style' => $b->style, 'colour' => $b->colour, 'cutting_no' => $b->cutting_no,
                'bundle_no' => $b->bundle_no, 'size' => $b->size, 'parts_name' => $b->parts_name, 'batch_no' => $b->batch_no, 'qty' => $b->qty,
                'cutting_status' => $b->cutting_status, 'additional_qty' => $b->additional_qty, 'order_qty' => $t['order_qty'], 'total_cut' => $t['cut'],
                'total_additional' => $t['additional'], 'additional_pct' => $t['additional_pct']];
        });

        return ApiResponse::ok(['from' => $from, 'to' => $to, 'status' => $status, 'groups' => $groups->all(), 'bundles' => $bundles->values()->all(),
            'total_bundles' => $bundles->count(), 'total_additional' => (int) $cards->sum('additional_qty')]);
    }

    /** @param list<string> $keys */
    private function count(Collection $cards, array $keys): Collection
    {
        return $cards->groupBy(fn ($c) => implode('|', array_map(fn ($k) => (string) $c[$k], $keys)))
            ->map(fn (Collection $g) => array_intersect_key($g->first(), array_flip($keys)) + ['bundles' => $g->count(), 'qty' => (int) $g->sum('qty')])->values();
    }

    private function sizeKey(string $size): string
    {
        $i = array_search(mb_strtoupper($size), self::SIZE_ORDER, true);

        return $i !== false ? sprintf('0%03d', $i) : (is_numeric($size) ? sprintf('1%08.2f', (float) $size) : '2'.$size);
    }
}
