<?php

declare(strict_types=1);

namespace App\Shared\Concerns;

use App\Shared\Support\TenantTimezone;
use Carbon\CarbonImmutable;

/**
 * Converts wall-time form input into UTC instants.
 *
 * A `datetime-local` input has no timezone in it at all — the browser sends
 * "2026-08-18T21:50" and nothing else. Parsed with the application default that
 * becomes 21:50 UTC, which in Dhaka is ten to four the following morning. The
 * breakdown then reads as eight hours long, or as having been repaired before
 * it broke, and every derived figure inherits the error.
 *
 * An input carrying its own offset (an ISO-8601 string from an API client) is
 * left alone: it already names an instant, and reinterpreting it would corrupt
 * a value that was correct.
 *
 * For FormRequest classes only — it reads $this->input(). A controller has no
 * such method and should call TenantTimezone::toUtc() directly.
 */
trait ParsesLocalDateTimes
{
    public function localDateTime(string $key): ?CarbonImmutable
    {
        $value = $this->input($key);

        if (blank($value)) {
            return null;
        }

        $value = (string) $value;

        if ($this->carriesTimezone($value)) {
            return CarbonImmutable::parse($value)->setTimezone('UTC');
        }

        return app(TenantTimezone::class)->toUtc($value);
    }

    /**
     * Trailing Z, or a +HH:MM / -HH:MM offset after the time part. The date's
     * own hyphens must not be mistaken for a negative offset, so only the
     * portion after "T" or the first space is examined.
     */
    private function carriesTimezone(string $value): bool
    {
        $timePart = preg_split('/[T ]/', trim($value), 2)[1] ?? '';

        return (bool) preg_match('/(Z|[+-]\d{2}:?\d{2})$/i', $timePart);
    }
}
