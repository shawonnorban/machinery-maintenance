<?php

declare(strict_types=1);

namespace App\Shared\Support;

/**
 * Times as people read them: a 12-hour clock with AM / PM (user, 2026-11-25).
 * Stored and compared values stay 24-hour; this is only for labels, reports
 * and PDFs (screens use frontend/src/lib/clock.js).
 */
final class Clock
{
    /** "17:05" or "17:05:00" → "05:05 PM"; anything else comes back as it is. */
    public static function hm(?string $time): ?string
    {
        if ($time === null || ! preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/', $time, $m)) {
            return $time;
        }
        $h = (int) $m[1];

        return sprintf('%02d:%s %s', $h % 12 ?: 12, $m[2], $h < 12 ? 'AM' : 'PM');
    }

    /** "08:00 AM–05:00 PM" */
    public static function range(?string $start, ?string $end): string
    {
        return self::hm($start).'–'.self::hm($end);
    }
}
