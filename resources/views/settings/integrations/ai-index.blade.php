@extends('layouts.workspace')

@section('title', 'Integrazioni AI — '.config('app.name', 'Monitoring Hub'))

@section('page')
    <main id="main-content" class="ui-page" tabindex="-1">
        <x-page-header title="Integrazioni AI" kicker="Impostazioni dell’installazione" description="Conserva più collegamenti AI e scegli quale usare nell’applicazione. Una sola integrazione può essere attiva alla volta.">
            <x-slot:actions>
                <a href="{{ route('settings.ai.create') }}" class="ui-button ui-button-primary"><x-icon name="plus" /> Nuova integrazione</a>
            </x-slot:actions>
        </x-page-header>

        @if (session('status'))
            <div class="mt-6 ui-alert ui-tone-success" role="status">{{ session('status') }}</div>
        @endif

        @if (session('integration_error'))
            <div class="mt-6 ui-alert ui-tone-danger" role="alert">{{ session('integration_error') }}</div>
        @endif

        <section class="mt-8 rounded-2xl border border-line bg-subtle p-5 text-sm leading-6 text-muted">
            <h2 class="font-semibold text-soft">Consumi e costi</h2>
            <p class="mt-2">L’app registra richieste e token restituiti dai provider, senza salvare prompt o credenziali nel registro d’uso. I token permettono di controllare il consumo, ma non sono un importo monetario: prezzi, cache e tipi di token possono cambiare il calcolo.</p>
            <p class="mt-2">Per OpenAI il costo ufficiale dell’organizzazione è disponibile tramite una API amministrativa separata, che richiede una Admin API key più privilegiata. Per sicurezza questa applicazione non la richiede: verifica l’importo esatto nel pannello di fatturazione del provider. <a href="https://developers.openai.com/api/reference/resources/admin/subresources/organization/subresources/usage/methods/costs" target="_blank" rel="noreferrer" class="text-accent-ink">Documentazione Costs API ↗</a></p>
        </section>

        @if ($integrations->isEmpty())
            <section class="mt-8 rounded-3xl border border-dashed border-line-strong bg-subtle px-8 py-16 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-accent-soft text-2xl text-accent-ink" aria-hidden="true">✦</div>
                <h2 class="mt-5 text-xl font-semibold">Nessuna integrazione AI</h2>
                <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-muted">Aggiungi OpenAI o Anthropic, salva la chiave API e verifica il collegamento prima di usarlo.</p>
                <a href="{{ route('settings.ai.create') }}" class="mt-6 ui-button ui-button-primary">
                    Crea la prima integrazione
                </a>
            </section>
        @else
            <div class="mt-8 grid gap-5 lg:grid-cols-2">
                @foreach ($integrations as $integration)
                    @php
                        $statusDetails = match ($integration->status) {
                            'verified' => ['label' => 'Chiave e modello verificati', 'class' => 'bg-success-soft text-success-ink', 'icon' => '✓'],
                            'error' => ['label' => 'Verifica non riuscita', 'class' => 'bg-danger-soft text-danger-ink', 'icon' => '!'],
                            default => ['label' => 'Da verificare', 'class' => 'bg-warning-soft text-warning-ink', 'icon' => '•'],
                        };
                    @endphp
                    <article class="rounded-3xl border {{ $integration->is_active ? 'border-accent/25 bg-accent-soft' : 'border-line bg-surface' }} p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-xl font-semibold">{{ $integration->name }}</h2>
                                    @if ($integration->is_active)
                                        <span class="ui-button ui-button-primary">Attiva</span>
                                    @endif
                                </div>
                                <p class="mt-2 text-sm text-muted">{{ config('ai.providers.'.$integration->provider.'.name', $integration->provider) }}</p>
                                <p class="mt-1 text-sm text-muted">{{ config('ai.providers.'.$integration->provider.'.models.'.$integration->model, $integration->model) }}</p>
                            </div>
                            <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold {{ $statusDetails['class'] }}">
                                <span aria-hidden="true">{{ $statusDetails['icon'] }}</span>
                                {{ $statusDetails['label'] }}
                            </span>
                        </div>

                        @if ($integration->status === 'error' && $integration->last_error)
                            <p class="mt-4 rounded-xl border border-danger-ink/22 bg-danger-soft p-3 text-sm leading-6 text-danger-ink">{{ $integration->last_error }}</p>
                        @elseif ($integration->last_tested_at)
                            <p class="mt-4 text-xs text-muted">Ultima verifica: {{ $integration->last_tested_at->format('d/m/Y H:i') }}</p>
                            <p class="mt-2 text-xs leading-5 text-muted">La verifica controlla accesso e modello, ma non esegue una generazione e non può confermare credito o budget disponibili.</p>
                        @endif

                        <dl class="mt-5 grid grid-cols-2 gap-3 rounded-2xl border border-line bg-subtle p-4 text-sm">
                            <div>
                                <dt class="text-xs text-muted">Richieste registrate</dt>
                                <dd class="mt-1 font-semibold text-soft">{{ number_format($integration->usage_records_count, 0, ',', '.') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted">Token totali</dt>
                                <dd class="mt-1 font-semibold text-soft">{{ number_format((int) $integration->usage_total_tokens, 0, ',', '.') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted">Token in ingresso</dt>
                                <dd class="mt-1 text-soft">{{ number_format((int) $integration->usage_input_tokens, 0, ',', '.') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted">Token in uscita</dt>
                                <dd class="mt-1 text-soft">{{ number_format((int) $integration->usage_output_tokens, 0, ',', '.') }}</dd>
                            </div>
                            @if ($integration->usage_last_occurred_at)
                                <div class="col-span-2 border-t border-line pt-3 text-xs text-muted">Ultimo utilizzo: {{ \Carbon\CarbonImmutable::parse($integration->usage_last_occurred_at)->format('d/m/Y H:i') }}</div>
                            @endif
                        </dl>

                        <div class="mt-6 flex flex-wrap gap-2 border-t border-line pt-5">
                            <a href="{{ route('settings.ai.edit', $integration) }}" class="ui-button ui-button-small">Modifica</a>

                            <form method="POST" action="{{ route('settings.ai.verify', $integration) }}">
                                @csrf
                                <button type="submit" class="ui-button ui-tone-success ui-button-small">Verifica</button>
                            </form>

                            @unless ($integration->is_active)
                                <form method="POST" action="{{ route('settings.ai.activate', $integration) }}">
                                    @csrf
                                    <button type="submit" class="ui-button ui-button-small">Rendi attiva</button>
                                </form>
                            @endunless

                            <form method="POST" action="{{ route('settings.ai.destroy', $integration) }}" class="sm:ml-auto" data-confirm="Vuoi rimuovere questa integrazione AI?" onsubmit="return confirm(this.dataset.confirm)">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ui-button ui-button-danger ui-button-small">Rimuovi</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </main>
@endsection
