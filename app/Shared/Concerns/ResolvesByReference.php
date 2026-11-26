<?php

declare(strict_types=1);

namespace App\Shared\Concerns;

use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Route binding by the record's readable number as well as its ULID, so a
 * page can live at `/assets/CUT-DHK-00401` rather than `/assets/01m2new…`
 * (AnnotechERP: no internal ids in page URLs) while every API call that
 * still sends the id keeps working.
 *
 * The model names its number column in `ROUTE_REFERENCE`; the column must
 * be unique per company (the tenant scope narrows the lookup to one).
 */
trait ResolvesByReference
{
    /**
     * @param  Builder  $query
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        if ($field !== null) {
            return parent::resolveRouteBindingQuery($query, $value, $field);
        }

        $value = (string) $value;

        return $query->where(fn ($q) => $q->where($this->qualifyColumn($this->getRouteKeyName()), $value)
            ->orWhere($this->qualifyColumn(static::ROUTE_REFERENCE), $value));
    }
}
