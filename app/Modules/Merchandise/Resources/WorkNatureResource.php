<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Merchandise\Models\StyleItem;
use App\Modules\Merchandise\Models\WorkNature;
use App\Modules\Merchandise\Support\MerchandiseDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Nature of Work Master: Cutting, Printing, Sewing, Finishing, AOP Printing —
 * the work a garment item may be given, several at a time; the name is the
 * text a style item carries. Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class WorkNatureResource extends MasterResource
{
    public function key(): string
    {
        return 'work-natures';
    }

    public function model(): string
    {
        return WorkNature::class;
    }

    public function columns(): array
    {
        return ['code', 'name', 'remarks', 'sort_order', 'active'];
    }

    public function defaults(): array
    {
        return ['active' => true, 'sort_order' => 0];
    }

    public function query(): Builder
    {
        MerchandiseDefaults::ensureWorkNatures($this->company());

        return WorkNature::query()->orderBy('sort_order')->orderBy('name');
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'code' => [$required, 'string', 'max:32', $this->uniqueCode($row)],
            // A style item keeps its natures joined by commas, so a name holds none.
            'name' => [$required, 'string', 'max:120', 'not_regex:/,/', Rule::unique('work_natures', 'name')->where('company_id', $this->company())->ignore($row?->getKey())],
            'remarks' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9999'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => __('merchandise.acc_po.required', ['field' => __('merchandise.master.nwk.f.code')]),
            'name.required' => __('merchandise.acc_po.required', ['field' => __('merchandise.master.nwk.f.name')]),
            'name.unique' => __('merchandise.master.nwk.name_taken'),
            'name.not_regex' => __('merchandise.master.nwk.no_comma'),
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $data = parent::beforeSave($data, $row);
        $data['sort_order'] = (int) ($data['sort_order'] ?? $row?->getAttribute('sort_order') ?? 0);

        return $data;
    }

    /** A nature a style item carries stays; switch it off instead. */
    public function beforeDelete(Model $row): void
    {
        $name = (string) $row->getAttribute('name');
        $used = StyleItem::query()->where('nature_of_work', 'like', '%'.$name.'%')->pluck('nature_of_work')
            ->contains(fn (string $v): bool => in_array($name, array_map('trim', explode(',', $v)), true));
        if ($used) {
            throw ValidationException::withMessages(['id' => __('merchandise.master.in_use')]);
        }
    }
}
