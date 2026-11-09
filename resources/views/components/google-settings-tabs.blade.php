@props(['returnSite' => null])

@php($parameters = $returnSite ? ['site' => $returnSite->id] : [])

<nav class="ui-tabs mb-6" aria-label="Impostazioni Google">
    <a href="{{ route('settings.google-analytics.edit', $parameters) }}" @if (request()->routeIs('settings.google-analytics.*')) aria-current="page" @endif>Google Analytics 4</a>
    <a href="{{ route('settings.google-search-console.edit', $parameters) }}" @if (request()->routeIs('settings.google-search-console.*')) aria-current="page" @endif>Google Search Console</a>
</nav>
