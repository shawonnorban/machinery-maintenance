<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Support;

use App\Modules\QualityTally\Models\QtInspector;
use Illuminate\Support\Facades\Hash;

/**
 * A QC's PIN (user, 2026-11-25): six digits, 000000 until the QC sets their
 * own in the app; the owner or manager can put it back to 000000.
 */
final class QtPins
{
    public const DEFAULT = '000000';

    /** A wrong PIN this many times in a row, and that ID waits. */
    public const TRIES = 5;

    public const LOCK_SECONDS = 900;

    public static function isDefault(QtInspector $inspector): bool
    {
        return $inspector->pin_hash === null;
    }

    /** Checked against a hash either way, so an unknown ID takes as long as a wrong PIN. */
    public static function matches(?QtInspector $inspector, string $pin): bool
    {
        if ($inspector?->pin_hash !== null) {
            return Hash::check($pin, $inspector->pin_hash);
        }
        Hash::make($pin);

        return $inspector !== null && hash_equals(self::DEFAULT, $pin);
    }

    /** One digit six times, a run up or down (123456, 987654), or a pair or three repeated. */
    public static function weak(string $pin): bool
    {
        $steps = [];
        for ($i = 1; $i < strlen($pin); $i++) {
            $steps[] = (int) $pin[$i] - (int) $pin[$i - 1];
        }
        $run = count(array_unique($steps)) === 1 && abs($steps[0]) <= 1;

        return $run || str_repeat(substr($pin, 0, 2), 3) === $pin || str_repeat(substr($pin, 0, 3), 2) === $pin;
    }

    /** Null puts it back to the default. */
    public static function set(QtInspector $inspector, ?string $pin): void
    {
        $inspector->forceFill(['pin_hash' => $pin === null ? null : Hash::make($pin), 'pin_changed_at' => now()])->save();
    }
}
