<?php

declare(strict_types=1);

namespace App\Modules\FabricPlanning\Resources;

use App\Modules\DyeHouse\Models\DyeColourGroup;
use App\Modules\DyeHouse\Models\DyeMachine;
use App\Modules\FabricPlanning\Models\FpMachineRoute;
use App\Modules\Merchandise\Models\Buyer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Dye Machine Route Master: the Dye House machine a fabric is routed to, by
 * fabric group, fiber combination template, buyer and finished GSM band.
 * A GSM band has both ends or is ticked Any. Fabric Requirement → Master
 * Data (AnnotechERP 13/01).
 */
class MachineRouteResource extends FabricPlanningResource
{
    public function key(): string
    {
        return 'fp-machine-routes';
    }

    public function model(): string
    {
        return FpMachineRoute::class;
    }

    public function columns(): array
    {
        return ['colour_group_id', 'fabric_group', 'fiber_template', 'buyer_id', 'dye_machine_id', 'gsm_from', 'gsm_to', 'active'];
    }

    public function booleans(): array
    {
        return ['active', 'any_fabric_group', 'any_template', 'any_buyer', 'any_gsm'];
    }

    public function defaults(): array
    {
        return ['active' => true];
    }

    protected function anyBoxes(): array
    {
        return ['fabric_group' => 'any_fabric_group', 'fiber_template' => 'any_template', 'buyer_id' => 'any_buyer'];
    }

    public function query(): Builder
    {
        return FpMachineRoute::query()->orderByDesc('active')->orderBy('fabric_group');
    }

    public function messages(): array
    {
        return ['dye_machine_id.required' => __('fabricplanning.master.machine_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = $this->company();

        return [
            'colour_group_id' => [$required, Rule::exists('dye_colour_groups', 'id')->where('company_id', $this->company())],
            'fabric_group' => ['nullable', Rule::in(FpMachineRoute::FABRIC_GROUPS)],
            'any_fabric_group' => ['sometimes', 'boolean'],
            'fiber_template' => ['nullable', Rule::in(['COTTON_100', 'LYCRA_BLEND', 'CVC_BLEND'])],
            'any_template' => ['sometimes', 'boolean'],
            'buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'any_buyer' => ['sometimes', 'boolean'],
            'dye_machine_id' => [$required, Rule::exists('dye_machines', 'id')->where('company_id', $company)],
            'gsm_from' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'gsm_to' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'any_gsm' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $data = $this->applyAny($data, $row);
        if (! empty($data['any_gsm'])) {
            $data['gsm_from'] = null;
            $data['gsm_to'] = null;
        } elseif (array_key_exists('gsm_from', $data) || array_key_exists('gsm_to', $data) || $row === null) {
            $from = $data['gsm_from'] ?? null;
            $to = $data['gsm_to'] ?? null;
            if ($from === null || $to === null) {
                throw ValidationException::withMessages(['gsm_from' => __('fabricplanning.master.gsm_band')]);
            }
            if ((int) $to < (int) $from) {
                throw ValidationException::withMessages(['gsm_to' => __('fabricplanning.master.gsm_order')]);
            }
        }
        unset($data['any_gsm']);

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['colour_group'] = $row->getAttribute('colour_group_id') ? DyeColourGroup::query()->whereKey($row->getAttribute('colour_group_id'))->value('name') : null;
        $out['any_gsm'] = $row->getAttribute('gsm_from') === null;
        $out['machine'] = DyeMachine::query()->whereKey($row->getAttribute('dye_machine_id'))->value('machine_no');
        $out['buyer'] = $row->getAttribute('buyer_id') ? Buyer::query()->whereKey($row->getAttribute('buyer_id'))->value('short_name') : null;

        return $out;
    }
}
