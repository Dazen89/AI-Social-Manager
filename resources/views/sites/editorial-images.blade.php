@extends('layouts.workspace')

@section('title', 'Immagini del post — '.$site->name)

@section('page')
    <main id="main-content" class="ui-page ui-page-narrow" tabindex="-1">
        <x-page-header title="Immagini del post" :kicker="$proposal->content->series_title" :description="$proposal->content->title">
            <x-slot:back><a href="{{ route('sites.editorial-plan.show', $site) }}#proposal-{{ $proposal->id }}">← Torna alla proposta</a></x-slot:back>
        </x-page-header>

        <section class="ui-panel p-5 sm:p-7">
            @if ($imageError)
                <p class="ui-alert ui-tone-danger" role="alert">{{ $imageError }}</p>
            @elseif ($images === [])
                <p class="text-sm text-muted">Questo capitolo non ha immagini disponibili per la selezione.</p>
            @else
                <p class="text-sm text-muted">Monitor propone fino a tre pagine casuali. Puoi cambiare la selezione prima di salvare.</p>
            @endif

            @if ($errors->any())
                <p class="mt-4 ui-alert ui-tone-danger" role="alert">{{ $errors->first() }}</p>
            @endif

            @if ($images !== [] || $proposal->image_attachments)
                <form method="POST" action="{{ route('sites.editorial-plan.proposals.images.update', [$site, $proposal]) }}" class="mt-5 space-y-5">
                    @csrf
                    @method('PATCH')
                    @if ($images !== [])
                        <fieldset data-image-limit="{{ $maxImages }}">
                            <legend class="mb-1 font-semibold">Seleziona fino a {{ $maxImages }} pagine</legend>
                            <p data-image-count role="status" class="mb-3 text-sm text-muted"></p>
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                                @foreach ($images as $image)
                                    <div class="rounded-xl border border-line p-2 has-checked:border-accent has-checked:bg-accent-soft">
                                        <label class="flex cursor-pointer flex-col gap-2 has-disabled:cursor-not-allowed has-disabled:opacity-60">
                                            <img src="{{ $image['thumbnail'] }}" alt="" loading="lazy" referrerpolicy="no-referrer" class="aspect-[2/3] w-full rounded-lg bg-subtle object-contain">
                                            <span class="flex items-center gap-2 text-sm">
                                                <input type="checkbox" name="image_ids[]" value="{{ $image['id'] }}" @checked(in_array($image['id'], (array) $selectedIds))>
                                                Pagina {{ $image['number'] }}
                                            </span>
                                        </label>
                                        <a href="{{ $image['url'] }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-block text-xs">Apri pagina intera ↗</a>
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="images_confirmed" value="1" @checked(old('images_confirmed'))>
                            Confermo di poter usare le pagine selezionate per la promozione su X.
                        </label>
                    @endif
                    <button type="submit" class="ui-button ui-button-primary">{{ $images === [] ? 'Rimuovi allegati' : 'Salva selezione' }}</button>
                </form>
            @endif
        </section>
    </main>
@endsection
