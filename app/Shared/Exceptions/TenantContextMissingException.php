<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use RuntimeException;

/**
 * Thrown when a tenant-scoped query runs with no resolved company.
 *
 * This is deliberately fatal rather than falling back to an unscoped query.
 * A silent unscoped query is a cross-tenant data leak (ADR-042 rule 1).
 */
class TenantContextMissingException extends RuntimeException {}
