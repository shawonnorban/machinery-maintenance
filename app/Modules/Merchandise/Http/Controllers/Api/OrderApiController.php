<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\BuyerOffice;
use App\Modules\Merchandise\Models\BuyerOfficeLink;
use App\Modules\Merchandise\Models\Colour;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\OrderSize;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Merchandise\Models\Size;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Merchandise\Models\StyleColour;
use App\Modules\Merchandise\Models\StyleSizeRange;
use App\Modules\Merchandise\Resources\OrderResource;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Style & Order → Order Register (AnnotechERP 06/07): the lists the order form
 * picks from, and the Size Breakdown panel — an order's colour × size lines,
 * saved together. Once an order has lines its quantity is their sum, worked
 * out again on every save.
 */
class OrderApiController extends ApiController
{
    public function lookups(): JsonResponse
    {
        $this->allow('merchandise.order.view');
        $offices = BuyerOffice::query()->orderBy('name')->pluck('name', 'id');

        return ApiResponse::ok([
            'buyers' => Buyer::query()->where('active', true)->orderBy('short_name')->get(['id', 'code', 'short_name', 'name'])
                ->map(fn (Buyer $b): array => ['value' => $b->id, 'label' => ($b->short_name ?: $b->name)." ({$b->code})"])->all(),
            'buyer_offices' => BuyerOfficeLink::query()->get(['buyer_id', 'buyer_office_id'])->groupBy('buyer_id')
                ->map(fn (Collection $rows): array => $rows->map(fn ($l): array => ['value' => $l->buyer_office_id, 'label' => $offices[$l->buyer_office_id] ?? ''])->values()->all()),
            'factories' => Factory::query()->orderBy('name')->get(['id', 'name'])->map(fn ($f): array => ['value' => $f->id, 'label' => $f->name])->all(),
            'countries' => OriginCountry::query()->where('active', true)->orderBy('name')->get(['id', 'code', 'name'])->map(fn ($c): array => ['value' => $c->id, 'label' => $c->name])->all(),
            'styles' => Style::query()->orderBy('style_ref')->get(['id', 'style_ref', 'style_ext', 'description', 'buyer_id', 'status'])
                ->map(fn (Style $s): array => ['value' => $s->id, 'label' => "{$s->styleNo()} — {$s->description}", 'buyer_id' => $s->buyer_id, 'status' => $s->status])->all(),
        ]);
    }

    public function sizes(string $order): JsonResponse
    {
        $this->allow('merchandise.order.view');
        $row = Order::query()->whereKey($order)->firstOrFail();

        return ApiResponse::ok($this->breakdown($row) + ['suggest' => $this->suggestions($row)]);
    }

    /** The whole breakdown at once; with any line, the order's quantity becomes their sum. */
    public function saveSizes(Request $request, string $order): JsonResponse
    {
        $this->allow('merchandise.order.manage');
        $row = Order::query()->whereKey($order)->firstOrFail();
        $data = $request->validate([
            'rows' => ['present', 'array', 'max:500'],
            'rows.*.colour' => ['required', 'string', 'max:64'],
            'rows.*.size' => ['required', 'string', 'max:32'],
            'rows.*.qty' => ['required', 'integer', 'min:1', 'max:10000000'],
        ], [
            'rows.*.colour.required' => __('merchandise.order.colour_required'),
            'rows.*.size.required' => __('merchandise.order.size_required'),
            'rows.*.qty.required' => __('merchandise.order.line_qty_required'),
        ]);

        $seen = [];
        foreach ($data['rows'] as $r) {
            $key = mb_strtoupper(trim($r['colour'])).'|'.mb_strtoupper(trim($r['size']));
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(['rows' => __('merchandise.order.line_twice', ['colour' => trim($r['colour']), 'size' => trim($r['size'])])]);
            }
            $seen[$key] = true;
        }

        if ($data['rows'] !== []) {
            app(OrderResource::class)->guardProjection($row, ['total_qty' => array_sum(array_map(fn (array $r): int => (int) $r['qty'], $data['rows']))]);
        }

        DB::transaction(function () use ($row, $data): void {
            OrderSize::query()->where('order_id', $row->id)->delete();
            foreach (array_values($data['rows']) as $i => $r) {
                OrderSize::query()->create(['order_id' => $row->id, 'colour' => trim($r['colour']), 'size' => trim($r['size']), 'qty' => (int) $r['qty'], 'sort_order' => ($i + 1) * 10]);
            }
            if ($data['rows'] !== []) {
                $row->update(['total_qty' => array_sum(array_map(fn (array $r): int => (int) $r['qty'], $data['rows']))]);
            }
        });

        return ApiResponse::ok($this->breakdown($row->refresh()));
    }

    /** @return array{order: array<string, mixed>, rows: list<array<string, mixed>>} */
    private function breakdown(Order $order): array
    {
        return [
            'order' => ['id' => $order->id, 'order_no' => $order->order_no, 'style_no' => $order->style_no, 'total_qty' => $order->total_qty, 'unit' => $order->unit],
            'rows' => OrderSize::query()->where('order_id', $order->id)->orderBy('sort_order')->get(['id', 'colour', 'size', 'qty'])
                ->map(fn (OrderSize $s): array => ['id' => $s->id, 'colour' => $s->colour, 'size' => $s->size, 'qty' => $s->qty])->values()->all(),
        ];
    }

    /** @return array{colours: list<string>, sizes: list<string>} the order's style colours and sizes, offered while typing a line */
    private function suggestions(Order $order): array
    {
        if (! $order->style_id) {
            return ['colours' => [], 'sizes' => []];
        }
        $colourIds = StyleColour::query()->where('style_id', $order->style_id)->orderBy('sort_order')->pluck('colour_id');
        $names = Colour::query()->whereIn('id', $colourIds)->pluck('name', 'id');
        $sizeIds = StyleSizeRange::query()->where('style_id', $order->style_id)->orderBy('sl_no')->get(['size_ids'])->flatMap(fn ($r) => $r->size_ids ?? [])->unique()->values();
        $sizes = Size::query()->whereIn('id', $sizeIds)->orderBy('display_order')->orderBy('name')->pluck('name');

        return [
            'colours' => $colourIds->map(fn (string $id): ?string => $names[$id] ?? null)->filter()->values()->all(),
            'sizes' => $sizes->values()->all(),
        ];
    }
}
