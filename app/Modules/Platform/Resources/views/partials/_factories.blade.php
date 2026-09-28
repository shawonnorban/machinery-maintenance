{{-- Which factories exist, and nothing about what is in them.

     A count and a name is a size; a machine list would be the customer's data,
     which needs a support grant (SRS 5, 5.4). --}}
<section class="panel">
    <header class="panel-head">
        <i class="cil-factory" aria-hidden="true"></i>
        <span>{{ __('platform.factories') }}</span>
        <span class="ms-auto tenant-code">{{ $factories->count() }}</span>
    </header>

    <div class="panel-list">
        @forelse ($factories as $factory)
            <div class="panel-list-item">
                <span>{{ $factory->name }}</span>
                <span class="ms-auto tenant-code">{{ $factory->code }}</span>
            </div>
        @empty
            <div class="panel-list-item text-body-secondary small">{{ __('platform.no_factories') }}</div>
        @endforelse
    </div>
</section>
