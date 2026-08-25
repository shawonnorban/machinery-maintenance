{{-- A company as a recognisable mark. Included rather than a component tag:
     module views are registered with loadViewsFrom, which namespaces includes
     but not anonymous component discovery.

     The customer's own logo when they have one, and a monogram when they do
     not. Both render as the same box at the same size, so a customer gaining
     a logo does not move anything around it — and the fallback is chosen here
     rather than at each call site, which is why the logo now appears on the
     customer cards as well without those cards knowing about it. --}}

@if ($company->logo_path !== null)
    <span class="tenant-mark tenant-mark-logo" aria-hidden="true">
        <img src="{{ $company->logoUrl() }}" alt="">
    </span>
@else
    @php
        // The colour is derived from the code rather than stored, so it is stable
        // for a customer for ever without a column, a picker or a migration —
        // and two customers whose names both begin with D still look different.
        $palette = ['#2c3e50', '#0c5460', '#4a3f8f', '#7a4b2a', '#1f6f54', '#8a2d4a', '#3d5a3f', '#5c4a7a'];
        $tint = $palette[crc32($company->code) % count($palette)];

        $initials = collect(preg_split('/\s+/', trim($company->name)))
            ->filter()
            ->take(2)
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');
    @endphp

    <span class="tenant-mark" style="background: {{ $tint }}" aria-hidden="true">{{ $initials ?: '?' }}</span>
@endif
