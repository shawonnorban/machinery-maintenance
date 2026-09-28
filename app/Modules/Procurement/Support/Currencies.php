<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Support;

/**
 * The currencies a buying document may be written in.
 *
 * One list, because the alternative was four copies drifting apart — a
 * rate contract offering a currency the order it prices cannot accept is
 * the kind of mismatch nobody notices until a mill invoices in it.
 *
 * BDT first: it is the default for local buying, which is most of it.
 */
final class Currencies
{
    /** @var list<string> */
    public const SUPPORTED = ['BDT', 'USD', 'EUR', 'CNY', 'INR'];

    public const DEFAULT = 'BDT';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return self::SUPPORTED;
    }
}
