<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Accounting\Models\AccAccount;
use App\Modules\Merchandise\Models\AccReceiptCost;
use App\Modules\Merchandise\Models\ProcCostHead;
use App\Modules\Merchandise\Support\MerchandiseDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Procurement Cost Head: the other costs an accessories receipt carries —
 * landed into stock value or expensed (on its expense ledger), inside or
 * outside the VAT base. A receipt copies the two flags onto its cost line
 * when saved, so changing a head never restates a receipt on file. Trims &
 * Accessories (AnnotechERP 06/06).
 */
class ProcCostHeadResource extends MasterResource
{
    public function key(): string
    {
        return 'proc-cost-heads';
    }

    public function model(): string
    {
        return ProcCostHead::class;
    }

    public function viewPermission(): string
    {
        return 'merchandise.accessories.view';
    }

    public function managePermission(): string
    {
        return 'merchandise.accessories.manage';
    }

    public function columns(): array
    {
        return ['code', 'name', 'landed', 'vat_applicable', 'expense_ledger_id', 'sort_order', 'active'];
    }

    public function booleans(): array
    {
        return ['active', 'landed', 'vat_applicable'];
    }

    public function defaults(): array
    {
        return ['active' => true, 'landed' => true, 'vat_applicable' => false];
    }

    public function query(): Builder
    {
        MerchandiseDefaults::ensureCostHeads($this->company());

        return ProcCostHead::query()->orderBy('sort_order')->orderBy('code');
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'code' => [$required, 'string', 'max:24', $this->uniqueCode($row)],
            'name' => [$required, 'string', 'max:120'],
            'landed' => ['sometimes', 'boolean'],
            'vat_applicable' => ['sometimes', 'boolean'],
            'expense_ledger_id' => ['nullable', Rule::exists('acc_accounts', 'id')->where('company_id', $this->company())],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => __('merchandise.acc_po.required', ['field' => __('merchandise.master.pch.f.code')]),
            'name.required' => __('merchandise.acc_po.required', ['field' => __('merchandise.master.pch.f.name')]),
        ];
    }

    /** A head that is not landed is expensed, so it needs the ledger it is expensed on. */
    public function beforeSave(array $data, ?Model $row): array
    {
        $data = parent::beforeSave($data, $row);
        $head = array_merge($row?->only(['landed', 'expense_ledger_id']) ?? ['landed' => true, 'expense_ledger_id' => null], $data);
        if (! ($head['landed'] ?? true) && empty($head['expense_ledger_id'])) {
            throw ValidationException::withMessages(['expense_ledger_id' => __('merchandise.master.pch.ledger_required')]);
        }
        $data['sort_order'] = (int) ($data['sort_order'] ?? $row?->getAttribute('sort_order') ?? 0);

        return $data;
    }

    /** A head a receipt carries stays; switch it off instead. */
    public function beforeDelete(Model $row): void
    {
        if (AccReceiptCost::query()->where('cost_head_id', $row->getKey())->exists()) {
            throw ValidationException::withMessages(['id' => __('merchandise.master.in_use')]);
        }
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $ledger = $row->getAttribute('expense_ledger_id') ? AccAccount::query()->whereKey($row->getAttribute('expense_ledger_id'))->first(['code', 'name']) : null;
        $out['expense_ledger'] = $ledger ? "{$ledger->code} — {$ledger->name}" : null;

        return $out;
    }
}
