<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Imports;

/**
 * What a row is being prepared against.
 *
 * Carries the row number so an error can name the line in the spreadsheet the
 * person is looking at, and a cache so a file of three thousand assets does not
 * look up the same six asset types three thousand times.
 */
final class RowContext
{
    /** @var array<string, mixed> */
    private array $cache = [];

    public function __construct(public readonly int $rowNumber) {}

    public function remember(string $key, callable $resolve): mixed
    {
        return $this->cache[$key] ??= $resolve();
    }
}
