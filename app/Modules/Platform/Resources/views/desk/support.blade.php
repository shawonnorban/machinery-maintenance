@extends('platform::layout')
@section('title', __('platform.support_access'))

@section('content')
    <x-page-header :title="__('platform.support_access')" :subtitle="__('platform.support_desk_intro')" />

    {{-- Open grants first and loudly. Somebody is inside a customer's data
         right now, which is the only reason this page is urgent. --}}
    <section class="panel panel-danger mb-4">
        <header class="panel-head">
            <i class="cil-lock-unlocked" aria-hidden="true"></i>
            <span>{{ __('platform.support_open_now') }}</span>
            @if ($open->isNotEmpty())
                <span class="badge bg-danger ms-auto">{{ $open->count() }}</span>
            @endif
        </header>

        <div class="panel-list">
            @forelse ($open as $grant)
                <div class="panel-list-item">
                    <div class="min-w-0">
                        <div class="fw-semibold">
                            {{ $grant->holder?->name }} — {{ $grant->company?->name }}
                        </div>
                        <div class="tenant-code">{{ $grant->reason }}</div>
                        <div class="tenant-code">
                            {{ __('platform.until') }} @dt($grant->expires_at)
                        </div>
                    </div>

                    <div class="ms-auto d-flex gap-2">
                        @if ($grant->company)
                            <a href="{{ route('platform.tenants.show', $grant->company) }}"
                               class="btn btn-sm btn-outline-secondary">{{ __('platform.manage') }}</a>
                        @endif

                        @if ($grant->granted_to === auth()->id())
                            <form method="POST" action="{{ route('platform.support.close', $grant) }}">
                                @csrf
                                <button class="btn btn-sm btn-danger">{{ __('platform.hand_back') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="panel-list-item text-body-secondary small">
                    {{ __('platform.support_none_open') }}
                </div>
            @endforelse
        </div>
    </section>

    <section class="panel">
        <header class="panel-head">
            <i class="cil-history" aria-hidden="true"></i>
            <span>{{ __('platform.access_history') }}</span>
        </header>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('platform.staff') }}</th>
                        <th>{{ __('platform.tenant') }}</th>
                        <th>{{ __('platform.reason') }}</th>
                        <th>{{ __('platform.term') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($past as $grant)
                        <tr>
                            <td>{{ $grant->holder?->name }}</td>
                            <td>
                                @if ($grant->company)
                                    <a href="{{ route('platform.tenants.show', $grant->company) }}">
                                        {{ $grant->company->name }}
                                    </a>
                                @else
                                    <span class="text-body-secondary">—</span>
                                @endif
                            </td>
                            <td class="small">{{ $grant->reason }}</td>
                            <td class="small text-nowrap">
                                @dt($grant->starts_at) → @dt($grant->ended_at ?? $grant->expires_at)
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-0">
                                <x-empty-state :title="__('platform.support_no_history')"
                                               :description="__('platform.support_no_history_hint')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
