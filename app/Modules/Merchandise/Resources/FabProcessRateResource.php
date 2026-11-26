<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\DyeHouse\Models\DyeColourGroup;
use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\Merchandise\Models\FabProcessRate;
use App\Modules\Merchandise\Models\FabricType;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Costing & Budget → Fabric Process Rate → Records, New process rate and Edit
 * (AnnotechERP 06/09). A rate is numbered in the company as it is raised; its
 * currency defaults to BDT and its unit to Kg. A rate left without a fibre
 * combination template is what the Pending Fiber Combination tab lists.
 */
class FabProcessRateResource extends ResourceDefinition
{
    public function key(): string
    {
        return 'merch-fab-process-rates';
    }

    public function model(): string
    {
        return FabProcessRate::class;
    }

    public function viewPermission(): string
    {
        return 'merchandise.costing.view';
    }

    public function managePermission(): string
    {
        return 'merchandise.costing.manage';
    }

    public function columns(): array
    {
        return ['rate_no', 'category', 'fiber_template', 'count_range', 'fabric_type_id', 'colour_group_id', 'is_slub', 'description', 'rate', 'currency', 'per', 'prod_rate', 'process_loss', 'active'];
    }

    public function booleans(): array
    {
        return ['is_slub', 'active'];
    }

    public function defaults(): array
    {
        return ['currency' => 'BDT', 'per' => 'Kg', 'active' => true, 'is_slub' => false];
    }

    public function query(): Builder
    {
        return FabProcessRate::query()->orderBy('rate_no');
    }

    public function deletable(): bool
    {
        return true;
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = app(TenantContext::class)->companyId();

        return [
            'category' => [$required, Rule::in(FabProcessRate::CATEGORIES)],
            'fiber_template' => ['nullable', Rule::in(FabProcessRate::TEMPLATES)],
            'count_range' => ['nullable', 'string', 'max:64'],
            'fabric_type_id' => ['nullable', Rule::exists('fabric_types', 'id')->where('company_id', $company)],
            'colour_group_id' => ['nullable', Rule::exists('dye_colour_groups', 'id')->where('company_id', $company)],
            'is_slub' => ['sometimes', 'boolean'],
            'description' => [$required, 'string', 'max:500'],
            'rate' => [$required, 'numeric', 'min:0', 'max:9999999'],
            'currency' => ['nullable', 'string', 'regex:/^[A-Za-z]{3}$/'],
            'per' => ['nullable', 'string', 'max:16'],
            'prod_rate' => [$required, 'numeric', 'min:0', 'max:9999999'],
            'process_loss' => [$required, 'numeric', 'min:0', 'max:100'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'category.required' => __('merchandise.fab_process_rate.category_required'),
            'description.required' => __('merchandise.fab_process_rate.description_required'),
            'rate.required' => __('merchandise.fab_process_rate.rate_required'),
            'prod_rate.required' => __('merchandise.fab_process_rate.prod_rate_required'),
            'process_loss.required' => __('merchandise.fab_process_rate.loss_required'),
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        foreach (['count_range', 'description', 'per'] as $column) {
            if (array_key_exists($column, $data) && is_string($data[$column])) {
                $data[$column] = trim($data[$column]) === '' ? null : trim($data[$column]);
            }
        }
        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }
        if ($row === null) {
            // Held to the end of the save's transaction, so two rates never share a number.
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['merch_fab_process_rates:'.app(TenantContext::class)->companyId()]);
            $data['rate_no'] = 1 + (int) FabProcessRate::query()->max('rate_no');
            $data['per'] ??= 'Kg';
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        foreach (['rate', 'prod_rate', 'process_loss'] as $column) {
            $out[$column] = (float) $row->getAttribute($column);
        }
        $type = $row->getAttribute('fabric_type_id') ? FabricType::query()->whereKey($row->getAttribute('fabric_type_id'))->first(['name', 'code']) : null;
        $out['fabric_type'] = $type?->name;
        $out['colour_group'] = $row->getAttribute('colour_group_id') ? DyeColourGroup::query()->whereKey($row->getAttribute('colour_group_id'))->value('name') : null;

        return $out;
    }
}
