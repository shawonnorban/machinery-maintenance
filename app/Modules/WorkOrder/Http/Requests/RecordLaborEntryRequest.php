<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Http\Requests;

use App\Shared\Concerns\ParsesLocalDateTimes;
use Illuminate\Foundation\Http\FormRequest;

class RecordLaborEntryRequest extends FormRequest
{
    use ParsesLocalDateTimes;

    public function authorize(): bool
    {
        return $this->user()?->can('work_order.labor.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            // Time and who spent it. There is no money on this form:
            // technicians are salaried, so an hour of theirs is already paid
            // for and has no cost to record here.
            'technician_id' => ['required', 'string', 'size:26'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
