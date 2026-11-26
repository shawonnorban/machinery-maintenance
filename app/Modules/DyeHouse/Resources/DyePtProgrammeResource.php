<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Resources;

use App\Modules\DyeHouse\Models\DyePtChallan;
use App\Modules\DyeHouse\Models\DyePtProgramme;
use App\Modules\DyeHouse\Support\DyeBatches;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sub-Contract Pre-Treatment Programme: heat-setting and other pre-treatment
 * sent out to a subcontractor. The programme is raised first and despatched
 * later — its challan columns fill in when the challan that carries it is
 * written, and from then it is that challan's to change.
 */
class DyePtProgrammeResource extends DyeBatchResource
{
    public function key(): string
    {
        return 'dye-pt-programmes';
    }

    public function model(): string
    {
        return DyePtProgramme::class;
    }

    public function columns(): array
    {
        return ['program_no', 'supplier_id', 'issue_date', 'exp_delivery_date', 'store_id', 'gate_pass_no', 'challan_id'];
    }

    public function query(): Builder
    {
        return DyePtProgramme::query()->orderByDesc('issue_date')->orderByDesc('program_no');
    }

    public function messages(): array
    {
        return [
            'supplier_id.required' => __('dyehouse.batch.v.supplier'), 'issue_date.required' => __('dyehouse.batch.v.issue_date'),
            'exp_delivery_date.after_or_equal' => __('dyehouse.batch.v.delivery_before_issue'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'supplier_id' => [$required, $this->own('mp_suppliers')],
            'issue_date' => [$required, 'date'],
            'exp_delivery_date' => ['nullable', 'date', 'after_or_equal:'.(request()->input('issue_date') ?? $row?->getAttribute('issue_date')?->toDateString() ?? '1900-01-01')],
            'store_id' => ['nullable', $this->own('stores')],
            'gate_pass_no' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if ($row && $row->getAttribute('challan_id') && isset($data['supplier_id']) && $data['supplier_id'] !== $row->getAttribute('supplier_id')) {
            throw ValidationException::withMessages(['supplier_id' => __('dyehouse.batch.v.programme_on_challan', ['no' => $row->getAttribute('program_no')])]);
        }
        if (! $row) {
            $data += ['program_no' => DyeBatches::nextNumber('DSC-PT-', 'dye_pt_programmes', 'program_no', $data['issue_date']), 'created_by' => $this->actingUserId()];
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $n = $this->names();
        $challan = $row->getAttribute('challan_id') ? DyePtChallan::query()->whereKey($row->getAttribute('challan_id'))->first(['challan_no', 'challan_date']) : null;
        $out['supplier'] = $n['suppliers'][$row->getAttribute('supplier_id')] ?? null;
        $out['store'] = $n['stores'][$row->getAttribute('store_id')] ?? null;
        $out['challan_no'] = $challan?->challan_no;
        $out['challan_date'] = $challan?->challan_date?->toDateString();
        $out['company'] = DB::table('companies')->where('id', $this->company())->value('name');

        return $out;
    }

    public function beforeDelete(Model $row): void
    {
        if ($row->getAttribute('challan_id')) {
            throw ValidationException::withMessages(['program_no' => __('dyehouse.batch.v.programme_on_challan', ['no' => $row->getAttribute('program_no')])]);
        }
    }
}
