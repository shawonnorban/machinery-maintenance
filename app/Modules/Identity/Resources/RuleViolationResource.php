<?php

declare(strict_types=1);

namespace App\Modules\Identity\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\Identity\Models\LookupTable;
use App\Modules\Identity\Models\RuleViolation;
use App\Modules\Identity\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Access Control → Rule Violation Log (AnnotechERP 02/04). Each module's own
 * checks will record here when somebody with the override right (User Rule
 * Mapping) passes a rule; a violation can also be recorded by hand.
 */
class RuleViolationResource extends ResourceDefinition
{
    public function __construct(private readonly TenantContext $context) {}

    public function key(): string
    {
        return 'rule-violations';
    }

    public function model(): string
    {
        return RuleViolation::class;
    }

    public function viewPermission(): string
    {
        return 'audit.log.view';
    }

    public function managePermission(): string
    {
        return 'admin.user.manage';
    }

    public function columns(): array
    {
        return ['rule_code', 'document_reference', 'severity', 'status', 'detail', 'raised_by'];
    }

    public function defaults(): array
    {
        return ['status' => 'OPEN'];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'rule_code' => [$required, 'string', Rule::in($this->ruleCodes())],
            'document_reference' => [$required, 'string', 'max:128'],
            'severity' => [$required, Rule::in(RuleViolation::SEVERITIES)],
            'status' => ['sometimes', Rule::in(RuleViolation::STATUSES)],
            'detail' => [$required, 'string', 'max:4000'],
        ];
    }

    public function messages(): array
    {
        return ['rule_code.required' => __('access.config.rule_required')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if ($row === null) {
            $data['raised_by'] = $this->actingUserId();
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['raised_by_name'] = $row->raised_by ? User::query()->whereKey($row->raised_by)->value('name') : null;

        return $out;
    }

    /** @return list<string> The BUSINESS_RULE values this company can see. */
    private function ruleCodes(): array
    {
        $table = LookupTable::query()->visible()->where('code', 'BUSINESS_RULE')->orderByRaw('company_id is null')->first();

        return $table ? $table->values()->visible()->pluck('code')->all() : [];
    }
}
