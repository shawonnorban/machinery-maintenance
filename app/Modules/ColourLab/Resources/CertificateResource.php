<?php

declare(strict_types=1);

namespace App\Modules\ColourLab\Resources;

use App\Modules\ColourLab\Models\LabCertificate;
use App\Modules\ColourLab\Models\LabCertRegister;
use App\Modules\ColourLab\Support\ColourLab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Test Certificate Directory (Brickland's "Certification List"): every
 * certificate the lab holds — its type, the equipment it covers, the filed
 * document, when it was applied for, issued and runs out (Valid Date), and
 * who is mailed Notify BF Days before it does. A certificate the register
 * has issued copies of stays; it can only be made inactive.
 */
class CertificateResource extends ColourLabResource
{
    public function key(): string
    {
        return 'lab-certificates';
    }

    public function model(): string
    {
        return LabCertificate::class;
    }

    public function columns(): array
    {
        return ['cert_type_id', 'equipment_id', 'document_ref', 'upload_doc', 'company_document_id', 'apply_date', 'applied_by', 'issue_date', 'valid_date', 'mail_to', 'mail_cc',
            'notify_days', 'lab_floor_id', 'active', 'remarks'];
    }

    public function booleans(): array
    {
        return ['active'];
    }

    public function defaults(): array
    {
        return ['active' => true, 'notify_days' => 30];
    }

    public function query(): Builder
    {
        return LabCertificate::query()->orderByDesc('apply_date')->orderBy('document_ref');
    }

    public function messages(): array
    {
        $m = fn (string $k) => __("colourlab.v.{$k}");

        return [
            'cert_type_id.required' => $m('cert_type'), 'equipment_id.required' => $m('equipment'), 'document_ref.required' => $m('document_ref'),
            'upload_doc.required' => $m('upload_doc'), 'apply_date.required' => $m('apply_date'), 'applied_by.required' => $m('applied_by'),
            'issue_date.required' => $m('issue_date'), 'valid_date.required' => $m('valid_date'), 'lab_floor_id.required' => $m('lab_floor'),
            'document_ref.unique' => $m('document_ref_taken'), 'issue_date.after_or_equal' => $m('issue_before_apply'), 'valid_date.after' => $m('valid_before_issue'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = $this->company();

        return [
            'cert_type_id' => [$required, Rule::exists('lab_cert_types', 'id')->where('company_id', $company)],
            'equipment_id' => [$required, Rule::exists('lab_equipment', 'id')->where('company_id', $company)],
            'document_ref' => [$required, 'string', 'max:64', Rule::unique('lab_certificates', 'document_ref')->where('company_id', $company)->ignore($row?->getKey())],
            'upload_doc' => [$required, 'string', 'max:255'],
            'company_document_id' => ['nullable', Rule::exists('company_documents', 'id')->where('company_id', $company)],
            'apply_date' => [$required, 'date'],
            'applied_by' => [$required, Rule::in(array_keys(ColourLab::users()))],
            'issue_date' => [$required, 'date', 'after_or_equal:apply_date'],
            'valid_date' => [$required, 'date', 'after:issue_date'],
            'mail_to' => ['nullable', 'string', 'max:1000'],
            'mail_cc' => ['nullable', 'string', 'max:1000'],
            'notify_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'lab_floor_id' => [$required, Rule::exists('floors', 'id')->where('company_id', $company)],
            'active' => ['sometimes', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** Mail (To) and Mail (Cc) are addresses, comma separated; a changed Valid Date earns a fresh reminder. */
    public function beforeSave(array $data, ?Model $row): array
    {
        foreach (['mail_to', 'mail_cc'] as $field) {
            if (array_key_exists($field, $data)) {
                [$all, $bad] = ColourLab::emails($data[$field]);
                if ($bad !== null) {
                    throw ValidationException::withMessages([$field => __('colourlab.v.email', ['value' => $bad])]);
                }
                $data[$field] = $all ? implode(', ', $all) : null;
            }
        }
        $data['notify_days'] = $data['notify_days'] ?? ($row?->getAttribute('notify_days') ?? 30);
        if ($row && array_key_exists('valid_date', $data) && (string) $data['valid_date'] !== $row->getAttribute('valid_date')?->toDateString()) {
            $data['notified_for'] = null;
        }

        return $data;
    }

    public function beforeDelete(Model $row): void
    {
        if (LabCertRegister::query()->where('certificate_id', $row->getKey())->exists()) {
            throw ValidationException::withMessages(['certificate' => __('colourlab.v.certificate_in_register')]);
        }
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['cert_type'] = ColourLab::certTypes()[$row->getAttribute('cert_type_id')] ?? null;
        $out['equipment'] = ColourLab::equipment()[$row->getAttribute('equipment_id')] ?? null;
        $out['applied_by_name'] = ColourLab::userNames()[$row->getAttribute('applied_by')] ?? null;
        $out['lab_floor'] = ColourLab::floors()[$row->getAttribute('lab_floor_id')] ?? null;
        foreach (['apply_date', 'issue_date', 'valid_date'] as $d) {
            $out[$d] = $row->getAttribute($d)?->toDateString();
        }

        return $out;
    }
}
