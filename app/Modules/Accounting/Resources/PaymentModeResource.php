<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Resources;

use App\Modules\Accounting\Models\AccPaymentMode;
use App\Modules\Accounting\Support\AccountingDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Payment Method Master: the modes a voucher may be settled by — cash,
 * cheque, transfer, adjustment — and the voucher types each is offered on.
 * Finance & Ledger → Master Data (AnnotechERP 23/01).
 */
class PaymentModeResource extends AccountingResource
{
    public function key(): string
    {
        return 'acc-payment-modes';
    }

    /** Brickland removes a row here; one still in use is refused (deactivate it instead). */
    public function deletable(): bool
    {
        return true;
    }

    public function model(): string
    {
        return AccPaymentMode::class;
    }

    public function columns(): array
    {
        return ['name', 'short_name', 'voucher_types', 'remarks', 'active'];
    }

    public function booleans(): array
    {
        return ['active'];
    }

    public function defaults(): array
    {
        return ['active' => true];
    }

    public function query(): Builder
    {
        AccountingDefaults::paymentModes($this->company());

        return AccPaymentMode::query()->orderBy('name');
    }

    public function messages(): array
    {
        return ['name.required' => __('accounting.master.name_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255', $this->uniqueText(AccPaymentMode::class, 'name', $row, __('accounting.master.mode_taken'))],
            'short_name' => ['nullable', 'string', 'max:16'],
            'voucher_types' => ['nullable'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (array_key_exists('voucher_types', $data) || ! $row) {
            $raw = $data['voucher_types'] ?? [];
            $types = array_values(array_unique(array_filter(array_map(fn ($t) => strtoupper(trim($t)), is_array($raw) ? $raw : explode(',', (string) $raw)))));
            if (array_diff($types, AccPaymentMode::VOUCHER_TYPES)) {
                throw ValidationException::withMessages(['voucher_types' => __('accounting.master.voucher_type_unknown')]);
            }
            $data['voucher_types'] = $types;
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['voucher_types'] = implode(',', $row->getAttribute('voucher_types') ?? []);

        return $out;
    }
}
