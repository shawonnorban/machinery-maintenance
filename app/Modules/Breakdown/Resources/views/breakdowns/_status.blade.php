@php
    // Red while the machine is down and nobody has picked it up, amber while
    // it is being worked, green once it is fixed (Frontend 3.4).
    $tone = match ($status) {
        'REPORTED' => 'danger',
        'ACKNOWLEDGED', 'ASSIGNED' => 'warning',
        'IN_REPAIR' => 'primary',
        'ON_HOLD' => 'warning',
        'REPAIRED' => 'info',
        'PRODUCTION_RESUMED', 'CLOSED' => 'success',
        default => 'secondary',
    };
@endphp

<x-status-pill :status="$status" :tone="$tone">
    {{ __('breakdown.status_'.strtolower($status)) }}
</x-status-pill>
