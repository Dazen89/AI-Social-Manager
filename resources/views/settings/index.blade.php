@extends('layouts.workspace')

@section('title', 'Impostazioni — '.config('app.name', 'Monitoring Hub'))

@section('page')
    <main id="main-content" class="ui-page ui-page-narrow" tabindex="-1">
        <x-page-header title="Impostazioni" kicker="Installazione" description="Consulta la configurazione generale dell’applicazione." />

        <section class="ui-panel" aria-labelledby="installation-heading">
            <div class="ui-panel-heading">
                <h2 id="installation-heading">Informazioni sull’installazione</h2>
                <p class="mt-1 text-sm text-muted">Questi valori provengono dalla configurazione del server.</p>
            </div>
            <dl class="grid gap-5 p-5 sm:grid-cols-3">
                <div>
                    <dt class="text-sm text-muted">Nome applicazione</dt>
                    <dd class="mt-1 font-semibold">{{ config('app.name') }}</dd>
                </div>
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
