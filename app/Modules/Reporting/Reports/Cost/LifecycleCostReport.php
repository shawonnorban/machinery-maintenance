<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Cost;

use App\Modules\Asset\Models\Asset;
use App\Modules\Costing\Services\AssetLifecycleCost;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * What each machine has cost over its life (SRS 32, SRS 23).
 *
 * The figure a repair-versus-replace decision is made on, so it comes from
 * AssetLifecycleCost rather than a sum assembled here. Two ways of adding up a
 * machine's history is one too many when the answer decides whether it gets
 * scrapped.
 *
 * Lifetime, not the selected period: the period filter narrows which machines
 * are listed, never how much of their history counts. A machine's cost to date
 * is not a monthly figure.
 */
class LifecycleCostReport extends Report
{
    public function __construct(private readonly AssetLifecycleCost $lifecycle) {}

    public function key(): string
    {
        return 'lifecycle_cost';
    }

    public function group(): string
    {
        return 'cost';
    }

    public function permission(): string
    {
        return 'asset.financial.view';
    }

    public function filters(): array
    {
        return ['factory'];
    }

    public function columns(): array
    {
        return [
            'asset_code' => ['label' => 'report.columns.asset_code'],
            'asset_name' => ['label' => 'report.columns.name'],
            'factory' => ['label' => 'report.columns.factory'],
            'status' => ['label' => 'report.columns.status'],
            'acquisition' => ['label' => 'report.columns.acquisition_cost', 'numeric' => true],
            'maintenance' => ['label' => 'report.columns.maintenance_cost', 'numeric' => true],
            'repair' => ['label' => 'report.columns.repair_cost', 'numeric' => true],
            'other' => ['label' => 'report.columns.other_cost', 'numeric' => true],
            'total_spend' => ['label' => 'report.columns.total_spend', 'numeric' => true],
            'lifetime_total' => ['label' => 'report.columns.lifetime_total', 'numeric' => true],
            'currency' => ['label' => 'report.columns.currency'],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        foreach ($this->base($query)->lazy() as $asset) {
            $cost = $this->lifecycle->forAsset($asset);

            yield [
                'asset_code' => $asset->asset_code,
                'asset_name' => $asset->name,
                'factory' => $asset->factory?->name,
                'status' => __('asset.status_'.strtolower($asset->status)),
                'acquisition' => $cost['acquisition'],
                'maintenance' => $cost['maintenance'],
                'repair' => $cost['repair'],
                'other' => $cost['other'],
                'total_spend' => $cost['total_spend'],
                'lifetime_total' => $cost['lifetime_total'],
                'currency' => $cost['currency'],
            ];
        }
    }

    protected function countable(ReportQuery $query): Builder
    {
        return $this->base($query);
    }

    private function base(ReportQuery $query): Builder
    {
        return Asset::query()
            ->with('factory')
            ->when($query->factoryId !== null, fn ($q) => $q->where('current_factory_id', $query->factoryId))
            ->orderBy('asset_code');
    }
}
