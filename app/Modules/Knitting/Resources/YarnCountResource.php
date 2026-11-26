<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Resources;

use App\Modules\Knitting\Models\KnitYarnCount;
use App\Modules\Knitting\Support\KnittingDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Yarn Count Master: the counts every yarn record is built from — the count as
 * the floor writes it, its system, its description and value, and whether it
 * is single or plied. A count in use is withdrawn, never deleted. Knit
 * Production → Master Data (AnnotechERP 15/01).
 */
class YarnCountResource extends KnittingResource
{
    public function key(): string
    {
        return 'knit-yarn-counts';
    }

    public function model(): string
    {
        return KnitYarnCount::class;
    }

    public function columns(): array
    {
        return ['count_no', 'count_system', 'description', 'count_value', 'is_single', 'display_order', 'active'];
    }

    public function booleans(): array
    {
        return ['is_single', 'active'];
    }

    public function defaults(): array
    {
        return ['is_single' => true, 'active' => true];
    }

    public function deletable(): bool
    {
        return true;
    }

    public function query(): Builder
    {
        KnittingDefaults::yarnCounts($this->company());

        return KnitYarnCount::query()->orderBy('display_order')->orderBy('count_no');
    }

    public function messages(): array
    {
        return ['count_no.required' => __('knitting.master.count_no_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'count_no' => [$required, 'string', 'max:32', $this->uniqueText(KnitYarnCount::class, 'count_no', $row, __('knitting.master.count_taken'))],
            'count_system' => [$required, Rule::in(KnitYarnCount::SYSTEMS)],
            'description' => [$required, 'string', 'max:255'],
            'count_value' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'is_single' => ['sometimes', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (array_key_exists('display_order', $data) && $data['display_order'] === null) {
            $data['display_order'] = 0;
        }

        return $data;
    }
}
