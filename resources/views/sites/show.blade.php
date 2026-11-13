@extends('layouts.workspace')

@section('title', $site->name.' — '.config('app.name', 'Monitoring Hub'))

@section('page')
    @php
        $formatNumber = fn (mixed $value): string => number_format((float) $value, 0, ',', '.');
        $formatPercentage = fn (mixed $value): string => number_format((float) $value * 100, 1, ',', '.').'%' ;
        $analysis = $analyticsInsight?->analysis ?? null;
        $formatDuration = function (mixed $value): string {
            $seconds = max(0, (int) round((float) $value));

            return $seconds >= 60
                ? intdiv($seconds, 60).' min '.str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT).' s'
                : $seconds.' s';
        };
    @endphp

    <main id="main-content" class="ui-page" tabindex="-1">
        <x-page-header :title="$site->name">
            <x-slot:back><a href="{{ route('sites.index') }}">← Torna ai siti</a></x-slot:back>
            <x-slot:status>
                <span class="ui-badge {{ $site->is_active ? 'ui-tone-success' : '' }}">{{ $site->is_active ? 'Monitoraggio attivo' : 'Monitoraggio sospeso' }}</span>
            </x-slot:status>
            <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted">
                <a href="{{ $site->base_url }}" target="_blank" rel="noopener noreferrer">{{ $site->host }} ↗</a>
                @if ($analyticsProperty)
                    <span aria-hidden="true">•</span>
                    <span>{{ $analyticsProperty->property_name ?: 'Proprietà '.$analyticsProperty->property_id }}</span>
                @endif
            </div>
            <x-slot:actions>
                @if ($analyticsIsConnected && ! $reportError && ($overview['has_data'] ?? false))
                    <a href="#ai-insight-heading" class="ui-button"><x-icon name="ai" /> Analisi AI</a>
                @endif
                <a href="{{ route('sites.editorial-plan.show', $site) }}" class="ui-button"><x-icon name="calendar" /> Piano editoriale</a>
                <a href="{{ route('sites.edit', $site) }}" class="ui-button"><x-icon name="settings" /> Gestisci sito e collegamenti</a>
            </x-slot:actions>
        </x-page-header>

        @unless ($analyticsIsConnected)
            <section class="ui-empty">
                <div class="mx-auto mb-3 grid size-10 place-items-center rounded-lg bg-accent-soft text-accent-ink"><x-icon name="chart" /></div>
                <h2 class="mt-5 text-xl font-semibold">Collega Google Analytics per vedere i dati</h2>
                <p class="mx-auto mt-2 max-w-2xl leading-7 text-muted">Questa pagina interroga i report GA4 in sola lettura. Prima completa o ripristina il collegamento dalla gestione del sito.</p>
                <a href="{{ route('sites.edit', $site) }}" class="mt-6 ui-button ui-button-primary">Vai ai collegamenti</a>
            </section>
        @else
            <section class="ui-panel mb-7 p-5" aria-label="Periodo del report">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-soft">Periodo analizzato</p>
                        <p class="mt-1 text-sm text-muted">Dati della proprietà filtrati esattamente per l’host <code class="text-soft">{{ $site->host }}</code>.</p>
                    </div>
                    <x-period-filter :site="$site" :days="$days" />
                </div>
            </section>

            <section class="ui-panel mb-7 p-5" aria-labelledby="analytics-quota-heading">
                <h2 id="analytics-quota-heading" class="text-lg font-semibold">Quote Google Analytics</h2>
                <p class="mt-2 text-sm text-muted">Disponibilità restituita da Google durante le letture dei report di questa proprietà. I valori si riferiscono alla data indicata e possono essere consumati anche da altre applicazioni.</p>
                @if (! empty($analyticsQuota['quotas']))
                    <p class="mt-2 text-xs text-muted">Ultima rilevazione {{ \Carbon\CarbonImmutable::parse($analyticsQuota['measured_at'])->setTimezone(config('app.timezone'))->format('d/m/Y H:i:s') }} ({{ config('app.timezone') }}). Per ogni quota mostriamo il minimo residuo osservato nel gruppo di report.</p>
                    <div class="ui-table-scroll mt-4">
                        <table class="w-full text-left text-sm">
                            <thead><tr><th scope="col" class="py-2">Quota</th><th scope="col" class="py-2 text-right">Residuo alla rilevazione</th></tr></thead>
                            <tbody>
                                @foreach ($analyticsQuota['quotas'] as $quota)
                                    <tr class="border-t border-line">
                                        <th scope="row" class="py-2 font-normal">{{ $quota['label'] }}</th>
                                        <td class="py-2 text-right {{ $quota['remaining'] === 0 ? 'font-semibold text-warning-ink' : '' }}">{{ number_format($quota['remaining'], 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="mt-3 text-sm text-muted">Google non ha ancora restituito le quote residue. Saranno richieste con la prossima lettura dei report.</p>
                @endif
                @if (! empty($analyticsQuota['error']))
                    <div class="ui-alert ui-tone-warning mt-4">
                        <p class="font-semibold">Ultimo rifiuto per quota · HTTP {{ $analyticsQuota['error']['http_status'] }}</p>
                        <p class="mt-1 text-sm">{{ $analyticsQuota['error']['quota_label'] ?? 'Google ha segnalato una quota esaurita senza specificare il limite raggiunto.' }}</p>
                        <p class="mt-2 text-sm">Rilevato il {{ \Carbon\CarbonImmutable::parse($analyticsQuota['error']['occurred_at'])->setTimezone(config('app.timezone'))->format('d/m/Y H:i:s') }}.</p>
                        @if (\Carbon\CarbonImmutable::parse($analyticsQuota['error']['retry_at'])->isFuture())
                            <p class="mt-1 text-sm">Nuove letture sospese dall’app fino alle {{ \Carbon\CarbonImmutable::parse($analyticsQuota['error']['retry_at'])->setTimezone(config('app.timezone'))->format('H:i:s') }} ({{ config('app.timezone') }}).</p>
                        @else
                            <p class="mt-1 text-sm">La pausa dell’app è terminata. La prossima lettura necessaria tenterà di aggiornare i dati.</p>
                        @endif
                        <p class="mt-2 text-xs">Il ripristino della quota dipende da Google. Le quote residue eventualmente mostrate sopra appartengono all’ultima lettura riuscita.</p>
                    </div>
                @endif
            </section>

            @if ($overview['is_stale'] ?? false)
                <div class="ui-alert ui-tone-warning mb-7" role="status">
                    <p class="font-semibold">Visualizzi l’ultimo report disponibile</p>
                    <p class="mt-2">{{ $overview['refresh_warning'] }} Dati recuperati il {{ \Carbon\CarbonImmutable::parse($overview['fetched_at'])->format('d/m/Y H:i') }}.</p>
                </div>
            @endif

            @if ($reportError)
                <section class="rounded-3xl border border-danger-ink/22 bg-danger-soft p-8" role="alert">
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-danger-ink">Dati non disponibili</p>
                    <h2 class="mt-2 text-xl font-semibold text-danger-ink">La lettura dei report non è riuscita</h2>
                    <p class="mt-3 max-w-3xl leading-7 text-danger-ink">{{ $reportError->getMessage() }}</p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        @if ($reportError->actionUrl && $reportError->actionLabel)
                            <a href="{{ $reportError->actionUrl }}" target="_blank" rel="noreferrer" class="ui-button ui-button-danger">{{ $reportError->actionLabel }} ↗</a>
                        @endif
                        <a href="{{ route('sites.edit', $site) }}" class="ui-button ui-button-danger">Controlla il collegamento</a>
                    </div>
                </section>
            @elseif (! $overview['has_data'])
                <section class="rounded-3xl border border-warning-ink/22 bg-warning-soft p-8">
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-warning-ink">Richiesta completata</p>
                    <h2 class="mt-2 text-xl font-semibold text-warning-ink">Nessun dato per questo dominio nel periodo scelto</h2>
                    <p class="mt-3 max-w-3xl leading-7 text-warning-ink">Google ha risposto correttamente, ma non ha restituito attività per <strong>{{ $site->host }}</strong>. Prova un periodo più ampio e verifica che lo stream GA4 raccolga proprio questo hostname. I valori assenti non vengono mostrati come zero.</p>
                </section>
            @else
                <section aria-labelledby="overview-heading">
                    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <p class="ui-kicker">Google Analytics 4</p>
                            <h2 id="overview-heading" class="mt-2 text-2xl font-semibold">Panoramica del traffico</h2>
                        </div>
                        <p class="text-xs text-muted">Recuperati {{ \Carbon\CarbonImmutable::parse($overview['fetched_at'])->format('d/m/Y H:i') }} · cache 10 minuti</p>
                    </div>

                    <dl class="ui-kpi-grid">
                        <x-kpi label="Utenti attivi">{{ $formatNumber($overview['summary']['activeUsers'] ?? 0) }}</x-kpi>
                        <x-kpi label="Sessioni">{{ $formatNumber($overview['summary']['sessions'] ?? 0) }}</x-kpi>
                        <x-kpi label="Visualizzazioni">{{ $formatNumber($overview['summary']['screenPageViews'] ?? 0) }}</x-kpi>
                        <x-kpi label="Tasso di coinvolgimento">
                            {{ $formatPercentage($overview['summary']['engagementRate'] ?? 0) }}
                            <x-slot:note>{{ $formatNumber($overview['summary']['engagedSessions'] ?? 0) }} sessioni coinvolte</x-slot:note>
                        </x-kpi>
                        <x-kpi label="Durata media sessione">{{ $formatDuration($overview['summary']['averageSessionDuration'] ?? 0) }}</x-kpi>
                    </dl>
                </section>

                <x-sessions-chart :overview="$overview" />

                <div class="mt-7 grid gap-7 lg:grid-cols-[1.35fr_0.65fr]">
                    <section class="ui-panel overflow-hidden" aria-labelledby="pages-heading">
                        <div class="border-b border-line p-6 sm:px-8">
                            <h2 id="pages-heading" class="text-xl font-semibold">Pagine principali</h2>
                            <p class="mt-1 text-sm text-muted">Le pagine più viste sul dominio nel periodo selezionato.</p>
                        </div>
                        @if ($overview['pages'] === [])
                            <p class="p-8 text-sm text-muted">Nessuna pagina disponibile.</p>
                        @else
                            <div class="ui-table-scroll">
                                <table class="ui-table">
                                    <thead class="text-xs uppercase tracking-wider text-muted">
                                        <tr>
                                            <th class="px-6 py-4 sm:pl-8">Pagina</th>
                                            <th class="px-4 py-4 text-right">Viste</th>
                                            <th class="px-6 py-4 text-right sm:pr-8">Utenti</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-line">
                                        @foreach ($overview['pages'] as $page)
                                            @php($pageUrl = rtrim($site->base_url, '/').'/'.ltrim($page['pagePathPlusQueryString'] ?? '/', '/'))
                                            <tr>
                                                <td class="max-w-xl px-6 py-4 sm:pl-8">
                                                    <a href="{{ $pageUrl }}" target="_blank" rel="noopener noreferrer" class="block truncate font-medium text-soft no-underline hover:text-accent-ink" title="{{ $page['pageTitle'] ?? '' }}">{{ filled($page['pageTitle'] ?? null) ? $page['pageTitle'] : ($page['pagePathPlusQueryString'] ?? '/') }}</a>
                                                    <span class="mt-1 block truncate text-xs text-muted">{{ $page['pagePathPlusQueryString'] ?? '/' }}</span>
                                                </td>
                                                <td class="px-4 py-4 text-right font-semibold text-soft">{{ $formatNumber($page['screenPageViews'] ?? 0) }}</td>
                                                <td class="px-6 py-4 text-right text-muted sm:pr-8">{{ $formatNumber($page['activeUsers'] ?? 0) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </section>

                    <section class="ui-panel p-6 sm:p-8" aria-labelledby="channels-heading">
                        <h2 id="channels-heading" class="text-xl font-semibold">Canali di acquisizione</h2>
                        <p class="mt-1 text-sm text-muted">Da dove iniziano le sessioni.</p>
                        <div class="mt-6 space-y-5">
                            @forelse ($overview['channels'] as $channel)
                                <div>
                                    <div class="flex items-center justify-between gap-4 text-sm">
                                        <span class="font-medium text-soft">{{ $channel['sessionDefaultChannelGroup'] ?: 'Non disponibile' }}</span>
                                        <span class="text-muted">{{ $formatNumber($channel['sessions'] ?? 0) }} sessioni</span>
                                    </div>
                                    <p class="mt-1 text-xs text-muted">{{ $formatNumber($channel['activeUsers'] ?? 0) }} utenti · {{ $formatPercentage($channel['engagementRate'] ?? 0) }} coinvolgimento</p>
                                </div>
                            @empty
                                <p class="text-sm text-muted">Nessun canale disponibile.</p>
                            @endforelse
                        </div>
                    </section>
                </div>

                <div class="mt-7 grid gap-7 md:grid-cols-2">
                    @foreach (['devices' => ['Dispositivi', 'Come vengono avviate le sessioni.'], 'countries' => ['Paesi', 'Provenienza geografica aggregata delle sessioni.']] as $group => [$heading, $description])
                        @php($maximum = max(1, (int) collect($overview[$group])->max('sessions')))
                        <section class="ui-panel p-6 sm:p-8" aria-labelledby="{{ $group }}-heading">
                            <h2 id="{{ $group }}-heading" class="text-xl font-semibold">{{ $heading }}</h2>
                            <p class="mt-1 text-sm text-muted">{{ $description }}</p>
                            <div class="mt-6 space-y-4">
                                @forelse ($overview[$group] as $item)
                                    <div>
                                        <div class="mb-2 flex items-center justify-between gap-4 text-sm">
                                            <span class="font-medium text-soft">{{ $item['label'] }}</span>
                                            <span class="text-muted">{{ $formatNumber($item['sessions']) }}</span>
                                        </div>
                                        <div class="ui-chart-meter">
                                            <div style="width: {{ max(1, ($item['sessions'] / $maximum) * 100) }}%"></div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-sm text-muted">Nessun dato disponibile.</p>
                                @endforelse
                            </div>
                        </section>
                    @endforeach
                </div>

                <p class="mt-6 text-xs leading-5 text-muted">Questa è una prima vista esplorativa dei dati disponibili tramite Google Analytics Data API. I numeri possono differire dai report standard di GA4 per periodo, filtri, modellazione e aggiornamento dei dati.</p>
                <section class="mt-7 ui-panel p-5 sm:p-6" aria-labelledby="ai-insight-heading">
                    <div class="flex flex-wrap items-start justify-between gap-5">
                        <div class="max-w-3xl">
                            <p class="ui-kicker">Analista AI</p>
                            <h2 id="ai-insight-heading" class="mt-2 text-2xl font-semibold">I numeri tradotti in decisioni</h2>
                            <p class="mt-2 leading-7 text-muted">Confronta GA4 con il periodo precedente e, se disponibili, usa anche i dati Search Console per evidenziare rischi e opportunità. I due servizi hanno periodi e metriche diversi. L’analisi non modifica il sito né i servizi Google.</p>
                        </div>

                        @if ($activeAiIntegration?->status === 'verified')
                            <form method="POST" action="{{ route('sites.analytics-insights.store', $site) }}">
                                @csrf
                                <input type="hidden" name="range" value="{{ $days }}">
                                <button type="submit" class="ui-button ui-button-primary">
                                    {{ $analysis ? 'Aggiorna analisi' : 'Analizza con AI' }}
                                </button>
                            </form>
                        @else
                            <a href="{{ route('settings.ai.index') }}" class="ui-button">
                                {{ $activeAiIntegration ? 'Verifica integrazione AI' : 'Configura integrazione AI' }}
                            </a>
                        @endif
                    </div>

                    @if ($insightJobStatus ?? null)
                        <div class="mt-5 ui-alert ui-tone-accent" role="status">{{ $insightJobStatus['message'] }}</div>
                    @endif

                    @if (session('analytics_insight_status'))
                        <div class="mt-5 ui-alert ui-tone-success" role="status">{{ session('analytics_insight_status') }}</div>
                    @endif

                    @if (session('analytics_insight_error'))
                        <div class="mt-5 ui-alert ui-tone-danger" role="alert">{{ session('analytics_insight_error') }}</div>
                    @endif

                    @if ($analysis)
                        <div class="mt-7 border-t border-accent/25 pt-7">
                            <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-muted">
                                <span>{{ config('ai.providers.'.$analyticsInsight->provider.'.models.'.$analyticsInsight->model, $analyticsInsight->model) }} · {{ $analyticsInsight->generated_at->format('d/m/Y H:i') }}</span>
                                <span>{{ number_format($analyticsInsight->total_tokens, 0, ',', '.') }} token registrati</span>
                            </div>
                            <p class="mt-2 text-xs text-muted">Fonti di questa analisi: GA4{{ data_get($analyticsInsight->source_snapshot, 'search_console.status') === 'available' ? ' e Search Console' : ' (Search Console non inclusa)' }}.</p>
                            <p class="mt-4 text-lg leading-8 text-soft">{{ $analysis['summary'] }}</p>

                            <div class="mt-7 grid gap-4 lg:grid-cols-2">
                                @foreach ($analysis['findings'] as $finding)
                                    <x-insight-card :finding="$finding" />
                                @endforeach
                            </div>

                            <div class="mt-7 grid gap-6 lg:grid-cols-2">
                                <div>
                                    <h3 class="text-lg font-semibold">Azioni suggerite</h3>
                                    <ol class="mt-4 space-y-3">
                                        @foreach ($analysis['next_actions'] as $action)
                                            <li class="rounded-xl border border-line bg-subtle p-4">
                                                <div class="flex flex-wrap justify-between gap-2">
                                                    <span class="font-semibold text-soft">{{ $action['title'] }}</span>
                                                    <span class="text-xs text-muted">priorità {{ $action['priority'] }} · impegno {{ $action['effort'] }}</span>
                                                </div>
                                                <p class="mt-2 text-sm leading-6 text-muted">{{ $action['why'] }}</p>
                                            </li>
                                        @endforeach
                                    </ol>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold">Cose da non fare</h3>
                                    <div class="mt-4 space-y-3">
                                        @foreach ($analysis['things_to_avoid'] as $item)
                                            <div class="rounded-xl border border-warning-ink/22 bg-warning-soft p-4">
                                                <p class="font-semibold text-warning-ink">{{ $item['title'] }}</p>
                                                <p class="mt-2 text-sm leading-6 text-muted">{{ $item['reason'] }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <details class="mt-7 rounded-xl border border-line bg-subtle p-4 text-sm text-muted">
                                <summary class="cursor-pointer font-semibold text-soft">Limiti di questa analisi</summary>
                                <ul class="mt-3 space-y-2">
                                    @foreach ($analysis['limitations'] as $limitation)
                                        <li>• {{ $limitation }}</li>
                                    @endforeach
                                </ul>
                            </details>
                        </div>
                    @elseif ($activeAiIntegration?->status === 'verified')
                        <p class="mt-6 rounded-xl border border-dashed border-line-strong p-5 text-sm text-muted">Nessuna analisi salvata per questo intervallo. La chiamata parte solo quando premi “Analizza con AI”.</p>
                    @else
                        <p class="mt-6 rounded-xl border border-dashed border-line-strong p-5 text-sm text-muted">Per usare l’analista devi avere una sola integrazione AI attiva e verificata.</p>
                    @endif
                </section>
            @endif
        @endunless

        <section class="ui-panel mt-8 p-6 sm:p-8" aria-labelledby="search-console-heading">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-success-ink">Google Search Console</p>
                    <h2 id="search-console-heading" class="mt-2 text-2xl font-semibold">Visibilità nella ricerca Google</h2>
                    <p class="mt-2 text-sm leading-6 text-muted">Dati della proprietà selezionata, filtrati per l’host {{ $site->host }}. Sono distinti dalle sessioni GA4.</p>
                </div>
                @unless ($analyticsIsConnected)
                    <x-period-filter :site="$site" :days="$days" label="Scegli intervallo Search Console" />
                @endunless
            </div>

            @if ($searchReportError)
                <div class="mt-6 ui-alert ui-tone-warning" role="alert">
                    {{ $searchReportError }}
                    @unless ($searchIsConnected)
                        <a href="{{ route('sites.edit', $site) }}#integrations-heading" class="font-medium text-accent-ink">Gestisci il collegamento</a>
                    @endunless
                </div>
            @elseif (! $searchIsConnected)
                <p class="mt-6 text-sm text-muted">Search Console non è collegata. <a href="{{ route('sites.edit', $site) }}" class="font-medium text-accent-ink">Gestisci il collegamento</a> per vedere impressioni, clic, CTR e posizione.</p>
            @elseif (! $searchOverview['has_data'])
                <div class="mt-6 ui-alert ui-tone-warning">Search Console ha risposto, ma non ci sono dati finalizzati per questo host dal {{ $searchOverview['start_date'] }} al {{ $searchOverview['end_date'] }}. I valori assenti non sono mostrati come zero.</div>
            @else
                <p class="mt-5 text-xs text-muted">Dati finalizzati dal {{ $searchOverview['start_date'] }} al {{ $searchOverview['end_date'] }} (fuso Pacifico) · recuperati {{ \Carbon\CarbonImmutable::parse($searchOverview['fetched_at'])->format('d/m/Y H:i') }} · cache 1 ora</p>
                <dl class="ui-kpi-grid mt-5 [--ui-metric-count:4]">
                    @foreach (['clicks' => 'Clic', 'impressions' => 'Impressioni', 'ctr' => 'CTR', 'position' => 'Posizione media'] as $metric => $label)
                        <x-kpi :label="$label">{{ $metric === 'ctr' ? $formatPercentage($searchOverview['summary'][$metric]) : ($metric === 'position' ? number_format($searchOverview['summary'][$metric], 1, ',', '.') : $formatNumber($searchOverview['summary'][$metric])) }}</x-kpi>
                    @endforeach
                </dl>
                <div class="mt-7 grid gap-7 lg:grid-cols-2">
                    @foreach (['pages' => 'Pagine più cliccate', 'queries' => 'Principali ricerche'] as $group => $heading)
                        <div>
                            <h3 class="font-semibold text-ink">{{ $heading }}</h3>
                            <div class="ui-table-scroll">
                                <table class="ui-table">
                                    <thead class="text-xs text-muted"><tr><th class="py-2 pr-3">{{ $group === 'pages' ? 'Percorso' : 'Ricerca' }}</th><th class="py-2 text-right">Clic</th><th class="py-2 pl-3 text-right">Impressioni</th></tr></thead>
                                    <tbody class="divide-y divide-line">
                                        @forelse ($searchOverview[$group] as $row)
                                            <tr><td class="max-w-xs break-all py-2 pr-3 text-soft">{{ $row['key'] }}</td><td class="py-2 text-right">{{ $formatNumber($row['clicks']) }}</td><td class="py-2 pl-3 text-right">{{ $formatNumber($row['impressions']) }}</td></tr>
                                        @empty
                                            <tr><td colspan="3" class="py-3 text-muted">Nessuna riga disponibile.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="mt-5 text-xs leading-5 text-muted">Le liste mostrano solo le prime righe per clic e non rappresentano necessariamente tutte le pagine o ricerche. Search Console e GA4 misurano fenomeni diversi e non vanno confrontati come totali equivalenti.</p>
            @endif
        </section>
    </main>
@endsection
