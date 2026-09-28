{{-- Support access (SRS 5.4).

     Deliberately awkward: a written reason, an expiry that cannot exceed eight
     hours, an audit row at each end, and a notice the customer reads. Support
     access that is easy and quiet is support access nobody can account for.

     Alone in its own tab now rather than paired with another panel, so it
     takes the full width — a lone half-width panel would leave the other half
     of the row sitting empty. --}}
<section class="panel panel-wide">
    <header class="panel-head">
        <i class="cil-lock-locked" aria-hidden="true"></i>
        <span>{{ __('platform.support_access') }}</span>
    </header>

    <div class="panel-body">
        <div class="form-text mb-3">{{ __('platform.support_access_hint') }}</div>

        @error('reason')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
        @error('grant')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
        @error('user_id')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

        @if ($activeGrant && $activeGrant->granted_to === auth()->id())
            <form method="POST" action="{{ route('platform.support.enter', $activeGrant) }}" class="row g-2">
                @csrf
                <div class="col-12">
                    <label for="act_as" class="form-label">{{ __('platform.act_as') }}</label>
                    <select id="act_as" name="user_id" class="form-select form-select-sm" required>
                        @foreach ($members as $member)
                            <option value="{{ $member->id }}">{{ $member->name }} — {{ $member->email }}</option>
                        @endforeach
                    </select>
                    {{-- Which person is chosen appears in the audit row:
                         "acted as the owner" and "acted as a storekeeper" are
                         different amounts of access. --}}
                    <div class="form-text">{{ __('platform.act_as_hint') }}</div>
                </div>

                <div class="col-12">
                    <button class="btn btn-danger">{{ __('platform.enter') }}</button>
                </div>
            </form>
        @else
            <form method="POST" action="{{ route('platform.support.open', $company) }}" class="row g-2">
                @csrf
                <div class="col-12">
                    <label for="reason" class="form-label">{{ __('platform.reason') }}</label>
                    <textarea id="reason" name="reason" rows="2" required maxlength="500"
                              class="form-control form-control-sm"
                              placeholder="{{ __('platform.reason_example') }}">{{ old('reason') }}</textarea>
                </div>

                <div class="col-md-3">
                    <label for="hours" class="form-label">{{ __('platform.hours') }}</label>
                    <input id="hours" name="hours" type="number" min="1" max="8"
                           value="{{ old('hours', 2) }}" required class="form-control form-control-sm">
                </div>

                <div class="col-12">
                    <button class="btn btn-outline-danger">{{ __('platform.request_access') }}</button>
                </div>
            </form>
        @endif
    </div>

    @if ($grants->isNotEmpty())
        <details class="panel-foot" open>
            <summary>{{ __('platform.access_history') }}</summary>

            <div class="access-log mt-2">
                @foreach ($grants as $grant)
                    <div class="access-entry">
                        <div class="small fw-semibold">
                            {{ $grant->holder?->name }}
                            @if ($grant->isActive())
                                <span class="badge bg-danger">{{ __('platform.active_now') }}</span>
                            @endif
                        </div>
                        <div class="small text-body-secondary">{{ $grant->reason }}</div>
                        <div class="small text-body-secondary">
                            @dt($grant->starts_at) → @dt($grant->ended_at ?? $grant->expires_at)
                        </div>
                    </div>
                @endforeach
            </div>
        </details>
    @endif
</section>
