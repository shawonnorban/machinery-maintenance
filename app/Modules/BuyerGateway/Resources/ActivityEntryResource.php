<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Resources;

use App\Modules\BuyerGateway\Models\ActivityEntry;
use App\Modules\BuyerGateway\Support\PortalBook;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Portal Activity Trail (AnnotechERP 25/05): every sign-in, download,
 * approval, comment and request made from the buyer's side, timestamped —
 * who, for which buyer, on what, and from which device. The portal writes
 * the trail; entries added here are corrections, so they name the actor and
 * never sit in the future. Newest first.
 */
class ActivityEntryResource extends BuyerGatewayResource
{
    private const TIME = '/^([01]?\d|2[0-3]):([0-5]\d)$/';

    protected function area(): string
    {
        return 'admin';
    }

    public function key(): string
    {
        return 'bg-activity-entries';
    }

    public function model(): string
    {
        return ActivityEntry::class;
    }

    public function columns(): array
    {
        return ['actor', 'buyer_id', 'action', 'target', 'occurred_on', 'occurred_time', 'device'];
    }

    public function query(): Builder
    {
        return ActivityEntry::query()->orderByDesc('occurred_on')->orderByDesc('occurred_time')->orderByDesc('created_at')->orderByDesc('id');
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $now = CarbonImmutable::now('Asia/Dhaka');
        $value = function (string $f) use ($row) {
            $d = request()->has($f) ? request()->input($f) : $row?->getAttribute($f);

            return $d instanceof \DateTimeInterface ? $d->format('Y-m-d') : $d;
        };

        return [
            'actor' => [$required, 'string', 'max:120'],
            'buyer_id' => ['nullable', 'string', Rule::exists('buyers', 'id')->where('company_id', $this->company())],
            'action' => ['nullable', Rule::in(PortalBook::ACTIONS)],
            'target' => ['nullable', 'string', 'max:200'],
            'occurred_on' => ['nullable', 'date_format:Y-m-d', function (string $attribute, mixed $v, Closure $fail) use ($now): void {
                if ($v > $now->toDateString()) {
                    $fail(__('buyergateway.v.activity_future'));
                }
            }],
            'occurred_time' => ['nullable', 'string', 'regex:'.self::TIME, function (string $attribute, mixed $v, Closure $fail) use ($value, $now): void {
                $day = $value('occurred_on') ?: $now->toDateString();
                if ($day === $now->toDateString() && self::pad((string) $v) > $now->format('H:i')) {
                    $fail(__('buyergateway.v.activity_future'));
                }
            }],
            'device' => ['nullable', Rule::in(PortalBook::DEVICES)],
        ];
    }

    public function messages(): array
    {
        return ['actor.required' => __('buyergateway.v.actor'), 'occurred_time.regex' => __('buyergateway.v.time')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $now = CarbonImmutable::now('Asia/Dhaka');
        foreach (['actor', 'target'] as $f) {
            if (isset($data[$f])) {
                $data[$f] = trim($data[$f]);
            }
        }
        if (! empty($data['occurred_time'])) {
            $data['occurred_time'] = self::pad($data['occurred_time']);
        }
        if (! $row) {
            $data['occurred_on'] ??= $now->toDateString();
            $data['occurred_time'] ??= $now->format('H:i');
        } else {
            // The day and time stay set once there is an entry.
            foreach (['occurred_on', 'occurred_time'] as $f) {
                if (array_key_exists($f, $data) && empty($data[$f])) {
                    unset($data[$f]);
                }
            }
        }

        return $data;
    }

    /** 9:41 → 09:41. */
    private static function pad(string $time): string
    {
        return preg_match(self::TIME, trim($time), $m) ? sprintf('%02d:%s', (int) $m[1], $m[2]) : $time;
    }
}
