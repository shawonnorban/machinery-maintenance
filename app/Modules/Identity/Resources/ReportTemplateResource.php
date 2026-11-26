<?php

declare(strict_types=1);

namespace App\Modules\Identity\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\Identity\Models\ReportTemplate;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Access Control → Report Template Setup (AnnotechERP 02/03). The buyer-wise
 * template mapping arrives with the buyer master.
 */
class ReportTemplateResource extends ResourceDefinition
{
    public function __construct(private readonly TenantContext $context) {}

    public function key(): string
    {
        return 'report-templates';
    }

    public function model(): string
    {
        return ReportTemplate::class;
    }

    public function viewPermission(): string
    {
        return 'settings.company.manage';
    }

    public function columns(): array
    {
        return ['code', 'name', 'report_path', 'output_format', 'orientation', 'paper_size', 'description', 'active'];
    }

    public function booleans(): array
    {
        return ['active'];
    }

    public function defaults(): array
    {
        return ['active' => true];
    }

    public function rules(?Model $row): array
    {
        return [
            'code' => [$row ? 'sometimes' : 'required', 'string', 'max:48',
                Rule::unique('report_templates', 'code')->where('company_id', $this->context->companyId())->ignore($row?->getKey())],
            'name' => [$row ? 'sometimes' : 'required', 'string', 'max:255'],
            'report_path' => ['nullable', 'string', 'max:255'],
            'output_format' => [$row ? 'sometimes' : 'required', Rule::in(ReportTemplate::FORMATS)],
            'orientation' => ['nullable', Rule::in(ReportTemplate::ORIENTATIONS)],
            'paper_size' => ['nullable', Rule::in(ReportTemplate::PAPER_SIZES)],
            'description' => ['nullable', 'string', 'max:2000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['code.required' => __('access.config.template_code_required')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        return $data;
    }
}
