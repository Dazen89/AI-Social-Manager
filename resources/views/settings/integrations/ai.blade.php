@extends('layouts.app')

@section('title', 'Integrazioni AI — '.config('app.name', 'Monitoring Hub'))

@section('content')
    @include('partials.navigation')

    <main class="mx-auto max-w-6xl px-6 py-10">
        <div class="mb-8">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-violet-300">Impostazioni dell’installazione</p>
            <h1 class="mt-2 text-3xl font-semibold">Integrazioni AI</h1>
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
                        <h2 id="ai-configuration-heading" class="text-xl font-semibold">Configurazione attiva</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-400">Una sola configurazione viene usata come predefinita dall’applicazione.</p>
                    </div>
                    @if ($setting)
                        <span class="rounded-full bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-300">Configurata</span>
                    @endif
                </div>

                <form method="POST" action="{{ route('settings.ai.update') }}" class="mt-7 space-y-6">
                    @csrf
                    @method('PUT')

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
                        <label for="api_key" class="block text-sm font-medium text-slate-200">API key @unless($setting)<span class="text-rose-300">*</span>@endunless</label>
                        <input id="api_key" name="api_key" type="password" value="" autocomplete="new-password" spellcheck="false" class="mt-2">
                        @if ($setting)
                            <p class="mt-2 text-xs leading-5 text-slate-500">La chiave salvata non viene mai mostrata. Lascia vuoto per mantenerla; se cambi fornitore devi inserirne una nuova.</p>
                        @else
                            <p class="mt-2 text-xs leading-5 text-slate-500">Copia qui la chiave segreta generata dal fornitore. Non inserirla mai in GitHub o in file versionati.</p>
                        @endif
                    </div>

                    <div class="rounded-xl border border-sky-500/20 bg-sky-500/10 p-4 text-xs leading-5 text-sky-100">
                        L’intera configurazione, inclusa la API key, viene cifrata prima di essere salvata nel database. Il segreto non viene restituito nell’HTML, nei messaggi o nei log.
                    </div>

                    <button type="submit" class="w-full rounded-xl bg-violet-400 px-5 py-3 font-semibold text-violet-950 hover:bg-violet-300">{{ $setting ? 'Salva modifiche' : 'Salva integrazione' }}</button>
                </form>

                @if ($setting)
                    <form method="POST" action="{{ route('settings.ai.destroy') }}" class="mt-4" onsubmit="return confirm('Vuoi rimuovere la configurazione AI da questa installazione?');">
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
                    <ol class="mt-4 list-decimal space-y-2 pl-5 text-sm leading-6 text-slate-400">
                        <li>Accedi alla piattaforma OpenAI e apri la pagina delle API key.</li>
                        <li>Crea una chiave per il progetto destinato a questa applicazione e copiala quando viene mostrata.</li>
                        <li>Consulta il catalogo modelli e scegli un modello disponibile per il tuo progetto.</li>
                        <li>Seleziona <strong class="text-slate-200">OpenAI</strong> qui a sinistra, scegli il modello e incolla la chiave.</li>
                    </ol>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <a href="https://platform.openai.com/api-keys" target="_blank" rel="noreferrer" class="inline-flex rounded-lg border border-sky-500/30 px-3 py-2 text-sm font-medium text-sky-300 no-underline hover:bg-sky-500/10">Apri API key ↗</a>
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
