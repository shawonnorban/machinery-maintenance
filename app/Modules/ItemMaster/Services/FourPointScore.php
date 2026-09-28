<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Services;

/**
 * The four-point system (Inventory spec §4.3).
 *
 *     points per 100 square yards = total points × 36 × 100
 *                                   ─────────────────────────
 *                                   inspected yards × width in inches
 *
 * The 36 turns yards of a 36"-wide strip into square yards, so the figure
 * compares a narrow roll with a wide one fairly. Verified against the
 * mill's own sheets: 34 points over 124 yards at 60" gives 16.45, which is
 * what the inspector wrote.
 *
 * **A lot's figure is pooled, not averaged.** Summing the points and the
 * yards and dividing once is not the same as taking the mean of the roll
 * percentages — a 20-yard roll would otherwise weigh as heavily as a
 * 200-yard one, and a single short bad roll could fail a good delivery.
 * The mill's sheet pools; so does this.
 */
class FourPointScore
{
    private const SCALE = 2;

    /**
     * Points a fault is worth, by how long it runs (spec §4.3).
     *
     * Length is in inches and the bands are the ones printed on the
     * inspection form: under 3", up to 6", up to 9", and longer. A hole
     * is four points whatever its size, which is why the class is given
     * per occurrence rather than derived from the defect type.
     *
     * @var array<int, int>
     */
    public const POINT_CLASSES = [1 => 1, 2 => 2, 3 => 3, 4 => 4];

    /** Inches of width one point is measured against. */
    private const SQUARE_YARD_INCHES = 36;

    /**
     * Total points from a tally of `{point_class, occurrences}` rows.
     *
     * @param  iterable<array{point_class: int|string, occurrences: int|string}>  $tally
     */
    public function pointsFrom(iterable $tally): string
    {
        $total = '0';

        foreach ($tally as $row) {
            $class = (int) $row['point_class'];

            if (! isset(self::POINT_CLASSES[$class])) {
                continue;
            }

            $total = bcadd($total, bcmul((string) self::POINT_CLASSES[$class], (string) $row['occurrences'], self::SCALE), self::SCALE);
        }

        return $total;
    }

    /**
     * Points per 100 square yards.
     *
     * Returns null rather than zero when there is nothing to divide by: a
     * roll with no measured length has not been inspected, and calling
     * that a clean 0.00 would let an unmeasured delivery pass.
     */
    public function penaltyPercent(string $points, string $yards, string $widthInches): ?string
    {
        if (bccomp($yards, '0', 4) !== 1 || bccomp($widthInches, '0', 4) !== 1) {
            return null;
        }

        $area = bcmul($yards, $widthInches, 6);
        $scaled = bcmul($points, (string) (self::SQUARE_YARD_INCHES * 100), 6);

        return bcdiv($scaled, $area, self::SCALE);
    }

    /**
     * The width the score is computed against: the mean of the three
     * measurements when they are there, else whatever single width is.
     *
     * @param  list<string|null>  $widths
     */
    public function effectiveWidth(array $widths, ?string $fallback = null): ?string
    {
        $measured = array_values(array_filter(
            $widths,
            fn (?string $w): bool => $w !== null && $w !== '' && bccomp($w, '0', 4) === 1,
        ));

        if ($measured === []) {
            return $fallback !== null && bccomp($fallback, '0', 4) === 1 ? $fallback : null;
        }

        $sum = '0';

        foreach ($measured as $width) {
            $sum = bcadd($sum, $width, 4);
        }

        return bcdiv($sum, (string) count($measured), self::SCALE);
    }

    /**
     * Pooled across every inspected roll — see the class docblock for why
     * this is not the mean of their percentages.
     *
     * @param  iterable<array{points: string, yards: string, width: string}>  $rolls
     * @return array{points: string, yards: string, penalty_percent: string|null, rolls: int}
     */
    public function pool(iterable $rolls): array
    {
        $points = '0';
        $yards = '0';
        // Width varies roll to roll, so the divisor is the actual area
        // inspected rather than one nominal width applied to everything.
        $area = '0';
        $count = 0;

        foreach ($rolls as $roll) {
            $count++;
            $points = bcadd($points, $roll['points'], self::SCALE);
            $yards = bcadd($yards, $roll['yards'], self::SCALE);
            $area = bcadd($area, bcmul($roll['yards'], $roll['width'], 6), 6);
        }

        return [
            'points' => $points,
            'yards' => $yards,
            'rolls' => $count,
            'penalty_percent' => bccomp($area, '0', 6) === 1
                ? bcdiv(bcmul($points, (string) (self::SQUARE_YARD_INCHES * 100), 6), $area, self::SCALE)
                : null,
        ];
    }

    /** Below or at the threshold passes; the bar itself is a setting. */
    public function passes(?string $penaltyPercent, string $threshold): bool
    {
        return $penaltyPercent !== null && bccomp($penaltyPercent, $threshold, self::SCALE) <= 0;
    }
}
