@extends('layouts.workspace')

@section('title', 'Pubblicazione su X — '.config('app.name', 'Monitoring Hub'))

@section('page')
    <main id="main-content" class="ui-page" tabindex="-1">
        <x-page-header title="Pubblicazione su X" kicker="Social publishing" description="Configura una sola app X per questa installazione e autorizza l’account che pubblicherà. Le credenziali e i token sono cifrati; nessun post viene inviato durante la configurazione.">
            <x-slot:status><span class="ui-badge">Provider iniziale: X</span></x-slot:status>
        </x-page-header>

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

        <div class="grid gap-8 lg:grid-cols-[1.08fr_0.92fr]">
            <div class="space-y-8">
                <section class="ui-panel p-5 sm:p-7" aria-labelledby="x-guide-heading">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-accent-ink">Guida passo passo</p>
                            <h2 id="x-guide-heading" class="mt-2 text-xl font-semibold">Prepara l’app nel Developer Console</h2>
                        </div>
                        <a href="https://developer.x.com/en/portal/dashboard" target="_blank" rel="noreferrer" class="shrink-0 ui-button ui-button-small">Apri X Developer ↗</a>
                    </div>

                    <div class="mt-7 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl border border-accent/25 bg-accent-soft p-4">
                            <p class="text-sm font-semibold text-accent-ink">Da recuperare nella Developer Console X</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-xs leading-5 text-muted">
                                <li>approvazione del caso d’uso e piano API;</li>
                                <li>progetto e app associata;</li>
                                <li>Client ID e Client Secret OAuth 2.0;</li>
                                <li>permessi e stato dell’app.</li>
                            </ul>
                        </div>
                        <div class="rounded-2xl border border-accent/25 bg-accent-soft p-4">
                            <p class="text-sm font-semibold text-accent-ink">Fornito da questa dashboard</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-xs leading-5 text-muted">
                                <li>Callback URI esatto;</li>
                                <li>scope OAuth minimi richiesti;</li>
                                <li>account verificato dopo il consenso.</li>
                            </ul>
                        </div>
                    </div>

                    <ol class="mt-7 space-y-7">
                        <li class="flex gap-4">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft text-sm font-semibold text-accent-ink">1</span>
                            <div class="min-w-0 flex-1">
                                <h3 class="font-semibold">Completa Developer Agreement &amp; Policy</h3>
                                <p class="mt-1 text-sm leading-6 text-muted">X mostra il campo <strong class="text-soft">“Describe all of your use cases of X’s data and API”</strong> prima di consentire l’accesso alla piattaforma. Descrivi l’uso reale e previsto, chi controlla l’account, quali dati vengono letti e quali azioni saranno automatizzate. Non dichiarare funzionalità diverse da quelle effettivamente usate.</p>
                                <div class="mt-3 rounded-xl border border-line-strong bg-subtle p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-muted">Testo di esempio in inglese — adattalo al tuo caso reale</p>
                                        <button type="button" data-copy-target="x-use-case-example" aria-live="polite" class="shrink-0 ui-button ui-button-small">Copia testo</button>
                                    </div>
                                    <p id="x-use-case-example" class="mt-3 whitespace-pre-line text-xs leading-6 text-soft">This is a private administrative application used by a small team to manage an X account that the team owns or is authorized to operate. The application will use the official X API to identify the authorized account, publish administrator-approved posts immediately or at scheduled times, and monitor post-level delivery and performance metadata. It will not scrape X, access Direct Messages, perform automated replies, follows, likes, or unsolicited interactions, sell or share X data, or use X data to train AI models. OAuth credentials and tokens are encrypted, access is restricted to administrators, and the account owner can revoke access.</p>
                                </div>
                                <p class="mt-3 text-xs leading-5 text-warning-ink">Prima di inviarlo, rimuovi ogni frase che non corrisponde al tuo utilizzo e aggiungi eventuali altri usi effettivi. L’approvazione e le informazioni inserite restano responsabilità del titolare dell’account sviluppatore.</p>
                            </div>
                        </li>
                        <li class="flex gap-4">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft text-sm font-semibold text-accent-ink">2</span>
                            <div>
                                <h3 class="font-semibold">Crea un progetto e un’app</h3>
                                <p class="mt-1 text-sm leading-6 text-muted">Accedi con l’account che gestisce il progetto X, abilita un piano API che includa la creazione di post e crea un’app dedicata a questa installazione. Costi, quote e disponibilità dipendono dal piano mostrato da X.</p>
                            </div>
                        </li>
                        <li class="flex gap-4">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft text-sm font-semibold text-accent-ink">3</span>
                            <div>
                                <h3 class="font-semibold">Abilita OAuth 2.0</h3>
                                <p class="mt-1 text-sm leading-6 text-muted">In <strong class="text-soft">User authentication settings</strong> abilita OAuth 2.0 e scegli <strong class="text-soft">Web App, Automated App or Bot</strong>. È un client confidenziale: genera quindi sia Client ID sia Client Secret. Questi due valori vanno recuperati nella Developer Console e inseriti nel modulo a destra.</p>
                                <p class="mt-2 text-xs leading-5 text-warning-ink">Non inserire API Key, API Key Secret o Bearer Token: sono credenziali diverse e non funzionano in questo collegamento OAuth.</p>
                            </div>
                        </li>
                        <li class="flex gap-4">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft text-sm font-semibold text-accent-ink">4</span>
                            <div class="min-w-0 flex-1">
                                <h3 class="font-semibold">Registra il callback esatto</h3>
                                <p class="mt-1 text-sm leading-6 text-muted">Imposta come Callback URI / Redirect URL questo indirizzo, senza modificare protocollo, dominio, porta o slash:</p>
                                <div class="mt-3 flex items-center gap-2 rounded-xl border border-line-strong bg-subtle p-3">
                                    <code id="x-callback-uri" class="min-w-0 flex-1 break-all text-xs text-accent-ink">{{ $redirectUri }}</code>
                                    <button type="button" data-copy-target="x-callback-uri" aria-live="polite" class="shrink-0 ui-button ui-button-small">Copia URI</button>
                                </div>
                                <p class="mt-3 text-xs leading-5 text-muted">Nel campo Website URL usa l’indirizzo pubblico della dashboard. In produzione X richiede normalmente HTTPS; il callback deve essere raggiungibile dal browser dell’amministratore.</p>
                            </div>
                        </li>
                        <li class="flex gap-4">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft text-sm font-semibold text-accent-ink">5</span>
                            <div>
                                <h3 class="font-semibold">Consenti lettura e scrittura</h3>
                                <p class="mt-1 text-sm leading-6 text-muted">Seleziona i permessi <strong class="text-soft">Read and write</strong>. Il collegamento richiederà gli scope per identificare l’account, creare post, caricare immagini e rinnovare l’accesso.</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($scopes as $scope)
                                        <code class="rounded-lg bg-subtle px-2.5 py-1.5 text-xs text-soft">{{ $scope }}</code>
                                    @endforeach
                                </div>
                            </div>
                        </li>
                        <li class="flex gap-4">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft text-sm font-semibold text-accent-ink">6</span>
                            <div>
                                <h3 class="font-semibold">Salva le credenziali e collega l’account</h3>
                                <p class="mt-1 text-sm leading-6 text-muted">Copia Client ID e Client Secret nel modulo. Dopo il salvataggio usa “Collega account X” e accedi all’account che dovrà pubblicare: il nome visualizzato viene letto direttamente dall’API, non inserito a mano.</p>
                            </div>
                        </li>
                    </ol>

                    <div class="mt-8 rounded-2xl border border-warning-ink/22 bg-warning-soft p-4 text-sm leading-6 text-warning-ink">
                        <p class="font-semibold">Prima della pubblicazione automatica</p>
                        <p class="mt-1">Verifica piano, quota e regole di automazione nel tuo account X. Il collegamento abilita tecnicamente la scrittura, ma i post dovranno comunque passare dal flusso di approvazione e dalla coda dell’applicazione.</p>
                    </div>
                </section>

                <section class="ui-panel p-5 sm:p-7" aria-labelledby="x-docs-heading">
                    <h2 id="x-docs-heading" class="text-xl font-semibold">Documentazione ufficiale</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <a href="https://docs.x.com/fundamentals/authentication/oauth-2-0/authorization-code" target="_blank" rel="noreferrer" class="rounded-xl border border-line-strong p-4 text-sm text-soft no-underline hover:border-accent hover:bg-subtle">OAuth 2.0 con PKCE ↗</a>
                        <a href="https://docs.x.com/x-api/posts/create-post" target="_blank" rel="noreferrer" class="rounded-xl border border-line-strong p-4 text-sm text-soft no-underline hover:border-accent hover:bg-subtle">Creazione dei post ↗</a>
                        <a href="https://docs.x.com/fundamentals/authentication/oauth-2-0/overview" target="_blank" rel="noreferrer" class="rounded-xl border border-line-strong p-4 text-sm text-soft no-underline hover:border-accent hover:bg-subtle">Scope e autenticazione ↗</a>
                        <a href="https://docs.x.com/developer-terms/agreement" target="_blank" rel="noreferrer" class="rounded-xl border border-line-strong p-4 text-sm text-soft no-underline hover:border-accent hover:bg-subtle">Developer Agreement ↗</a>
                        <a href="https://docs.x.com/developer-terms/policy" target="_blank" rel="noreferrer" class="rounded-xl border border-line-strong p-4 text-sm text-soft no-underline hover:border-accent hover:bg-subtle">Developer Policy ↗</a>
                    </div>
                </section>
            </div>

            <div class="space-y-8">
                <section class="ui-panel p-5 sm:p-7" aria-labelledby="x-credentials-heading">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 id="x-credentials-heading" class="text-xl font-semibold">Credenziali dell’app X</h2>
                            <p class="mt-2 text-sm leading-6 text-muted">Identificano questa installazione. Il segreto viene cifrato e non viene mai mostrato dopo il salvataggio.</p>
                        </div>
                        @if ($environmentManaged)
                            <span class="rounded-full bg-accent-soft px-3 py-1 text-xs font-semibold text-accent-ink">Gestite dall’ambiente</span>
                        @elseif ($setting)
                            <span class="rounded-full bg-success-soft px-3 py-1 text-xs font-semibold text-success-ink">Salvate</span>
                        @else
                            <span class="rounded-full bg-warning-soft px-3 py-1 text-xs font-semibold text-warning-ink">Da configurare</span>
                        @endif
                    </div>

                    @if ($environmentManaged)
                        <div class="mt-6 rounded-xl border border-accent/25 bg-accent-soft p-4 text-sm leading-6 text-accent-ink">
                            Client ID e Client Secret provengono da <code>X_CLIENT_ID</code> e <code>X_CLIENT_SECRET</code>. Per sicurezza i valori non sono esposti nella dashboard.
                        </div>
                    @else
                        <form method="POST" action="{{ route('settings.x.update') }}" class="mt-6 space-y-5">
                            @csrf
                            @method('PUT')

                            <div>
                                <label for="client_id" class="mb-2 block text-sm font-medium text-soft">Client ID</label>
                                <input id="client_id" name="client_id" type="text" value="{{ old('client_id', $clientId) }}" autocomplete="off" required>
                                <p class="mt-2 text-xs leading-5 text-muted">Si trova in Keys and tokens dopo aver abilitato OAuth 2.0.</p>
                            </div>

                            <div>
                                <label for="client_secret" class="mb-2 block text-sm font-medium text-soft">Client Secret</label>
                                <input id="client_secret" name="client_secret" type="password" value="" autocomplete="new-password" {{ $setting ? '' : 'required' }}>
                                <p class="mt-2 text-xs leading-5 text-muted">{{ $setting ? 'Lascia vuoto per conservare il segreto già salvato.' : 'È un dato sensibile: non inserirlo nel repository o nei log.' }}</p>
                            </div>

                            <button type="submit" class="w-full ui-button ui-button-primary">Salva credenziali</button>
                        </form>

                        @if ($setting)
                            <form method="POST" action="{{ route('settings.x.destroy') }}" class="mt-4" data-confirm="Vuoi rimuovere le credenziali dell’app X?" onsubmit="return confirm(this.dataset.confirm)">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-full ui-button ui-button-danger">Rimuovi credenziali</button>
                            </form>
                        @endif
                    @endif
                </section>

                <section class="rounded-3xl border {{ $connection ? 'border-success-ink/22 bg-success-soft' : 'border-line bg-surface' }} p-8" aria-labelledby="x-account-heading">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-muted">Account di pubblicazione</p>
                            <h2 id="x-account-heading" class="mt-2 text-xl font-semibold">{{ $connection ? '@'.$connection->username : 'Nessun account collegato' }}</h2>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $connection ? 'bg-success-soft text-success-ink' : 'bg-subtle text-muted' }}">{{ $connection ? 'Verificato' : 'Non collegato' }}</span>
                    </div>

                    @if ($connection)
                        <div class="mt-6 flex items-center gap-4 rounded-2xl border border-line bg-subtle p-4">
                            @if ($connection->profile_image_url)
                                <img src="{{ $connection->profile_image_url }}" alt="" class="h-12 w-12 rounded-full" referrerpolicy="no-referrer">
                            @else
                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-subtle font-bold text-soft" aria-hidden="true">X</div>
                            @endif
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-ink">{{ $connection->display_name }}</p>
                                <a href="https://x.com/{{ rawurlencode($connection->username) }}" target="_blank" rel="noreferrer" class="text-sm text-accent-ink no-underline hover:underline">{{ '@'.$connection->username }} ↗</a>
                                <p class="mt-1 text-xs text-muted">Verificato il {{ $connection->last_verified_at?->format('d/m/Y H:i') }}</p>
                            </div>
                        </div>

                        <div class="mt-5 rounded-2xl border border-line bg-surface p-4">
                            <h3 class="font-semibold text-ink">Permessi di pubblicazione</h3>
                            @unless (app()->environment('production'))
                                <p class="mt-2 text-sm text-muted">L’invio reale su X è disponibile in produzione.</p>
                            @endunless
                            @unless ($connection->hasWriteAccess())
                                <p class="mt-2 text-sm text-muted">Ricollega l’account per concedere il permesso di pubblicare.</p>
                            @endunless

                            @foreach (['manual' => 'manuale', 'automatic' => 'automatica'] as $mode => $label)
                                @php($automatic = $mode === 'automatic')
                                @php($enabled = $automatic ? $connection->automatic_publishing_enabled : $connection->manual_publishing_enabled)
                                <div class="mt-4 border-t border-line pt-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <h4 class="text-sm font-semibold">Pubblicazione {{ $label }}</h4>
                                        <span class="ui-badge {{ $connection->canPublish($automatic) ? 'ui-tone-success' : '' }}">{{ $connection->canPublish($automatic) ? 'Abilitata' : 'Sospesa' }}</span>
                                    </div>
                                    <p class="mt-2 text-sm text-muted">{{ $automatic ? 'Invia le proposte approvate all’orario previsto nei siti configurati.' : 'Consente l’invio di una proposta dopo la conferma con «Pubblica ora».' }}</p>
                                    @if ($enabled || $connection->hasWriteAccess())
                                        <form method="POST" action="{{ route('x.publishing.update') }}" class="mt-3" data-confirm="{{ $enabled ? 'Vuoi sospendere la pubblicazione '.$label.' su X?' : ($automatic ? 'Vuoi abilitare la pubblicazione automatica? I siti configurati potranno inviare anche le proposte approvate già in scadenza.' : 'Vuoi abilitare l’invio con «Pubblica ora»?') }}" onsubmit="return confirm(this.dataset.confirm)">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="account" value="{{ $connection->x_user_id }}">
                                            <input type="hidden" name="mode" value="{{ $mode }}">
                                            <input type="hidden" name="enabled" value="{{ $enabled ? '0' : '1' }}">
                                            <button type="submit" class="ui-button {{ $enabled ? '' : 'ui-button-primary' }}">{{ $enabled ? 'Sospendi' : 'Abilita' }} pubblicazione {{ $label }}</button>
                                        </form>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <a href="{{ route('x.connect') }}" class="mt-5 w-full ui-button">Cambia account X</a>
                        @unless (in_array('media.write', $connection->scopes ?? [], true))
                            <p class="mt-3 text-sm text-muted">Ricollega l’account per autorizzare il caricamento delle immagini su X.</p>
                            <a href="{{ route('x.connect') }}" class="mt-2 w-full ui-button">Ricollega per le immagini</a>
                        @endunless
                        <form method="POST" action="{{ route('x.destroy') }}" class="mt-3" data-confirm="Vuoi scollegare l’account X? L’invio automatico verrà disattivato per tutti i siti." onsubmit="return confirm(this.dataset.confirm)">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="w-full ui-button ui-button-danger">Scollega account</button>
                        </form>
                    @elseif ($environmentManaged || $setting)
                        <p class="mt-4 text-sm leading-6 text-muted">Il collegamento apre X, chiede il consenso per gli scope elencati e torna qui dopo aver verificato l’identità dell’account.</p>
                        <a href="{{ route('x.connect') }}" class="mt-5 w-full ui-button ui-button-primary">Collega account X</a>
                    @else
                        <p class="mt-4 text-sm leading-6 text-muted">Salva prima Client ID e Client Secret. Il pulsante di autorizzazione verrà abilitato subito dopo.</p>
                        <button type="button" disabled class="mt-5 w-full ui-button">Collega account X</button>
                    @endif
                </section>

                <section class="rounded-2xl border border-line bg-subtle p-5 text-sm leading-6 text-muted">
                    <p class="font-semibold text-soft">Cosa viene salvato</p>
                    <p class="mt-2">ID, username e nome pubblico dell’account, token OAuth cifrati, scope concessi e data dell’ultima verifica. Non vengono richiesti password X, email o messaggi privati.</p>
                </section>
            </div>
        </div>
    </main>

    <script>
        const copyWithFallback = async (text) => {
            if (navigator.clipboard && window.isSecureContext) {
                try {
                    await navigator.clipboard.writeText(text);

                    return true;
                } catch {
                    // Some browsers expose the Clipboard API but deny access.
                }
            }

            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'fixed';
            textarea.style.left = '-9999px';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();
            textarea.setSelectionRange(0, textarea.value.length);

            let copied = false;

            try {
                copied = document.execCommand('copy');
            } catch {
                copied = false;
            }

            textarea.remove();

            return copied;
        };

        document.querySelectorAll('[data-copy-target]').forEach((button) => {
            button.addEventListener('click', async () => {
                const target = document.getElementById(button.dataset.copyTarget);
                if (! target) return;

                const original = button.textContent;
                button.disabled = true;
                const copied = await copyWithFallback(target.textContent.trim());
                button.textContent = copied ? 'Copiato' : 'Copia non riuscita';

                window.setTimeout(() => {
                    button.textContent = original;
                    button.disabled = false;
                }, 1800);
            });
        });
    </script>
@endsection
