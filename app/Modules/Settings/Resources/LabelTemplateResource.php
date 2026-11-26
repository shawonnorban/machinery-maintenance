<?php

declare(strict_types=1);

namespace App\Modules\Settings\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\Settings\Models\LabelTemplate;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Settings → Barcode & Label Settings (AnnotechERP 03/03): the label a code is
 * printed on — what it goes on, the symbology, its size, how many across, and
 * the data printed beside the code.
 */
class LabelTemplateResource extends ResourceDefinition
{
    public function __construct(private readonly TenantContext $context) {}

    public function key(): string
    {
        return 'label-templates';
    }

    public function model(): string
    {
        return LabelTemplate::class;
    }

    public function viewPermission(): string
    {
        return 'settings.company.manage';
    }

    public function columns(): array
    {
        return ['code', 'name', 'usage', 'symbology', 'width_mm', 'height_mm', 'columns', 'fields', 'printer_language', 'active'];
    }

    public function booleans(): array
    {
        return ['active'];
    }

    public function defaults(): array
    {
        return ['active' => true, 'columns' => 1, 'printer_language' => 'PDF'];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'code' => [$required, 'string', 'max:48',
                Rule::unique('label_templates', 'code')->where('company_id', $this->context->companyId())->ignore($row?->getKey())],
            'name' => [$required, 'string', 'max:255'],
            'usage' => [$required, Rule::in(LabelTemplate::USAGES)],
            'symbology' => [$required, Rule::in(LabelTemplate::SYMBOLOGIES)],
            'width_mm' => [$required, 'numeric', 'min:10', 'max:300'],
            'height_mm' => [$required, 'numeric', 'min:5', 'max:300'],
            'columns' => ['nullable', 'integer', 'min:1', 'max:6'],
            // A form sends "CODE,NAME"; JSON may send a list.
            'fields' => ['nullable', function (string $attribute, mixed $value, \Closure $fail): void {
                foreach ($this->fieldList($value) as $field) {
                    if (! in_array($field, LabelTemplate::FIELDS, true)) {
                        $fail(__('settings.hub.unknown_label_field', ['field' => $field]));
                    }
                }
            }],
            'printer_language' => ['nullable', Rule::in(LabelTemplate::PRINTER_LANGUAGES)],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (array_key_exists('fields', $data)) {
            $data['fields'] = $this->fieldList($data['fields']);
        }
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['fields_text'] = implode(',', $row->fields ?? []);

        return $out;
    }

    /** @return list<string> */
    private function fieldList(mixed $value): array
    {
        $items = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_unique(array_filter(array_map(fn ($v): string => strtoupper(trim((string) $v)), $items))));
    }
}
