@extends('layouts.workspace')

@section('title', 'Utenti — '.config('app.name', 'Monitoring Hub'))

@section('page')
    <main id="main-content" class="ui-page" tabindex="-1">
        <x-page-header title="Utenti" kicker="Installazione" description="Crea gli account degli amministratori e gestisci il loro accesso al workspace.">
            <x-slot:actions><a href="{{ route('users.create') }}" class="ui-button ui-button-primary"><x-icon name="plus" /> Aggiungi amministratore</a></x-slot:actions>
        </x-page-header>

        @if (session('status'))
            <p class="mb-6 ui-alert ui-tone-success" role="status">{{ session('status') }}</p>
        @endif

        <section class="ui-panel" aria-labelledby="users-heading">
            <div class="ui-panel-heading ui-actions">
                <h2 id="users-heading">Account</h2>
                <span class="ui-badge">{{ $accounts->total() }}</span>
            </div>

            @foreach ($accounts as $account)
                @php($isCurrent = $account->is(auth()->user()))
                <article class="ui-site-row">
                    <div class="min-w-0">
                        <h3 class="break-words font-semibold">{{ $account->name }} @if ($isCurrent)<span class="text-xs text-muted">(tu)</span>@endif</h3>
                        <p class="mt-1 break-all text-sm text-muted">{{ $account->email }}</p>
                    </div>
                    <div>
                        <span class="ui-badge {{ $account->is_admin ? 'ui-tone-success' : 'ui-tone-warning' }}">{{ $account->is_admin ? 'Amministratore' : 'Accesso sospeso' }}</span>
                    </div>
                    <div class="ui-actions">
                        <a href="{{ route('users.edit', $account) }}" class="ui-button ui-button-small" aria-label="Modifica {{ $account->name }}">Modifica</a>
                        @unless ($isCurrent)
                            <form method="POST" action="{{ route('users.access.update', $account) }}" data-confirm="{{ $account->is_admin ? 'Vuoi sospendere l’accesso di '.$account->name.'?' : 'Vuoi riattivare l’accesso amministratore di '.$account->name.'?' }}" onsubmit="return confirm(this.dataset.confirm)">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="enabled" value="{{ $account->is_admin ? '0' : '1' }}">
                                <button type="submit" class="ui-button ui-button-small {{ $account->is_admin ? 'ui-button-danger' : '' }}">{{ $account->is_admin ? 'Sospendi accesso' : 'Riattiva accesso' }}</button>
                            </form>
                        @endunless
                    </div>
                </article>
            @endforeach
        </section>

        <div class="mt-6">{{ $accounts->links() }}</div>
    </main>
@endsection
