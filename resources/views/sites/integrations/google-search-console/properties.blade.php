@extends('layouts.app')

@section('title', 'Scegli proprietà Search Console — '.config('app.name', 'Monitoring Hub'))

@section('content')
    @include('partials.navigation')

    <main class="mx-auto max-w-4xl px-6 py-10">
        <div class="mb-8">
            <button type="submit" form="cancel-search-console" class="border-0 bg-transparent p-0 text-sm font-normal text-sky-400">← Annulla e torna a {{ $site->name }}</button>
            <p class="mt-6 text-sm font-semibold uppercase tracking-[0.2em] text-sky-400">Google Search Console</p>
            <h1 class="mt-2 text-3xl font-semibold">Scegli la proprietà da collegare</h1>
            <p class="mt-3 max-w-2xl leading-7 text-slate-400">Sono mostrate soltanto le proprietà accessibili dall’account autorizzato. Quelle compatibili con <strong class="font-medium text-slate-300">{{ $site->host }}</strong> sono evidenziate e mostrate per prime.</p>
        </div>

        @if (session('integration_error'))
            <div class="mb-6 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200" role="alert">{{ session('integration_error') }}</div>
        @endif

        <section class="rounded-3xl border border-slate-800 bg-slate-900 p-8">
            @if ($properties === [])
                <div class="text-center">
                    <h2 class="text-xl font-semibold">Nessuna proprietà disponibile</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-400">L’account autorizzato non può accedere ad alcuna proprietà Search Console. Verifica di aver aggiunto e verificato il sito, oppure collega un altro account Google.</p>
                    <a href="https://search.google.com/search-console" target="_blank" rel="noreferrer" class="mt-4 inline-flex text-sm font-medium text-sky-400">Controlla in Search Console ↗</a>
                    <div><button type="submit" form="cancel-search-console" class="mt-6 rounded-xl bg-slate-700 px-5 py-3 font-semibold text-slate-200">Annulla e torna al sito</button></div>
                </div>
            @else
                <form method="POST" action="{{ route('google-search-console.store', $site) }}" class="space-y-8">
                    @csrf

                    <div class="grid gap-3">
                        @foreach ($properties as $property)
                            <label class="flex cursor-pointer items-start gap-4 rounded-xl border p-4 {{ $property['matches_host'] ? 'border-emerald-500/40 bg-emerald-500/5 hover:border-emerald-400' : 'border-slate-700 bg-slate-950/50 hover:border-sky-500/60' }}">
                                <input name="property" type="radio" value="{{ $property['site_url'] }}" class="mt-1 size-4 border-slate-600 bg-slate-900 text-sky-500" @checked(old('property') === $property['site_url'])>
                                <span class="min-w-0 flex-1">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <span class="break-all font-medium text-slate-100">{{ $property['site_url'] }}</span>
                                        @if ($property['matches_host'])
                                            <span class="rounded-full bg-emerald-400/10 px-2 py-0.5 text-xs font-medium text-emerald-300">Compatibile con il sito</span>
                                        @endif
                                    </span>
                                    <span class="mt-1 block text-sm text-slate-400">{{ $property['property_type'] === 'domain' ? 'Proprietà Dominio' : 'Proprietà con prefisso URL' }} · permesso {{ $property['permission_level'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    @error('property')<p class="text-sm text-rose-400">{{ $message }}</p>@enderror

                    <p class="rounded-xl border border-amber-500/20 bg-amber-500/10 p-4 text-sm leading-6 text-amber-100">Puoi scegliere anche una proprietà non evidenziata, ma i dati potrebbero non corrispondere al dominio censito. Per una proprietà con prefisso URL controlla anche protocollo e sottodominio.</p>

                    <div class="flex flex-wrap items-center gap-4 border-t border-slate-800 pt-6">
                        <button type="submit" class="rounded-xl bg-sky-500 px-5 py-3 font-semibold text-slate-950">Collega proprietà selezionata</button>
                        <button type="submit" form="cancel-search-console" class="border-0 bg-transparent p-0 text-sm font-normal text-slate-400">Annulla collegamento</button>
                    </div>
                </form>
            @endif

            <form id="cancel-search-console" method="POST" action="{{ route('google-search-console.cancel', $site) }}">
                @csrf
                @method('DELETE')
            </form>
        </section>
    </main>
@endsection
