<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Resources;

use App\Modules\Accounting\Models\AccAccount;
use App\Modules\Accounting\Models\AccBankAccount;
use App\Modules\Accounting\Support\AccountingDefaults;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\TradeExport\Models\TxBank;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Chart of Accounts Master: the ledger hierarchy every voucher line and
 * statement is built on. A child's code extends its parent's (two digits a
 * level), so the number says where an account sits; a node with children
 * cannot take postings, a postable node cannot be given children, and a child
 * keeps its parent's type. A head may name the register its vouchers refer to
 * (buyer, supplier, employee, bank, bank account). Finance & Ledger → Master
 * Data (AnnotechERP 23/01).
 */
class AccountResource extends AccountingResource
{
    public function key(): string
    {
        return 'acc-accounts';
    }

    /** Brickland removes a row here; one still in use is refused (deactivate it instead). */
    public function deletable(): bool
    {
        return true;
    }

    public function model(): string
    {
        return AccAccount::class;
    }

    public function columns(): array
    {
        return ['code', 'name', 'parent_id', 'type', 'postable', 'voucher_ref_type', 'voucher_ref_id', 'display_order', 'active'];
    }

    public function booleans(): array
    {
        return ['postable', 'active'];
    }

    public function defaults(): array
    {
        return ['postable' => true, 'active' => true];
    }

    public function query(): Builder
    {
        AccountingDefaults::accounts($this->company());

        return AccAccount::query()->orderBy('code');
    }

    public function messages(): array
    {
        return ['name.required' => __('accounting.master.name_required'), 'type.required' => __('accounting.master.type_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = $this->company();

        return [
            'parent_id' => ['nullable', Rule::exists('acc_accounts', 'id')->where('company_id', $company)],
            'code' => ['nullable', 'string', 'max:24', 'regex:/^[0-9]+$/'],
            'name' => [$required, 'string', 'max:255'],
            'type' => [$required, Rule::in(AccAccount::TYPES)],
            'postable' => ['sometimes', 'boolean'],
            'voucher_ref_type' => ['nullable', Rule::in(AccAccount::REF_TYPES)],
            'voucher_ref_id' => ['nullable', 'string', 'max:26'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $p = array_merge($row?->only(['parent_id', 'type', 'postable', 'code', 'voucher_ref_type', 'voucher_ref_id']) ?? ['postable' => true], $data);
        $parent = empty($p['parent_id']) ? null : AccAccount::query()->find($p['parent_id']);
        if ($row && $parent && $parent->getKey() === $row->getKey()) {
            throw ValidationException::withMessages(['parent_id' => __('accounting.master.parent_self')]);
        }
        if ($parent?->postable) {
            throw ValidationException::withMessages(['parent_id' => __('accounting.master.parent_postable')]);
        }
        if ($parent && $p['type'] !== $parent->type) {
            throw ValidationException::withMessages(['type' => __('accounting.master.type_parent')]);
        }
        if ($row && ($p['postable'] ?? false) && AccAccount::query()->where('parent_id', $row->getKey())->exists()) {
            throw ValidationException::withMessages(['postable' => __('accounting.master.postable_children')]);
        }
        $moved = $row && array_key_exists('parent_id', $data) && ($data['parent_id'] ?: null) !== $row->parent_id;
        if ($moved && AccAccount::query()->where('parent_id', $row->getKey())->exists()) {
            throw ValidationException::withMessages(['parent_id' => __('accounting.master.move_with_children')]);
        }
        if (! $row || $moved || array_key_exists('code', $data)) {
            $code = trim((string) ($data['code'] ?? ''));
            if ($code === '' || ($moved && ! array_key_exists('code', $data))) {
                $code = self::nextCode($parent);
            } elseif ($parent && ! (str_starts_with($code, $parent->code) && strlen($code) === strlen($parent->code) + 2)) {
                throw ValidationException::withMessages(['code' => __('accounting.master.code_extends', ['code' => $parent->code])]);
            }
            if (AccAccount::query()->where('code', $code)->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists()) {
                throw ValidationException::withMessages(['code' => __('accounting.master.code_taken')]);
            }
            $data['code'] = $code;
        }
        $data['parent_id'] = $parent?->getKey();
        $this->checkReference($p['voucher_ref_type'] ?? null, $p['voucher_ref_id'] ?? null);

        return $data;
    }

    /** The next code under a parent: its code and the next two digits; a top-level class takes the next single digit. */
    public static function nextCode(?AccAccount $parent): string
    {
        if (! $parent) {
            $top = AccAccount::query()->whereNull('parent_id')->whereRaw('length(code) = 1')->pluck('code')->map(fn ($c) => (int) $c)->max() ?? 0;

            return (string) ($top + 1);
        }
        $last = AccAccount::query()->where('parent_id', $parent->getKey())->whereRaw('length(code) = ?', [strlen($parent->code) + 2])->pluck('code')
            ->map(fn ($c) => (int) substr($c, -2))->max() ?? 0;
        if ($last >= 99) {
            throw ValidationException::withMessages(['parent_id' => __('accounting.master.parent_full')]);
        }

        return $parent->code.str_pad((string) ($last + 1), 2, '0', STR_PAD_LEFT);
    }

    private function checkReference(?string $type, ?string $id): void
    {
        if (! $type && ! $id) {
            return;
        }
        if (! $type) {
            throw ValidationException::withMessages(['voucher_ref_type' => __('accounting.master.ref_type_first')]);
        }
        if (! $id) {
            return;
        }
        $exists = match ($type) {
            'BUYER' => Buyer::query()->whereKey($id)->exists(),
            'SUPPLIER' => MaterialSupplier::query()->whereKey($id)->exists(),
            'EMPLOYEE' => CompanyUser::query()->where('company_id', $this->company())->where('user_id', $id)->exists(),
            'BANK' => TxBank::query()->whereKey($id)->exists(),
            'BANK_ACCOUNT' => AccBankAccount::query()->whereKey($id)->exists(),
        };
        if (! $exists) {
            throw ValidationException::withMessages(['voucher_ref_id' => __('validation.exists', ['attribute' => 'voucher_ref_id'])]);
        }
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $parent = $row->getAttribute('parent_id') ? AccAccount::query()->whereKey($row->getAttribute('parent_id'))->first(['code', 'name']) : null;
        $out['parent'] = $parent ? "{$parent->code} — {$parent->name}" : null;
        $out['children'] = AccAccount::query()->where('parent_id', $row->getKey())->count();

        return $out;
    }
}
