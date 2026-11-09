@props(['label', 'icon' => null])

<div {{ $attributes->class('ui-panel ui-kpi') }}>
    <dt>
        @if ($icon)
            <x-icon :name="$icon" />
        @endif
        {{ $label }}
    </dt>
    <dd>{{ $slot }}</dd>
    @isset($note)
        <dd class="ui-kpi-note">{{ $note }}</dd>
    @endisset
    @isset($status)
        <dd class="ui-kpi-note">{{ $status }}</dd>
    @endisset
</div>
