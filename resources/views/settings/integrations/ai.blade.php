@extends('layouts.app')

@section('title', ($integration ? 'Modifica integrazione AI' : 'Nuova integrazione AI').' — '.config('app.name', 'Monitoring Hub'))

@section('content')
    @include('partials.navigation')

    <main class="mx-auto max-w-6xl px-6 py-10">
        <div class="mb-8">
            <a href="{{ route('settings.ai.index') }}" class="text-sm font-medium text-slate-400 no-underline hover:text-white">← Torna alle integrazioni</a>
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-violet-300">Impostazioni dell’installazione</p>
            <h1 class="mt-2 text-3xl font-semibold">{{ $integration ? 'Modifica integrazione AI' : 'Nuova integrazione AI' }}</h1>
            <p class="mt-3 max-w-3xl leading-7 text-slate-400">
                Scegli il <strong class="text-slate-200">fornitore</strong> del servizio AI, il modello e la relativa API key. Claude è la famiglia di modelli di Anthropic; OpenAI è sia il fornitore sia il nome della piattaforma API.
            </p>
        </div>

        @if (session('status'))
            <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200" role="alert">
                <p class="font-semibold">Controlla i dati inseriti:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-8 lg:grid-cols-[0.9fr_1.1fr]">
            <section class="rounded-3xl border border-slate-800 bg-slate-900 p-8" aria-labelledby="ai-configuration-heading">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 id="ai-configuration-heading" class="text-xl font-semibold">Dati dell’integrazione</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-400">Potrai scegliere dal riepilogo quale integrazione rendere attiva.</p>
                    </div>
                    @php
                        $status = $integration?->status;
                        $statusDetails = match ($status) {
                            'verified' => ['label' => 'Credenziali verificate', 'class' => 'bg-emerald-400/10 text-emerald-300', 'icon' => '✓'],
                            'error' => ['label' => 'Verifica non riuscita', 'class' => 'bg-rose-400/10 text-rose-300', 'icon' => '!'],
                            'configured' => ['label' => 'Salvata, da verificare', 'class' => 'bg-amber-400/10 text-amber-200', 'icon' => '•'],
                            default => ['label' => 'Nuova', 'class' => 'bg-slate-800 text-slate-400', 'icon' => '＋'],
                        };
                    @endphp
                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold {{ $statusDetails['class'] }}" role="status">
                        <span aria-hidden="true">{{ $statusDetails['icon'] }}</span>
                        {{ $statusDetails['label'] }}
                    </span>
                </div>

                @if ($integration?->status === 'verified')
                    <div class="mt-5 rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-sm leading-6 text-emerald-100">
                        <p class="font-semibold">Collegamento operativo</p>
                        <p class="mt-1">La API key è stata accettata dal fornitore e il modello selezionato risulta disponibile.</p>
                        @if ($integration->last_tested_at)
                            <p class="mt-1 text-xs text-emerald-200/70">Ultima verifica: {{ $integration->last_tested_at->format('d/m/Y H:i') }}</p>
                        @endif
                    </div>
                @elseif ($integration?->status === 'error')
                    <div class="mt-5 rounded-xl border border-rose-500/20 bg-rose-500/10 p-4 text-sm leading-6 text-rose-100">
                        <p class="font-semibold">La configurazione richiede attenzione</p>
                        <p class="mt-1">{{ $integration->last_error }}</p>
                    </div>
                @elseif ($integration)
                    <div class="mt-5 rounded-xl border border-amber-400/20 bg-amber-400/10 p-4 text-sm leading-6 text-amber-100">
                        <p class="font-semibold">Credenziali salvate, ma non ancora verificate</p>
                        <p class="mt-1">Usa il pulsante qui sotto per controllare chiave e modello con una richiesta di sola lettura.</p>
                    </div>
                @endif

                <form method="POST" action="{{ $integration ? route('settings.ai.update', $integration) : route('settings.ai.store') }}" class="mt-7 space-y-6">
                    @csrf
                    @if ($integration)
                        @method('PUT')
                    @endif

                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-200">Nome integrazione</label>
                        <input id="name" name="name" type="text" maxlength="100" value="{{ old('name', $integration?->name) }}" placeholder="Es. OpenAI principale" class="mt-2">
                        <p class="mt-2 text-xs leading-5 text-slate-500">Facoltativo. Serve a distinguere facilmente più collegamenti; se vuoto useremo il nome del fornitore.</p>
                    </div>

                    <div>
                        <label for="ai_provider" class="block text-sm font-medium text-slate-200">Fornitore AI <span class="text-rose-300">*</span></label>
                        <select id="ai_provider" name="ai_provider" required class="mt-2">
                            <option value="">Seleziona un fornitore</option>
                            @foreach ($providers as $providerId => $provider)
                                <option value="{{ $providerId }}" @selected(old('ai_provider', $selectedProvider) === $providerId)>{{ $provider['name'] }}</option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-xs leading-5 text-slate-500">Il fornitore gestisce l’API e fattura il relativo utilizzo.</p>
                    </div>

                    <div>
                        <label for="model" class="block text-sm font-medium text-slate-200">Modello <span class="text-rose-300">*</span></label>
                        <select id="model" name="model" required class="mt-2" data-selected-model="{{ old('model', $selectedModel) }}">
                            <option value="">Prima seleziona il fornitore</option>
                            @foreach (data_get($providers, old('ai_provider', $selectedProvider).'.models', []) as $modelId => $modelName)
                                <option value="{{ $modelId }}" @selected(old('model', $selectedModel) === $modelId)>{{ $modelName }} — {{ $modelId }}</option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-xs leading-5 text-slate-500">Il modello determina capacità, velocità e costo. La disponibilità dipende dal tuo account.</p>
                    </div>

                    <div>
                        <label for="api_key" class="block text-sm font-medium text-slate-200">API key @unless($integration)<span class="text-rose-300">*</span>@endunless</label>
                        <input id="api_key" name="api_key" type="password" value="" autocomplete="new-password" spellcheck="false" class="mt-2">
                        @if ($integration)
                            <p class="mt-2 text-xs leading-5 text-slate-500">La chiave salvata non viene mai mostrata. Lascia vuoto per mantenerla; se cambi fornitore devi inserirne una nuova.</p>
                        @else
                            <p class="mt-2 text-xs leading-5 text-slate-500">Copia qui la chiave segreta generata dal fornitore. Non inserirla mai in GitHub o in file versionati.</p>
                        @endif
                    </div>

                    <div class="rounded-xl border border-sky-500/20 bg-sky-500/10 p-4 text-xs leading-5 text-sky-100">
                        La API key viene cifrata prima di essere salvata nel database. Il segreto non viene restituito nell’HTML, nei messaggi o nei log.
                    </div>

                    <button type="submit" class="w-full rounded-xl bg-violet-400 px-5 py-3 font-semibold text-violet-950 hover:bg-violet-300">{{ $integration ? 'Salva modifiche' : 'Crea integrazione' }}</button>
                </form>

                @if ($integration)
                    <form method="POST" action="{{ route('settings.ai.verify', $integration) }}" class="mt-4">
                        @csrf
                        <button type="submit" class="w-full rounded-xl border border-emerald-400/40 bg-emerald-400/10 px-5 py-3 font-semibold text-emerald-200 hover:bg-emerald-400/20">Verifica credenziali</button>
                        <p class="mt-2 text-center text-xs leading-5 text-slate-500">Controllo in sola lettura: non genera testo e non usa token.</p>
                    </form>
                    <form method="POST" action="{{ route('settings.ai.destroy', $integration) }}" class="mt-4" onsubmit="return confirm('Vuoi rimuovere questa integrazione AI?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="w-full border border-rose-500/30 bg-transparent text-rose-300 hover:bg-rose-500/10">Rimuovi configurazione</button>
                    </form>
                @endif
            </section>

            <section class="space-y-6" aria-labelledby="ai-guides-heading">
                <div>
                    <h2 id="ai-guides-heading" class="text-xl font-semibold">Dove trovare i parametri</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-400">Crea una chiave dedicata a questa applicazione e conservala come un segreto. Non servono password personali né credenziali dell’account.</p>
                </div>

                <article class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
                    <div class="flex items-center justify-between gap-4">
                        <h3 class="text-lg font-semibold">OpenAI</h3>
                        <span class="rounded-full bg-emerald-400/10 px-3 py-1 text-xs font-medium text-emerald-300">API Responses</span>
                    </div>
                    <div class="mt-4 rounded-xl border border-amber-400/30 bg-amber-400/10 p-4 text-sm leading-6 text-amber-50">
                        <p class="font-semibold text-amber-100">Prima di creare la chiave</p>
                        <p class="mt-1">Se OpenAI ti mostra un campo per un <strong>codice di autenticazione</strong>, sei ancora nel login della piattaforma. Quel codice serve solo a verificare l’accesso al tuo account (per esempio tramite il metodo di verifica mostrato da OpenAI): <strong>non è la API key</strong> e non devi abilitarlo dentro Monitoring Hub.</p>
                        <p class="mt-2">Completa il login solo se riconosci l’account e il metodo di verifica. Dopo l’accesso devi arrivare alla pagina del progetto con il pulsante per creare una nuova chiave segreta. Se non riconosci la richiesta o non puoi ricevere il codice, interrompi il flusso e recupera prima l’accesso all’account OpenAI.</p>
                    </div>
                    <ol class="mt-4 list-decimal space-y-2 pl-5 text-sm leading-6 text-slate-400">
                        <li>Apri la console API OpenAI e, se richiesto, accedi con lo stesso account OpenAI che vuoi usare per questa installazione.</li>
                        <li>Seleziona il progetto corretto, crea una nuova chiave segreta e copiala subito: il valore completo viene mostrato solo durante la creazione.</li>
                        <li>Consulta il catalogo modelli e scegli un modello disponibile per quel progetto, ad esempio <code class="rounded bg-slate-950/60 px-1.5 py-0.5 text-amber-100">gpt-5.4-mini</code>.</li>
                        <li>Torna qui, seleziona <strong class="text-slate-200">OpenAI</strong>, scegli il modello e incolla la chiave nel campo <strong class="text-slate-200">API key</strong>.</li>
                    </ol>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <a href="https://platform.openai.com/settings/organization/api-keys" target="_blank" rel="noreferrer" class="inline-flex rounded-lg border border-sky-500/30 px-3 py-2 text-sm font-medium text-sky-300 no-underline hover:bg-sky-500/10">Apri console API key ↗</a>
                        <a href="https://developers.openai.com/api/docs/quickstart" target="_blank" rel="noreferrer" class="inline-flex rounded-lg border border-slate-700 px-3 py-2 text-sm font-medium text-slate-300 no-underline hover:bg-slate-800">Guida ufficiale ↗</a>
                        <a href="https://developers.openai.com/api/docs/models" target="_blank" rel="noreferrer" class="inline-flex rounded-lg border border-slate-700 px-3 py-2 text-sm font-medium text-slate-300 no-underline hover:bg-slate-800">Catalogo modelli ↗</a>
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
                    <div class="flex items-center justify-between gap-4">
                        <h3 class="text-lg font-semibold">Anthropic (Claude)</h3>
                        <span class="rounded-full bg-amber-400/10 px-3 py-1 text-xs font-medium text-amber-200">API Messages</span>
                    </div>
                    <ol class="mt-4 list-decimal space-y-2 pl-5 text-sm leading-6 text-slate-400">
                        <li>Accedi alla Claude Console e vai in <strong class="text-slate-200">Settings → API keys</strong>.</li>
                        <li>Crea una chiave personale per lo sviluppo o una chiave di service account per un’installazione condivisa; associala a un singolo workspace così non serve indicarne l’ID a ogni richiesta.</li>
                        <li>Copia subito la chiave: il valore completo viene mostrato una sola volta.</li>
                        <li>Seleziona <strong class="text-slate-200">Anthropic (Claude)</strong> qui a sinistra, scegli il modello e incolla la chiave.</li>
                    </ol>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <a href="https://platform.claude.com/settings/keys" target="_blank" rel="noreferrer" class="inline-flex rounded-lg border border-sky-500/30 px-3 py-2 text-sm font-medium text-sky-300 no-underline hover:bg-sky-500/10">Apri API key ↗</a>
                        <a href="https://platform.claude.com/docs/en/get-api-key" target="_blank" rel="noreferrer" class="inline-flex rounded-lg border border-slate-700 px-3 py-2 text-sm font-medium text-slate-300 no-underline hover:bg-slate-800">Guida ufficiale ↗</a>
                        <a href="https://platform.claude.com/docs/en/models/overview" target="_blank" rel="noreferrer" class="inline-flex rounded-lg border border-slate-700 px-3 py-2 text-sm font-medium text-slate-300 no-underline hover:bg-slate-800">Catalogo modelli ↗</a>
                    </div>
                </article>

                <p class="text-xs leading-5 text-slate-500">Le chiavi danno accesso a servizi a consumo. Prima dell’uso in produzione configura limiti di spesa e rotazione delle chiavi nei pannelli del fornitore.</p>
            </section>
        </div>
    </main>

    <script>
        const providers = @json($providers);
        const providerSelect = document.getElementById('ai_provider');
        const modelSelect = document.getElementById('model');

        const fillModels = (preservedModel = '') => {
            const models = providers[providerSelect.value]?.models ?? {};

            modelSelect.replaceChildren(new Option(
                Object.keys(models).length ? 'Seleziona un modello' : 'Prima seleziona il fornitore',
                '',
            ));

            Object.entries(models).forEach(([id, name]) => {
                modelSelect.add(new Option(`${name} — ${id}`, id, false, id === preservedModel));
            });
        };

        providerSelect.addEventListener('change', () => fillModels());

        if (providerSelect.value && modelSelect.options.length === 1) {
            fillModels(modelSelect.dataset.selectedModel);
        }
    </script>
@endsection
