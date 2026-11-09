@props(['overview'])

@php
    $rows = $overview['daily'];
    $maximum = max(1, (int) collect($rows)->max('sessions'));
    $date = fn ($value) => \Carbon\CarbonImmutable::parse($value)->format('d/m/Y');
    $number = fn ($value) => number_format((int) $value, 0, ',', '.');
@endphp

<section class="ui-panel mt-5" aria-labelledby="trend-heading">
    <div class="ui-panel-heading flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 id="trend-heading">Sessioni giorno per giorno</h2>
            <p class="mt-1 text-sm text-muted">{{ $date($overview['start_date']) }} – {{ $date($overview['end_date']) }}</p>
        </div>
        <div class="ui-chart-legend"><span class="ui-chart-swatch" aria-hidden="true"></span> Sessioni</div>
    </div>
    @if ($rows === [])
        <p class="p-5 text-sm text-muted">Nessuna sessione giornaliera disponibile.</p>
    @else
        <div class="p-5">
            <figure aria-label="Andamento delle sessioni giornaliere">
                <div class="ui-chart-grid" aria-hidden="true">
                    <div class="ui-chart-axis"><span>{{ $number($maximum) }}</span><span>0</span></div>
                    <div class="ui-chart-plot">
                        @foreach ($rows as $row)
                            @php($sessions = (int) ($row['sessions'] ?? 0))
                            <div class="ui-chart-bar" title="{{ $date($row['date']) }} — {{ $sessions }} sessioni, {{ $row['activeUsers'] ?? 0 }} utenti, {{ $row['screenPageViews'] ?? 0 }} visualizzazioni">
                                <span style="height: {{ ($sessions / $maximum) * 100 }}%"></span>
                            </div>
                        @endforeach
                    </div>
                    <div class="ui-chart-dates">
                        <span>{{ $date($rows[0]['date']) }}</span>
                        <span>{{ $date($rows[array_key_last($rows)]['date']) }}</span>
                    </div>
                </div>
            </figure>

            <details class="mt-4 text-sm">
                <summary class="font-semibold text-accent-ink">Dati giornalieri</summary>
                <div class="ui-table-scroll mt-3">
                    <table class="ui-table" aria-label="Sessioni, utenti e visualizzazioni per giorno">
                        <thead>
                            <tr><th scope="col">Data</th><th scope="col" class="text-right">Sessioni</th><th scope="col" class="text-right">Utenti</th><th scope="col" class="text-right">Visualizzazioni</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <th scope="row">{{ $date($row['date']) }}</th>
                                    <td class="text-right">{{ $number($row['sessions'] ?? 0) }}</td>
                                    <td class="text-right">{{ $number($row['activeUsers'] ?? 0) }}</td>
                                    <td class="text-right">{{ $number($row['screenPageViews'] ?? 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        </div>
    @endif
</section>
