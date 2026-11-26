<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\OrderSize;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Merchandise\Support\OrderNumber;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Style & Order → Order Register → Records, New order and Edit order
 * (AnnotechERP 06/07). Left blank, the order number is the buyer's next one
 * (LPP-2026-000001). A style picked from the development styles brings its
 * style number and buyer when those are left blank. Total price and lead time
 * are worked out from the quantity, the unit price and the dates, never typed;
 * once the order has a colour × size breakdown, its quantity is their sum.
 */
class OrderResource extends ResourceDefinition
{
    public function key(): string
    {
        return 'merch-orders';
    }

    public function model(): string
    {
        return Order::class;
    }

    public function viewPermission(): string
    {
        return 'merchandise.order.view';
    }

    public function managePermission(): string
    {
        return 'merchandise.order.manage';
    }

    public function columns(): array
    {
        return [
            'order_no', 'job_no', 'style_id', 'style_no', 'work_style_no', 'buyer_id', 'buyer_office_id', 'factory_id', 'order_type', 'item', 'gmt_type',
            'order_date', 'conf_date', 'ship_date', 'ship_country_id', 'total_qty', 'unit', 'unit_price', 'currency', 'status',
        ];
    }

    public function defaults(): array
    {
        return ['order_type' => 'BULK_NEW', 'unit' => 'PCS', 'currency' => 'USD', 'status' => 'CONFIRMED'];
    }

    public function query(): Builder
    {
        return Order::query()->latest('order_date')->latest('order_no');
    }

    public function deletable(): bool
    {
        return true;
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = app(TenantContext::class)->companyId();

        return [
            'order_no' => ['nullable', 'string', 'max:32', Rule::unique('merch_orders', 'order_no')->where('company_id', $company)->ignore($row?->getKey())],
            'job_no' => [$required, 'string', 'max:64'],
            'style_id' => ['nullable', Rule::exists('merch_styles', 'id')->where('company_id', $company)],
            'style_no' => [$row ? 'sometimes' : 'required_without:style_id', 'nullable', 'string', 'max:96'],
            'work_style_no' => ['nullable', 'string', 'max:96'],
            'buyer_id' => [$row ? 'sometimes' : 'required_without:style_id', 'nullable', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'buyer_office_id' => ['nullable', Rule::exists('buyer_offices', 'id')->where('company_id', $company)],
            'factory_id' => ['nullable', Rule::exists('factories', 'id')->where('company_id', $company)],
            'order_type' => ['nullable', Rule::in(Order::TYPES)],
            'item' => ['nullable', 'string', 'max:128'],
            'gmt_type' => ['nullable', 'string', 'max:128'],
            'order_date' => ['nullable', 'date'],
            'conf_date' => ['nullable', 'date'],
            'ship_date' => ['nullable', 'date'],
            'ship_country_id' => ['nullable', Rule::exists('origin_countries', 'id')->where('company_id', $company)],
            'total_qty' => [$required, 'integer', 'min:1', 'max:100000000'],
            'unit' => ['nullable', Rule::in(Order::UNITS)],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'currency' => ['nullable', 'string', 'regex:/^[A-Za-z]{3}$/'],
            'status' => ['nullable', Rule::in(Order::STATUSES)],
        ];
    }

    public function messages(): array
    {
        return [
            'job_no.required' => __('merchandise.order.job_required'),
            'style_no.required_without' => __('merchandise.order.style_required'),
            'buyer_id.required_without' => __('merchandise.order.buyer_required'),
            'total_qty.required' => __('merchandise.order.qty_required'),
            'order_no.unique' => __('merchandise.order.number_taken'),
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        foreach (['order_no', 'job_no', 'style_no', 'work_style_no', 'item', 'gmt_type'] as $column) {
            if (array_key_exists($column, $data) && is_string($data[$column])) {
                $data[$column] = trim($data[$column]) === '' ? null : trim($data[$column]);
            }
        }
        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        // A picked style brings what is left blank: its number, buyer and account.
        $styleId = array_key_exists('style_id', $data) ? $data['style_id'] : $row?->getAttribute('style_id');
        $style = $styleId ? Style::query()->whereKey($styleId)->first() : null;
        if ($style) {
            $data['style_no'] ??= $row?->getAttribute('style_no') ?? $style->styleNo();
            $data['buyer_id'] ??= $row?->getAttribute('buyer_id') ?? $style->buyer_id;
            $data['buyer_office_id'] ??= $row?->getAttribute('buyer_office_id') ?? $style->buyer_office_id;
            if ($data['buyer_id'] !== $style->buyer_id) {
                throw ValidationException::withMessages(['style_id' => __('merchandise.order.style_of_other_buyer', ['style' => $style->styleNo()])]);
            }
        }

        $orderDate = $data['order_date'] ?? $row?->getAttribute('order_date')?->format('Y-m-d');
        $shipDate = $data['ship_date'] ?? $row?->getAttribute('ship_date')?->format('Y-m-d');
        if ($orderDate && $shipDate && $shipDate < $orderDate) {
            throw ValidationException::withMessages(['ship_date' => __('merchandise.order.ship_before_order')]);
        }

        // With a breakdown, the quantity is the breakdown's.
        if ($row && OrderSize::query()->where('order_id', $row->getKey())->exists()) {
            $data['total_qty'] = (int) OrderSize::query()->where('order_id', $row->getKey())->sum('qty');
        }

        if ($row) {
            $this->guardProjection($row, $data);
        }

        if ($row === null) {
            $data['order_no'] ??= OrderNumber::next($data['buyer_id']);
            $data['created_by'] = $this->actingUserId();
        } elseif (array_key_exists('order_no', $data) && $data['order_no'] === null) {
            unset($data['order_no']);
        }

        return $data;
    }

    /**
     * A projection with call-offs stays a projection and never projects less
     * than was drawn; a call-off never draws past what its projection has left.
     *
     * @param  array<string, mixed>  $data
     */
    public function guardProjection(Model $row, array $data): void
    {
        $qty = (int) ($data['total_qty'] ?? $row->getAttribute('total_qty'));
        $live = fn () => Order::query()->where('status', '!=', 'CANCELLED');
        $drawn = (int) $live()->where('projection_id', $row->getKey())->sum('total_qty');
        $hasCalls = $live()->where('projection_id', $row->getKey())->exists();
        if ($hasCalls && ($data['order_type'] ?? $row->getAttribute('order_type')) !== 'PROJECTION') {
            throw ValidationException::withMessages(['order_type' => __('merchandise.projection.keeps_type')]);
        }
        if ($hasCalls && $qty < $drawn) {
            throw ValidationException::withMessages(['total_qty' => __('merchandise.projection.below_drawn', ['drawn' => $drawn])]);
        }
        $projectionId = $row->getAttribute('projection_id');
        if ($projectionId) {
            $projected = (int) Order::query()->whereKey($projectionId)->value('total_qty');
            $others = (int) $live()->where('projection_id', $projectionId)->whereKeyNot($row->getKey())->sum('total_qty');
            if ($qty + $others > $projected) {
                throw ValidationException::withMessages(['total_qty' => __('merchandise.projection.over_balance', ['left' => max(0, $projected - $others), 'no' => Order::query()->whereKey($projectionId)->value('order_no')])]);
            }
        }
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $qty = (int) $row->getAttribute('total_qty');
        $price = $row->getAttribute('unit_price');
        $from = $row->getAttribute('conf_date') ?? $row->getAttribute('order_date');
        $ship = $row->getAttribute('ship_date');
        $out['unit_price'] = $price === null ? null : (float) $price;
        $out['total_price'] = $price === null ? null : round($qty * (float) $price, 2);
        $out['lead_time'] = $from && $ship ? (int) $from->diffInDays($ship, false) : null;
        $out['buyer'] = Buyer::query()->whereKey($row->getAttribute('buyer_id'))->value('short_name');
        $out['factory'] = $row->getAttribute('factory_id') ? Factory::query()->whereKey($row->getAttribute('factory_id'))->value('name') : null;
        $out['ship_country'] = $row->getAttribute('ship_country_id') ? OriginCountry::query()->whereKey($row->getAttribute('ship_country_id'))->value('name') : null;
        $out['size_lines'] = OrderSize::query()->where('order_id', $row->getKey())->count();
        foreach (['po_no', 'sub_style', 'inquiry_id', 'sales_contract_no', 'projection_only', 'is_multi', 'pcs_per_unit', 'exchange_rate', 'tna_template_id', 'projection_id'] as $column) {
            $out[$column] = $row->getAttribute($column);
        }

        return $out;
    }
}
