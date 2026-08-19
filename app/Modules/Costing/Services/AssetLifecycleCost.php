<?php

declare(strict_types=1);

namespace App\Modules\Costing\Services;

use App\Modules\Asset\Models\Asset;
use App\Modules\Costing\Models\CostEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What a machine has cost (SRS 23).
 *
 * Acquisition plus everything spent keeping it running. This is the figure a
 * repair-versus-replace decision is made on, which is why it is assembled from
 * posted entries rather than from anybody's spreadsheet.
 *
 * Depreciation is deliberately absent. Accounting depreciation is a different
 * question answered by a different department on a different basis, and mixing
 * it in here would produce a number that is wrong for both purposes (SRS 23).
 */
class AssetLifecycleCost
{
    private const SCALE = 4;

    /**
     * @return array{
     *     acquisition: string,
     *     maintenance: string,
     *     repair: string,
     *     other: string,
     *     total_spend: string,
     *     lifetime_total: string,
     *     by_category: Collection<int, object>,
     *     entry_count: int,
     *     currency: string
     * }
     */
    public function forAsset(Asset $asset, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array
    {
        $entries = CostEntry::query()
            ->where('asset_id', $asset->id)
            ->when($from !== null, fn ($q) => $q->where('occurred_at', '>=', $from))
            ->when($to !== null, fn ($q) => $q->where('occurred_at', '<=', $to))
            ->with('category')
            ->get();

        $buckets = ['ACQUISITION' => '0.0000', 'INSTALLATION' => '0.0000', 'UPGRADE' => '0.0000',
            'MAINTENANCE' => '0.0000', 'REPAIR' => '0.0000', 'OTHER' => '0.0000'];

        foreach ($entries as $entry) {
            $bucket = $entry->category?->lifecycle_bucket ?? 'OTHER';
            $buckets[$bucket] = bcadd($buckets[$bucket] ?? '0.0000', (string) $entry->base_amount, self::SCALE);
        }

        // Everything spent on the machine since it arrived, excluding what was
        // paid to buy it.
        $spend = '0.0000';

        foreach (['INSTALLATION', 'UPGRADE', 'MAINTENANCE', 'REPAIR', 'OTHER'] as $bucket) {
            $spend = bcadd($spend, $buckets[$bucket], self::SCALE);
        }

        // The purchase price on the asset record counts even when nobody posted
        // a cost entry for it: most machines are bought before this system
        // exists, and omitting the purchase would make every old machine look
        // cheap to keep.
        $acquisition = bccomp($buckets['ACQUISITION'], '0', self::SCALE) !== 0
            ? $buckets['ACQUISITION']
            : $this->money($asset->acquisition_cost);

        return [
            'acquisition' => $acquisition,
            'maintenance' => $buckets['MAINTENANCE'],
            'repair' => $buckets['REPAIR'],
            'other' => bcadd(
                bcadd($buckets['INSTALLATION'], $buckets['UPGRADE'], self::SCALE),
                $buckets['OTHER'],
                self::SCALE,
            ),
            'total_spend' => $spend,
            'lifetime_total' => bcadd($acquisition, $spend, self::SCALE),
            'by_category' => $this->byCategory($asset, $from, $to),
            'entry_count' => $entries->count(),
            'currency' => $asset->currency ?? 'BDT',
        ];
    }

    /**
     * Maintenance spend as a share of what the machine cost to buy.
     *
     * The number that turns "this keeps breaking" into a decision. Null when
     * there is no purchase price to compare against — a percentage of an
     * unknown is not a small percentage, it is no answer at all
     * (SRS 31.2 rule 2).
     */
    public function spendAgainstValue(Asset $asset): ?float
    {
        $summary = $this->forAsset($asset);

        if (bccomp($summary['acquisition'], '0', self::SCALE) <= 0) {
            return null;
        }

        return round(
            (float) bcdiv($summary['total_spend'], $summary['acquisition'], 6) * 100,
            1,
        );
    }

    /**
     * @return Collection<int, object>
     */
    private function byCategory(Asset $asset, ?CarbonImmutable $from, ?CarbonImmutable $to): Collection
    {
        return CostEntry::query()
            ->select([
                'cost_category_id',
                DB::raw('SUM(base_amount) as total'),
                DB::raw('COUNT(*) as entries'),
            ])
            ->with('category:id,name,name_bn,code,lifecycle_bucket')
            ->where('asset_id', $asset->id)
            ->when($from !== null, fn ($q) => $q->where('occurred_at', '>=', $from))
            ->when($to !== null, fn ($q) => $q->where('occurred_at', '<=', $to))
            ->groupBy('cost_category_id')
            ->orderByDesc('total')
            ->get();
    }

    private function money(mixed $value): string
    {
        return number_format((float) ($value ?? 0), self::SCALE, '.', '');
    }
}
