<?php

declare(strict_types=1);

namespace App\Modules\Settings\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\Settings\Models\SmsGateway;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Settings → SMS Gateway (AnnotechERP 03/02). One gateway is the default; the
 * API key's environment variable is named, never the key itself.
 */
class SmsGatewayResource extends ResourceDefinition
{
    public function key(): string
    {
        return 'sms-gateways';
    }

    public function model(): string
    {
        return SmsGateway::class;
    }

    public function viewPermission(): string
    {
        return 'settings.company.manage';
    }

    public function columns(): array
    {
        return ['name', 'provider', 'base_url', 'username', 'sender_id', 'api_key_env', 'is_default', 'active', 'last_used_at'];
    }

    public function booleans(): array
    {
        return ['is_default', 'active'];
    }

    public function defaults(): array
    {
        return ['active' => true, 'is_default' => false];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255'],
            'provider' => [$required, Rule::in(SmsGateway::PROVIDERS)],
            // Required for a generic gateway, which has no address of its own.
            'base_url' => ['nullable', 'required_if:provider,GENERIC_HTTP', 'url', 'max:255'],
            'username' => ['nullable', 'required_if:provider,TWILIO', 'string', 'max:255'],
            'sender_id' => ['nullable', 'string', 'max:32'],
            'api_key_env' => [$required, 'string', 'max:64', 'regex:/^[A-Z][A-Z0-9_]*$/'],
            'is_default' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['api_key_env.regex' => __('access.config.password_env_format')];
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['last_used_at'] = $row->last_used_at?->toIso8601String();

        return $out;
    }

    public function afterSave(Model $row, bool $created): void
    {
        if ($row->is_default) {
            SmsGateway::query()->whereKeyNot($row->getKey())->update(['is_default' => false]);
        }
    }
}
