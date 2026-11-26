<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Resources;

use App\Modules\BuyerGateway\Models\PortalInvoice;
use App\Modules\BuyerGateway\Support\PortalBook;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Invoice Lookup (AnnotechERP 25/03): commercial invoices published to the
 * buyer, exactly as issued — number (unique), buyer, the order it is against,
 * amount (USD), issued and due dates, and state. Published means visible to
 * the buyer; a published invoice past its due date is shown overdue.
 */
class PortalInvoiceResource extends BuyerGatewayResource
{
    protected function area(): string
    {
        return 'billing';
    }

    public function key(): string
    {
        return 'bg-invoices';
    }

    public function model(): string
    {
        return PortalInvoice::class;
    }

    public function columns(): array
    {
        return ['invoice_no', 'buyer_id', 'order_no', 'amount', 'issued_on', 'due_on', 'state'];
    }

    public function defaults(): array
    {
        return ['state' => 'PUBLISHED', 'amount' => 0];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $date = function (string $f) use ($row) {
            $d = request()->has($f) ? request()->input($f) : $row?->getAttribute($f);

            return $d instanceof \DateTimeInterface ? $d->format('Y-m-d') : $d;
        };

        return [
            'invoice_no' => [$required, 'string', 'max:40', function (string $attribute, mixed $v, Closure $fail) use ($row): void {
                if (PortalInvoice::query()->whereRaw('upper(invoice_no) = ?', [Str::upper(trim((string) $v))])->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists()) {
                    $fail(__('buyergateway.v.invoice_taken'));
                }
            }],
            'buyer_id' => $this->buyerRule(! $row),
            'order_no' => ['nullable', 'string', 'max:60'],
            'amount' => ['sometimes', 'numeric', 'min:0', 'max:999999999999'],
            'issued_on' => ['nullable', 'date_format:Y-m-d'],
            'due_on' => ['nullable', 'date_format:Y-m-d', function (string $attribute, mixed $v, Closure $fail) use ($date): void {
                $issued = $date('issued_on');
                if ($v && $issued && $v < $issued) {
                    $fail(__('buyergateway.v.due_before_issued'));
                }
            }],
            'state' => ['sometimes', Rule::in(PortalBook::INVOICE_STATES)],
        ];
    }

    public function messages(): array
    {
        return ['invoice_no.required' => __('buyergateway.v.invoice'), 'buyer_id.required' => __('buyergateway.v.buyer')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        foreach (['invoice_no', 'order_no'] as $f) {
            if (isset($data[$f])) {
                $data[$f] = Str::upper(trim($data[$f]));
            }
        }
        if (! $row) {
            $data['issued_on'] ??= CarbonImmutable::now('Asia/Dhaka')->toDateString();
        }

        return $data;
    }

    /** A published invoice past its due date reads overdue. */
    public function present(Model $row): array
    {
        $out = parent::present($row);
        $today = CarbonImmutable::now('Asia/Dhaka')->toDateString();
        if ($out['state'] === 'PUBLISHED' && $out['due_on'] && $out['due_on'] < $today) {
            $out['state'] = 'OVERDUE';
        }

        return $out;
    }
}
