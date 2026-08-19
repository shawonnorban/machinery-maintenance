<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Http\Requests;

use App\Shared\Concerns\ParsesLocalDateTimes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateWorkOrderRequest extends FormRequest
{
    use ParsesLocalDateTimes;

    public function authorize(): bool
    {
        return $this->user()?->can('work_order.work_order.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'string', 'size:26'],
            'maintenance_type_id' => ['required', 'string', 'size:26'],
            'template_version_id' => ['nullable', 'string', 'size:26'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'])],
            'requires_shutdown' => ['sometimes', 'boolean'],
            'assigned_team_id' => ['nullable', 'string', 'size:26'],
            'scheduled_start' => ['nullable', 'date'],
            'scheduled_end' => ['nullable', 'date', 'after_or_equal:scheduled_start'],
            // Estimates, not actuals. Actual cost is derived from labour and
            // part records and is never accepted from a client (ADR-064).
            'estimated_labor_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999999999'],
            'estimated_parts_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999999999'],
        ];
    }

    /**
     * An unchecked checkbox is absent from the request rather than false, and
     * the optional selects arrive as empty strings.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'asset_id' => $validated['asset_id'],
            'maintenance_type_id' => $validated['maintenance_type_id'],
            'template_version_id' => filled($validated['template_version_id'] ?? null)
                ? $validated['template_version_id'] : null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'priority' => $validated['priority'],
            'requires_shutdown' => $this->boolean('requires_shutdown'),
            'assigned_team_id' => filled($validated['assigned_team_id'] ?? null)
                ? $validated['assigned_team_id'] : null,
            // On the factory's clock: a datetime-local field carries no
            // timezone, so parsing it as UTC would shift every scheduled job.
            'scheduled_start' => $this->localDateTime('scheduled_start'),
            'scheduled_end' => $this->localDateTime('scheduled_end'),
            'estimated_labor_cost' => filled($validated['estimated_labor_cost'] ?? null)
                ? $validated['estimated_labor_cost'] : null,
            'estimated_parts_cost' => filled($validated['estimated_parts_cost'] ?? null)
                ? $validated['estimated_parts_cost'] : null,
            'source' => 'MANUAL',
        ];
    }
}
