<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Resources;

use App\Modules\DyeHouse\Models\DyePtChallan;
use App\Modules\DyeHouse\Models\DyePtProgramme;
use App\Modules\DyeHouse\Models\DyePtReceipt;
use App\Modules\DyeHouse\Support\DyeBatches;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sub-Contract Pre-Treatment Challan: the challan fabric leaves on for
 * subcontract pre-treatment, and the party's programmes it carries — each on
 * one challan only. Writing it fills in those programmes' challan columns.
 */
class DyePtChallanResource extends DyeBatchResource
{
    /** @var list<string>|null the programmes the save carries, held for afterSave */
    private ?array $programmes = null;

    public function key(): string
    {
        return 'dye-pt-challans';
    }

    public function model(): string
    {
        return DyePtChallan::class;
    }

    public function columns(): array
    {
        return ['challan_no', 'supplier_id', 'challan_date', 'store_id', 'gate_pass_no'];
    }

    public function query(): Builder
    {
        return DyePtChallan::query()->orderByDesc('challan_date')->orderByDesc('challan_no');
    }

    public function messages(): array
    {
        return ['supplier_id.required' => __('dyehouse.batch.v.supplier'), 'challan_date.required' => __('dyehouse.batch.v.challan_date')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'supplier_id' => [$required, $this->own('mp_suppliers')],
            'challan_date' => [$required, 'date'],
            'store_id' => ['nullable', $this->own('stores')],
            'gate_pass_no' => ['nullable', 'string', 'max:40'],
            // The programmes carried, as the form's ticks send them: "id,id,id".
            'programme_ids' => ['nullable', 'string', 'max:4000'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (array_key_exists('programme_ids', $data)) {
            $ids = array_values(array_unique(array_filter(explode(',', (string) $data['programme_ids']))));
            $supplier = $data['supplier_id'] ?? $row?->getAttribute('supplier_id');
            foreach (DyePtProgramme::query()->whereIn('id', $ids)->get() as $p) {
                if ($p->supplier_id !== $supplier) {
                    throw ValidationException::withMessages(['programme_ids' => __('dyehouse.batch.v.programme_other_party', ['no' => $p->program_no])]);
                }
                if ($p->challan_id && $p->challan_id !== $row?->getKey()) {
                    throw ValidationException::withMessages(['programme_ids' => __('dyehouse.batch.v.programme_on_challan', ['no' => $p->program_no])]);
                }
            }
            if (count($ids) !== DyePtProgramme::query()->whereIn('id', $ids)->count()) {
                throw ValidationException::withMessages(['programme_ids' => __('dyehouse.batch.v.programme_unknown')]);
            }
            $this->programmes = $ids;
            unset($data['programme_ids']);
        }
        if ($row && isset($data['supplier_id']) && $data['supplier_id'] !== $row->getAttribute('supplier_id') && $this->programmes === null
            && DyePtProgramme::query()->where('challan_id', $row->getKey())->exists()) {
            throw ValidationException::withMessages(['supplier_id' => __('dyehouse.batch.v.challan_party_fixed')]);
        }
        if (! $row) {
            $data += ['challan_no' => DyeBatches::nextNumber('CLN-PT-', 'dye_pt_challans', 'challan_no', $data['challan_date']), 'created_by' => $this->actingUserId()];
        }

        return $data;
    }

    public function afterSave(Model $row, bool $created): void
    {
        if ($this->programmes === null) {
            return;
        }
        DyePtProgramme::query()->where('challan_id', $row->getKey())->whereNotIn('id', $this->programmes)->update(['challan_id' => null]);
        DyePtProgramme::query()->whereIn('id', $this->programmes)->update(['challan_id' => $row->getKey()]);
        $this->programmes = null;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $n = $this->names();
        $programmes = DyePtProgramme::query()->where('challan_id', $row->getKey())->orderBy('program_no')->get(['id', 'program_no']);
        $out['supplier'] = $n['suppliers'][$row->getAttribute('supplier_id')] ?? null;
        $out['store'] = $n['stores'][$row->getAttribute('store_id')] ?? null;
        $out['programs'] = $programmes->count();
        $out['program_list'] = $programmes->pluck('program_no')->implode(', ');
        $out['programme_ids'] = $programmes->pluck('id')->implode(',');
        $out['company'] = DB::table('companies')->where('id', $this->company())->value('name');

        return $out;
    }

    public function beforeDelete(Model $row): void
    {
        if (DyePtReceipt::query()->where('challan_id', $row->getKey())->exists()) {
            throw ValidationException::withMessages(['challan_no' => __('dyehouse.batch.v.challan_received', ['no' => $row->getAttribute('challan_no')])]);
        }
    }
}
