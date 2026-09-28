@php
    $tone = match ($priority) {
        'CRITICAL' => 'danger',
        'HIGH' => 'warning',
        'LOW' => 'secondary',
        default => 'info',
    };
@endphp

<x-status-pill :status="$priority" :tone="$tone">
    {{ __('work_order.priority_'.strtolower($priority)) }}
</x-status-pill>
