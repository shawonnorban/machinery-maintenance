<?php

declare(strict_types=1);

namespace App\Shared\Http\Api;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * The one shape every API response takes (API 1).
 *
 * Success and failure share an envelope so a client can tell them apart
 * without inspecting the status line, and `meta.request_id` is on every one of
 * them: a support ticket quoting a request id has to resolve to the exact
 * database changes it caused, and that only works if the client was given the
 * id in the first place.
 */
class ApiResponse
{
    /**
     * @param  mixed  $data
     * @param  array<string, mixed>  $meta
     */
    public static function ok($data = null, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => self::meta($meta),
        ], $status);
    }

    /**
     * @param  mixed  $data
     * @param  array<string, mixed>  $meta
     */
    public static function created($data = null, array $meta = []): JsonResponse
    {
        return self::ok($data, $meta, 201);
    }

    /**
     * Queued work. The client is handed something to poll rather than a
     * result, because the result does not exist yet (API 2).
     *
     * @param  mixed  $job
     */
    public static function accepted($job): JsonResponse
    {
        return self::ok($job, [], 202);
    }

    public static function noContent(): JsonResponse
    {
        // 204 carries no envelope by definition. Nothing to say is said by
        // saying nothing, not by an empty object.
        return response()->json(null, 204);
    }

    /**
     * Offset pagination: the page numbers a screen needs.
     *
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @param  (callable(mixed): mixed)|null  $transform
     */
    public static function paginated(LengthAwarePaginator $paginator, ?callable $transform = null): JsonResponse
    {
        $items = Collection::make($paginator->items());

        return self::ok(
            ($transform === null ? $items : $items->map($transform))->values()->all(),
            [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        );
    }

    /**
     * Cursor pagination for append-only tables (API 29).
     *
     * No `total`, deliberately. Counting a large ledger on every page is the
     * exact cost cursor pagination exists to avoid, and a client that needs a
     * count has a report endpoint for it.
     *
     * @param  CursorPaginator<int, mixed>  $paginator
     * @param  (callable(mixed): mixed)|null  $transform
     */
    public static function cursor(CursorPaginator $paginator, ?callable $transform = null): JsonResponse
    {
        $items = Collection::make($paginator->items());

        return self::ok(
            ($transform === null ? $items : $items->map($transform))->values()->all(),
            [
                'per_page' => $paginator->perPage(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'has_more' => $paginator->hasMorePages(),
            ],
        );
    }

    /**
     * @param  array<string, list<string>>  $errors
     * @param  array<string, mixed>  $meta
     */
    public static function error(
        ErrorCode $code,
        ?string $message = null,
        array $errors = [],
        array $meta = [],
        ?int $status = null,
    ): JsonResponse {
        $body = [
            'success' => false,
            'message' => $message ?? $code->message(),
            'code' => $code->value,
        ];

        // Present only for 422, per the contract. An empty `errors` object on
        // every other failure trains clients to look in the wrong place.
        if ($errors !== []) {
            $body['errors'] = $errors;
        }

        $body['meta'] = self::meta($meta);

        return response()->json($body, $status ?? $code->status());
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private static function meta(array $meta): array
    {
        return array_merge(
            ['request_id' => request()->attributes->get('request_id')],
            $meta,
        );
    }
}
