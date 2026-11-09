@extends('layouts.workspace')

@section('title', 'Impostazioni Google Analytics — '.config('app.name', 'Monitoring Hub'))

@section('page')
    <main id="main-content" class="ui-page ui-page-narrow" tabindex="-1">
        <x-page-header title="Google Analytics 4" :kicker="$site->name" description="Configura l’accesso in sola lettura per collegare una proprietà Analytics a questo sito.">
            <x-slot:back>
                <a href="{{ route('sites.edit', $site) }}#integrations-heading">← Servizi di {{ $site->name }}</a>
            </x-slot:back>
        </x-page-header>

        <x-google-settings-tabs :site="$site" />

        @if (session('status'))
            <div class="mb-6 ui-alert ui-tone-success" role="status">{{ session('status') }}</div>
        @endif

        @if (session('integration_error'))
            <div class="mb-6 ui-alert ui-tone-danger" role="alert">{{ session('integration_error') }}</div>
        @endif

        @if ($errors->any())
            <div class="mb-6 ui-alert ui-tone-danger" role="alert">
                <p class="font-semibold">Controlla i dati inseriti:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="mb-8 rounded-2xl border border-warning-ink/22 bg-warning-soft p-6" aria-labelledby="current-google-screen-heading">
            <h2 id="current-google-screen-heading" class="text-lg font-semibold text-warning-ink">Sei già nella schermata giusta</h2>
            <p class="mt-2 text-sm leading-6 text-warning-ink">In <strong>API e servizi → Credenziali</strong>, la sezione “ID client OAuth 2.0” è vuota perché devi prima completare la schermata di consenso. Clicca il pulsante giallo <strong>Configura schermata di consenso</strong>. Ignora invece <strong>Chiavi API</strong> e <strong>Service account</strong>: non servono a questo collegamento.</p>
            <p class="mt-2 text-xs leading-5 text-warning-ink">Il banner relativo alla prova gratuita di Google Cloud non è il passaggio che ti sta bloccando.</p>
        </section>

        <div class="grid gap-8 lg:grid-cols-[1.2fr_0.8fr]">
            <section class="ui-panel p-5 sm:p-7" aria-labelledby="google-setup-heading">
                <h2 id="google-setup-heading" class="text-xl font-semibold">Procedura completa in Google Cloud</h2>
                <p class="mt-2 text-sm leading-6 text-muted">Segui i passaggi nell’ordine. Mantieni selezionato lo stesso progetto Google Cloud dall’inizio alla fine.</p>

                <ol class="mt-7 space-y-8">
                    <li class="flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft text-sm font-semibold text-accent-ink">1</span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold">Abilita le due API Google Analytics</h3>
                            <p class="mt-1 text-sm leading-6 text-muted">Nel medesimo progetto abilita <strong class="text-soft">Google Analytics Admin API</strong>, usata per trovare account e proprietà, e <strong class="text-soft">Google Analytics Data API</strong>, usata per leggere i report. In entrambe le pagine premi <strong class="text-soft">Abilita</strong>; se vedi “Gestisci”, l’API è già attiva.</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <a href="https://console.cloud.google.com/apis/library/analyticsadmin.googleapis.com" target="_blank" rel="noreferrer" class="ui-button ui-button-small">Apri Admin API ↗</a>
                                <a href="https://console.cloud.google.com/apis/library/analyticsdata.googleapis.com" target="_blank" rel="noreferrer" class="ui-button ui-button-small">Apri Data API ↗</a>
                            </div>
                        </div>
                    </li>
                    <li class="flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft text-sm font-semibold text-accent-ink">2</span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold">Completa la schermata di consenso</h3>
                            <p class="mt-1 text-sm leading-6 text-muted">Dal tuo schermo clicca <strong class="text-soft">Configura schermata di consenso</strong>. Se Google mostra “Inizia”, compilala così:</p>
                            <ul class="mt-3 list-disc space-y-2 pl-5 text-sm leading-6 text-muted">
                                <li><strong class="text-soft">Nome applicazione:</strong> {{ config('app.name', 'Monitoring Hub') }}</li>
                                <li><strong class="text-soft">Email assistenza utenti:</strong> un tuo indirizzo controllato</li>
                                <li><strong class="text-soft">Pubblico:</strong> “Esterno” per account Gmail o utenti fuori dalla tua organizzazione; “Interno” solo per utenti dello stesso Google Workspace</li>
                                <li><strong class="text-soft">Email di contatto:</strong> un tuo indirizzo controllato</li>
                            </ul>
                            <a href="https://console.cloud.google.com/auth/branding" target="_blank" rel="noreferrer" class="mt-3 ui-button ui-button-small">Apri configurazione consenso ↗</a>
                        </div>
                    </li>
                    <li class="flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft text-sm font-semibold text-accent-ink">3</span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold">Autorizza il tuo account di prova</h3>
                            <p class="mt-1 text-sm leading-6 text-muted">Se hai scelto <strong class="text-soft">Esterno</strong> e lo stato è “Test”, apri <strong class="text-soft">Pubblico → Utenti di test</strong> e aggiungi l’account Google che userai per collegare Analytics.</p>
                            <p class="mt-2 text-xs leading-5 text-warning-ink">In modalità Test il collegamento serve per provare il flusso, ma il refresh token può scadere dopo 7 giorni. Per un’installazione stabile dovrai passare l’app “In produzione” e seguire le eventuali richieste di verifica di Google.</p>
                            <a href="https://console.cloud.google.com/auth/audience" target="_blank" rel="noreferrer" class="mt-3 ui-button ui-button-small">Apri Pubblico e utenti di test ↗</a>
                        </div>
                    </li>
                    <li class="flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft text-sm font-semibold text-accent-ink">4</span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold">Aggiungi l’ambito Analytics in sola lettura</h3>
                            <p class="mt-1 text-sm leading-6 text-muted">In <strong class="text-soft">Accesso ai dati</strong>, scegli “Aggiungi o rimuovi ambiti” e cerca questo valore:</p>
                            <div class="mt-3 flex items-start gap-2 rounded-xl border border-line-strong bg-subtle p-3">
                                <code id="analytics-scope" class="min-w-0 flex-1 break-all text-xs leading-5 text-soft">https://www.googleapis.com/auth/analytics.readonly</code>
                                <button type="button" data-copy-target="analytics-scope" class="shrink-0 ui-button ui-button-small">Copia</button>
                            </div>
                            <a href="https://console.cloud.google.com/auth/scopes" target="_blank" rel="noreferrer" class="mt-3 ui-button ui-button-small">Apri Accesso ai dati ↗</a>
                        </div>
                    </li>
                    <li class="flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft text-sm font-semibold text-accent-ink">5</span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold">Crea l’ID client OAuth</h3>
                            <p class="mt-1 text-sm leading-6 text-muted">Torna in <strong class="text-soft">Credenziali</strong>, clicca <strong class="text-soft">Crea credenziali → ID client OAuth</strong> e scegli:</p>
                            <ul class="mt-3 list-disc space-y-2 pl-5 text-sm leading-6 text-muted">
                                <li><strong class="text-soft">Tipo di applicazione:</strong> Applicazione web</li>
                                <li><strong class="text-soft">Nome:</strong> {{ config('app.name', 'Monitoring Hub') }}</li>
                                <li><strong class="text-soft">Origini JavaScript autorizzate:</strong> lascia vuoto</li>
                                <li><strong class="text-soft">URI di reindirizzamento autorizzati:</strong> aggiungi esattamente il valore qui sotto</li>
                            </ul>
                            <div class="mt-3 flex items-start gap-2 rounded-xl border border-line-strong bg-subtle p-3">
                                <code id="google-callback-uri" class="min-w-0 flex-1 break-all text-xs leading-5 text-soft">{{ $redirectUri }}</code>
                                <button type="button" data-copy-target="google-callback-uri" class="shrink-0 ui-button ui-button-small">Copia URI</button>
                            </div>
                            <p class="mt-2 text-xs leading-5 text-muted">Questo indirizzo deriva dalla configurazione dell’installazione e deve coincidere esattamente con quello registrato in Google Cloud.</p>
                            <a href="https://console.cloud.google.com/auth/clients" target="_blank" rel="noreferrer" class="mt-3 ui-button ui-button-small">Apri Client OAuth ↗</a>
                        </div>
                    </li>
                    <li class="flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-success-soft text-sm font-semibold text-success-ink">6</span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold">Copia Client ID e Client Secret</h3>
                            <p class="mt-1 text-sm leading-6 text-muted">Premi <strong class="text-soft">Crea</strong>. Google mostra i due valori: incollali nel modulo a destra e salvali. Non serve scaricare o caricare un file JSON.</p>
                        </div>
                    </li>
                </ol>

                <div class="mt-8 flex flex-wrap gap-4 text-sm">
                    <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noreferrer" class="font-medium text-accent-ink">Torna alla pagina Credenziali ↗</a>
                    <a href="https://developers.google.com/identity/protocols/oauth2/web-server" target="_blank" rel="noreferrer" class="font-medium text-accent-ink">Guida OAuth ufficiale ↗</a>
                </div>
            </section>

            <section class="ui-panel p-5 sm:p-7" aria-labelledby="credentials-heading">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="credentials-heading" class="text-xl font-semibold">Credenziali dell’applicazione</h2>
                        <p class="mt-2 text-sm text-muted">Valide per tutti i siti registrati.</p>
                    </div>

                    @if ($environmentManaged)
                        <span class="rounded-full bg-accent-soft px-3 py-1 text-xs font-medium text-accent-ink">Gestite dall’ambiente</span>
                    @elseif ($setting?->status === 'verified')
                        <span class="rounded-full bg-success-soft px-3 py-1 text-xs font-medium text-success-ink">Verificate</span>
                    @elseif ($setting?->status === 'error')
                        <span class="rounded-full bg-danger-soft px-3 py-1 text-xs font-medium text-danger-ink">Da controllare</span>
                    @elseif ($setting)
                        <span class="rounded-full bg-warning-soft px-3 py-1 text-xs font-medium text-warning-ink">Salvate, da verificare</span>
                    @else
                        <span class="rounded-full bg-subtle px-3 py-1 text-xs font-medium text-soft">Non configurate</span>
                    @endif
                </div>

                @if ($environmentManaged)
                    <div class="mt-7 rounded-xl border border-accent/25 bg-accent-soft p-4 text-sm leading-6 text-accent-ink">
                        <p class="font-semibold">Questa installazione usa la configurazione avanzata del server.</p>
                        <p class="mt-2">Le variabili d’ambiente hanno la precedenza e, per sicurezza, non possono essere lette o modificate da questa pagina. Il gestore del server può aggiornarle nel file <code class="rounded bg-subtle px-1.5 py-0.5 text-xs">.env</code>.</p>
                    </div>
                @else
                    @if ($setting?->status === 'error' && $setting->last_error)
                        <div class="mt-7 rounded-xl border border-danger-ink/22 bg-danger-soft p-4 text-sm leading-6 text-danger-ink">
                            <p class="font-semibold">Ultima verifica non riuscita</p>
                            <p class="mt-1">{{ $setting->last_error }}</p>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('google-analytics.settings.update', $site) }}" class="mt-7 space-y-6">
                        @csrf
                        @method('PUT')

                        <div>
                            <label for="client_id" class="block text-sm font-medium text-soft">Client ID <span class="text-danger-ink">*</span></label>
                            <input id="client_id" name="client_id" type="text" value="{{ old('client_id', $clientId) }}" required autocomplete="off" spellcheck="false" @readonly($hasConnections) class="mt-2 w-full rounded-xl border border-line-strong bg-subtle px-4 py-3 text-sm text-ink read-only:cursor-not-allowed read-only:opacity-60">
                            <p class="mt-2 text-xs leading-5 text-muted">È l’identificativo pubblico che termina normalmente con <code>apps.googleusercontent.com</code>.</p>
                            @if ($hasConnections)
                                <p class="mt-2 text-xs leading-5 text-warning-ink">Non è modificabile finché esistono collegamenti Google attivi, perché i token appartengono a questo client.</p>
                            @endif
                        </div>

                        <div>
                            <label for="client_secret" class="block text-sm font-medium text-soft">Client Secret @unless($setting)<span class="text-danger-ink">*</span>@endunless</label>
                            <input id="client_secret" name="client_secret" type="password" value="" autocomplete="new-password" spellcheck="false" class="mt-2 w-full rounded-xl border border-line-strong bg-subtle px-4 py-3 text-sm text-ink">
                            @if ($setting)
                                <p class="mt-2 text-xs leading-5 text-muted">Il segreto salvato non viene mai mostrato. Lascia vuoto per mantenerlo oppure inseriscine uno nuovo per sostituirlo.</p>
                            @else
                                <p class="mt-2 text-xs leading-5 text-muted">È il valore riservato mostrato da Google Cloud. Non condividerlo e non inserirlo nel repository.</p>
                            @endif
                        </div>

                        <button type="submit" class="w-full ui-button ui-button-primary">{{ $setting ? 'Salva modifiche' : 'Salva credenziali' }}</button>
                    </form>

                    @if ($setting && ! $hasConnections)
                        <form method="POST" action="{{ route('google-analytics.settings.destroy', $site) }}" class="mt-4" data-confirm="Vuoi rimuovere le credenziali Google Analytics da questa installazione?" onsubmit="return confirm(this.dataset.confirm)">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="w-full ui-button ui-button-danger">Rimuovi configurazione</button>
                        </form>
                    @endif
                @endif

                <div class="mt-7 border-t border-line pt-5 text-xs leading-5 text-muted">
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
