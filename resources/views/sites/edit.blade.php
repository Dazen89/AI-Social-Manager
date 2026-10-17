@extends('layouts.app')

@section('title', 'Gestisci '.$site->name.' — '.config('app.name', 'Monitoring Hub'))

@section('content')
    @include('partials.navigation')

    <main class="mx-auto max-w-5xl px-6 py-10">
        <div class="mb-8">
            <a href="{{ route('sites.index') }}" class="text-sm text-sky-400">← Torna ai siti</a>
            <h1 class="mt-4 text-3xl font-semibold">Gestisci {{ $site->name }}</h1>
            <p class="mt-2 text-slate-400">Aggiorna i dati del sito e scegli i servizi da collegare.</p>
        </div>

        @if (session('status'))
            <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200" role="status">{{ session('status') }}</div>
        @endif

        @if (session('integration_error'))
            <div class="mb-6 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200" role="alert">{{ session('integration_error') }}</div>
        @endif

        <section class="rounded-3xl border border-slate-800 bg-slate-900 p-8" aria-labelledby="site-details-heading">
            <div class="mb-8">
                <h2 id="site-details-heading" class="text-xl font-semibold">Dati del sito</h2>
                <p class="mt-2 text-sm text-slate-400">Queste informazioni identificano il sito all’interno della dashboard.</p>
            </div>

            <form method="POST" action="{{ route('sites.update', $site) }}" class="space-y-8">
                @csrf
                @method('PUT')
                @include('sites.partials.form', ['submitLabel' => 'Salva modifiche'])
            </form>
        </section>

        <section class="mt-8 rounded-3xl border border-slate-800 bg-slate-900 p-8" aria-labelledby="integrations-heading">
            <div class="mb-6">
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-sky-400">Passaggio successivo</p>
                <h2 id="integrations-heading" class="mt-2 text-xl font-semibold">Servizi e integrazioni</h2>
                <p class="mt-2 text-sm leading-6 text-slate-400">Collega soltanto i servizi che utilizzi. Ogni integrazione mostrerà il proprio stato e le eventuali azioni richieste.</p>
            </div>

            <article class="rounded-2xl border border-slate-700 bg-slate-950/50 p-6">
                @php
                    $analyticsProperty = $site->googleAnalyticsProperty;
                    $analyticsConnection = $analyticsProperty?->connection;
                    $analyticsIsConnected = $analyticsConnection?->status === 'active';
                @endphp

                <div class="flex flex-wrap items-start justify-between gap-5">
                    <div class="max-w-2xl">
                        <div class="flex flex-wrap items-center gap-3">
                            <h3 class="text-lg font-semibold">Google Analytics 4</h3>
                            @if ($analyticsIsConnected)
                                <span class="rounded-full bg-emerald-400/10 px-2.5 py-1 text-xs font-medium text-emerald-300">Collegato</span>
                            @elseif ($analyticsProperty)
                                <span class="rounded-full bg-amber-400/10 px-2.5 py-1 text-xs font-medium text-amber-300">Configurazione precedente</span>
                            @else
                                <span class="rounded-full bg-slate-700 px-2.5 py-1 text-xs font-medium text-slate-300">Non collegato</span>
                            @endif
                        </div>

                        <p class="mt-3 text-sm leading-6 text-slate-400">Il collegamento usa l’accesso Google in sola lettura. Dopo l’autorizzazione puoi scegliere account e proprietà da un elenco verificato, senza copiare manualmente gli identificativi.</p>

                        @if ($analyticsIsConnected)
                            <dl class="mt-4 grid gap-3 rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-4 text-sm sm:grid-cols-2">
                                <div>
                                    <dt class="text-slate-500">Account</dt>
                                    <dd class="mt-1 font-medium text-slate-200">{{ $analyticsProperty->account_name ?: $analyticsProperty->account_id }}</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500">Proprietà</dt>
                                    <dd class="mt-1 font-medium text-slate-200">{{ $analyticsProperty->property_name ?: $analyticsProperty->property_id }}</dd>
                                </div>
                                @if ($analyticsProperty->measurement_id)
                                    <div>
                                        <dt class="text-slate-500">Measurement ID</dt>
                                        <dd class="mt-1 font-medium text-slate-200">{{ $analyticsProperty->measurement_id }}</dd>
                                    </div>
                                @endif
                                <div>
                                    <dt class="text-slate-500">Ultima verifica</dt>
                                    <dd class="mt-1 font-medium text-slate-200">{{ $analyticsProperty->last_verified_at?->format('d/m/Y H:i') }}</dd>
                                </div>
                            </dl>
                        @elseif ($analyticsProperty)
                            <p class="mt-3 rounded-xl border border-amber-500/20 bg-amber-500/10 px-4 py-3 text-sm leading-6 text-amber-200">Sono presenti identificativi salvati con il flusso precedente. Restano conservati, ma non rappresentano ancora una connessione verificata con Google.</p>
                        @endif
                    </div>

                    <div class="flex min-w-56 flex-col items-stretch gap-3">
                        @if ($analyticsIsConnected)
                            <a href="{{ route('sites.show', $site) }}" class="rounded-xl bg-emerald-400 px-5 py-3 text-center font-semibold text-emerald-950 no-underline hover:bg-emerald-300">Apri panoramica dati</a>
                        @endif

                        @if ($googleAnalyticsOAuthConfigured)
                            <a href="{{ route('google-analytics.connect', $site) }}" class="rounded-xl bg-sky-500 px-5 py-3 text-center font-semibold text-slate-950 no-underline hover:bg-sky-400">{{ $analyticsIsConnected ? 'Cambia account o proprietà' : 'Collega account Google' }}</a>
                        @else
                            <a href="{{ route('settings.google-analytics.edit', ['site' => $site->id]) }}" class="rounded-xl bg-amber-400 px-5 py-3 text-center font-semibold text-slate-950 no-underline hover:bg-amber-300">Configura Google Analytics</a>
                        @endif

                        @if ($analyticsProperty)
                            <form method="POST" action="{{ route('google-analytics.destroy', $site) }}" onsubmit="return confirm('Vuoi scollegare Google Analytics da questo sito?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-full border border-rose-500/30 bg-transparent text-rose-300 hover:bg-rose-500/10">Scollega</button>
                            </form>
                        @endif
                    </div>
                </div>

                @unless ($googleAnalyticsOAuthConfigured)
                    <div class="mt-6 rounded-xl border border-amber-500/20 bg-amber-500/10 p-4 text-sm leading-6 text-amber-100">
                        <p class="font-semibold">Prima configura l’accesso Google dell’installazione</p>
                        <p class="mt-2">La procedura guidata spiega quali credenziali creare in Google Cloud, dove trovarle e come salvarle in sicurezza dalla dashboard. È un’operazione unica per tutta l’installazione.</p>
                        <a href="{{ route('settings.google-analytics.edit', ['site' => $site->id]) }}" class="mt-3 inline-flex font-medium text-amber-200 underline underline-offset-4">Apri la procedura guidata</a>
                    </div>
                @endunless
            </article>

            <article class="mt-6 rounded-2xl border border-slate-700 bg-slate-950/50 p-6">
                @php
                    $searchConsoleProperty = $site->googleSearchConsoleProperty;
                    $searchConsoleConnection = $searchConsoleProperty?->connection;
                    $searchConsoleIsConnected = $searchConsoleConnection?->status === 'active';
                @endphp

                <div class="flex flex-wrap items-start justify-between gap-5">
                    <div class="max-w-2xl">
                        <div class="flex flex-wrap items-center gap-3">
                            <h3 class="text-lg font-semibold">Google Search Console</h3>
                            @if ($searchConsoleIsConnected)
                                <span class="rounded-full bg-emerald-400/10 px-2.5 py-1 text-xs font-medium text-emerald-300">Collegata</span>
                            @else
                                <span class="rounded-full bg-slate-700 px-2.5 py-1 text-xs font-medium text-slate-300">Non collegata</span>
                            @endif
                        </div>

                        <p class="mt-3 text-sm leading-6 text-slate-400">Aggiunge query, impressioni, clic, CTR e posizione media nei risultati Google. Il collegamento usa soltanto il permesso Search Console di lettura e non può modificare il sito.</p>

                        @if ($searchConsoleIsConnected)
                            <dl class="mt-4 grid gap-3 rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-4 text-sm sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <dt class="text-slate-500">Proprietà</dt>
                                    <dd class="mt-1 break-all font-medium text-slate-200">{{ $searchConsoleProperty->property_url }}</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500">Tipo</dt>
                                    <dd class="mt-1 font-medium text-slate-200">{{ $searchConsoleProperty->property_type === 'domain' ? 'Dominio' : 'Prefisso URL' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500">Ultima verifica</dt>
                                    <dd class="mt-1 font-medium text-slate-200">{{ $searchConsoleProperty->last_verified_at?->format('d/m/Y H:i') }}</dd>
                                </div>
                            </dl>
                        @endif
                    </div>

                    <div class="flex min-w-56 flex-col items-stretch gap-3">
                        @if ($googleSearchConsoleOAuthConfigured)
                            <a href="{{ route('google-search-console.connect', $site) }}" class="rounded-xl bg-sky-500 px-5 py-3 text-center font-semibold text-slate-950 no-underline hover:bg-sky-400">{{ $searchConsoleIsConnected ? 'Cambia account o proprietà' : 'Collega Search Console' }}</a>
                        @else
                            <a href="{{ route('settings.google-search-console.edit', ['site' => $site->id]) }}" class="rounded-xl bg-amber-400 px-5 py-3 text-center font-semibold text-slate-950 no-underline hover:bg-amber-300">Configura Search Console</a>
                        @endif

                        @if ($searchConsoleProperty)
                            <form method="POST" action="{{ route('google-search-console.destroy', $site) }}" onsubmit="return confirm('Vuoi scollegare Google Search Console da questo sito?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-full border border-rose-500/30 bg-transparent text-rose-300 hover:bg-rose-500/10">Scollega</button>
                            </form>
                        @endif
                    </div>
                </div>

                @unless ($googleSearchConsoleOAuthConfigured)
                    <div class="mt-6 rounded-xl border border-amber-500/20 bg-amber-500/10 p-4 text-sm leading-6 text-amber-100">
                        <p class="font-semibold">Prima abilita l’accesso Search Console dell’installazione</p>
                        <p class="mt-2">La guida mostra come verificare il sito in Search Console, abilitare l’API, aggiungere il permesso di sola lettura e configurare il callback. Se hai già Google Analytics, puoi riutilizzare lo stesso progetto e client OAuth.</p>
                        <a href="{{ route('settings.google-search-console.edit', ['site' => $site->id]) }}" class="mt-3 inline-flex font-medium text-amber-200 underline underline-offset-4">Apri la procedura guidata</a>
                    </div>
                @endunless
            </article>
        </section>
    </main>
@endsection
