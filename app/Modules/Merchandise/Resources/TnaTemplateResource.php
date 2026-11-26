<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\TnaTemplate;
use App\Modules\Merchandise\Models\TnaTemplateTask;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * TNA Template Setup: reusable T&A calendars applied to a new style. Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class TnaTemplateResource extends MasterResource
{
    public function key(): string
    {
        return 'tna-templates';
    }

    public function model(): string
    {
        return TnaTemplate::class;
    }

    public function columns(): array
    {
        return ['code', 'scope', 'buyer_id', 'order_type', 'gmt_type', 'lead_from', 'lead_to', 'std_days', 'before_swing', 'after_swing', 'description', 'approved_by', 'prepared_by'];
    }

    public function booleans(): array
    {
        return [];
    }

    public function defaults(): array
    {
        return ['scope' => 'GENERAL'];
    }

    public function query(): Builder
    {
        return TnaTemplate::query()->orderBy('lead_from')->orderBy('code');
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'code' => [$required, 'string', 'max:16', $this->uniqueCode($row)],
            'scope' => ['sometimes', Rule::in(TnaTemplate::SCOPES)],
            // A buyer-specific template names its buyer; a general one names none.
            'buyer_id' => ['nullable', 'required_if:scope,BUYER', 'prohibited_if:scope,GENERAL', Rule::exists('buyers', 'id')->where('company_id', $this->company())],
            'order_type' => [$required, Rule::in(TnaTemplate::ORDER_TYPES)],
            'gmt_type' => [$required, Rule::in(TnaTemplate::GMT_TYPES)],
            'lead_from' => [$required, 'integer', 'min:1', 'max:999'],
            'lead_to' => [$required, 'integer', 'gte:lead_from', 'max:999'],
            'std_days' => [$required, 'integer', 'min:0', 'max:999'],
            'before_swing' => [$required, 'integer', 'min:0', 'max:999'],
            'after_swing' => [$required, 'integer', 'min:0', 'max:999'],
            'description' => ['nullable', 'string', 'max:2000'],
            'approved_by' => [$required, Rule::exists('company_users', 'user_id')->where('company_id', $this->company())],
            'prepared_by' => ['nullable', Rule::exists('company_users', 'user_id')->where('company_id', $this->company())],
        ];
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['buyer'] = $row->getAttribute('buyer_id') ? Buyer::query()->whereKey($row->getAttribute('buyer_id'))->value('short_name') : null;
        $out['task_count'] = TnaTemplateTask::query()->where('tna_template_id', $row->getKey())->count();

        return $out;
    }
}
