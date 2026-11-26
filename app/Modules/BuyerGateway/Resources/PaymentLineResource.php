<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Resources;

use App\Modules\BuyerGateway\Models\PaymentLine;
use App\Modules\BuyerGateway\Support\PortalBook;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Payment Progress View (AnnotechERP 25/03): per buyer, what has been billed
 * and received (USD), the payment terms and the next due date. Outstanding is
 * billed less received; the ageing band follows the due date (not due, 1–30,
 * 31–60, 61–90, over 90 days past it). One line per buyer; nothing received
 * beyond what was billed.
 */
class PaymentLineResource extends BuyerGatewayResource
{
    protected function area(): string
    {
        return 'billing';
    }

    public function key(): string
    {
        return 'bg-payment-lines';
    }

    public function model(): string
    {
        return PaymentLine::class;
    }

    public function columns(): array
    {
        return ['buyer_id', 'billed', 'received', 'payment_terms', 'next_due'];
    }

    public function defaults(): array
    {
        return ['billed' => 0, 'received' => 0];
    }

    public function rules(?Model $row): array
    {
        $num = fn (string $f) => (float) (request()->has($f) ? request()->input($f) : $row?->getAttribute($f));

        return [
            'buyer_id' => [...$this->buyerRule(! $row), $this->unique($row, ['buyer_id'], 'buyergateway.v.payment_taken')],
            'billed' => ['sometimes', 'numeric', 'min:0', 'max:999999999999', function (string $attribute, mixed $v, Closure $fail) use ($num): void {
                // Lowering what was billed below what already came in.
                if (! request()->has('received') && (float) $v + 0.004 < $num('received')) {
                    $fail(__('buyergateway.v.received_over_billed'));
                }
            }],
            'received' => ['sometimes', 'numeric', 'min:0', 'max:999999999999', function (string $attribute, mixed $v, Closure $fail) use ($num): void {
                if ((float) $v > $num('billed') + 0.004) {
                    $fail(__('buyergateway.v.received_over_billed'));
                }
            }],
            'payment_terms' => ['nullable', Rule::in(PortalBook::PAYMENT_TERMS)],
            'next_due' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return ['buyer_id.required' => __('buyergateway.v.buyer')];
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['outstanding'] = round((float) $out['billed'] - (float) $out['received'], 2);
        $out['ageing'] = PortalBook::ageing($out['next_due'], $out['outstanding'], CarbonImmutable::now('Asia/Dhaka')->toDateString());

        return $out;
    }
}
