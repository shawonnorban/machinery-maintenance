{{-- Twelve months of this customer's own usage, already measured.

     Nothing here is computed for the page. UsageMeter records every company's
     factories, machines, users and work-order volume monthly (billing:advance)
     so the contract's limits can be set against evidence rather than a guess;
     this reads that same history back rather than keeping a second copy of it. --}}
@php
    $panels = [
        'ACTIVE_FACTORIES' => ['label' => __('platform.factories'), 'icon' => 'building'],
        'ACTIVE_ASSETS' => ['label' => __('platform.assets'), 'icon' => 'settings'],
        'ACTIVE_USERS' => ['label' => __('platform.users'), 'icon' => 'people'],
        'WORK_ORDERS_CREATED' => ['label' => __('platform.work_orders'), 'icon' => 'description'],
    ];
@endphp

@foreach ($panels as $metric => $meta)
    @php
        $months = $usageHistory[$metric];
        $values = array_values($months);
        $latest = end($values) ?: 0;
    @endphp

    <section class="panel">
        <header class="panel-head">
            <i class="cil-{{ $meta['icon'] }}" aria-hidden="true"></i>
            <span>{{ $meta['label'] }}</span>
            <span class="ms-auto panel-figure">{{ $latest }}</span>
        </header>

        <div class="panel-body">
            @if (array_sum($values) === 0)
                <div class="text-body-secondary small">{{ __('platform.no_usage_history') }}</div>
            @else
                <svg class="analytics-chart" viewBox="0 0 100 32" preserveAspectRatio="none" role="img"
                     aria-label="{{ $meta['label'] }}">
                    @php
                        $max = max(1, ...$values);
                        $barWidth = 100 / (count($months) * 1.5);
                        $gap = $barWidth * 0.5;
                    @endphp
                    @foreach ($months as $label => $value)
                        @php
                            $height = $value === 0 ? 1 : max(2, ($value / $max) * 28);
                            $x = $loop->index * ($barWidth + $gap);
                        @endphp
                        <rect x="{{ $x }}" y="{{ 30 - $height }}" width="{{ $barWidth }}" height="{{ $height }}"
                              rx="0.6" class="{{ $loop->last ? 'analytics-bar is-current' : 'analytics-bar' }}">
                            <title>{{ $label }}: {{ $value }}</title>
                        </rect>
                    @endforeach
                </svg>
                <div class="d-flex justify-content-between text-body-secondary" style="font-size: 0.6875rem">
                    <span>{{ array_key_first($months) }}</span>
                    <span>{{ array_key_last($months) }}</span>
                </div>
            @endif
        </div>
    </section>
@endforeach
