@extends('layouts.workspace')

@section('title', 'Dashboard — '.config('app.name'))

@section('page')
    <main id="main-content" class="ui-page" tabindex="-1">
        <x-page-header title="Dashboard" kicker="Area di monitoraggio" description="Consulta i tuoi siti e lo stato dei servizi collegati.">
            <x-slot:actions>
                <a href="{{ route('sites.create') }}" class="ui-button ui-button-primary"><x-icon name="plus" /> Aggiungi sito</a>
            </x-slot:actions>
        </x-page-header>

        @if ($sites->isEmpty())
            <section class="ui-empty">
                <div class="mx-auto mb-3 grid size-10 place-items-center rounded-lg bg-accent-soft text-accent-ink"><x-icon name="globe" /></div>
                <h2>Nessun sito registrato</h2>
                <p>Aggiungi il primo sito che possiedi o sei autorizzato a monitorare.</p>
            </section>
        @else
            <section class="ui-panel" aria-labelledby="dashboard-sites-heading">
                <div class="ui-panel-heading">
                    <h2 id="dashboard-sites-heading">Siti</h2>
                    <p class="mt-1 text-sm text-muted">Analytics e Search Console si collegano a ogni sito. Social (X) e AI sono condivisi nel workspace.</p>
                </div>

                <div class="divide-y divide-line">
                    @foreach ($sites as $site)
                        @php
                            $siteIntegrations = route('sites.edit', $site).'#integrations-heading';
                            $services = [
                                ['Analytics', 'chart', $site->analytics_connected, $siteIntegrations],
                                ['Search Console', 'search', $site->search_console_connected, $siteIntegrations],
                                ['Social (X)', 'x', $xConnected, route('settings.x.edit')],
                                ['AI', 'ai', $aiConnected, route('settings.ai.index')],
                            ];
                        @endphp

                        <article class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3 px-5 py-4">
                            <div class="min-w-0">
                                <h3 class="font-semibold">
                                    <a href="{{ route('sites.show', $site) }}" class="text-ink no-underline hover:text-accent-ink hover:underline">{{ $site->name }}</a>
                                </h3>
                                <div class="mt-1 ui-actions">
                                    <span class="text-sm text-muted">{{ $site->host }}</span>
                                    @unless ($site->is_active)
                                        <span class="ui-badge">Sospeso</span>
                                    @endunless
                                </div>
                            </div>

                            <ul class="ui-actions">
                                @foreach ($services as [$label, $icon, $connected, $url])
                                    @php($statusLabel = $label.' — '.($connected ? 'Collegato' : 'Non collegato'))
                                    <li>
                                        <a href="{{ $url }}" title="{{ $statusLabel }}" aria-label="{{ $statusLabel }}"
                                           class="inline-flex min-h-8 items-center gap-1.5 rounded-lg border px-2 text-xs font-semibold no-underline hover:underline {{ $connected ? 'ui-tone-success' : 'border-line bg-subtle text-muted' }}">
                                            <x-icon :name="$icon" />
                                            {{ $label }}
                                            <span aria-hidden="true" class="size-1.5 shrink-0 rounded-full border border-current {{ $connected ? 'bg-current' : '' }}"></span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </article>
                    @endforeach
                </div>
            </section>

            @if ($sites->hasPages())
                <div class="mt-6">{{ $sites->links() }}</div>
            @endif
        @endif
    </main>
@endsection
