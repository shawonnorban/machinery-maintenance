<?php

declare(strict_types=1);

namespace App\Modules\Vendor\Actions;

use App\Modules\Asset\Models\Asset;
use App\Modules\Vendor\Models\Vendor;
use App\Modules\Vendor\Models\Warranty;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records cover on a machine (SRS 26).
 *
 * Writing the warranty also updates the asset's own warranty_start and
 * warranty_end when this is the cover that runs longest. Those two columns are
 * what the asset list and the register report read, and leaving them stale
 * would mean the machine screen and the warranty screen disagreeing about the
 * same machine.
 */
class RecordWarranty
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?string $userId = null): Warranty
    {
        $asset = Asset::find($data['asset_id'] ?? null);

        if ($asset === null) {
            throw ValidationException::withMessages(['asset_id' => __('vendor.asset_not_found')]);
        }

        $start = CarbonImmutable::parse($data['start_date']);
        $end = CarbonImmutable::parse($data['end_date']);

        if ($end->lessThan($start)) {
            throw ValidationException::withMessages([
                'end_date' => __('vendor.end_before_start'),
            ]);
        }

        if (($data['vendor_id'] ?? null) !== null && Vendor::find($data['vendor_id']) === null) {
            throw ValidationException::withMessages(['vendor_id' => __('vendor.vendor_not_found')]);
        }

        return DB::transaction(function () use ($data, $asset, $start, $end): Warranty {
            $warranty = Warranty::create([
                'asset_id' => $asset->id,
                'vendor_id' => $data['vendor_id'] ?? null,
                'warranty_type' => $data['warranty_type'] ?? 'MANUFACTURER',
                'reference' => $data['reference'] ?? null,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'coverage' => $data['coverage'] ?? null,
                'exclusions' => $data['exclusions'] ?? null,
                'status' => $end->isPast() ? 'EXPIRED' : 'ACTIVE',
            ]);

            $this->syncAssetDates($asset, $warranty);

            return $warranty;
        });
    }

    /**
     * The asset carries the longest cover it has, so the machine screen and
     * the warranty list cannot disagree.
     */
    private function syncAssetDates(Asset $asset, Warranty $warranty): void
    {
        $longest = Warranty::where('asset_id', $asset->id)
            ->where('status', '!=', 'VOID')
            ->orderByDesc('end_date')
            ->first();

        if ($longest === null) {
            return;
        }

        $earliestStart = Warranty::where('asset_id', $asset->id)
            ->where('status', '!=', 'VOID')
            ->orderBy('start_date')
            ->value('start_date');

        $asset->forceFill([
            'warranty_start' => $earliestStart,
            'warranty_end' => $longest->end_date->toDateString(),
        ])->save();
    }
}
