{{-- Usage against what the contract includes.

     The bar is the point. A number beside a limit needs arithmetic; a bar does
     not, and "how close is this customer to what they bought" is the only
     question these figures exist to answer.

     It also closes a gap that was there before: the limits could be set, and
     nothing on this screen showed what the customer was actually using, so
     they were being set blind. --}}

@php
    $rows = [
        ['label' => __('platform.factories'), 'used' => $factoryCount, 'limit' => $contract?->included_factories],
        ['label' => __('platform.assets'), 'used' => $assetCount, 'limit' => $contract?->included_assets],
        ['label' => __('platform.users'), 'used' => $userCount, 'limit' => $contract?->included_users],
    ];
@endphp

@foreach ($rows as $row)
    @php
        $limit = $row['limit'];
        $share = $limit > 0 ? min(($row['used'] / $limit) * 100, 100) : 0;
        $tone = match (true) {
            $limit === null || $limit === 0 => '',
            $row['used'] > $limit => 'is-over',
            $share >= 85 => 'is-near',
            default => '',
        };
    @endphp

    <div class="usage-row">
        <div class="usage-head">
            <span>{{ $row['label'] }}</span>

            @if ($limit === null || $limit === 0)
                {{-- No limit set is not "unlimited plan, zero used"; it is a
                     contract that says nothing about this dimension. A full or
                     empty bar would both imply otherwise, so there is no bar. --}}
                <span class="usage-count usage-unlimited">
                    {{ $row['used'] }} · {{ __('platform.no_limit_set') }}
                </span>
            @else
                <span class="usage-count {{ $row['used'] > $limit ? 'text-danger fw-semibold' : '' }}">
                    {{ $row['used'] }} / {{ $limit }}
                </span>
            @endif
        </div>

        @if ($limit !== null && $limit > 0)
            <div class="usage-track">
                <div class="usage-fill {{ $tone }}" style="width: {{ $share }}%"></div>
            </div>
        @endif
    </div>
@endforeach

@if ($contract && ($contract->included_assets || $contract->included_users || $contract->included_factories))
    {{-- Which sentence is true depends on the policy. Saying the wrong one is
         worse than saying nothing: for a long time this read "nothing is
         blocked" on every contract, including the ones that block. --}}
    <div class="form-text mt-3">
        {{ $contract->overage_policy === 'BLOCK'
            ? __('platform.limits_are_enforced')
            : __('platform.limits_are_advisory') }}
    </div>
@endif
