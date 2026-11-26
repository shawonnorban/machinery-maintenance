<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Resources;

use App\Modules\BuyerGateway\Models\Complaint;
use App\Modules\BuyerGateway\Support\PortalBook;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Complaint & Claim Register (AnnotechERP 25/04): complaints a buyer raises
 * against a shipment — the cause, the claim value (once the buyer has
 * quantified it, USD), the state and the resolution. The reference is unique;
 * left blank it takes the year's next number (CMP-2026-001). Settled or
 * rejected needs the resolution written down.
 */
class ComplaintResource extends BuyerGatewayResource
{
    protected function area(): string
    {
        return 'engagement';
    }

    public function key(): string
    {
        return 'bg-complaints';
    }

    public function model(): string
    {
        return Complaint::class;
    }

    public function columns(): array
    {
        return ['reference', 'buyer_id', 'shipment', 'cause', 'claim_value', 'raised_on', 'state', 'resolution'];
    }

    public function defaults(): array
    {
        return ['state' => 'OPEN'];
    }

    public function rules(?Model $row): array
    {
        $resolution = fn () => trim((string) (request()->has('resolution') ? request()->input('resolution') : $row?->resolution));
        $state = fn () => request()->has('state') ? request()->input('state') : ($row?->state ?? 'OPEN');

        return [
            'reference' => ['nullable', 'string', 'max:40', function (string $attribute, mixed $v, Closure $fail) use ($row): void {
                if ($v && Complaint::query()->whereRaw('upper(reference) = ?', [Str::upper(trim((string) $v))])->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists()) {
                    $fail(__('buyergateway.v.complaint_taken'));
                }
            }],
            'buyer_id' => $this->buyerRule(! $row),
            'shipment' => ['nullable', 'string', 'max:60'],
            'cause' => ['nullable', Rule::in(PortalBook::CAUSES)],
            'claim_value' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'raised_on' => ['nullable', 'date_format:Y-m-d'],
            'state' => ['sometimes', Rule::in(PortalBook::COMPLAINT_STATES), function (string $attribute, mixed $v, Closure $fail) use ($resolution): void {
                if (in_array($v, ['SETTLED', 'REJECTED'], true) && $resolution() === '') {
                    $fail(__('buyergateway.v.resolution'));
                }
            }],
            // A settled or rejected complaint keeps its resolution (blanking it is refused).
            'resolution' => [Rule::requiredIf(fn () => request()->has('resolution') && in_array($state(), ['SETTLED', 'REJECTED'], true)), 'nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['buyer_id.required' => __('buyergateway.v.buyer'), 'resolution.required' => __('buyergateway.v.resolution')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (array_key_exists('reference', $data) || ! $row) {
            $ref = Str::upper(trim((string) ($data['reference'] ?? '')));
            $data['reference'] = $ref !== '' ? $ref : ($row?->reference ?? $this->nextNumber('CMP', 'reference'));
        }
        if (isset($data['shipment'])) {
            $data['shipment'] = Str::upper(trim($data['shipment']));
        }
        if (! $row) {
            $data['raised_on'] ??= CarbonImmutable::now('Asia/Dhaka')->toDateString();
        }

        return $data;
    }
}
