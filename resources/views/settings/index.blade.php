@extends('layouts.workspace')

@section('title', 'Impostazioni — '.config('app.name', 'Monitoring Hub'))

@section('page')
    <main id="main-content" class="ui-page ui-page-narrow" tabindex="-1">
        <x-page-header title="Impostazioni" kicker="Installazione" description="Personalizza il nome e consulta i dati generali dell’applicazione." />

        @if (session('status'))
            <p class="mb-6 ui-alert ui-tone-success" role="status">{{ session('status') }}</p>
        @endif

        <section class="ui-panel mb-6" aria-labelledby="app-name-heading">
            <div class="ui-panel-heading">
                <h2 id="app-name-heading">Identità dell’applicazione</h2>
            </div>
            <form method="POST" action="{{ route('settings.update') }}" class="space-y-4 p-5">
                @csrf
                @method('PATCH')
                <div>
                    <label for="app_name" class="mb-2 block text-sm font-medium text-soft">Nome applicazione</label>
                    <input id="app_name" name="app_name" type="text" value="{{ old('app_name', config('app.name')) }}" maxlength="80" required
                        aria-describedby="app-name-help{{ $errors->has('app_name') ? ' app-name-error' : '' }}" @error('app_name') aria-invalid="true" @enderror>
                    <p id="app-name-help" class="mt-2 text-sm text-muted">Compare nella barra laterale, nella pagina di accesso e nei titoli delle pagine.</p>
                    @error('app_name')<p id="app-name-error" class="mt-2 text-sm text-danger-ink" role="alert">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="ui-button ui-button-primary">Salva nome</button>
            </form>
        </section>

        <section class="ui-panel" aria-labelledby="installation-heading">
            <div class="ui-panel-heading">
                <h2 id="installation-heading">Informazioni sull’installazione</h2>
                <p class="mt-1 text-sm text-muted">Questi valori provengono dalla configurazione del server.</p>
            </div>
            <dl class="grid gap-5 p-5 sm:grid-cols-2">
                <div>
                    <dt class="text-sm text-muted">Indirizzo applicazione</dt>
                    <dd class="mt-1 font-semibold">{{ config('app.url') }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-muted">Fuso orario dell’applicazione</dt>
                    <dd class="mt-1 font-semibold">{{ config('app.timezone') }}</dd>
                </div>
            </dl>
        </section>
    </main>
@endsection
