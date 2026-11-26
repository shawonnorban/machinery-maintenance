<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * The shared ground of Finance & Ledger's master lists (AnnotechERP 23/01):
 * one view and one manage permission; rows are deactivated, never deleted.
 */
abstract class AccountingResource extends ResourceDefinition
{
    public function viewPermission(): string
    {
        return 'accounting.master.view';
    }

    public function managePermission(): string
    {
        return 'accounting.master.manage';
    }

    public function deletable(): bool
    {
        return false;
    }

    protected function company(): string
    {
        return app(TenantContext::class)->companyId();
    }

    /** Case-blind uniqueness within a scope — Postgres compares text exactly. */
    protected function uniqueText(string $model, string $column, ?Model $row, string $message, array $scope = []): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($model, $column, $row, $message, $scope): void {
            $taken = $model::query()->where($scope)->whereRaw("upper(trim({$column})) = ?", [strtoupper(trim((string) $value))])
                ->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
            if ($taken) {
                $fail($message);
            }
        };
    }
}
