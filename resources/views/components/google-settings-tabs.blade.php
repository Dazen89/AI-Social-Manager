@props(['site'])

<nav class="ui-tabs mb-6" aria-label="Impostazioni Google">
    <a href="{{ route('google-analytics.settings.edit', $site) }}" @if (request()->routeIs('google-analytics.settings.edit')) aria-current="page" @endif>Google Analytics 4</a>
    <a href="{{ route('google-search-console.settings.edit', $site) }}" @if (request()->routeIs('google-search-console.settings.edit')) aria-current="page" @endif>Google Search Console</a>
</nav>
