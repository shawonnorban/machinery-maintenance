<?php

declare(strict_types=1);

namespace App\Modules\Approval\Resources;

use App\Modules\Approval\Models\ApprovalRequestType;
use App\Modules\Approval\Models\ApprovalTicket;
use App\Modules\Approval\Support\RequestTypeDefaults;
use App\Modules\Erp\Support\ResourceDefinition;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Approval Center → Request Types & Routing (AnnotechERP 04/03): what can be
 * asked for, its default priority and deadline, the chain after Request To,
 * and whom an overdue request goes to.
 */
class ApprovalRequestTypeResource extends ResourceDefinition
{
    public function key(): string
    {
        return 'approval-request-types';
    }

    public function model(): string
    {
        return ApprovalRequestType::class;
    }

    public function viewPermission(): string
    {
        return 'approval.ticket.create';
    }

    public function managePermission(): string
    {
        return 'approval.type.manage';
    }

    public function columns(): array
    {
        return ['code', 'name', 'module', 'default_priority', 'due_days', 'sort_order', 'workflow_id', 'escalate_to_user_id', 'escalate_after_hours', 'description', 'active'];
    }

    public function booleans(): array
    {
        return ['active'];
    }

    public function defaults(): array
    {
        return ['default_priority' => 'MEDIUM', 'due_days' => 0, 'sort_order' => 0, 'escalate_after_hours' => 24, 'active' => true];
    }

    public function query(): Builder
    {
        RequestTypeDefaults::ensure(app(TenantContext::class)->companyId());

        return ApprovalRequestType::query()->with(['workflow', 'escalateTo'])->orderBy('sort_order')->orderBy('name');
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = app(TenantContext::class)->companyId();

        return [
            // Stored upper-case, so compared upper-case: "fabric-booking" is FABRIC-BOOKING.
            'code' => [$required, 'string', 'max:48', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/', function (string $attribute, mixed $value, \Closure $fail) use ($company, $row): void {
                $taken = ApprovalRequestType::query()->where('company_id', $company)->where('code', strtoupper((string) $value))
                    ->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
                if ($taken) {
                    $fail(__('validation.unique', ['attribute' => $attribute]));
                }
            }],
            'name' => [$required, 'string', 'max:255'],
            'module' => [$required, 'string', 'max:255'],
            'default_priority' => ['sometimes', Rule::in(ApprovalRequestType::PRIORITIES)],
            'due_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'workflow_id' => ['nullable', Rule::exists('approval_workflows', 'id')->where('company_id', $company)],
            'escalate_to_user_id' => ['nullable', Rule::exists('company_users', 'user_id')->where('company_id', $company)->where('status', 'ACTIVE')],
            'escalate_after_hours' => ['sometimes', 'integer', 'min:1', 'max:720'],
            'description' => ['nullable', 'string', 'max:2000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        return $data;
    }

    public function present(Model $row): array
    {
        /** @var ApprovalRequestType $row */
        $row->loadMissing(['workflow', 'escalateTo']);
        $out = parent::present($row);
        $out['workflow'] = $row->workflow ? trim(($row->workflow->type_code ? $row->workflow->type_code.' — ' : '').$row->workflow->name) : null;
        $out['escalate_to'] = $row->escalateTo?->name;
        $out['requests'] = ApprovalTicket::query()->where('request_type_id', $row->id)->count();

        return $out;
    }

    /** A type with requests behind it is kept for their sake; it can be switched off instead. */
    public function beforeDelete(Model $row): void
    {
        if (ApprovalTicket::query()->where('request_type_id', $row->getKey())->exists()) {
            throw ValidationException::withMessages(['id' => __('approval.center.type_in_use')]);
        }
    }
}
