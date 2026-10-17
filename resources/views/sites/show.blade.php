@extends('layouts.app')

@section('title', $site->name.' — '.config('app.name', 'Monitoring Hub'))

@section('content')
    @include('partials.navigation')

    @php
        $formatNumber = fn (mixed $value): string => number_format((float) $value, 0, ',', '.');
        $formatPercentage = fn (mixed $value): string => number_format((float) $value * 100, 1, ',', '.').'%' ;
        $formatDuration = function (mixed $value): string {
            $seconds = max(0, (int) round((float) $value));

            return $seconds >= 60
                ? intdiv($seconds, 60).' min '.str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT).' s'
                : $seconds.' s';
        };
        $dailyMaximum = $overview ? max(1, (int) collect($overview['daily'])->max('sessions')) : 1;
    @endphp

    <main class="mx-auto max-w-6xl px-6 py-10">
        <div class="mb-8 flex flex-wrap items-start justify-between gap-5">
            <div>
                <a href="{{ route('sites.index') }}" class="text-sm text-sky-400">← Torna ai siti</a>
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <h1 class="text-3xl font-semibold">{{ $site->name }}</h1>
                    <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $site->is_active ? 'bg-emerald-400/10 text-emerald-300' : 'bg-slate-700 text-slate-300' }}">{{ $site->is_active ? 'Monitoraggio attivo' : 'Monitoraggio sospeso' }}</span>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-400">
                    <a href="{{ $site->base_url }}" target="_blank" rel="noopener noreferrer" class="text-sky-400">{{ $site->host }} ↗</a>
                    @if ($analyticsProperty)
                        <span aria-hidden="true">•</span>
                        <span>{{ $analyticsProperty->property_name ?: 'Proprietà '.$analyticsProperty->property_id }}</span>
                    @endif
                </div>
            </div>

            <a href="{{ route('sites.edit', $site) }}" class="rounded-xl border border-slate-700 px-5 py-3 text-sm font-semibold text-slate-200 no-underline hover:border-slate-500 hover:bg-slate-900">Gestisci sito e collegamenti</a>
        </div>

        @unless ($analyticsIsConnected)
            <section class="rounded-3xl border border-dashed border-slate-700 bg-slate-900/60 p-10 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-800 text-xl" aria-hidden="true">↗</div>
                <h2 class="mt-5 text-xl font-semibold">Collega Google Analytics per vedere i dati</h2>
                <p class="mx-auto mt-2 max-w-2xl leading-7 text-slate-400">Questa pagina interroga i report GA4 in sola lettura. Prima completa o ripristina il collegamento dalla gestione del sito.</p>
                <a href="{{ route('sites.edit', $site) }}" class="mt-6 inline-flex rounded-xl bg-sky-500 px-5 py-3 font-semibold text-slate-950 no-underline hover:bg-sky-400">Vai ai collegamenti</a>
            </section>
        @else
            <section class="mb-7 rounded-2xl border border-slate-800 bg-slate-900 p-5" aria-label="Periodo del report">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-slate-200">Periodo analizzato</p>
                        <p class="mt-1 text-sm text-slate-400">Dati della proprietà filtrati esattamente per l’host <code class="text-slate-300">{{ $site->host }}</code>.</p>
                    </div>
                    <nav class="inline-flex rounded-xl border border-slate-700 bg-slate-950 p-1" aria-label="Scegli intervallo">
                        @foreach ([7, 28, 90] as $range)
                            <a href="{{ route('sites.show', ['site' => $site, 'range' => $range]) }}" @if ($days === $range) aria-current="page" @endif class="rounded-lg px-4 py-2 text-sm font-semibold no-underline {{ $days === $range ? 'bg-sky-500 text-slate-950' : 'text-slate-400 hover:text-white' }}">{{ $range }} giorni</a>
                        @endforeach
                    </nav>
                </div>
            </section>

            @if ($reportError)
                <section class="rounded-3xl border border-rose-500/30 bg-rose-500/10 p-8" role="alert">
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-rose-300">Dati non disponibili</p>
                    <h2 class="mt-2 text-xl font-semibold text-rose-100">La lettura dei report non è riuscita</h2>
                    <p class="mt-3 max-w-3xl leading-7 text-rose-100/80">{{ $reportError->getMessage() }}</p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        @if ($reportError->actionUrl && $reportError->actionLabel)
                            <a href="{{ $reportError->actionUrl }}" target="_blank" rel="noreferrer" class="rounded-xl bg-rose-200 px-5 py-3 font-semibold text-rose-950 no-underline hover:bg-white">{{ $reportError->actionLabel }} ↗</a>
                        @endif
                        <a href="{{ route('sites.edit', $site) }}" class="rounded-xl border border-rose-300/30 px-5 py-3 font-semibold text-rose-100 no-underline hover:bg-rose-500/10">Controlla il collegamento</a>
                    </div>
                </section>
            @elseif (! $overview['has_data'])
                <section class="rounded-3xl border border-amber-500/30 bg-amber-500/10 p-8">
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-amber-300">Richiesta completata</p>
                    <h2 class="mt-2 text-xl font-semibold text-amber-100">Nessun dato per questo dominio nel periodo scelto</h2>
                    <p class="mt-3 max-w-3xl leading-7 text-amber-100/80">Google ha risposto correttamente, ma non ha restituito attività per <strong>{{ $site->host }}</strong>. Prova un periodo più ampio e verifica che lo stream GA4 raccolga proprio questo hostname. I valori assenti non vengono mostrati come zero.</p>
                </section>
            @else
                <section aria-labelledby="overview-heading">
                    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-sky-400">Google Analytics 4</p>
                            <h2 id="overview-heading" class="mt-2 text-2xl font-semibold">Panoramica del traffico</h2>
                        </div>
                        <p class="text-xs text-slate-500">Recuperati {{ \Carbon\CarbonImmutable::parse($overview['fetched_at'])->format('d/m/Y H:i') }} · cache 10 minuti</p>
                    </div>

                    <dl class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                            <dt class="text-sm text-slate-400">Utenti attivi</dt>
                            <dd class="mt-2 text-3xl font-semibold text-white">{{ $formatNumber($overview['summary']['activeUsers'] ?? 0) }}</dd>
                        </div>
                        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                            <dt class="text-sm text-slate-400">Sessioni</dt>
                            <dd class="mt-2 text-3xl font-semibold text-white">{{ $formatNumber($overview['summary']['sessions'] ?? 0) }}</dd>
                        </div>
                        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                            <dt class="text-sm text-slate-400">Visualizzazioni</dt>
                            <dd class="mt-2 text-3xl font-semibold text-white">{{ $formatNumber($overview['summary']['screenPageViews'] ?? 0) }}</dd>
                        </div>
                        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                            <dt class="text-sm text-slate-400">Tasso di coinvolgimento</dt>
                            <dd class="mt-2 text-3xl font-semibold text-white">{{ $formatPercentage($overview['summary']['engagementRate'] ?? 0) }}</dd>
                            <p class="mt-2 text-xs text-slate-500">{{ $formatNumber($overview['summary']['engagedSessions'] ?? 0) }} sessioni coinvolte</p>
                        </div>
                        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                            <dt class="text-sm text-slate-400">Durata media sessione</dt>
                            <dd class="mt-2 text-3xl font-semibold text-white">{{ $formatDuration($overview['summary']['averageSessionDuration'] ?? 0) }}</dd>
                        </div>
                    </dl>
                </section>

                @php
                    $analysis = $analyticsInsight?->analysis ?? null;
                @endphp
                <section class="mt-7 rounded-3xl border border-violet-400/30 bg-violet-400/[0.04] p-6 sm:p-8" aria-labelledby="ai-insight-heading">
                    <div class="flex flex-wrap items-start justify-between gap-5">
                        <div class="max-w-3xl">
                            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-violet-300">Analista AI</p>
                            <h2 id="ai-insight-heading" class="mt-2 text-2xl font-semibold">I numeri tradotti in decisioni</h2>
                            <p class="mt-2 leading-7 text-slate-400">Confronta questo periodo con quello precedente, evidenzia rischi e opportunità e propone azioni motivate dai dati. L’analisi non modifica il sito né Google Analytics.</p>
                        </div>

                        @if ($activeAiIntegration?->status === 'verified')
                            <form method="POST" action="{{ route('sites.analytics-insights.store', $site) }}">
                                @csrf
                                <input type="hidden" name="range" value="{{ $days }}">
                                <button type="submit" class="rounded-xl bg-violet-400 px-5 py-3 font-semibold text-slate-950 hover:bg-violet-300">
                                    {{ $analysis ? 'Aggiorna analisi' : 'Analizza con AI' }}
                                </button>
                            </form>
                        @else
                            <a href="{{ route('settings.ai.index') }}" class="rounded-xl border border-violet-400/40 px-5 py-3 font-semibold text-violet-200 no-underline hover:bg-violet-400/10">
                                {{ $activeAiIntegration ? 'Verifica integrazione AI' : 'Configura integrazione AI' }}
                            </a>
                        @endif
                    </div>

                    @if (session('analytics_insight_status'))
                        <div class="mt-5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200" role="status">{{ session('analytics_insight_status') }}</div>
                    @endif

                    @if (session('analytics_insight_error'))
                        <div class="mt-5 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200" role="alert">{{ session('analytics_insight_error') }}</div>
                    @endif

                    @if ($analysis)
                        <div class="mt-7 border-t border-violet-400/20 pt-7">
                            <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500">
                                <span>{{ config('ai.providers.'.$analyticsInsight->provider.'.models.'.$analyticsInsight->model, $analyticsInsight->model) }} · {{ $analyticsInsight->generated_at->format('d/m/Y H:i') }}</span>
                                <span>{{ number_format($analyticsInsight->total_tokens, 0, ',', '.') }} token registrati</span>
                            </div>
                            <p class="mt-4 text-lg leading-8 text-slate-200">{{ $analysis['summary'] }}</p>

                            <div class="mt-7 grid gap-4 lg:grid-cols-2">
                                @foreach ($analysis['findings'] as $finding)
                                    @php
                                        $findingStyle = match ($finding['type']) {
                                            'risk' => ['label' => 'Criticità', 'class' => 'bg-rose-400/10 text-rose-300'],
                                            'opportunity' => ['label' => 'Opportunità', 'class' => 'bg-emerald-400/10 text-emerald-300'],
                                            default => ['label' => 'Osservazione', 'class' => 'bg-sky-400/10 text-sky-300'],
                                        };
                                        $priorityLabel = match ($finding['priority']) {
                                            'high' => 'Priorità alta',
                                            'medium' => 'Priorità media',
                                            default => 'Priorità bassa',
                                        };
                                    @endphp
                                    <article class="rounded-2xl border border-slate-800 bg-slate-950/60 p-5">
                                        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
                                            <span class="rounded-full px-2.5 py-1 {{ $findingStyle['class'] }}">{{ $findingStyle['label'] }}</span>
                                            <span class="text-slate-500">{{ $priorityLabel }} · confidenza {{ $finding['confidence'] }}</span>
                                        </div>
                                        <h3 class="mt-4 text-lg font-semibold">{{ $finding['title'] }}</h3>
                                        <p class="mt-2 text-sm leading-6 text-slate-400">{{ $finding['explanation'] }}</p>
                                        <ul class="mt-3 space-y-1 text-sm text-slate-500">
                                            @foreach ($finding['evidence'] as $evidence)
                                                <li>• {{ $evidence }}</li>
                                            @endforeach
                                        </ul>
                                        <p class="mt-4 rounded-xl bg-violet-400/[0.07] p-3 text-sm leading-6 text-violet-100"><strong>Azione:</strong> {{ $finding['recommended_action'] }}</p>
                                    </article>
                                @endforeach
                            </div>

                            <div class="mt-7 grid gap-6 lg:grid-cols-2">
                                <div>
                                    <h3 class="text-lg font-semibold">Prossime azioni</h3>
                                    <ol class="mt-4 space-y-3">
                                        @foreach ($analysis['next_actions'] as $action)
                                            <li class="rounded-xl border border-slate-800 bg-slate-950/50 p-4">
                                                <div class="flex flex-wrap justify-between gap-2">
                                                    <span class="font-semibold text-slate-200">{{ $action['title'] }}</span>
                                                    <span class="text-xs text-slate-500">priorità {{ $action['priority'] }} · impegno {{ $action['effort'] }}</span>
                                                </div>
                                                <p class="mt-2 text-sm leading-6 text-slate-400">{{ $action['why'] }}</p>
                                            </li>
                                        @endforeach
                                    </ol>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold">Cose da non fare</h3>
                                    <div class="mt-4 space-y-3">
                                        @foreach ($analysis['things_to_avoid'] as $item)
                                            <div class="rounded-xl border border-amber-500/20 bg-amber-500/[0.05] p-4">
                                                <p class="font-semibold text-amber-100">{{ $item['title'] }}</p>
                                                <p class="mt-2 text-sm leading-6 text-slate-400">{{ $item['reason'] }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <details class="mt-7 rounded-xl border border-slate-800 bg-slate-950/40 p-4 text-sm text-slate-400">
                                <summary class="cursor-pointer font-semibold text-slate-300">Limiti di questa analisi</summary>
                                <ul class="mt-3 space-y-2">
                                    @foreach ($analysis['limitations'] as $limitation)
                                        <li>• {{ $limitation }}</li>
                                    @endforeach
                                </ul>
                            </details>
                        </div>
                    @elseif ($activeAiIntegration?->status === 'verified')
                        <p class="mt-6 rounded-xl border border-dashed border-slate-700 p-5 text-sm text-slate-400">Nessuna analisi salvata per questo intervallo. La chiamata parte solo quando premi “Analizza con AI”.</p>
                    @else
                        <p class="mt-6 rounded-xl border border-dashed border-slate-700 p-5 text-sm text-slate-400">Per usare l’analista devi avere una sola integrazione AI attiva e verificata.</p>
                    @endif
                </section>

                <section class="mt-7 rounded-3xl border border-slate-800 bg-slate-900 p-6 sm:p-8" aria-labelledby="trend-heading">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 id="trend-heading" class="text-xl font-semibold">Sessioni giorno per giorno</h2>
                            <p class="mt-1 text-sm text-slate-400">Passa sui valori per leggere data, sessioni, utenti e visualizzazioni.</p>
                        </div>
                        <p class="text-sm text-slate-500">{{ \Carbon\CarbonImmutable::parse($overview['start_date'])->format('d/m/Y') }} – {{ \Carbon\CarbonImmutable::parse($overview['end_date'])->format('d/m/Y') }}</p>
                    </div>

                    <div class="mt-8 flex h-56 items-end gap-1 overflow-x-auto border-b border-slate-700 pb-px" role="img" aria-label="Grafico delle sessioni giornaliere">
                        @foreach ($overview['daily'] as $row)
                            @php($height = max(1.5, ((int) ($row['sessions'] ?? 0) / $dailyMaximum) * 100))
                            <div class="group relative flex h-full min-w-2 flex-1 items-end" title="{{ \Carbon\CarbonImmutable::parse($row['date'])->format('d/m/Y') }} — {{ $row['sessions'] ?? 0 }} sessioni, {{ $row['activeUsers'] ?? 0 }} utenti, {{ $row['screenPageViews'] ?? 0 }} visualizzazioni">
                                <div class="w-full rounded-t bg-sky-500/70 transition group-hover:bg-sky-300" style="height: {{ $height }}%"></div>
                            </div>
                        @endforeach
                    </div>
                    @if ($overview['daily'] !== [])
                        <div class="mt-2 flex justify-between text-xs text-slate-500">
                            <span>{{ \Carbon\CarbonImmutable::parse($overview['daily'][0]['date'])->format('d/m') }}</span>
                            <span>{{ \Carbon\CarbonImmutable::parse($overview['daily'][array_key_last($overview['daily'])]['date'])->format('d/m') }}</span>
                        </div>
                    @endif
                </section>

                <div class="mt-7 grid gap-7 lg:grid-cols-[1.35fr_0.65fr]">
                    <section class="overflow-hidden rounded-3xl border border-slate-800 bg-slate-900" aria-labelledby="pages-heading">
                        <div class="border-b border-slate-800 p-6 sm:px-8">
                            <h2 id="pages-heading" class="text-xl font-semibold">Pagine principali</h2>
                            <p class="mt-1 text-sm text-slate-400">Le pagine più viste sul dominio nel periodo selezionato.</p>
                        </div>
                        @if ($overview['pages'] === [])
                            <p class="p-8 text-sm text-slate-400">Nessuna pagina disponibile.</p>
                        @else
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead class="text-xs uppercase tracking-wider text-slate-500">
                                        <tr>
                                            <th class="px-6 py-4 sm:pl-8">Pagina</th>
                                            <th class="px-4 py-4 text-right">Viste</th>
                                            <th class="px-6 py-4 text-right sm:pr-8">Utenti</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-800">
                                        @foreach ($overview['pages'] as $page)
                                            @php($pageUrl = rtrim($site->base_url, '/').'/'.ltrim($page['pagePathPlusQueryString'] ?? '/', '/'))
                                            <tr>
                                                <td class="max-w-xl px-6 py-4 sm:pl-8">
                                                    <a href="{{ $pageUrl }}" target="_blank" rel="noopener noreferrer" class="block truncate font-medium text-slate-200 no-underline hover:text-sky-300" title="{{ $page['pageTitle'] ?? '' }}">{{ filled($page['pageTitle'] ?? null) ? $page['pageTitle'] : ($page['pagePathPlusQueryString'] ?? '/') }}</a>
                                                    <span class="mt-1 block truncate text-xs text-slate-500">{{ $page['pagePathPlusQueryString'] ?? '/' }}</span>
                                                </td>
                                                <td class="px-4 py-4 text-right font-semibold text-slate-200">{{ $formatNumber($page['screenPageViews'] ?? 0) }}</td>
                                                <td class="px-6 py-4 text-right text-slate-400 sm:pr-8">{{ $formatNumber($page['activeUsers'] ?? 0) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </section>

                    <section class="rounded-3xl border border-slate-800 bg-slate-900 p-6 sm:p-8" aria-labelledby="channels-heading">
                        <h2 id="channels-heading" class="text-xl font-semibold">Canali di acquisizione</h2>
                        <p class="mt-1 text-sm text-slate-400">Da dove iniziano le sessioni.</p>
                        <div class="mt-6 space-y-5">
                            @forelse ($overview['channels'] as $channel)
                                <div>
                                    <div class="flex items-center justify-between gap-4 text-sm">
                                        <span class="font-medium text-slate-200">{{ $channel['sessionDefaultChannelGroup'] ?: 'Non disponibile' }}</span>
                                        <span class="text-slate-400">{{ $formatNumber($channel['sessions'] ?? 0) }} sessioni</span>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">{{ $formatNumber($channel['activeUsers'] ?? 0) }} utenti · {{ $formatPercentage($channel['engagementRate'] ?? 0) }} coinvolgimento</p>
                                </div>
                            @empty
                                <p class="text-sm text-slate-400">Nessun canale disponibile.</p>
                            @endforelse
                        </div>
                    </section>
                </div>

                <div class="mt-7 grid gap-7 md:grid-cols-2">
                    @foreach (['devices' => ['Dispositivi', 'Come vengono avviate le sessioni.'], 'countries' => ['Paesi', 'Provenienza geografica aggregata delle sessioni.']] as $group => [$heading, $description])
                        @php($maximum = max(1, (int) collect($overview[$group])->max('sessions')))
                        <section class="rounded-3xl border border-slate-800 bg-slate-900 p-6 sm:p-8" aria-labelledby="{{ $group }}-heading">
                            <h2 id="{{ $group }}-heading" class="text-xl font-semibold">{{ $heading }}</h2>
                            <p class="mt-1 text-sm text-slate-400">{{ $description }}</p>
                            <div class="mt-6 space-y-4">
                                @forelse ($overview[$group] as $item)
                                    <div>
                                        <div class="mb-2 flex items-center justify-between gap-4 text-sm">
                                            <span class="font-medium text-slate-200">{{ $item['label'] }}</span>
                                            <span class="text-slate-400">{{ $formatNumber($item['sessions']) }}</span>
                                        </div>
                                        <div class="h-2 overflow-hidden rounded-full bg-slate-800">
                                            <div class="h-full rounded-full bg-sky-500" style="width: {{ max(1, ($item['sessions'] / $maximum) * 100) }}%"></div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-sm text-slate-400">Nessun dato disponibile.</p>
                                @endforelse
                            </div>
                        </section>
                    @endforeach
                </div>

                <p class="mt-6 text-xs leading-5 text-slate-500">Questa è una prima vista esplorativa dei dati disponibili tramite Google Analytics Data API. I numeri possono differire dai report standard di GA4 per periodo, filtri, modellazione e aggiornamento dei dati.</p>
            @endif
        @endunless
    </main>
@endsection
