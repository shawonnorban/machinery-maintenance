<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Resources;

use App\Modules\BuyerGateway\Models\BuyerRequest;
use App\Modules\BuyerGateway\Support\PortalBook;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Buyer Request Inbox (AnnotechERP 25/04): what a buyer asks for — order
 * change, sample, document, price or shipment query — routed to the desk that
 * answers it, with priority and state. Routing is the point: an unrouted
 * request cannot be in progress or answered. Its age counts from the day it
 * was raised until it is closed.
 */
class BuyerRequestResource extends BuyerGatewayResource
{
    protected function area(): string
    {
        return 'engagement';
    }

    public function key(): string
    {
        return 'bg-requests';
    }

    public function model(): string
    {
        return BuyerRequest::class;
    }

    public function columns(): array
    {
        return ['subject', 'buyer_id', 'kind', 'routed_to', 'raised_on', 'priority', 'state', 'closed_on'];
    }

    public function defaults(): array
    {
        return ['routed_to' => 'UNROUTED', 'priority' => 'NORMAL', 'state' => 'NEW'];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $routed = fn () => request()->has('routed_to') ? request()->input('routed_to') : ($row?->routed_to ?? 'UNROUTED');

        return [
            'subject' => [$required, 'string', 'max:200'],
            'buyer_id' => $this->buyerRule(! $row),
            'kind' => ['nullable', Rule::in(PortalBook::REQUEST_KINDS)],
            'routed_to' => ['sometimes', Rule::in(PortalBook::DESKS)],
            'raised_on' => ['nullable', 'date_format:Y-m-d'],
            'priority' => ['sometimes', Rule::in(PortalBook::PRIORITIES)],
            'state' => ['sometimes', Rule::in(PortalBook::REQUEST_STATES), function (string $attribute, mixed $v, Closure $fail) use ($routed): void {
                if (in_array($v, ['IN_PROGRESS', 'ANSWERED'], true) && $routed() === 'UNROUTED') {
                    $fail(__('buyergateway.v.route_first'));
                }
            }],
        ];
    }

    public function messages(): array
    {
        return ['subject.required' => __('buyergateway.v.subject'), 'buyer_id.required' => __('buyergateway.v.buyer')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $today = CarbonImmutable::now('Asia/Dhaka')->toDateString();
        if (isset($data['subject'])) {
            $data['subject'] = trim($data['subject']);
        }
        if (! $row) {
            $data['raised_on'] ??= $today;
        }
        if (isset($data['state'])) {
            $data['closed_on'] = $data['state'] === 'CLOSED' ? ($row?->closed_on?->format('Y-m-d') ?? $today) : null;
        }

        return $data;
    }

    /** Age in days: from raised to closed, or to today. */
    public function present(Model $row): array
    {
        $out = parent::present($row);
        $end = $out['closed_on'] ?? CarbonImmutable::now('Asia/Dhaka')->toDateString();
        $out['age'] = $out['raised_on'] ? max(0, (int) CarbonImmutable::parse($out['raised_on'])->diffInDays(CarbonImmutable::parse($end))) : null;

        return $out;
    }
}
