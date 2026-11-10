@extends('layouts.workspace')

@section('title', 'Pubblica ora su X — '.$site->name)

@section('page')
    <main id="main-content" class="ui-page ui-page-narrow" tabindex="-1">
        <x-page-header title="Pubblica ora su X" :kicker="$proposal->content->series_title" :description="$proposal->content->title">
            <x-slot:back><a href="{{ route('sites.editorial-plan.show', $site) }}#proposal-{{ $proposal->id }}">← Torna alla proposta</a></x-slot:back>
        </x-page-header>

        <section class="ui-panel p-5 sm:p-7" aria-labelledby="preview-heading">
            <h2 id="preview-heading" class="text-xl font-semibold">Anteprima del post</h2>
            @if ($connection)<p class="mt-2 text-sm text-muted">Account X {{ '@'.$connection->username }}</p>@endif
            <p class="mt-4 whitespace-pre-wrap break-words rounded-xl border border-line bg-subtle p-4 text-sm text-ink">{{ $proposal->post_text }}</p>

            @if ($attachments)
                @include('sites.partials.editorial-image-preview', ['source' => $proposal->content->source])
            @elseif (! $problem)
                <p class="mt-3 text-sm text-muted">Il post contiene solo testo.</p>
            @endif
            <a href="{{ route('sites.editorial-plan.proposals.images.edit', [$site, $proposal]) }}" class="mt-4 inline-block text-sm text-accent-ink underline">Cambia selezione immagini</a>

            @if ($problem)
                <p class="mt-4 ui-alert ui-tone-warning" role="status">{{ $problem }}</p>
                @unless ($connection?->canPublish(automatic: false))
                    <a href="{{ route('settings.x.edit') }}" class="mt-3 inline-block text-sm text-accent-ink underline">Apri la pagina Social</a>
                @endunless
            @endif
            @if ($errors->any())
                <p class="mt-4 ui-alert ui-tone-danger" role="alert">{{ $errors->first() }}</p>
            @endif

            @if ($confirmation)
                <form method="POST" action="{{ route('sites.editorial-plan.proposals.publish.store', [$site, $proposal]) }}" class="mt-5 space-y-4">
                    @csrf
                    <input type="hidden" name="confirmation" value="{{ $confirmation }}">
                    @if ($attachments)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="images_confirmed" value="1" required>
                            Confermo di poter usare queste immagini per la promozione su X.
                        </label>
                    @endif
                    <button type="submit" class="ui-button ui-button-primary">Conferma e pubblica su X</button>
                </form>
            @endif
        </section>
    </main>
@endsection
