<?php

declare(strict_types=1);

namespace App\Shared\Fabric;

use App\Modules\Settings\Services\SettingsResolver;
use Throwable;

/**
 * Fabric is counted by length from the grey fabric on (the user's decision of
 * 2026-11-16) — yards or metres, the company's choice (`fabric.length_unit`,
 * yards unless set) — with its weight kept beside it, because dyeing loads its
 * machines and doses its recipes by the kilo. Lengths are stored in metres and
 * read and written in the company's unit; a weight and a length convert
 * through the fabric's GSM and open width.
 */
final class FabricLength
{
    public const UNITS = ['YDS', 'MTR'];

    /** Metres in one unit. */
    private const METRES = ['YDS' => 0.9144, 'MTR' => 1.0];

    public static function unit(): string
    {
        try {
            $u = strtoupper((string) app(SettingsResolver::class)->get('fabric.length_unit'));
        } catch (Throwable) {
            $u = 'YDS';
        }

        return in_array($u, self::UNITS, true) ? $u : 'YDS';
    }

    /** Metres in one of the company's units (or the one named) — to multiply before rounding. */
    public static function metresPer(?string $unit = null): float
    {
        return self::METRES[$unit ?? self::unit()];
    }

    /** A length in the company's unit (or the one named) to metres. */
    public static function toMetres(float|int|string|null $qty, ?string $unit = null): ?float
    {
        if ($qty === null || $qty === '') {
            return null;
        }

        return round((float) $qty * self::METRES[$unit ?? self::unit()], 3);
    }

    /** Stored metres to the company's unit (or the one named). */
    public static function fromMetres(float|int|string|null $metres, ?string $unit = null): ?float
    {
        if ($metres === null || $metres === '') {
            return null;
        }

        return round((float) $metres / self::METRES[$unit ?? self::unit()], 3);
    }

    /** Metres a kilo of this fabric runs: 1000 ÷ (GSM × open width in metres). */
    public static function metresPerKg(float|int|string|null $gsm, float|int|string|null $widthInch): ?float
    {
        $g = (float) preg_replace('/[^0-9.]/', '', (string) $gsm);
        $w = (float) preg_replace('/[^0-9.]/', '', (string) $widthInch);

        return $g > 0 && $w > 0 ? 1000 / ($g * $w * 0.0254) : null;
    }

    public static function kgToMetres(float|int|null $kg, float|int|string|null $gsm, float|int|string|null $widthInch): ?float
    {
        $r = self::metresPerKg($gsm, $widthInch);

        return $kg !== null && $r ? round((float) $kg * $r, 3) : null;
    }

    public static function metresToKg(float|int|null $metres, float|int|string|null $gsm, float|int|string|null $widthInch): ?float
    {
        $r = self::metresPerKg($gsm, $widthInch);

        return $metres !== null && $r ? round((float) $metres / $r, 3) : null;
    }
}
