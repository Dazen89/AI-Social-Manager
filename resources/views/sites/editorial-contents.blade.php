@extends('layouts.app')

@section('title', 'Uscite editoriali — '.$site->name.' — '.config('app.name', 'Monitoring Hub'))

@section('content')
    @include('partials.navigation')

    <main class="mx-auto max-w-6xl px-6 py-10">
        <a href="{{ route('sites.editorial-plan.show', $site) }}" class="text-sm text-sky-400">← Torna al piano editoriale</a>
        <h1 class="mt-4 text-3xl font-semibold">Uscite da esaminare</h1>
        <p class="mt-3 text-slate-400">L’importazione non autorizza l’uso promozionale. Conferma solo le uscite che puoi promuovere su X, nel rispetto dei diritti e delle regole sui contenuti.</p>

        <nav class="mt-8 flex gap-2 border-b border-slate-800 pb-3 text-sm" aria-label="Sezioni del piano editoriale">
            <a href="{{ route('sites.editorial-plan.show', $site) }}" class="rounded-lg px-4 py-2 text-slate-300 no-underline hover:bg-slate-900">Calendario e proposte</a>
            <a href="{{ route('sites.editorial-plan.contents.index', $site) }}" aria-current="page" class="rounded-lg bg-sky-500/15 px-4 py-2 font-semibold text-sky-300 no-underline">Uscite</a>
            <a href="{{ route('sites.editorial-plan.sources.index', $site) }}" class="rounded-lg px-4 py-2 text-slate-300 no-underline hover:bg-slate-900">Fonti</a>
        </nav>

        @if (session('status')) <p class="mt-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-emerald-200" role="status">{{ session('status') }}</p> @endif
        @if ($errors->any()) <p class="mt-6 rounded-xl border border-rose-500/30 bg-rose-500/10 p-4 text-rose-200" role="alert">{{ $errors->first() }}</p> @endif

        <section class="mt-8 space-y-4" aria-label="Uscite importate">
            @forelse ($contents as $content)
                <article class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 class="font-semibold text-slate-100">{{ $content->series_title }} — {{ $content->title }}</h2>
                            <p class="mt-2 text-sm text-slate-400">{{ $content->source->name }} · {{ $content->released_at?->format('d/m/Y') ?? 'Data non disponibile' }} · {{ match ($content->eligibility_status) { 'eligible' => 'Idonea', 'excluded' => 'Esclusa', default => 'Da esaminare' } }}@if ($content->proposal) · Proposta {{ match ($content->proposal->status) { 'approved' => 'approvata', 'rejected' => 'scartata', default => 'in bozza' } }}@endif</p>
                            <a href="{{ $content->public_url }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-block break-all text-sm text-sky-400">Apri la pagina originale ↗</a>
                        </div>
                        <div class="flex gap-2">
                            @if ($content->eligibility_status !== 'eligible')
                                <form method="POST" action="{{ route('sites.editorial-plan.contents.eligibility', [$site, $content]) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="eligible">
                                    <button class="rounded-xl border border-emerald-500/50 px-4 py-2 text-sm text-emerald-300 hover:bg-slate-800">Conferma idoneità</button>
                                </form>
                            @endif
                            @if ($content->eligibility_status !== 'excluded')
                                <form method="POST" action="{{ route('sites.editorial-plan.contents.eligibility', [$site, $content]) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="excluded">
                                    <button class="rounded-xl border border-slate-700 px-4 py-2 text-sm text-slate-300 hover:bg-slate-800">Escludi</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <p class="rounded-2xl border border-dashed border-slate-700 p-6 text-slate-400">Nessuna uscita importata. <a href="{{ route('sites.editorial-plan.sources.index', $site) }}" class="text-sky-400">Verifica una fonte e importa le uscite.</a></p>
            @endforelse
        </section>
        <div class="mt-8">{{ $contents->links() }}</div>
    </main>
@endsection
