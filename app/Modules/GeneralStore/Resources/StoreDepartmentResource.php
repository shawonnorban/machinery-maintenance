<?php

declare(strict_types=1);

namespace App\Modules\GeneralStore\Resources;

use App\Modules\GeneralStore\Models\GsDepartment;
use App\Modules\GeneralStore\Support\GeneralStoreDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Department: the departments the store issues to and takes requisitions
 * from. General Store → Master Data (AnnotechERP 10/01).
 */
class StoreDepartmentResource extends StoreMasterResource
{
    public function key(): string
    {
        return 'store-departments';
    }

    public function model(): string
    {
        return GsDepartment::class;
    }

    public function columns(): array
    {
        return ['code', 'name', 'description', 'active'];
    }

    public function query(): Builder
    {
        GeneralStoreDefaults::departments($this->company());

        return parent::query();
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'code' => [$required, 'string', 'max:32', $this->uniqueCode($row)],
            'name' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
