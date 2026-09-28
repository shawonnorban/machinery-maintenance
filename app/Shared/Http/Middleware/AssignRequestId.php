<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Correlates the HTTP request, the jobs it dispatches, and the audit rows it
 * writes (API 1, ADR-061).
 *
 * A support ticket citing one request id must resolve to the exact database
 * changes it caused, so the id is echoed on the response and shared with the
 * log context.
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id');

        if (! is_string($requestId) || $requestId === '' || strlen($requestId) > 64) {
            $requestId = (string) Str::ulid();
        }

        $request->attributes->set('request_id', $requestId);

        Log::shareContext(['request_id' => $requestId]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
