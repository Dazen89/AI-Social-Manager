@extends('layouts.workspace')

@section('title', 'Gestisci '.$site->name.' — '.config('app.name', 'Monitoring Hub'))

@section('page')
    <main id="main-content" class="ui-page ui-page-narrow" tabindex="-1">
        <x-page-header :title="'Gestisci '.$site->name" description="Aggiorna i dati del sito e scegli i servizi da collegare.">
            <x-slot:back><a href="{{ route('sites.index') }}">← Torna ai siti</a></x-slot:back>
        </x-page-header>

        @if (session('status'))
            <div class="mb-6 ui-alert ui-tone-success" role="status">{{ session('status') }}</div>
        @endif

        @if (session('integration_error'))
            <div class="mb-6 ui-alert ui-tone-danger" role="alert">{{ session('integration_error') }}</div>
        @endif

        <section class="ui-panel p-5 sm:p-7" aria-labelledby="site-details-heading">
            <div class="mb-8">
                <h2 id="site-details-heading" class="text-xl font-semibold">Dati del sito</h2>
                <p class="mt-2 text-sm text-muted">Queste informazioni identificano il sito all’interno della dashboard.</p>
            </div>

            <form method="POST" action="{{ route('sites.update', $site) }}" class="space-y-8">
                @csrf
                @method('PUT')
                @include('sites.partials.form', ['submitLabel' => 'Salva modifiche'])
            </form>
        </section>

        <section class="ui-panel mt-8 p-8" aria-labelledby="integrations-heading">
            <div class="mb-6">
                <p class="ui-kicker">Passaggio successivo</p>
                <h2 id="integrations-heading" class="mt-2 text-xl font-semibold">Servizi e integrazioni</h2>
                <p class="mt-2 text-sm leading-6 text-muted">Collega soltanto i servizi che utilizzi. Ogni integrazione mostrerà il proprio stato e le eventuali azioni richieste.</p>
            </div>

            <article class="rounded-2xl border border-line-strong bg-subtle p-6">
                @php
                    $analyticsProperty = $site->googleAnalyticsProperty;
                    $analyticsConnection = $analyticsProperty?->connection;
                    $analyticsIsConnected = $analyticsConnection?->status === 'active';
                    $analyticsNeedsReconnection = ! $analyticsIsConnected && $analyticsProperty?->hasAuthorizationHistory();
                @endphp

                <div class="flex flex-wrap items-start justify-between gap-5">
                    <div class="max-w-2xl">
                        <div class="flex flex-wrap items-center gap-3">
                            <h3 class="text-lg font-semibold">Google Analytics 4</h3>
                            @if ($analyticsIsConnected)
                                <span class="rounded-full bg-success-soft px-2.5 py-1 text-xs font-medium text-success-ink">Collegato</span>
                            @elseif ($analyticsNeedsReconnection)
                                <span class="rounded-full bg-warning-soft px-2.5 py-1 text-xs font-medium text-warning-ink">Da ricollegare</span>
                            @elseif ($analyticsProperty)
                                <span class="rounded-full bg-warning-soft px-2.5 py-1 text-xs font-medium text-warning-ink">Configurazione precedente</span>
                            @else
                                <span class="rounded-full bg-subtle px-2.5 py-1 text-xs font-medium text-soft">Non collegato</span>
                            @endif
                        </div>

                        <p class="mt-3 text-sm leading-6 text-muted">Il collegamento usa l’accesso Google in sola lettura. Dopo l’autorizzazione puoi scegliere account e proprietà da un elenco verificato, senza copiare manualmente gli identificativi.</p>

                        @if ($analyticsIsConnected)
                            <dl class="mt-4 grid gap-3 rounded-xl border border-success-ink/22 bg-success-soft p-4 text-sm sm:grid-cols-2">
                                <div>
                                    <dt class="text-muted">Account</dt>
                                    <dd class="mt-1 font-medium text-soft">{{ $analyticsProperty->account_name ?: $analyticsProperty->account_id }}</dd>
                                </div>
                                <div>
                                    <dt class="text-muted">Proprietà</dt>
                                    <dd class="mt-1 font-medium text-soft">{{ $analyticsProperty->property_name ?: $analyticsProperty->property_id }}</dd>
                                </div>
                                @if ($analyticsProperty->measurement_id)
                                    <div>
                                        <dt class="text-muted">Measurement ID</dt>
                                        <dd class="mt-1 font-medium text-soft">{{ $analyticsProperty->measurement_id }}</dd>
                                    </div>
                                @endif
                                <div>
                                    <dt class="text-muted">Ultima verifica</dt>
                                    <dd class="mt-1 font-medium text-soft">{{ $analyticsProperty->last_verified_at?->format('d/m/Y H:i') }}</dd>
                                </div>
                            </dl>
                        @elseif ($analyticsProperty)
                            <div class="mt-3 rounded-xl border border-warning-ink/22 bg-warning-soft px-4 py-3 text-sm leading-6 text-warning-ink">
                                @if ($analyticsNeedsReconnection)
                                    <p>{{ $analyticsConnection?->last_error ?: 'L’accesso a Google Analytics richiede una nuova autorizzazione.' }}</p>
                                @else
                                    <p>Sono presenti identificativi salvati con il flusso precedente. Restano conservati, ma non rappresentano ancora una connessione verificata con Google.</p>
                                @endif
                                <p class="mt-1">Proprietà salvata <strong>{{ $analyticsProperty->property_id }}</strong>@if ($analyticsProperty->property_name) · {{ $analyticsProperty->property_name }}@endif. Autorizza l’account Google e seleziona questa proprietà.</p>
                            </div>
                        @endif
                    </div>

                    <div class="flex min-w-56 flex-col items-stretch gap-3">
                        @if ($analyticsIsConnected)
                            <a href="{{ route('sites.show', $site) }}" class="ui-button ui-tone-success">Apri panoramica dati</a>
                        @endif

                        @if ($googleAnalyticsOAuthConfigured)
                            <a href="{{ route('google-analytics.connect', $site) }}" class="ui-button ui-button-primary">{{ match (true) { $analyticsIsConnected => 'Cambia account o proprietà', $analyticsNeedsReconnection => 'Ricollega account Google', default => 'Collega account Google' } }}</a>
                            <a href="{{ route('google-analytics.settings.edit', $site) }}" class="ui-button">Credenziali OAuth e guida</a>
                        @else
                            <a href="{{ route('google-analytics.settings.edit', $site) }}" class="ui-button ui-button-primary">Configura Google Analytics</a>
                        @endif

                        @if ($analyticsProperty)
                            <form method="POST" action="{{ route('google-analytics.destroy', $site) }}" data-confirm="Vuoi scollegare Google Analytics da questo sito?" onsubmit="return confirm(this.dataset.confirm)">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-full ui-button ui-button-danger">Scollega</button>
                            </form>
                        @endif
                    </div>
                </div>

                @unless ($googleAnalyticsOAuthConfigured)
                    <div class="mt-6 rounded-xl border border-warning-ink/22 bg-warning-soft p-4 text-sm leading-6 text-warning-ink">
                        <p class="font-semibold">Prima configura l’accesso Google dell’installazione</p>
                        <p class="mt-2">La procedura guidata spiega quali credenziali creare in Google Cloud, dove trovarle e come salvarle in sicurezza dalla dashboard. È un’operazione unica per tutta l’installazione.</p>
                        <a href="{{ route('google-analytics.settings.edit', $site) }}" class="mt-3 inline-flex font-medium text-warning-ink underline underline-offset-4">Apri la procedura guidata</a>
                    </div>
                @endunless
            </article>

            <article class="mt-6 rounded-2xl border border-line-strong bg-subtle p-6">
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
                                <span class="rounded-full bg-success-soft px-2.5 py-1 text-xs font-medium text-success-ink">Collegata</span>
                            @elseif ($searchConsoleConnection?->status === 'error')
                                <span class="rounded-full bg-warning-soft px-2.5 py-1 text-xs font-medium text-warning-ink">Da verificare</span>
                            @else
                                <span class="rounded-full bg-subtle px-2.5 py-1 text-xs font-medium text-soft">Non collegata</span>
                            @endif
                        </div>

                        <p class="mt-3 text-sm leading-6 text-muted">Aggiunge query, impressioni, clic, CTR e posizione media nei risultati Google. Il collegamento usa soltanto il permesso Search Console di lettura e non può modificare il sito.</p>

                        @if ($searchConsoleConnection?->status === 'error')
                            <p class="mt-3 ui-alert ui-tone-warning">
                                {{ $searchConsoleConnection->last_error ?: 'Il collegamento Search Console richiede una nuova autorizzazione.' }}
                                @if ($searchConsoleConnection->canLoadReports())
                                    <a href="{{ route('sites.show', $site) }}#search-console-heading" class="font-medium text-accent-ink">Apri la panoramica per riprovare</a>
                                @endif
                            </p>
                        @endif

                        @if ($searchConsoleIsConnected)
                            <dl class="mt-4 grid gap-3 rounded-xl border border-success-ink/22 bg-success-soft p-4 text-sm sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <dt class="text-muted">Proprietà</dt>
                                    <dd class="mt-1 break-all font-medium text-soft">{{ $searchConsoleProperty->property_url }}</dd>
                                </div>
                                <div>
                                    <dt class="text-muted">Tipo</dt>
                                    <dd class="mt-1 font-medium text-soft">{{ $searchConsoleProperty->property_type === 'domain' ? 'Dominio' : 'Prefisso URL' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-muted">Ultima verifica</dt>
                                    <dd class="mt-1 font-medium text-soft">{{ $searchConsoleProperty->last_verified_at?->format('d/m/Y H:i') }}</dd>
                                </div>
                            </dl>
                        @endif
                    </div>

                    <div class="flex min-w-56 flex-col items-stretch gap-3">
                        @if ($googleSearchConsoleOAuthConfigured)
                            <a href="{{ route('google-search-console.connect', $site) }}" class="ui-button ui-button-primary">{{ $searchConsoleIsConnected ? 'Cambia account o proprietà' : 'Collega Search Console' }}</a>
                            <a href="{{ route('google-search-console.settings.edit', $site) }}" class="ui-button">Credenziali OAuth e guida</a>
                        @else
                            <a href="{{ route('google-search-console.settings.edit', $site) }}" class="ui-button ui-button-primary">Configura Search Console</a>
                        @endif

                        @if ($searchConsoleProperty)
                            <form method="POST" action="{{ route('google-search-console.destroy', $site) }}" data-confirm="Vuoi scollegare Google Search Console da questo sito?" onsubmit="return confirm(this.dataset.confirm)">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-full ui-button ui-button-danger">Scollega</button>
                            </form>
                        @endif
                    </div>
                </div>

                @unless ($googleSearchConsoleOAuthConfigured)
                    <div class="mt-6 rounded-xl border border-warning-ink/22 bg-warning-soft p-4 text-sm leading-6 text-warning-ink">
                        <p class="font-semibold">Prima abilita l’accesso Search Console dell’installazione</p>
                        <p class="mt-2">La guida mostra come verificare il sito in Search Console, abilitare l’API, aggiungere il permesso di sola lettura e configurare il callback. Se hai già Google Analytics, puoi riutilizzare lo stesso progetto e client OAuth.</p>
                        <a href="{{ route('google-search-console.settings.edit', $site) }}" class="mt-3 inline-flex font-medium text-warning-ink underline underline-offset-4">Apri la procedura guidata</a>
                    </div>
                @endunless
            </article>
        </section>

        <section class="ui-panel mt-8 p-8" aria-labelledby="editorial-plan-heading">
            <h2 id="editorial-plan-heading" class="text-xl font-semibold">Piano editoriale</h2>
            <p class="mt-2 text-sm leading-6 text-muted">Consulta il calendario e le proposte editoriali. Le fonti dei contenuti si configurano in una pagina separata; importazione, pianificazione e post su X non sono ancora attivi.</p>
            <a href="{{ route('sites.editorial-plan.show', $site) }}" class="mt-5 ui-button ui-button-primary">Apri piano editoriale</a>
        </section>
    </main>
@endsection
