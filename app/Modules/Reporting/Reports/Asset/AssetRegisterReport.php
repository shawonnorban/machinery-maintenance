<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Asset;

use App\Modules\Asset\Models\Asset;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use App\Shared\Support\TenantTimezone;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every machine on the books (SRS 32).
 *
 * The register is what an auditor asks for first, so it lists what a machine
 * is and where it is rather than how it has been performing. Retired and
 * scrapped machines are included: a register that silently drops them cannot
 * be reconciled against a fixed asset ledger, which is usually the point of
 * asking for it.
 */
class AssetRegisterReport extends Report
{
    public function __construct(private readonly TenantTimezone $timezone) {}

    public function key(): string
    {
        return 'asset_register';
    }

    public function group(): string
    {
        return 'asset';
    }

    public function permission(): string
    {
        return 'asset.asset.view_any';
    }

    public function filters(): array
    {
        return ['factory', 'status'];
    }

    public function columns(): array
    {
        return [
            'asset_code' => ['label' => 'report.columns.asset_code'],
            'name' => ['label' => 'report.columns.name'],
            'type' => ['label' => 'report.columns.asset_type'],
            'manufacturer' => ['label' => 'report.columns.manufacturer'],
            'serial_number' => ['label' => 'report.columns.serial_number'],
            'factory' => ['label' => 'report.columns.factory'],
            'location' => ['label' => 'report.columns.location'],
            'criticality' => ['label' => 'report.columns.criticality'],
            'status' => ['label' => 'report.columns.status'],
            'purchase_date' => ['label' => 'report.columns.purchase_date'],
            'acquisition_cost' => ['label' => 'report.columns.acquisition_cost', 'numeric' => true],
            'warranty_end' => ['label' => 'report.columns.warranty_end'],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        foreach ($this->base($query)->lazy() as $asset) {
            yield [
                'asset_code' => $asset->asset_code,
                'name' => $asset->name,
                'type' => $asset->type?->name,
                'manufacturer' => $asset->manufacturer?->name,
                'serial_number' => $asset->serial_number,
                'factory' => $asset->factory?->name,
                'location' => $asset->location?->name,
                'criticality' => __('asset.criticality_'.strtolower($asset->criticality)),
                'status' => __('asset.status_'.strtolower($asset->status)),
                'purchase_date' => $this->timezone->format($asset->purchase_date, 'Y-m-d'),
                'acquisition_cost' => $asset->acquisition_cost,
                'warranty_end' => $this->timezone->format($asset->warranty_end, 'Y-m-d'),
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
            ->with(['type', 'manufacturer', 'factory', 'location'])
            ->when($query->factoryId !== null, fn ($q) => $q->where('current_factory_id', $query->factoryId))
            ->when($query->get('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderBy('asset_code');
    }
}
