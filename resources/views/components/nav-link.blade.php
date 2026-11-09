@props(['href', 'active' => false, 'icon'])

<a href="{{ $href }}" class="ui-nav-link" @if ($active) aria-current="page" @endif>
    <x-icon :name="$icon" />
    {{ $slot }}
</a>
