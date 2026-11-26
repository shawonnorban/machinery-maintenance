<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Merchandise\Models\Colour;
use App\Modules\Merchandise\Models\GarmentPart;
use App\Modules\Merchandise\Models\ProcessLossAllowance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Process Loss Quantity Setup and Cutting Process Loss Setup (AnnotechERP
 * 06/01): allowable process loss quantities per quantity band, by stage.
 */
abstract class ProcessLossAllowanceResource extends MasterResource
{
    public function model(): string
    {
        return ProcessLossAllowance::class;
    }

    public function columns(): array
    {
        return ['stage', 'qty_low', 'qty_high', 'loss_qty', 'add_to_booking', 'fabric_group', 'colour_type', 'effective_from', 'effective_to'];
    }

    abstract protected function stage(): string;

    public function booleans(): array
    {
        return ['add_to_booking'];
    }

    public function defaults(): array
    {
        return ['add_to_booking' => true];
    }

    public function query(): Builder
    {
        return ProcessLossAllowance::query()->where('stage', $this->stage())->orderBy('qty_low');
    }

    protected function upperCase(): array
    {
        return [];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'qty_low' => [$required, 'numeric', 'min:0'],
            'qty_high' => [$required, 'numeric', 'gt:qty_low'],
            'loss_qty' => ['nullable', 'numeric', 'min:0'],
            'add_to_booking' => ['sometimes', 'boolean'],
            'fabric_group' => ['nullable', Rule::in(GarmentPart::FABRIC_GROUPS)],
            'colour_type' => ['nullable', Rule::in(Colour::TYPES)],
            'effective_from' => [$required, 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }

    /**
     * The stage is the screen's, never the request's; and two allowances for
     * the same fabric group and colour type may not cover the same quantity
     * on the same day.
     */
    public function beforeSave(array $data, ?Model $row): array
    {
        $data['stage'] = $this->stage();
        $a = array_merge($row?->only(['qty_low', 'qty_high', 'fabric_group', 'colour_type', 'effective_from', 'effective_to']) ?? [], $data);
        $from = substr((string) $a['effective_from'], 0, 10);
        $to = empty($a['effective_to']) ? null : substr((string) $a['effective_to'], 0, 10);
        $clash = ProcessLossAllowance::query()
            ->where('stage', $this->stage())
            ->where(fn ($q) => empty($a['fabric_group']) ? $q->whereNull('fabric_group') : $q->where('fabric_group', $a['fabric_group']))
            ->where(fn ($q) => empty($a['colour_type']) ? $q->whereNull('colour_type') : $q->where('colour_type', $a['colour_type']))
            ->where('qty_low', '<', $a['qty_high'])
            ->where('qty_high', '>', $a['qty_low'])
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $from))
            ->when($to, fn ($q) => $q->where('effective_from', '<=', $to))
            ->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))
            ->exists();
        if ($clash) {
            throw ValidationException::withMessages(['qty_low' => __('merchandise.master.band_overlap')]);
        }

        return $data;
    }
}
