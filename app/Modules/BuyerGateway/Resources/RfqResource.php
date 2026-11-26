<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Resources;

use App\Modules\BuyerGateway\Models\Rfq;
use App\Modules\BuyerGateway\Support\PortalBook;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Quotation Request Inbox (AnnotechERP 25/03): requests for quotation from
 * buyers, before they become an inquiry — reference, buyer, product,
 * quantity, target price, received and respond-by dates, and state (new,
 * under costing, quoted, declined, converted). The reference is unique; left
 * blank it takes the year's next number (RFQ-2026-001). The answer is not
 * due before the request arrived.
 */
class RfqResource extends BuyerGatewayResource
{
    protected function area(): string
    {
        return 'billing';
    }

    public function key(): string
    {
        return 'bg-rfqs';
    }

    public function model(): string
    {
        return Rfq::class;
    }

    public function columns(): array
    {
        return ['reference', 'buyer_id', 'product_id', 'quantity', 'target_price', 'received_on', 'respond_by', 'state'];
    }

    public function defaults(): array
    {
        return ['state' => 'NEW', 'quantity' => 0];
    }

    public function rules(?Model $row): array
    {
        $date = function (string $f) use ($row) {
            $d = request()->has($f) ? request()->input($f) : $row?->getAttribute($f);

            return $d instanceof \DateTimeInterface ? $d->format('Y-m-d') : $d;
        };

        return [
            'reference' => ['nullable', 'string', 'max:40', function (string $attribute, mixed $v, Closure $fail) use ($row): void {
                if ($v && Rfq::query()->whereRaw('upper(reference) = ?', [Str::upper(trim((string) $v))])->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists()) {
                    $fail(__('buyergateway.v.rfq_taken'));
                }
            }],
            'buyer_id' => $this->buyerRule(! $row),
            'product_id' => ['nullable', 'string', Rule::exists('ws_garment_items', 'id')->where('company_id', $this->company())],
            'quantity' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'target_price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'received_on' => ['nullable', 'date_format:Y-m-d'],
            'respond_by' => ['nullable', 'date_format:Y-m-d', function (string $attribute, mixed $v, Closure $fail) use ($date): void {
                $received = $date('received_on');
                if ($v && $received && $v < $received) {
                    $fail(__('buyergateway.v.respond_before_received'));
                }
            }],
            'state' => ['sometimes', Rule::in(PortalBook::RFQ_STATES)],
        ];
    }

    public function messages(): array
    {
        return ['buyer_id.required' => __('buyergateway.v.buyer')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $today = CarbonImmutable::now('Asia/Dhaka');
        if (array_key_exists('reference', $data) || ! $row) {
            $ref = Str::upper(trim((string) ($data['reference'] ?? '')));
            $data['reference'] = $ref !== '' ? $ref : ($row?->reference ?? $this->nextReference($today->format('Y')));
        }
        if (! $row) {
            $data['received_on'] ??= $today->toDateString();
        }

        return $data;
    }

    public function present(Model $row): array
    {
        return parent::present($row) + ['product' => PortalBook::products()[$row->getAttribute('product_id')] ?? null];
    }

    /** RFQ-2026-001, -002 … the year's next. */
    private function nextReference(string $year): string
    {
        $prefix = "RFQ-{$year}-";
        $last = Rfq::query()->where('reference', 'like', $prefix.'%')->pluck('reference')->map(fn ($r) => (int) substr($r, strlen($prefix)))->max() ?? 0;

        return $prefix.str_pad((string) ($last + 1), 3, '0', STR_PAD_LEFT);
    }
}
