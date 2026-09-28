{{-- The customer's own sign-in.

     One account, not a list. The company's users are the customer's business
     to manage; what the platform is ever asked to rescue is the owner account
     — the one nobody inside the company can reset, because resetting it needs
     an inbox that is often exactly what has been lost. --}}
@if ($owner)
    <section class="panel panel-warn" id="credentials">
        <header class="panel-head">
            <i class="cil-user" aria-hidden="true"></i>
            <span>{{ __('platform.customer_login') }}</span>
            <span class="ms-auto tenant-code">{{ $owner->name }}</span>
        </header>

        {{-- Who this customer is, read rather than filled in. Everything a
             support call actually needs to confirm it is speaking to the
             right person, in one glance, before touching either form below. --}}
        <div class="panel-body">
            <div class="field-grid">
                <div>
                    <span class="field-label">{{ __('platform.owner_name') }}</span>
                    <span class="field-value">{{ $owner->name }}</span>
                </div>
                <div>
                    <span class="field-label">{{ __('platform.owner_phone') }}</span>
                    <span class="field-value">{{ $owner->phone ?? __('platform.not_recorded') }}</span>
                </div>
                <div>
                    <span class="field-label">{{ __('platform.owner_email_verified') }}</span>
                    <span class="field-value">
                        @if ($owner->email_verified_at)
                            <span class="text-success">{{ __('platform.verified_on', ['date' => $owner->email_verified_at->toDateString()]) }}</span>
                        @else
                            <span class="text-warning">{{ __('platform.not_verified') }}</span>
                        @endif
                    </span>
                </div>
                <div>
                    <span class="field-label">{{ __('platform.owner_last_login') }}</span>
                    <span class="field-value">
                        {{ $owner->last_login_at?->diffForHumans() ?? __('platform.never_signed_in') }}
                    </span>
                </div>
                <div>
                    <span class="field-label">{{ __('platform.owner_customer_since') }}</span>
                    <span class="field-value">{{ $ownerMembership?->created_at?->toDateString() ?? '—' }}</span>
                </div>
                <div>
                    <span class="field-label">{{ __('platform.owner_status') }}</span>
                    <span class="field-value">
                        @if ($ownerMembership?->status === 'ACTIVE')
                            <span class="text-success">{{ __('platform.member_status_active') }}</span>
                        @else
                            <span class="text-warning">{{ __('platform.member_status_suspended') }}</span>
                        @endif
                    </span>
                </div>
            </div>
        </div>

        <div class="panel-body panel-divided">
            <div class="form-text mb-3">{{ __('platform.customer_login_hint') }}</div>

            @error('email')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

            <form method="POST" action="{{ route('platform.tenants.members.email', [$company, $owner]) }}">
                @csrf
                @method('PATCH')

                <div class="mb-3">
                    <label for="owner_email" class="form-label">{{ __('platform.customer_id') }}</label>
                    <input id="owner_email" name="email" type="email" required maxlength="255"
                           value="{{ old('email', $owner->email) }}" class="form-control form-control-sm">
                    <div class="form-text">{{ __('platform.customer_id_hint') }}</div>
                </div>

                <div class="mb-3">
                    <label for="email_reason" class="form-label">{{ __('platform.reason') }}</label>
                    <input id="email_reason" name="reason" type="text" required minlength="10" maxlength="500"
                           class="form-control form-control-sm"
                           placeholder="{{ __('platform.credential_reason_example') }}">
                </div>

                <button class="btn btn-outline-secondary">{{ __('platform.save_email') }}</button>
            </form>
        </div>

        <div class="panel-body panel-divided">
            <form method="POST" action="{{ route('platform.tenants.members.password', [$company, $owner]) }}"
                  onsubmit="return confirm('{{ __('platform.reset_confirm', ['name' => $owner->name]) }}')">
                @csrf

                <div class="mb-3">
                    <label for="reset_reason" class="form-label">{{ __('platform.customer_password') }}</label>
                    <input id="reset_reason" name="reason" type="text" required minlength="10" maxlength="500"
                           class="form-control form-control-sm"
                           placeholder="{{ __('platform.reset_reason_example') }}">
                    {{-- Said before the button, because it is what somebody
                         about to press it needs to know. --}}
                    <div class="form-text">{{ __('platform.reset_hint') }}</div>
                </div>

                <button class="btn btn-outline-danger">{{ __('platform.reset_password') }}</button>
            </form>
        </div>
    </section>
@endif
