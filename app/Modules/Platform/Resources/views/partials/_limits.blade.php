{{-- What this customer is allowed, and what happens when they pass it.

     A panel of its own rather than four fields inside the contract form,
     because these are the numbers that actually change: a customer buys twenty
     more machines in March. Doing that through the contract form would put a
     new contract number in their file for a change of one field. --}}
<section class="panel panel-wide" id="limits">
    <header class="panel-head">
        <i class="cil-speedometer" aria-hidden="true"></i>
        <span>{{ __('platform.limits') }}</span>
    </header>

    @if ($contract)
        <form method="POST" action="{{ route('platform.tenants.limits', $company) }}">
            @csrf
            @method('PATCH')

            <div class="panel-body">
                <div class="form-text mb-3">{{ __('platform.limits_hint') }}</div>

                <div class="field-grid">
                    <div>
                        <label for="limit_factories" class="form-label">{{ __('platform.factories') }}</label>
                        <input id="limit_factories" name="included_factories" type="number" min="0"
                               value="{{ old('included_factories', $contract->included_factories) }}"
                               class="form-control form-control-sm"
                               placeholder="{{ __('platform.unlimited') }}">
                        <div class="form-text">{{ __('platform.in_use', ['count' => $factoryCount]) }}</div>
                    </div>

                    <div>
                        <label for="limit_assets" class="form-label">{{ __('platform.assets') }}</label>
                        <input id="limit_assets" name="included_assets" type="number" min="0"
                               value="{{ old('included_assets', $contract->included_assets) }}"
                               class="form-control form-control-sm"
                               placeholder="{{ __('platform.unlimited') }}">
                        <div class="form-text">{{ __('platform.in_use', ['count' => $assetCount]) }}</div>
                    </div>

                    <div>
                        <label for="limit_users" class="form-label">{{ __('platform.users') }}</label>
                        <input id="limit_users" name="included_users" type="number" min="0"
                               value="{{ old('included_users', $contract->included_users) }}"
                               class="form-control form-control-sm"
                               placeholder="{{ __('platform.unlimited') }}">
                        <div class="form-text">{{ __('platform.in_use', ['count' => $userCount]) }}</div>
                    </div>
                </div>

                {{-- Left blank means unlimited, and it has to say so. An empty
                     box that quietly meant nought would lock a customer out of
                     their own system the moment somebody tabbed past it. --}}
                <div class="form-text mt-2">{{ __('platform.limits_blank_hint') }}</div>
            </div>

            <div class="panel-body panel-divided">
                <label for="limit_policy" class="form-label">{{ __('platform.overage') }}</label>
                <select id="limit_policy" name="overage_policy" class="form-select form-select-sm">
                    @foreach (['WARN_ONLY', 'ALLOW_AND_BILL', 'BLOCK'] as $policy)
                        <option value="{{ $policy }}"
                            @selected(old('overage_policy', $contract->overage_policy) === $policy)>
                            {{ __('platform.overage_'.strtolower($policy)) }}
                        </option>
                    @endforeach
                </select>

                {{-- The sentence that makes the difference real. Only BLOCK
                     stops anything; the other two are commercial answers to
                     going over, not technical ones. --}}
                <div class="form-text mt-2">{{ __('platform.overage_effect') }}</div>

                <button class="btn btn-primary mt-3">{{ __('common.save') }}</button>
            </div>
        </form>
    @else
        <div class="panel-body text-body-secondary">
            {{ __('platform.limits_need_contract') }}
        </div>
    @endif
</section>
