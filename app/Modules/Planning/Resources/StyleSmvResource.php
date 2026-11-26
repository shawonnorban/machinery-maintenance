<?php

declare(strict_types=1);

namespace App\Modules\Planning\Resources;

use App\Modules\Planning\Models\PlanningStyleSmv;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * SMV / SAM Master: standard minutes per style for planning — sewing SMV and
 * whole-garment SAM. Planning uses the IE's sewing SMV once Work Study has
 * one, this row's SMV until then, and its SAM when there is no SMV.
 * Capacity Planning → Master Data (AnnotechERP 12/01).
 */
class StyleSmvResource extends PlanningMasterResource
{
    public function key(): string
    {
        return 'planning-style-smvs';
    }

    public function model(): string
    {
        return PlanningStyleSmv::class;
    }

    public function columns(): array
    {
        return ['style_no', 'smv', 'sam', 'remarks', 'active'];
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
        return PlanningStyleSmv::query()->orderBy('style_no');
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'style_no' => [$required, 'string', 'max:64', function (string $attribute, mixed $value, Closure $fail) use ($row): void {
                $taken = PlanningStyleSmv::query()->where('style_no', strtoupper(trim((string) $value)))->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
                if ($taken) {
                    $fail(__('planning.master.style_taken'));
                }
            }],
            'smv' => ['nullable', 'numeric', 'gt:0', 'max:999'],
            'sam' => ['nullable', 'numeric', 'gt:0', 'max:999'],
            'remarks' => ['nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['style_no'])) {
            $data['style_no'] = strtoupper(trim($data['style_no']));
        }
        $m = array_merge($row?->only(['smv', 'sam']) ?? [], $data);
        if (empty($m['smv']) && empty($m['sam'])) {
            throw ValidationException::withMessages(['smv' => __('planning.master.smv_or_sam')]);
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        // Work Study (14) brings the IE's sewing SMV; until then planning reads this row.
        $out['ie_smv'] = null;
        $out['planning_smv'] = $row->getAttribute('smv') ?? $row->getAttribute('sam');
        $out['planning_source'] = $row->getAttribute('smv') !== null ? 'SMV' : 'SAM';

        return $out;
    }
}
