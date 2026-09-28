@php
    // Tone carries the same meaning everywhere: red is wrong, amber is waiting,
    // green is done, grey is nothing happening yet (Frontend 3.4).
    $tone = match ($status) {
        'IN_PROGRESS' => 'primary',
        'ON_HOLD' => 'warning',
        'PENDING_APPROVAL' => 'warning',
        'COMPLETED' => 'info',
        'VERIFIED' => 'success',
        'CLOSED' => 'success',
        'CANCELLED', 'REJECTED' => 'danger',
        default => 'secondary',
    };
@endphp

<x-status-pill :status="$status" :tone="$tone">
    {{ __('work_order.status_'.strtolower($status)) }}
</x-status-pill>
