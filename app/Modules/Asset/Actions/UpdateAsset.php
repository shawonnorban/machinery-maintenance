<?php

declare(strict_types=1);

namespace App\Modules\Asset\Actions;

use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetCategory;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * Updates an asset under optimistic locking (ADR-025).
 *
 * Status and location are deliberately not updatable here: both have their own
 * audited actions. Allowing them through a general update would let a machine
 * move or change state with no history row.
 */
class UpdateAsset
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Asset $asset, array $data, ?string $userId = null): Asset
    {
        $submitted = (int) ($data['version'] ?? 0);

        if ($submitted !== $asset->version) {
            throw ValidationException::withMessages([
                'version' => __('asset.version_conflict', [
                    'current' => $asset->version,
                    'submitted' => $submitted,
                ]),
            ])->status(409);
        }

        unset(
            $data['version'],
            $data['company_id'],
            $data['qr_code'],
            $data['asset_code'],
            $data['status'],
            $data['asset_location_id'],
        );

        $this->assertCategoryBelongsToType($asset, $data);
        $this->assertParentIsNotACycle($asset, $data);

        // Changing factory without moving the asset would leave it pointing at
        // a location in the factory it just left.
        if (array_key_exists('current_factory_id', $data)
            && $data['current_factory_id'] !== $asset->current_factory_id) {
            throw ValidationException::withMessages([
                'current_factory_id' => __('asset.factory_change_needs_transfer'),
            ])->status(409);
        }

        unset($data['current_factory_id']);

        $asset->fill($data);
        $asset->updated_by = $userId;
        $asset->version++;
        $asset->save();

        return $asset;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertCategoryBelongsToType(Asset $asset, array $data): void
    {
        $typeId = $data['asset_type_id'] ?? $asset->asset_type_id;
        $categoryId = $data['asset_category_id'] ?? $asset->asset_category_id;

        $category = AssetCategory::availableTo($this->context->companyId())->find($categoryId);

        if ($category === null || $category->asset_type_id !== $typeId) {
            throw ValidationException::withMessages([
                'asset_category_id' => __('asset.category_type_mismatch'),
            ]);
        }
    }

    /**
     * ADR-007: prevent circular parent-child relationships.
     *
     * A cycle makes every recursive walk of the hierarchy hang, and the
     * hierarchy is walked by cost rollup, maintenance planning and the UI tree.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertParentIsNotACycle(Asset $asset, array $data): void
    {
        $parentId = $data['parent_asset_id'] ?? null;

        if ($parentId === null) {
            return;
        }

        if ($parentId === $asset->id) {
            throw ValidationException::withMessages([
                'parent_asset_id' => __('asset.parent_self'),
            ]);
        }

        $seen = [$asset->id];
        $cursor = Asset::find($parentId);
        $depth = 0;

        while ($cursor !== null) {
            if (in_array($cursor->id, $seen, true)) {
                throw ValidationException::withMessages([
                    'parent_asset_id' => __('asset.parent_cycle'),
                ]);
            }

            $seen[] = $cursor->id;

            // A depth bound as well, so a pre-existing cycle in legacy data
            // cannot hang the request while we are trying to detect one.
            if (++$depth > 32) {
                throw ValidationException::withMessages([
                    'parent_asset_id' => __('asset.parent_too_deep'),
                ]);
            }

            $cursor = $cursor->parent_asset_id === null ? null : Asset::find($cursor->parent_asset_id);
        }
    }
}
