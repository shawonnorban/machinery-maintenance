<?php

declare(strict_types=1);

namespace App\Modules\AiImport\Resources;

use App\Modules\AiImport\Models\ExtractionRule;
use App\Modules\AiImport\Support\ImportBook;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Field Extraction Rules (AnnotechERP 26/01): a rule pulls one field out of
 * one family and lands it in one ERP column (`table.column`) — how it is
 * found, its type, its accuracy, and whether a file without it cannot post.
 * Within a family, an ERP column is filled by one rule only.
 */
class ExtractionRuleResource extends AiImportResource
{
    private const COLUMN = '/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/';

    public function key(): string
    {
        return 'ai-extraction-rules';
    }

    public function model(): string
    {
        return ExtractionRule::class;
    }

    public function columns(): array
    {
        return ['field', 'family', 'found_by', 'erp_column', 'data_type', 'accuracy', 'mandatory'];
    }

    public function booleans(): array
    {
        return ['mandatory'];
    }

    public function defaults(): array
    {
        return ['data_type' => 'TEXT', 'accuracy' => 0, 'mandatory' => false];
    }

    public function rules(?Model $row): array
    {
        $value = fn (string $f) => request()->has($f) ? request()->input($f) : $row?->getAttribute($f);
        // One rule per ERP column within a family.
        $taken = function (string $attribute, mixed $v, Closure $fail) use ($row, $value): void {
            $column = Str::lower(trim((string) $value('erp_column')));
            if ($column === '' || ! $value('family')) {
                return;
            }
            if (ExtractionRule::query()->where('family', $value('family'))->whereRaw('lower(erp_column) = ?', [$column])->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists()) {
                $fail(__('aiimport.v.column_taken'));
            }
        };

        return [
            'field' => [$row ? 'sometimes' : 'required', 'string', 'max:120'],
            'family' => ['nullable', Rule::in(ImportBook::DATA_FAMILIES)],
            'found_by' => ['nullable', Rule::in(ImportBook::FOUND_BY)],
            'erp_column' => ['nullable', 'string', 'max:80', function (string $attribute, mixed $v, Closure $fail): void {
                if (! preg_match(self::COLUMN, Str::lower(trim((string) $v)))) {
                    $fail(__('aiimport.v.erp_column'));
                }
            }, $taken],
            'data_type' => ['sometimes', Rule::in(ImportBook::DATA_TYPES)],
            'accuracy' => $this->percent(),
            'mandatory' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['field.required' => __('aiimport.v.field')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['field'])) {
            $data['field'] = trim($data['field']);
        }
        if (isset($data['erp_column'])) {
            $data['erp_column'] = Str::lower(trim($data['erp_column']));
        }

        return $data;
    }
}
