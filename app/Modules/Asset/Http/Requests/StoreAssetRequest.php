<?php

declare(strict_types=1);

namespace App\Modules\Asset\Http\Requests;

use App\Modules\Asset\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation lives here once and serves the web form, the API and the
 * generated OpenAPI schema (ADR-066, API Schemas 4.2).
 */
class StoreAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Asset::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'asset_code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],

            'asset_type_id' => ['required', 'string', 'size:26'],
            // Cross-field validity (category belongs to type) is checked in the
            // action, because it is a domain rule that the API must enforce too.
            'asset_category_id' => ['required', 'string', 'size:26'],
            'manufacturer_id' => ['nullable', 'string', 'size:26'],
            'asset_model_id' => ['nullable', 'string', 'size:26'],
            'parent_asset_id' => ['nullable', 'string', 'size:26'],

            'serial_number' => ['nullable', 'string', 'max:128'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'country_of_origin' => ['nullable', 'string', 'size:2'],

            'criticality' => ['required', Rule::in(Asset::CRITICALITIES)],
            'status' => ['nullable', Rule::in(Asset::CREATABLE_STATUSES)],

            'current_factory_id' => ['required', 'string', 'size:26'],
            'asset_location_id' => ['required', 'string', 'size:26'],

            'purchase_date' => ['nullable', 'date'],
            'installation_date' => ['nullable', 'date'],
            'commissioning_date' => ['nullable', 'date'],
            'warranty_start' => ['nullable', 'date'],
            'warranty_end' => ['nullable', 'date'],

            'acquisition_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999999999'],
            'installation_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999999999'],
            'currency' => ['nullable', 'string', 'size:3'],

            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'asset_code' => __('asset.asset_code'),
            'asset_type_id' => __('asset.type'),
            'asset_category_id' => __('asset.category'),
            'current_factory_id' => __('asset.factory'),
            'asset_location_id' => __('asset.location'),
        ];
    }

    /**
     * Cost without a currency is a number with no meaning, and in a
     * multi-currency system it silently becomes the wrong number.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $hasCost = filled($this->input('acquisition_cost')) || filled($this->input('installation_cost'));

            if ($hasCost && blank($this->input('currency'))) {
                $validator->errors()->add('currency', __('asset.currency_required_with_cost'));
            }
        });
    }
}
