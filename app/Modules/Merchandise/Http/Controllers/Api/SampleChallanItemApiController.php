<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\Merchandise\Models\SampleChallan;
use App\Modules\Merchandise\Models\SampleChallanItem;
use App\Modules\Merchandise\Models\SampleType;
use App\Modules\Merchandise\Support\SampleTypeDefaults;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Sample Delivery Challan → Challan Items (AnnotechERP 06/10): what is on one
 * challan. Add item keeps the item at once, as Brickland does ("Added …");
 * the description, quantity and unit are then changed in place and saved
 * together, and an item left out is removed.
 */
class SampleChallanItemApiController extends ApiController
{
    public function index(string $challan): JsonResponse
    {
        $this->allow('merchandise.sample.view');
        $row = SampleChallan::query()->whereKey($challan)->firstOrFail();
        SampleTypeDefaults::ensure($this->caller()->companyId);

        return ApiResponse::ok($this->present($row) + [
            'types' => SampleType::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn (SampleType $t): array => ['value' => $t->id, 'label' => $t->name])->values()->all(),
        ]);
    }

    public function add(Request $request, string $challan): JsonResponse
    {
        $this->allow('merchandise.sample.manage');
        $row = SampleChallan::query()->whereKey($challan)->firstOrFail();
        $data = $request->validate($this->itemRules('') + ['sample_type_id' => ['nullable', Rule::exists('sample_types', 'id')->where('company_id', $this->caller()->companyId)]], $this->itemMessages(''));
        $item = SampleChallanItem::query()->create($this->values($data) + [
            'challan_id' => $row->id, 'sample_type_id' => $data['sample_type_id'] ?? null,
            'sort_order' => 10 + (int) SampleChallanItem::query()->where('challan_id', $row->id)->max('sort_order'),
        ]);

        return ApiResponse::ok($this->present($row) + ['added' => $item->description]);
    }

    public function save(Request $request, string $challan): JsonResponse
    {
        $this->allow('merchandise.sample.manage');
        $row = SampleChallan::query()->whereKey($challan)->firstOrFail();
        $data = $request->validate(['rows' => ['present', 'array', 'max:300'], 'rows.*.id' => ['required', 'string']] + $this->itemRules('rows.*.'), $this->itemMessages('rows.*.'));
        $existing = SampleChallanItem::query()->where('challan_id', $row->id)->get()->keyBy('id');
        $kept = collect($data['rows'])->pluck('id')->all();
        foreach ($kept as $id) {
            if (! $existing->has($id)) {
                throw ValidationException::withMessages(['rows' => __('merchandise.challan.item_gone')]);
            }
        }
        DB::transaction(function () use ($row, $data, $existing, $kept): void {
            SampleChallanItem::query()->where('challan_id', $row->id)->whereNotIn('id', $kept)->delete();
            foreach (array_values($data['rows']) as $i => $r) {
                $existing[$r['id']]->update($this->values($r) + ['sort_order' => ($i + 1) * 10]);
            }
        });

        return ApiResponse::ok($this->present($row));
    }

    /** @return array<string, list<mixed>> */
    private function itemRules(string $prefix): array
    {
        return [
            $prefix.'description' => ['required', 'string', 'max:255'],
            $prefix.'qty' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            $prefix.'uom' => ['nullable', 'string', 'max:16', UomCodes::rule($this->caller()->companyId)],
        ];
    }

    /** @return array<string, string> */
    private function itemMessages(string $prefix): array
    {
        return [
            $prefix.'description.required' => __('merchandise.challan.description_required'),
            $prefix.'qty.required' => __('merchandise.challan.qty_required'),
            $prefix.'qty.gt' => __('merchandise.challan.qty_positive'),
        ];
    }

    /** @return array<string, mixed> */
    private function values(array $r): array
    {
        return ['description' => trim($r['description']), 'qty' => $r['qty'], 'uom' => UomCodes::normalize($r['uom'] ?? null)];
    }

    /** @return array{challan: array<string, mixed>, rows: list<array<string, mixed>>} */
    private function present(SampleChallan $row): array
    {
        $items = SampleChallanItem::query()->where('challan_id', $row->id)->orderBy('sort_order')->get();
        $types = SampleType::query()->whereIn('id', $items->pluck('sample_type_id')->filter())->pluck('name', 'id');

        return [
            'challan' => ['id' => $row->id, 'challan_no' => $row->challan_no],
            'rows' => $items->map(fn (SampleChallanItem $i): array => [
                'id' => $i->id, 'description' => $i->description, 'sample_type_id' => $i->sample_type_id, 'type' => $i->sample_type_id ? ($types[$i->sample_type_id] ?? null) : null,
                'qty' => $i->qty, 'uom' => $i->uom,
            ])->values()->all(),
        ];
    }
}
