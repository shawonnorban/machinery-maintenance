<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\Knitting\Support\KnittingLabels;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * The shared ground of Knit Production's master lists (AnnotechERP 15/01): one
 * view and one manage permission.
 */
abstract class KnittingResource extends ResourceDefinition
{
    public function viewPermission(): string
    {
        return 'knitting.master.view';
    }

    public function managePermission(): string
    {
        return 'knitting.master.manage';
    }

    public function afterSave(Model $row, bool $created): void
    {
        KnittingLabels::forget();
    }

    protected function company(): string
    {
        return app(TenantContext::class)->companyId();
    }

    /** Case-blind uniqueness of a code within the company — Postgres compares text exactly. */
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
