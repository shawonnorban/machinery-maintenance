<?php

declare(strict_types=1);

namespace App\Modules\GeneralStore\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Shared\Tenancy\TenantContext;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The shared ground of General Store's master lists (AnnotechERP 10/01): one
 * view and one manage permission, an Active flag ("inactive entries are not
 * offered on new entries"), codes stored upper-case and unique within the
 * company, a list in code order, and nothing deleted — an entry is switched
 * off.
 */
abstract class StoreMasterResource extends ResourceDefinition
{
    public function viewPermission(): string
    {
        return 'store.master.view';
    }

    public function managePermission(): string
    {
        return 'store.master.manage';
    }

    public function booleans(): array
    {
        return ['active'];
    }

    public function defaults(): array
    {
        return ['active' => true];
    }

    public function deletable(): bool
    {
        return false;
    }

    /** The column codes live in. */
    protected function codeColumn(): string
    {
        return 'code';
    }

    public function query(): Builder
    {
        $model = $this->model();

        return $model::query()->orderBy($this->codeColumn());
    }

    protected function company(): string
    {
        return app(TenantContext::class)->companyId();
    }

    /** Unique within the company, compared as stored: "elec" is ELEC. */
    protected function uniqueCode(?Model $row): Closure
    {
        $model = $this->model();
        $column = $this->codeColumn();

        return function (string $attribute, mixed $value, Closure $fail) use ($model, $row, $column): void {
            $taken = $model::query()->where($column, strtoupper(trim((string) $value)))->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
            if ($taken) {
                $fail(__('store.master.code_taken'));
            }
        };
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $column = $this->codeColumn();
        if (isset($data[$column]) && is_string($data[$column])) {
            $data[$column] = strtoupper(trim($data[$column]));
        }

        return $data;
    }
}
