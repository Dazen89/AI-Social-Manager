@extends('layouts.workspace')

@section('title', 'Siti — '.config('app.name', 'Monitoring Hub'))

@section('page')
    <main id="main-content" class="ui-page" tabindex="-1">
        <x-page-header title="Siti" kicker="Monitoraggio" description="Registra i siti autorizzati e scegli per ciascuno quali servizi collegare.">
            <x-slot:actions><a href="{{ route('sites.create') }}" class="ui-button ui-button-primary"><x-icon name="plus" /> Aggiungi sito</a></x-slot:actions>
        </x-page-header>

        @if (session('status'))
            <div class="mb-6 ui-alert ui-tone-success" role="status">{{ session('status') }}</div>
        @endif

        @if (session('integration_error'))
            <div class="mb-6 ui-alert ui-tone-danger" role="alert">{{ session('integration_error') }}</div>
        @endif

        @if ($sites->isEmpty())
            <section class="ui-empty">
                <div class="mx-auto mb-3 grid size-10 place-items-center rounded-lg bg-accent-soft text-accent-ink"><x-icon name="globe" /></div>
                <h2>Nessun sito registrato</h2>
                <p class="mt-2 text-muted">Aggiungi il primo sito che possiedi o sei autorizzato a monitorare.</p>
            </section>
        @else
            <section class="ui-panel" aria-labelledby="sites-heading">
                <div class="ui-panel-heading ui-actions"><h2 id="sites-heading">Siti registrati</h2> <span class="ui-badge">{{ $sites->total() }}</span></div>
                @foreach ($sites as $site)
                    <article class="ui-site-row">
                        <div>
                            <div class="ui-actions">
                                <h3 class="font-semibold">
                                    <a href="{{ route('sites.show', $site) }}" class="text-ink no-underline hover:text-accent-ink">{{ $site->name }}</a>
                                </h3>
                                <span class="ui-badge {{ $site->is_active ? 'ui-tone-success' : '' }}">{{ $site->is_active ? 'Attivo' : 'Sospeso' }}</span>
                            </div>
                            <a href="{{ $site->base_url }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-block text-sm text-muted">{{ $site->host }} ↗</a>
                        </div>

                        <div class="space-y-1.5">
                            @if ($site->googleAnalyticsProperty)
                                @if ($site->googleAnalyticsProperty->connection?->status === 'active')
                                    <p class="text-sm font-medium text-success-ink">Google Analytics collegato: {{ $site->googleAnalyticsProperty->property_name ?: $site->googleAnalyticsProperty->property_id }}</p>
                                @else
                                    <p class="text-sm font-medium text-warning-ink">Google Analytics: configurazione precedente non verificata</p>
                                @endif
                            @else
                                <p class="text-sm text-muted">Google Analytics non collegato.</p>
                            @endif

                            @if ($site->googleSearchConsoleProperty?->connection?->status === 'active')
                                <p class="text-sm font-medium text-success-ink">Search Console collegata: {{ $site->googleSearchConsoleProperty->property_url }}</p>
                            @else
                                <p class="text-sm text-muted">Google Search Console non collegata.</p>
                            @endif
                        </div>

                        <div class="ui-actions">
                            <a href="{{ route('sites.show', $site) }}" class="ui-button ui-button-small">Apri panoramica <x-icon name="arrow" /></a>
                            <a href="{{ route('sites.edit', $site) }}" class="ui-button ui-button-small">Gestisci</a>
                        </div>
                    </article>
                @endforeach
            </section>

            <div class="mt-6">{{ $sites->links() }}</div>
        @endif
    </main>
@endsection
