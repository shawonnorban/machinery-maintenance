<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Resources;

use App\Modules\DyeHouse\Models\DyePtChallan;
use App\Modules\DyeHouse\Models\DyePtProgramme;
use App\Modules\DyeHouse\Models\DyePtReceipt;
use App\Modules\DyeHouse\Support\DyeBatches;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Sub-Contract Pre-Treatment Receipt: pre-treated fabric received back. The
 * programmes a return closes are the ones the challan it came back on
 * carried; the transfer party is where the goods actually came from, when
 * that is not the party they were sent to.
 */
class DyePtReceiptResource extends DyeBatchResource
{
    public function key(): string
    {
        return 'dye-pt-receipts';
    }

    public function model(): string
    {
        return DyePtReceipt::class;
    }

    public function columns(): array
    {
        return ['mrr_no', 'supplier_id', 'transfer_party_id', 'issue_date', 'qc_no', 'challan_id', 'store_id'];
    }

    public function query(): Builder
    {
        return DyePtReceipt::query()->orderByDesc('issue_date')->orderByDesc('mrr_no');
    }

    public function messages(): array
    {
        return ['supplier_id.required' => __('dyehouse.batch.v.sc_party'), 'issue_date.required' => __('dyehouse.batch.v.issue_date')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'supplier_id' => [$required, $this->own('mp_suppliers')],
            'transfer_party_id' => ['nullable', $this->own('mp_suppliers')],
            'issue_date' => [$required, 'date'],
            'qc_no' => ['nullable', 'string', 'max:40'],
            'challan_id' => ['nullable', $this->own('dye_pt_challans')],
            'store_id' => ['nullable', $this->own('stores')],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $challan = $data['challan_id'] ?? $row?->getAttribute('challan_id');
        if ($challan) {
            $c = DyePtChallan::query()->findOrFail($challan);
            if ($c->supplier_id !== ($data['supplier_id'] ?? $row?->getAttribute('supplier_id'))) {
                throw ValidationException::withMessages(['challan_id' => __('dyehouse.batch.v.challan_other_party', ['no' => $c->challan_no])]);
            }
        }
        if (! $row) {
            $data += ['mrr_no' => DyeBatches::nextNumber('MRR-PT-', 'dye_pt_receipts', 'mrr_no', $data['issue_date']), 'created_by' => $this->actingUserId()];
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $n = $this->names();
        $challan = $row->getAttribute('challan_id') ? DyePtChallan::query()->whereKey($row->getAttribute('challan_id'))->first(['challan_no', 'challan_date']) : null;
        $out['supplier'] = $n['suppliers'][$row->getAttribute('supplier_id')] ?? null;
        $out['transfer_party'] = $n['suppliers'][$row->getAttribute('transfer_party_id')] ?? null;
        $out['store'] = $n['stores'][$row->getAttribute('store_id')] ?? null;
        $out['challan_no'] = $challan?->challan_no;
        $out['challan_date'] = $challan?->challan_date?->toDateString();
        $out['program_list'] = $row->getAttribute('challan_id') ? DyePtProgramme::query()->where('challan_id', $row->getAttribute('challan_id'))->orderBy('program_no')->pluck('program_no')->implode(', ') : null;

        return $out;
    }
}
