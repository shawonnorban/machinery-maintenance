@props(['companies', 'factories'])

<header class="header header-sticky mb-4 border-bottom">
    <div class="container-fluid px-4">
        <button class="header-toggler d-md-none" type="button" data-sidebar-toggle
                aria-label="{{ __('common.toggle_navigation') }}">
            <span class="navbar-toggler-icon"></span>
        </button>

        {{-- Global scope control, not a per-page filter. Set once, respected
             by every list (Frontend 4.2). --}}
        @if ($factories->isNotEmpty())
            <form method="POST" action="{{ route('app.factory-scope') }}" class="d-none d-lg-block ms-3">
                @csrf
                <select name="factory_id" class="form-select form-select-sm"
                        aria-label="{{ __('common.factory_scope') }}"
                        onchange="this.form.submit()">
                    <option value="">{{ __('common.all_factories') }}</option>
                    @foreach ($factories as $factory)
                        <option value="{{ $factory->id }}" @selected($scopedFactoryId === $factory->id)>
                            {{ $factory->name }}
                        </option>
                    @endforeach
                </select>
            </form>
        @endif

        <ul class="header-nav ms-auto align-items-center">
            {{-- A technician must know their screen is stale (Frontend 8 rule 3). --}}
            <li class="nav-item d-none d-sm-block">
                <span class="connection-indicator" data-state="live" id="connection-state">
                    ● {{ __('common.connection_live') }}
                </span>
            </li>

            <li class="nav-item dropdown">
                <a class="nav-link" href="#" data-coreui-toggle="dropdown" role="button" aria-expanded="false">
                    {{ strtoupper(app()->getLocale()) }}
                </a>
                <div class="dropdown-menu dropdown-menu-end">
                    @foreach (['en' => 'English', 'bn' => 'বাংলা'] as $code => $name)
                        <form method="POST" action="{{ route('app.locale') }}">
                            @csrf
                            <input type="hidden" name="locale" value="{{ $code }}">
                            <button type="submit" class="dropdown-item @if(app()->getLocale() === $code) active @endif">
                                {{ $name }}
                            </button>
                        </form>
                    @endforeach
                </div>
            </li>

            @if ($companies->count() > 1)
                <li class="nav-item dropdown">
                    <a class="nav-link" href="#" data-coreui-toggle="dropdown" role="button" aria-expanded="false">
                        {{ Str::limit($companies->firstWhere('id', $tenant->companyIdOrNull())?->name ?? '', 18) }}
                    </a>
                    <div class="dropdown-menu dropdown-menu-end">
                        <h6 class="dropdown-header">{{ __('common.switch_company') }}</h6>
                        @foreach ($companies as $company)
                            <form method="POST" action="{{ route('app.switch-company') }}">
                                @csrf
                                <input type="hidden" name="company_id" value="{{ $company->id }}">
                                <button type="submit" class="dropdown-item @if($tenant->companyIdOrNull() === $company->id) active @endif">
                                    {{ $company->name }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                </li>
            @endif

            <li class="nav-item dropdown">
                <a class="nav-link py-0" href="#" data-coreui-toggle="dropdown" role="button" aria-expanded="false">
                    <span class="fw-semibold">{{ auth()->user()->name }}</span>
                </a>
                <div class="dropdown-menu dropdown-menu-end pt-0">
                    <div class="dropdown-header bg-body-secondary fw-semibold py-2">
                        {{ auth()->user()->email }}
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item">{{ __('common.sign_out') }}</button>
                    </form>
                </div>
            </li>
        </ul>
    </div>
</header>
