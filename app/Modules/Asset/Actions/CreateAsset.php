<?php

declare(strict_types=1);

namespace App\Modules\Asset\Actions;

use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetCategory;
use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Asset\Models\AssetStatusHistory;
use App\Modules\Asset\Services\QrTokenGenerator;
use App\Modules\Billing\Services\EntitlementGuard;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates an asset.
 *
 * Called by both the web controller and the API controller, so a rule written
 * here applies to both entry points. A rule written in a controller would
 * apply to only one, which is a defect (ADR-066).
 */
class CreateAsset
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly QrTokenGenerator $qr,
        private readonly EntitlementGuard $entitlements,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?string $userId = null): Asset
    {
        $companyId = $this->context->companyId();

        // Before the work, not after: a customer at their machine limit should
        // be told so instead of filling in a form that then fails.
        $this->entitlements->assertCanAdd('ACTIVE_ASSETS');

        $this->assertCategoryBelongsToType($data);
        $this->assertLocationMatchesFactory($data);
        $this->assertDateOrder($data);

        // company_id is never taken from input (ADR-064); the trait assigns it
        // from resolved context.
        unset($data['company_id'], $data['qr_code'], $data['version']);

        $status = $data['status'] ?? 'DRAFT';

        if (! in_array($status, Asset::CREATABLE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'An asset may only be created as '
                    .implode(', ', Asset::CREATABLE_STATUSES)
                    .'. Later states are reached through a status change, which is audited.',
            ]);
        }

        return DB::transaction(function () use ($data, $status, $companyId, $userId): Asset {
            $asset = new Asset($data);
            $asset->status = $status;
            $asset->qr_code = $this->qr->forAsset($companyId);
            $asset->created_by = $userId;
            $asset->version = 1;
            $asset->save();

            // The opening history row, so an asset's audit trail has no gap
            // between creation and its first transition.
            AssetStatusHistory::create([
                'asset_id' => $asset->id,
                'from_status' => null,
                'to_status' => $status,
                'changed_by' => $userId,
                'changed_at' => now(),
                'reason' => 'Asset created',
                'source' => 'MANUAL',
            ]);

            return $asset;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertCategoryBelongsToType(array $data): void
    {
        $category = AssetCategory::availableTo($this->context->companyId())
            ->find($data['asset_category_id'] ?? null);

        if ($category === null) {
            throw ValidationException::withMessages([
                'asset_category_id' => 'The selected category is not available to this company.',
            ]);
        }

        if ($category->asset_type_id !== ($data['asset_type_id'] ?? null)) {
            throw ValidationException::withMessages([
                'asset_category_id' => 'The selected category does not belong to the selected asset type.',
            ]);
        }
    }

    /**
     * ERD Section 32 rule 23: an asset's location must resolve to a location
     * whose factory matches the asset's factory. Otherwise a Dhaka asset could
     * be recorded at a Gazipur workstation.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertLocationMatchesFactory(array $data): void
    {
        $location = AssetLocation::find($data['asset_location_id'] ?? null);

        if ($location === null) {
            throw ValidationException::withMessages([
                'asset_location_id' => 'The selected location does not exist.',
            ]);
        }

        if ($location->factory_id !== ($data['current_factory_id'] ?? null)) {
            throw ValidationException::withMessages([
                'asset_location_id' => 'The selected location belongs to a different factory.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertDateOrder(array $data): void
    {
        $purchase = $data['purchase_date'] ?? null;
        $installation = $data['installation_date'] ?? null;
        $commissioning = $data['commissioning_date'] ?? null;

        if ($purchase && $installation && $installation < $purchase) {
            throw ValidationException::withMessages([
                'installation_date' => 'Installation cannot precede purchase.',
            ]);
        }

        if ($installation && $commissioning && $commissioning < $installation) {
            throw ValidationException::withMessages([
                'commissioning_date' => 'Commissioning cannot precede installation.',
            ]);
        }

        $warrantyStart = $data['warranty_start'] ?? null;
        $warrantyEnd = $data['warranty_end'] ?? null;

        if ($warrantyStart && $warrantyEnd && $warrantyEnd <= $warrantyStart) {
            throw ValidationException::withMessages([
                'warranty_end' => 'Warranty end must be after warranty start.',
            ]);
        }
    }
}
