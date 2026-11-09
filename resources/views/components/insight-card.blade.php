@props(['finding'])

@php
    [$label, $tone, $icon] = match ($finding['type']) {
        'risk' => ['Criticità', 'danger', 'warning'],
        'opportunity' => ['Opportunità', 'success', 'opportunity'],
        default => ['Osservazione', 'accent', 'info'],
    };
    $priority = match ($finding['priority']) {
        'high' => 'Priorità alta',
        'medium' => 'Priorità media',
        default => 'Priorità bassa',
    };
@endphp

<article class="ui-panel p-5">
    <div class="flex flex-wrap items-center gap-2 text-xs font-semibold">
        <span class="ui-badge ui-tone-{{ $tone }}"><x-icon :name="$icon" /> {{ $label }}</span>
        <span class="text-muted">{{ $priority }} · confidenza {{ $finding['confidence'] }}</span>
    </div>
    <h3 class="mt-3 text-base font-semibold">{{ $finding['title'] }}</h3>
    <p class="mt-2 text-sm leading-6 text-soft">{{ $finding['explanation'] }}</p>
    <ul class="mt-3 list-disc space-y-1 pl-4 text-sm text-muted">
        @foreach ($finding['evidence'] as $evidence)
            <li>{{ $evidence }}</li>
        @endforeach
    </ul>
    <div class="ui-insight-action mt-4 text-sm leading-6">
        <p class="font-semibold text-accent-ink">Raccomandazione</p>
        <p>{{ $finding['recommended_action'] }}</p>
    </div>
</article>
