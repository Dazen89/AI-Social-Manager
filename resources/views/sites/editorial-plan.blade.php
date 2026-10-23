@extends('layouts.app')

@section('title', 'Fonti editoriali — '.$site->name.' — '.config('app.name', 'Monitoring Hub'))

@section('content')
    @include('partials.navigation')

    <main class="mx-auto max-w-5xl px-6 py-10">
        <a href="{{ route('sites.editorial-plan.show', $site) }}" class="text-sm text-sky-400">← Torna al piano editoriale</a>

        <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-sky-400">{{ $site->name }}</p>
                <h1 class="mt-2 text-3xl font-semibold">Fonti editoriali</h1>
                <p class="mt-3 max-w-2xl text-slate-400">Configura da dove Monitor potrà leggere le nuove uscite. La programmazione e la pubblicazione su X arriveranno nei prossimi passaggi.</p>
            </div>
            <span class="rounded-full border border-amber-500/30 bg-amber-500/10 px-3 py-1 text-sm text-amber-200">Fonte e verifica API</span>
        </div>

        <nav class="mt-8 flex gap-2 border-b border-slate-800 pb-3 text-sm" aria-label="Sezioni del piano editoriale">
            <a href="{{ route('sites.editorial-plan.show', $site) }}" class="rounded-lg px-4 py-2 text-slate-300 no-underline hover:bg-slate-800">Calendario e proposte</a>
            <a href="{{ route('sites.editorial-plan.sources.index', $site) }}" aria-current="page" class="rounded-lg bg-sky-500/15 px-4 py-2 font-semibold text-sky-300 no-underline">Fonti</a>
        </nav>

        @if (session('status'))
            <div class="mt-7 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200" role="status">{{ session('status') }}</div>
        @endif

        @if (session('verification_error'))
            <div class="mt-7 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200" role="alert">{{ session('verification_error') }}</div>
        @endif

        <section class="mt-8 rounded-3xl border border-slate-800 bg-slate-900 p-8" aria-labelledby="sources-heading">
            <h2 id="sources-heading" class="text-xl font-semibold">Fonti dei contenuti</h2>
            <p class="mt-2 text-sm leading-6 text-slate-400">Una fonte è l’installazione da cui arriveranno i contenuti candidati ai post. Per ora è disponibile FoOlSlide-Improved. Puoi verificare l’accesso all’API in sola lettura; nessun contenuto viene importato o pubblicato.</p>

            @forelse ($sources as $source)
                <article class="mt-6 rounded-2xl border border-slate-700 bg-slate-950/50 p-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div class="flex flex-wrap items-center gap-3">
                                <h3 class="font-semibold">{{ $source->name }}</h3>
                                <span class="rounded-full px-2.5 py-1 text-xs {{ $source->is_active ? 'bg-emerald-500/10 text-emerald-300' : 'bg-slate-700 text-slate-300' }}">{{ $source->is_active ? 'Abilitata' : 'Disattivata' }}</span>
                                <span class="rounded-full px-2.5 py-1 text-xs {{ $source->verification_status === 'verified' ? 'bg-emerald-500/10 text-emerald-300' : ($source->verification_status === 'failed' ? 'bg-rose-500/10 text-rose-300' : 'bg-slate-700 text-slate-300') }}">{{ match ($source->verification_status) { 'verified' => 'API verificata', 'failed' => 'Verifica non riuscita', default => 'API da verificare' } }}</span>
                            </div>
                            <p class="mt-2 text-sm text-slate-400">FoOlSlide · <span class="break-all">{{ $source->base_url }}</span></p>
                            @if ($source->verification_message)
                                <p class="mt-2 text-sm {{ $source->verification_status === 'verified' ? 'text-emerald-300' : 'text-rose-300' }}">{{ $source->verification_message }}</p>
                            @endif
                            @if ($source->last_checked_at)
                                <p class="mt-1 text-xs text-slate-500">Ultimo controllo: {{ $source->last_checked_at->format('d/m/Y H:i') }}@if ($source->last_verified_at && $source->verification_status !== 'verified') · Ultimo esito positivo: {{ $source->last_verified_at->format('d/m/Y H:i') }}@endif</p>
                            @endif
                            <p class="mt-2 text-xs text-amber-200">Sincronizzazione non ancora disponibile. Nessun contenuto è stato importato.</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if ($source->is_active)
                                <form method="POST" action="{{ route('sites.editorial-plan.sources.verify', [$site, $source]) }}">
                                    @csrf
                                    <button type="submit" class="rounded-xl border border-sky-500/50 bg-transparent px-4 py-2 text-sm font-semibold text-sky-300 hover:bg-slate-800">Verifica API</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('sites.editorial-plan.sources.toggle', [$site, $source]) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="rounded-xl border border-slate-700 bg-transparent px-4 py-2 text-sm text-slate-200 hover:bg-slate-800">{{ $source->is_active ? 'Disattiva' : 'Riattiva' }}</button>
                            </form>
                        </div>
                    </div>
                    <details class="mt-4 text-sm text-slate-400">
                        <summary class="cursor-pointer text-sky-400">Mostra endpoint previsto</summary>
                        <code class="mt-2 block break-all rounded-lg bg-slate-900 p-3 text-xs text-slate-300">{{ $source->apiChaptersUrl() }}</code>
                    </details>
                    <details class="mt-4 text-sm text-slate-400" @if ($errors->any() && old('source_id') == $source->id) open @endif>
                        <summary class="cursor-pointer text-sky-400">Modifica fonte</summary>
                        <form method="POST" action="{{ route('sites.editorial-plan.sources.update', [$site, $source]) }}" class="mt-4 space-y-4">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="provider" value="foolslide">
                            <input type="hidden" name="source_id" value="{{ $source->id }}">
                            <div>
                                <label for="source-name-{{ $source->id }}" class="block font-medium text-slate-200">Nome</label>
                                <input id="source-name-{{ $source->id }}" name="name" value="{{ old('source_id') == $source->id ? old('name') : $source->name }}" required maxlength="255" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900 px-4 py-3 text-slate-100">
                                @if (old('source_id') == $source->id) @error('name') <p class="mt-2 text-rose-300">{{ $message }}</p> @enderror @endif
                            </div>
                            <div>
                                <label for="source-url-{{ $source->id }}" class="block font-medium text-slate-200">URL base</label>
                                <input id="source-url-{{ $source->id }}" name="base_url" type="url" value="{{ old('source_id') == $source->id ? old('base_url') : $source->base_url }}" required maxlength="2048" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900 px-4 py-3 text-slate-100">
                                @if (old('source_id') == $source->id) @error('base_url') <p class="mt-2 text-rose-300">{{ $message }}</p> @enderror @endif
                            </div>
                            <button type="submit" class="rounded-xl border border-sky-500/50 bg-transparent px-4 py-2 font-semibold text-sky-300 hover:bg-slate-800">Salva fonte</button>
                        </form>
                    </details>
                </article>
            @empty
                <p class="mt-6 rounded-2xl border border-dashed border-slate-700 p-6 text-sm text-slate-400">Nessuna fonte aggiunta. Compila il modulo qui sotto per iniziare.</p>
            @endforelse
        </section>

        <section class="mt-8 rounded-3xl border border-slate-800 bg-slate-900 p-8" aria-labelledby="add-source-heading">
            <h2 id="add-source-heading" class="text-xl font-semibold">Aggiungi una fonte</h2>
            <p class="mt-2 text-sm text-slate-400">Indica l’indirizzo pubblico HTTPS della cartella in cui è installato FoOlSlide. Se è nella radice del sito, usa l’URL principale; se è in una sottocartella, includila.</p>
            @if (config('app.env') === 'local')
                <p class="mt-2 text-sm text-sky-300">Per provare l’installazione Docker locale, puoi aggiungere <code>http://localhost:8080</code> anche a questo sito, indipendentemente dal suo dominio e dalla sua porta. Questa eccezione vale solo in locale: prima di usare la fonte in produzione servirà un URL HTTPS pubblico.</p>
            @endif

            <form method="POST" action="{{ route('sites.editorial-plan.sources.store', $site) }}" class="mt-6 space-y-5">
                @csrf
                <div>
                    <label for="provider" class="block text-sm font-medium text-slate-200">Tipo di fonte</label>
                    <select id="provider" name="provider" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-slate-100 sm:max-w-md">
                        <option value="foolslide" @selected(old('provider', 'foolslide') === 'foolslide')>FoOlSlide-Improved</option>
                    </select>
                    @error('provider') <p class="mt-2 text-sm text-rose-300">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-200">Nome per riconoscere la fonte</label>
                    <input id="name" name="name" value="{{ old('source_id') ? 'FoOlSlide' : old('name', 'FoOlSlide') }}" required maxlength="255" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-slate-100 sm:max-w-md">
                    @unless (old('source_id')) @error('name') <p class="mt-2 text-sm text-rose-300">{{ $message }}</p> @enderror @endunless
                </div>
                <div>
                    <label for="base_url" class="block text-sm font-medium text-slate-200">URL base di FoOlSlide</label>
                    <input id="base_url" name="base_url" type="url" inputmode="url" value="{{ old('source_id') ? $site->base_url : old('base_url', $site->base_url) }}" required maxlength="2048" placeholder="https://example.org/slide" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-slate-100">
                    <p class="mt-2 text-sm text-slate-400">Le fonti pubbliche devono appartenere al dominio {{ $site->host }} e usare HTTPS sulla porta standard. Solo in locale puoi usare HTTP su localhost con una porta non privilegiata diversa (ad esempio 8080). Non inserire l’URL di un fumetto, un capitolo o dell’endpoint API.</p>
                    @unless (old('source_id')) @error('base_url') <p class="mt-2 text-sm text-rose-300">{{ $message }}</p> @enderror @endunless
                </div>
                <button type="submit" class="rounded-xl bg-sky-500 px-5 py-3 font-semibold text-slate-950 hover:bg-sky-400">Aggiungi fonte</button>
            </form>
        </section>

        <section class="mt-8 rounded-3xl border border-slate-800 bg-slate-900 p-8" aria-labelledby="guide-heading">
            <h2 id="guide-heading" class="text-xl font-semibold">Come funzionerà il collegamento</h2>
            <ol class="mt-4 list-inside list-decimal space-y-3 text-sm leading-6 text-slate-300">
                <li>FoOlSlide-Improved espone un’API pubblica di sola lettura per elencare i capitoli, con formato JSON, pagine e ordinamento. Sono disponibili anche RSS e Atom.</li>
                <li>Nel prossimo incremento Monitor leggerà solo le uscite ammesse, salverà gli identificativi e gli URL canonici ed eviterà i duplicati.</li>
                <li>Una regola di idoneità e un’approvazione esplicita precederanno qualsiasi proposta o pubblicazione su X. Nessun post viene generato da questa pagina.</li>
            </ol>
            <p class="mt-4 text-sm text-amber-200">Attenzione: l’endpoint dei capitoli non esclude esplicitamente quelli nascosti. La futura sincronizzazione dovrà applicare un filtro di visibilità e le regole editoriali prima di usare i dati.</p>
            <a href="https://github.com/fcibecchini/FoOlSlide-Improved/blob/master/application/controllers/api/reader.php" target="_blank" rel="noopener noreferrer" class="mt-4 inline-flex text-sm text-sky-400 underline underline-offset-4">Vedi il controller API nel repository FoOlSlide ↗</a>
        </section>
    </main>
@endsection
