<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Http\Requests;

use App\Modules\WorkOrder\Models\WorkOrderLaborEntry;
use App\Shared\Concerns\ParsesLocalDateTimes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'technician_id' => ['nullable', 'string', 'size:26'],
            'labor_category' => ['required', Rule::in(WorkOrderLaborEntry::CATEGORIES)],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date'],
            // Only ever read for EXTERNAL. Internal rates come from the
            // technician's grade, server-side, because a client-supplied rate
            // would let anyone set what the work cost (ADR-065).
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'vendor_id' => ['nullable', 'string', 'size:26'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
