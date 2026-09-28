@props(['status'])

@php
    // Tone is a hint, never the message. The pill always carries text
    // (Frontend 3.3 rule 4).
    $tone = match ($status) {
        'RUNNING', 'COMMISSIONED' => 'success',
        'BREAKDOWN', 'UNDER_REPAIR' => 'danger',
        'UNDER_MAINTENANCE', 'IDLE' => 'warning',
        'RETIRED', 'SCRAPPED', 'LOST' => 'secondary',
        default => 'info',
    };
@endphp

<x-status-pill :status="$status" :tone="$tone">
    {{ __('asset.status_'.strtolower($status)) }}
</x-status-pill>
