<?php

declare(strict_types=1);

namespace App\Modules\ColourLab\Resources;

use App\Modules\Api\Support\ApiCaller;
use App\Modules\ColourLab\Models\LabCertRegister;
use App\Modules\ColourLab\Support\ColourLab;
use App\Modules\Merchandise\Models\Buyer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Certificate Issue Register: every copy of a certificate issued, handed
 * over, returned, renewed or surrendered — to whom, for which buyer, how
 * many copies, when it is due back and came back, and who handed it. The
 * Register No is allocated on save, in sequence for the month of the entry,
 * and a refused save burns nothing. Colour Lab (AnnotechERP 16/01).
 */
class RegisterResource extends ColourLabResource
{
    public function key(): string
    {
        return 'lab-cert-register';
    }

    public function model(): string
    {
        return LabCertRegister::class;
    }

    public function columns(): array
    {
        return ['register_no', 'certificate_id', 'entry_date', 'action', 'issued_to', 'buyer_id', 'copies', 'due_date', 'return_date', 'handed_by', 'remarks'];
    }

    public function defaults(): array
    {
        return ['action' => 'ISSUED', 'copies' => 1];
    }

    public function query(): Builder
    {
        return LabCertRegister::query()->orderByDesc('entry_date')->orderByDesc('register_no');
    }

    public function messages(): array
    {
        $m = fn (string $k) => __("colourlab.v.{$k}");

        return [
            'certificate_id.required' => $m('certificate'), 'entry_date.required' => $m('entry_date'), 'issued_to.required' => $m('issued_to'),
            'due_date.after_or_equal' => $m('due_before_entry'), 'return_date.after_or_equal' => $m('return_before_entry'), 'copies.min' => $m('copies'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = $this->company();

        return [
            'certificate_id' => [$required, Rule::exists('lab_certificates', 'id')->where('company_id', $company)],
            'entry_date' => [$required, 'date'],
            'action' => ['nullable', Rule::in(LabCertRegister::ACTIONS)],
            'issued_to' => [$required, 'string', 'max:255'],
            'buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'copies' => ['nullable', 'integer', 'min:1', 'max:999'],
            'due_date' => ['nullable', 'date', 'after_or_equal:entry_date'],
            'return_date' => ['nullable', 'date', 'after_or_equal:entry_date'],
            'handed_by' => ['nullable', Rule::in(array_keys(ColourLab::users()))],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        unset($data['register_no']);
        $data['action'] = $data['action'] ?? ($row?->getAttribute('action') ?? 'ISSUED');
        $data['copies'] = $data['copies'] ?? ($row?->getAttribute('copies') ?? 1);
        if (! $row) {
            $data['register_no'] = ColourLab::nextNumber('CIR', 'lab_cert_register', 'register_no', $data['entry_date']);
            $data['created_by'] = app(ApiCaller::class)->auditUserId();
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['certificate'] = ColourLab::certificates()[$row->getAttribute('certificate_id')] ?? null;
        $out['buyer'] = $row->getAttribute('buyer_id') ? Buyer::query()->whereKey($row->getAttribute('buyer_id'))->value('short_name') : null;
        $out['handed_by_name'] = ColourLab::userNames()[$row->getAttribute('handed_by')] ?? null;
        foreach (['entry_date', 'due_date', 'return_date'] as $d) {
            $out[$d] = $row->getAttribute($d)?->toDateString();
        }

        return $out;
    }
}
