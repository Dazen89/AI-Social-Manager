@props(['name'])

<svg {{ $attributes->class('ui-icon') }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('brand') <path d="M4 19V5m0 14h16M8 15l4-4 3 2 5-6m-5 0h5v5"/> @break
        @case('dashboard') <rect x="3" y="3" width="18" height="18" rx="3"/><path d="M3 9h18M10 9v12"/> @break
        @case('globe') <circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="4" ry="9"/><path d="M3 12h18"/> @break
        @case('ai') <path d="M12 3l2.2 5.8L20 11l-5.8 2.2L12 19l-2.2-5.8L4 11l5.8-2.2L12 3Zm7 12 1 2.5 2.5 1L20 19.5 19 22l-1-2.5-2.5-1 2.5-1L19 15Z"/> @break
        @case('social') <path d="M3 5h18v12h-9l-4 4v-4H3zM7 9h10m-10 4h6"/> @break
        @case('users') <circle cx="9" cy="8" r="3"/><path d="M3 20v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2m1-15a3 3 0 0 1 0 6m5 9v-2a4 4 0 0 0-3-3.9"/> @break
        @case('settings') <path d="M4 6h16M4 12h16M4 18h16"/><path d="M8 3v6m8 0v6m-5 0v6" stroke-width="2.5"/> @break
        @case('logout') <path d="M10 4H4v16h6m4-12 4 4-4 4m-6-4h12"/> @break
        @case('menu') <path d="M4 6h16M4 12h16M4 18h16"/> @break
        @case('close') <path d="m6 6 12 12M18 6 6 18"/> @break
        @case('plus') <path d="M12 5v14M5 12h14"/> @break
        @case('arrow') <path d="M4 12h15m-6-6 6 6-6 6"/> @break
        @case('chart') <path d="M4 19V5m0 14h16M8 15v-3m4 3V8m4 7V5"/> @break
        @case('search') <circle cx="10.5" cy="10.5" r="5.5"/><path d="m14.5 14.5 5 5"/> @break
        @case('x') <path d="M4 4h4l12 16h-4L4 4Zm16 0-6.5 7.5m-3 3.5L4 20"/> @break
        @case('calendar') <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 10h18"/> @break
        @case('warning') <path d="M12 3 2.8 20h18.4L12 3Zm0 6v4m0 3v.1"/> @break
        @case('info') <circle cx="12" cy="12" r="9"/><path d="M12 11v5m0-8v.1"/> @break
        @case('opportunity') <path d="m4 16 5-5 4 3 7-8m-6 0h6v6"/> @break
    @endswitch
</svg>
