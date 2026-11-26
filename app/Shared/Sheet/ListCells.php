<?php

declare(strict_types=1);

namespace App\Shared\Sheet;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * A list column of a values sheet (a cell holding several values — JOB / PO
 * numbers) under an Excel-style header filter: each value counts for its
 * row, a row with none is a blank, and a filter value matches a row holding
 * it. Pre-Budget and Export Statement use it; Order Summary and Dev Sample
 * keep their own copy of the same.
 */
final class ListCells
{
    /** @return Collection<int, object{v: string, n: int}> */
    public static function distinct(Builder $query, string $key): Collection
    {
        $counts = [];
        foreach ($query->toBase()->selectRaw('"values"->? as v', [$key])->limit(50000)->pluck('v') as $json) {
            $list = json_decode((string) $json, true);
            $list = is_array($list) ? $list : (is_string($list) && trim($list) !== '' ? [$list] : []);
            foreach ($list !== [] ? array_unique(array_map('strval', $list)) : [''] as $v) {
                $counts[$v] = ($counts[$v] ?? 0) + 1;
            }
        }
        ksort($counts, SORT_NATURAL | SORT_FLAG_CASE);

        return collect($counts)->map(fn ($n, $v) => (object) ['v' => (string) $v, 'n' => $n])->values();
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|Builder  $q  the filter's where-group
     * @param  list<string>  $vals
     */
    public static function filter($q, string $key, array $vals, bool $blank): void
    {
        if ($vals !== []) {
            $quoted = '{'.implode(',', array_map(fn ($v) => '"'.addcslashes($v, '"\\').'"', $vals)).'}';
            $q->orWhereRaw('jsonb_exists_any(case when jsonb_typeof("values"->?) = \'array\' then "values"->? else \'[]\'::jsonb end, ?::text[])', [$key, $key, $quoted]);
        }
        if ($blank) {
            $q->orWhereRaw('coalesce(jsonb_array_length(case when jsonb_typeof("values"->?) = \'array\' then "values"->? end), 0) = 0', [$key, $key]);
        }
    }
}
