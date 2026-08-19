<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Asset;

use App\Modules\Asset\Models\AssetTransfer;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use App\Shared\Support\TenantTimezone;
use Illuminate\Database\Eloquent\Builder;

/**
 * Machines moved between factories and locations (SRS 32).
 *
 * Filtered on the request date rather than the receipt date, because a transfer
 * that was requested and never received is exactly what someone running this
 * report is looking for. Filtering on receipt would hide the machines that are
 * still in transit — the ones nobody can find.
 */
class AssetTransferReport extends Report
{
    public function __construct(private readonly TenantTimezone $timezone) {}

    public function key(): string
    {
        return 'asset_transfer';
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
        return ['period', 'asset'];
    }

    public function columns(): array
    {
        return [
            'transfer_number' => ['label' => 'report.columns.transfer_number'],
            'asset_code' => ['label' => 'report.columns.asset_code'],
            'from_factory' => ['label' => 'report.columns.from_factory'],
            'to_factory' => ['label' => 'report.columns.to_factory'],
            'status' => ['label' => 'report.columns.status'],
            'requested_at' => ['label' => 'report.columns.requested_at'],
            'approved_at' => ['label' => 'report.columns.approved_at'],
            'received_at' => ['label' => 'report.columns.received_at'],
            'reason' => ['label' => 'report.columns.reason'],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        foreach ($this->base($query)->lazy() as $transfer) {
            yield [
                'transfer_number' => $transfer->transfer_number,
                'asset_code' => $transfer->asset?->asset_code,
                'from_factory' => $transfer->fromFactory?->name,
                'to_factory' => $transfer->toFactory?->name,
                'status' => __('asset.transfer_status_'.strtolower($transfer->status)),
                'requested_at' => $this->timezone->format($transfer->requested_at),
                'approved_at' => $this->timezone->format($transfer->approved_at),
                'received_at' => $this->timezone->format($transfer->received_at),
                'reason' => $transfer->reason,
            ];
        }
    }

    protected function countable(ReportQuery $query): Builder
    {
        return $this->base($query);
    }

    private function base(ReportQuery $query): Builder
    {
        return AssetTransfer::query()
            ->with(['asset', 'fromFactory', 'toFactory'])
            ->whereBetween('requested_at', [$query->from, $query->to])
            ->when($query->assetId !== null, fn ($q) => $q->where('asset_id', $query->assetId))
            ->orderByDesc('requested_at');
    }
}
