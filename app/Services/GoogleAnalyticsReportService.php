<?php

namespace App\Services;

use App\Exceptions\GoogleAnalyticsReportException;
use App\Models\GoogleAnalyticsProperty;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GoogleAnalyticsReportService
{
    private const DATA_API_ENDPOINT = 'https://analyticsdata.googleapis.com/v1beta';

    private const FAILURE_DELAYS_MINUTES = [5, 15, 30, 60];

    private const QUOTA_SAFETY_THRESHOLDS = [
        'tokensPerDay' => 100,
        'tokensPerHour' => 100,
        'tokensPerProjectPerHour' => 100,
        'serverErrorsPerProjectPerHour' => 2,
    ];

    private const QUOTA_LABELS = [
        'tokensPerDay' => 'Token giornalieri della proprietà',
        'tokensPerHour' => 'Token orari della proprietà',
        'tokensPerProjectPerHour' => 'Token orari del progetto per questa proprietà',
        'concurrentRequests' => 'Richieste contemporanee',
        'serverErrorsPerProjectPerHour' => 'Errori del servizio Google nell’ora',
        'potentiallyThresholdedRequestsPerHour' => 'Richieste orarie soggette a soglie di privacy',
    ];

    public function __construct(private readonly GoogleAnalyticsService $analytics) {}

    /** @return array<string, mixed> */
    public function quotaDiagnostics(GoogleAnalyticsProperty $property): array
    {
        return Cache::get($this->cooldownKey($property).'.diagnostics', []);
    }

    /** @param array<int, array<string, mixed>> $reports */
    private function recordQuota(GoogleAnalyticsProperty $property, array $reports): void
    {
        $remaining = [];

        foreach ($reports as $report) {
            foreach (self::QUOTA_LABELS as $field => $label) {
                $value = data_get($report, "propertyQuota.{$field}.remaining");

                if (is_int($value) && $value >= 0) {
                    $remaining[$field] = min($remaining[$field] ?? $value, $value);
                }
            }
        }

        $diagnostics = $this->quotaDiagnostics($property);
        unset($diagnostics['error'], $diagnostics['request_error'], $diagnostics['quota_pause']);
        Cache::forget($this->cooldownKey($property).'.failures');

        if ($remaining !== []) {
            $diagnostics['measured_at'] = now()->toIso8601String();
            $diagnostics['quotas'] = [];

            foreach ($remaining as $field => $value) {
                $diagnostics['quotas'][] = ['label' => self::QUOTA_LABELS[$field], 'remaining' => $value];
            }
        }

        foreach (self::QUOTA_SAFETY_THRESHOLDS as $field => $threshold) {
            if (isset($remaining[$field]) && $remaining[$field] <= $threshold) {
                $message = 'Aggiornamento sospeso per un’ora per proteggere la quota Google Analytics: '.self::QUOTA_LABELS[$field].'.';
                $diagnostics['quota_pause'] = [
                    'message' => $message,
                    'retry_at' => now()->addHour()->toIso8601String(),
                ];
                Cache::put($this->cooldownKey($property), $message, now()->addHour());
                break;
            }
        }

        Cache::put($this->cooldownKey($property).'.diagnostics', $diagnostics, now()->addDay());
    }

    /** @param array<string, mixed> $payload */
    private function recordQuotaError(GoogleAnalyticsProperty $property, array $payload, int $delayMinutes): void
    {
        // Classify known quota descriptions without persisting provider messages or metadata.
        $description = strtolower(json_encode($payload) ?: '');
        $compact = preg_replace('/[^a-z]/', '', $description) ?? '';
        $label = null;

        foreach (self::QUOTA_LABELS as $field => $candidate) {
            if (str_contains($compact, strtolower($field))) {
                $label = $candidate;
                break;
            }
        }

        if ($label === null) {
            $patterns = [
                '/hourly tokens? per project|tokens? per project per (?:property per )?hour/' => 'tokensPerProjectPerHour',
                '/daily tokens?|tokens? per (?:property per )?day/' => 'tokensPerDay',
                '/hourly tokens?|tokens? per (?:property per )?hour/' => 'tokensPerHour',
                '/concurrent requests?/' => 'concurrentRequests',
                '/server errors?/' => 'serverErrorsPerProjectPerHour',
                '/potentially thresholded/' => 'potentiallyThresholdedRequestsPerHour',
            ];

            foreach ($patterns as $pattern => $field) {
                if (preg_match($pattern, $description)) {
                    $label = self::QUOTA_LABELS[$field];
                    break;
                }
            }
        }

        $diagnostics = $this->quotaDiagnostics($property);
        unset($diagnostics['request_error']);
        $diagnostics['error'] = [
            'http_status' => 429,
            'quota_label' => $label,
            'occurred_at' => now()->toIso8601String(),
            'retry_at' => now()->addMinutes($delayMinutes)->toIso8601String(),
        ];
        Cache::put($this->cooldownKey($property).'.diagnostics', $diagnostics, now()->addDay());
    }

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

        return $this->cachedReport($property, $cacheKey, true, function () use ($property, $host, $days): array {
            return $this->requestOverview($property, $host, $days);
        });
    }

    private function nextFailureDelay(GoogleAnalyticsProperty $property): int
    {
        $key = $this->cooldownKey($property).'.failures';
        $failures = min((int) Cache::get($key, 0) + 1, count(self::FAILURE_DELAYS_MINUTES));
        Cache::put($key, $failures, now()->addDay());

        return self::FAILURE_DELAYS_MINUTES[$failures - 1];
    }

    private function recordRequestError(GoogleAnalyticsProperty $property, string $type, string $message, ?int $status = null, ?string $phase = null): string
    {
        $retryAt = null;

        if ($type !== 'http') {
            $delay = $this->nextFailureDelay($property);
            $retryAt = now()->addMinutes($delay);
            $message .= " Nuove letture sospese per {$delay} minuti.";
            Cache::put($this->cooldownKey($property), $message, $retryAt);
        }

        $diagnostics = $this->quotaDiagnostics($property);
        $diagnostics['request_error'] = [
            'type' => $type,
            'message' => $message,
            'http_status' => $status,
            'phase' => $phase,
            'occurred_at' => now()->toIso8601String(),
            'retry_at' => $retryAt?->toIso8601String(),
        ];
        Cache::put($this->cooldownKey($property).'.diagnostics', $diagnostics, now()->addDay());

        return $message;
    }

    /**
     * @return array{
     *     previous_summary: array<string, string>,
     *     landing_pages: array<int, array<string, string>>,
     *     device_performance: array<int, array<string, string>>
     * }
     */
    public function insightData(GoogleAnalyticsProperty $property, string $host, int $days): array
    {
        $connection = $property->connection;

        if (! $connection || $connection->status !== 'active') {
            throw new GoogleAnalyticsReportException('Il collegamento Google Analytics non è attivo. Collegalo nuovamente prima di richiedere i dati.');
        }

        $cacheKey = implode('.', [
            'google-analytics-insight-data',
            $property->getKey(),
            $connection->getKey(),
            $property->property_id,
            $days,
            sha1($host),
        ]);

        return $this->cachedReport($property, $cacheKey, false, function () use ($property, $host, $days): array {
            return $this->requestInsightData($property, $host, $days);
        });
    }

    private function cooldownKey(GoogleAnalyticsProperty $property): string
    {
        return 'ga4-report-cooldown.'.sha1((string) $property->property_id);
    }

    private function cachedReport(GoogleAnalyticsProperty $property, string $key, bool $allowStale, callable $fetch): array
    {
        if (is_array($cached = Cache::get($key))) {
            return $cached;
        }

        $fallback = function (string $message) use ($key, $allowStale): array {
            $previous = $allowStale ? Cache::get($key.'.last-success') : null;

            if (is_array($previous)) {
                return $previous + ['is_stale' => true, 'refresh_warning' => $message];
            }

            throw new GoogleAnalyticsReportException($message);
        };

        if (is_string($message = Cache::get($this->cooldownKey($property)))) {
            return $fallback($message);
        }

        $lock = Cache::lock('ga4-report-lock.'.sha1((string) $property->property_id), 60);

        if (! $lock->get()) {
            return $fallback('È già in corso una lettura dei report Analytics. Riprova tra poco.');
        }

        try {
            // Another request may have populated the cache before this lock was acquired.
            if (is_array($cached = Cache::get($key))) {
                return $cached;
            }

            if (is_string($message = Cache::get($this->cooldownKey($property)))) {
                return $fallback($message);
            }

            $data = $fetch();
            Cache::put($key, $data, now()->addMinutes(10));

            if ($allowStale) {
                Cache::put($key.'.last-success', $data, now()->addDay());
            }

            return $data;
        } catch (GoogleAnalyticsReportException $exception) {
            if (is_string($message = Cache::get($this->cooldownKey($property)))) {
                return $fallback($message);
            }

            throw $exception;
        } finally {
            $lock->release();
        }
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
        $reports = $this->batchReports($property, $requests);
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
     * @return array<string, mixed>
     */
    private function requestInsightData(GoogleAnalyticsProperty $property, string $host, int $days): array
    {
        $currentDateRange = [
            'startDate' => ($days - 1).'daysAgo',
            'endDate' => 'today',
        ];
        $previousDateRange = [
            'startDate' => (($days * 2) - 1).'daysAgo',
            'endDate' => $days.'daysAgo',
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

        $reports = $this->batchReports($property, [
            [
                'dateRanges' => [$previousDateRange],
                'dimensionFilter' => $hostFilter,
                'metrics' => $this->fields([
                    'activeUsers',
                    'sessions',
                    'screenPageViews',
                    'engagedSessions',
                    'engagementRate',
                    'averageSessionDuration',
                ]),
            ],
            [
                'dateRanges' => [$currentDateRange],
                'dimensionFilter' => $hostFilter,
                'dimensions' => $this->fields(['landingPagePlusQueryString']),
                'metrics' => $this->fields(['sessions', 'activeUsers', 'engagementRate', 'averageSessionDuration']),
                'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
                'limit' => '20',
            ],
            [
                'dateRanges' => [$currentDateRange],
                'dimensionFilter' => $hostFilter,
                'dimensions' => $this->fields(['deviceCategory']),
                'metrics' => $this->fields(['sessions', 'activeUsers', 'engagementRate', 'averageSessionDuration']),
                'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
                'limit' => '10',
            ],
        ]);

        return [
            'previous_summary' => $this->rows((array) ($reports[0] ?? []))[0] ?? [],
            'landing_pages' => $this->rows((array) ($reports[1] ?? [])),
            'device_performance' => $this->rows((array) ($reports[2] ?? [])),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $requests
     * @return array<int, array<string, mixed>>
     */
    private function batchReports(GoogleAnalyticsProperty $property, array $requests): array
    {
        $url = self::DATA_API_ENDPOINT.'/properties/'.$property->property_id.':batchRunReports';
        $requests = array_map(fn (array $request): array => $request + ['returnPropertyQuota' => true], $requests);
        $phase = 'authorization';

        try {
            $token = $this->analytics->validAccessToken($property->connection);
            $phase = 'reports';
            $response = Http::acceptJson()
                ->withToken($token)
                ->connectTimeout(5)
                ->timeout(20)
                ->post($url, ['requests' => $requests]);
        } catch (ConnectionException $exception) {
            $errno = 0;

            // Read only the numeric error code. URLs and raw exception messages stay out of diagnostics.
            if (preg_match('/cURL error (\d+):/i', $exception->getMessage(), $match)) {
                $errno = (int) $match[1];
            }

            $type = $errno === 28 ? 'timeout' : 'network';
            $reason = match ($errno) {
                28 => 'Timeout nella comunicazione con Google.',
                6 => 'Errore DNS. Il server non è riuscito a risolvere l’indirizzo di Google.',
                7 => 'Connessione a Google non riuscita.',
                35, 60 => 'Errore TLS nella connessione sicura con Google.',
                default => 'Errore di rete nella comunicazione con Google.',
            };
            $stage = $phase === 'authorization' ? 'Il problema si è verificato durante il rinnovo dell’autorizzazione.' : 'Il problema si è verificato durante la lettura dei report.';
            $message = $this->recordRequestError($property, $type, $reason.' '.$stage, phase: $phase);
            throw new GoogleAnalyticsReportException($message);
        }

        if ($response->failed()) {
            $this->throwReportException($property, $response->status(), (array) $response->json());
        }

        $payload = $response->json();

        if (! is_array($payload) || ! is_array($payload['reports'] ?? null)) {
            $message = 'Google Analytics ha restituito una risposta dati non valida. Riprova più tardi.';
            $message = $this->recordRequestError($property, 'invalid_response', $message, $response->status(), 'reports');
            throw new GoogleAnalyticsReportException($message);
        }

        $reports = array_values($payload['reports']);
        $this->recordQuota($property, $reports);

        return $reports;
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
        if ($status !== 429) {
            $message = match ($status) {
                401 => 'Google ha rifiutato l’autorizzazione salvata.',
                403 => 'Google ha negato l’accesso ai report. Verifica permessi e attivazione della Data API.',
                500 => 'Google Analytics ha restituito un errore interno del servizio (HTTP 500).',
                503 => 'Il servizio Google Analytics è temporaneamente indisponibile (HTTP 503).',
                default => "Google Analytics ha restituito un errore HTTP {$status}.",
            };
            $message = $this->recordRequestError($property, $status >= 500 ? 'server' : 'http', $message, $status, 'reports');
        }

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
            $delay = max(15, $this->nextFailureDelay($property));
            $this->recordQuotaError($property, $payload, $delay);
            $message = "Google Analytics ha temporaneamente esaurito la quota di richieste. L’app sospende le nuove letture per {$delay} minuti, poi riprova alla prossima richiesta.";
            Cache::put($this->cooldownKey($property), $message, now()->addMinutes($delay));
            throw new GoogleAnalyticsReportException($message);
        }

        if ($status >= 500) {
            throw new GoogleAnalyticsReportException($message);
        }

        throw new GoogleAnalyticsReportException('Google Analytics non ha restituito i dati richiesti. Riprova più tardi; se il problema continua, controlla la configurazione della proprietà.');
    }
}
