@extends('layouts.workspace')

@section('title', 'Dashboard — AI Social Manager')

@section('page')
    <main id="main-content" class="ui-page" tabindex="-1">
        <x-page-header title="Dashboard" kicker="Area di monitoraggio" :description="'Benvenuto, '.auth()->user()->name.'. Registra i siti che vuoi monitorare e scegli quali servizi collegare.'">
            <x-slot:actions>
                <a href="{{ route('sites.create') }}" class="ui-button ui-button-primary"><x-icon name="plus" /> Aggiungi sito</a>
            </x-slot:actions>
        </x-page-header>

        <dl class="grid gap-4 sm:grid-cols-3">
            <x-kpi label="Siti registrati" icon="globe">{{ $siteCount }}</x-kpi>
            <x-kpi label="Google Analytics collegati" icon="chart">{{ $connectedAnalyticsCount }}</x-kpi>
            <x-kpi label="Integrazione AI" icon="ai">
                <span class="block text-lg">{{ $aiIntegration?->name ?? 'Da configurare' }}</span>
                @if ($aiIntegration)
                    <x-slot:note>{{ config('ai.providers.'.$aiIntegration->provider.'.name') }} · {{ config('ai.providers.'.$aiIntegration->provider.'.models.'.$aiIntegration->model, $aiIntegration->model) }}</x-slot:note>
                    <x-slot:status>
                        @php
                            [$tone, $label] = match ($aiIntegration->status) {
                                'verified' => ['success', 'Chiave e modello verificati'],
                                'error' => ['danger', 'Verifica non riuscita'],
                                default => ['warning', 'Da verificare'],
                            };
                        @endphp
                        <span class="ui-badge ui-tone-{{ $tone }}">{{ $label }}</span>
                    </x-slot:status>
                @endif
            </x-kpi>
        </dl>

        <section class="ui-panel mt-6" aria-labelledby="workspace-actions">
            <div class="ui-panel-heading">
                <h2 id="workspace-actions">Gestisci il workspace</h2>
            </div>
            <div class="grid divide-y divide-line md:grid-cols-3 md:divide-x md:divide-y-0">
                <div class="p-5">
                    <h3 class="font-semibold">Siti e report</h3>
                    <p class="mt-1 text-sm text-muted">Apri la panoramica di un sito, consulta i dati o gestisci i collegamenti Google.</p>
                    <a href="{{ route('sites.index') }}" class="mt-4 ui-button ui-button-small">Gestisci i siti <x-icon name="arrow" /></a>
                </div>
                <div class="p-5">
                    <h3 class="font-semibold">Analisi AI</h3>
                    <p class="mt-1 text-sm text-muted">Configura i provider e scegli l’integrazione attiva per le analisi.</p>
                    <a href="{{ route('settings.ai.index') }}" class="mt-4 ui-button ui-button-small">Gestisci integrazioni AI <x-icon name="arrow" /></a>
                </div>
                <div class="p-5">
                    <h3 class="font-semibold">Social</h3>
                    <p class="mt-1 text-sm text-muted">Consulta la configurazione dell’app X e lo stato dell’account collegato.</p>
                    <a href="{{ route('settings.x.edit') }}" class="mt-4 ui-button ui-button-small">Apri Social <x-icon name="arrow" /></a>
                </div>
            </div>
        </section>
    </main>
@endsection
