@extends('layouts.app')

@section('title', 'Piano editoriale — '.$site->name.' — '.config('app.name', 'Monitoring Hub'))

@section('content')
    @include('partials.navigation')

    <main class="mx-auto max-w-6xl px-6 py-10">
        <a href="{{ route('sites.show', $site) }}" class="text-sm text-sky-400">← Torna alla panoramica</a>

        <div class="mt-4 flex flex-wrap items-start justify-between gap-5">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-sky-400">{{ $site->name }}</p>
                <h1 class="mt-2 text-3xl font-semibold">Piano editoriale</h1>
                <p class="mt-3 max-w-2xl text-slate-400">Consulta il calendario dei post e le proposte editoriali del sito.</p>
            </div>
            <a href="{{ route('sites.editorial-plan.sources.index', $site) }}" class="inline-flex rounded-xl border border-sky-500/50 px-5 py-3 text-sm font-semibold text-sky-300 no-underline hover:bg-slate-900">Gestisci fonti</a>
        </div>

        <nav class="mt-8 flex gap-2 border-b border-slate-800 pb-3 text-sm" aria-label="Sezioni del piano editoriale">
            <a href="{{ route('sites.editorial-plan.show', $site) }}" aria-current="page" class="rounded-lg bg-sky-500/15 px-4 py-2 font-semibold text-sky-300 no-underline">Calendario e proposte</a>
            <a href="{{ route('sites.editorial-plan.sources.index', $site) }}" class="rounded-lg px-4 py-2 text-slate-300 no-underline hover:bg-slate-900">Fonti</a>
        </nav>

        <section class="mt-8 rounded-3xl border border-slate-800 bg-slate-900 p-6 sm:p-8" aria-labelledby="calendar-heading">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 id="calendar-heading" class="text-xl font-semibold">Calendario</h2>
                    <p class="mt-1 text-sm text-slate-400">Post pubblicati e programmati appariranno qui quando la pianificazione sarà disponibile.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('sites.editorial-plan.show', ['site' => $site, 'month' => $month->subMonth()->format('Y-m')]) }}" aria-label="Mese precedente" class="rounded-lg border border-slate-700 px-3 py-2 text-slate-200 no-underline hover:bg-slate-800">←</a>
                    <span class="min-w-32 text-center font-semibold capitalize text-slate-100">{{ $month->locale('it')->isoFormat('MMMM YYYY') }}</span>
                    <a href="{{ route('sites.editorial-plan.show', ['site' => $site, 'month' => $month->addMonth()->format('Y-m')]) }}" aria-label="Mese successivo" class="rounded-lg border border-slate-700 px-3 py-2 text-slate-200 no-underline hover:bg-slate-800">→</a>
                    <a href="{{ route('sites.editorial-plan.show', $site) }}" class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-200 no-underline hover:bg-slate-800">Oggi</a>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="w-full min-w-[42rem] table-fixed border-separate border-spacing-1 text-left" aria-label="Calendario di {{ $month->locale('it')->isoFormat('MMMM YYYY') }}">
                    <thead>
                        <tr>
                            @foreach (['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'] as $weekday)
                                <th scope="col" class="px-3 py-2 text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $weekday }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($weeks as $week)
                            <tr>
                                @foreach ($week as $day)
                                    <td class="h-24 align-top rounded-xl border {{ $day === null ? 'border-transparent bg-slate-950/20' : 'border-slate-800 bg-slate-950/60' }} p-3">
                                        @if ($day !== null)
                                            @php($date = $month->setDay($day)->format('Y-m-d'))
                                            <time datetime="{{ $date }}" class="inline-flex size-8 items-center justify-center rounded-full text-sm {{ $date === $today ? 'bg-sky-500 font-bold text-slate-950' : 'text-slate-200' }}">{{ $day }}</time>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-5 text-sm text-slate-400">Nessun post da mostrare: programmazione e pubblicazione non sono ancora attive.</p>
        </section>

        <section class="mt-8 rounded-3xl border border-slate-800 bg-slate-900 p-6 sm:p-8" aria-labelledby="proposals-heading">
            <h2 id="proposals-heading" class="text-xl font-semibold">Proposte editoriali</h2>
            <div class="mt-5 rounded-2xl border border-dashed border-slate-700 bg-slate-950/50 p-6">
                <p class="font-medium text-slate-200">Nessuna proposta disponibile</p>
                <p class="mt-2 text-sm leading-6 text-slate-400">Monitor non importa ancora le uscite né genera proposte. La verifica di una fonte conferma soltanto che la sua API è raggiungibile; non crea post.</p>
            </div>
            <p class="mt-4 text-sm text-slate-400">Fonti configurate: {{ $sourcesCount }}. <a href="{{ route('sites.editorial-plan.sources.index', $site) }}" class="font-medium text-sky-400">Apri la gestione delle fonti →</a></p>
        </section>
    </main>
@endsection
