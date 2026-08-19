<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Imports;

/**
 * One column of an import file.
 *
 * The label is a translation key rather than the header text itself: a file is
 * matched on its English column names, which stay stable, while the screen and
 * the template explain them in the reader's language. Translating the header a
 * file must contain would mean a file that stops importing when somebody
 * switches language.
 */
final class ImportColumn
{
    public function __construct(
        public readonly string $label,
        public readonly bool $required = false,
        public readonly string $example = '',
        public readonly ?string $hint = null,
    ) {}
}
