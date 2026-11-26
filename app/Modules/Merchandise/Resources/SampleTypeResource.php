<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Merchandise\Models\BuyerSampleType;
use App\Modules\Merchandise\Models\SampleType;
use App\Modules\Merchandise\Support\SampleTypeDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Prototype Desk → Sample Type Configuration: what each sample type requires. Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class SampleTypeResource extends MasterResource
{
    public function key(): string
    {
        return 'sample-types';
    }

    public function model(): string
    {
        return SampleType::class;
    }

    public function columns(): array
    {
        return ['code', 'name', 'stage', 'order_required', 'po_required', 'buyer_approval_required', 'qc_required', 'material_required', 'revision_allowed', 'delivery_required', 'lead_days', 'customised', 'active'];
    }

    private const FLAGS = ['order_required', 'po_required', 'buyer_approval_required', 'qc_required', 'material_required', 'revision_allowed', 'delivery_required'];

    public function booleans(): array
    {
        return ['active', ...self::FLAGS];
    }

    public function query(): Builder
    {
        SampleTypeDefaults::ensure($this->company());

        return SampleType::query()->orderBy('stage')->orderBy('code');
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $flags = array_fill_keys(self::FLAGS, ['sometimes', 'boolean']);

        return [
            'code' => [$required, 'string', 'max:16', $this->uniqueCode($row)],
            'name' => [$required, 'string', 'max:255'],
            'stage' => [$required, Rule::in(SampleType::STAGES)],
            'lead_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'active' => ['sometimes', 'boolean'],
        ] + $flags;
    }

    /** An edited default is "Configured"; a new type is the company's own from the start. */
    public function beforeSave(array $data, ?Model $row): array
    {
        $data = parent::beforeSave($data, $row);
        $data['customised'] = true;

        return $data;
    }

    /** A type buyers still require stays; switch it off instead. */
    public function beforeDelete(Model $row): void
    {
        if (BuyerSampleType::query()->where('sample_type_id', $row->getKey())->exists()) {
            throw ValidationException::withMessages(['id' => __('merchandise.master.in_use')]);
        }
    }
}
