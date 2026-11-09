@props(['site', 'days', 'label' => 'Scegli intervallo'])

<nav class="ui-tabs" aria-label="{{ $label }}">
    @foreach ([7, 28, 90] as $range)
        <a href="{{ route('sites.show', ['site' => $site, 'range' => $range]) }}" @if ($days === $range) aria-current="page" @endif>{{ $range }} giorni</a>
    @endforeach
</nav>
