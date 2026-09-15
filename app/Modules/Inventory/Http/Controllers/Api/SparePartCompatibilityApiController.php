<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers\Api;

use App\Modules\Asset\Models\AssetModel;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Models\SparePartCompatibility;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Which part fits what, and what will do instead (SRS 20; mirrors the web
 * `CompatibilityController`, which this delegates to unchanged per
 * ADR-003 — no Action class exists here on either side, both write the
 * `SparePartCompatibility` row directly).
 */
class SparePartCompatibilityApiController extends ApiController
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(SparePart $part): JsonResponse
    {
        $this->allow('inventory.part.view_any');

        $rows = SparePartCompatibility::where('spare_part_id', $part->id)
            ->with(['assetModel:id,model', 'substituteFor:id,part_number,name'])
            ->get();

        return ApiResponse::ok($rows->map(fn (SparePartCompatibility $row): array => $this->summary($row))->all());
    }

    public function store(Request $request, SparePart $part): JsonResponse
    {
        $this->allow('inventory.part.update');

        $data = $request->validate([
            'compatibility_type' => ['required', Rule::in(SparePartCompatibility::TYPES)],
            'asset_model_id' => ['nullable', 'string', 'size:26'],
            'substitute_for_part_id' => ['nullable', 'string', 'size:26'],
        ]);

        if ($data['compatibility_type'] === 'FITS') {
            $this->assertModelVisible($data['asset_model_id'] ?? null);

            $exists = SparePartCompatibility::where('spare_part_id', $part->id)
                ->where('asset_model_id', $data['asset_model_id'])
                ->exists();

            if ($exists) {
                throw ApiException::of(ErrorCode::VALIDATION_ERROR, __('inventory.compatibility_already_listed'), [
                    'asset_model_id' => [__('inventory.compatibility_already_listed')],
                ]);
            }

            $row = SparePartCompatibility::create([
                'company_id' => $this->context->companyId(),
                'spare_part_id' => $part->id,
                'asset_model_id' => $data['asset_model_id'],
                'compatibility_type' => 'FITS',
            ]);

            return ApiResponse::created($this->summary($row->load('assetModel:id,model')));
        }

        $substituteFor = SparePart::find($data['substitute_for_part_id'] ?? null);

        if ($substituteFor === null) {
            throw ApiException::of(ErrorCode::VALIDATION_ERROR, __('inventory.unknown_part'), [
                'substitute_for_part_id' => [__('inventory.unknown_part')],
            ]);
        }

        if ($substituteFor->id === $part->id) {
            throw ApiException::of(ErrorCode::VALIDATION_ERROR, __('inventory.cannot_substitute_itself'), [
                'substitute_for_part_id' => [__('inventory.cannot_substitute_itself')],
            ]);
        }

        $row = SparePartCompatibility::create([
            'company_id' => $this->context->companyId(),
            'spare_part_id' => $part->id,
            'substitute_for_part_id' => $substituteFor->id,
            'compatibility_type' => 'SUBSTITUTE',
        ]);

        return ApiResponse::created($this->summary($row->load('substituteFor:id,part_number,name')));
    }

    public function destroy(SparePart $part, SparePartCompatibility $compatibility): JsonResponse
    {
        $this->allow('inventory.part.update');

        if ($compatibility->spare_part_id !== $part->id) {
            abort(404);
        }

        $compatibility->delete();

        return ApiResponse::noContent();
    }

    private function assertModelVisible(?string $modelId): void
    {
        $visible = filled($modelId)
            && AssetModel::availableTo($this->context->companyId())->whereKey($modelId)->exists();

        if (! $visible) {
            throw ApiException::of(ErrorCode::VALIDATION_ERROR, __('inventory.unknown_asset_model'), [
                'asset_model_id' => [__('inventory.unknown_asset_model')],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(SparePartCompatibility $row): array
    {
        return [
            'id' => $row->id,
            'compatibility_type' => $row->compatibility_type,
            'asset_model' => $row->relationLoaded('assetModel') && $row->assetModel !== null
                ? ['id' => $row->asset_model_id, 'model' => $row->assetModel->model]
                : ($row->asset_model_id !== null ? ['id' => $row->asset_model_id] : null),
            'substitute_for' => $row->relationLoaded('substituteFor') && $row->substituteFor !== null
                ? [
                    'id' => $row->substitute_for_part_id,
                    'part_number' => $row->substituteFor->part_number,
                    'name' => $row->substituteFor->name,
                ]
                : ($row->substitute_for_part_id !== null ? ['id' => $row->substitute_for_part_id] : null),
        ];
    }
}
