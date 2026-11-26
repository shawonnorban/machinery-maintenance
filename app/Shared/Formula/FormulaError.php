<?php

declare(strict_types=1);

namespace App\Shared\Formula;

use RuntimeException;

/**
 * A formula that cannot be read or used: a typo, an unknown column or
 * function, a wrong number of arguments, or a loop between columns. `code`
 * names the kind (lang `formula.<code>`), `position` the character it was
 * found at (null when it is about the whole formula).
 */
final class FormulaError extends RuntimeException
{
    /** @param  array<string, string|int>  $params */
    public function __construct(public readonly string $kind, public readonly array $params = [], public readonly ?int $position = null)
    {
        parent::__construct($kind.($params ? ' '.json_encode($params) : ''));
    }
}
