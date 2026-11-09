@extends('layouts.workspace')

@section('title', 'Scegli proprietà Search Console — '.config('app.name', 'Monitoring Hub'))

@section('page')
    <main id="main-content" class="ui-page ui-page-narrow" tabindex="-1">
        <x-page-header title="Scegli la proprietà da collegare" kicker="Google Search Console">
            <x-slot:back><button type="submit" form="cancel-search-console" class="border-0 bg-transparent p-0 text-sm font-normal text-accent-ink">← Annulla e torna a {{ $site->name }}</button></x-slot:back>
            <p class="ui-page-description">Sono mostrate soltanto le proprietà accessibili dall’account autorizzato. Quelle compatibili con <strong class="font-medium text-soft">{{ $site->host }}</strong> sono evidenziate e mostrate per prime.</p>
        </x-page-header>

        @if (session('integration_error'))
            <div class="mb-6 ui-alert ui-tone-danger" role="alert">{{ session('integration_error') }}</div>
        @endif

        <section class="ui-panel p-5 sm:p-7">
            @if ($properties === [])
                <div class="text-center">
                    <h2 class="text-xl font-semibold">Nessuna proprietà disponibile</h2>
                    <p class="mt-3 text-sm leading-6 text-muted">L’account autorizzato non può accedere ad alcuna proprietà Search Console. Verifica di aver aggiunto e verificato il sito, oppure collega un altro account Google.</p>
                    <a href="https://search.google.com/search-console" target="_blank" rel="noreferrer" class="mt-4 inline-flex text-sm font-medium text-accent-ink">Controlla in Search Console ↗</a>
                    <div><button type="submit" form="cancel-search-console" class="mt-6 ui-button">Annulla e torna al sito</button></div>
                </div>
            @else
                <form method="POST" action="{{ route('google-search-console.store', $site) }}" class="space-y-8">
                    @csrf

                    <div class="grid gap-3">
                        @foreach ($properties as $property)
                            <label class="flex cursor-pointer items-start gap-4 rounded-xl border p-4 {{ $property['matches_host'] ? 'border-success-ink/22 bg-success-soft hover:border-success-ink/22' : 'border-line-strong bg-subtle hover:border-accent' }}">
                                <input name="property" type="radio" value="{{ $property['site_url'] }}" class="mt-1 size-4 border-line-strong bg-surface text-accent-ink" @checked(old('property') === $property['site_url'])>
                                <span class="min-w-0 flex-1">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <span class="break-all font-medium text-ink">{{ $property['site_url'] }}</span>
                                        @if ($property['matches_host'])
                                            <span class="rounded-full bg-success-soft px-2 py-0.5 text-xs font-medium text-success-ink">Compatibile con il sito</span>
                                        @endif
                                    </span>
                                    <span class="mt-1 block text-sm text-muted">{{ $property['property_type'] === 'domain' ? 'Proprietà Dominio' : 'Proprietà con prefisso URL' }} · permesso {{ $property['permission_level'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    @error('property')<p class="text-sm text-danger-ink">{{ $message }}</p>@enderror

                    <p class="rounded-xl border border-warning-ink/22 bg-warning-soft p-4 text-sm leading-6 text-warning-ink">Puoi scegliere anche una proprietà non evidenziata, ma i dati potrebbero non corrispondere al dominio censito. Per una proprietà con prefisso URL controlla anche protocollo e sottodominio.</p>

                    <div class="flex flex-wrap items-center gap-4 border-t border-line pt-6">
                        <button type="submit" class="ui-button ui-button-primary">Collega proprietà selezionata</button>
                        <button type="submit" form="cancel-search-console" class="border-0 bg-transparent p-0 text-sm font-normal text-muted">Annulla collegamento</button>
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
