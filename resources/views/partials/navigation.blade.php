<header class="border-b border-slate-800 bg-slate-900/80">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-6 px-6 py-4">
        <div class="flex items-center gap-8">
            <div>
                <p class="font-semibold text-sky-400">Monitoring Hub</p>
                <p class="text-xs text-slate-400">Area riservata</p>
            </div>

            <nav class="hidden items-center gap-2 sm:flex" aria-label="Navigazione principale">
                <a href="{{ route('dashboard') }}" class="rounded-lg px-3 py-2 text-sm no-underline {{ request()->routeIs('dashboard') ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-white' }}">Dashboard</a>
                <a href="{{ route('sites.index') }}" class="rounded-lg px-3 py-2 text-sm no-underline {{ request()->routeIs('sites.*') ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-white' }}">Siti</a>
                <a href="{{ route('settings.ai.index') }}" class="rounded-lg px-3 py-2 text-sm no-underline {{ request()->routeIs('settings.ai.*') ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-white' }}">Integrazioni AI</a>
                <a href="{{ route('settings.google-analytics.edit') }}" class="rounded-lg px-3 py-2 text-sm no-underline {{ request()->routeIs('settings.google-analytics.*') ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-white' }}">Impostazioni</a>
            </nav>
        </div>

        <div class="flex items-center gap-4">
            <div class="hidden text-right md:block">
                <p class="text-sm font-medium text-slate-100">{{ auth()->user()->name }}</p>
                <p class="text-xs text-slate-400">{{ auth()->user()->email }}</p>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded-lg border border-slate-700 bg-transparent px-4 py-2 text-sm font-medium text-slate-200 transition hover:border-slate-500 hover:bg-slate-800">Esci</button>
            </form>
        </div>
    </div>
</header>
