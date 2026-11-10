@extends('layouts.workspace')

@php($pageTitle = $account->exists ? 'Modifica utente' : 'Aggiungi amministratore')

@section('title', $pageTitle.' — '.config('app.name', 'Monitoring Hub'))

@section('page')
    <main id="main-content" class="ui-page ui-page-narrow" tabindex="-1">
        <x-page-header :title="$pageTitle" :description="$account->exists ? 'Aggiorna i dati di accesso di questo account.' : 'Il nuovo amministratore avrà accesso a tutto il workspace.'">
            <x-slot:back><a href="{{ route('users.index') }}">← Torna agli utenti</a></x-slot:back>
        </x-page-header>

        <section class="ui-panel p-5 sm:p-7">
            <form method="POST" action="{{ $account->exists ? route('users.update', $account) : route('users.store') }}" class="space-y-5">
                @csrf
                @if ($account->exists) @method('PATCH') @endif

                <div>
                    <label for="name" class="mb-2 block text-sm font-medium text-soft">Nome</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $account->name) }}" autocomplete="name" maxlength="255" required>
                    @error('name')<p class="mt-2 text-sm text-danger-ink" role="alert">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="email" class="mb-2 block text-sm font-medium text-soft">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $account->email) }}" autocomplete="email" maxlength="255" required>
                    @error('email')<p class="mt-2 text-sm text-danger-ink" role="alert">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="password" class="mb-2 block text-sm font-medium text-soft">{{ $account->exists ? 'Nuova password' : 'Password' }}</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" aria-describedby="password-help" @required(! $account->exists)>
                    <p id="password-help" class="mt-2 text-sm text-muted">Usa almeno 12 caratteri. @if ($account->exists) Lascia vuoto per conservare la password attuale. @endif</p>
                    @error('password')<p class="mt-2 text-sm text-danger-ink" role="alert">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="password_confirmation" class="mb-2 block text-sm font-medium text-soft">Conferma password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" @required(! $account->exists)>
                </div>

                <div class="ui-actions border-t border-line pt-5">
                    <button type="submit" class="ui-button ui-button-primary">{{ $account->exists ? 'Salva modifiche' : 'Crea amministratore' }}</button>
                    <a href="{{ route('users.index') }}" class="ui-button">Annulla</a>
                </div>
            </form>
        </section>
    </main>
@endsection
