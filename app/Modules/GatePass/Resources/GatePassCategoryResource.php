<?php

declare(strict_types=1);

namespace App\Modules\GatePass\Resources;

use App\Modules\GatePass\Models\GatePassCategory;
use App\Modules\GatePass\Support\GatePassDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Gate Pass Category: main gate passes for an employee, item gate passes for
 * products and materials. Gate Pass → Master Data (AnnotechERP 11/01).
 */
class GatePassCategoryResource extends GatePassMasterResource
{
    public function key(): string
    {
        return 'gatepass-categories';
    }

    public function model(): string
    {
        return GatePassCategory::class;
    }

    public function columns(): array
    {
        return ['code', 'name', 'kind', 'description', 'active'];
    }

    public function query(): Builder
    {
        GatePassDefaults::categories($this->company());

        return parent::query();
    }

    public function rules(?Model $row): array
    {
        return $this->basicRules($row) + ['kind' => [$row ? 'sometimes' : 'required', Rule::in(GatePassCategory::KINDS)]];
    }
}
