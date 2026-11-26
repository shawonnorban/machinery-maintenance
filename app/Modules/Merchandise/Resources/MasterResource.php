<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\Inventory\Support\UomCodes;
use App\Shared\Tenancy\TenantContext;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The shared ground of Merchandise Desk's master lists (AnnotechERP 06/01):
 * one view and one manage permission, an Active flag, codes stored upper-case
 * and unique within the company, and a list in display order.
 */
abstract class MasterResource extends ResourceDefinition
{
    public function viewPermission(): string
    {
        return 'merchandise.master.view';
    }

    public function managePermission(): string
    {
        return 'merchandise.master.manage';
    }

    public function booleans(): array
    {
        return ['active'];
    }

    public function defaults(): array
    {
        return ['active' => true];
    }

    /** The column the list is ordered by. */
    protected function orderBy(): string
    {
        return 'code';
    }

    public function query(): Builder
    {
        $model = $this->model();

        return $model::query()->orderBy($this->orderBy())->orderBy('name');
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        foreach ($this->upperCase() as $column) {
            if (isset($data[$column]) && is_string($data[$column])) {
                $data[$column] = strtoupper(trim($data[$column]));
            }
        }
        // A unit is kept as the units-of-measure master's code ("pcs" → PCS).
        if (array_key_exists('uom', $data)) {
            $data['uom'] = UomCodes::normalize($data['uom']);
        }

        return $data;
    }

    /** Columns kept upper-case, as codes are. @return list<string> */
    protected function upperCase(): array
    {
        return ['code'];
    }

    protected function company(): string
    {
        return app(TenantContext::class)->companyId();
    }

    /**
     * Unique within the company, compared as stored: "sj" is SJ.
     *
     * @param  array<string, mixed>  $scope  further columns the code is unique within
     */
    protected function uniqueCode(?Model $row, string $column = 'code', array $scope = []): Closure
    {
        $model = $this->model();

        return function (string $attribute, mixed $value, Closure $fail) use ($model, $row, $column, $scope): void {
            $taken = $model::query()
                ->where('company_id', $this->company())
                ->where($column, strtoupper(trim((string) $value)))
                ->where($scope)
                ->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))
                ->exists();
            if ($taken) {
                $fail(__('merchandise.master.code_taken'));
            }
        };
    }
}
