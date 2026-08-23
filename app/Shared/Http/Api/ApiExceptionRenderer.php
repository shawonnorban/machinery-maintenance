<?php

declare(strict_types=1);

namespace App\Shared\Http\Api;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Turns every way this application can fail into the one error envelope.
 *
 * Deliberately scoped to `api/*` alone. The web UI's failures are redirects
 * with flash messages and must stay that way; a shared renderer that also
 * caught those would replace a form's field errors with JSON.
 *
 * The mapping matters more than it looks. Laravel's defaults are close but
 * not the contract: a missing record is a 404 with `{"message": ""}`, and this
 * has to be a 404 with `RESOURCE_NOT_FOUND` so a client can branch on it.
 */
class ApiExceptionRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        return match (true) {
            $e instanceof ApiException => ApiResponse::error($e->errorCode, $e->getMessage(), $e->errors, $e->meta),

            $e instanceof ValidationException => $this->validation($e),

            $e instanceof AuthenticationException => ApiResponse::error(ErrorCode::UNAUTHENTICATED),

            $e instanceof AuthorizationException,
            $e instanceof AccessDeniedHttpException => ApiResponse::error(
                ErrorCode::FORBIDDEN,
                $this->messageOrNull($e),
            ),

            // Both arms are the same answer on purpose: a record belonging to
            // another tenant is indistinguishable from one that never existed,
            // or the 404 becomes an existence oracle (API 2).
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => ApiResponse::error(ErrorCode::RESOURCE_NOT_FOUND),

            $e instanceof ThrottleRequestsException => $this->throttled($e),

            $e instanceof PostTooLargeException => ApiResponse::error(ErrorCode::PAYLOAD_TOO_LARGE),

            default => $this->unexpected($e),
        };
    }

    /**
     * A validation exception carries its own status in this codebase.
     *
     * `withMessages([...])->status(409)` is how a business rule refuses
     * something while still naming the field it refused — a work order that
     * cannot close, a part that has stock. Those are conflicts, not
     * validation failures, and the code has to say so.
     */
    private function validation(ValidationException $e): JsonResponse
    {
        $code = match ($e->status) {
            409 => ErrorCode::CONFLICT,
            403 => ErrorCode::FORBIDDEN,
            default => ErrorCode::VALIDATION_ERROR,
        };

        return ApiResponse::error(
            $code,
            $e->getMessage(),
            $e->errors(),
            status: $e->status,
        );
    }

    private function throttled(ThrottleRequestsException $e): JsonResponse
    {
        $response = ApiResponse::error(ErrorCode::RATE_LIMITED);

        // Retry-After is the only part of a 429 a client can act on; losing it
        // in translation turns backoff into guesswork (API 35.1).
        foreach (['Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset'] as $header) {
            $value = $e->getHeaders()[$header] ?? null;

            if ($value !== null) {
                $response->headers->set($header, (string) $value);
            }
        }

        return $response;
    }

    /**
     * Anything unplanned. The client is told a request id and nothing else:
     * a stack trace, a SQL fragment or a file path in a response body is a
     * map of the server (API 1 rule 5).
     */
    private function unexpected(Throwable $e): ?JsonResponse
    {
        if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
            // A framework HTTP exception with a deliberate status — 405, 415 —
            // keeps it. Only the status is ours to preserve, not the message.
            return null;
        }

        if (config('app.debug')) {
            // Locally, hiding the cause helps nobody. This branch never runs
            // in production, where debug is off.
            return null;
        }

        report($e);

        return ApiResponse::error(ErrorCode::SERVER_ERROR);
    }

    private function messageOrNull(Throwable $e): ?string
    {
        $message = $e->getMessage();

        return $message === '' ? null : $message;
    }
}
