@props(['criticality'])

@php
    $tone = match ($criticality) {
        'CRITICAL' => 'danger',
        'HIGH' => 'warning',
        'MEDIUM' => 'info',
        default => 'secondary',
    };
@endphp

<x-status-pill :status="$criticality" :tone="$tone">
    {{ __('asset.criticality_'.strtolower($criticality)) }}
</x-status-pill>
