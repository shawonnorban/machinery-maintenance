<?php

declare(strict_types=1);

namespace App\Modules\Settings\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\Settings\Models\BankIntegration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Settings → Bank & Payment Integration (AnnotechERP 03/02): how the company
 * exchanges payments and statements with each bank — an API, an SFTP drop, or
 * files in a standard format. Credentials stay in the server environment.
 */
class BankIntegrationResource extends ResourceDefinition
{
    public function key(): string
    {
        return 'bank-integrations';
    }

    public function model(): string
    {
        return BankIntegration::class;
    }

    public function viewPermission(): string
    {
        return 'settings.company.manage';
    }

    public function columns(): array
    {
        return ['bank_name', 'account_name', 'account_no', 'routing_no', 'swift_code', 'channel', 'file_format', 'endpoint', 'credential_env', 'active', 'last_tested_at', 'last_test_status'];
    }

    public function booleans(): array
    {
        return ['active'];
    }

    public function defaults(): array
    {
        return ['active' => true, 'file_format' => 'NONE'];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'bank_name' => [$required, 'string', 'max:255'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'account_no' => [$required, 'string', 'max:48'],
            'routing_no' => ['nullable', 'string', 'max:32'],
            'swift_code' => ['nullable', 'string', 'regex:/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/i'],
            'channel' => [$required, Rule::in(BankIntegration::CHANNELS)],
            'file_format' => ['nullable', Rule::in(BankIntegration::FILE_FORMATS)],
            'endpoint' => ['nullable', 'required_if:channel,API,SFTP', 'string', 'max:255'],
            'credential_env' => ['nullable', 'string', 'max:64', 'regex:/^[A-Z][A-Z0-9_]*$/'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'credential_env.regex' => __('access.config.password_env_format'),
            'swift_code.regex' => __('settings.hub.bank.swift_format'),
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['swift_code'])) {
            $data['swift_code'] = strtoupper($data['swift_code']);
        }
        $data['file_format'] ??= $row?->file_format ?? 'NONE';

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['last_tested_at'] = $row->last_tested_at?->toIso8601String();

        return $out;
    }
}
