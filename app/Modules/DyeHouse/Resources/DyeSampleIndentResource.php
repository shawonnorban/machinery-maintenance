<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Resources;

use App\Modules\DyeHouse\Models\DyeBatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Sample Grey Fabric Indent: grey fabric called off the store against a
 * sample batch card, as the bulk indent is — its lines copied from the card,
 * one requisition per card — and raised for a company. The Schedule is the
 * sample loading plan the batch runs under; choosing one files the card on it.
 */
class DyeSampleIndentResource extends DyeGreyIndentResource
{
    /** The Schedule the save names, held for afterSave. */
    private ?string $schedule = null;

    public function key(): string
    {
        return 'dye-sample-indents';
    }

    protected function type(): string
    {
        return 'SAMPLE';
    }

    protected function scope(): string
    {
        return 'SAMPLE';
    }

    public function columns(): array
    {
        return [...parent::columns(), 'party_id'];
    }

    public function rules(?Model $row): array
    {
        return parent::rules($row) + [
            'plan_id' => ['nullable', Rule::exists('dye_batch_plans', 'id')->where('company_id', $this->company())->where('scope', 'SAMPLE')->where('active', true)],
            'party_id' => ['nullable', Rule::in([$this->company()])],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $this->schedule = $data['plan_id'] ?? null;
        unset($data['plan_id']);

        return parent::beforeSave($data, $row);
    }

    public function afterSave(Model $row, bool $created): void
    {
        parent::afterSave($row, $created);
        if ($this->schedule) {
            DyeBatch::query()->whereKey($row->getAttribute('batch_id'))->update(['plan_id' => $this->schedule]);
            $this->schedule = null;
        }
    }
}
