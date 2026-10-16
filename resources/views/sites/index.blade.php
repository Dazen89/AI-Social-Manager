@extends('layouts.app')

@section('title', 'Siti — '.config('app.name', 'Monitoring Hub'))

@section('content')
    @include('partials.navigation')

    <main class="mx-auto max-w-6xl px-6 py-10">
        <div class="mb-8 flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-sky-400">Monitoraggio</p>
                <h1 class="mt-2 text-3xl font-semibold">Siti</h1>
                <p class="mt-2 text-slate-400">Registra i siti autorizzati e scegli per ciascuno quali servizi collegare.</p>
            </div>

            <a href="{{ route('sites.create') }}" class="inline-flex rounded-xl bg-sky-500 px-5 py-3 font-semibold text-slate-950 no-underline transition hover:bg-sky-400">Aggiungi sito</a>
        </div>

        @if (session('status'))
            <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200" role="status">{{ session('status') }}</div>
        @endif

        @if (session('integration_error'))
            <div class="mb-6 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200" role="alert">{{ session('integration_error') }}</div>
        @endif

        @if ($sites->isEmpty())
            <section class="rounded-3xl border border-dashed border-slate-700 bg-slate-900/50 p-10 text-center">
                <h2 class="text-xl font-semibold">Nessun sito registrato</h2>
                <p class="mt-2 text-slate-400">Aggiungi il primo sito che possiedi o sei autorizzato a monitorare.</p>
            </section>
        @else
            <div class="grid gap-4">
                @foreach ($sites as $site)
                    <article class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <h2 class="text-xl font-semibold">{{ $site->name }}</h2>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $site->is_active ? 'bg-emerald-400/10 text-emerald-300' : 'bg-slate-700 text-slate-300' }}">{{ $site->is_active ? 'Attivo' : 'Sospeso' }}</span>
                                </div>
                                <a href="{{ $site->base_url }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-block text-sm text-sky-400">{{ $site->host }}</a>
                            </div>

                            <a href="{{ route('sites.edit', $site) }}" class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-medium text-slate-200 no-underline hover:border-slate-500">Gestisci</a>
                        </div>

                        <div class="mt-5 border-t border-slate-800 pt-5">
                            @if ($site->googleAnalyticsProperty)
                                @if ($site->googleAnalyticsProperty->connection?->status === 'active')
                                    <p class="text-sm font-medium text-emerald-300">Google Analytics collegato: {{ $site->googleAnalyticsProperty->property_name ?: $site->googleAnalyticsProperty->property_id }}</p>
                                @else
                                    <p class="text-sm font-medium text-amber-300">Google Analytics: configurazione precedente non verificata</p>
                                @endif
                            @else
                                <p class="text-sm text-slate-400">Nessun servizio collegato.</p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-8">{{ $sites->links() }}</div>
        @endif
    </main>
@endsection
