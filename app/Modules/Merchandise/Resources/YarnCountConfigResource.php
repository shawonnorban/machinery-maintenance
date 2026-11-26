<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Merchandise\Models\FabricType;
use App\Modules\Merchandise\Models\YarnCountConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * GSM-Wise Yarn Count Setup: yarn counts per fabric type and GSM band. Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class YarnCountConfigResource extends MasterResource
{
    public function key(): string
    {
        return 'yarn-count-configs';
    }

    public function model(): string
    {
        return YarnCountConfig::class;
    }

    public function columns(): array
    {
        return ['fabric_type_id', 'template', 'gauge', 'gsm_min', 'gsm_max', 'count_top', 'count_binding', 'count_back'];
    }

    public function booleans(): array
    {
        return [];
    }

    public function defaults(): array
    {
        return [];
    }

    public function query(): Builder
    {
        return YarnCountConfig::query()->orderBy('fabric_type_id')->orderBy('gsm_min');
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'fabric_type_id' => [$required, Rule::exists('fabric_types', 'id')->where('company_id', $this->company())],
            'template' => ['nullable', Rule::in(YarnCountConfig::TEMPLATES)],
            'gauge' => [$required, 'string', 'max:16'],
            'gsm_min' => [$required, 'integer', 'min:1', 'max:2000'],
            'gsm_max' => [$required, 'integer', 'min:1', 'max:2000', 'gte:gsm_min'],
            'count_top' => ['nullable', 'string', 'max:32'],
            'count_binding' => ['nullable', 'string', 'max:32'],
            'count_back' => ['nullable', 'string', 'max:32'],
        ];
    }

    protected function upperCase(): array
    {
        return [];
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['construction'] = FabricType::query()->whereKey($row->getAttribute('fabric_type_id'))->value('name');

        return $out;
    }
}
