@extends('layouts.workspace')

@section('title', 'Impostazioni Google Search Console — '.config('app.name', 'Monitoring Hub'))

@section('page')
    <main id="main-content" class="ui-page ui-page-narrow" tabindex="-1">
        <x-page-header title="Google Search Console" :kicker="$site->name" description="Configura l’accesso in sola lettura per collegare una proprietà Search Console a questo sito.">
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

        <section class="mb-8 rounded-2xl border border-accent/25 bg-accent-soft p-6" aria-labelledby="reuse-google-heading">
            <h2 id="reuse-google-heading" class="text-lg font-semibold text-accent-ink">Hai già configurato Google Analytics?</h2>
            <p class="mt-2 text-sm leading-6 text-accent-ink">Puoi riutilizzare lo stesso progetto Google Cloud e lo stesso client OAuth. Nel client esistente aggiungi anche il nuovo URI di reindirizzamento mostrato sotto, abilita la Search Console API e aggiungi lo scope <code class="rounded bg-subtle px-1.5 py-0.5 text-xs">webmasters.readonly</code>. Poi inserisci qui lo stesso Client ID e Client Secret. La dashboard conserva i collegamenti separatamente.</p>
            <p class="mt-2 text-xs leading-5 text-accent-ink">Google gestisce però consenso e revoca a livello di progetto Cloud: se riutilizzi lo stesso progetto e in seguito revochi un collegamento, potrebbe essere necessario autorizzare nuovamente anche l’altro servizio. Usa due progetti distinti soltanto se ti serve isolamento completo delle autorizzazioni.</p>
        </section>

        <div class="grid gap-8 lg:grid-cols-[1.2fr_0.8fr]">
            <section class="ui-panel p-5 sm:p-7" aria-labelledby="search-console-setup-heading">
                <h2 id="search-console-setup-heading" class="text-xl font-semibold">Procedura completa</h2>
                <p class="mt-2 text-sm leading-6 text-muted">Segui i passaggi nell’ordine usando un account che possa accedere alla proprietà Search Console del sito.</p>

                <ol class="mt-7 space-y-8">
                    <li class="flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft text-sm font-semibold text-accent-ink">1</span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold">Controlla la proprietà in Search Console</h3>
                            <p class="mt-1 text-sm leading-6 text-muted">Apri Search Console e verifica che l’account Google possa vedere il sito. Sono supportate sia le proprietà Dominio, come <code>sc-domain:example.com</code>, sia le proprietà con prefisso URL, come <code>https://www.example.com/</code>. Se il sito non compare, aggiungilo e completa prima la verifica richiesta da Google.</p>
                            <a href="https://search.google.com/search-console" target="_blank" rel="noreferrer" class="mt-3 ui-button ui-button-small">Apri Search Console ↗</a>
                        </div>
                    </li>
                    <li class="flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft text-sm font-semibold text-accent-ink">2</span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold">Abilita la Search Console API</h3>
                            <p class="mt-1 text-sm leading-6 text-muted">Nel progetto Google Cloud scelto abilita <strong class="text-soft">Google Search Console API</strong>. Premi “Abilita”; se compare “Gestisci”, l’API è già attiva.</p>
                            <a href="https://console.cloud.google.com/apis/library/searchconsole.googleapis.com" target="_blank" rel="noreferrer" class="mt-3 ui-button ui-button-small">Apri Search Console API ↗</a>
                        </div>
                    </li>
                    <li class="flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft text-sm font-semibold text-accent-ink">3</span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold">Configura consenso e utenti di test</h3>
                            <p class="mt-1 text-sm leading-6 text-muted">In <strong class="text-soft">Google Auth Platform</strong> completa Branding e Pubblico. Se l’app è “Esterna” e in modalità Test, aggiungi tra gli utenti di test l’account che userai per collegare Search Console. Se hai già completato questi passaggi per Analytics nello stesso progetto, non devi ripeterli.</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <a href="https://console.cloud.google.com/auth/branding" target="_blank" rel="noreferrer" class="ui-button ui-button-small">Apri Branding ↗</a>
                                <a href="https://console.cloud.google.com/auth/audience" target="_blank" rel="noreferrer" class="ui-button ui-button-small">Apri Pubblico ↗</a>
                            </div>
                        </div>
                    </li>
                    <li class="flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft text-sm font-semibold text-accent-ink">4</span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold">Aggiungi il solo ambito di lettura</h3>
                            <p class="mt-1 text-sm leading-6 text-muted">In <strong class="text-soft">Accesso ai dati → Aggiungi o rimuovi ambiti</strong> cerca e aggiungi esattamente:</p>
                            <div class="mt-3 flex items-start gap-2 rounded-xl border border-line-strong bg-subtle p-3">
                                <code id="search-console-scope" class="min-w-0 flex-1 break-all text-xs leading-5 text-soft">https://www.googleapis.com/auth/webmasters.readonly</code>
                                <button type="button" data-copy-target="search-console-scope" class="shrink-0 ui-button ui-button-small">Copia</button>
                            </div>
                            <p class="mt-2 text-xs leading-5 text-muted">Questo permesso consente di leggere proprietà e report, ma non di modificare Search Console o il sito.</p>
                            <a href="https://console.cloud.google.com/auth/scopes" target="_blank" rel="noreferrer" class="mt-3 ui-button ui-button-small">Apri Accesso ai dati ↗</a>
                        </div>
                    </li>
                    <li class="flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft text-sm font-semibold text-accent-ink">5</span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold">Crea o aggiorna il client OAuth web</h3>
                            <p class="mt-1 text-sm leading-6 text-muted">In <strong class="text-soft">Client</strong> crea un client di tipo “Applicazione web”, oppure apri quello già usato da Analytics. Lascia vuote le origini JavaScript e aggiungi tra gli <strong class="text-soft">URI di reindirizzamento autorizzati</strong> questo valore esatto:</p>
                            <div class="mt-3 flex items-start gap-2 rounded-xl border border-line-strong bg-subtle p-3">
                                <code id="search-console-callback-uri" class="min-w-0 flex-1 break-all text-xs leading-5 text-soft">{{ $redirectUri }}</code>
                                <button type="button" data-copy-target="search-console-callback-uri" class="shrink-0 ui-button ui-button-small">Copia URI</button>
                            </div>
                            <p class="mt-2 text-xs leading-5 text-muted">Protocollo, dominio, porta, percorso e slash devono coincidere. Non sostituire questo valore con l’indirizzo del sito monitorato.</p>
                            <a href="https://console.cloud.google.com/auth/clients" target="_blank" rel="noreferrer" class="mt-3 ui-button ui-button-small">Apri Client OAuth ↗</a>
                        </div>
                    </li>
                    <li class="flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-success-soft text-sm font-semibold text-success-ink">6</span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold">Salva Client ID e Client Secret</h3>
                            <p class="mt-1 text-sm leading-6 text-muted">Copia i due valori nel modulo a destra. Il Client Secret sarà cifrato nel database e non verrà più mostrato. Non inserirlo in GitHub, nella documentazione o negli screenshot.</p>
                        </div>
                    </li>
                </ol>

                <div class="mt-8 flex flex-wrap gap-4 text-sm">
                    <a href="https://developers.google.com/webmaster-tools/v1/how-tos/authorizing" target="_blank" rel="noreferrer" class="font-medium text-accent-ink">Autorizzazione ufficiale ↗</a>
                    <a href="https://developers.google.com/webmaster-tools/v1/sites/list" target="_blank" rel="noreferrer" class="font-medium text-accent-ink">Elenco proprietà API ↗</a>
                </div>
            </section>

            <section class="ui-panel p-5 sm:p-7" aria-labelledby="search-console-credentials-heading">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="search-console-credentials-heading" class="text-xl font-semibold">Credenziali dell’applicazione</h2>
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
                        <p class="font-semibold">Credenziali gestite dal server.</p>
                        <p class="mt-2">Le variabili d’ambiente hanno la precedenza e, per sicurezza, non possono essere lette o modificate dalla dashboard.</p>
                    </div>
                @else
                    @if ($setting?->status === 'error' && $setting->last_error)
                        <div class="mt-7 rounded-xl border border-danger-ink/22 bg-danger-soft p-4 text-sm leading-6 text-danger-ink">
                            <p class="font-semibold">Ultima verifica non riuscita</p>
                            <p class="mt-1">{{ $setting->last_error }}</p>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('google-search-console.settings.update', $site) }}" class="mt-7 space-y-6">
                        @csrf
                        @method('PUT')

                        <div>
                            <label for="client_id" class="block text-sm font-medium text-soft">Client ID <span class="text-danger-ink">*</span></label>
                            <input id="client_id" name="client_id" type="text" value="{{ old('client_id', $clientId) }}" required autocomplete="off" spellcheck="false" @readonly($hasConnections) class="mt-2 w-full rounded-xl border border-line-strong bg-subtle px-4 py-3 text-sm text-ink read-only:cursor-not-allowed read-only:opacity-60">
                            <p class="mt-2 text-xs leading-5 text-muted">È l’identificativo pubblico che termina normalmente con <code>apps.googleusercontent.com</code>.</p>
                            @if ($hasConnections)
                                <p class="mt-2 text-xs leading-5 text-warning-ink">Non è modificabile finché esistono collegamenti Search Console attivi.</p>
                            @endif
                        </div>

                        <div>
                            <label for="client_secret" class="block text-sm font-medium text-soft">Client Secret @unless($setting)<span class="text-danger-ink">*</span>@endunless</label>
                            <input id="client_secret" name="client_secret" type="password" value="" autocomplete="new-password" spellcheck="false" class="mt-2 w-full rounded-xl border border-line-strong bg-subtle px-4 py-3 text-sm text-ink">
                            @if ($setting)
                                <p class="mt-2 text-xs leading-5 text-muted">Lascia vuoto per mantenere il segreto salvato oppure inseriscine uno nuovo per sostituirlo.</p>
                            @else
                                <p class="mt-2 text-xs leading-5 text-muted">Il valore è riservato: verrà cifrato e non sarà mai mostrato dopo il salvataggio.</p>
                            @endif
                        </div>

                        <button type="submit" class="w-full ui-button ui-button-primary">{{ $setting ? 'Salva modifiche' : 'Salva credenziali' }}</button>
                    </form>

                    @if ($setting && ! $hasConnections)
                        <p class="mt-6 text-xs leading-5 text-muted">Hai inserito credenziali errate? Puoi correggerle nel modulo qui sopra oppure rimuoverle e ricominciare. La rimozione non modifica il client nella Google Cloud Console.</p>
                        <form method="POST" action="{{ route('google-search-console.settings.destroy', $site) }}" class="mt-3" data-confirm="Vuoi rimuovere le credenziali Search Console da questa installazione?" onsubmit="return confirm(this.dataset.confirm)">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="w-full ui-button ui-button-danger">Rimuovi credenziali OAuth</button>
                        </form>
                    @elseif ($setting && $hasConnections)
                        <p class="mt-6 text-xs leading-5 text-warning-ink">Per cambiare Client ID o rimuovere le credenziali, scollega prima Search Console da tutti i siti. I token già autorizzati appartengono al client attuale.</p>
                    @endif
                @endif

                <div class="mt-7 border-t border-line pt-5 text-xs leading-5 text-muted">
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
