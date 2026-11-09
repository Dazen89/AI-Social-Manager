@extends('layouts.workspace')

@section('title', 'Aggiungi sito — '.config('app.name', 'Monitoring Hub'))

@section('page')
    <main id="main-content" class="ui-page ui-page-narrow" tabindex="-1">
        <x-page-header title="Aggiungi un sito" description="Prima registra il sito. Nel passaggio successivo potrai scegliere quali servizi collegare.">
            <x-slot:back><a href="{{ route('sites.index') }}">← Torna ai siti</a></x-slot:back>
        </x-page-header>

        <section class="ui-panel p-5 sm:p-7">
            <form method="POST" action="{{ route('sites.store') }}" class="space-y-8">
                @csrf
                @include('sites.partials.form', ['submitLabel' => 'Salva e continua'])
            </form>
        </section>
    </main>
@endsection
