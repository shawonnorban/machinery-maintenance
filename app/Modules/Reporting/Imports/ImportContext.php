<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Imports;

/**
 * What an import is being written under.
 *
 * The batch id is the job's own id, stamped onto every record it creates. That
 * is what makes an import reversible in principle and traceable in practice:
 * without it, three hundred machines that arrived from a bad file are three
 * hundred machines somebody has to identify by hand.
 */
final class ImportContext
{
    public function __construct(
        public readonly string $batchId,
        public readonly ?string $userId,
    ) {}
}
