<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Merchandise\Models\Size;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Size Directory: sizes in their groups and run order. Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class SizeResource extends MasterResource
{
    public function key(): string
    {
        return 'sizes';
    }

    public function model(): string
    {
        return Size::class;
    }

    public function columns(): array
    {
        return ['size_group', 'code', 'name', 'name_bn', 'comp_size_group', 'display_order', 'comp_display_order', 'active'];
    }

    protected function orderBy(): string
    {
        return 'display_order';
    }

    public function defaults(): array
    {
        return ['active' => true, 'display_order' => 0, 'comp_display_order' => 0];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'size_group' => ['nullable', Rule::in(Size::GROUPS)],
            'code' => ['nullable', 'string', 'max:16'],
            'name' => [$required, 'string', 'max:64', function (string $attribute, mixed $value, \Closure $fail) use ($row): void {
                $group = request()->input('size_group', $row?->size_group);
                $taken = Size::query()->where('company_id', $this->company())->where('size_group', $group)
                    ->whereRaw('lower(name) = ?', [mb_strtolower(trim((string) $value))])
                    ->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
                if ($taken) {
                    $fail(__('merchandise.master.size_taken'));
                }
            }],
            'name_bn' => ['nullable', 'string', 'max:64'],
            'comp_size_group' => ['nullable', Rule::in(Size::GROUPS)],
            'display_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'comp_display_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
