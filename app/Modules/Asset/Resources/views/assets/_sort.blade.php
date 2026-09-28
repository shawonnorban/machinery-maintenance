@php
    $active = ($sort ?? null) === $column;
    $next = $active && ($direction ?? 'desc') === 'asc' ? 'desc' : 'asc';
@endphp

<a href="{{ request()->fullUrlWithQuery(['sort' => $column, 'direction' => $next]) }}"
   class="text-decoration-none text-body">
    {{ $label }}@if ($active) <span aria-hidden="true">{{ $direction === 'asc' ? '↑' : '↓' }}</span>@endif
</a>
