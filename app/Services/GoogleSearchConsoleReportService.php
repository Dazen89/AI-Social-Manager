<?php

namespace App\Services;

use App\Exceptions\GoogleSearchConsoleException;
use App\Models\GoogleSearchConsoleProperty;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class GoogleSearchConsoleReportService
{
    private const API_ENDPOINT = 'https://www.googleapis.com/webmasters/v3/sites/';

    public function __construct(private readonly GoogleSearchConsoleService $searchConsole) {}

    /** @return array<string, mixed> */
    public function overview(GoogleSearchConsoleProperty $property, string $host, int $days): array
    {
        $connection = $property->connection;

        if (! $connection || $connection->status !== 'active') {
            throw new GoogleSearchConsoleException('Il collegamento Search Console non è attivo. Collega nuovamente l’account.');
        }

        // Search Console uses Pacific Time. Leave a margin for finalized daily data.
        $end = CarbonImmutable::now('America/Los_Angeles')->subDays(3)->startOfDay();
        $start = $end->subDays($days - 1);
        $cacheKey = implode('.', [
            'search-console-overview',
            $property->getKey(),
            $connection->getKey(),
            sha1($property->property_url.'|'.$host),
            $days,
            $end->toDateString(),
        ]);

        return Cache::remember($cacheKey, now()->addHour(), function () use ($property, $host, $days, $start, $end): array {
            $base = [
                'startDate' => $start->toDateString(),
                'endDate' => $end->toDateString(),
                'type' => 'web',
                'dataState' => 'final',
                'aggregationType' => 'auto',
                'dimensionFilterGroups' => [[
                    'filters' => [[
                        'dimension' => 'page',
                        'operator' => 'includingRegex',
                        'expression' => '^https?://'.preg_quote($host, '/').'(?::[0-9]+)?/',
                    ]],
                ]],
            ];

            $summary = $this->query($property, array_merge($base, ['rowLimit' => 1]));
            $summaryRow = $this->rows($summary)[0] ?? null;

            if ($summaryRow === null) {
                return [
                    'has_data' => false,
                    'summary' => null,
                    'daily' => [],
                    'pages' => [],
                    'queries' => [],
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'fetched_at' => now()->toIso8601String(),
                ];
            }

            $daily = $this->rows($this->query($property, array_merge($base, [
                'dimensions' => ['date'],
                'rowLimit' => $days,
            ])));
            $pages = $this->rows($this->query($property, array_merge($base, [
                'dimensions' => ['page'],
                'rowLimit' => 20,
            ])));
            $queries = $this->rows($this->query($property, array_merge($base, [
                'dimensions' => ['query'],
                'rowLimit' => 20,
            ])));

            $pages = array_values(array_filter(array_map(function (array $row) use ($host): ?array {
                $url = (string) ($row['key'] ?? '');

                if (strtolower((string) parse_url($url, PHP_URL_HOST)) !== strtolower($host)) {
                    return null;
                }

                $path = (string) parse_url($url, PHP_URL_PATH);
                $row['key'] = mb_strimwidth($path === '' ? '/' : $path, 0, 500, '');

                return $row;
            }, $pages)));

            return [
                'has_data' => true,
                'summary' => $summaryRow,
                'daily' => $daily,
                'pages' => $pages,
                'queries' => $queries,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'fetched_at' => now()->toIso8601String(),
            ];
        });
    }

    /** @param array<string, mixed> $body
     *  @return array<string, mixed>
     */
    private function query(GoogleSearchConsoleProperty $property, array $body): array
    {
        $quotaCooldown = 'search-console-quota.'.$property->getKey().'.'.$property->connection->getKey();

        if (Cache::has($quotaCooldown)) {
            throw new GoogleSearchConsoleException('Quota Search Console temporaneamente esaurita. Riprova più tardi.');
        }

        $url = self::API_ENDPOINT.rawurlencode($property->property_url).'/searchAnalytics/query';

        try {
            $response = Http::acceptJson()
                ->withToken($this->searchConsole->validAccessToken($property->connection))
                ->timeout(20)
                ->retry(2, 250, function (Throwable $exception): bool {
                    return $exception instanceof ConnectionException
                        || ($exception instanceof RequestException && $exception->response->serverError());
                }, throw: false)
                ->post($url, $body);
        } catch (ConnectionException) {
            throw new GoogleSearchConsoleException('Search Console non è raggiungibile in questo momento. Riprova più tardi.');
        }

        if ($response->status() === 401) {
            $property->connection->update([
                'status' => 'error',
                'last_error' => 'Google non ha accettato l’autorizzazione durante la lettura dei report.',
            ]);

            throw new GoogleSearchConsoleException('L’autorizzazione Search Console non è più valida. Collega nuovamente l’account.');
        }

        $errorReason = strtolower((string) (data_get($response->json(), 'error.errors.0.reason')
            ?: data_get($response->json(), 'error.status', '')));

        if ($response->status() === 429 || str_contains($errorReason, 'quota') || str_contains($errorReason, 'ratelimit') || $errorReason === 'resource_exhausted') {
            Cache::put($quotaCooldown, true, now()->addMinutes(10));

            throw new GoogleSearchConsoleException('Quota Search Console temporaneamente esaurita. Riprova più tardi.');
        }

        if ($response->status() === 403) {
            throw new GoogleSearchConsoleException('Search Console ha negato l’accesso ai report. Verifica che l’API sia abilitata e che l’account abbia accesso alla proprietà.');
        }

        if ($response->failed()) {
            throw new GoogleSearchConsoleException('Search Console non ha restituito i dati richiesti. Riprova più tardi.');
        }

        $payload = $response->json();

        if (! is_array($payload) || (isset($payload['rows']) && ! is_array($payload['rows']))) {
            throw new GoogleSearchConsoleException('Search Console ha restituito una risposta dati non valida.');
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload
     *  @return array<int, array{key: string, clicks: int, impressions: int, ctr: float, position: float}>
     */
    private function rows(array $payload): array
    {
        return collect($payload['rows'] ?? [])
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(fn (array $row): array => [
                'key' => (string) ($row['keys'][0] ?? ''),
                'clicks' => max(0, (int) ($row['clicks'] ?? 0)),
                'impressions' => max(0, (int) ($row['impressions'] ?? 0)),
                'ctr' => round((float) ($row['ctr'] ?? 0), 4),
                'position' => round((float) ($row['position'] ?? 0), 1),
            ])
            ->values()
            ->all();
    }
}
