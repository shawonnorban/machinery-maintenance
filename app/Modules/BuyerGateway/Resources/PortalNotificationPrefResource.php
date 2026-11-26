<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Resources;

use App\Modules\BuyerGateway\Models\PortalNotificationPref;
use App\Modules\BuyerGateway\Support\PortalBook;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Notification Settings (AnnotechERP 25/01): how a portal event reaches a
 * buyer — e-mail, in-portal, both or muted; at once or in a digest; to whom.
 * Set per buyer per event (one preference each). A muted event is off, and
 * switching it on is refused until it has a channel again.
 */
class PortalNotificationPrefResource extends BuyerGatewayResource
{
    public function key(): string
    {
        return 'bg-notification-prefs';
    }

    public function model(): string
    {
        return PortalNotificationPref::class;
    }

    public function columns(): array
    {
        return ['event', 'buyer_id', 'channel', 'digest', 'recipients', 'enabled', 'set_on'];
    }

    public function booleans(): array
    {
        return ['enabled'];
    }

    public function defaults(): array
    {
        return ['channel' => 'EMAIL', 'digest' => 'IMMEDIATE', 'recipients' => 'ALL', 'enabled' => true];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'event' => [$required, Rule::in(PortalBook::EVENTS), $this->unique($row, ['buyer_id', 'event'], 'buyergateway.v.pref_taken')],
            'buyer_id' => $this->buyerRule(! $row),
            'channel' => ['sometimes', Rule::in(PortalBook::CHANNELS)],
            'digest' => ['sometimes', Rule::in(PortalBook::DIGESTS)],
            'recipients' => ['sometimes', Rule::in(PortalBook::RECIPIENTS)],
            'set_on' => ['nullable', 'date_format:Y-m-d'],
            'enabled' => ['sometimes', 'boolean', function (string $attribute, mixed $value, Closure $fail) use ($row): void {
                if ($value && request()->input('channel', $row?->channel) === 'MUTED' && ! request()->has('channel')) {
                    $fail(__('buyergateway.v.muted'));
                }
            }],
        ];
    }

    public function messages(): array
    {
        return ['event.required' => __('buyergateway.v.event'), 'buyer_id.required' => __('buyergateway.v.buyer')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (($data['channel'] ?? $row?->channel) === 'MUTED') {
            $data['enabled'] = false;
        }
        if (! $row && empty($data['set_on'])) {
            $data['set_on'] = CarbonImmutable::now('Asia/Dhaka')->toDateString();
        }

        return $data;
    }
}
