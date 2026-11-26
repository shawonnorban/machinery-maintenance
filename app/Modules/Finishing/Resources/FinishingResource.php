<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * The shared ground of Finishing Line's master lists (AnnotechERP 18/01): one
 * view and one manage permission.
 */
abstract class FinishingResource extends ResourceDefinition
{
    public function viewPermission(): string
    {
        return 'finishing.master.view';
    }

    public function managePermission(): string
    {
        return 'finishing.master.manage';
    }

    public function deletable(): bool
    {
        return false;
    }

    protected function company(): string
    {
        return app(TenantContext::class)->companyId();
    }

    /** Case-blind uniqueness within the company — Postgres compares text exactly. */
    protected function uniqueText(string $model, string $column, ?Model $row, string $message): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($model, $column, $row, $message): void {
            $taken = $model::query()->whereRaw("upper(trim({$column})) = ?", [strtoupper(trim((string) $value))])
                ->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
            if ($taken) {
                $fail($message);
            }
        };
    }
}
