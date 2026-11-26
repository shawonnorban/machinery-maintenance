<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Resources;

use App\Modules\DyeHouse\Models\DyeChemLoanOpening;
use App\Modules\Inventory\Support\UomCodes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Chemical Loan Opening Balance: what each supplier had lent us (Loan
 * Received, not yet given back) and we them (Loan Issued, not yet returned)
 * of one item on opening day — one position per supplier per item, carrying
 * both directions. A finalised position stops being editable. It moves no
 * stock: the drums themselves are on the stock opening.
 */
class DyeChemLoanOpeningResource extends DyeBatchResource
{
    /** @var array<string, string>|null */
    private ?array $items = null;

    public function key(): string
    {
        return 'dye-chem-loan-openings';
    }

    public function model(): string
    {
        return DyeChemLoanOpening::class;
    }

    public function viewPermission(): string
    {
        return 'dyehouse.chemstore.view';
    }

    public function managePermission(): string
    {
        return 'dyehouse.chemstore.manage';
    }

    public function columns(): array
    {
        return ['ob_date', 'supplier_id', 'category_id', 'item_id', 'uom', 'loan_received', 'loan_issued', 'is_final'];
    }

    public function booleans(): array
    {
        return ['is_final'];
    }

    public function query(): Builder
    {
        return DyeChemLoanOpening::query()->orderByDesc('ob_date');
    }

    public function messages(): array
    {
        return ['ob_date.required' => __('dyehouse.chem.v.ob_date'), 'supplier_id.required' => __('dyehouse.chem.v.supplier'), 'item_id.required' => __('dyehouse.chem.v.item')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'ob_date' => [$required, 'date'],
            'supplier_id' => [$required, $this->own('mp_suppliers')],
            'category_id' => ['nullable', $this->own('item_categories')],
            'item_id' => [$required, $this->own('items')],
            'uom' => ['nullable', 'string', 'max:16', UomCodes::rule($this->company())],
            'loan_received' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'loan_issued' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'is_final' => ['boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if ($row?->getAttribute('is_final')) {
            throw ValidationException::withMessages(['is_final' => __('dyehouse.chem.v.loan_final')]);
        }
        $get = fn (string $k) => array_key_exists($k, $data) ? $data[$k] : $row?->getAttribute($k);
        $data['loan_received'] = (float) ($get('loan_received') ?? 0);
        $data['loan_issued'] = (float) ($get('loan_issued') ?? 0);
        if ($data['loan_received'] <= 0 && $data['loan_issued'] <= 0) {
            throw ValidationException::withMessages(['loan_received' => __('dyehouse.chem.v.loan_nothing')]);
        }
        if (DyeChemLoanOpening::query()->where('supplier_id', $get('supplier_id'))->where('item_id', $get('item_id'))->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists()) {
            throw ValidationException::withMessages(['item_id' => __('dyehouse.chem.v.loan_twice')]);
        }
        if (array_key_exists('uom', $data)) {
            $data['uom'] = UomCodes::normalize($data['uom']);
        }
        if (! $row) {
            $data['created_by'] = $this->actingUserId();
        }

        return $data;
    }

    public function beforeDelete(Model $row): void
    {
        if ($row->getAttribute('is_final')) {
            throw ValidationException::withMessages(['is_final' => __('dyehouse.chem.v.loan_final')]);
        }
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $n = $this->names();
        $this->items ??= DB::table('items')->where('company_id', $this->company())->get(['id', 'name', 'item_code'])->mapWithKeys(fn ($i) => [$i->id => "{$i->name} ({$i->item_code})"])->all();
        $out['supplier'] = $n['suppliers'][$row->getAttribute('supplier_id')] ?? null;
        $out['item'] = $this->items[$row->getAttribute('item_id')] ?? null;
        $out['loan_received'] = (float) $row->getAttribute('loan_received');
        $out['loan_issued'] = (float) $row->getAttribute('loan_issued');
        $out['net'] = round($out['loan_issued'] - $out['loan_received'], 4);

        return $out;
    }
}
