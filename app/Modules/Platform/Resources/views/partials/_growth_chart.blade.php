{{-- Six real months of onboarding, as bars.

     Inline SVG rather than Chart.js: chart.js is in package.json but wired to
     no page in the product, and pulling in a whole charting library's first
     real use for six numbers would cost more than it says. An SVG this small
     is also immune to the one failure mode a JS chart has here — nothing to
     draw before the script runs, so there is no blank card on a slow
     connection. --}}
@php
    $max = max(1, ...array_values($months));
    $barWidth = 100 / (count($months) * 1.6);
    $gap = $barWidth * 0.6;
@endphp

<svg class="figure-chart" viewBox="0 0 100 32" preserveAspectRatio="none" role="img"
     aria-label="{{ __('platform.growth_chart_label') }}">
    @foreach ($months as $label => $value)
        @php
            $height = max(2, ($value / $max) * 28);
            $x = $loop->index * ($barWidth + $gap);
        @endphp
        <rect x="{{ $x }}" y="{{ 30 - $height }}" width="{{ $barWidth }}" height="{{ $height }}"
              rx="0.6" class="{{ $loop->last ? 'figure-chart-bar is-current' : 'figure-chart-bar' }}">
            <title>{{ $label }}: {{ $value }}</title>
        </rect>
    @endforeach
</svg>
