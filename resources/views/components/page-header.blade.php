@props(['title', 'subtitle' => null])

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h1 class="h4 mb-0">{{ $title }}</h1>
        @if ($subtitle)
            <div class="text-body-secondary small">{{ $subtitle }}</div>
        @endif
    </div>

    @if (isset($actions))
        <div class="d-flex gap-2">{{ $actions }}</div>
    @endif
</div>
