@props([
    'label',
    'value' => null,
    'caption' => null,
    'tone' => 'primary',
    'reason' => null,
])

{{-- A KPI with a null denominator renders N/A with a reason, never 0.
     Zero would read as "fails constantly" (SRS 31.2 rule 2). --}}
<div class="card kpi-tile mb-4">
    <div class="card-body">
        <div class="text-body-secondary text-uppercase small fw-semibold">{{ $label }}</div>

        @if ($value === null)
            <div class="kpi-na" title="{{ $reason ?? __('common.no_data_reason') }}">
                {{ __('common.not_available') }}
            </div>
        @else
            <div class="kpi-value text-{{ $tone }}">{{ $value }}</div>
        @endif

        @if ($caption)
            <div class="kpi-caption">{{ $caption }}</div>
        @endif
    </div>
</div>
