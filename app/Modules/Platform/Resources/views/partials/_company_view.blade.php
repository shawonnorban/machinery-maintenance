{{-- Who this customer is, read rather than filled in.

     This tab used to be the edit form itself, which meant the answer to "what
     is this customer's phone number" was a row of input boxes — every fact
     sitting in a control that invited changing it, and no way to look
     something up without appearing to be halfway through editing it. Reading
     is the common errand and editing is the rare one, so reading is the page
     and editing is a button on it. --}}
<section class="panel panel-wide" id="details">
    <header class="panel-head">
        <i class="cil-building" aria-hidden="true"></i>
        <span>{{ __('platform.details') }}</span>

        <a href="{{ route('platform.tenants.edit', $company) }}"
           class="btn btn-sm btn-outline-primary ms-auto">
            <i class="cil-pencil" aria-hidden="true"></i> {{ __('common.edit') }}
        </a>
    </header>

    <div class="panel-body">
        <div class="company-profile">
            <div class="tenant-logo tenant-logo-lg">
                @if ($company->logoUrl())
                    <img src="{{ $company->logoUrl() }}" alt="{{ $company->name }}">
                @else
                    {{-- No logo is a fact worth showing plainly rather than
                         filling with a monogram that looks like one. --}}
                    <span class="tenant-logo-empty">{{ __('platform.no_logo') }}</span>
                @endif
            </div>

            <div class="min-w-0 flex-grow-1">
                <div class="company-profile-name">{{ $company->name }}</div>

                @if ($company->legal_name)
                    <div class="text-body-secondary">{{ $company->legal_name }}</div>
                @endif

                <div class="tenant-code mt-1">{{ $company->code }}</div>

                <div class="field-grid mt-3">
                    <div>
                        <span class="field-label">{{ __('platform.company_email') }}</span>
                        <span class="field-value">
                            @if ($company->email)
                                <a href="mailto:{{ $company->email }}">{{ $company->email }}</a>
                            @else
                                <span class="text-body-secondary">{{ __('platform.not_recorded') }}</span>
                            @endif
                        </span>
                    </div>

                    <div>
                        <span class="field-label">{{ __('platform.company_phone') }}</span>
                        <span class="field-value">
                            {{ $company->phone ?? __('platform.not_recorded') }}
                        </span>
                    </div>

                    <div>
                        <span class="field-label">{{ __('platform.company_country') }}</span>
                        <span class="field-value">
                            {{ $company->country ?? __('platform.not_recorded') }}
                        </span>
                    </div>

                    <div>
                        <span class="field-label">{{ __('platform.customer_since') }}</span>
                        <span class="field-value">{{ $company->created_at?->toDateString() ?? '—' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($company->address)
        <div class="panel-body panel-divided">
            <span class="field-label">{{ __('platform.company_address') }}</span>
            <div class="field-value" style="white-space: pre-line">{{ $company->address }}</div>
        </div>
    @endif

    <div class="panel-body panel-divided">
        <div class="field-grid">
            <div>
                <span class="field-label">{{ __('platform.currency') }}</span>
                <span class="field-value">{{ $company->base_currency }}</span>
            </div>
            <div>
                <span class="field-label">{{ __('platform.timezone') }}</span>
                <span class="field-value">{{ $company->timezone }}</span>
            </div>
            <div>
                <span class="field-label">{{ __('platform.locale') }}</span>
                <span class="field-value">
                    {{ $company->default_locale === 'bn' ? 'বাংলা' : 'English' }}
                </span>
            </div>
            <div>
                <span class="field-label">{{ __('platform.figure_active') }}</span>
                <span class="field-value">
                    {{ __('platform.company_'.strtolower($company->status)) }}
                </span>
            </div>
        </div>
    </div>

    {{-- Counts, not contents. How many machines is a size; which machines is
         the customer's data, and seeing that needs a support grant (SRS §5). --}}
    <div class="panel-body panel-divided">
        <div class="company-counts">
            <div>
                <span class="panel-figure">{{ $factoryCount }}</span>
                <span class="field-label">{{ __('platform.factories') }}</span>
            </div>
            <div>
                <span class="panel-figure">{{ $assetCount }}</span>
                <span class="field-label">{{ __('platform.assets') }}</span>
            </div>
            <div>
                <span class="panel-figure">{{ $userCount }}</span>
                <span class="field-label">{{ __('platform.users') }}</span>
            </div>
        </div>
    </div>
</section>
