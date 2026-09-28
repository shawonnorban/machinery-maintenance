@props(['status'])

@php
    $tone = match ($status) {
        'DUE' => 'warning',
        'OVERDUE' => 'danger',
        'COMPLETED' => 'success',
        'IN_PROGRESS' => 'info',
        'SKIPPED', 'CANCELLED' => 'secondary',
        default => 'secondary',
    };
@endphp

<x-status-pill :status="$status" :tone="$tone">
    {{ __('maintenance.status_'.strtolower($status)) }}
</x-status-pill>
