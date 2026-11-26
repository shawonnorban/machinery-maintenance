<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Merchandise\Models\Inquiry;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\OrderSize;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Merchandise\Models\StyleBom;
use App\Modules\Merchandise\Models\StyleBomLine;
use App\Modules\Merchandise\Models\StyleBomVersion;
use App\Modules\Merchandise\Models\StyleColour;
use App\Modules\Merchandise\Models\StyleFabric;
use App\Modules\Merchandise\Models\StyleFabricMap;
use App\Modules\Merchandise\Models\StyleItem;
use App\Modules\Merchandise\Models\StyleItemStyle;
use App\Modules\Merchandise\Models\StylePacking;
use App\Modules\Merchandise\Models\StyleProcess;
use App\Modules\Merchandise\Models\TnaTemplate;
use App\Modules\Merchandise\Resources\OrderResource;
use App\Modules\Merchandise\Support\OrderNumber;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Style & Order → Order Confirmation (AnnotechERP 06/07), in its two places:
 *
 * - the Order Confirmation screen confirms approved styles of a buyer as
 *   orders in one entry — the buyer's PO, the order and shipment dates, the
 *   currency, and per style its quantity and unit price — one order per
 *   style, numbered from the buyer's series; what each order inherits from
 *   its style (items, fabrics, colours, services, BOM, fabric mapping) is
 *   counted beside it;
 * - the style sheet's Order Confirmation tab lists a style's orders and
 *   confirms a new, repeat or development order against it in full.
 */
class OrderConfirmationApiController extends ApiController
{
    public const CURRENCIES = ['USD', 'EUR', 'GBP', 'CAD', 'BDT'];

    /** A buyer's approved styles, each with what an order would inherit from it. */
    public function styles(Request $request): JsonResponse
    {
        $this->allow('merchandise.order.view');
        $buyer = (string) $request->query('buyer', '');
        $styles = $buyer === '' ? collect() : Style::query()->where('buyer_id', $buyer)->where('status', 'STYLE_APPROVED')->orderBy('style_ref')->orderBy('style_ext')->get();
        $ids = $styles->pluck('id')->all();

        $links = StyleItemStyle::query()->whereIn('style_id', $ids)->get(['style_id', 'style_item_id']);
        $items = StyleItem::query()->whereIn('id', $links->pluck('style_item_id'))->get(['id', 'full_name', 'short_name', 'key_image_id'])->keyBy('id');
        $count = fn (string $model, string $column = 'style_id') => $model::query()->whereIn($column, $ids)->selectRaw("{$column}, count(*) as n")->groupBy($column)->pluck('n', $column);
        $fabrics = $count(StyleFabric::class);
        $colours = $count(StyleColour::class);
        $processes = $count(StyleProcess::class);
        $maps = $count(StyleFabricMap::class);
        $packing = $count(StylePacking::class);
        $boms = StyleBom::query()->whereIn('style_id', $ids)->pluck('id', 'style_id');
        $bomLines = $boms->map(function (string $bomId): int {
            $version = StyleBomVersion::query()->where('bom_id', $bomId)->orderByDesc('version')->value('id');

            return $version ? StyleBomLine::query()->where('version_id', $version)->count() : 0;
        });
        $orders = Order::query()->whereIn('style_id', $ids)->selectRaw('style_id, count(*) as n')->groupBy('style_id')->pluck('n', 'style_id');

        return ApiResponse::ok($styles->map(function (Style $s) use ($links, $items, $fabrics, $colours, $processes, $maps, $packing, $bomLines, $orders): array {
            $own = $links->where('style_id', $s->id)->map(fn ($l) => $items[$l->style_item_id] ?? null)->filter()->values();
            $first = $own->first();

            return [
                'id' => $s->id, 'style_no' => $s->styleNo(), 'description' => $s->description, 'status' => $s->status, 'qty_unit' => $s->qty_unit, 'pcs_per_unit' => $s->pcs_per_unit,
                'garment_type' => $first ? ($first->full_name ?: $first->short_name) : null,
                'image' => $first?->key_image_id ? "/api/files/{$first->key_image_id}/download" : null,
                'orders' => (int) ($orders[$s->id] ?? 0),
                'inherit' => [
                    'items' => $own->count(),
                    'fabrics' => (int) ($fabrics[$s->id] ?? 0),
                    'colours' => (int) ($colours[$s->id] ?? 0),
                    'services' => (int) ($processes[$s->id] ?? 0),
                    'bom' => (int) ($bomLines[$s->id] ?? 0),
                    'fabric_maps' => (int) ($maps[$s->id] ?? 0),
                    'packing' => (int) ($packing[$s->id] ?? 0),
                    'attachments' => $own->filter(fn ($i) => $i->key_image_id)->count(),
                ],
            ];
        })->values()->all());
    }

    /** One order per style picked, all under the buyer's PO, numbered from the buyer's series. */
    public function confirm(Request $request): JsonResponse
    {
        $this->allow('merchandise.order.manage');
        $company = $this->caller()->companyId;
        $buyer = (string) $request->input('buyer_id', '');
        $data = $request->validate([
            'buyer_id' => ['required', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'po_no' => ['required', 'string', 'max:64'],
            'order_date' => ['required', 'date'],
            'ship_date' => ['required', 'date', 'after_or_equal:order_date'],
            'currency' => ['nullable', Rule::in(self::CURRENCIES)],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.style_id' => ['required', 'distinct', Rule::exists('merch_styles', 'id')->where('company_id', $company)->where('buyer_id', $buyer)->where('status', 'STYLE_APPROVED')],
            'lines.*.qty' => ['required', 'integer', 'min:1', 'max:100000000'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ], [
            'buyer_id.required' => __('merchandise.order_confirmation.buyer_required'),
            'po_no.required' => __('merchandise.order_confirmation.po_required'),
            'order_date.required' => __('merchandise.order_confirmation.order_date_required'),
            'ship_date.required' => __('merchandise.order_confirmation.ship_date_required'),
            'ship_date.after_or_equal' => __('merchandise.order.ship_before_order'),
            'lines.required' => __('merchandise.order_confirmation.style_required'),
            'lines.min' => __('merchandise.order_confirmation.style_required'),
            'lines.*.style_id.exists' => __('merchandise.order_confirmation.style_not_approved'),
            'lines.*.qty.required' => __('merchandise.order_confirmation.qty_required'),
            'lines.*.qty.min' => __('merchandise.order_confirmation.qty_required'),
        ]);

        $created = DB::transaction(function () use ($data): array {
            $out = [];
            foreach ($data['lines'] as $line) {
                $style = Style::query()->whereKey($line['style_id'])->firstOrFail();
                $out[] = Order::query()->create([
                    'order_no' => OrderNumber::next($data['buyer_id']),
                    'job_no' => trim($data['po_no']),
                    'po_no' => trim($data['po_no']),
                    'style_id' => $style->id,
                    'style_no' => $style->styleNo(),
                    'sub_style' => $style->styleNo(),
                    'buyer_id' => $style->buyer_id,
                    'buyer_office_id' => $style->buyer_office_id,
                    'inquiry_id' => $style->inquiry_id,
                    'order_type' => Order::query()->where('style_id', $style->id)->exists() ? 'REPEAT' : 'BULK_NEW',
                    'gmt_type' => $style->description,
                    'item' => $this->itemOf($style),
                    'order_date' => $data['order_date'],
                    'conf_date' => $data['order_date'],
                    'ship_date' => $data['ship_date'],
                    'ship_country_id' => $style->origin_country_id,
                    'total_qty' => (int) $line['qty'],
                    'unit' => in_array($style->qty_unit, Order::UNITS, true) ? $style->qty_unit : 'PCS',
                    'pcs_per_unit' => max(1, (int) $style->pcs_per_unit),
                    'unit_price' => $line['unit_price'] ?? null,
                    'currency' => $data['currency'] ?? 'USD',
                    'status' => 'CONFIRMED',
                    'created_by' => $this->caller()->auditUserId(),
                ]);
            }

            return $out;
        });
        $resource = app(OrderResource::class);

        return ApiResponse::created(array_map(fn (Order $o): array => $resource->present($o), $created));
    }

    /** The style sheet's Order Confirmation tab: the style's orders and what the form picks from. */
    public function ofStyle(string $style): JsonResponse
    {
        $this->allow('merchandise.order.view');
        $row = Style::query()->whereKey($style)->firstOrFail();

        return ApiResponse::ok([
            'rows' => $this->ordersOf($row),
            'style' => [
                'id' => $row->id, 'style_no' => $row->styleNo(), 'buyer_id' => $row->buyer_id, 'inquiry_id' => $row->inquiry_id,
                'qty_unit' => in_array($row->qty_unit, Order::UNITS, true) ? $row->qty_unit : 'PCS', 'pcs_per_unit' => max(1, (int) $row->pcs_per_unit), 'origin_country_id' => $row->origin_country_id,
            ],
            'options' => [
                'company' => Company::query()->whereKey($this->caller()->companyId)->value('name'),
                'factories' => Factory::query()->orderBy('name')->get(['id', 'name'])->map(fn ($f): array => ['value' => $f->id, 'label' => $f->name])->all(),
                'countries' => OriginCountry::query()->where('active', true)->orderBy('name')->get(['id', 'code', 'name'])->map(fn ($c): array => ['value' => $c->id, 'label' => "{$c->name} ({$c->code})"])->all(),
                'inquiries' => Inquiry::query()->where('buyer_id', $row->buyer_id)->where('status', 'CONFIRMED')->orderByDesc('inquiry_no')->get(['id', 'inquiry_no', 'style_no'])
                    ->map(fn (Inquiry $i): array => ['value' => $i->id, 'label' => "{$i->inquiry_no} ({$i->style_no})"])->all(),
                'tna_templates' => TnaTemplate::query()->orderBy('code')->get(['id', 'code'])->map(fn ($t): array => ['value' => $t->id, 'label' => $t->code])->all(),
                'types' => Order::TYPES, 'units' => Order::UNITS, 'statuses' => Order::STATUSES, 'currencies' => array_values(array_diff(self::CURRENCIES, ['BDT'])),
            ],
        ]);
    }

    public function storeForStyle(Request $request, string $style): JsonResponse
    {
        $this->allow('merchandise.order.manage');
        $row = Style::query()->whereKey($style)->firstOrFail();
        $data = $this->validated($request, $row, null);

        DB::transaction(function () use ($row, $data): void {
            Order::query()->create($data + [
                'order_no' => OrderNumber::next($row->buyer_id),
                'style_id' => $row->id,
                'style_no' => $row->styleNo(),
                'buyer_id' => $row->buyer_id,
                'buyer_office_id' => $row->buyer_office_id,
                'gmt_type' => $row->description,
                'item' => $this->itemOf($row),
                'order_date' => $data['conf_date'],
                'created_by' => $this->caller()->auditUserId(),
            ]);
        });

        return ApiResponse::created($this->ordersOf($row));
    }

    public function updateForStyle(Request $request, string $order): JsonResponse
    {
        $this->allow('merchandise.order.manage');
        $existing = Order::query()->whereKey($order)->whereNotNull('style_id')->firstOrFail();
        $row = Style::query()->whereKey($existing->style_id)->firstOrFail();
        $data = $this->validated($request, $row, $existing);
        // With a breakdown, the quantity is the breakdown's.
        if (OrderSize::query()->where('order_id', $existing->id)->exists()) {
            $data['total_qty'] = (int) OrderSize::query()->where('order_id', $existing->id)->sum('qty');
        }
        app(OrderResource::class)->guardProjection($existing, $data);
        $existing->update($data);

        return ApiResponse::ok($this->ordersOf($row));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, Style $style, ?Order $order): array
    {
        $company = $this->caller()->companyId;
        $data = $request->validate([
            'order_type' => ['required', Rule::in(Order::TYPES)],
            'job_no' => ['required', 'string', 'max:64'],
            'inquiry_id' => ['nullable', Rule::exists('merch_inquiries', 'id')->where('company_id', $company)->where('buyer_id', $style->buyer_id)],
            'sales_contract_no' => ['nullable', 'string', 'max:64'],
            'po_no' => ['nullable', 'string', 'max:64'],
            'sub_style' => ['nullable', 'string', 'max:96'],
            'conf_date' => ['required', 'date'],
            'projection_only' => ['sometimes', 'boolean'],
            'is_multi' => ['sometimes', 'boolean'],
            'ship_date' => ['required', 'date', 'after_or_equal:conf_date'],
            'total_qty' => ['required', 'integer', 'min:1', 'max:100000000'],
            'unit' => ['required', Rule::in(Order::UNITS)],
            'pcs_per_unit' => ['required', 'integer', 'min:1', 'max:1000'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'currency' => ['required', Rule::in(self::CURRENCIES)],
            'exchange_rate' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'factory_id' => ['nullable', Rule::exists('factories', 'id')->where('company_id', $company)],
            'status' => ['required', Rule::in(Order::STATUSES)],
            'tna_template_id' => ['nullable', Rule::exists('tna_templates', 'id')->where('company_id', $company)],
            'ship_country_id' => ['required', Rule::exists('origin_countries', 'id')->where('company_id', $company)],
        ], [
            'job_no.required' => __('merchandise.order_confirmation.job_required'),
            'conf_date.required' => __('merchandise.order_confirmation.conf_date_required'),
            'ship_date.required' => __('merchandise.order_confirmation.ship_date_required'),
            'ship_date.after_or_equal' => __('merchandise.order_confirmation.ship_before_conf'),
            'total_qty.required' => __('merchandise.order.qty_required'),
            'unit_price.required' => __('merchandise.order_confirmation.price_required'),
            'exchange_rate.required' => __('merchandise.order_confirmation.rate_required'),
            'ship_country_id.required' => __('merchandise.order_confirmation.delivery_required'),
        ]);
        foreach (['job_no', 'sales_contract_no', 'po_no', 'sub_style'] as $column) {
            if (isset($data[$column])) {
                $data[$column] = trim($data[$column]) === '' ? null : trim($data[$column]);
            }
        }
        $data['projection_only'] = (bool) ($data['projection_only'] ?? false);
        $data['is_multi'] = (bool) ($data['is_multi'] ?? false);

        return $data;
    }

    /** @return list<array<string, mixed>> */
    private function ordersOf(Style $style): array
    {
        $resource = app(OrderResource::class);

        return Order::query()->where('style_id', $style->id)->orderBy('conf_date')->orderBy('order_no')->get()->map(fn (Order $o): array => $resource->present($o))->values()->all();
    }

    private function itemOf(Style $style): ?string
    {
        $itemId = StyleItemStyle::query()->where('style_id', $style->id)->value('style_item_id');
        $item = $itemId ? StyleItem::query()->whereKey($itemId)->first(['full_name', 'short_name']) : null;

        return $item ? ($item->full_name ?: $item->short_name) : null;
    }
}
