<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Inventory\Concerns;

use App\Modules\Inventory\Models\Bin;
use App\Modules\Reporting\Reports\ReportQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * The two things every item-stock report needs (Inventory spec §14).
 *
 * Stock hangs off a bin, and a bin reaches its factory through
 * `store → warehouse`, so narrowing a stock report to one factory is a
 * three-table walk that each report would otherwise repeat — and get
 * subtly differently.
 *
 * The bin ids are resolved once into a list rather than joined in: these
 * reports stream their rows, and a join that widens the row set changes
 * what `lazy()` holds in memory. A factory has tens of bins, not
 * thousands.
 */
trait ScopesItemStock
{
    /**
     * @return list<string>|null  null when no factory was asked for
     */
    protected function binIdsForFactory(ReportQuery $query): ?array
    {
        if ($query->factoryId === null) {
            return null;
        }

        return Bin::query()
            ->whereHas('store.warehouse', fn ($q) => $q->where('factory_id', $query->factoryId))
            ->pluck('id')
            ->all();
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $builder
     */
    protected function scopeToFactory(Builder $builder, ReportQuery $query, string $column = 'bin_id'): Builder
    {
        $binIds = $this->binIdsForFactory($query);

        if ($binIds === null) {
            return $builder;
        }

        // An empty list is a factory with no bins, which must return
        // nothing rather than everything.
        return $builder->whereIn($column, $binIds);
    }

    /** Matches an item by code or name, the two things anybody types. */
    protected function scopeToSearch(Builder $builder, ReportQuery $query): Builder
    {
        $search = $query->get('search');

        if (! is_string($search) || $search === '') {
            return $builder;
        }

        return $builder->whereHas('item', fn ($q) => $q
            ->where('item_code', 'ilike', "%{$search}%")
            ->orWhere('name', 'ilike', "%{$search}%"));
    }
}
