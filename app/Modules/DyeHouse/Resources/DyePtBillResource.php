<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Resources;

use App\Modules\DyeHouse\Models\DyePtBill;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Sub-Contract Pre-Treatment Bill: a subcontractor's bill for pre-treatment,
 * and where it stands. A corrected bill is a revision of the one it replaces:
 * it names the original in Ref.Bill#, which must be one of the party's bills,
 * and a bill number and revision are filed once per party.
 */
class DyePtBillResource extends DyeBatchResource
{
    public function key(): string
    {
        return 'dye-pt-bills';
    }

    public function model(): string
    {
        return DyePtBill::class;
    }

    public function columns(): array
    {
        return ['supplier_id', 'bill_no', 'bill_date', 'amount', 'ref_bill_no', 'revision_no', 'revision_descr', 'status'];
    }

    public function query(): Builder
    {
        return DyePtBill::query()->orderByDesc('bill_date')->orderByDesc('bill_no');
    }

    public function messages(): array
    {
        return [
            'supplier_id.required' => __('dyehouse.batch.v.party'), 'bill_no.required' => __('dyehouse.batch.v.bill_no'), 'bill_date.required' => __('dyehouse.batch.v.bill_date'),
            'amount.required' => __('dyehouse.batch.v.amount'), 'amount.gt' => __('dyehouse.batch.v.amount'), 'status.required' => __('dyehouse.batch.v.status'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'supplier_id' => [$required, $this->own('mp_suppliers')],
            'bill_no' => [$required, 'string', 'max:40'],
            'bill_date' => [$required, 'date'],
            'amount' => [$required, 'numeric', 'gt:0', 'max:999999999999'],
            'ref_bill_no' => ['nullable', 'string', 'max:40'],
            'revision_no' => ['nullable', 'integer', 'min:0', 'max:999'],
            'revision_descr' => ['nullable', 'string', 'max:2000'],
            'status' => [$required, Rule::in(DyePtBill::STATUSES)],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $supplier = $data['supplier_id'] ?? $row?->getAttribute('supplier_id');
        $billNo = trim((string) ($data['bill_no'] ?? $row?->getAttribute('bill_no')));
        $revision = array_key_exists('revision_no', $data) ? $data['revision_no'] : $row?->getAttribute('revision_no');
        if (isset($data['bill_no'])) {
            $data['bill_no'] = $billNo;
        }
        $twin = DyePtBill::query()->where('supplier_id', $supplier)->where('bill_no', $billNo)
            ->when($revision === null, fn ($q) => $q->whereNull('revision_no'), fn ($q) => $q->where('revision_no', $revision))
            ->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
        if ($twin) {
            throw ValidationException::withMessages(['bill_no' => __('dyehouse.batch.v.bill_taken', ['no' => $billNo])]);
        }
        $ref = trim((string) ($data['ref_bill_no'] ?? ''));
        if ($ref !== '' && ! DyePtBill::query()->where('supplier_id', $supplier)->where('bill_no', $ref)->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists()) {
            throw ValidationException::withMessages(['ref_bill_no' => __('dyehouse.batch.v.ref_bill_unknown', ['no' => $ref])]);
        }
        if (! $row) {
            $data['created_by'] = $this->actingUserId();
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['supplier'] = $this->names()['suppliers'][$row->getAttribute('supplier_id')] ?? null;
        $out['amount'] = (float) $row->getAttribute('amount');

        return $out;
    }
}
