<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Resources;

use App\Modules\BuyerGateway\Models\VisibilityRule;
use App\Modules\BuyerGateway\Support\PortalBook;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Order Visibility Rules (AnnotechERP 25/02): which orders a buyer may see
 * (scope) and how much of each order's progress (depth — status, milestones,
 * quantities or the full production trail), whether delays show, from when,
 * who set it and why. One rule per buyer; a rule can be switched off.
 */
class VisibilityRuleResource extends BuyerGatewayResource
{
    protected function area(): string
    {
        return 'visibility';
    }

    public function key(): string
    {
        return 'bg-visibility-rules';
    }

    public function model(): string
    {
        return VisibilityRule::class;
    }

    public function columns(): array
    {
        return ['buyer_id', 'scope', 'depth', 'delays', 'effective_from', 'set_by', 'note', 'active'];
    }

    public function booleans(): array
    {
        return ['active'];
    }

    public function defaults(): array
    {
        return ['scope' => 'CONFIRMED', 'depth' => 'STATUS', 'delays' => 'REVIEWED', 'active' => true];
    }

    public function rules(?Model $row): array
    {
        return [
            'buyer_id' => [...$this->buyerRule(! $row), $this->unique($row, ['buyer_id'], 'buyergateway.v.rule_taken')],
            'scope' => ['sometimes', Rule::in(PortalBook::SCOPES)],
            'depth' => ['sometimes', Rule::in(PortalBook::DEPTHS)],
            'delays' => ['sometimes', Rule::in(PortalBook::DELAYS)],
            'effective_from' => ['nullable', 'date_format:Y-m-d'],
            'set_by' => ['nullable', 'string', Rule::exists('company_users', 'user_id')->where('company_id', $this->company())],
            'note' => ['nullable', 'string', 'max:500'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['buyer_id.required' => __('buyergateway.v.buyer')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (! $row) {
            $data['effective_from'] ??= CarbonImmutable::now('Asia/Dhaka')->toDateString();
            $data['set_by'] ??= $this->actingUserId();
        }

        return $data;
    }

    public function present(Model $row): array
    {
        return parent::present($row) + ['set_by_name' => PortalBook::staff()[$row->getAttribute('set_by')] ?? null];
    }
}
