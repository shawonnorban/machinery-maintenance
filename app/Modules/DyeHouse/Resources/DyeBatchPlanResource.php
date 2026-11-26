<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Resources;

use App\Modules\DyeHouse\Models\DyeBatch;
use App\Modules\DyeHouse\Models\DyeBatchPlan;
use App\Modules\DyeHouse\Support\DyeBatches;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Dye Batch Plan's Plan List and New Plan: a board's code (issued when left
 * blank), name, date, company, bulk or sample, the hours one column covers
 * and how wide it draws. Raising a plan moves no fabric; batches are pulled
 * onto it on the board. A retired plan drops out of the board's list.
 */
class DyeBatchPlanResource extends DyeBatchResource
{
    public function key(): string
    {
        return 'dye-batch-plans';
    }

    public function model(): string
    {
        return DyeBatchPlan::class;
    }

    public function columns(): array
    {
        return ['code', 'name', 'plan_date', 'party_id', 'scope', 'scale_hours', 'cell_width', 'remarks', 'active'];
    }

    public function booleans(): array
    {
        return ['active'];
    }

    public function defaults(): array
    {
        return ['active' => true];
    }

    public function query(): Builder
    {
        return DyeBatchPlan::query()->orderByDesc('plan_date')->orderByDesc('code');
    }

    public function messages(): array
    {
        return [
            'code.unique' => __('dyehouse.batch.v.plan_code_taken'), 'code.regex' => __('dyehouse.batch.v.code_chars'), 'name.required' => __('dyehouse.batch.v.plan_name'), 'plan_date.required' => __('dyehouse.batch.v.plan_date'),
            'scope.required' => __('dyehouse.batch.v.scope'), 'scale_hours.required' => __('dyehouse.batch.v.scale'),
            'cell_width.required' => __('dyehouse.batch.v.cell_width'), 'cell_width.between' => __('dyehouse.batch.v.cell_width'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'code' => ['nullable', 'string', 'max:24', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('dye_batch_plans', 'code')->where('company_id', $this->company())->ignore($row?->getKey())],
            'name' => [$required, 'string', 'max:255'],
            'plan_date' => [$required, 'date'],
            'party_id' => ['nullable', Rule::in([$this->company()])],
            'scope' => [$required, Rule::in(DyeBatchPlan::SCOPES)],
            'scale_hours' => [$required, 'integer', Rule::in(DyeBatchPlan::SCALES)],
            'cell_width' => [$required, 'numeric', 'between:4,60'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (array_key_exists('code', $data)) {
            $data['code'] = trim((string) $data['code']) === '' ? null : mb_strtoupper(trim((string) $data['code']));
        }
        if (! $row) {
            $data['code'] ??= DyeBatches::nextNumber('PLN-', 'dye_batch_plans', 'code', $data['plan_date']);
            $data['created_by'] = $this->actingUserId();
        } elseif (array_key_exists('code', $data) && $data['code'] === null) {
            unset($data['code']);
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $batches = DyeBatch::query()->where('plan_id', $row->getKey())->get(['card_qty', 'planned_start']);
        $out['party'] = $row->getAttribute('party_id') ? ($this->names()['companies'][$row->getAttribute('party_id')] ?? null) : null;
        $out['batches'] = $batches->count();
        $out['qty'] = round((float) $batches->sum('card_qty'), 2);
        $out['laid_out'] = $batches->whereNotNull('planned_start')->count();
        $out['cell_width'] = (float) $row->getAttribute('cell_width');

        return $out;
    }

    public function beforeDelete(Model $row): void
    {
        if (DyeBatch::query()->where('plan_id', $row->getKey())->exists()) {
            throw ValidationException::withMessages(['code' => __('dyehouse.batch.v.plan_has_batches', ['code' => $row->getAttribute('code')])]);
        }
    }
}
