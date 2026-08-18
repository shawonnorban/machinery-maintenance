<?php

declare(strict_types=1);

namespace App\Modules\Asset\Http\Requests;

use App\Modules\Asset\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeAssetStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('changeStatus', $this->route('asset')) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(Asset::STATUSES)],
            // Required for terminal states; the action enforces that, because
            // the rule must hold for the API too.
            'reason' => ['nullable', 'string', 'max:255'],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}
