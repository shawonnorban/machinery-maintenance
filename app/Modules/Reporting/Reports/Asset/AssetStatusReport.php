<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Asset;

use App\Modules\Asset\Models\AssetStatusHistory;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use App\Shared\Support\TenantTimezone;
use Illuminate\Database\Eloquent\Builder;

/**
 * How machine status changed over a period (SRS 32).
 *
 * The history, not the current status: the register already says what a machine
 * is doing now. What this answers is how often it moved between running and
 * broken, and who moved it — the trail behind an availability figure somebody
 * has questioned.
 */
class AssetStatusReport extends Report
{
    public function __construct(private readonly TenantTimezone $timezone) {}

    public function key(): string
    {
        return 'asset_status';
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
        return ['period', 'factory', 'asset'];
    }

    public function columns(): array
    {
        return [
            'changed_at' => ['label' => 'report.columns.changed_at'],
            'asset_code' => ['label' => 'report.columns.asset_code'],
            'asset_name' => ['label' => 'report.columns.name'],
            'from_status' => ['label' => 'report.columns.from_status'],
            'to_status' => ['label' => 'report.columns.to_status'],
            'source' => ['label' => 'report.columns.source'],
            'reason' => ['label' => 'report.columns.reason'],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        foreach ($this->base($query)->lazy() as $change) {
            yield [
                'changed_at' => $this->timezone->format($change->changed_at),
                'asset_code' => $change->asset?->asset_code,
                'asset_name' => $change->asset?->name,
                // A first status has no predecessor; an empty cell says that
                // better than the word "none" in one of two languages.
                'from_status' => $change->from_status === null
                    ? null
                    : __('asset.status_'.strtolower($change->from_status)),
                'to_status' => __('asset.status_'.strtolower($change->to_status)),
                'source' => $change->source,
                'reason' => $change->reason,
            ];
        }
    }

    protected function countable(ReportQuery $query): Builder
    {
        return $this->base($query);
    }

    private function base(ReportQuery $query): Builder
    {
        return AssetStatusHistory::query()
            ->with('asset')
            ->whereBetween('changed_at', [$query->from, $query->to])
            ->when($query->assetId !== null, fn ($q) => $q->where('asset_id', $query->assetId))
            ->when(
                $query->factoryId !== null,
                fn ($q) => $q->whereHas('asset', fn ($a) => $a->where('current_factory_id', $query->factoryId)),
            )
            ->orderBy('changed_at')
            ->orderBy('id');
    }
}
