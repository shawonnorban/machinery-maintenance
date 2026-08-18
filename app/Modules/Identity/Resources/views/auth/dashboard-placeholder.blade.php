{{-- Placeholder until the dashboard module lands (build order step 26). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Dashboard') }} — {{ config('app.name') }}</title>
</head>
<body>
    <h1>{{ __('Dashboard') }}</h1>

    @if (session('status'))
        <p role="status">{{ session('status') }}</p>
    @endif

    <p>{{ auth()->user()->name }} ({{ auth()->user()->email }})</p>

    @php($context = app(\App\Shared\Tenancy\TenantContext::class))
    <p data-testid="company-id">{{ $context->companyIdOrNull() }}</p>
    <p data-testid="factory-count">{{ count($context->accessibleFactoryIds()) }}</p>

    @can('asset.asset.create')
        <button data-testid="create-asset">{{ __('Create asset') }}</button>
    @endcan

    @can('billing.subscription.manage')
        <button data-testid="manage-billing">{{ __('Manage billing') }}</button>
    @endcan

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">{{ __('Sign out') }}</button>
    </form>
</body>
</html>
