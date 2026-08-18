<?php

declare(strict_types=1);

namespace App\Modules\Asset\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('transfer', $this->route('asset')) ?? false;
    }

    public function rules(): array
    {
        return [
            'to_location_id' => ['required', 'string', 'size:26'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}
