<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Resources;

use App\Modules\BuyerGateway\Models\UsageLine;
use App\Modules\BuyerGateway\Support\PortalBook;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Portal Usage Insight (AnnotechERP 25/05): what each buyer looks at, how
 * often, and where they stop looking — logins, active users, the page opened
 * most, downloads, the average session (minutes) and adoption (%). Usage is
 * held per buyer per month, the grain the account review uses, so a buyer
 * has one line a month; active users never outnumber logins.
 */
class UsageLineResource extends BuyerGatewayResource
{
    protected function area(): string
    {
        return 'admin';
    }

    public function key(): string
    {
        return 'bg-usage-lines';
    }

    public function model(): string
    {
        return UsageLine::class;
    }

    public function columns(): array
    {
        return ['buyer_id', 'period', 'logins', 'active_users', 'most_opened', 'downloads', 'avg_session', 'adoption'];
    }

    public function defaults(): array
    {
        return ['logins' => 0, 'active_users' => 0, 'downloads' => 0, 'avg_session' => 0, 'adoption' => 0];
    }

    public function rules(?Model $row): array
    {
        $value = fn (string $f) => request()->has($f) ? request()->input($f) : $row?->getAttribute($f);
        $count = ['sometimes', 'integer', 'min:0', 'max:10000000'];

        return [
            'buyer_id' => [...$this->buyerRule(! $row), $this->unique($row, ['buyer_id', 'period'], 'buyergateway.v.usage_taken')],
            'period' => [$row ? 'sometimes' : 'required', Rule::in(array_keys(PortalBook::periods())), function (string $attribute, mixed $v, Closure $fail) use ($row): void {
                // Moving a line to another month: that month must be free too.
                if (! request()->has('buyer_id')) {
                    ($this->unique($row, ['buyer_id', 'period'], 'buyergateway.v.usage_taken'))($attribute, $v, $fail);
                }
            }],
            'logins' => [...$count, function (string $attribute, mixed $v, Closure $fail) use ($value): void {
                // Lowering the logins below the users already counted is the same mistake.
                if ((int) $v < (int) $value('active_users')) {
                    $fail(__('buyergateway.v.users_over_logins'));
                }
            }],
            'active_users' => [...$count, function (string $attribute, mixed $v, Closure $fail) use ($value): void {
                if ((int) $v > (int) $value('logins')) {
                    $fail(__('buyergateway.v.users_over_logins'));
                }
            }],
            'most_opened' => ['nullable', Rule::in(PortalBook::PAGES)],
            'downloads' => $count,
            'avg_session' => ['sometimes', 'numeric', 'min:0', 'max:600'],
            'adoption' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return ['buyer_id.required' => __('buyergateway.v.buyer'), 'period.required' => __('buyergateway.v.period'), 'period.in' => __('buyergateway.v.period')];
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['period_label'] = $out['period'] ? CarbonImmutable::createFromFormat('!Y-m', $out['period'])->format('M Y') : null;

        return $out;
    }
}
