@extends('layouts.app')

@section('title', 'Scegli proprietà Google Analytics — '.config('app.name', 'Monitoring Hub'))

@section('content')
    @include('partials.navigation')

    <main class="mx-auto max-w-4xl px-6 py-10">
        <div class="mb-8">
            <button type="submit" form="cancel-google-analytics" class="border-0 bg-transparent p-0 text-sm font-normal text-sky-400">← Annulla e torna a {{ $site->name }}</button>
            <p class="mt-6 text-sm font-semibold uppercase tracking-[0.2em] text-sky-400">Google Analytics 4</p>
            <h1 class="mt-2 text-3xl font-semibold">Scegli la proprietà da collegare</h1>
            <p class="mt-3 max-w-2xl leading-7 text-slate-400">Sono mostrate soltanto le proprietà accessibili dall’account Google appena autorizzato. Scegli quella che raccoglie i dati di <strong class="font-medium text-slate-300">{{ $site->host }}</strong>.</p>
        </div>

        @if (session('integration_error'))
            <div class="mb-6 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200" role="alert">{{ session('integration_error') }}</div>
        @endif

        <section class="rounded-3xl border border-slate-800 bg-slate-900 p-8">
            @if ($accounts === [])
                <div class="text-center">
                    <h2 class="text-xl font-semibold">Nessuna proprietà disponibile</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-400">L’account autorizzato non può accedere ad alcuna proprietà GA4. Verifica i permessi in Google Analytics oppure collega un altro account Google.</p>
                    <button type="submit" form="cancel-google-analytics" class="mt-6 rounded-xl bg-slate-700 px-5 py-3 font-semibold text-slate-200">Annulla e torna al sito</button>
                </div>
            @else
                <form method="POST" action="{{ route('google-analytics.store', $site) }}" class="space-y-8">
                    @csrf

                    <div class="space-y-6">
                        @foreach ($accounts as $account)
                            <fieldset>
                                <legend class="mb-3 text-sm font-semibold text-slate-300">{{ $account['account_name'] }} <span class="font-normal text-slate-500">Account {{ $account['account_id'] }}</span></legend>

                                <div class="grid gap-3">
                                    @foreach ($account['properties'] as $property)
                                        <label class="flex cursor-pointer items-start gap-4 rounded-xl border border-slate-700 bg-slate-950/50 p-4 hover:border-sky-500/60">
                                            <input name="property" type="radio" value="{{ $account['account_id'] }}:{{ $property['property_id'] }}" class="mt-1 size-4 border-slate-600 bg-slate-900 text-sky-500" @checked(old('property') === $account['account_id'].':'.$property['property_id'])>
                                            <span>
                                                <span class="block font-medium text-slate-100">{{ $property['property_name'] }}</span>
                                                <span class="mt-1 block text-sm text-slate-400">Proprietà {{ $property['property_id'] }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach
                    </div>

                    @error('property')<p class="text-sm text-rose-400">{{ $message }}</p>@enderror

                    <div class="flex flex-wrap items-center gap-4 border-t border-slate-800 pt-6">
                        <button type="submit" class="rounded-xl bg-sky-500 px-5 py-3 font-semibold text-slate-950">Collega proprietà selezionata</button>
                        <button type="submit" form="cancel-google-analytics" class="border-0 bg-transparent p-0 text-sm font-normal text-slate-400">Annulla collegamento</button>
                    </div>
                </form>
            @endif

            <form id="cancel-google-analytics" method="POST" action="{{ route('google-analytics.cancel', $site) }}">
                @csrf
                @method('DELETE')
            </form>
        </section>
    </main>
@endsection
