{{-- The two things this page can do *to* a customer rather than for one.

     Both in the same red panel, in the order of how hard they are to undo:
     suspension lifts this afternoon, closing takes a decision to reverse. --}}
<section class="panel panel-danger" id="danger">
    <header class="panel-head">
        <i class="cil-ban" aria-hidden="true"></i>
        <span>{{ __('platform.suspend') }}</span>
    </header>

    @unless ($company->isSuspended())
        <form method="POST" action="{{ route('platform.tenants.suspend', $company) }}"
              onsubmit="return confirm('{{ __('platform.suspend_confirm') }}')">
            @csrf
            <div class="panel-body">
                <div class="form-text mb-2">{{ __('platform.suspend_hint') }}</div>

                <label for="suspend_reason" class="form-label">{{ __('platform.suspension_reason') }}</label>
                {{-- Required, and shown to the customer verbatim. "Policy"
                     answers nothing for somebody whose factory has just lost
                     its maintenance system. --}}
                <textarea id="suspend_reason" name="reason" rows="2" required minlength="10" maxlength="500"
                          class="form-control form-control-sm"
                          placeholder="{{ __('platform.suspension_reason_example') }}"></textarea>
                <div class="form-text">{{ __('platform.suspension_reason_hint') }}</div>

                <button class="btn btn-outline-danger mt-3">{{ __('platform.suspend') }}</button>
            </div>
        </form>
    @endunless

    <div class="panel-body panel-divided" id="close">
        <div class="panel-subhead">{{ __('platform.close_account') }}</div>
        <div class="form-text mb-2">{{ __('platform.close_hint') }}</div>
        {{-- Said before the fields, not after the button. It is the fact that
             decides whether somebody should carry on typing. --}}
        <div class="form-text mb-3">{{ __('platform.close_reversible') }}</div>

        @error('confirm_code')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

        <form method="POST" action="{{ route('platform.tenants.destroy', $company) }}">
            @csrf
            @method('DELETE')

            <div class="mb-3">
                <label for="close_reason" class="form-label">{{ __('platform.reason') }}</label>
                <input id="close_reason" name="reason" type="text" required minlength="10" maxlength="500"
                       class="form-control form-control-sm" value="{{ old('reason') }}">
            </div>

            <div class="mb-3">
                {{-- Typed, not clicked. A confirm() dialog is dismissed by
                     reflex the third time somebody sees it; a code has to be
                     read off the screen and copied. --}}
                <label for="close_code" class="form-label">
                    {{ __('platform.confirm_code_label', ['code' => $company->code]) }}
                </label>
                <input id="close_code" name="confirm_code" type="text" required autocomplete="off"
                       class="form-control form-control-sm" placeholder="{{ $company->code }}">
            </div>

            <button class="btn btn-danger">{{ __('platform.close_account') }}</button>
        </form>
    </div>
</section>
