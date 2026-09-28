<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Console;

use RuntimeException;

/** Thrown inside an import transaction to roll a dry run back. */
final class DryRunRollback extends RuntimeException {}
