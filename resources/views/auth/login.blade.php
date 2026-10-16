@extends('layouts.app')

@section('title', 'Accedi — '.config('app.name', 'AI Social Manager'))

@section('content')
    <main class="flex min-h-screen items-center justify-center px-6 py-12">
        <section class="w-full max-w-md rounded-3xl border border-slate-800 bg-slate-900/80 p-8 shadow-2xl shadow-sky-950/30 backdrop-blur">
            <div class="mb-8">
                <p class="mb-3 text-sm font-semibold uppercase tracking-[0.2em] text-sky-400">AI Social Manager</p>
                <h1 class="text-3xl font-semibold tracking-tight">Accedi al servizio</h1>
                <p class="mt-3 text-sm leading-6 text-slate-400">L’area è riservata agli amministratori autorizzati.</p>
            </div>

            <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="mb-2 block text-sm font-medium text-slate-300">Email</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="username"
                    >
                    @error('email')
                        <p class="mt-2 text-sm text-rose-400" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="mb-2 block text-sm font-medium text-slate-300">Password</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        autocomplete="current-password"
                    >
                    @error('password')
                        <p class="mt-2 text-sm text-rose-400" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <label class="flex items-center gap-3 text-sm text-slate-400">
                    <input name="remember" type="checkbox" class="size-4 rounded border-slate-700 bg-slate-900 text-sky-500 focus:ring-sky-500">
                    Ricordami su questo dispositivo
                </label>

                <button type="submit" class="w-full rounded-xl bg-sky-500 px-4 py-3 font-semibold text-slate-950 transition hover:bg-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-300 focus:ring-offset-2 focus:ring-offset-slate-900">
                    Accedi
                </button>
            </form>
        </section>
    </main>
@endsection
