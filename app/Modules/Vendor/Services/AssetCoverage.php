<?php

declare(strict_types=1);

namespace App\Modules\Vendor\Services;

use App\Modules\Asset\Models\Asset;
use App\Modules\Vendor\Models\ServiceContract;
use App\Modules\Vendor\Models\Warranty;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Is this machine's repair already paid for? (SRS 26)
 *
 * One place answers it, because the answer appears on the asset screen, on a
 * breakdown, and in a report, and three implementations would eventually
 * disagree — at which point the factory pays for a repair it was owed.
 *
 * Dates decide, not status. A daily sweep marks warranties expired, and between
 * midnight and that sweep a lapsed warranty still reads ACTIVE; a technician
 * asking at 6am must not be told the machine is covered when it is not.
 */
class AssetCoverage
{
    /**
     * @return array{
     *     covered: bool,
     *     warranty: Warranty|null,
     *     warranties: Collection<int, Warranty>,
     *     contracts: Collection<int, ServiceContract>,
     * }
     */
    public function forAsset(Asset $asset, ?CarbonImmutable $on = null): array
    {
        $on ??= CarbonImmutable::now();

        $warranties = Warranty::query()
            ->with('vendor')
            ->where('asset_id', $asset->id)
            ->where('status', '!=', 'VOID')
            ->orderByDesc('end_date')
            ->get()
            ->filter(fn (Warranty $warranty) => $warranty->isActiveOn($on))
            ->values();

        $contracts = $this->contractsFor($asset, $on);

        return [
            'covered' => $warranties->isNotEmpty() || $contracts->isNotEmpty(),
            // The one that runs longest, which is the one worth quoting to a
            // vendor.
            'warranty' => $warranties->first(),
            'warranties' => $warranties,
            'contracts' => $contracts,
        ];
    }

    /**
     * @return Collection<int, ServiceContract>
     */
    public function contractsFor(Asset $asset, ?CarbonImmutable $on = null): Collection
    {
        $on ??= CarbonImmutable::now();

        return ServiceContract::query()
            ->with('vendor')
            ->whereIn('status', ['ACTIVE', 'RENEWED'])
            // The three shapes a contract's scope can take: this machine, this
            // machine on a named list, or every machine in the factory.
            ->where(fn ($query) => $query
                ->where('asset_id', $asset->id)
                ->orWhere(fn ($q) => $q->whereNull('asset_id')
                    ->where('factory_id', $asset->current_factory_id))
                ->orWhereHas('assets', fn ($q) => $q->where('assets.id', $asset->id)))
            ->orderByDesc('end_date')
            ->get()
            ->filter(fn (ServiceContract $contract) => $contract->isActiveOn($on))
            ->values();
    }

    /**
     * A short line for a screen: covered until when, and by whom.
     */
    public function summaryFor(Asset $asset, ?CarbonImmutable $on = null): ?string
    {
        $coverage = $this->forAsset($asset, $on);

        if (! $coverage['covered']) {
            return null;
        }

        $warranty = $coverage['warranty'];
        $contract = $coverage['contracts']->first();

        if ($warranty !== null) {
            return __('vendor.covered_by_warranty', [
                'vendor' => $warranty->vendor?->name ?? __('vendor.unnamed_vendor'),
                'until' => $warranty->end_date->format('Y-m-d'),
            ]);
        }

        return __('vendor.covered_by_contract', [
            'vendor' => $contract->vendor?->name ?? __('vendor.unnamed_vendor'),
            'number' => $contract->contract_number,
            'until' => $contract->end_date->format('Y-m-d'),
        ]);
    }
}
