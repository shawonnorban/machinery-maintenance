<?php

declare(strict_types=1);

namespace App\Modules\Breakdown\Http\Requests;

use App\Modules\Breakdown\Models\Breakdown;
use App\Shared\Concerns\ParsesLocalDateTimes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportBreakdownRequest extends FormRequest
{
    use ParsesLocalDateTimes;

    public function authorize(): bool
    {
        return $this->user()?->can('breakdown.breakdown.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'string', 'size:26'],
            'problem_description' => ['required', 'string', 'max:5000'],
            // Both optional: an operator at a stopped machine should be able to
            // report it in three fields. Everything diagnostic is filled in by
            // maintenance later.
            'failure_at' => ['nullable', 'date'],
            'reported_at' => ['nullable', 'date'],
            'priority' => ['nullable', Rule::in(Breakdown::PRIORITIES)],
            'severity' => ['nullable', Rule::in(Breakdown::SEVERITIES)],
            'production_line_id' => ['nullable', 'string', 'size:26'],
            'production_order_reference' => ['nullable', 'string', 'max:255'],
            'failure_category_id' => ['nullable', 'string', 'size:26'],
            'failure_code_id' => ['nullable', 'string', 'size:26'],
            'downtime_reason_code_id' => ['nullable', 'string', 'size:26'],
        ];
    }

    /**
     * Optional selects arrive as empty strings rather than absent.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $validated = $this->validated();

        $nullable = [
            'priority', 'severity',
            'production_line_id', 'production_order_reference',
            'failure_category_id', 'failure_code_id', 'downtime_reason_code_id',
        ];

        $payload = [
            'asset_id' => $validated['asset_id'],
            'problem_description' => $validated['problem_description'],
            // A datetime-local field carries no timezone. Parsed as UTC, a
            // Dhaka supervisor's "21:50" lands at 03:50 the next morning and
            // every derived figure inherits the six-hour error (SRS 47.2).
            'failure_at' => $this->localDateTime('failure_at'),
            'reported_at' => $this->localDateTime('reported_at'),
        ];

        foreach ($nullable as $field) {
            $payload[$field] = filled($validated[$field] ?? null) ? $validated[$field] : null;
        }

        return $payload;
    }
}
