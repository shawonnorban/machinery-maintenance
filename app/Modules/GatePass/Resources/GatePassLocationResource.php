<?php

declare(strict_types=1);

namespace App\Modules\GatePass\Resources;

use App\Modules\GatePass\Models\GatePassLocation;
use App\Modules\GatePass\Models\GatePassLocationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Factory / Location: the places outside the gate goods go to, their type,
 * and who to reach there. Gate Pass → Master Data (AnnotechERP 11/01).
 */
class GatePassLocationResource extends GatePassMasterResource
{
    public function key(): string
    {
        return 'gatepass-locations';
    }

    public function model(): string
    {
        return GatePassLocation::class;
    }

    public function columns(): array
    {
        return ['code', 'name', 'location_type_id', 'contact_person', 'mobile', 'email', 'address', 'remarks', 'active'];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $rules = $this->basicRules($row);
        unset($rules['description']);

        return $rules + [
            'location_type_id' => [$required, Rule::exists('gate_pass_location_types', 'id')->where('company_id', $this->company())],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['location_type'] = GatePassLocationType::query()->whereKey($row->getAttribute('location_type_id'))->value('name');

        return $out;
    }
}
