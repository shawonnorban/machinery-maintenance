<?php

declare(strict_types=1);

namespace App\Modules\GatePass\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Shared\Tenancy\TenantContext;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The shared ground of Gate Pass's master lists (AnnotechERP 11/01): one view
 * and one manage permission, an Active flag ("inactive entries are not offered
 * on new gate passes"), codes stored upper-case and unique within the company,
 * a list in code order, and nothing deleted.
 */
abstract class GatePassMasterResource extends ResourceDefinition
{
    public function viewPermission(): string
    {
        return 'gatepass.master.view';
    }

    public function managePermission(): string
    {
        return 'gatepass.master.manage';
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

    public function query(): Builder
    {
        $model = $this->model();

        return $model::query()->orderBy('code');
    }

    protected function company(): string
    {
        return app(TenantContext::class)->companyId();
    }

    /** Unique within the company, compared as stored: "lt-11" is LT-11. */
    protected function uniqueCode(?Model $row): Closure
    {
        $model = $this->model();

        return function (string $attribute, mixed $value, Closure $fail) use ($model, $row): void {
            $taken = $model::query()->where('code', strtoupper(trim((string) $value)))->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
            if ($taken) {
                $fail(__('gatepass.master.code_taken'));
            }
        };
    }

    /** Code, name, description and Active — the shape most of these lists share. @return array<string, mixed> */
    protected function basicRules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'code' => [$required, 'string', 'max:32', $this->uniqueCode($row)],
            'name' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['code']) && is_string($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }

        return $data;
    }
}
