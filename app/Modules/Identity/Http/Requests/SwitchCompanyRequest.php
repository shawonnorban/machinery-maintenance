<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SwitchCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // Existence is deliberately not validated with `exists`. Membership
            // is checked in the action, and a validation message that
            // distinguishes "no such company" from "not yours" would leak
            // which company ids are real.
            'company_id' => ['required', 'string', 'size:26'],
        ];
    }
}
