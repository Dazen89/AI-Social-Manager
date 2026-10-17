@extends('layouts.app')

@section('title', 'Dashboard — '.config('app.name', 'Monitoring Hub'))

@section('content')
    @include('partials.navigation')

    <main class="mx-auto max-w-6xl px-6 py-12">
        <section class="rounded-3xl border border-slate-800 bg-slate-900 p-8">
            <span class="inline-flex rounded-full bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-300">Area di monitoraggio</span>
            <h1 class="mt-5 text-3xl font-semibold tracking-tight">Benvenuto, {{ auth()->user()->name }}.</h1>
            <p class="mt-3 max-w-2xl leading-7 text-slate-400">
                Registra i siti che vuoi monitorare, quindi scegli per ciascuno i servizi da collegare.
            </p>

            <div class="mt-8 grid gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-slate-800 bg-slate-950/50 p-5">
                    <p class="text-sm text-slate-400">Siti registrati</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-100">{{ $siteCount }}</p>
                </div>
                <div class="rounded-2xl border border-slate-800 bg-slate-950/50 p-5">
                    <p class="text-sm text-slate-400">Google Analytics collegati</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-100">{{ $connectedAnalyticsCount }}</p>
                </div>
                <div class="rounded-2xl border border-slate-800 bg-slate-950/50 p-5">
                    <p class="text-sm text-slate-400">Integrazione AI</p>
                    @if ($aiIntegration)
                        <p class="mt-2 text-lg font-semibold text-slate-100">{{ $aiIntegration->name }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ config('ai.providers.'.$aiIntegration->provider.'.name') }} · {{ config('ai.providers.'.$aiIntegration->provider.'.models.'.$aiIntegration->model, $aiIntegration->model) }}</p>
                        <p class="mt-2 text-xs font-medium {{ $aiIntegration->status === 'verified' ? 'text-emerald-300' : ($aiIntegration->status === 'error' ? 'text-rose-300' : 'text-amber-300') }}">
                            {{ $aiIntegration->status === 'verified' ? 'Chiave e modello verificati' : ($aiIntegration->status === 'error' ? 'Verifica non riuscita' : 'Da verificare') }}
                        </p>
                    @else
                        <p class="mt-2 text-lg font-semibold text-amber-300">Da configurare</p>
                    @endif
                </div>
            </div>

            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('sites.index') }}" class="inline-flex rounded-xl bg-sky-500 px-5 py-3 font-semibold text-slate-950 no-underline transition hover:bg-sky-400">
                    Gestisci i siti
                </a>
                <a href="{{ route('settings.ai.index') }}" class="inline-flex rounded-xl border border-violet-400/40 px-5 py-3 font-semibold text-violet-200 no-underline transition hover:bg-violet-400/10">
                    Gestisci integrazioni AI
                </a>
            </div>
        </section>
    </main>
@endsection
