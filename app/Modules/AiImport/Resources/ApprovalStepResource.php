<?php

declare(strict_types=1);

namespace App\Modules\AiImport\Resources;

use App\Modules\AiImport\Models\ApprovalStep;
use App\Modules\AiImport\Support\ImportBook;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Import Approval Workflow (AnnotechERP 26/01): what a person must sign off
 * before an import writes to the ERP — the step, the family it covers (or
 * every family), the approver, its place in the order, the confidence above
 * which it is skipped, the service level and what is waiting on it. Within a
 * family each place in the order is taken once; left blank, a new step goes
 * last.
 */
class ApprovalStepResource extends AiImportResource
{
    public function key(): string
    {
        return 'ai-approval-steps';
    }

    public function model(): string
    {
        return ApprovalStep::class;
    }

    public function columns(): array
    {
        return ['name', 'applies_to', 'approver', 'sequence', 'auto_skip', 'sla_hours', 'pending', 'enabled'];
    }

    public function booleans(): array
    {
        return ['enabled'];
    }

    public function defaults(): array
    {
        return ['applies_to' => 'EVERY', 'pending' => 0, 'enabled' => true];
    }

    public function rules(?Model $row): array
    {
        $value = fn (string $f) => request()->has($f) ? request()->input($f) : $row?->getAttribute($f);
        // A place in a family's order is taken once — checked from either side.
        $taken = function (string $attribute, mixed $v, Closure $fail) use ($row, $value): void {
            $sequence = $value('sequence');
            if ($sequence === null || $sequence === '') {
                return;
            }
            $scope = $value('applies_to') ?: 'EVERY';
            if (ApprovalStep::query()->where('applies_to', $scope)->where('sequence', (int) $sequence)->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists()) {
                $fail(__('aiimport.v.sequence_taken', ['n' => (int) $sequence]));
            }
        };

        return [
            'name' => [$row ? 'sometimes' : 'required', 'string', 'max:200'],
            'applies_to' => ['sometimes', Rule::in(ImportBook::SCOPES), function (string $attribute, mixed $v, Closure $fail) use ($taken): void {
                if (! request()->has('sequence')) {
                    $taken($attribute, $v, $fail);
                }
            }],
            'approver' => ['nullable', Rule::in(ImportBook::APPROVERS)],
            'sequence' => ['nullable', 'integer', 'min:1', 'max:99', $taken],
            'auto_skip' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sla_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'pending' => $this->count(),
            'enabled' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['name.required' => __('aiimport.v.step')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['name'])) {
            $data['name'] = trim($data['name']);
        }
        // Left blank, a step goes last in its family; an edit keeps its place.
        if (array_key_exists('sequence', $data) && $data['sequence'] === null) {
            unset($data['sequence']);
        }
        if (! $row && ! isset($data['sequence'])) {
            $data['sequence'] = (int) ApprovalStep::query()->where('applies_to', $data['applies_to'] ?? 'EVERY')->max('sequence') + 1;
        }

        return $data;
    }
}
