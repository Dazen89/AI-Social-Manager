@extends('layouts.app')

@section('title', 'Dashboard — '.config('app.name', 'AI Social Manager'))

@section('content')
    <header class="border-b border-slate-800 bg-slate-900/80">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-6 px-6 py-4">
            <div>
                <p class="font-semibold text-sky-400">AI Social Manager</p>
                <p class="text-sm text-slate-400">Area riservata</p>
            </div>

            <div class="flex items-center gap-4">
                <div class="hidden text-right sm:block">
                    <p class="text-sm font-medium">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-slate-400">{{ auth()->user()->email }}</p>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-medium transition hover:border-slate-500 hover:bg-slate-800">
                        Esci
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-6 py-12">
        <section class="rounded-3xl border border-slate-800 bg-slate-900 p-8">
            <span class="inline-flex rounded-full bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-300">Accesso protetto attivo</span>
            <h1 class="mt-5 text-3xl font-semibold tracking-tight">Benvenuto, {{ auth()->user()->name }}.</h1>
            <p class="mt-3 max-w-2xl leading-7 text-slate-400">
                Questa è la base privata del servizio. Catalogo contenuti, workflow editoriale, pubblicazione e metriche verranno aggiunti nei prossimi incrementi.
            </p>
        </section>
    </main>
@endsection
