@extends('layouts.app')

@section('title', 'Aggiungi sito — '.config('app.name', 'Monitoring Hub'))

@section('content')
    @include('partials.navigation')

    <main class="mx-auto max-w-4xl px-6 py-10">
        <div class="mb-8">
            <a href="{{ route('sites.index') }}" class="text-sm text-sky-400">← Torna ai siti</a>
            <h1 class="mt-4 text-3xl font-semibold">Aggiungi un sito</h1>
            <p class="mt-2 text-slate-400">Prima registra il sito. Nel passaggio successivo potrai scegliere quali servizi collegare.</p>
        </div>

        <section class="rounded-3xl border border-slate-800 bg-slate-900 p-8">
            <form method="POST" action="{{ route('sites.store') }}" class="space-y-8">
                @csrf
                @include('sites.partials.form', ['submitLabel' => 'Salva e continua'])
            </form>
        </section>
    </main>
@endsection
