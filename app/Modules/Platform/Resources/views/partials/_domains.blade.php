{{-- Where this customer reaches their system.

     Two kinds, and they cost the customer very different amounts of effort. A
     subdomain works the moment it is added. A customer's own domain needs
     their DNS, their proof, and a certificate on our side — so the screen says
     what is still outstanding rather than showing a row that looks finished
     and quietly does nothing. --}}
<section class="panel panel-wide" id="domains">
    <header class="panel-head">
        <i class="cil-globe-alt" aria-hidden="true"></i>
        <span>{{ __('platform.domains') }}</span>
    </header>

    <div class="panel-list">
        @forelse ($domains as $domain)
            <div class="panel-list-item">
                <div class="min-w-0">
                    <div class="fw-semibold">
                        {{ $domain->host }}
                        @if ($domain->is_primary)
                            <span class="badge bg-primary">{{ __('platform.domain_primary') }}</span>
                        @endif
                    </div>
                    <div class="tenant-code">
                        {{ __('platform.domain_kind_'.strtolower($domain->kind)) }}
                        @if ($domain->isVerified())
                            · <span class="text-success">{{ __('platform.domain_verified') }}</span>
                        @else
                            · <span class="text-warning">{{ __('platform.domain_pending') }}</span>
                        @endif
                    </div>
                </div>

                <div class="ms-auto d-flex gap-2">
                    @unless ($domain->isVerified())
                        <form method="POST" action="{{ route('platform.domains.verify', $domain->id) }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-primary">{{ __('platform.domain_check') }}</button>
                        </form>
                    @endunless

                    @if ($domain->isVerified() && ! $domain->is_primary)
                        <form method="POST" action="{{ route('platform.domains.primary', $domain->id) }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-secondary">
                                {{ __('platform.domain_make_primary') }}
                            </button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('platform.domains.destroy', $domain->id) }}"
                          onsubmit="return confirm('{{ __('platform.domain_remove_confirm', ['host' => $domain->host]) }}')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">{{ __('common.delete') }}</button>
                    </form>
                </div>
            </div>

            @unless ($domain->isVerified())
                {{-- The instructions, next to the address they are for, and
                     open rather than folded: a domain sits unverified exactly
                     because somebody has not done this yet. --}}
                <div class="panel-body panel-divided">
                    <div class="panel-subhead">{{ __('platform.domain_steps') }}</div>

                    <ol class="mb-0 ps-3 small">
                        <li class="mb-2">
                            {{ __('platform.domain_step_cname') }}
                            <div class="mt-1">
                                <code class="user-select-all">{{ $domain->host }}</code>
                                → <code class="user-select-all">{{ config('tenancy.platform_host') }}</code>
                            </div>
                        </li>
                        <li class="mb-2">
                            {{ __('platform.domain_step_txt') }}
                            <div class="mt-1">
                                <code class="user-select-all">{{ $domain->verificationRecordName() }}</code>
                                = <code class="user-select-all">{{ $domain->verification_token }}</code>
                            </div>
                        </li>
                        <li>{{ __('platform.domain_step_check') }}</li>
                    </ol>

                    {{-- The part that is not ours and cannot be made ours from
                         this screen. Better said here than discovered as a
                         certificate warning by the customer's staff. --}}
                    <div class="form-text mt-3">{{ __('platform.domain_tls_note') }}</div>
                </div>
            @endunless
        @empty
            <div class="panel-list-item text-body-secondary small">
                {{ __('platform.domain_none') }}
            </div>
        @endforelse
    </div>

    <details class="panel-foot" @if ($errors->has('host')) open @endif>
        <summary>{{ __('platform.domain_add') }}</summary>

        @error('host')<div class="alert alert-danger py-2 mt-2 mb-0">{{ $message }}</div>@enderror

        <form method="POST" action="{{ route('platform.tenants.domains.store', $company) }}" class="mt-3">
            @csrf

            <div class="row g-3">
                <div class="col-md-4">
                    <label for="domain_kind" class="form-label">{{ __('platform.domain_kind') }}</label>
                    <select id="domain_kind" name="kind" class="form-select form-select-sm">
                        <option value="SUBDOMAIN">{{ __('platform.domain_kind_subdomain') }}</option>
                        <option value="CUSTOM" @selected(old('kind') === 'CUSTOM')>
                            {{ __('platform.domain_kind_custom') }}
                        </option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="domain_host" class="form-label">{{ __('platform.domain_host') }}</label>
                    <input id="domain_host" name="host" type="text" required maxlength="255"
                           value="{{ old('host') }}" class="form-control form-control-sm"
                           placeholder="{{ strtolower($company->code) }}">
                    <div class="form-text">
                        {{ __('platform.domain_host_hint', [
                            'base' => config('tenancy.subdomain_host') ?: config('tenancy.platform_host'),
                        ]) }}
                    </div>
                </div>

            </div>

            {{-- On its own line at its natural width, like every other submit
                 in the platform area. Squeezed into a grid column with w-100
                 it stretched to whatever the column happened to be and sat
                 level with the hint text under the field beside it rather
                 than with the field. --}}
            <button class="btn btn-primary mt-3">{{ __('common.save') }}</button>
        </form>
    </details>
</section>
