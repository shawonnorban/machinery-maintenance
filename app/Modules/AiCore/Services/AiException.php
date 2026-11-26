<?php

declare(strict_types=1);

namespace App\Modules\AiCore\Services;

use RuntimeException;

/**
 * An AI call that did not produce the answer asked for. The message is safe
 * to show a user: it never carries the API key or the request body.
 */
class AiException extends RuntimeException
{
    /** The service declined the request (a safety refusal), as opposed to failing. */
    public static function declined(string $service): self
    {
        return new self(__('aicore.e.declined', ['service' => $service]));
    }

    public static function failed(string $service, string $why): self
    {
        return new self(__('aicore.e.failed', ['service' => $service, 'why' => mb_strimwidth($why, 0, 200, '…')]));
    }

    /** An HTTP error from the service, said plainly: the key refused, the model unknown, the limit reached … */
    public static function status(string $service, ?int $status, ?string $detail): self
    {
        $key = match (true) {
            $status === 401 => 'aicore.e.http_401',
            $status === 403 => 'aicore.e.http_403',
            $status === 404 => 'aicore.e.http_404',
            $status === 429 => 'aicore.e.http_429',
            $status !== null && $status >= 500 => 'aicore.e.http_5xx',
            default => null,
        };

        return $key !== null ? new self(__($key, ['service' => $service])) : self::failed($service, (string) ($detail ?: "HTTP {$status}"));
    }

    public static function notJson(string $service): self
    {
        return new self(__('aicore.e.not_json', ['service' => $service]));
    }
}
