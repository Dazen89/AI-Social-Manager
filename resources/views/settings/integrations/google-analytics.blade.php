@extends('layouts.app')

@section('title', 'Impostazioni Google Analytics — '.config('app.name', 'Monitoring Hub'))

@section('content')
    @include('partials.navigation')

    <main class="mx-auto max-w-5xl px-6 py-10">
        <div class="mb-8">
            @if ($returnSite)
                <a href="{{ route('sites.edit', $returnSite) }}" class="text-sm text-sky-400">← Torna a {{ $returnSite->name }}</a>
            @endif
            <p class="mt-4 text-sm font-semibold uppercase tracking-[0.2em] text-sky-400">Impostazioni dell’installazione</p>
            <h1 class="mt-2 text-3xl font-semibold">Google Analytics 4</h1>
            <p class="mt-3 max-w-3xl leading-7 text-slate-400">Queste credenziali identificano questa installazione presso Google. Si configurano una sola volta e consentono poi agli amministratori di collegare gli account Analytics ai singoli siti.</p>
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

        <section class="mb-8 rounded-2xl border border-amber-400/30 bg-amber-400/10 p-6" aria-labelledby="current-google-screen-heading">
            <h2 id="current-google-screen-heading" class="text-lg font-semibold text-amber-100">Sei già nella schermata giusta</h2>
            <p class="mt-2 text-sm leading-6 text-amber-50/90">In <strong>API e servizi → Credenziali</strong>, la sezione “ID client OAuth 2.0” è vuota perché devi prima completare la schermata di consenso. Clicca il pulsante giallo <strong>Configura schermata di consenso</strong>. Ignora invece <strong>Chiavi API</strong> e <strong>Service account</strong>: non servono a questo collegamento.</p>
            <p class="mt-2 text-xs leading-5 text-amber-200/80">Il banner relativo alla prova gratuita di Google Cloud non è il passaggio che ti sta bloccando.</p>
        </section>

        <div class="grid gap-8 lg:grid-cols-[1.2fr_0.8fr]">
            <section class="rounded-3xl border border-slate-800 bg-slate-900 p-8" aria-labelledby="google-setup-heading">
                <h2 id="google-setup-heading" class="text-xl font-semibold">Procedura completa in Google Cloud</h2>
                <p class="mt-2 text-sm leading-6 text-slate-400">Segui i passaggi nell’ordine. Mantieni selezionato lo stesso progetto Google Cloud dall’inizio alla fine.</p>

                <ol class="mt-7 space-y-8">
                    <li class="flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-500/15 text-sm font-semibold text-sky-300">1</span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold">Abilita le due API Google Analytics</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-400">Nel medesimo progetto abilita <strong class="text-slate-200">Google Analytics Admin API</strong>, usata per trovare account e proprietà, e <strong class="text-slate-200">Google Analytics Data API</strong>, usata per leggere i report. In entrambe le pagine premi <strong class="text-slate-200">Abilita</strong>; se vedi “Gestisci”, l’API è già attiva.</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <a href="https://console.cloud.google.com/apis/library/analyticsadmin.googleapis.com" target="_blank" rel="noreferrer" class="inline-flex rounded-lg border border-sky-500/30 px-3 py-2 text-sm font-medium text-sky-300 no-underline hover:bg-sky-500/10">Apri Admin API ↗</a>
                                <a href="https://console.cloud.google.com/apis/library/analyticsdata.googleapis.com" target="_blank" rel="noreferrer" class="inline-flex rounded-lg border border-sky-500/30 px-3 py-2 text-sm font-medium text-sky-300 no-underline hover:bg-sky-500/10">Apri Data API ↗</a>
                            </div>
                        </div>
                    </li>
                    <li class="flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-500/15 text-sm font-semibold text-sky-300">2</span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold">Completa la schermata di consenso</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-400">Dal tuo schermo clicca <strong class="text-slate-200">Configura schermata di consenso</strong>. Se Google mostra “Inizia”, compilala così:</p>
                            <ul class="mt-3 list-disc space-y-2 pl-5 text-sm leading-6 text-slate-400">
                                <li><strong class="text-slate-200">Nome applicazione:</strong> {{ config('app.name', 'Monitoring Hub') }}</li>
                                <li><strong class="text-slate-200">Email assistenza utenti:</strong> un tuo indirizzo controllato</li>
                                <li><strong class="text-slate-200">Pubblico:</strong> “Esterno” per account Gmail o utenti fuori dalla tua organizzazione; “Interno” solo per utenti dello stesso Google Workspace</li>
                                <li><strong class="text-slate-200">Email di contatto:</strong> un tuo indirizzo controllato</li>
                            </ul>
                            <a href="https://console.cloud.google.com/auth/branding" target="_blank" rel="noreferrer" class="mt-3 inline-flex rounded-lg border border-sky-500/30 px-3 py-2 text-sm font-medium text-sky-300 no-underline hover:bg-sky-500/10">Apri configurazione consenso ↗</a>
                        </div>
                    </li>
                    <li class="flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-500/15 text-sm font-semibold text-sky-300">3</span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold">Autorizza il tuo account di prova</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-400">Se hai scelto <strong class="text-slate-200">Esterno</strong> e lo stato è “Test”, apri <strong class="text-slate-200">Pubblico → Utenti di test</strong> e aggiungi l’account Google che userai per collegare Analytics.</p>
                            <p class="mt-2 text-xs leading-5 text-amber-300">In modalità Test il collegamento serve per provare il flusso, ma il refresh token può scadere dopo 7 giorni. Per un’installazione stabile dovrai passare l’app “In produzione” e seguire le eventuali richieste di verifica di Google.</p>
                            <a href="https://console.cloud.google.com/auth/audience" target="_blank" rel="noreferrer" class="mt-3 inline-flex rounded-lg border border-sky-500/30 px-3 py-2 text-sm font-medium text-sky-300 no-underline hover:bg-sky-500/10">Apri Pubblico e utenti di test ↗</a>
                        </div>
                    </li>
                    <li class="flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-500/15 text-sm font-semibold text-sky-300">4</span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold">Aggiungi l’ambito Analytics in sola lettura</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-400">In <strong class="text-slate-200">Accesso ai dati</strong>, scegli “Aggiungi o rimuovi ambiti” e cerca questo valore:</p>
                            <div class="mt-3 flex items-start gap-2 rounded-xl border border-slate-700 bg-slate-950 p-3">
                                <code id="analytics-scope" class="min-w-0 flex-1 break-all text-xs leading-5 text-slate-200">https://www.googleapis.com/auth/analytics.readonly</code>
                                <button type="button" data-copy-target="analytics-scope" class="shrink-0 rounded-lg border border-slate-600 bg-slate-900 px-3 py-1.5 text-xs font-semibold text-slate-200 hover:border-sky-400">Copia</button>
                            </div>
                            <a href="https://console.cloud.google.com/auth/scopes" target="_blank" rel="noreferrer" class="mt-3 inline-flex rounded-lg border border-sky-500/30 px-3 py-2 text-sm font-medium text-sky-300 no-underline hover:bg-sky-500/10">Apri Accesso ai dati ↗</a>
                        </div>
                    </li>
                    <li class="flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-500/15 text-sm font-semibold text-sky-300">5</span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold">Crea l’ID client OAuth</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-400">Torna in <strong class="text-slate-200">Credenziali</strong>, clicca <strong class="text-slate-200">Crea credenziali → ID client OAuth</strong> e scegli:</p>
                            <ul class="mt-3 list-disc space-y-2 pl-5 text-sm leading-6 text-slate-400">
                                <li><strong class="text-slate-200">Tipo di applicazione:</strong> Applicazione web</li>
                                <li><strong class="text-slate-200">Nome:</strong> {{ config('app.name', 'Monitoring Hub') }}</li>
                                <li><strong class="text-slate-200">Origini JavaScript autorizzate:</strong> lascia vuoto</li>
                                <li><strong class="text-slate-200">URI di reindirizzamento autorizzati:</strong> aggiungi esattamente il valore qui sotto</li>
                            </ul>
                            <div class="mt-3 flex items-start gap-2 rounded-xl border border-slate-700 bg-slate-950 p-3">
                                <code id="google-callback-uri" class="min-w-0 flex-1 break-all text-xs leading-5 text-slate-200">{{ $redirectUri }}</code>
                                <button type="button" data-copy-target="google-callback-uri" class="shrink-0 rounded-lg border border-slate-600 bg-slate-900 px-3 py-1.5 text-xs font-semibold text-slate-200 hover:border-sky-400">Copia URI</button>
                            </div>
                            <p class="mt-2 text-xs leading-5 text-slate-500">Questo indirizzo deriva dalla configurazione dell’installazione e deve coincidere esattamente con quello registrato in Google Cloud.</p>
                            <a href="https://console.cloud.google.com/auth/clients" target="_blank" rel="noreferrer" class="mt-3 inline-flex rounded-lg border border-sky-500/30 px-3 py-2 text-sm font-medium text-sky-300 no-underline hover:bg-sky-500/10">Apri Client OAuth ↗</a>
                        </div>
                    </li>
                    <li class="flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-500/15 text-sm font-semibold text-emerald-300">6</span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold">Copia Client ID e Client Secret</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-400">Premi <strong class="text-slate-200">Crea</strong>. Google mostra i due valori: incollali nel modulo a destra e salvali. Non serve scaricare o caricare un file JSON.</p>
                        </div>
                    </li>
                </ol>

                <div class="mt-8 flex flex-wrap gap-4 text-sm">
                    <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noreferrer" class="font-medium text-sky-400">Torna alla pagina Credenziali ↗</a>
                    <a href="https://developers.google.com/identity/protocols/oauth2/web-server" target="_blank" rel="noreferrer" class="font-medium text-sky-400">Guida OAuth ufficiale ↗</a>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-800 bg-slate-900 p-8" aria-labelledby="credentials-heading">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="credentials-heading" class="text-xl font-semibold">Credenziali dell’applicazione</h2>
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
                        <p class="font-semibold">Questa installazione usa la configurazione avanzata del server.</p>
                        <p class="mt-2">Le variabili d’ambiente hanno la precedenza e, per sicurezza, non possono essere lette o modificate da questa pagina. Il gestore del server può aggiornarle nel file <code class="rounded bg-slate-950/50 px-1.5 py-0.5 text-xs">.env</code>.</p>
                    </div>
                @else
                    @if ($setting?->status === 'error' && $setting->last_error)
                        <div class="mt-7 rounded-xl border border-rose-500/20 bg-rose-500/10 p-4 text-sm leading-6 text-rose-100">
                            <p class="font-semibold">Ultima verifica non riuscita</p>
                            <p class="mt-1">{{ $setting->last_error }}</p>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('settings.google-analytics.update') }}" class="mt-7 space-y-6">
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
                                <p class="mt-2 text-xs leading-5 text-amber-300">Non è modificabile finché esistono collegamenti Google attivi, perché i token appartengono a questo client.</p>
                            @endif
                        </div>

                        <div>
                            <label for="client_secret" class="block text-sm font-medium text-slate-200">Client Secret @unless($setting)<span class="text-rose-300">*</span>@endunless</label>
                            <input id="client_secret" name="client_secret" type="password" value="" autocomplete="new-password" spellcheck="false" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-slate-100">
                            @if ($setting)
                                <p class="mt-2 text-xs leading-5 text-slate-500">Il segreto salvato non viene mai mostrato. Lascia vuoto per mantenerlo oppure inseriscine uno nuovo per sostituirlo.</p>
                            @else
                                <p class="mt-2 text-xs leading-5 text-slate-500">È il valore riservato mostrato da Google Cloud. Non condividerlo e non inserirlo nel repository.</p>
                            @endif
                        </div>

                        <button type="submit" class="w-full rounded-xl bg-sky-500 px-5 py-3 font-semibold text-slate-950 hover:bg-sky-400">{{ $setting ? 'Salva modifiche' : 'Salva credenziali' }}</button>
                    </form>

                    @if ($setting && ! $hasConnections)
                        <form method="POST" action="{{ route('settings.google-analytics.destroy') }}" class="mt-4" onsubmit="return confirm('Vuoi rimuovere le credenziali Google Analytics da questa installazione?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="w-full border border-rose-500/30 bg-transparent text-rose-300 hover:bg-rose-500/10">Rimuovi configurazione</button>
                        </form>
                    @endif
                @endif

                <div class="mt-7 border-t border-slate-800 pt-5 text-xs leading-5 text-slate-500">
                    <p>La verifica effettiva avviene quando colleghi il primo account Google. Le credenziali permettono il login OAuth; l’accesso ai dati dipende dall’account autorizzato.</p>
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
