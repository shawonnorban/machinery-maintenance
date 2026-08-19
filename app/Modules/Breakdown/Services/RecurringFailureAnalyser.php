<?php

declare(strict_types=1);

namespace App\Modules\Breakdown\Services;

use App\Modules\Breakdown\Models\Breakdown;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Which machine keeps failing, and with what (SRS 16).
 *
 * The question this answers is the one that justifies buying the system: a
 * machine repaired eleven times in a quarter is not a maintenance problem, it
 * is a replacement decision nobody has made yet, and it is invisible in a list
 * of individual breakdowns.
 *
 * Recurrence links are excluded throughout. A second report against a machine
 * already down is the same stoppage, and counting it would show a machine that
 * broke once as a repeat offender.
 */
class RecurringFailureAnalyser
{
    /**
     * Assets with repeated failures in a window.
     *
     * @return Collection<int, object>
     */
    public function repeatOffenders(
        CarbonImmutable $from,
        CarbonImmutable $to,
        int $minimumFailures = 3,
        ?string $factoryId = null,
    ): Collection {
        return Breakdown::query()
            ->select([
                'asset_id',
                DB::raw('COUNT(*) as failure_count'),
                DB::raw('MIN(failure_at) as first_failure_at'),
                DB::raw('MAX(failure_at) as last_failure_at'),
                DB::raw('COUNT(DISTINCT failure_code_id) as distinct_codes'),
            ])
            ->with('asset:id,asset_code,name,criticality,current_factory_id')
            ->whereBetween('failure_at', [$from, $to])
            ->whereNotIn('status', ['CANCELLED'])
            ->whereNull('is_recurrence_of_breakdown_id')
            ->when($factoryId !== null, fn ($q) => $q->where('factory_id', $factoryId))
            ->groupBy('asset_id')
            ->havingRaw('COUNT(*) >= ?', [$minimumFailures])
            ->orderByDesc('failure_count')
            ->get();
    }

    /**
     * The failure codes behind those repeats, most common first.
     *
     * @return Collection<int, object>
     */
    public function failureCodeFrequency(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $factoryId = null,
    ): Collection {
        return Breakdown::query()
            ->select([
                'failure_code_id',
                DB::raw('COUNT(*) as failure_count'),
            ])
            ->with('failureCode:id,name,name_bn,code')
            ->whereBetween('failure_at', [$from, $to])
            ->whereNotIn('status', ['CANCELLED'])
            ->whereNull('is_recurrence_of_breakdown_id')
            ->whereNotNull('failure_code_id')
            ->when($factoryId !== null, fn ($q) => $q->where('factory_id', $factoryId))
            ->groupBy('failure_code_id')
            ->orderByDesc('failure_count')
            ->get();
    }

    /**
     * The share of closures coded UNKNOWN.
     *
     * A data-quality metric, not a failure metric. UNKNOWN is seeded on purpose
     * so a technician under pressure has an honest option instead of guessing,
     * but a rising share of it means the analysis is running on air, and the
     * only way to notice is to measure it (Seed Catalog 3.5).
     *
     * @return array{closed: int, unknown: int, share: float|null}
     */
    public function unknownCauseShare(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $factoryId = null,
    ): array {
        $base = Breakdown::query()
            ->where('status', 'CLOSED')
            ->whereBetween('failure_at', [$from, $to])
            ->when($factoryId !== null, fn ($q) => $q->where('factory_id', $factoryId));

        $closed = (clone $base)->count();

        $unknown = (clone $base)
            ->whereHas('failureCode', fn ($q) => $q->where('code', 'UNKNOWN'))
            ->count();

        return [
            'closed' => $closed,
            'unknown' => $unknown,
            // Null rather than zero when nothing closed. A 0% unknown rate on
            // no data reads as excellent record-keeping (SRS 31.2 rule 2).
            'share' => $closed === 0 ? null : round($unknown / $closed * 100, 1),
        ];
    }

    /**
     * Mean time between failures for one asset, in hours.
     *
     * Needs at least two independent failures: with one there is no interval to
     * average, and reporting the age of the machine instead would be a
     * different number wearing the same name.
     */
    public function mtbfHours(string $assetId, CarbonImmutable $from, CarbonImmutable $to): ?float
    {
        $failures = Breakdown::query()
            ->where('asset_id', $assetId)
            ->whereBetween('failure_at', [$from, $to])
            ->whereNotIn('status', ['CANCELLED'])
            ->whereNull('is_recurrence_of_breakdown_id')
            ->orderBy('failure_at')
            ->pluck('failure_at');

        if ($failures->count() < 2) {
            return null;
        }

        $total = 0.0;

        for ($i = 1; $i < $failures->count(); $i++) {
            $total += $failures[$i - 1]->diffInMinutes($failures[$i], absolute: true);
        }

        return round($total / ($failures->count() - 1) / 60, 2);
    }
}
