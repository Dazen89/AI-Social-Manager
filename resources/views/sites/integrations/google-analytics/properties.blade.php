@extends('layouts.workspace')

@section('title', 'Scegli proprietà Google Analytics — '.config('app.name', 'Monitoring Hub'))

@section('page')
    <main id="main-content" class="ui-page ui-page-narrow" tabindex="-1">
        <x-page-header title="Scegli la proprietà da collegare" kicker="Google Analytics 4">
            <x-slot:back><button type="submit" form="cancel-google-analytics" class="border-0 bg-transparent p-0 text-sm font-normal text-accent-ink">← Annulla e torna a {{ $site->name }}</button></x-slot:back>
            <p class="ui-page-description">Sono mostrate soltanto le proprietà accessibili dall’account Google appena autorizzato. Scegli quella che raccoglie i dati di <strong class="font-medium text-soft">{{ $site->host }}</strong>.</p>
        </x-page-header>

        @if (session('integration_error'))
            <div class="mb-6 ui-alert ui-tone-danger" role="alert">{{ session('integration_error') }}</div>
        @endif

        <section class="ui-panel p-5 sm:p-7">
            @if ($accounts === [])
                <div class="text-center">
                    <h2 class="text-xl font-semibold">Nessuna proprietà disponibile</h2>
                    <p class="mt-3 text-sm leading-6 text-muted">L’account autorizzato non può accedere ad alcuna proprietà GA4. Verifica i permessi in Google Analytics oppure collega un altro account Google.</p>
                    <button type="submit" form="cancel-google-analytics" class="mt-6 ui-button">Annulla e torna al sito</button>
                </div>
            @else
                <form method="POST" action="{{ route('google-analytics.store', $site) }}" class="space-y-8">
                    @csrf

                    <div class="space-y-6">
                        @foreach ($accounts as $account)
                            <fieldset>
                                <legend class="mb-3 text-sm font-semibold text-soft">{{ $account['account_name'] }} <span class="font-normal text-muted">Account {{ $account['account_id'] }}</span></legend>

                                <div class="grid gap-3">
                                    @foreach ($account['properties'] as $property)
                                        <label class="flex cursor-pointer items-start gap-4 rounded-xl border border-line-strong bg-subtle p-4 hover:border-accent">
                                            <input name="property" type="radio" value="{{ $account['account_id'] }}:{{ $property['property_id'] }}" class="mt-1 size-4 border-line-strong bg-surface text-accent-ink" @checked(old('property') === $account['account_id'].':'.$property['property_id'])>
                                            <span>
                                                <span class="block font-medium text-ink">{{ $property['property_name'] }}</span>
                                                <span class="mt-1 block text-sm text-muted">Proprietà {{ $property['property_id'] }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach
                    </div>

                    @error('property')<p class="text-sm text-danger-ink">{{ $message }}</p>@enderror

                    <div class="flex flex-wrap items-center gap-4 border-t border-line pt-6">
                        <button type="submit" class="ui-button ui-button-primary">Collega proprietà selezionata</button>
                        <button type="submit" form="cancel-google-analytics" class="border-0 bg-transparent p-0 text-sm font-normal text-muted">Annulla collegamento</button>
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
