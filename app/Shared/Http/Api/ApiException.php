<?php

declare(strict_types=1);

namespace App\Shared\Http\Api;

use RuntimeException;
use Throwable;

/**
 * A failure a client is meant to read.
 *
 * Anything thrown as one of these is a documented outcome of the contract, not
 * a defect: it renders as the standard error envelope with its code and never
 * reaches the error log. A defect throws something else and becomes a 500 with
 * a request id, which is the only thing the client is told about it.
 */
class ApiException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly ErrorCode $code,
        ?string $message = null,
        public readonly array $errors = [],
        public readonly array $meta = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message ?? $code->message(), $code->status(), $previous);
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    public static function of(ErrorCode $code, ?string $message = null, array $errors = []): self
    {
        return new self($code, $message, $errors);
    }
}
