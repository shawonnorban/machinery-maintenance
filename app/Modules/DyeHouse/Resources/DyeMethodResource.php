<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Resources;

use App\Modules\DyeHouse\Models\DyeMethod;
use App\Modules\DyeHouse\Support\DyeHouseDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Dye Method Master: every dyeing method a batch may be run under — single or
 * double — and the one proposed on a new batch. Ticking Default clears it
 * from whichever method had it. Dye House → Master Data (AnnotechERP 17/01).
 */
class DyeMethodResource extends DyeMasterResource
{
    public function key(): string
    {
        return 'dye-methods';
    }

    public function model(): string
    {
        return DyeMethod::class;
    }

    public function columns(): array
    {
        return ['name', 'short_name', 'sd', 'is_default', 'active'];
    }

    public function booleans(): array
    {
        return ['is_default', 'active'];
    }

    public function defaults(): array
    {
        return ['active' => true, 'is_default' => false];
    }

    public function deletable(): bool
    {
        return false;
    }

    public function query(): Builder
    {
        DyeHouseDefaults::methods($this->company());

        return DyeMethod::query()->orderByDesc('active')->orderBy('name');
    }

    public function messages(): array
    {
        return ['name.required' => __('dyehouse.master.method_name_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255', Rule::unique('dye_methods', 'name')->where('company_id', $this->company())->ignore($row?->getKey())],
            'short_name' => [$required, 'string', 'max:32'],
            'sd' => [$required, Rule::in(DyeMethod::SD)],
            'is_default' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function afterSave(Model $row, bool $created): void
    {
        if ($row->getAttribute('is_default')) {
            DyeMethod::query()->whereKeyNot($row->getKey())->where('is_default', true)->update(['is_default' => false]);
        }
    }
}
