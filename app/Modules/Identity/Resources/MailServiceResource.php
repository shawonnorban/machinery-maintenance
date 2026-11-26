<?php

declare(strict_types=1);

namespace App\Modules\Identity\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\Identity\Models\MailService;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Access Control → Mail Service Configuration (AnnotechERP 02/03). As in
 * Brickland, the relay's password is never stored: a row names the
 * environment variable that holds it. One relay is the default.
 */
class MailServiceResource extends ResourceDefinition
{
    public function __construct(private readonly TenantContext $context) {}

    public function key(): string
    {
        return 'mail-services';
    }

    public function model(): string
    {
        return MailService::class;
    }

    public function viewPermission(): string
    {
        return 'settings.company.manage';
    }

    public function columns(): array
    {
        return ['service_name', 'module_code', 'title', 'to_mail', 'cc_mail', 'bcc_mail', 'smtp_host', 'port', 'smtp_username', 'password_env',
            'encryption', 'from_name', 'from_address', 'reply_to', 'remarks', 'is_default', 'active'];
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
        $emails = ['nullable', 'string', 'max:2000', 'regex:/^\s*[^@\s,]+@[^@\s,]+(\s*,\s*[^@\s,]+@[^@\s,]+)*\s*$/'];

        return [
            'service_name' => [$required, 'string', 'max:128',
                Rule::unique('mail_services', 'service_name')->where('company_id', $this->context->companyId())->ignore($row?->getKey())],
            'module_code' => ['nullable', 'string', 'max:48'],
            'title' => ['nullable', 'string', 'max:255'],
            'to_mail' => $emails,
            'cc_mail' => $emails,
            'bcc_mail' => $emails,
            'smtp_host' => [$required, 'string', 'max:255'],
            'port' => [$required, 'integer', 'min:1', 'max:65535'],
            'smtp_username' => [$required, 'string', 'max:255'],
            'password_env' => [$required, 'string', 'max:64', 'regex:/^[A-Z][A-Z0-9_]*$/'],
            'encryption' => [$required, Rule::in(MailService::ENCRYPTIONS)],
            'from_name' => [$required, 'string', 'max:255'],
            'from_address' => [$required, 'email', 'max:255'],
            'reply_to' => ['nullable', 'email', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'is_default' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'password_env.required' => __('access.config.password_env_required'),
            'password_env.regex' => __('access.config.password_env_format'),
            'to_mail.regex' => __('access.config.email_list'),
            'cc_mail.regex' => __('access.config.email_list'),
            'bcc_mail.regex' => __('access.config.email_list'),
        ];
    }

    /** One default relay: marking this one clears the others. */
    public function afterSave(Model $row, bool $created): void
    {
        if ($row->is_default) {
            MailService::query()->whereKeyNot($row->getKey())->update(['is_default' => false]);
        }
    }
}
