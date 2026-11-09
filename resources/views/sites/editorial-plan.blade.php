@extends('layouts.workspace')

@section('title', 'Fonti editoriali — '.$site->name.' — '.config('app.name', 'Monitoring Hub'))

@section('page')
    <main id="main-content" class="ui-page ui-page-narrow" tabindex="-1">
        <x-page-header title="Fonti editoriali" :kicker="$site->name" description="Configura da dove Monitor legge i capitoli e autorizza esplicitamente le fonti che possono essere promosse automaticamente su X.">
            <x-slot:back><a href="{{ route('sites.editorial-plan.show', $site) }}">← Torna al piano editoriale</a></x-slot:back>
            <x-slot:status><span class="ui-badge ui-tone-warning">Fonte e verifica API</span></x-slot:status>
        </x-page-header>

        <x-editorial-tabs :site="$site" />

        @if (session('status'))
            <div class="mt-7 ui-alert ui-tone-success" role="status">{{ session('status') }}</div>
        @endif

        @if (session('verification_error'))
            <div class="mt-7 ui-alert ui-tone-danger" role="alert">{{ session('verification_error') }}</div>
        @endif

        <section class="ui-panel mt-8 p-8" aria-labelledby="sources-heading">
            <h2 id="sources-heading" class="text-xl font-semibold">Fonti dei contenuti</h2>
            <p class="mt-2 text-sm leading-6 text-muted">Verifica l’API FoOlSlide-Improved, poi imposta la frequenza nel <a href="{{ route('sites.editorial-plan.show', $site) }}" class="text-accent-ink">piano editoriale</a>. L’autorizzazione della fonte attesta che i suoi capitoli possono essere promossi; da sola non abilita la pubblicazione su X.</p>

            @forelse ($sources as $source)
                <article class="mt-6 rounded-2xl border border-line-strong bg-subtle p-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div class="flex flex-wrap items-center gap-3">
                                <h3 class="font-semibold">{{ $source->name }}</h3>
                                <span class="rounded-full px-2.5 py-1 text-xs {{ $source->is_active ? 'bg-success-soft text-success-ink' : 'bg-subtle text-soft' }}">{{ $source->is_active ? 'Abilitata' : 'Disattivata' }}</span>
                                <span class="rounded-full px-2.5 py-1 text-xs {{ $source->verification_status === 'verified' ? 'bg-success-soft text-success-ink' : ($source->verification_status === 'failed' ? 'bg-danger-soft text-danger-ink' : 'bg-subtle text-soft') }}">{{ match ($source->verification_status) { 'verified' => 'API verificata', 'failed' => 'Verifica non riuscita', default => 'API da verificare' } }}</span>
                            </div>
                            <p class="mt-2 text-sm text-muted">FoOlSlide · <span class="break-all">{{ $source->base_url }}</span></p>
                            @if ($source->verification_message)
                                <p class="mt-2 text-sm {{ $source->verification_status === 'verified' ? 'text-success-ink' : 'text-danger-ink' }}">{{ $source->verification_message }}</p>
                            @endif
                            @if ($source->last_checked_at)
                                <p class="mt-1 text-xs text-muted">Ultimo controllo: {{ $source->last_checked_at->format('d/m/Y H:i') }}@if ($source->last_verified_at && $source->verification_status !== 'verified') · Ultimo esito positivo: {{ $source->last_verified_at->format('d/m/Y H:i') }}@endif</p>
                            @endif
                            <p class="mt-2 text-xs text-muted">Uscite rilevate: {{ $source->contents_count }}. Puoi rivederle o escluderle sotto il calendario dei post.</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if ($source->is_active)
                                <form method="POST" action="{{ route('sites.editorial-plan.sources.verify', [$site, $source]) }}">
                                    @csrf
                                    <button type="submit" class="ui-button">Verifica API</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('sites.editorial-plan.sources.toggle', [$site, $source]) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="ui-button">{{ $source->is_active ? 'Disattiva' : 'Riattiva' }}</button>
                            </form>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('sites.editorial-plan.sources.promotion-authorization', [$site, $source]) }}" class="mt-4 rounded-xl border border-warning-ink/22 bg-warning-soft p-4 text-sm text-soft">
                        @csrf @method('PATCH')
                        @if ($source->promotion_authorized_at)
                            <input type="hidden" name="authorized" value="0">
                            <p>Promozione automatica autorizzata il {{ $source->promotion_authorized_at->format('d/m/Y') }}. I capitoli esclusi restano esclusi.</p>
                            <button class="mt-2 ui-button ui-tone-warning ui-button-small">Revoca autorizzazione</button>
                        @else
                            <input type="hidden" name="authorized" value="1">
                            <p>Confermo di avere i diritti e l’autorizzazione per promuovere automaticamente su X i capitoli pubblici di questa fonte. Potrò escludere singoli capitoli dal calendario.</p>
                            <button class="mt-2 ui-button ui-tone-warning ui-button-small">Autorizza promozione</button>
                        @endif
                    </form>
                    <details class="mt-4 text-sm text-muted">
                        <summary class="cursor-pointer text-accent-ink">Mostra endpoint previsto</summary>
                        <code class="mt-2 block break-all rounded-lg bg-surface p-3 text-xs text-soft">{{ $source->apiChaptersUrl() }}</code>
                    </details>
                    <details class="mt-4 text-sm text-muted" @if ($errors->any() && old('source_id') == $source->id) open @endif>
                        <summary class="cursor-pointer text-accent-ink">Modifica fonte</summary>
                        <form method="POST" action="{{ route('sites.editorial-plan.sources.update', [$site, $source]) }}" class="mt-4 space-y-4">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="provider" value="foolslide">
                            <input type="hidden" name="source_id" value="{{ $source->id }}">
                            <div>
                                <label for="source-name-{{ $source->id }}" class="block font-medium text-soft">Nome</label>
                                <input id="source-name-{{ $source->id }}" name="name" value="{{ old('source_id') == $source->id ? old('name') : $source->name }}" required maxlength="255" class="mt-2 w-full rounded-xl border border-line-strong bg-surface px-4 py-3 text-ink">
                                @if (old('source_id') == $source->id) @error('name') <p class="mt-2 text-danger-ink">{{ $message }}</p> @enderror @endif
                            </div>
                            <div>
                                <label for="source-url-{{ $source->id }}" class="block font-medium text-soft">URL base</label>
                                <input id="source-url-{{ $source->id }}" name="base_url" type="url" value="{{ old('source_id') == $source->id ? old('base_url') : $source->base_url }}" required maxlength="2048" class="mt-2 w-full rounded-xl border border-line-strong bg-surface px-4 py-3 text-ink">
                                @if (old('source_id') == $source->id) @error('base_url') <p class="mt-2 text-danger-ink">{{ $message }}</p> @enderror @endif
                            </div>
                            <button type="submit" class="ui-button">Salva fonte</button>
                        </form>
                    </details>
                </article>
            @empty
                <p class="mt-6 rounded-2xl border border-dashed border-line-strong p-6 text-sm text-muted">Nessuna fonte aggiunta. Compila il modulo qui sotto per iniziare.</p>
            @endforelse
        </section>

        <section class="ui-panel mt-8 p-8" aria-labelledby="add-source-heading">
            <h2 id="add-source-heading" class="text-xl font-semibold">Aggiungi una fonte</h2>
            <p class="mt-2 text-sm text-muted">Indica l’indirizzo pubblico HTTPS della cartella in cui è installato FoOlSlide. Se è nella radice del sito, usa l’URL principale; se è in una sottocartella, includila.</p>
            @if (config('app.env') === 'local')
                <p class="mt-2 text-sm text-accent-ink">Per provare l’installazione Docker locale, puoi aggiungere <code>http://localhost:8080</code> anche a questo sito, indipendentemente dal suo dominio e dalla sua porta. Questa eccezione vale solo in locale: prima di usare la fonte in produzione servirà un URL HTTPS pubblico.</p>
            @endif

            <form method="POST" action="{{ route('sites.editorial-plan.sources.store', $site) }}" class="mt-6 space-y-5">
                @csrf
                <div>
                    <label for="provider" class="block text-sm font-medium text-soft">Tipo di fonte</label>
                    <select id="provider" name="provider" class="mt-2 w-full rounded-xl border border-line-strong bg-subtle px-4 py-3 text-ink sm:max-w-md">
                        <option value="foolslide" @selected(old('provider', 'foolslide') === 'foolslide')>FoOlSlide-Improved</option>
                    </select>
                    @error('provider') <p class="mt-2 text-sm text-danger-ink">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="name" class="block text-sm font-medium text-soft">Nome per riconoscere la fonte</label>
                    <input id="name" name="name" value="{{ old('source_id') ? 'FoOlSlide' : old('name', 'FoOlSlide') }}" required maxlength="255" class="mt-2 w-full rounded-xl border border-line-strong bg-subtle px-4 py-3 text-ink sm:max-w-md">
                    @unless (old('source_id')) @error('name') <p class="mt-2 text-sm text-danger-ink">{{ $message }}</p> @enderror @endunless
                </div>
                <div>
                    <label for="base_url" class="block text-sm font-medium text-soft">URL base di FoOlSlide</label>
                    <input id="base_url" name="base_url" type="url" inputmode="url" value="{{ old('source_id') ? $site->base_url : old('base_url', $site->base_url) }}" required maxlength="2048" placeholder="https://example.org/slide" class="mt-2 w-full rounded-xl border border-line-strong bg-subtle px-4 py-3 text-ink">
                    <p class="mt-2 text-sm text-muted">Le fonti pubbliche devono appartenere al dominio {{ $site->host }} e usare HTTPS sulla porta standard. Solo in locale puoi usare HTTP su localhost con una porta non privilegiata diversa (ad esempio 8080). Non inserire l’URL di un fumetto, un capitolo o dell’endpoint API.</p>
                    @unless (old('source_id')) @error('base_url') <p class="mt-2 text-sm text-danger-ink">{{ $message }}</p> @enderror @endunless
                </div>
                <button type="submit" class="ui-button ui-button-primary">Aggiungi fonte</button>
            </form>
        </section>

        <section class="ui-panel mt-8 p-8" aria-labelledby="guide-heading">
            <h2 id="guide-heading" class="text-xl font-semibold">Come funziona il collegamento</h2>
            <ol class="mt-4 list-inside list-decimal space-y-3 text-sm leading-6 text-soft">
                <li>FoOlSlide-Improved espone un’API pubblica di sola lettura per elencare i capitoli, con formato JSON, pagine e ordinamento. Sono disponibili anche RSS e Atom.</li>
                <li>Quando il piano automatico è attivo, Monitor legge a intervalli configurati le due pagine più recenti, salva identificativi e URL canonici ed evita i duplicati.</li>
                <li>Il calendario mostra i post X con le motivazioni dell’AI. Se autorizzi la fonte e attivi la pubblicazione nel sito, Monitor può approvarli e inviarli automaticamente; puoi sempre escludere capitoli o modificare il piano prima dell’invio.</li>
            </ol>
            <p class="mt-4 text-sm text-warning-ink">Monitor scarta le voci marcate come nascoste e gli URL esterni alla fonte. Autorizza la promozione automatica soltanto per contenuti di cui hai verificato diritti e idoneità.</p>
            <a href="https://github.com/fcibecchini/FoOlSlide-Improved/blob/master/application/controllers/api/reader.php" target="_blank" rel="noopener noreferrer" class="mt-4 inline-flex text-sm text-accent-ink underline underline-offset-4">Vedi il controller API nel repository FoOlSlide ↗</a>
        </section>
    </main>
@endsection
