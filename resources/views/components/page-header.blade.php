@props(['title', 'kicker' => null, 'description' => null])

<header {{ $attributes->class('ui-page-header') }}>
    <div>
        @if (isset($back) && $back->isNotEmpty())
            <div class="mb-3 text-sm">{{ $back }}</div>
        @endif
        @if ($kicker)
            <p class="ui-kicker">{{ $kicker }}</p>
        @endif
        <div class="ui-actions">
            <h1 class="ui-page-title">{{ $title }}</h1>
            {{ $status ?? '' }}
        </div>
        @if ($description)
            <p class="ui-page-description">{{ $description }}</p>
        @endif
        {{ $slot }}
    </div>
    @isset($actions)
        <div class="ui-actions">{{ $actions }}</div>
    @endisset
</header>
