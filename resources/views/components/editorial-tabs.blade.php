@props(['site', 'showContents' => false])

<nav class="ui-tabs my-5" aria-label="Sezioni del piano editoriale">
    <a href="{{ route('sites.editorial-plan.show', $site) }}" @if (request()->routeIs('sites.editorial-plan.show')) aria-current="page" @endif>Calendario e proposte</a>
    @if ($showContents)
        <a href="{{ route('sites.editorial-plan.contents.index', $site) }}" @if (request()->routeIs('sites.editorial-plan.contents.index')) aria-current="page" @endif>Uscite</a>
    @endif
    <a href="{{ route('sites.editorial-plan.sources.index', $site) }}" @if (request()->routeIs('sites.editorial-plan.sources.index')) aria-current="page" @endif>Fonti</a>
</nav>
