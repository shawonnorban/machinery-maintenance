{{-- Placeholder markup. Replaced by the CoreUI login layout in build order
     step 2 (Frontend 5.1). Kept minimal so the auth flow is testable now. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Sign in') }} — {{ config('app.name') }}</title>
</head>
<body>
    <main>
        <h1>{{ config('app.name') }}</h1>

        @if (session('status'))
            <p role="status">{{ session('status') }}</p>
        @endif

        @if ($errors->any())
            <ul role="alert">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <label for="email">{{ __('Email') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>

            <label for="password">{{ __('Password') }}</label>
            <input id="password" name="password" type="password" required>

            <label><input type="checkbox" name="remember" value="1"> {{ __('Remember me') }}</label>

            <button type="submit">{{ __('Sign in') }}</button>
        </form>
    </main>
</body>
</html>
