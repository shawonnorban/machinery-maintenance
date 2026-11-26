<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\Merchandise\Models\CadRecord;
use App\Modules\Merchandise\Models\CadSize;
use App\Modules\Merchandise\Models\GarmentPart;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * CAD Consumption Register → Consumption (AnnotechERP 06/09): one CAD
 * record's marker consumption per size and garment part, saved together.
 * A size is entered once per garment part.
 */
class CadSizeApiController extends ApiController
{
    public function index(string $record): JsonResponse
    {
        $this->allow('merchandise.costing.view');
        $row = CadRecord::query()->whereKey($record)->firstOrFail();

        return ApiResponse::ok($this->present($row) + [
            'parts' => GarmentPart::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($p): array => ['value' => $p->id, 'label' => $p->name])->values()->all(),
        ]);
    }

    public function save(Request $request, string $record): JsonResponse
    {
        $this->allow('merchandise.costing.manage');
        $row = CadRecord::query()->whereKey($record)->firstOrFail();
        $data = $request->validate([
            'rows' => ['present', 'array', 'max:200'],
            'rows.*.size' => ['required', 'string', 'max:32'],
            'rows.*.garment_part_id' => ['nullable', Rule::exists('garment_parts', 'id')->where('company_id', $this->caller()->companyId)],
            'rows.*.consumption' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'rows.*.uom' => ['nullable', 'string', 'max:16', UomCodes::rule($this->caller()->companyId)],
        ], [
            'rows.*.size.required' => __('merchandise.cad.size_required'),
            'rows.*.consumption.required' => __('merchandise.cad.consumption_required'),
        ]);
        $seen = [];
        foreach ($data['rows'] as $r) {
            $key = mb_strtoupper(trim($r['size'])).'|'.($r['garment_part_id'] ?? '');
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(['rows' => __('merchandise.cad.size_twice', ['size' => trim($r['size'])])]);
            }
            $seen[$key] = true;
        }

        DB::transaction(function () use ($row, $data): void {
            CadSize::query()->where('cad_id', $row->id)->delete();
            foreach (array_values($data['rows']) as $i => $r) {
                CadSize::query()->create([
                    'cad_id' => $row->id,
                    'size' => trim($r['size']),
                    'garment_part_id' => $r['garment_part_id'] ?? null,
                    'consumption' => $r['consumption'],
                    'uom' => UomCodes::normalize($r['uom'] ?? null),
                    'sort_order' => ($i + 1) * 10,
                ]);
            }
        });

        return ApiResponse::ok($this->present($row));
    }

    /** @return array{record: array<string, mixed>, rows: list<array<string, mixed>>} */
    private function present(CadRecord $row): array
    {
        $parts = GarmentPart::query()->pluck('name', 'id');

        return [
            'record' => ['id' => $row->id, 'job_no' => $row->job_no, 'category' => $row->category],
            'rows' => CadSize::query()->where('cad_id', $row->id)->orderBy('sort_order')->get()->map(fn (CadSize $s): array => [
                'id' => $s->id, 'size' => $s->size, 'garment_part_id' => $s->garment_part_id, 'part' => $s->garment_part_id ? ($parts[$s->garment_part_id] ?? null) : null,
                'consumption' => $s->consumption, 'uom' => $s->uom,
            ])->values()->all(),
        ];
    }
}
