<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Resources;

use App\Modules\BuyerGateway\Models\Announcement;
use App\Modules\BuyerGateway\Support\PortalBook;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Portal Announcements (AnnotechERP 25/04): notices published to the portal —
 * holidays, capacity, policy, system — for an audience (all buyers, strategic
 * buyers, one buyer, logistics contacts) and a window. A draft stays a
 * draft; otherwise the state follows the window: scheduled before it opens,
 * live inside it, expired after it. "One buyer" names the buyer.
 */
class AnnouncementResource extends BuyerGatewayResource
{
    protected function area(): string
    {
        return 'engagement';
    }

    public function key(): string
    {
        return 'bg-announcements';
    }

    public function model(): string
    {
        return Announcement::class;
    }

    public function columns(): array
    {
        return ['title', 'audience', 'buyer_id', 'kind', 'published_on', 'expires_on', 'reads', 'draft', 'body'];
    }

    public function booleans(): array
    {
        return ['draft'];
    }

    public function defaults(): array
    {
        return ['audience' => 'ALL', 'reads' => 0, 'draft' => false];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $value = function (string $f) use ($row) {
            $d = request()->has($f) ? request()->input($f) : $row?->getAttribute($f);

            return $d instanceof \DateTimeInterface ? $d->format('Y-m-d') : $d;
        };

        return [
            'title' => [$required, 'string', 'max:160'],
            'audience' => ['sometimes', Rule::in(PortalBook::AUDIENCES), function (string $attribute, mixed $v, Closure $fail) use ($value): void {
                if ($v === 'ONE' && ! $value('buyer_id')) {
                    $fail(__('buyergateway.v.one_buyer'));
                }
            }],
            // Clearing the buyer of a one-buyer announcement is refused too.
            'buyer_id' => [Rule::requiredIf(fn () => request()->has('buyer_id') && $value('audience') === 'ONE'), 'nullable', 'string', Rule::exists('buyers', 'id')->where('company_id', $this->company())],
            'kind' => ['nullable', Rule::in(PortalBook::NOTICE_KINDS)],
            'published_on' => ['nullable', 'date_format:Y-m-d'],
            'expires_on' => ['nullable', 'date_format:Y-m-d', function (string $attribute, mixed $v, Closure $fail) use ($value): void {
                $from = $value('published_on');
                if ($v && $from && $v < $from) {
                    $fail(__('buyergateway.v.expires_before_published'));
                }
            }],
            'reads' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'state' => ['sometimes', Rule::in(PortalBook::NOTICE_STATES)],
            'body' => ['nullable', 'string', 'max:4000'],
        ];
    }

    public function messages(): array
    {
        return ['title.required' => __('buyergateway.v.notice_title'), 'buyer_id.required' => __('buyergateway.v.one_buyer')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['title'])) {
            $data['title'] = trim($data['title']);
        }
        if (array_key_exists('state', $data)) {
            // Only Draft is kept; Live, Scheduled and Expired follow the window.
            $data['draft'] = $data['state'] === 'DRAFT';
            unset($data['state']);
        }
        if (($data['audience'] ?? $row?->audience) !== 'ONE') {
            $data['buyer_id'] = null;
        }
        if (! $row) {
            $data['published_on'] ??= CarbonImmutable::now('Asia/Dhaka')->toDateString();
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $today = CarbonImmutable::now('Asia/Dhaka')->toDateString();
        $out['state'] = match (true) {
            (bool) $out['draft'] => 'DRAFT',
            $out['published_on'] && $out['published_on'] > $today => 'SCHEDULED',
            $out['expires_on'] && $out['expires_on'] < $today => 'EXPIRED',
            default => 'LIVE',
        };

        return $out;
    }
}
