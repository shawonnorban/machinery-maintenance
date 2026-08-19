<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Imports;

/**
 * What happened when a prepared row was written.
 *
 * Created and updated are counted separately because they mean different
 * things to the person who ran the import. "Three hundred imported" when they
 * expected three hundred new machines and got three hundred overwrites is a
 * bad afternoon.
 */
final class ImportOutcome
{
    private function __construct(
        public readonly string $result,
        public readonly ?string $error = null,
    ) {}

    public static function created(): self
    {
        return new self('CREATED');
    }

    public static function updated(): self
    {
        return new self('UPDATED');
    }

    public static function failed(string $error): self
    {
        return new self('FAILED', $error);
    }

    public function isFailure(): bool
    {
        return $this->result === 'FAILED';
    }
}
