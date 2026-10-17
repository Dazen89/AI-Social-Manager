<?php

namespace App\Services;

use App\Exceptions\GoogleAnalyticsReportException;
use App\Models\GoogleAnalyticsProperty;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class GoogleAnalyticsReportService
{
    private const DATA_API_ENDPOINT = 'https://analyticsdata.googleapis.com/v1beta';

    public function __construct(private readonly GoogleAnalyticsService $analytics) {}

    /**
     * @return array{
     *     summary: array<string, string>,
     *     daily: array<int, array<string, string>>,
     *     pages: array<int, array<string, string>>,
     *     channels: array<int, array<string, string>>,
     *     devices: array<int, array{label: string, sessions: int}>,
     *     countries: array<int, array{label: string, sessions: int}>,
     *     has_data: bool,
     *     fetched_at: string,
     *     start_date: string,
     *     end_date: string
     * }
     */
    public function overview(GoogleAnalyticsProperty $property, string $host, int $days): array
    {
        $connection = $property->connection;

        if (! $connection || $connection->status !== 'active') {
            throw new GoogleAnalyticsReportException('Il collegamento Google Analytics non è attivo. Collegalo nuovamente prima di richiedere i dati.');
        }

        $cacheKey = implode('.', [
            'google-analytics-overview',
            $property->getKey(),
            $connection->getKey(),
            $property->property_id,
            $days,
            sha1($host),
        ]);

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($property, $host, $days): array {
            return $this->requestOverview($property, $host, $days);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function requestOverview(GoogleAnalyticsProperty $property, string $host, int $days): array
    {
        $dateRange = [
            'startDate' => ($days - 1).'daysAgo',
            'endDate' => 'today',
        ];
        $hostFilter = [
            'filter' => [
                'fieldName' => 'hostName',
                'stringFilter' => [
                    'matchType' => 'EXACT',
                    'value' => $host,
                    'caseSensitive' => false,
                ],
            ],
        ];
        $baseRequest = [
            'dateRanges' => [$dateRange],
            'dimensionFilter' => $hostFilter,
        ];

        $requests = [
            array_merge($baseRequest, [
                'metrics' => $this->fields([
                    'activeUsers',
                    'sessions',
                    'screenPageViews',
                    'engagedSessions',
                    'engagementRate',
                    'averageSessionDuration',
                ]),
            ]),
            array_merge($baseRequest, [
                'dimensions' => $this->fields(['date']),
                'metrics' => $this->fields(['sessions', 'activeUsers', 'screenPageViews']),
                'orderBys' => [['dimension' => ['dimensionName' => 'date']]],
                'limit' => (string) $days,
            ]),
            array_merge($baseRequest, [
                'dimensions' => $this->fields(['pagePathPlusQueryString', 'pageTitle']),
                'metrics' => $this->fields(['screenPageViews', 'activeUsers']),
                'orderBys' => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]],
                'limit' => '10',
            ]),
            array_merge($baseRequest, [
                'dimensions' => $this->fields(['sessionDefaultChannelGroup']),
                'metrics' => $this->fields(['sessions', 'activeUsers', 'engagementRate']),
                'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
                'limit' => '10',
            ]),
            array_merge($baseRequest, [
                'dimensions' => $this->fields(['country', 'deviceCategory']),
                'metrics' => $this->fields(['sessions']),
                'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
                'limit' => '1000',
            ]),
        ];

        $url = self::DATA_API_ENDPOINT.'/properties/'.$property->property_id.':batchRunReports';
        try {
            $response = Http::acceptJson()
                ->withToken($this->analytics->validAccessToken($property->connection))
                ->timeout(20)
                ->retry(2, 250, function (Throwable $exception): bool {
                    return $exception instanceof ConnectionException
                        || ($exception instanceof RequestException && $exception->response->serverError());
                }, throw: false)
                ->post($url, ['requests' => $requests]);
        } catch (ConnectionException) {
            throw new GoogleAnalyticsReportException('Google Analytics non è raggiungibile in questo momento. Controlla la connessione del server e riprova.');
        }

        if ($response->failed()) {
            $this->throwReportException($property, $response->status(), (array) $response->json());
        }

        $payload = $response->json();

        if (! is_array($payload) || ! is_array($payload['reports'] ?? null)) {
            throw new GoogleAnalyticsReportException('Google Analytics ha restituito una risposta dati non valida. Riprova più tardi.');
        }

        $reports = array_values($payload['reports']);
        $summary = $this->rows((array) ($reports[0] ?? []))[0] ?? [];
        $daily = $this->rows((array) ($reports[1] ?? []));
        $pages = $this->rows((array) ($reports[2] ?? []));
        $channels = $this->rows((array) ($reports[3] ?? []));
        $audienceRows = $this->rows((array) ($reports[4] ?? []));

        foreach ($daily as &$row) {
            $rawDate = (string) ($row['date'] ?? '');
            $row['date'] = preg_match('/^\d{8}$/', $rawDate)
                ? CarbonImmutable::createFromFormat('Ymd', $rawDate)->format('Y-m-d')
                : $rawDate;
        }
        unset($row);

        $hasData = (int) ($summary['activeUsers'] ?? 0) > 0
            || (int) ($summary['sessions'] ?? 0) > 0
            || (int) ($summary['screenPageViews'] ?? 0) > 0;

        return [
            'summary' => $summary,
            'daily' => $daily,
            'pages' => $pages,
            'channels' => $channels,
            'devices' => $this->aggregateSessions($audienceRows, 'deviceCategory'),
            'countries' => $this->aggregateSessions($audienceRows, 'country'),
            'has_data' => $hasData,
            'fetched_at' => now()->toIso8601String(),
            'start_date' => now()->subDays($days - 1)->toDateString(),
            'end_date' => now()->toDateString(),
        ];
    }

    /**
     * @param  array<int, string>  $names
     * @return array<int, array{name: string}>
     */
    private function fields(array $names): array
    {
        return array_map(fn (string $name): array => ['name' => $name], $names);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<int, array<string, string>>
     */
    private function rows(array $report): array
    {
        $dimensionNames = collect(Arr::wrap($report['dimensionHeaders'] ?? []))
            ->pluck('name')
            ->map(fn (mixed $name): string => (string) $name)
            ->all();
        $metricNames = collect(Arr::wrap($report['metricHeaders'] ?? []))
            ->pluck('name')
            ->map(fn (mixed $name): string => (string) $name)
            ->all();

        return collect(Arr::wrap($report['rows'] ?? []))
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(function (array $row) use ($dimensionNames, $metricNames): array {
                $values = [];

                foreach ($dimensionNames as $index => $name) {
                    $values[$name] = (string) data_get($row, "dimensionValues.{$index}.value", '');
                }

                foreach ($metricNames as $index => $name) {
                    $values[$name] = (string) data_get($row, "metricValues.{$index}.value", '0');
                }

                return $values;
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @return array<int, array{label: string, sessions: int}>
     */
    private function aggregateSessions(array $rows, string $dimension): array
    {
        $totals = [];

        foreach ($rows as $row) {
            $label = trim((string) ($row[$dimension] ?? ''));
            $label = $label === '' || $label === '(not set)' ? 'Non disponibile' : $label;
            $totals[$label] = ($totals[$label] ?? 0) + (int) ($row['sessions'] ?? 0);
        }

        arsort($totals);

        return collect($totals)
            ->take(10)
            ->map(fn (int $sessions, string $label): array => compact('label', 'sessions'))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function throwReportException(GoogleAnalyticsProperty $property, int $status, array $payload): never
    {
        $encodedPayload = json_encode($payload) ?: '';

        if ($status === 403 && (str_contains($encodedPayload, 'analyticsdata.googleapis.com') || str_contains($encodedPayload, 'SERVICE_DISABLED'))) {
            throw new GoogleAnalyticsReportException(
                'La connessione è valida, ma nel progetto Google Cloud non è ancora attiva la Google Analytics Data API. Abilitala e riprova dopo qualche minuto.',
                'https://console.cloud.google.com/apis/library/analyticsdata.googleapis.com',
                'Abilita Google Analytics Data API',
            );
        }

        if ($status === 401) {
            $property->connection?->update([
                'status' => 'error',
                'last_error' => 'Google non ha accettato l’autorizzazione durante la lettura dei report.',
            ]);

            throw new GoogleAnalyticsReportException('Google non ha accettato l’autorizzazione salvata. Collega nuovamente l’account dalla gestione del sito.');
        }

        if ($status === 403) {
            throw new GoogleAnalyticsReportException('L’account Google collegato non può leggere i report di questa proprietà. Controlla i permessi oppure collega un altro account.');
        }

        if ($status === 429) {
            throw new GoogleAnalyticsReportException('Google Analytics ha temporaneamente esaurito la quota di richieste. Attendi qualche minuto e riprova.');
        }

        throw new GoogleAnalyticsReportException('Google Analytics non ha restituito i dati richiesti. Riprova più tardi; se il problema continua, controlla la configurazione della proprietà.');
    }
}
