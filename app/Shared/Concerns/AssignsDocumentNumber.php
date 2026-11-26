<?php

declare(strict_types=1);

namespace App\Shared\Concerns;

use App\Modules\Settings\Services\NumberSequenceGenerator;
use Illuminate\Database\Eloquent\Model;

/**
 * A record that numbers itself from its company's series as it is created:
 * the model names the column in `ROUTE_REFERENCE` (the same number its page
 * is addressed by, see ResolvesByReference) and the series in
 * `NUMBER_SERIES` (a NumberSequenceGenerator::FORMATS key).
 *
 * Done on `creating` so every path that makes one (API, web, seeders,
 * imports) gets a number without each remembering to ask. A number already
 * set is kept. Use it after BelongsToTenant, whose own `creating` fills in
 * the company this one numbers from.
 */
trait AssignsDocumentNumber
{
    public static function bootAssignsDocumentNumber(): void
    {
        static::creating(function (Model $model): void {
            $column = static::ROUTE_REFERENCE;
            $companyId = $model->getAttribute('company_id');

            if (blank($model->getAttribute($column)) && $companyId !== null) {
                $model->setAttribute($column, app(NumberSequenceGenerator::class)
                    ->next(static::NUMBER_SERIES, companyId: (string) $companyId));
            }
        });
    }
}
