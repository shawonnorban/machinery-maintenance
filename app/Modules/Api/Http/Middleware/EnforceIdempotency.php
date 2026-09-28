<?php

declare(strict_types=1);

namespace App\Modules\Api\Http\Middleware;

use App\Modules\Api\Models\IdempotencyKey;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Makes a retry safe on the endpoints that move stock or money (API 32).
 *
 * The problem this solves is not theoretical. A technician issuing a bearing
 * at 2am on a factory wifi that drops will press the button again when the
 * first attempt appears to hang, and without this the store loses two
 * bearings and the work order is charged twice. Stock and money are the two
 * things this system cannot un-move.
 *
 * The claim row is written before the work, so two concurrent retries race on
 * a unique index and exactly one proceeds.
 *
 * One deliberate narrowing of the specification: only a successful response is
 * recorded and replayed. A failed attempt had no effect worth protecting, and
 * pinning a transient 503 to a key for 24 hours would leave a client unable to
 * do the very thing the retry was for.
 *
 * Usage: `->middleware('idempotent')` to accept a key, `'idempotent:required'`
 * to insist on one.
 */
class EnforceIdempotency
{
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next, string $mode = 'optional'): Response
    {
        if (! in_array($request->method(), self::WRITE_METHODS, true)) {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key');
        $key = is_string($key) ? trim($key) : '';

        if ($key === '') {
            if ($mode === 'required') {
                throw new ApiException(
                    ErrorCode::VALIDATION_ERROR,
                    __('api.idempotency_key_required'),
                    ['Idempotency-Key' => [__('api.idempotency_key_required')]],
                );
            }

            return $next($request);
        }

        if (strlen($key) > 128) {
            throw new ApiException(
                ErrorCode::VALIDATION_ERROR,
                __('api.idempotency_key_too_long'),
                ['Idempotency-Key' => [__('api.idempotency_key_too_long')]],
            );
        }

        $companyId = $this->context->companyIdOrNull();

        if ($companyId === null) {
            // No tenant means nothing to scope the key to. Whatever comes next
            // will refuse the request anyway.
            return $next($request);
        }

        $endpoint = $request->method().' '.$request->path();
        $hash = hash('sha256', $endpoint.'|'.$request->getContent());

        $claim = $this->claim($companyId, $key, $endpoint, $hash, $request);

        if ($claim instanceof Response) {
            return $claim;
        }

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            // The operation did not complete. Releasing the claim is what lets
            // the client fix the request and try again with the same key.
            $this->release($claim);

            throw $e;
        }

        $response->getStatusCode() < 300
            ? $this->complete($claim, $response)
            : $this->release($claim);

        return $response;
    }

    /**
     * Win the race, or answer as the loser.
     *
     * @return IdempotencyKey|Response the claim to hold, or the reply to send
     */
    private function claim(
        string $companyId,
        string $key,
        string $endpoint,
        string $hash,
        Request $request,
    ): IdempotencyKey|Response {
        $now = Carbon::now();

        try {
            return IdempotencyKey::create([
                'company_id' => $companyId,
                'user_id' => $request->user()?->id,
                'api_client_id' => $request->attributes->get('api_client_id'),
                'key' => $key,
                'endpoint' => $endpoint,
                'request_hash' => $hash,
                'status' => 'IN_PROGRESS',
                'locked_at' => $now,
                'expires_at' => $now->copy()->addHours(IdempotencyKey::TTL_HOURS),
            ]);
        } catch (QueryException $e) {
            if (! $this->isDuplicate($e)) {
                throw $e;
            }
        }

        $existing = IdempotencyKey::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $companyId)
            ->where('key', $key)
            ->where('endpoint', $endpoint)
            ->first();

        if ($existing === null) {
            // The row was purged between the failed insert and this read. One
            // more attempt is honest; a loop is not.
            return $this->claim($companyId, $key, $endpoint, $hash, $request);
        }

        if ($existing->hasExpired()) {
            $existing->delete();

            return $this->claim($companyId, $key, $endpoint, $hash, $request);
        }

        if ($existing->request_hash !== $hash) {
            // Same key, different body. This is a client bug, and executing it
            // would be the worst possible reading of an ambiguous request.
            throw ApiException::of(ErrorCode::IDEMPOTENCY_CONFLICT, __('api.idempotency_body_changed'));
        }

        if (! $existing->isComplete()) {
            // The first attempt is still running. Answering "in flight" is the
            // only safe reply: the client can retry once it has settled.
            throw ApiException::of(ErrorCode::IDEMPOTENCY_CONFLICT, __('api.idempotency_in_flight'));
        }

        return response()
            ->json($existing->response_body_json, $existing->response_status ?? 200)
            ->header('Idempotent-Replay', 'true');
    }

    private function complete(IdempotencyKey $claim, Response $response): void
    {
        $decoded = json_decode((string) $response->getContent(), true);

        $claim->forceFill([
            'status' => 'COMPLETED',
            'response_status' => $response->getStatusCode(),
            'response_body_json' => is_array($decoded) ? $decoded : null,
            'resource_id' => is_array($decoded) && is_array($decoded['data'] ?? null)
                ? ($decoded['data']['id'] ?? null)
                : null,
        ])->save();
    }

    private function release(IdempotencyKey $claim): void
    {
        $claim->delete();
    }

    private function isDuplicate(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062;
    }
}
