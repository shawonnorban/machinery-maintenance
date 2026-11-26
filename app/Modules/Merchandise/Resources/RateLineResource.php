<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\Merchandise\Models\RateLine;
use App\Modules\Merchandise\Models\RateLineRate;
use App\Modules\Merchandise\Support\MerchandiseDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Inquiry Rate Chart: the standing process and CM rate lines price quotations price against. Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class RateLineResource extends MasterResource
{
    public function key(): string
    {
        return 'rate-lines';
    }

    public function model(): string
    {
        return RateLine::class;
    }

    public function columns(): array
    {
        return ['rate_group', 'description', 'uom', 'sort_order', 'active'];
    }

    public function query(): Builder
    {
        MerchandiseDefaults::ensureRateLines($this->company());

        return RateLine::query()->orderBy('sort_order')->orderBy('description');
    }

    protected function upperCase(): array
    {
        return [];
    }

    public function defaults(): array
    {
        return ['active' => true, 'sort_order' => 0];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $group = request()->input('rate_group', $row?->getAttribute('rate_group'));

        return [
            'rate_group' => [$required, Rule::in(RateLine::GROUPS)],
            'description' => [$required, 'string', 'max:255', Rule::unique('rate_lines', 'description')->where('company_id', $this->company())->where('rate_group', $group)->ignore($row?->getKey())],
            'uom' => ['nullable', 'string', 'max:16', UomCodes::rule($this->company())],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:99999'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /** How many offices have a rate for this line, and the average of their latest rates. */
    public function present(Model $row): array
    {
        $out = parent::present($row);
        $latest = RateLineRate::query()->where('rate_line_id', $row->getKey())->orderByDesc('effective_from')->get()->unique('buyer_office_id');
        $out['offices_quoted'] = $latest->count();
        $out['avg_rate'] = $latest->isEmpty() ? null : round((float) $latest->avg('rate'), 4);

        return $out;
    }
}
