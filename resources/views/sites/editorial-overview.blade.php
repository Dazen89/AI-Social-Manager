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
                <a href="{{ request()->fullUrl() }}" class="ui-button">Ricarica lo stato</a>
                <span class="text-xs text-muted">Il worker di coda elabora la richiesta in background.</span>
            </form>

            <dl class="mt-4 grid gap-4 rounded-xl border border-line bg-subtle p-4 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-muted">Ultimo aggiornamento riuscito</dt>
                    <dd class="mt-1">
                        <span class="font-semibold">{{ $schedule?->last_run_at?->setTimezone($timezone)->format('d/m/Y H:i') ?? 'Mai completato' }}</span>
                        @if ($schedule?->last_run_at)
                            <span class="mt-1 block text-xs text-muted">{{ $schedule->last_imported_count }} nuove uscite, {{ $schedule->last_proposal_count }} proposte elaborate.</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-muted">Ultimo tentativo avviato</dt>
                    <dd class="mt-1">
                        <span class="font-semibold">{{ $schedule?->last_attempt_at?->setTimezone($timezone)->format('d/m/Y H:i') ?? 'Mai avviato' }}</span>
                        @if ($schedule?->running_started_at?->isAfter(now()->subMinutes(10)))
                            <span class="mt-1 block text-xs text-accent-ink" role="status">Analisi in corso</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-muted">Prossimo controllo automatico</dt>
                    <dd class="mt-1 font-semibold">
                        @if ($schedule?->enabled)
                            {{ $schedule->next_run_at?->setTimezone($timezone)->format('d/m/Y H:i') ?? 'In attesa' }}
                            @if ($schedule->next_run_at?->isPast())
                                <span class="ui-badge ui-tone-warning">In ritardo</span>
                            @endif
                        @else
                            Automazione disattivata
                        @endif
                    </dd>
                </div>
            </dl>
            <p class="mt-2 text-xs text-muted">Orari nel fuso {{ $timezone }}.</p>
            @if ($schedule?->last_error)
                <p class="mt-2 text-sm text-danger-ink" role="alert">Ultimo tentativo non riuscito: {{ $schedule->last_error }}</p>
            @endif

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
                    <p class="mt-1">In produzione l’AI approva le proposte delle fonti autorizzate e Monitor le invia all’ora prevista. Account {{ $xConnection ? '@'.$xConnection->username : 'da collegare' }}. Gestisci l’account e l’abilitazione nella pagina <a class="underline" href="{{ route('settings.x.edit') }}">Social</a> e autorizza le fonti nella pagina <a class="underline" href="{{ route('sites.editorial-plan.sources.index', $site) }}">Fonti</a>.</p>
                    @unless ($xConnection?->canPublish(automatic: true))
                        <p class="mt-2 text-warning-ink">Invio automatico sospeso. Abilita la pubblicazione automatica dell’account X in Social.</p>
                    @endunless
                    <input type="hidden" name="x_autopublish_enabled" value="0">
                    <label class="mt-3 flex items-center gap-2"><input type="checkbox" name="x_autopublish_enabled" value="1" @checked((bool) old('x_autopublish_enabled', $schedule?->x_autopublish_enabled ?? false)) @disabled(! $schedule?->x_autopublish_enabled && (! $xConnection?->canPublish(automatic: true) || ! app()->environment('production'))) class="rounded border-line-strong">Attiva approvazione AI e pubblicazione automatica</label>
                </div>
            </form>
        </section>

        <section class="ui-panel mt-8 p-6 sm:p-8" aria-labelledby="cadence-heading">
            <h2 id="cadence-heading" class="text-xl font-semibold">Cadenza editoriale giornaliera</h2>
            @if ($cadence)
                <p class="mt-3 text-lg font-semibold text-accent-ink">{{ $cadence->posts_per_week }} post a settimana <span class="text-sm font-normal text-muted">· un post al giorno · fiducia dell’analisi {{ match ($cadence->confidence) { 'high' => 'alta', 'medium' => 'media', default => 'bassa' } }}</span></p>
                <p class="mt-3 text-sm text-soft"><span class="font-semibold">Perché:</span> {{ $cadence->rationale }}</p>
                <p class="mt-3 text-xs text-muted">Analisi del {{ $cadence->generated_at->setTimezone($timezone)->format('d/m/Y H:i') }}. {{ ($cadence->source_snapshot['google_analytics']['status'] ?? null) === 'available' ? 'GA4 disponibile' : 'GA4 non disponibile' }} · {{ ($cadence->source_snapshot['search_console']['status'] ?? null) === 'available' ? 'Search Console disponibile' : 'Search Console non disponibile' }} · {{ ($cadence->source_snapshot['x_post_metrics']['status'] ?? null) === 'available' ? 'Metriche dei post X disponibili' : 'metriche dei post X non ancora raccolte' }}.</p>
            @else
                <p class="mt-3 text-sm text-muted">Il piano prevede un post al giorno. Avvia il primo controllo con «Analizza ora» oppure attiva il piano automatico.</p>
            @endif
        </section>

        <section class="ui-panel mt-8 p-6 sm:p-8" aria-labelledby="x-metrics-heading">
            <h2 id="x-metrics-heading" class="text-xl font-semibold">Come vengono usate le metriche di X</h2>
            <dl class="mt-4 space-y-3 text-sm text-soft">
                <div>
                    <dt class="font-semibold text-ink">Quando raccogliamo i dati</dt>
                    <dd>In produzione, con la pubblicazione su X abilitata, l’app controlla ogni sei ore i post pubblicati dal piano editoriale. Prova a salvare una rilevazione dopo circa 24 ore e una dopo 7 giorni. Legge da X impressioni, Mi piace, risposte, repost, citazioni e segnalibri. Se X li rende disponibili per l’account che ha pubblicato, salva anche i clic sul link.</dd>
                </div>
                <div>
                    <dt class="font-semibold text-ink">Come li confrontiamo</dt>
                    <dd>«Analizza ora» usa le rilevazioni già salvate per un massimo di 30 post di questo sito pubblicati negli ultimi 30 giorni. L’AI confronta post di serie simili misurati alla stessa distanza dalla pubblicazione e guarda quante interazioni o clic hanno ottenuto ogni 100 impressioni. Usa questi segnali per proporre formati di testo da provare.</dd>
                </div>
                <div>
                    <dt class="font-semibold text-ink">Che cosa significano i clic</dt>
                    <dd>X conta i clic sui link nel post. Google Analytics, se collegato, misura le sessioni sul sito. L’analisi tiene distinti i due conteggi e segnala quando i dati sono scarsi.</dd>
                </div>
            </dl>
            @if ($cadence && data_get($cadence->source_snapshot, 'x_post_metrics.sample_size', 0) > 0)
                <p class="mt-4 text-sm text-muted">Post X inclusi nell’ultima analisi: <strong>{{ data_get($cadence->source_snapshot, 'x_post_metrics.sample_size') }}</strong>.</p>
            @elseif ($cadence)
                <p class="mt-4 text-sm text-muted">Nessuna rilevazione X disponibile al momento dell’ultima analisi.</p>
            @else
                <p class="mt-4 text-sm text-muted">Il numero di post inclusi comparirà dopo la prima analisi.</p>
            @endif
        </section>

        @php($calendarParams = ['site' => $site, 'published' => $includePublished ? 1 : null])
        <section class="ui-panel mt-8 p-6 sm:p-8" aria-labelledby="calendar-heading">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 id="calendar-heading" class="text-xl font-semibold">Calendario</h2>
                    <p class="mt-1 text-sm text-muted">Il calendario mostra l’orario proposto per le bozze e quello effettivo per i post pubblicati ({{ $timezone }}). Puoi inviare una proposta con «Pubblica ora» o farla pubblicare all’ora prevista con il piano automatico attivo.</p>
                </div>
                <div class="ui-actions">
                    <a href="{{ route('sites.editorial-plan.show', $calendarParams + ['month' => $month->subMonth()->format('Y-m')]) }}" aria-label="Mese precedente" class="ui-button ui-button-small">←</a>
                    <span class="min-w-32 text-center font-semibold capitalize text-ink">{{ $month->locale('it')->isoFormat('MMMM YYYY') }}</span>
                    <a href="{{ route('sites.editorial-plan.show', $calendarParams + ['month' => $month->addMonth()->format('Y-m')]) }}" aria-label="Mese successivo" class="ui-button ui-button-small">→</a>
                    <a href="{{ route('sites.editorial-plan.show', $calendarParams) }}" class="ui-button ui-button-small">Oggi</a>
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
                                                @php($calendarPostPublished = $proposal->status === 'published')
                                                @php($calendarPostParams = ['site' => $site, 'month' => $month->format('Y-m'), 'published' => $includePublished || $calendarPostPublished ? 1 : null, 'proposal' => $proposal->id])
                                                @php($calendarPostUrl = $proposals->contains('id', $proposal->id) ? '' : route('sites.editorial-plan.show', $calendarPostParams))
                                                <a href="{{ $calendarPostUrl }}#proposal-{{ $proposal->id }}" class="mt-1 block rounded-lg px-2 py-1 text-xs no-underline {{ $calendarPostPublished ? 'ui-tone-success' : 'bg-accent-soft text-accent-ink' }}">
                                                    <span class="font-semibold">{{ ($proposal->published_at ?? $proposal->proposed_for)->setTimezone($timezone)->format('H:i') }} · {{ $proposal->content->series_title }}</span>
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

        <details id="chapters" class="group ui-panel mt-8" open>
            <summary class="cursor-pointer list-none rounded-xl px-5 py-3 hover:bg-subtle [&::-webkit-details-marker]:hidden">
                <h2 class="flex flex-wrap items-center gap-x-3 gap-y-1 text-lg font-semibold">
                    <span>Capitoli letti dalle fonti</span>
                    <span class="ui-badge" title="Capitoli importati">{{ $recentContents->total() }}</span>
                    <span class="ml-auto text-sm font-medium text-accent-ink">
                        <span class="hidden group-open:inline">Compatta</span>
                        <span class="group-open:hidden">Mostra</span>
                    </span>
                </h2>
            </summary>

            <div class="border-t border-line px-5 pb-3">
                <div class="divide-y divide-line">
                    @forelse ($recentContents as $content)
                        <article class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 py-2.5">
                            <div class="w-full min-w-0 sm:w-auto sm:flex-1">
                                <p class="text-sm font-semibold">{{ $content->title }} <span class="font-normal text-muted">· {{ $content->series_title }}</span></p>
                                <p class="mt-1 text-xs text-muted">
                                    {{ $content->source->name }} · {{ $content->released_at?->setTimezone($timezone)->format('d/m/Y') ?? 'data non disponibile' }} · {{ match ($content->eligibility_status) { 'eligible' => 'Idoneo', 'excluded' => 'Escluso', default => 'Da valutare' } }}
                                    @if ($content->source->is_active)
                                        · {{ $content->proposal ? 'Post: '.$content->proposal->status : 'Nessun post proposto' }}
                                    @else
                                        · Fonte disattivata
                                    @endif
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($content->source->is_active)
                                    <a href="{{ $content->public_url }}" target="_blank" rel="noopener noreferrer" class="px-1 text-xs text-accent-ink">Apri il capitolo ↗</a>
                                @endif
                                @if ($content->eligibility_status !== 'excluded')
                                    <form method="POST" action="{{ route('sites.editorial-plan.contents.eligibility', [$site, $content]) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="excluded"><button class="ui-button ui-button-small">Escludi</button></form>
                                @else
                                    <form method="POST" action="{{ route('sites.editorial-plan.contents.eligibility', [$site, $content]) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="eligible"><button class="ui-button ui-tone-success ui-button-small">Ripristina</button></form>
                                @endif
                            </div>
                        </article>
                    @empty
                        <p class="pt-3 text-sm text-muted">Nessun capitolo letto. Usa “Analizza ora” dopo aver verificato una fonte.</p>
                    @endforelse
                </div>
                @if ($recentContents->hasPages())
                    <div class="mt-3 border-t border-line pt-3">{{ $recentContents->links() }}</div>
                @endif
            </div>
        </details>

        <section id="proposals" class="ui-panel mt-8 p-6 sm:p-8" aria-labelledby="proposals-heading">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 id="proposals-heading" class="text-xl font-semibold">Proposte editoriali</h2>
                    <p class="mt-2 text-sm text-muted">Capitoli candidabili senza proposta: {{ $candidateCount }}. L’AI rivaluta il piano a ogni controllo, anche senza nuove uscite, usando i dati GA4 e Search Console disponibili; gli orari restano ipotesi da verificare.</p>
                </div>
                <a href="{{ route('sites.editorial-plan.show', [
                    'site' => $site,
                    'month' => $month->format('Y-m'),
                    'chapters' => $recentContents->currentPage() > 1 ? $recentContents->currentPage() : null,
                    'published' => $includePublished ? null : 1,
                ]) }}#proposals" class="ui-button ui-button-small">
                    {{ $includePublished ? 'Nascondi le pubblicate' : 'Mostra anche le pubblicate' }}
                </a>
            </div>
            @unless ($aiReady) <p class="mt-3 text-sm text-warning-ink">Per generare bozze, <a href="{{ route('settings.ai.index') }}" class="underline">verifica e attiva un servizio AI</a>.</p> @endunless
            @forelse ($proposals as $proposal)
                @php($source = $proposal->content->source)
                @php($isPublished = $proposal->status === 'published')
                @php($canEditImages = $proposal->platform === 'x' && $source->is_active && in_array($proposal->status, \App\Models\EditorialProposal::EDITABLE_STATUSES, true))
                <article id="proposal-{{ $proposal->id }}" class="mt-5 rounded-2xl border border-line-strong p-5 {{ $isPublished ? 'border-l-4 border-l-success-ink bg-surface' : 'bg-subtle' }}">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="font-semibold">{{ $proposal->content->series_title }} — {{ $proposal->content->title }}</h3>
                        @if ($isPublished)
                            <span class="ui-badge ui-tone-success">Pubblicata su X</span>
                        @else
                            <span class="text-sm text-muted">X · {{ match ($proposal->status) { 'approved' => 'Approvata', 'rejected' => 'Scartata', 'deferred' => 'Rimandata dall’AI', 'delivering' => 'Invio in corso', 'delivery_uncertain' => 'Esito da verificare', default => 'Bozza' } }}</span>
                        @endif
                    </div>
                    <p class="mt-3 whitespace-pre-wrap break-words text-sm text-soft">{{ $proposal->post_text }}</p>
                    @if ($proposal->image_attachments && $source->is_active)
                        @include('sites.partials.editorial-image-preview', ['attachments' => $proposal->image_attachments, 'suggested' => false])
                    @elseif ($proposal->image_attachments === null && $canEditImages && $source->verification_status === 'verified')
                        <div data-image-preview="{{ route('sites.editorial-plan.proposals.images.preview', [$site, $proposal]) }}"></div>
                    @endif
                    <p class="mt-3 text-sm text-muted">{{ $proposal->published_at ? 'Pubblicata il' : 'Data suggerita:' }} {{ ($proposal->published_at ?? $proposal->proposed_for)?->setTimezone($timezone)->format('d/m/Y H:i') ?? 'non impostata' }} ({{ $timezone }})</p>
                    @if ($proposal->generated_at || $proposal->manual_override)
                        <p class="mt-1 text-xs text-muted">
                            @if ($proposal->generated_at)
                                Ultima generazione AI <time datetime="{{ $proposal->generated_at->toIso8601String() }}">{{ $proposal->generated_at->setTimezone($timezone)->format('d/m/Y H:i') }}</time>
                            @endif
                            @if ($proposal->manual_override)
                                @if ($proposal->generated_at) · @endif
                                Modificata a mano
                            @endif
                        </p>
                    @endif
                    <p class="mt-2 text-sm text-soft"><span class="font-semibold">Motivazione:</span> {{ $proposal->rationale }}</p>
                    @if ($proposal->x_post_id) <a href="https://x.com/i/web/status/{{ $proposal->x_post_id }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-block text-sm text-accent-ink">Apri il post su X ↗</a> @endif
                    @if ($proposal->delivery_error) <p class="mt-2 text-sm text-danger-ink">{{ $proposal->delivery_error }}</p> @endif
                    @if ($proposal->platform === 'x' && $proposal->status === 'delivery_uncertain' && $proposal->x_post_id === null && $proposal->published_at === null && $source->is_active)
                        <details class="mt-3">
                            <summary class="cursor-pointer text-sm font-medium text-accent-ink">Riapri la proposta</summary>
                            <form method="POST" action="{{ route('sites.editorial-plan.proposals.publish.reopen', [$site, $proposal]) }}" class="mt-3 space-y-3">
                                @csrf @method('PATCH')
                                <input type="hidden" name="delivery_revision" value="{{ $proposal->delivery_revision }}">
                                <p class="text-sm text-muted">Controlla il profilo dell’account X usato per l’invio. Se il post è assente, puoi riaprire la stessa bozza e usare «Pubblica ora».</p>
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="confirmed_absent" value="1" required>
                                    Ho verificato che il post non compare sul profilo X.
                                </label>
                                <button type="submit" class="ui-button ui-button-small">Riapri in bozza</button>
                            </form>
                        </details>
                    @endif
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
                        @if ($canEditImages)
                            <a href="{{ route('sites.editorial-plan.proposals.publish.confirm', [$site, $proposal]) }}" class="ui-button ui-button-primary">Pubblica ora</a>
                            <a href="{{ route('sites.editorial-plan.proposals.images.edit', [$site, $proposal]) }}" class="ui-button">{{ $proposal->image_attachments ? 'Modifica immagini' : 'Scegli immagini' }}</a>
                        @endif
                        @if (! in_array($proposal->status, ['approved', 'published', 'delivery_uncertain', 'delivering'], true) && $proposal->content->eligibility_status === 'eligible')
                            <form method="POST" action="{{ route('sites.editorial-plan.proposals.review', [$site, $proposal]) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="approved"><button class="ui-button ui-tone-success">{{ $proposal->image_attachments ? 'Approva proposta con immagini' : 'Approva solo testo' }}</button></form>
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
            @if ($proposals->hasPages())
                <div class="mt-5">{{ $proposals->links() }}</div>
            @endif
            <p class="mt-5 text-sm text-warning-ink">La pubblicazione automatica richiede consenso della fonte, account X collegato, abilitazione per il sito e ambiente di produzione. In locale nessuna bozza viene inviata a X.</p>
            <p class="mt-4 text-sm text-muted">Fonti configurate: {{ $sourcesCount }}. <a href="{{ route('sites.editorial-plan.sources.index', $site) }}" class="font-medium text-accent-ink">Apri la gestione delle fonti →</a></p>
        </section>
    </main>
@endsection
