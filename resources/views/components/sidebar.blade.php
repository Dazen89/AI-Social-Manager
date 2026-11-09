@php($user = auth()->user())

<aside id="workspace-sidebar" class="ui-sidebar" aria-label="Menu del workspace" data-sidebar>
    <div class="ui-sidebar-top">
        <a href="{{ route('dashboard') }}"><x-brand /></a>
        <button type="button" class="ui-icon-button ui-sidebar-close" aria-label="Chiudi navigazione" data-menu-close>
            <x-icon name="close" />
        </button>
    </div>

    <nav aria-label="Navigazione principale">
        <p class="ui-nav-label">Workspace</p>
        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="dashboard">Dashboard</x-nav-link>
        <x-nav-link :href="route('settings.ai.index')" :active="request()->routeIs('settings.ai.*')" icon="ai">Integrazioni AI</x-nav-link>
        <x-nav-link :href="route('settings.x.edit')" :active="request()->routeIs('settings.x.*', 'x.*')" icon="social">Social</x-nav-link>
        <x-nav-link :href="route('settings.index')" :active="request()->routeIs('settings.index')" icon="settings">Impostazioni</x-nav-link>
    </nav>

    <div class="ui-sidebar-footer">
        <div class="ui-profile">
            <span class="ui-profile-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($user->name, 0, 1)) }}</span>
            <div class="ui-profile-text">
                <span title="{{ $user->name }}">{{ $user->name }}</span>
                <span class="ui-profile-email" title="{{ $user->email }}">{{ $user->email }}</span>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="ui-icon-button" title="Esci" aria-label="Esci"><x-icon name="logout" /></button>
            </form>
        </div>
    </div>
</aside>
