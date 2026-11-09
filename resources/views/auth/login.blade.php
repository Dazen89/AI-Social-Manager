@extends('layouts.app')

@section('title', 'Accedi — AI Social Manager')

@section('content')
    <main class="ui-auth">
        <div class="ui-auth-stack">
            <x-brand />
            <section class="ui-panel ui-auth-panel" aria-labelledby="login-title">
                <p class="ui-kicker">Area riservata</p>
                <h1 id="login-title" class="ui-page-title">Accedi al servizio</h1>
                <p class="ui-page-description">L’area è riservata agli amministratori autorizzati.</p>

                <form method="POST" action="{{ route('login.store') }}">
                    @csrf

                    <div>
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                            @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                        @error('email')
                            <p id="email-error" class="mt-2 ui-error-text" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password">Password</label>
                        <input id="password" name="password" type="password" required autocomplete="current-password"
                            @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                        @error('password')
                            <p id="password-error" class="mt-2 ui-error-text" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="ui-remember">
                        <input name="remember" type="checkbox" class="size-4">
                        Ricordami su questo dispositivo
                    </label>

                    <button type="submit" class="ui-button ui-button-primary">Accedi</button>
                </form>
            </section>
        </div>
    </main>
@endsection
