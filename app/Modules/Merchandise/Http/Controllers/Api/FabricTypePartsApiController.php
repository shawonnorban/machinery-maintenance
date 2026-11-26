<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Merchandise\Models\FabricType;
use App\Modules\Merchandise\Models\FabricTypeGarmentPart;
use App\Modules\Merchandise\Models\GarmentPart;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Fabric Type Master → GARM Part List (AnnotechERP 06/01): pick a fabric type,
 * tick the garment parts it may be cut for. Saved as a whole.
 */
class FabricTypePartsApiController extends ApiController
{
    public function show(string $fabricType): JsonResponse
    {
        $this->allow('merchandise.master.view');

        $type = FabricType::query()->whereKey($fabricType)->firstOrFail();

        return ApiResponse::ok($this->rows($type));
    }

    public function save(Request $request, string $fabricType): JsonResponse
    {
        $this->allow('merchandise.master.manage');

        $type = FabricType::query()->whereKey($fabricType)->firstOrFail();
        $company = $this->caller()->companyId;
        $data = $request->validate([
            'garment_part_ids' => ['present', 'array'],
            'garment_part_ids.*' => ['string', Rule::exists('garment_parts', 'id')->where('company_id', $company)],
        ]);

        DB::transaction(function () use ($type, $data, $company): void {
            FabricTypeGarmentPart::query()->where('fabric_type_id', $type->id)->whereNotIn('garment_part_id', $data['garment_part_ids'])->delete();
            foreach (array_unique($data['garment_part_ids']) as $partId) {
                FabricTypeGarmentPart::query()->firstOrCreate(['fabric_type_id' => $type->id, 'garment_part_id' => $partId], ['company_id' => $company]);
            }
        });

        return ApiResponse::ok($this->rows($type));
    }

    /**
     * Every garment part, with the ones this type may be cut for ticked.
     *
     * @return list<array<string, mixed>>
     */
    private function rows(FabricType $type): array
    {
        $ticked = FabricTypeGarmentPart::query()->where('fabric_type_id', $type->id)->pluck('garment_part_id')->flip();

        return GarmentPart::query()->orderBy('name')->get()
            ->map(fn (GarmentPart $p): array => ['id' => $p->id, 'code' => $p->code, 'name' => $p->name, 'fabric_group' => $p->fabric_group, 'active' => $p->active, 'assigned' => $ticked->has($p->id)])
            ->all();
    }
}
