<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\EmbellishmentRate;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\Style;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Costing & Budget → Print & Embroidery Rate Setup → Records, New and Edit
 * embellishment rate, and the Pendings tab's Confirm and Drop (AnnotechERP
 * 06/09). A rate is in BDT per piece unless said otherwise. Confirming a rate
 * makes it costable and closes it: a confirmed rate is not rewritten, though
 * it may still be removed.
 */
class EmbellishmentRateResource extends ResourceDefinition
{
    public function key(): string
    {
        return 'merch-embellishment-rates';
    }

    public function model(): string
    {
        return EmbellishmentRate::class;
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
        return ['type', 'style_id', 'order_id', 'buyer_id', 'supplier_id', 'description', 'rate', 'currency', 'per', 'confirmed'];
    }

    public function booleans(): array
    {
        return ['confirmed'];
    }

    public function defaults(): array
    {
        return ['currency' => 'BDT', 'per' => 'Pcs', 'confirmed' => false];
    }

    public function query(): Builder
    {
        return EmbellishmentRate::query()->latest();
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
            'type' => [$required, Rule::in(EmbellishmentRate::TYPES)],
            'style_id' => ['nullable', Rule::exists('merch_styles', 'id')->where('company_id', $company)],
            'order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $company)],
            'buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'supplier_id' => ['nullable', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'description' => [$required, 'string', 'max:1000'],
            'rate' => [$required, 'numeric', 'min:0', 'max:9999999'],
            'currency' => ['nullable', 'string', 'regex:/^[A-Za-z]{3}$/'],
            'per' => ['nullable', 'string', 'max:16'],
            'confirmed' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => __('merchandise.embellishment_rate.type_required'),
            'description.required' => __('merchandise.embellishment_rate.description_required'),
            'rate.required' => __('merchandise.embellishment_rate.rate_required'),
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if ($row && $row->getAttribute('confirmed')) {
            throw ValidationException::withMessages(['confirmed' => __('merchandise.embellishment_rate.confirmed_closed')]);
        }
        foreach (['description', 'per'] as $column) {
            if (array_key_exists($column, $data) && is_string($data[$column])) {
                $data[$column] = trim($data[$column]) === '' ? null : trim($data[$column]);
            }
        }
        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }
        if (! empty($data['confirmed'])) {
            $data['confirmed_at'] = now();
            $data['confirmed_by'] = $this->actingUserId();
        }
        if ($row === null) {
            $data['per'] ??= 'Pcs';
            $data['created_by'] = $this->actingUserId();
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['rate'] = (float) $row->getAttribute('rate');
        $order = $row->getAttribute('order_id') ? Order::query()->whereKey($row->getAttribute('order_id'))->first(['order_no', 'style_no']) : null;
        $style = $row->getAttribute('style_id') ? Style::query()->whereKey($row->getAttribute('style_id'))->first() : null;
        $out['order_no'] = $order?->order_no;
        $out['style_no'] = $style?->styleNo() ?? $order?->style_no;
        $out['order_style'] = $order?->order_no ?? $style?->styleNo();
        $out['buyer'] = $row->getAttribute('buyer_id') ? Buyer::query()->whereKey($row->getAttribute('buyer_id'))->value('short_name') : null;
        $out['supplier'] = $row->getAttribute('supplier_id') ? MaterialSupplier::query()->whereKey($row->getAttribute('supplier_id'))->value('name') : null;
        $out['confirmed_at'] = $row->getAttribute('confirmed_at')?->toIso8601String();

        return $out;
    }
}
