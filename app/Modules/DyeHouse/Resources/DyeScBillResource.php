<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Resources;

use App\Modules\DyeHouse\Models\DyeScBill;
use App\Modules\DyeHouse\Support\DyeBatches;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Out-House Process Bill: an outside dye house's bill (DF-SC-BL-YYYYMM####),
 * its amount and standing, and its revision trail — a bill naming the bill
 * it revises (the party's own) takes the next revision number. A completed
 * or cancelled bill reads only.
 */
class DyeScBillResource extends DyeBatchResource
{
    public function key(): string
    {
        return 'dye-sc-bills';
    }

    public function model(): string
    {
        return DyeScBill::class;
    }

    public function viewPermission(): string
    {
        return 'dyehouse.sc.view';
    }

    public function managePermission(): string
    {
        return 'dyehouse.sc.manage';
    }

    public function columns(): array
    {
        return ['bill_no', 'bill_date', 'supplier_id', 'amount', 'ref_bill_id', 'revision_no', 'revision_descr', 'status', 'remarks'];
    }

    public function query(): Builder
    {
        return DyeScBill::query()->orderByDesc('bill_date')->orderByDesc('bill_no');
    }

    public function messages(): array
    {
        return ['bill_date.required' => __('dyehouse.sc.v.bill_date'), 'supplier_id.required' => __('dyehouse.sc.v.party'), 'amount.required' => __('dyehouse.sc.v.amount'),
            'amount.gt' => __('dyehouse.sc.v.amount'), 'status.required' => __('dyehouse.sc.v.status')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'bill_date' => [$required, 'date'],
            'supplier_id' => [$required, $this->own('mp_suppliers')],
            'amount' => [$required, 'numeric', 'gt:0', 'max:9999999999'],
            'ref_bill_id' => ['nullable', $this->own('dye_sc_bills')],
            'revision_descr' => ['nullable', 'string', 'max:255'],
            'status' => [$required, Rule::in(DyeScBill::STATUSES)],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if ($row && in_array($row->getAttribute('status'), ['COMPLETED', 'CANCELLED'], true)) {
            throw ValidationException::withMessages(['status' => __('dyehouse.sc.v.bill_closed', ['no' => $row->getAttribute('bill_no')])]);
        }
        $get = fn (string $k) => array_key_exists($k, $data) ? $data[$k] : $row?->getAttribute($k);
        $ref = $get('ref_bill_id') ? DyeScBill::query()->find($get('ref_bill_id')) : null;
        if ($ref && ($ref->supplier_id !== $get('supplier_id') || $ref->getKey() === $row?->getKey())) {
            throw ValidationException::withMessages(['ref_bill_id' => __('dyehouse.sc.v.ref_other')]);
        }
        $data['revision_no'] = $ref ? (int) $ref->revision_no + 1 : 0;
        unset($data['bill_no']);
        if (! $row) {
            $data['bill_no'] = DyeBatches::nextNumber('DF-SC-BL-', 'dye_sc_bills', 'bill_no', (string) $get('bill_date'), 'Ym');
            $data['created_by'] = $this->actingUserId();
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['supplier'] = $this->names()['suppliers'][$row->getAttribute('supplier_id')] ?? null;
        $out['ref_bill_no'] = $row->getAttribute('ref_bill_id') ? DyeScBill::query()->whereKey($row->getAttribute('ref_bill_id'))->value('bill_no') : null;
        $out['amount'] = (float) $row->getAttribute('amount');

        return $out;
    }
}
