<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\DyeHouse\Models\DyeColourGroup;
use App\Modules\Merchandise\Models\Colour;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Colour Directory: colours with their Pantone references. Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class ColourResource extends MasterResource
{
    public function key(): string
    {
        return 'colours';
    }

    public function model(): string
    {
        return Colour::class;
    }

    public function columns(): array
    {
        return ['code', 'colour_type', 'name', 'pantone_no', 'version_no', 'colour_group_id', 'swatch_provided', 'active'];
    }

    public function booleans(): array
    {
        return ['active', 'swatch_provided'];
    }

    protected function orderBy(): string
    {
        return 'colour_type';
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'code' => ['nullable', 'string', 'max:32'],
            'colour_type' => [$required, Rule::in(Colour::TYPES)],
            // A colour name is unique within its type: Navy solid and Navy melange are two colours.
            'name' => [$required, 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail) use ($row): void {
                $type = request()->input('colour_type', $row?->colour_type);
                $taken = Colour::query()->where('company_id', $this->company())->where('colour_type', $type)
                    ->whereRaw('lower(name) = ?', [mb_strtolower(trim((string) $value))])
                    ->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
                if ($taken) {
                    $fail(__('merchandise.master.colour_taken'));
                }
            }],
            'pantone_no' => ['nullable', 'string', 'max:32'],
            'version_no' => ['nullable', 'string', 'max:16'],
            'colour_group_id' => ['nullable', Rule::exists('dye_colour_groups', 'id')->where('company_id', $this->company())],
            'swatch_provided' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['colour_group'] = $row->getAttribute('colour_group_id') ? DyeColourGroup::query()->whereKey($row->getAttribute('colour_group_id'))->value('name') : null;

        return $out;
    }
}
