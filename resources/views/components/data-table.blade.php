@props([
    'title',
    'icon' => 'cil-list',
    'paginator' => null,
    'searchable' => true,
    'perPage' => true,
    'action' => null,
])

{{--
    The dense index listing (Frontend 9.3).

    One component so every list screen looks and behaves the same: a titled
    card, a toolbar, a compact table, and a footer stating the range rather
    than only the page number. "Showing 1 to 10 of 591" tells an operator
    whether their filter worked; "page 1" does not.

    Sorting, filtering and paging are server-side. A tenant with 20,000 assets
    cannot ship them all to the browser.
--}}
<div class="card">
    <div class="card-header">
        <i class="{{ $icon }}" aria-hidden="true"></i>
        <span>{{ $title }}</span>

        @if (isset($actions))
            <span class="ms-auto d-flex gap-2">{{ $actions }}</span>
        @endif
    </div>

    @if (isset($toolbar) || $searchable || $perPage)
        <div class="table-toolbar">
            @if ($perPage)
                <div class="d-flex align-items-center gap-2">
                    <label for="per_page" class="mb-0 text-nowrap">{{ __('common.show') }}</label>
                    <select id="per_page" name="per_page" form="{{ $attributes->get('filter-form', 'list-filter') }}"
                            class="form-select form-select-sm" onchange="this.form.requestSubmit()">
                        @foreach ([10, 25, 50, 100] as $size)
                            <option value="{{ $size }}" @selected((int) request('per_page', 25) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                    <span class="text-nowrap">{{ __('common.entries') }}</span>
                </div>
            @endif

            @if (isset($toolbar))
                <div class="d-flex align-items-center gap-2">{{ $toolbar }}</div>
            @endif

            @if ($searchable)
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <label for="search" class="mb-0 text-nowrap">{{ __('common.search') }}:</label>
                    <input id="search" name="search" type="search"
                           form="{{ $attributes->get('filter-form', 'list-filter') }}"
                           class="form-control form-control-sm" value="{{ request('search') }}"
                           placeholder="{{ __('common.search_ellipsis') }}">
                </div>
            @endif
        </div>
    @endif

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            {{ $slot }}
        </table>
    </div>

    @if ($paginator !== null)
        <div class="table-footer">
            <div>
                {{-- The range, not just the page: it is how an operator sees
                     whether a filter narrowed anything. --}}
                @if ($paginator->total() > 0)
                    {{ __('common.showing_entries', [
                        'from' => $paginator->firstItem(),
                        'to' => $paginator->lastItem(),
                        'total' => number_format($paginator->total()),
                    ]) }}
                @else
                    {{ __('common.showing_none') }}
                @endif
            </div>

            @if ($paginator->hasPages())
                <div class="ms-auto">{{ $paginator->onEachSide(1)->links() }}</div>
            @endif
        </div>
    @endif
</div>
