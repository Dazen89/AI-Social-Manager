@extends('layouts.app')

@section('title', 'Pubblicazione su X — '.config('app.name', 'Monitoring Hub'))

@section('content')
    @include('partials.navigation')

    <main class="mx-auto max-w-6xl px-6 py-10">
        <div class="mb-8 flex flex-col justify-between gap-5 md:flex-row md:items-end">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-sky-400">Social publishing</p>
                <h1 class="mt-2 text-3xl font-semibold">Pubblicazione su X</h1>
                <p class="mt-3 max-w-3xl leading-7 text-slate-400">Configura una sola app X per questa installazione e autorizza l’account che pubblicherà. Le credenziali e i token sono cifrati; nessun post viene inviato durante la configurazione.</p>
            </div>
            <span class="inline-flex w-fit rounded-full border border-slate-700 bg-slate-900 px-3 py-1.5 text-xs font-semibold text-slate-300">Provider iniziale: X</span>
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

        <div class="grid gap-8 lg:grid-cols-[1.08fr_0.92fr]">
            <div class="space-y-8">
                <section class="rounded-3xl border border-slate-800 bg-slate-900 p-8" aria-labelledby="x-guide-heading">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-400">Guida passo passo</p>
                            <h2 id="x-guide-heading" class="mt-2 text-xl font-semibold">Prepara l’app nel Developer Console</h2>
                        </div>
                        <a href="https://developer.x.com/en/portal/dashboard" target="_blank" rel="noreferrer" class="shrink-0 rounded-lg border border-sky-500/30 px-3 py-2 text-sm font-medium text-sky-300 no-underline hover:bg-sky-500/10">Apri X Developer ↗</a>
                    </div>

                    <div class="mt-7 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl border border-sky-500/20 bg-sky-500/[0.06] p-4">
                            <p class="text-sm font-semibold text-sky-200">Da recuperare nella Developer Console X</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-xs leading-5 text-slate-400">
                                <li>approvazione del caso d’uso e piano API;</li>
                                <li>progetto e app associata;</li>
                                <li>Client ID e Client Secret OAuth 2.0;</li>
                                <li>permessi e stato dell’app.</li>
                            </ul>
                        </div>
                        <div class="rounded-2xl border border-violet-500/20 bg-violet-500/[0.06] p-4">
                            <p class="text-sm font-semibold text-violet-200">Fornito da questa dashboard</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-xs leading-5 text-slate-400">
                                <li>Callback URI esatto;</li>
                                <li>scope OAuth minimi richiesti;</li>
                                <li>account verificato dopo il consenso.</li>
                            </ul>
                        </div>
                    </div>

                    <ol class="mt-7 space-y-7">
                        <li class="flex gap-4">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-500/15 text-sm font-semibold text-sky-300">1</span>
                            <div class="min-w-0 flex-1">
                                <h3 class="font-semibold">Completa Developer Agreement &amp; Policy</h3>
                                <p class="mt-1 text-sm leading-6 text-slate-400">X mostra il campo <strong class="text-slate-200">“Describe all of your use cases of X’s data and API”</strong> prima di consentire l’accesso alla piattaforma. Descrivi l’uso reale e previsto, chi controlla l’account, quali dati vengono letti e quali azioni saranno automatizzate. Non dichiarare funzionalità diverse da quelle effettivamente usate.</p>
                                <div class="mt-3 rounded-xl border border-slate-700 bg-slate-950 p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Testo di esempio in inglese — adattalo al tuo caso reale</p>
                                        <button type="button" data-copy-target="x-use-case-example" aria-live="polite" class="shrink-0 rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-xs text-slate-200 hover:bg-slate-800">Copia testo</button>
                                    </div>
                                    <p id="x-use-case-example" class="mt-3 whitespace-pre-line text-xs leading-6 text-slate-300">This is a private administrative application used by a small team to manage an X account that the team owns or is authorized to operate. The application will use the official X API to identify the authorized account, publish administrator-approved posts immediately or at scheduled times, and monitor post-level delivery and performance metadata. It will not scrape X, access Direct Messages, perform automated replies, follows, likes, or unsolicited interactions, sell or share X data, or use X data to train AI models. OAuth credentials and tokens are encrypted, access is restricted to administrators, and the account owner can revoke access.</p>
                                </div>
                                <p class="mt-3 text-xs leading-5 text-amber-200/80">Prima di inviarlo, rimuovi ogni frase che non corrisponde al tuo utilizzo e aggiungi eventuali altri usi effettivi. L’approvazione e le informazioni inserite restano responsabilità del titolare dell’account sviluppatore.</p>
                            </div>
                        </li>
                        <li class="flex gap-4">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-500/15 text-sm font-semibold text-sky-300">2</span>
                            <div>
                                <h3 class="font-semibold">Crea un progetto e un’app</h3>
                                <p class="mt-1 text-sm leading-6 text-slate-400">Accedi con l’account che gestisce il progetto X, abilita un piano API che includa la creazione di post e crea un’app dedicata a questa installazione. Costi, quote e disponibilità dipendono dal piano mostrato da X.</p>
                            </div>
                        </li>
                        <li class="flex gap-4">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-500/15 text-sm font-semibold text-sky-300">3</span>
                            <div>
                                <h3 class="font-semibold">Abilita OAuth 2.0</h3>
                                <p class="mt-1 text-sm leading-6 text-slate-400">In <strong class="text-slate-200">User authentication settings</strong> abilita OAuth 2.0 e scegli <strong class="text-slate-200">Web App, Automated App or Bot</strong>. È un client confidenziale: genera quindi sia Client ID sia Client Secret. Questi due valori vanno recuperati nella Developer Console e inseriti nel modulo a destra.</p>
                                <p class="mt-2 text-xs leading-5 text-amber-200/80">Non inserire API Key, API Key Secret o Bearer Token: sono credenziali diverse e non funzionano in questo collegamento OAuth.</p>
                            </div>
                        </li>
                        <li class="flex gap-4">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-500/15 text-sm font-semibold text-sky-300">4</span>
                            <div class="min-w-0 flex-1">
                                <h3 class="font-semibold">Registra il callback esatto</h3>
                                <p class="mt-1 text-sm leading-6 text-slate-400">Imposta come Callback URI / Redirect URL questo indirizzo, senza modificare protocollo, dominio, porta o slash:</p>
                                <div class="mt-3 flex items-center gap-2 rounded-xl border border-slate-700 bg-slate-950 p-3">
                                    <code id="x-callback-uri" class="min-w-0 flex-1 break-all text-xs text-sky-200">{{ $redirectUri }}</code>
                                    <button type="button" data-copy-target="x-callback-uri" aria-live="polite" class="shrink-0 rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-xs text-slate-200 hover:bg-slate-800">Copia URI</button>
                                </div>
                                <p class="mt-3 text-xs leading-5 text-slate-500">Nel campo Website URL usa l’indirizzo pubblico della dashboard. In produzione X richiede normalmente HTTPS; il callback deve essere raggiungibile dal browser dell’amministratore.</p>
                            </div>
                        </li>
                        <li class="flex gap-4">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-500/15 text-sm font-semibold text-sky-300">5</span>
                            <div>
                                <h3 class="font-semibold">Consenti lettura e scrittura</h3>
                                <p class="mt-1 text-sm leading-6 text-slate-400">Seleziona i permessi <strong class="text-slate-200">Read and write</strong>. Il collegamento richiederà esclusivamente gli scope necessari a identificare l’account, creare post e rinnovare l’accesso:</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($scopes as $scope)
                                        <code class="rounded-lg bg-slate-950 px-2.5 py-1.5 text-xs text-slate-300">{{ $scope }}</code>
                                    @endforeach
                                </div>
                            </div>
                        </li>
                        <li class="flex gap-4">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-500/15 text-sm font-semibold text-sky-300">6</span>
                            <div>
                                <h3 class="font-semibold">Salva le credenziali e collega l’account</h3>
                                <p class="mt-1 text-sm leading-6 text-slate-400">Copia Client ID e Client Secret nel modulo. Dopo il salvataggio usa “Collega account X” e accedi all’account che dovrà pubblicare: il nome visualizzato viene letto direttamente dall’API, non inserito a mano.</p>
                            </div>
                        </li>
                    </ol>

                    <div class="mt-8 rounded-2xl border border-amber-400/25 bg-amber-400/10 p-4 text-sm leading-6 text-amber-100">
                        <p class="font-semibold">Prima della pubblicazione automatica</p>
                        <p class="mt-1">Verifica piano, quota e regole di automazione nel tuo account X. Il collegamento abilita tecnicamente la scrittura, ma i post dovranno comunque passare dal flusso di approvazione e dalla coda dell’applicazione.</p>
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-800 bg-slate-900 p-8" aria-labelledby="x-docs-heading">
                    <h2 id="x-docs-heading" class="text-xl font-semibold">Documentazione ufficiale</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <a href="https://docs.x.com/fundamentals/authentication/oauth-2-0/authorization-code" target="_blank" rel="noreferrer" class="rounded-xl border border-slate-700 p-4 text-sm text-slate-200 no-underline hover:border-sky-500/50 hover:bg-slate-800">OAuth 2.0 con PKCE ↗</a>
                        <a href="https://docs.x.com/x-api/posts/create-post" target="_blank" rel="noreferrer" class="rounded-xl border border-slate-700 p-4 text-sm text-slate-200 no-underline hover:border-sky-500/50 hover:bg-slate-800">Creazione dei post ↗</a>
                        <a href="https://docs.x.com/fundamentals/authentication/oauth-2-0/overview" target="_blank" rel="noreferrer" class="rounded-xl border border-slate-700 p-4 text-sm text-slate-200 no-underline hover:border-sky-500/50 hover:bg-slate-800">Scope e autenticazione ↗</a>
                        <a href="https://docs.x.com/developer-terms/agreement" target="_blank" rel="noreferrer" class="rounded-xl border border-slate-700 p-4 text-sm text-slate-200 no-underline hover:border-sky-500/50 hover:bg-slate-800">Developer Agreement ↗</a>
                        <a href="https://docs.x.com/developer-terms/policy" target="_blank" rel="noreferrer" class="rounded-xl border border-slate-700 p-4 text-sm text-slate-200 no-underline hover:border-sky-500/50 hover:bg-slate-800">Developer Policy ↗</a>
                    </div>
                </section>
            </div>

            <div class="space-y-8">
                <section class="rounded-3xl border border-slate-800 bg-slate-900 p-8" aria-labelledby="x-credentials-heading">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 id="x-credentials-heading" class="text-xl font-semibold">Credenziali dell’app X</h2>
                            <p class="mt-2 text-sm leading-6 text-slate-400">Identificano questa installazione. Il segreto viene cifrato e non viene mai mostrato dopo il salvataggio.</p>
                        </div>
                        @if ($environmentManaged)
                            <span class="rounded-full bg-violet-400/10 px-3 py-1 text-xs font-semibold text-violet-200">Gestite dall’ambiente</span>
                        @elseif ($setting)
                            <span class="rounded-full bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-200">Salvate</span>
                        @else
                            <span class="rounded-full bg-amber-400/10 px-3 py-1 text-xs font-semibold text-amber-200">Da configurare</span>
                        @endif
                    </div>

                    @if ($environmentManaged)
                        <div class="mt-6 rounded-xl border border-violet-400/20 bg-violet-400/10 p-4 text-sm leading-6 text-violet-100">
                            Client ID e Client Secret provengono da <code>X_CLIENT_ID</code> e <code>X_CLIENT_SECRET</code>. Per sicurezza i valori non sono esposti nella dashboard.
                        </div>
                    @else
                        <form method="POST" action="{{ route('settings.x.update') }}" class="mt-6 space-y-5">
                            @csrf
                            @method('PUT')

                            <div>
                                <label for="client_id" class="mb-2 block text-sm font-medium text-slate-200">Client ID</label>
                                <input id="client_id" name="client_id" type="text" value="{{ old('client_id', $clientId) }}" autocomplete="off" required>
                                <p class="mt-2 text-xs leading-5 text-slate-500">Si trova in Keys and tokens dopo aver abilitato OAuth 2.0.</p>
                            </div>

                            <div>
                                <label for="client_secret" class="mb-2 block text-sm font-medium text-slate-200">Client Secret</label>
                                <input id="client_secret" name="client_secret" type="password" value="" autocomplete="new-password" {{ $setting ? '' : 'required' }}>
                                <p class="mt-2 text-xs leading-5 text-slate-500">{{ $setting ? 'Lascia vuoto per conservare il segreto già salvato.' : 'È un dato sensibile: non inserirlo nel repository o nei log.' }}</p>
                            </div>

                            <button type="submit" class="w-full rounded-xl bg-sky-400 px-5 py-3 font-semibold text-slate-950 hover:bg-sky-300">Salva credenziali</button>
                        </form>

                        @if ($setting)
                            <form method="POST" action="{{ route('settings.x.destroy') }}" class="mt-4" onsubmit="return confirm('Vuoi rimuovere le credenziali dell’app X?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-full rounded-xl border border-rose-500/30 bg-transparent px-5 py-3 text-sm font-semibold text-rose-300 hover:bg-rose-500/10">Rimuovi credenziali</button>
                            </form>
                        @endif
                    @endif
                </section>

                <section class="rounded-3xl border {{ $connection ? 'border-emerald-400/30 bg-emerald-400/[0.04]' : 'border-slate-800 bg-slate-900' }} p-8" aria-labelledby="x-account-heading">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Account di pubblicazione</p>
                            <h2 id="x-account-heading" class="mt-2 text-xl font-semibold">{{ $connection ? '@'.$connection->username : 'Nessun account collegato' }}</h2>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $connection ? 'bg-emerald-400/10 text-emerald-200' : 'bg-slate-800 text-slate-400' }}">{{ $connection ? 'Verificato' : 'Non collegato' }}</span>
                    </div>

                    @if ($connection)
                        <div class="mt-6 flex items-center gap-4 rounded-2xl border border-slate-800 bg-slate-950/60 p-4">
                            @if ($connection->profile_image_url)
                                <img src="{{ $connection->profile_image_url }}" alt="" class="h-12 w-12 rounded-full" referrerpolicy="no-referrer">
                            @else
                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-800 font-bold text-slate-300" aria-hidden="true">X</div>
                            @endif
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-slate-100">{{ $connection->display_name }}</p>
                                <a href="https://x.com/{{ rawurlencode($connection->username) }}" target="_blank" rel="noreferrer" class="text-sm text-sky-400 no-underline hover:underline">{{ '@'.$connection->username }} ↗</a>
                                <p class="mt-1 text-xs text-slate-500">Verificato il {{ $connection->last_verified_at?->format('d/m/Y H:i') }}</p>
                            </div>
                        </div>

                        <a href="{{ route('x.connect') }}" class="mt-5 inline-flex w-full justify-center rounded-xl border border-sky-400/40 px-5 py-3 font-semibold text-sky-200 no-underline hover:bg-sky-400/10">Cambia account X</a>
                        <form method="POST" action="{{ route('x.destroy') }}" class="mt-3" onsubmit="return confirm('Vuoi scollegare l’account X usato per pubblicare?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="w-full rounded-xl border border-rose-500/30 bg-transparent px-5 py-3 text-sm font-semibold text-rose-300 hover:bg-rose-500/10">Scollega account</button>
                        </form>
                    @elseif ($environmentManaged || $setting)
                        <p class="mt-4 text-sm leading-6 text-slate-400">Il collegamento apre X, chiede il consenso per gli scope elencati e torna qui dopo aver verificato l’identità dell’account.</p>
                        <a href="{{ route('x.connect') }}" class="mt-5 inline-flex w-full justify-center rounded-xl bg-slate-100 px-5 py-3 font-semibold text-slate-950 no-underline hover:bg-white">Collega account X</a>
                    @else
                        <p class="mt-4 text-sm leading-6 text-slate-400">Salva prima Client ID e Client Secret. Il pulsante di autorizzazione verrà abilitato subito dopo.</p>
                        <button type="button" disabled class="mt-5 w-full cursor-not-allowed rounded-xl bg-slate-800 px-5 py-3 font-semibold text-slate-500">Collega account X</button>
                    @endif
                </section>

                <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5 text-sm leading-6 text-slate-400">
                    <p class="font-semibold text-slate-200">Cosa viene salvato</p>
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
