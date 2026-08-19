<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Imports;

/**
 * A row that has been checked, with either the values to write or the reasons
 * it cannot be.
 *
 * Both, never one or the other by convention: a row object that is sometimes
 * valid and sometimes carries errors invites the caller to forget to look.
 */
final class PreparedRow
{
    /**
     * @param  array<string, mixed>  $values
     * @param  list<array{field: string|null, error: string, value: string|null}>  $errors
     * @param  array<string, string|null>  $original
     */
    private function __construct(
        public readonly int $rowNumber,
        public readonly array $values,
        public readonly array $errors,
        public readonly array $original = [],
    ) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public static function valid(int $rowNumber, array $values, array $original = []): self
    {
        return new self($rowNumber, $values, [], $original);
    }

    /**
     * @param  list<array{field: string|null, error: string, value: string|null}>  $errors
     */
    public static function invalid(int $rowNumber, array $errors, array $original = []): self
    {
        return new self($rowNumber, [], $errors, $original);
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
