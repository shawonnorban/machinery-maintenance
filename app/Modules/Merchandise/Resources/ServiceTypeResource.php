<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Merchandise\Models\ServiceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Service Type Setup: kinds of AOP, yarn dyeing, print, embroidery and wash. Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class ServiceTypeResource extends MasterResource
{
    public function key(): string
    {
        return 'service-types';
    }

    public function model(): string
    {
        return ServiceType::class;
    }

    public function columns(): array
    {
        return ['service_for', 'code', 'name', 'remarks', 'sort_order', 'active'];
    }

    protected function orderBy(): string
    {
        return 'service_for';
    }

    public function defaults(): array
    {
        return ['active' => true, 'sort_order' => 0];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $service = request()->input('service_for', $row?->service_for);

        return [
            'service_for' => [$required, Rule::in(ServiceType::SERVICES)],
            'code' => [$required, 'string', 'max:32', $this->uniqueCode($row, 'code', ['service_for' => $service])],
            'name' => [$required, 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
