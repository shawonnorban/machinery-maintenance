<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Resources;

use App\Modules\Accounting\Models\AccBankAccount;
use App\Modules\Accounting\Models\AccChequeBook;
use App\Modules\Accounting\Models\AccChequeLeaf;
use App\Modules\TradeExport\Models\TxBank;
use App\Modules\TradeExport\Models\TxBankBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Cheque Book Register: the cheque books held per bank account. The leaves
 * are generated from the prefix and the number range, so the register and the
 * leaves always agree; the leaf count must be the required leaf. A finalised
 * book's leaves are in circulation and the book can no longer be edited.
 * Finance & Ledger → Master Data (AnnotechERP 23/01).
 */
class ChequeBookResource extends AccountingResource
{
    public function key(): string
    {
        return 'acc-cheque-books';
    }

    public function model(): string
    {
        return AccChequeBook::class;
    }

    public function columns(): array
    {
        return ['bank_account_id', 'book_no', 'required_leaf', 'prefix', 'no_from', 'no_to', 'requisition_date', 'est_receive_date', 'receive_date', 'remarks', 'finalized'];
    }

    public function booleans(): array
    {
        return ['finalized'];
    }

    public function query(): Builder
    {
        return AccChequeBook::query()->orderByDesc('receive_date')->orderByDesc('created_at');
    }

    public function messages(): array
    {
        return ['no_from.required' => __('accounting.master.no_from_required'), 'bank_account_id.required' => __('accounting.master.account_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'no_from' => [$required, 'integer', 'min:0'],
            'bank_account_id' => [$required, Rule::exists('acc_bank_accounts', 'id')->where('company_id', $this->company())],
            'book_no' => [$required, 'string', 'max:32'],
            'required_leaf' => [$required, 'integer', 'min:1', 'max:1000'],
            'prefix' => [$required, 'string', 'max:12'],
            'no_to' => [$required, 'integer', 'min:0'],
            'requisition_date' => [$required, 'date'],
            'est_receive_date' => ['nullable', 'date'],
            'receive_date' => [$required, 'date'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'finalized' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if ($row?->finalized) {
            throw ValidationException::withMessages(['finalized' => __('accounting.master.book_finalized')]);
        }
        $p = array_merge($row ? $row->only(['bank_account_id', 'book_no', 'required_leaf', 'no_from', 'no_to', 'requisition_date', 'receive_date']) : [], $data);
        if ((int) $p['no_to'] < (int) $p['no_from']) {
            throw ValidationException::withMessages(['no_to' => __('accounting.master.range_order')]);
        }
        if ((int) $p['no_to'] - (int) $p['no_from'] + 1 !== (int) $p['required_leaf']) {
            throw ValidationException::withMessages(['required_leaf' => __('accounting.master.leaf_count', ['count' => (int) $p['no_to'] - (int) $p['no_from'] + 1])]);
        }
        if (substr((string) $p['receive_date'], 0, 10) < substr((string) $p['requisition_date'], 0, 10)) {
            throw ValidationException::withMessages(['receive_date' => __('accounting.master.receive_before_requisition')]);
        }
        $taken = AccChequeBook::query()->where('bank_account_id', $p['bank_account_id'])->where('book_no', $p['book_no'])->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['book_no' => __('accounting.master.book_taken')]);
        }
        $overlap = AccChequeBook::query()->where('bank_account_id', $p['bank_account_id'])->where('no_from', '<=', $p['no_to'])->where('no_to', '>=', $p['no_from'])
            ->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['no_from' => __('accounting.master.range_overlap')]);
        }

        return $data;
    }

    /** The leaves, regenerated from the range while the book is still open. */
    public function afterSave(Model $row, bool $created): void
    {
        if (! $created && AccChequeLeaf::query()->where('book_id', $row->getKey())->where('status', '!=', 'UNUSED')->exists()) {
            return;
        }
        DB::transaction(function () use ($row): void {
            AccChequeLeaf::query()->where('book_id', $row->getKey())->delete();
            $now = now();
            $width = strlen((string) $row->no_to);
            $rows = [];
            for ($n = $row->no_from; $n <= $row->no_to; $n++) {
                $rows[] = ['id' => (string) Str::ulid(), 'company_id' => $row->company_id, 'book_id' => $row->getKey(), 'number' => $n, 'cheque_no' => $row->prefix.str_pad((string) $n, $width, '0', STR_PAD_LEFT), 'status' => 'UNUSED', 'active' => true, 'created_at' => $now, 'updated_at' => $now];
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('acc_cheque_leaves')->insert($chunk);
            }
        });
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $account = AccBankAccount::query()->whereKey($row->getAttribute('bank_account_id'))->first(['account_no', 'account_type', 'bank_id', 'branch_id']);
        $out['account_no'] = $account?->account_no;
        $out['account_type'] = $account?->account_type;
        $out['bank_id'] = $account?->bank_id;
        $out['branch_id'] = $account?->branch_id;
        $out['bank'] = $account ? TxBank::query()->whereKey($account->bank_id)->value('name') : null;
        $out['branch'] = $account?->branch_id ? TxBankBranch::query()->whereKey($account->branch_id)->value('name') : null;
        $out['leaves'] = AccChequeLeaf::query()->where('book_id', $row->getKey())->count();
        $out['used'] = AccChequeLeaf::query()->where('book_id', $row->getKey())->where('status', '!=', 'UNUSED')->count();

        return $out;
    }
}
