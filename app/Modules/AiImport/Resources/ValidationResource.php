<?php

declare(strict_types=1);

namespace App\Modules\AiImport\Resources;

use App\Modules\AiImport\Models\ImportValidation;
use App\Modules\AiImport\Support\ImportBook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Data Validation Engine (AnnotechERP 26/01): the checks extracted values
 * pass before they land — a master lookup, a range, a format, a cross-field
 * rule or a duplicate check — for one family or every family, and what a
 * failure does: block the post, warn and continue, or route to review.
 */
class ValidationResource extends AiImportResource
{
    public function key(): string
    {
        return 'ai-validations';
    }

    public function model(): string
    {
        return ImportValidation::class;
    }

    public function columns(): array
    {
        return ['name', 'kind', 'applies_to', 'on_failure', 'triggered', 'pass_rate', 'enabled'];
    }

    public function booleans(): array
    {
        return ['enabled'];
    }

    public function defaults(): array
    {
        return ['applies_to' => 'EVERY', 'on_failure' => 'WARN', 'triggered' => 0, 'pass_rate' => 0, 'enabled' => true];
    }

    public function rules(?Model $row): array
    {
        return [
            'name' => [$row ? 'sometimes' : 'required', 'string', 'max:200'],
            'kind' => ['nullable', Rule::in(ImportBook::KINDS)],
            'applies_to' => ['sometimes', Rule::in(ImportBook::SCOPES)],
            'on_failure' => ['sometimes', Rule::in(ImportBook::ON_FAILURE)],
            'triggered' => $this->count(),
            'pass_rate' => $this->percent(),
            'enabled' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['name.required' => __('aiimport.v.validation')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['name'])) {
            $data['name'] = trim($data['name']);
        }

        return $data;
    }
}
