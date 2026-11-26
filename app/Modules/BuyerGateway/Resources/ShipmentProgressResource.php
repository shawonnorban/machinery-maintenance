<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Resources;

use App\Modules\BuyerGateway\Models\ShipmentProgress;
use App\Modules\BuyerGateway\Support\PortalBook;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Shipment Progress View (AnnotechERP 25/02): booked, gated out, on the water,
 * arrived or delivered, against the buyer's agreed date (not the internal
 * plan date) — order, buyer, mode, quantity and cartons. Arrived and
 * Delivered carry the actual date.
 */
class ShipmentProgressResource extends BuyerGatewayResource
{
    protected function area(): string
    {
        return 'visibility';
    }

    public function key(): string
    {
        return 'bg-shipment-progress';
    }

    public function model(): string
    {
        return ShipmentProgress::class;
    }

    public function columns(): array
    {
        return ['order_no', 'buyer_id', 'mode', 'stage', 'agreed_date', 'actual_date', 'quantity', 'cartons'];
    }

    public function defaults(): array
    {
        return ['stage' => 'BOOKED', 'quantity' => 0, 'cartons' => 0];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $actual = function () use ($row) {
            $d = request()->has('actual_date') ? request()->input('actual_date') : $row?->actual_date;

            return $d instanceof \DateTimeInterface ? $d->format('Y-m-d') : $d;
        };

        return [
            'order_no' => [$required, 'string', 'max:60'],
            'buyer_id' => $this->buyerRule(! $row),
            'mode' => ['nullable', Rule::in(PortalBook::MODES)],
            'stage' => ['sometimes', Rule::in(PortalBook::SHIPMENT_STAGES), function (string $attribute, mixed $v, Closure $fail) use ($actual): void {
                if (in_array($v, ['ARRIVED', 'DELIVERED'], true) && ! $actual()) {
                    $fail(__('buyergateway.v.actual_required'));
                }
            }],
            'agreed_date' => ['nullable', 'date_format:Y-m-d'],
            'actual_date' => ['nullable', 'date_format:Y-m-d'],
            'quantity' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'cartons' => ['sometimes', 'integer', 'min:0', 'max:10000000'],
        ];
    }

    public function messages(): array
    {
        return ['order_no.required' => __('buyergateway.v.order'), 'buyer_id.required' => __('buyergateway.v.buyer')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['order_no'])) {
            $data['order_no'] = Str::upper(trim($data['order_no']));
        }

        return $data;
    }
}
