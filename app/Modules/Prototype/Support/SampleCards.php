<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Support;

use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\SampleType;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Prototype\Models\Sample;
use App\Modules\Prototype\Models\SampleRequisition;
use App\Modules\Prototype\Models\SampleRevision;
use Illuminate\Support\Collection;

/**
 * What every Prototype Desk screen shows about the sample it is about — the
 * Sample block of the development, revision, QC, approval and handover
 * forms: its requisition, buyer, style, order, type and stage, its version
 * and latest revision — read off the requisition that minted it.
 */
final class SampleCards
{
    /** @return array<string, array<string, mixed>> sample id => card */
    public static function of(Collection $samples): array
    {
        $reqs = SampleRequisition::query()->whereIn('id', $samples->pluck('requisition_id'))->get()->keyBy('id');
        $buyers = Buyer::query()->whereIn('id', $reqs->pluck('buyer_id'))->pluck('short_name', 'id');
        $styles = Style::query()->whereIn('id', $reqs->pluck('style_id'))->get(['id', 'style_ref', 'style_ext', 'description'])->keyBy('id');
        $orders = Order::query()->whereIn('id', $reqs->pluck('order_id')->filter())->pluck('order_no', 'id');
        $types = SampleType::query()->whereIn('id', $reqs->pluck('sample_type_id'))->pluck('name', 'id');
        $revisions = SampleRevision::query()->whereIn('sample_id', $samples->pluck('id'))->orderBy('new_version')->get()->groupBy('sample_id');

        return $samples->mapWithKeys(function (Sample $s) use ($reqs, $buyers, $styles, $orders, $types, $revisions): array {
            $r = $reqs[$s->requisition_id] ?? null;
            $style = $r ? ($styles[$r->style_id] ?? null) : null;

            return [$s->id => [
                'sample_id' => $s->id, 'sample_no' => $s->sample_no, 'sample_version' => $s->version, 'sample_phase' => $s->phase, 'sample_state' => $s->state,
                'requisition_no' => $r?->req_no, 'buyer' => $r ? ($buyers[$r->buyer_id] ?? null) : null,
                'style_ref' => $style ? trim($style->style_ref.($style->style_ext ? "-{$style->style_ext}" : '')) : null, 'style_name' => $style?->description,
                'order_no' => $r?->order_id ? ($orders[$r->order_id] ?? null) : null, 'sample_type' => $r ? ($types[$r->sample_type_id] ?? null) : null,
                'sample_stage' => $r?->sample_stage, 'sample_qty' => $r?->qty, 'revision_no' => ($revisions[$s->id] ?? collect())->last()?->rev_no,
            ]];
        })->all();
    }

    /** A sample picker's options: "SMP-2026-00001 — Summer Set · PP Sample · LPP", each with its card. @return list<array<string, mixed>> */
    public static function options(Collection $samples): array
    {
        return collect(self::of($samples))->map(fn (array $c): array => [
            'value' => $c['sample_id'], 'label' => $c['sample_no'].' — '.implode(' · ', array_filter([$c['style_name'] ?: $c['style_ref'], $c['sample_type'], $c['buyer']])),
        ] + $c)->values()->all();
    }
}
