@extends('layouts.workspace')

@section('title', 'Uscite editoriali — '.$site->name.' — '.config('app.name', 'Monitoring Hub'))

@section('page')
    <main id="main-content" class="ui-page" tabindex="-1">
        <x-page-header title="Uscite da esaminare" :kicker="$site->name" description="L’importazione non autorizza l’uso promozionale. Conferma solo le uscite che puoi promuovere su X, nel rispetto dei diritti e delle regole sui contenuti.">
            <x-slot:back><a href="{{ route('sites.editorial-plan.show', $site) }}">← Torna al piano editoriale</a></x-slot:back>
        </x-page-header>

        <x-editorial-tabs :site="$site" :show-contents="true" />

        @if (session('status')) <p class="mt-6 ui-alert ui-tone-success" role="status">{{ session('status') }}</p> @endif
        @if ($errors->any()) <p class="mt-6 ui-alert ui-tone-danger" role="alert">{{ $errors->first() }}</p> @endif

        <section class="mt-8 space-y-4" aria-label="Uscite importate">
            @forelse ($contents as $content)
                <article class="ui-panel p-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 class="font-semibold text-ink">{{ $content->series_title }} — {{ $content->title }}</h2>
                            <p class="mt-2 text-sm text-muted">{{ $content->source->name }} · {{ $content->released_at?->format('d/m/Y') ?? 'Data non disponibile' }} · {{ match ($content->eligibility_status) { 'eligible' => 'Idonea', 'excluded' => 'Esclusa', default => 'Da esaminare' } }}@if ($content->proposal) · Proposta {{ match ($content->proposal->status) { 'approved' => 'approvata', 'rejected' => 'scartata', default => 'in bozza' } }}@endif</p>
                            <a href="{{ $content->public_url }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-block break-all text-sm text-accent-ink">Apri la pagina originale ↗</a>
                        </div>
                        <div class="flex gap-2">
                            @if ($content->eligibility_status !== 'eligible')
                                <form method="POST" action="{{ route('sites.editorial-plan.contents.eligibility', [$site, $content]) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="eligible">
                                    <button class="ui-button ui-tone-success">Conferma idoneità</button>
                                </form>
                            @endif
                            @if ($content->eligibility_status !== 'excluded')
                                <form method="POST" action="{{ route('sites.editorial-plan.contents.eligibility', [$site, $content]) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="excluded">
                                    <button class="ui-button">Escludi</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <p class="rounded-2xl border border-dashed border-line-strong p-6 text-muted">Nessuna uscita importata. <a href="{{ route('sites.editorial-plan.sources.index', $site) }}" class="text-accent-ink">Verifica una fonte e importa le uscite.</a></p>
            @endforelse
        </section>
        <div class="mt-8">{{ $contents->links() }}</div>
    </main>
@endsection
