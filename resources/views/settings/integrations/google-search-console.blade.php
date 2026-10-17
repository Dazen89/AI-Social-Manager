@extends('layouts.app')

@section('title', 'Impostazioni Google Search Console — '.config('app.name', 'Monitoring Hub'))

@section('content')
    @include('partials.navigation')

    <main class="mx-auto max-w-5xl px-6 py-10">
        <div class="mb-8">
            @if ($returnSite)
                <a href="{{ route('sites.edit', $returnSite) }}" class="text-sm text-sky-400">← Torna a {{ $returnSite->name }}</a>
            @endif
            <p class="mt-4 text-sm font-semibold uppercase tracking-[0.2em] text-sky-400">Impostazioni dell’installazione</p>
            <h1 class="mt-2 text-3xl font-semibold">Google Search Console</h1>
            <p class="mt-3 max-w-3xl leading-7 text-slate-400">Configura una sola volta l’accesso OAuth dell’applicazione. In seguito potrai collegare a ogni sito una proprietà Search Console verificata, usando esclusivamente il permesso di lettura.</p>
        </div>

        @if (session('status'))
            <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200" role="status">{{ session('status') }}</div>
        @endif

        @if (session('integration_error'))
            <div class="mb-6 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200" role="alert">{{ session('integration_error') }}</div>
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

        <section class="mb-8 rounded-2xl border border-sky-400/30 bg-sky-400/10 p-6" aria-labelledby="reuse-google-heading">
            <h2 id="reuse-google-heading" class="text-lg font-semibold text-sky-100">Hai già configurato Google Analytics?</h2>
            <p class="mt-2 text-sm leading-6 text-sky-50/90">Puoi riutilizzare lo stesso progetto Google Cloud e lo stesso client OAuth. Nel client esistente aggiungi anche il nuovo URI di reindirizzamento mostrato sotto, abilita la Search Console API e aggiungi lo scope <code class="rounded bg-slate-950/40 px-1.5 py-0.5 text-xs">webmasters.readonly</code>. Poi inserisci qui lo stesso Client ID e Client Secret. La dashboard conserva i collegamenti separatamente.</p>
            <p class="mt-2 text-xs leading-5 text-sky-200/80">Google gestisce però consenso e revoca a livello di progetto Cloud: se riutilizzi lo stesso progetto e in seguito revochi un collegamento, potrebbe essere necessario autorizzare nuovamente anche l’altro servizio. Usa due progetti distinti soltanto se ti serve isolamento completo delle autorizzazioni.</p>
        </section>

        <div class="grid gap-8 lg:grid-cols-[1.2fr_0.8fr]">
            <section class="rounded-3xl border border-slate-800 bg-slate-900 p-8" aria-labelledby="search-console-setup-heading">
                <h2 id="search-console-setup-heading" class="text-xl font-semibold">Procedura completa</h2>
                <p class="mt-2 text-sm leading-6 text-slate-400">Segui i passaggi nell’ordine usando un account che possa accedere alla proprietà Search Console del sito.</p>

                <ol class="mt-7 space-y-8">
                    <li class="flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-500/15 text-sm font-semibold text-sky-300">1</span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold">Controlla la proprietà in Search Console</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-400">Apri Search Console e verifica che l’account Google possa vedere il sito. Sono supportate sia le proprietà Dominio, come <code>sc-domain:example.com</code>, sia le proprietà con prefisso URL, come <code>https://www.example.com/</code>. Se il sito non compare, aggiungilo e completa prima la verifica richiesta da Google.</p>
                            <a href="https://search.google.com/search-console" target="_blank" rel="noreferrer" class="mt-3 inline-flex rounded-lg border border-sky-500/30 px-3 py-2 text-sm font-medium text-sky-300 no-underline hover:bg-sky-500/10">Apri Search Console ↗</a>
                        </div>
                    </li>
                    <li class="flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-500/15 text-sm font-semibold text-sky-300">2</span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold">Abilita la Search Console API</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-400">Nel progetto Google Cloud scelto abilita <strong class="text-slate-200">Google Search Console API</strong>. Premi “Abilita”; se compare “Gestisci”, l’API è già attiva.</p>
                            <a href="https://console.cloud.google.com/apis/library/searchconsole.googleapis.com" target="_blank" rel="noreferrer" class="mt-3 inline-flex rounded-lg border border-sky-500/30 px-3 py-2 text-sm font-medium text-sky-300 no-underline hover:bg-sky-500/10">Apri Search Console API ↗</a>
                        </div>
                    </li>
                    <li class="flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-500/15 text-sm font-semibold text-sky-300">3</span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold">Configura consenso e utenti di test</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-400">In <strong class="text-slate-200">Google Auth Platform</strong> completa Branding e Pubblico. Se l’app è “Esterna” e in modalità Test, aggiungi tra gli utenti di test l’account che userai per collegare Search Console. Se hai già completato questi passaggi per Analytics nello stesso progetto, non devi ripeterli.</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <a href="https://console.cloud.google.com/auth/branding" target="_blank" rel="noreferrer" class="inline-flex rounded-lg border border-sky-500/30 px-3 py-2 text-sm font-medium text-sky-300 no-underline hover:bg-sky-500/10">Apri Branding ↗</a>
                                <a href="https://console.cloud.google.com/auth/audience" target="_blank" rel="noreferrer" class="inline-flex rounded-lg border border-sky-500/30 px-3 py-2 text-sm font-medium text-sky-300 no-underline hover:bg-sky-500/10">Apri Pubblico ↗</a>
                            </div>
                        </div>
                    </li>
                    <li class="flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-500/15 text-sm font-semibold text-sky-300">4</span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold">Aggiungi il solo ambito di lettura</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-400">In <strong class="text-slate-200">Accesso ai dati → Aggiungi o rimuovi ambiti</strong> cerca e aggiungi esattamente:</p>
                            <div class="mt-3 flex items-start gap-2 rounded-xl border border-slate-700 bg-slate-950 p-3">
                                <code id="search-console-scope" class="min-w-0 flex-1 break-all text-xs leading-5 text-slate-200">https://www.googleapis.com/auth/webmasters.readonly</code>
                                <button type="button" data-copy-target="search-console-scope" class="shrink-0 rounded-lg border border-slate-600 bg-slate-900 px-3 py-1.5 text-xs font-semibold text-slate-200 hover:border-sky-400">Copia</button>
                            </div>
                            <p class="mt-2 text-xs leading-5 text-slate-500">Questo permesso consente di leggere proprietà e report, ma non di modificare Search Console o il sito.</p>
                            <a href="https://console.cloud.google.com/auth/scopes" target="_blank" rel="noreferrer" class="mt-3 inline-flex rounded-lg border border-sky-500/30 px-3 py-2 text-sm font-medium text-sky-300 no-underline hover:bg-sky-500/10">Apri Accesso ai dati ↗</a>
                        </div>
                    </li>
                    <li class="flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-500/15 text-sm font-semibold text-sky-300">5</span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold">Crea o aggiorna il client OAuth web</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-400">In <strong class="text-slate-200">Client</strong> crea un client di tipo “Applicazione web”, oppure apri quello già usato da Analytics. Lascia vuote le origini JavaScript e aggiungi tra gli <strong class="text-slate-200">URI di reindirizzamento autorizzati</strong> questo valore esatto:</p>
                            <div class="mt-3 flex items-start gap-2 rounded-xl border border-slate-700 bg-slate-950 p-3">
                                <code id="search-console-callback-uri" class="min-w-0 flex-1 break-all text-xs leading-5 text-slate-200">{{ $redirectUri }}</code>
                                <button type="button" data-copy-target="search-console-callback-uri" class="shrink-0 rounded-lg border border-slate-600 bg-slate-900 px-3 py-1.5 text-xs font-semibold text-slate-200 hover:border-sky-400">Copia URI</button>
                            </div>
                            <p class="mt-2 text-xs leading-5 text-slate-500">Protocollo, dominio, porta, percorso e slash devono coincidere. Non sostituire questo valore con l’indirizzo del sito monitorato.</p>
                            <a href="https://console.cloud.google.com/auth/clients" target="_blank" rel="noreferrer" class="mt-3 inline-flex rounded-lg border border-sky-500/30 px-3 py-2 text-sm font-medium text-sky-300 no-underline hover:bg-sky-500/10">Apri Client OAuth ↗</a>
                        </div>
                    </li>
                    <li class="flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-500/15 text-sm font-semibold text-emerald-300">6</span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold">Salva Client ID e Client Secret</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-400">Copia i due valori nel modulo a destra. Il Client Secret sarà cifrato nel database e non verrà più mostrato. Non inserirlo in GitHub, nella documentazione o negli screenshot.</p>
                        </div>
                    </li>
                </ol>

                <div class="mt-8 flex flex-wrap gap-4 text-sm">
                    <a href="https://developers.google.com/webmaster-tools/v1/how-tos/authorizing" target="_blank" rel="noreferrer" class="font-medium text-sky-400">Autorizzazione ufficiale ↗</a>
                    <a href="https://developers.google.com/webmaster-tools/v1/sites/list" target="_blank" rel="noreferrer" class="font-medium text-sky-400">Elenco proprietà API ↗</a>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-800 bg-slate-900 p-8" aria-labelledby="search-console-credentials-heading">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="search-console-credentials-heading" class="text-xl font-semibold">Credenziali dell’applicazione</h2>
                        <p class="mt-2 text-sm text-slate-400">Valide per tutti i siti registrati.</p>
                    </div>

                    @if ($environmentManaged)
                        <span class="rounded-full bg-sky-400/10 px-3 py-1 text-xs font-medium text-sky-300">Gestite dall’ambiente</span>
                    @elseif ($setting?->status === 'verified')
                        <span class="rounded-full bg-emerald-400/10 px-3 py-1 text-xs font-medium text-emerald-300">Verificate</span>
                    @elseif ($setting?->status === 'error')
                        <span class="rounded-full bg-rose-400/10 px-3 py-1 text-xs font-medium text-rose-300">Da controllare</span>
                    @elseif ($setting)
                        <span class="rounded-full bg-amber-400/10 px-3 py-1 text-xs font-medium text-amber-300">Salvate, da verificare</span>
                    @else
                        <span class="rounded-full bg-slate-700 px-3 py-1 text-xs font-medium text-slate-300">Non configurate</span>
                    @endif
                </div>

                @if ($environmentManaged)
                    <div class="mt-7 rounded-xl border border-sky-500/20 bg-sky-500/10 p-4 text-sm leading-6 text-sky-100">
                        <p class="font-semibold">Credenziali gestite dal server.</p>
                        <p class="mt-2">Le variabili d’ambiente hanno la precedenza e, per sicurezza, non possono essere lette o modificate dalla dashboard.</p>
                    </div>
                @else
                    @if ($setting?->status === 'error' && $setting->last_error)
                        <div class="mt-7 rounded-xl border border-rose-500/20 bg-rose-500/10 p-4 text-sm leading-6 text-rose-100">
                            <p class="font-semibold">Ultima verifica non riuscita</p>
                            <p class="mt-1">{{ $setting->last_error }}</p>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('settings.google-search-console.update') }}" class="mt-7 space-y-6">
                        @csrf
                        @method('PUT')

                        @if ($returnSite)
                            <input type="hidden" name="return_site_id" value="{{ $returnSite->id }}">
                        @endif

                        <div>
                            <label for="client_id" class="block text-sm font-medium text-slate-200">Client ID <span class="text-rose-300">*</span></label>
                            <input id="client_id" name="client_id" type="text" value="{{ old('client_id', $clientId) }}" required autocomplete="off" spellcheck="false" @readonly($hasConnections) class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-slate-100 read-only:cursor-not-allowed read-only:opacity-60">
                            <p class="mt-2 text-xs leading-5 text-slate-500">È l’identificativo pubblico che termina normalmente con <code>apps.googleusercontent.com</code>.</p>
                            @if ($hasConnections)
                                <p class="mt-2 text-xs leading-5 text-amber-300">Non è modificabile finché esistono collegamenti Search Console attivi.</p>
                            @endif
                        </div>

                        <div>
                            <label for="client_secret" class="block text-sm font-medium text-slate-200">Client Secret @unless($setting)<span class="text-rose-300">*</span>@endunless</label>
                            <input id="client_secret" name="client_secret" type="password" value="" autocomplete="new-password" spellcheck="false" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-slate-100">
                            @if ($setting)
                                <p class="mt-2 text-xs leading-5 text-slate-500">Lascia vuoto per mantenere il segreto salvato oppure inseriscine uno nuovo per sostituirlo.</p>
                            @else
                                <p class="mt-2 text-xs leading-5 text-slate-500">Il valore è riservato: verrà cifrato e non sarà mai mostrato dopo il salvataggio.</p>
                            @endif
                        </div>

                        <button type="submit" class="w-full rounded-xl bg-sky-500 px-5 py-3 font-semibold text-slate-950 hover:bg-sky-400">{{ $setting ? 'Salva modifiche' : 'Salva credenziali' }}</button>
                    </form>

                    @if ($setting && ! $hasConnections)
                        <form method="POST" action="{{ route('settings.google-search-console.destroy') }}" class="mt-4" onsubmit="return confirm('Vuoi rimuovere le credenziali Search Console da questa installazione?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="w-full border border-rose-500/30 bg-transparent text-rose-300 hover:bg-rose-500/10">Rimuovi configurazione</button>
                        </form>
                    @endif
                @endif

                <div class="mt-7 border-t border-slate-800 pt-5 text-xs leading-5 text-slate-500">
                    <p>La verifica effettiva avviene al primo collegamento. L’account autorizzato deve già avere accesso alla proprietà Search Console desiderata.</p>
                    @if ($setting?->last_tested_at)
                        <p class="mt-2">Ultimo controllo: {{ $setting->last_tested_at->format('d/m/Y H:i') }}</p>
                    @endif
                </div>
            </section>
        </div>
    </main>

    <p id="copy-feedback" class="sr-only" aria-live="polite"></p>
    <script>
        document.querySelectorAll('[data-copy-target]').forEach((button) => {
            button.addEventListener('click', async () => {
                const target = document.getElementById(button.dataset.copyTarget);

                if (! target) {
                    return;
                }

                const value = target.textContent.trim();

                try {
                    if (navigator.clipboard && window.isSecureContext) {
                        await navigator.clipboard.writeText(value);
                    } else {
                        const textarea = document.createElement('textarea');
                        textarea.value = value;
                        textarea.style.position = 'fixed';
                        textarea.style.opacity = '0';
                        document.body.appendChild(textarea);
                        textarea.select();
                        document.execCommand('copy');
                        textarea.remove();
                    }

                    const originalLabel = button.textContent;
                    button.textContent = 'Copiato';
                    document.getElementById('copy-feedback').textContent = 'Valore copiato negli appunti.';
                    window.setTimeout(() => button.textContent = originalLabel, 1800);
                } catch (error) {
                    document.getElementById('copy-feedback').textContent = 'Copia non riuscita: seleziona il valore manualmente.';
                }
            });
        });
    </script>
@endsection
