<?php

declare(strict_types=1);

namespace App\Shared\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Money is DECIMAL(18,4) in the database and a decimal STRING on the wire
 * (ERD rule 13, API Schemas 2.1).
 *
 * It is never cast to a PHP float. IEEE 754 cannot represent 0.1 exactly, and
 * a maintenance cost that drifts by fractions across a year of aggregation is
 * not defensible to a customer.
 *
 * @implements CastsAttributes<string|null, string|int|float|null>
 */
class MoneyCast implements CastsAttributes
{
    public function __construct(private readonly int $scale = 4)
    {
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return number_format((float) $value, $this->scale, '.', '');
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, $this->scale, '.', '');
    }
}
