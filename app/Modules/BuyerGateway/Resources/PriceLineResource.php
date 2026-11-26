<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Resources;

use App\Modules\BuyerGateway\Models\PriceLine;
use App\Modules\BuyerGateway\Support\PortalBook;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Buyer Price Schedules (AnnotechERP 25/03): the agreed prices a buyer sees —
 * product, season, price, incoterm, minimum quantity and the validity window
 * (published to the buyer only once it opens). The window cannot end before
 * it starts.
 */
class PriceLineResource extends BuyerGatewayResource
{
    protected function area(): string
    {
        return 'billing';
    }

    public function key(): string
    {
        return 'bg-price-lines';
    }

    public function model(): string
    {
        return PriceLine::class;
    }

    public function columns(): array
    {
        return ['product', 'buyer_id', 'season', 'price', 'incoterm', 'min_qty', 'valid_from', 'valid_to'];
    }

    public function defaults(): array
    {
        return ['price' => 0, 'min_qty' => 0];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $date = function (string $f) use ($row) {
            $d = request()->has($f) ? request()->input($f) : $row?->getAttribute($f);

            return $d instanceof \DateTimeInterface ? $d->format('Y-m-d') : $d;
        };

        return [
            'product' => [$required, 'string', 'max:160'],
            'buyer_id' => $this->buyerRule(! $row),
            'season' => ['nullable', Rule::in(PortalBook::seasons())],
            'price' => ['sometimes', 'numeric', 'min:0', 'max:1000000'],
            'incoterm' => ['nullable', Rule::in(PortalBook::INCOTERMS)],
            'min_qty' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'valid_from' => ['nullable', 'date_format:Y-m-d'],
            'valid_to' => ['nullable', 'date_format:Y-m-d', function (string $attribute, mixed $v, Closure $fail) use ($date): void {
                $from = $date('valid_from');
                if ($v && $from && $v < $from) {
                    $fail(__('buyergateway.v.valid_to_before_from'));
                }
            }],
        ];
    }

    public function messages(): array
    {
        return ['product.required' => __('buyergateway.v.product'), 'buyer_id.required' => __('buyergateway.v.buyer')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['product'])) {
            $data['product'] = trim($data['product']);
        }

        return $data;
    }
}
