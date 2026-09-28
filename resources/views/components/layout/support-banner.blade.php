@php
    $supportStaffId = session(\App\Modules\Platform\Actions\ManageSupportAccess::SESSION_KEY);
@endphp

@if ($supportStaffId)
    {{-- Unmissable, and at the very top of every page, because the failure this
         prevents is somebody forgetting whose account they are in and acting as
         a customer by accident (SRS 5.4). A discreet badge in a corner would
         not do it: this has to be the first thing on the screen. --}}
    <div class="bg-danger text-white py-2 px-3 d-flex flex-wrap align-items-center gap-3">
        <strong>{{ __('platform.support_session_banner') }}</strong>

        <span class="small">
            {{ __('platform.acting_as', ['name' => auth()->user()->name, 'email' => auth()->user()->email]) }}
        </span>

        <form method="POST" action="{{ route('app.support.leave') }}" class="ms-auto">
            @csrf
            <button class="btn btn-sm btn-light">{{ __('platform.leave_support_session') }}</button>
        </form>
    </div>
@endif
