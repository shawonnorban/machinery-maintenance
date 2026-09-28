<?php

declare(strict_types=1);

namespace App\Shared\Http\Api;

use App\Modules\Api\Support\ApiCaller;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Base for every API controller.
 *
 * Carries the three things a list endpoint always needs and always gets wrong
 * when each writes its own: a capped page size, an allowlisted sort, and
 * allowlisted filters. Arbitrary column names never reach SQL (API 35.3).
 */
abstract class ApiController extends Controller
{
    private const DEFAULT_PER_PAGE = 25;

    private const MAX_PER_PAGE = 100;

    /**
     * The permission check every API endpoint uses.
     *
     * Deliberately not `$this->authorize()`. That path runs through the Gate
     * with the logged-in user, and half the callers here are machines with no
     * user at all; `ApiCaller` is what knows how to answer for both, and
     * having one method means an endpoint cannot accidentally be written to
     * only understand people.
     */
    protected function allow(string $permission): void
    {
        if (! app(ApiCaller::class)->can($permission)) {
            throw ApiException::of(ErrorCode::FORBIDDEN);
        }
    }

    protected function caller(): ApiCaller
    {
        return app(ApiCaller::class);
    }

    /**
     * Page size, clamped. A client asking for 5,000 rows gets 100 rather than
     * an error: the cap is a defence against cost, not a rejection of intent.
     */
    protected function perPage(Request $request): int
    {
        $requested = (int) $request->query('per_page', (string) self::DEFAULT_PER_PAGE);

        if ($requested < 1) {
            return self::DEFAULT_PER_PAGE;
        }

        return min($requested, self::MAX_PER_PAGE);
    }

    /**
     * Equality filters, restricted to an explicit list of columns.
     *
     * A comma in the value means "any of these": `status=OPEN,IN_PROGRESS`.
     * Filtering never bypasses tenant scope, because the builder handed in is
     * already scoped and this only ever adds conditions (API 30).
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  list<string>  $allowed
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    protected function applyFilters(Builder $query, Request $request, array $allowed): Builder
    {
        foreach ($allowed as $column) {
            $value = $request->query($column);

            if (! is_string($value) || $value === '') {
                continue;
            }

            $values = array_values(array_filter(
                array_map('trim', explode(',', $value)),
                fn (string $v): bool => $v !== '',
            ));

            if ($values === []) {
                continue;
            }

            count($values) === 1
                ? $query->where($column, $values[0])
                : $query->whereIn($column, $values);
        }

        return $query;
    }

    /**
     * Sorting, restricted to an explicit list of columns (API 31).
     *
     * An unknown sort field falls back to the default rather than failing. A
     * client that mistypes one still gets its data, in a defined order, which
     * is what it needed; the alternative is a 422 for something that changes
     * nothing about correctness.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  list<string>  $allowed
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    protected function applySort(
        Builder $query,
        Request $request,
        array $allowed,
        string $default,
        string $defaultDirection = 'desc',
    ): Builder {
        $sort = $request->query('sort');
        $column = is_string($sort) && in_array($sort, $allowed, true) ? $sort : $default;

        $direction = strtolower((string) $request->query('direction', $defaultDirection)) === 'asc'
            ? 'asc'
            : 'desc';

        return $query->orderBy($column, $direction);
    }
}
