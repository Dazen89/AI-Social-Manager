@extends('layouts.workspace')

@section('title', 'Piano editoriale — '.$site->name.' — '.config('app.name', 'Monitoring Hub'))

@section('page')
    <main id="main-content" class="ui-page" tabindex="-1">
        <x-page-header title="Piano editoriale" :kicker="$site->name" description="Il calendario mostra quando Monitor propone di pubblicare su X. Qui puoi anche rivedere o escludere i capitoli letti dalla fonte.">
            <x-slot:back><a href="{{ route('sites.show', $site) }}">← Torna alla panoramica</a></x-slot:back>
            <x-slot:actions><a href="{{ route('sites.editorial-plan.sources.index', $site) }}" class="ui-button"><x-icon name="settings" /> Gestisci fonti</a></x-slot:actions>
        </x-page-header>

        <x-editorial-tabs :site="$site" />

        @if (session('status')) <p class="mt-6 ui-alert ui-tone-success" role="status">{{ session('status') }}</p> @endif
        @if (session('workflow_error')) <p class="mt-6 ui-alert ui-tone-danger" role="alert">{{ session('workflow_error') }}</p> @endif
        @if ($errors->any()) <p class="mt-6 ui-alert ui-tone-danger" role="alert">{{ $errors->first() }}</p> @endif

        <section class="ui-panel mt-8 p-6 sm:p-8" aria-labelledby="schedule-heading">
            <h2 id="schedule-heading" class="text-xl font-semibold">Piano automatico</h2>
            <p class="mt-2 text-sm text-muted">A ogni controllo Monitor legge i capitoli dalla fonte e l’AI rivaluta il piano, anche quando non ci sono nuove uscite. Puoi avviare lo stesso controllo adesso o ripeterlo automaticamente alla frequenza scelta. La pubblicazione richiede una seconda attivazione esplicita.</p>
            <form method="POST" action="{{ route('sites.editorial-plan.run', $site) }}" class="ui-actions mt-4">
                @csrf
                <button type="submit" class="ui-button ui-button-primary">Analizza ora</button>
                <span class="text-xs text-muted">Avvia in background la lettura delle fonti e l’analisi AI; il calendario si aggiorna al completamento.</span>
            </form>
            @unless ($schedule)
                <p class="mt-4 ui-alert ui-tone-warning" role="status">Non hai ancora attivato i controlli periodici. Puoi comunque usare “Analizza ora” per creare il piano senza attendere lo Scheduler.</p>
            @endunless
            <form method="POST" action="{{ route('sites.editorial-plan.schedule.update', $site) }}" class="mt-5 grid gap-4 sm:grid-cols-4 sm:items-end">
                @csrf @method('PUT')
                <div>
                    <label for="interval_hours" class="block text-sm font-medium text-soft">Frequenza controllo fonti</label>
                    <select id="interval_hours" name="interval_hours" class="mt-2 w-full rounded-xl border border-line-strong bg-subtle px-3 py-2 text-ink">
                        @foreach ([6 => 'Ogni 6 ore', 12 => 'Ogni 12 ore', 24 => 'Una volta al giorno'] as $hours => $label)
                            <option value="{{ $hours }}" @selected((int) old('interval_hours', $schedule?->interval_hours ?? 24) === $hours)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="start_time" class="block text-sm font-medium text-soft">Ora di riferimento</label>
                    <input id="start_time" name="start_time" type="time" value="{{ old('start_time', $schedule?->start_time ?? '09:00') }}" required class="mt-2 w-full rounded-xl border border-line-strong bg-subtle px-3 py-2 text-ink">
                </div>
                <div>
                    <label for="timezone" class="block text-sm font-medium text-soft">Fuso orario</label>
                    <input id="timezone" name="timezone" value="{{ old('timezone', $schedule?->timezone ?? 'Europe/Rome') }}" required maxlength="64" list="editorial-timezones" class="mt-2 w-full rounded-xl border border-line-strong bg-subtle px-3 py-2 text-ink">
                    <datalist id="editorial-timezones"><option value="Europe/Rome"><option value="UTC"><option value="America/New_York"></datalist>
                </div>
                <div class="flex flex-wrap gap-2 sm:col-span-4">
                    <input type="hidden" name="enabled" value="0">
                    <label class="flex items-center gap-2 text-sm text-soft"><input type="checkbox" name="enabled" value="1" @checked((bool) old('enabled', $schedule?->enabled ?? false)) class="rounded border-line-strong">Attiva piano automatico</label>
                    <button class="ui-button ui-button-primary">Salva pianificazione</button>
                </div>
                <div class="sm:col-span-4 rounded-xl border border-warning-ink/22 bg-warning-soft p-4 text-sm text-soft">
                    <p class="font-semibold text-warning-ink">Pubblicazione automatica su X</p>
                    <p class="mt-1">L’AI approva i post delle fonti autorizzate e Monitor li invia all’ora prevista. Account: {{ $xConnection ? '@'.$xConnection->username : 'non collegato' }}. Le fonti si autorizzano nella pagina Fonti. La pubblicazione è bloccata fuori dalla produzione e finché l’operatore non abilita X_AUTO_PUBLISH_ALLOWED.</p>
                    <input type="hidden" name="x_autopublish_enabled" value="0">
                    <label class="mt-3 flex items-center gap-2"><input type="checkbox" name="x_autopublish_enabled" value="1" @checked((bool) old('x_autopublish_enabled', $schedule?->x_autopublish_enabled ?? false)) class="rounded border-line-strong">Attiva approvazione AI e pubblicazione automatica</label>
                </div>
            </form>
            @if ($schedule?->enabled)
                <p class="mt-4 text-sm text-soft">Prossimo controllo: {{ $schedule->next_run_at?->setTimezone($timezone)->format('d/m/Y H:i') ?? 'in attesa' }} ({{ $timezone }}). Ultimo controllo riuscito: {{ $schedule->last_run_at?->setTimezone($timezone)->format('d/m/Y H:i') ?? 'non ancora eseguito' }}.</p>
                @if ($schedule->last_run_at) <p class="mt-1 text-sm text-muted">Ultimo esito: {{ $schedule->last_imported_count }} nuove uscite, {{ $schedule->last_proposal_count }} nuove proposte.</p> @endif
            @else
                <p class="mt-4 text-sm text-warning-ink">Automazione disattivata. Servono almeno una fonte verificata e un servizio AI attivo e verificato.</p>
            @endif
            @if ($schedule?->last_error) <p class="mt-2 text-sm text-danger-ink" role="alert">Ultimo tentativo non riuscito: {{ $schedule->last_error }}@if ($schedule->enabled) Nuovo tentativo previsto tra 15 minuti.@endif</p> @endif
        </section>

        <section class="ui-panel mt-8 p-6 sm:p-8" aria-labelledby="cadence-heading">
            <h2 id="cadence-heading" class="text-xl font-semibold">Cadenza editoriale giornaliera</h2>
            @if ($cadence)
                <p class="mt-3 text-lg font-semibold text-accent-ink">{{ $cadence->posts_per_week }} post a settimana <span class="text-sm font-normal text-muted">· un post al giorno · fiducia dell’analisi {{ match ($cadence->confidence) { 'high' => 'alta', 'medium' => 'media', default => 'bassa' } }}</span></p>
                <p class="mt-3 text-sm text-soft"><span class="font-semibold">Perché:</span> {{ $cadence->rationale }}</p>
                <p class="mt-3 text-xs text-muted">Analisi del {{ $cadence->generated_at->setTimezone($timezone)->format('d/m/Y H:i') }}. {{ ($cadence->source_snapshot['google_analytics']['status'] ?? null) === 'available' ? 'GA4 disponibile' : 'GA4 non disponibile' }} · {{ ($cadence->source_snapshot['search_console']['status'] ?? null) === 'available' ? 'Search Console disponibile' : 'Search Console non disponibile' }} · {{ ($cadence->source_snapshot['x_post_metrics']['status'] ?? null) === 'available' ? 'Metriche dei post X disponibili' : 'metriche dei post X non ancora raccolte' }}.</p>
            @else
                <p class="mt-3 text-sm text-muted">Il piano prevede un post al giorno. La prima analisi AI partirà quando il piano automatico sarà attivo, lo Scheduler avrà eseguito un controllo e saranno presenti capitoli candidabili. Le metriche dei post X non sono ancora raccolte.</p>
            @endif
        </section>

        <section class="ui-panel mt-8 p-6 sm:p-8" aria-labelledby="calendar-heading">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 id="calendar-heading" class="text-xl font-semibold">Calendario</h2>
                    <p class="mt-1 text-sm text-muted">Qui compaiono solo i post X, nella data e nell’ora proposte ({{ $timezone }}), con motivazione. “Bozza” non viene inviata; “Approvata” sarà inviata soltanto con la pubblicazione automatica attiva; “Pubblicata” è già su X.</p>
                </div>
                <div class="ui-actions">
                    <a href="{{ route('sites.editorial-plan.show', ['site' => $site, 'month' => $month->subMonth()->format('Y-m')]) }}" aria-label="Mese precedente" class="ui-button ui-button-small">←</a>
                    <span class="min-w-32 text-center font-semibold capitalize text-ink">{{ $month->locale('it')->isoFormat('MMMM YYYY') }}</span>
                    <a href="{{ route('sites.editorial-plan.show', ['site' => $site, 'month' => $month->addMonth()->format('Y-m')]) }}" aria-label="Mese successivo" class="ui-button ui-button-small">→</a>
                    <a href="{{ route('sites.editorial-plan.show', $site) }}" class="ui-button ui-button-small">Oggi</a>
                </div>
            </div>

            <div class="ui-table-scroll mt-6">
                <table class="w-full min-w-[42rem] table-fixed border-separate border-spacing-1 text-left" aria-label="Calendario di {{ $month->locale('it')->isoFormat('MMMM YYYY') }}">
                    <thead>
                        <tr>
                            @foreach (['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'] as $weekday)
                                <th scope="col" class="px-3 py-2 text-xs font-semibold uppercase tracking-wider text-muted">{{ $weekday }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($weeks as $week)
                            <tr>
                                @foreach ($week as $day)
                                    <td class="h-24 align-top rounded-xl border {{ $day === null ? 'border-transparent bg-subtle' : 'border-line bg-subtle' }} p-3">
                                        @if ($day !== null)
                                            @php($date = $month->setDay($day)->format('Y-m-d'))
                                            <time datetime="{{ $date }}" class="inline-flex size-8 items-center justify-center rounded-full text-sm {{ $date === $today ? 'bg-accent font-bold text-white' : 'text-soft' }}">{{ $day }}</time>
                                            @foreach ($calendarProposals->get($date, collect()) as $proposal)
                                                <a href="#proposal-{{ $proposal->id }}" class="mt-1 block rounded-lg bg-accent-soft px-2 py-1 text-xs text-accent-ink no-underline hover:bg-accent-soft">
                                                    <span class="font-semibold">{{ $proposal->proposed_for->setTimezone($timezone)->format('H:i') }} · {{ $proposal->content->series_title }}</span>
                                                    <span class="block">{{ match ($proposal->status) { 'approved' => 'Approvata', 'published' => 'Pubblicata su X', 'delivery_uncertain' => 'Esito X da verificare', default => 'Bozza' } }}</span>
                                                    <span class="mt-1 block text-soft">{{ \Illuminate\Support\Str::limit($proposal->rationale, 100) }}</span>
                                                </a>
                                            @endforeach
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($calendarProposals->isEmpty())
                <p class="mt-5 text-sm text-muted">Nessun post proposto in questo mese. Usa “Analizza ora” per rivalutare i capitoli disponibili oppure consulta un altro mese.</p>
            @endif
        </section>

        <section class="ui-panel mt-8 p-6 sm:p-8" aria-labelledby="chapters-heading">
            <h2 id="chapters-heading" class="text-xl font-semibold">Capitoli letti dalle fonti</h2>
            <p class="mt-2 text-sm text-muted">Gestisci qui le uscite: escludere un capitolo lo lascia nello storico ma impedisce che venga proposto di nuovo. I 30 più recenti sono mostrati sotto il calendario; la data del capitolo non occupa una casella del piano X.</p>
            @forelse ($recentContents as $content)
                <article class="mt-4 rounded-2xl border border-line-strong bg-subtle p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold">{{ $content->title }} <span class="font-normal text-muted">· {{ $content->series_title }}</span></p>
                            <p class="mt-1 text-xs text-muted">{{ $content->source->name }} · {{ $content->released_at?->setTimezone($timezone)->format('d/m/Y') ?? 'data non disponibile' }} · {{ match ($content->eligibility_status) { 'eligible' => 'Idoneo', 'excluded' => 'Escluso', default => 'Da valutare' } }} · {{ $content->proposal ? 'Post: '.$content->proposal->status : 'Nessun post proposto' }}</p>
                            <a href="{{ $content->public_url }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-block text-xs text-accent-ink">Apri il capitolo ↗</a>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if ($content->eligibility_status !== 'excluded')
                                <form method="POST" action="{{ route('sites.editorial-plan.contents.eligibility', [$site, $content]) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="excluded"><button class="ui-button ui-button-small">Escludi</button></form>
                            @else
                                <form method="POST" action="{{ route('sites.editorial-plan.contents.eligibility', [$site, $content]) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="eligible"><button class="ui-button ui-tone-success ui-button-small">Ripristina</button></form>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <p class="mt-4 text-sm text-muted">Nessun capitolo letto. Usa “Analizza ora” dopo aver verificato una fonte.</p>
            @endforelse
            <div class="mt-5">{{ $recentContents->links() }}</div>
        </section>

        <section class="ui-panel mt-8 p-6 sm:p-8" aria-labelledby="proposals-heading">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 id="proposals-heading" class="text-xl font-semibold">Proposte editoriali</h2>
                    <p class="mt-2 text-sm text-muted">Capitoli candidabili senza proposta: {{ $candidateCount }}. L’AI rivaluta il piano a ogni controllo, anche senza nuove uscite, usando i dati GA4 e Search Console disponibili; gli orari restano ipotesi da verificare.</p>
                </div>
            </div>
            @unless ($aiReady) <p class="mt-3 text-sm text-warning-ink">Per generare bozze, <a href="{{ route('settings.ai.index') }}" class="underline">verifica e attiva un servizio AI</a>.</p> @endunless
            @forelse ($proposals as $proposal)
                <article id="proposal-{{ $proposal->id }}" class="mt-5 rounded-2xl border border-line-strong bg-subtle p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="font-semibold">{{ $proposal->content->series_title }} — {{ $proposal->content->title }}</h3>
                        <span class="text-sm text-muted">X · {{ match ($proposal->status) { 'approved' => 'Approvata', 'rejected' => 'Scartata', 'deferred' => 'Rimandata dall’AI', 'published' => 'Pubblicata', 'delivery_uncertain' => 'Esito da verificare', default => 'Bozza' } }}</span>
                    </div>
                    <p class="mt-3 whitespace-pre-wrap break-words text-sm text-soft">{{ $proposal->post_text }}</p>
                    <p class="mt-3 text-sm text-muted">Data suggerita: {{ $proposal->proposed_for?->setTimezone($timezone)->format('d/m/Y H:i') ?? 'non impostata' }} ({{ $timezone }})</p>
                    <p class="mt-2 text-sm text-soft"><span class="font-semibold">Motivazione:</span> {{ $proposal->rationale }}</p>
                    @if ($proposal->x_post_id) <a href="https://x.com/i/web/status/{{ $proposal->x_post_id }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-block text-sm text-accent-ink">Apri il post su X ↗</a> @endif
                    @if ($proposal->delivery_error) <p class="mt-2 text-sm text-danger-ink">{{ $proposal->delivery_error }}</p> @endif
                    @if ($proposal->content->eligibility_status !== 'eligible')
                        <p class="mt-3 text-sm text-warning-ink">Contenuto da verificare: conferma diritti e idoneità prima di approvare la proposta.</p>
                        @if ($proposal->content->eligibility_status === 'pending')
                            <form method="POST" action="{{ route('sites.editorial-plan.contents.eligibility', [$site, $proposal->content]) }}" class="mt-2">@csrf @method('PATCH')<input type="hidden" name="status" value="eligible"><button class="ui-button ui-tone-warning">Confermo l’idoneità promozionale</button></form>
                        @endif
                    @endif
                    @if (! in_array($proposal->status, ['published', 'delivery_uncertain', 'delivering'], true))
                    <details class="mt-4">
                        <summary class="cursor-pointer text-sm text-accent-ink">Modifica bozza e data</summary>
                        <form method="POST" action="{{ route('sites.editorial-plan.proposals.update', [$site, $proposal]) }}" class="mt-3 space-y-3">
                            @csrf @method('PATCH')
                            <label class="block text-sm text-soft" for="post-text-{{ $proposal->id }}">Testo (termina con il link della fonte)</label>
                            <textarea id="post-text-{{ $proposal->id }}" name="post_text" rows="5" maxlength="280" required class="w-full rounded-xl border border-line-strong bg-surface p-3 text-ink">{{ $proposal->post_text }}</textarea>
                            <label class="block text-sm text-soft" for="proposed-for-{{ $proposal->id }}">Data e ora suggerite</label>
                            <input id="proposed-for-{{ $proposal->id }}" name="proposed_for" type="datetime-local" value="{{ $proposal->proposed_for?->setTimezone($timezone)->format('Y-m-d\TH:i') }}" required class="rounded-xl border border-line-strong bg-surface p-3 text-ink">
                            <button class="ui-button">Salva bozza</button>
                        </form>
                    </details>
                    @endif
                    <div class="mt-4 flex flex-wrap gap-2">
                        @if (! in_array($proposal->status, ['approved', 'published', 'delivery_uncertain', 'delivering'], true) && $proposal->content->eligibility_status === 'eligible')
                            <form method="POST" action="{{ route('sites.editorial-plan.proposals.review', [$site, $proposal]) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="approved"><button class="ui-button ui-tone-success">Approva proposta</button></form>
                        @endif
                        @if (! in_array($proposal->status, ['rejected', 'published', 'delivery_uncertain', 'delivering'], true))
                            <form method="POST" action="{{ route('sites.editorial-plan.proposals.review', [$site, $proposal]) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="rejected"><button class="ui-button">Scarta</button></form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="mt-5 rounded-2xl border border-dashed border-line-strong bg-subtle p-6">
                    <p class="font-medium text-soft">Nessuna proposta disponibile</p>
                    <p class="mt-2 text-sm leading-6 text-muted">Verifica una fonte e usa “Analizza ora”, oppure attiva i controlli periodici. I post selezionati dall’AI appariranno nel calendario con le motivazioni.</p>
                </div>
            @endforelse
            <p class="mt-5 text-sm text-warning-ink">La pubblicazione automatica richiede consenso della fonte, account X collegato, abilitazione per il sito e ambiente di produzione. In locale nessuna bozza viene inviata a X.</p>
            <p class="mt-4 text-sm text-muted">Fonti configurate: {{ $sourcesCount }}. <a href="{{ route('sites.editorial-plan.sources.index', $site) }}" class="font-medium text-accent-ink">Apri la gestione delle fonti →</a></p>
        </section>
    </main>
@endsection
