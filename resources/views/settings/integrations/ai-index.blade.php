@extends('layouts.app')

@section('title', 'Integrazioni AI — '.config('app.name', 'Monitoring Hub'))

@section('content')
    @include('partials.navigation')

    <main class="mx-auto max-w-6xl px-6 py-10">
        <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-violet-300">Impostazioni dell’installazione</p>
                <h1 class="mt-2 text-3xl font-semibold">Integrazioni AI</h1>
                <p class="mt-3 max-w-3xl leading-7 text-slate-400">
                    Conserva più collegamenti AI e scegli quale usare nell’applicazione. Una sola integrazione può essere attiva alla volta.
                </p>
            </div>
            <a href="{{ route('settings.ai.create') }}" class="inline-flex shrink-0 justify-center rounded-xl bg-violet-400 px-5 py-3 font-semibold text-slate-950 no-underline transition hover:bg-violet-300">
                Nuova integrazione
            </a>
        </div>

        @if (session('status'))
            <div class="mt-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200" role="status">{{ session('status') }}</div>
        @endif

        @if (session('integration_error'))
            <div class="mt-6 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200" role="alert">{{ session('integration_error') }}</div>
        @endif

        <section class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-5 text-sm leading-6 text-slate-400">
            <h2 class="font-semibold text-slate-200">Consumi e costi</h2>
            <p class="mt-2">L’app registra richieste e token restituiti dai provider, senza salvare prompt o credenziali nel registro d’uso. I token permettono di controllare il consumo, ma non sono un importo monetario: prezzi, cache e tipi di token possono cambiare il calcolo.</p>
            <p class="mt-2">Per OpenAI il costo ufficiale dell’organizzazione è disponibile tramite una API amministrativa separata, che richiede una Admin API key più privilegiata. Per sicurezza questa applicazione non la richiede: verifica l’importo esatto nel pannello di fatturazione del provider. <a href="https://developers.openai.com/api/reference/resources/admin/subresources/organization/subresources/usage/methods/costs" target="_blank" rel="noreferrer" class="text-sky-400">Documentazione Costs API ↗</a></p>
        </section>

        @if ($integrations->isEmpty())
            <section class="mt-8 rounded-3xl border border-dashed border-slate-700 bg-slate-900/60 px-8 py-16 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-violet-400/10 text-2xl text-violet-300" aria-hidden="true">✦</div>
                <h2 class="mt-5 text-xl font-semibold">Nessuna integrazione AI</h2>
                <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-400">Aggiungi OpenAI o Anthropic, salva la chiave API e verifica il collegamento prima di usarlo.</p>
                <a href="{{ route('settings.ai.create') }}" class="mt-6 inline-flex rounded-xl bg-violet-400 px-5 py-3 font-semibold text-slate-950 no-underline transition hover:bg-violet-300">
                    Crea la prima integrazione
                </a>
            </section>
        @else
            <div class="mt-8 grid gap-5 lg:grid-cols-2">
                @foreach ($integrations as $integration)
                    @php
                        $statusDetails = match ($integration->status) {
                            'verified' => ['label' => 'Chiave e modello verificati', 'class' => 'bg-emerald-400/10 text-emerald-300', 'icon' => '✓'],
                            'error' => ['label' => 'Verifica non riuscita', 'class' => 'bg-rose-400/10 text-rose-300', 'icon' => '!'],
                            default => ['label' => 'Da verificare', 'class' => 'bg-amber-400/10 text-amber-200', 'icon' => '•'],
                        };
                    @endphp
                    <article class="rounded-3xl border {{ $integration->is_active ? 'border-violet-400/50 bg-violet-400/[0.04]' : 'border-slate-800 bg-slate-900' }} p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-xl font-semibold">{{ $integration->name }}</h2>
                                    @if ($integration->is_active)
                                        <span class="rounded-full bg-violet-400 px-2.5 py-1 text-xs font-bold text-slate-950">Attiva</span>
                                    @endif
                                </div>
                                <p class="mt-2 text-sm text-slate-400">{{ config('ai.providers.'.$integration->provider.'.name', $integration->provider) }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ config('ai.providers.'.$integration->provider.'.models.'.$integration->model, $integration->model) }}</p>
                            </div>
                            <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold {{ $statusDetails['class'] }}">
                                <span aria-hidden="true">{{ $statusDetails['icon'] }}</span>
                                {{ $statusDetails['label'] }}
                            </span>
                        </div>

                        @if ($integration->status === 'error' && $integration->last_error)
                            <p class="mt-4 rounded-xl border border-rose-500/20 bg-rose-500/10 p-3 text-sm leading-6 text-rose-100">{{ $integration->last_error }}</p>
                        @elseif ($integration->last_tested_at)
                            <p class="mt-4 text-xs text-slate-500">Ultima verifica: {{ $integration->last_tested_at->format('d/m/Y H:i') }}</p>
                            <p class="mt-2 text-xs leading-5 text-slate-500">La verifica controlla accesso e modello, ma non esegue una generazione e non può confermare credito o budget disponibili.</p>
                        @endif

                        <dl class="mt-5 grid grid-cols-2 gap-3 rounded-2xl border border-slate-800 bg-slate-950/50 p-4 text-sm">
                            <div>
                                <dt class="text-xs text-slate-500">Richieste registrate</dt>
                                <dd class="mt-1 font-semibold text-slate-200">{{ number_format($integration->usage_records_count, 0, ',', '.') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500">Token totali</dt>
                                <dd class="mt-1 font-semibold text-slate-200">{{ number_format((int) $integration->usage_total_tokens, 0, ',', '.') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500">Token in ingresso</dt>
                                <dd class="mt-1 text-slate-300">{{ number_format((int) $integration->usage_input_tokens, 0, ',', '.') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500">Token in uscita</dt>
                                <dd class="mt-1 text-slate-300">{{ number_format((int) $integration->usage_output_tokens, 0, ',', '.') }}</dd>
                            </div>
                            @if ($integration->usage_last_occurred_at)
                                <div class="col-span-2 border-t border-slate-800 pt-3 text-xs text-slate-500">Ultimo utilizzo: {{ \Carbon\CarbonImmutable::parse($integration->usage_last_occurred_at)->format('d/m/Y H:i') }}</div>
                            @endif
                        </dl>

                        <div class="mt-6 flex flex-wrap gap-2 border-t border-slate-800 pt-5">
                            <a href="{{ route('settings.ai.edit', $integration) }}" class="inline-flex rounded-lg border border-slate-700 px-3 py-2 text-sm font-medium text-slate-200 no-underline hover:bg-slate-800">Modifica</a>

                            <form method="POST" action="{{ route('settings.ai.verify', $integration) }}">
                                @csrf
                                <button type="submit" class="rounded-lg border border-emerald-400/40 px-3 py-2 text-sm font-medium text-emerald-200 hover:bg-emerald-400/10">Verifica</button>
                            </form>

                            @unless ($integration->is_active)
                                <form method="POST" action="{{ route('settings.ai.activate', $integration) }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg border border-violet-400/40 px-3 py-2 text-sm font-medium text-violet-200 hover:bg-violet-400/10">Rendi attiva</button>
                                </form>
                            @endunless

                            <form method="POST" action="{{ route('settings.ai.destroy', $integration) }}" class="sm:ml-auto" onsubmit="return confirm('Vuoi rimuovere questa integrazione AI?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-lg border border-rose-500/30 px-3 py-2 text-sm font-medium text-rose-300 hover:bg-rose-500/10">Rimuovi</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </main>
@endsection
