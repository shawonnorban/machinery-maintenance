<?php

declare(strict_types=1);

namespace App\Modules\Asset\Http\Requests;

/**
 * Same shape as creation, minus the fields that are immutable after creation
 * and plus the optimistic-locking version (ADR-025).
 */
class UpdateAssetRequest extends StoreAssetRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('asset')) ?? false;
    }

    public function rules(): array
    {
        $rules = parent::rules();

        // asset_code is the identifier printed on the machine and referenced
        // in every historical record. Changing it would orphan that history.
        unset($rules['asset_code'], $rules['status']);

        $rules['version'] = ['required', 'integer', 'min:1'];

        return $rules;
    }
}
