<?php

namespace App\Services;

use App\Exceptions\GoogleAnalyticsException;
use App\Models\GoogleAnalyticsConnection;
use App\Models\IntegrationSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleAnalyticsService
{
    private const AUTHORIZATION_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    private const REVOCATION_ENDPOINT = 'https://oauth2.googleapis.com/revoke';

    private const ADMIN_API_ENDPOINT = 'https://analyticsadmin.googleapis.com/v1beta';

    public function credentialsAreConfigured(): bool
    {
        return $this->oauthClientCredentialsAreConfigured()
            && filled($this->redirectUri());
    }

    public function oauthClientCredentialsAreConfigured(): bool
    {
        $credentials = $this->credentials();

        return filled($credentials['client_id'] ?? null)
            && filled($credentials['client_secret'] ?? null);
    }

    public function environmentCredentialsAreConfigured(): bool
    {
        return filled(config('services.google_analytics.client_id'))
            && filled(config('services.google_analytics.client_secret'));
    }

    public function credentialsSource(): ?string
    {
        if ($this->environmentCredentialsAreConfigured()) {
            return 'environment';
        }

        return $this->databaseSetting() ? 'database' : null;
    }

    public function redirectUri(): string
    {
        return (string) (config('services.google_analytics.redirect_uri') ?: route('google-analytics.callback'));
    }

    public function authorizationUrl(string $state): string
    {
        $credentials = $this->credentials();

        $query = http_build_query([
            'client_id' => $credentials['client_id'] ?? null,
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => config('services.google_analytics.scope'),
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'prompt' => 'consent select_account',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);

        return self::AUTHORIZATION_ENDPOINT.'?'.$query;
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int, scope: string|null}
     */
    public function exchangeAuthorizationCode(string $code): array
    {
        $credentials = $this->credentials();
        $response = Http::asForm()
            ->acceptJson()
            ->timeout(15)
            ->post(self::TOKEN_ENDPOINT, [
                'client_id' => $credentials['client_id'] ?? null,
                'client_secret' => $credentials['client_secret'] ?? null,
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->redirectUri(),
            ]);

        if ($response->failed()) {
            $this->markDatabaseCredentialsError('Google non ha accettato le credenziali OAuth configurate.');

            throw new GoogleAnalyticsException('Google non ha accettato il codice di autorizzazione. Riprova il collegamento.');
        }

        $tokens = $response->json();

        if (! is_array($tokens) || blank($tokens['access_token'] ?? null) || blank($tokens['refresh_token'] ?? null)) {
            $this->markDatabaseCredentialsError('Google non ha restituito un accesso OAuth permanente.');

            throw new GoogleAnalyticsException('Google non ha restituito un accesso permanente. Riprova e conferma nuovamente il consenso.');
        }

        $grantedScopes = explode(' ', (string) ($tokens['scope'] ?? ''));

        if (! in_array(config('services.google_analytics.scope'), $grantedScopes, true)) {
            $this->markDatabaseCredentialsError('Il permesso analytics.readonly non è stato concesso.');

            throw new GoogleAnalyticsException('Google non ha concesso il permesso di lettura Analytics richiesto. Riprova e accetta l’autorizzazione.');
        }

        $this->markDatabaseCredentialsVerified();

        return [
            'access_token' => (string) $tokens['access_token'],
            'refresh_token' => (string) $tokens['refresh_token'],
            'expires_in' => max(1, (int) ($tokens['expires_in'] ?? 3600)),
            'scope' => isset($tokens['scope']) ? (string) $tokens['scope'] : null,
        ];
    }

    /**
     * @return array<int, array{account_id: string, account_name: string, properties: array<int, array{property_id: string, property_name: string}>}>
     */
    public function accountSummaries(GoogleAnalyticsConnection $connection): array
    {
        $summaries = [];
        $pageToken = null;

        for ($page = 0; $page < 10; $page++) {
            $query = array_filter([
                'pageSize' => 200,
                'pageToken' => $pageToken,
            ]);

            $payload = $this->get($connection, self::ADMIN_API_ENDPOINT.'/accountSummaries', $query);

            foreach (Arr::wrap($payload['accountSummaries'] ?? []) as $account) {
                if (! is_array($account)) {
                    continue;
                }

                $properties = collect(Arr::wrap($account['propertySummaries'] ?? []))
                    ->filter(fn (mixed $property): bool => is_array($property) && filled($property['property'] ?? null))
                    ->map(fn (array $property): array => [
                        'property_id' => Str::after((string) $property['property'], 'properties/'),
                        'property_name' => (string) ($property['displayName'] ?? $property['property']),
                    ])
                    ->values()
                    ->all();

                if ($properties === []) {
                    continue;
                }

                $summaries[] = [
                    'account_id' => Str::after((string) ($account['account'] ?? ''), 'accounts/'),
                    'account_name' => (string) ($account['displayName'] ?? $account['account'] ?? 'Account Google Analytics'),
                    'properties' => $properties,
                ];
            }

            $pageToken = $payload['nextPageToken'] ?? null;

            if (blank($pageToken)) {
                break;
            }
        }

        return $summaries;
    }

    /**
     * @return array{data_stream_id: string, measurement_id: string|null}|null
     */
    public function matchingWebStream(GoogleAnalyticsConnection $connection, string $propertyId, string $host): ?array
    {
        $payload = $this->get(
            $connection,
            self::ADMIN_API_ENDPOINT.'/properties/'.$propertyId.'/dataStreams',
            ['pageSize' => 200],
        );

        $streams = collect(Arr::wrap($payload['dataStreams'] ?? []))
            ->filter(fn (mixed $stream): bool => is_array($stream) && ($stream['type'] ?? null) === 'WEB_DATA_STREAM')
            ->map(function (array $stream): array {
                $defaultUri = (string) data_get($stream, 'webStreamData.defaultUri', '');

                return [
                    'host' => Str::lower((string) parse_url($defaultUri, PHP_URL_HOST)),
                    'data_stream_id' => Str::after((string) ($stream['name'] ?? ''), '/dataStreams/'),
                    'measurement_id' => data_get($stream, 'webStreamData.measurementId'),
                ];
            });

        $match = $streams->first(fn (array $stream): bool => $stream['host'] === Str::lower($host));

        if (! is_array($match) || blank($match['data_stream_id'])) {
            return null;
        }

        return [
            'data_stream_id' => $match['data_stream_id'],
            'measurement_id' => filled($match['measurement_id']) ? (string) $match['measurement_id'] : null,
        ];
    }

    public function revoke(GoogleAnalyticsConnection $connection): void
    {
        $token = $connection->refresh_token ?: $connection->access_token;

        if (blank($token)) {
            return;
        }

        Http::asForm()
            ->acceptJson()
            ->timeout(10)
            ->post(self::REVOCATION_ENDPOINT, ['token' => $token]);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(GoogleAnalyticsConnection $connection, string $url, array $query = []): array
    {
        $response = Http::acceptJson()
            ->withToken($this->validAccessToken($connection))
            ->timeout(15)
            ->retry(2, 250, throw: false)
            ->get($url, $query);

        if ($response->failed()) {
            $connection->update([
                'status' => 'error',
                'last_error' => 'Google Analytics non è raggiungibile o l’autorizzazione non è più valida.',
            ]);

            throw new GoogleAnalyticsException('Google Analytics non è raggiungibile o l’autorizzazione non è più valida. Riprova il collegamento.');
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new GoogleAnalyticsException('Google Analytics ha restituito una risposta non valida. Riprova più tardi.');
        }

        return $payload;
    }

    public function validAccessToken(GoogleAnalyticsConnection $connection): string
    {
        if ($connection->token_expires_at?->isAfter(now()->addMinute())) {
            return $connection->access_token;
        }

        if (blank($connection->refresh_token)) {
            throw new GoogleAnalyticsException('L’autorizzazione Google è scaduta. Collega nuovamente l’account.');
        }

        $credentials = $this->credentials();
        $response = Http::asForm()
            ->acceptJson()
            ->timeout(15)
            ->post(self::TOKEN_ENDPOINT, [
                'client_id' => $credentials['client_id'] ?? null,
                'client_secret' => $credentials['client_secret'] ?? null,
                'refresh_token' => $connection->refresh_token,
                'grant_type' => 'refresh_token',
            ]);

        if ($response->failed() || blank($response->json('access_token'))) {
            $connection->update([
                'status' => 'error',
                'last_error' => 'L’autorizzazione Google non può essere rinnovata.',
            ]);

            throw new GoogleAnalyticsException('L’autorizzazione Google non può essere rinnovata. Collega nuovamente l’account.');
        }

        $connection->update([
            'access_token' => (string) $response->json('access_token'),
            'token_expires_at' => now()->addSeconds(max(1, (int) $response->json('expires_in', 3600))),
            'status' => 'active',
            'last_error' => null,
        ]);

        return $connection->access_token;
    }

    /**
     * @return array{client_id?: string, client_secret?: string}
     */
    private function credentials(): array
    {
        if ($this->environmentCredentialsAreConfigured()) {
            return [
                'client_id' => (string) config('services.google_analytics.client_id'),
                'client_secret' => (string) config('services.google_analytics.client_secret'),
            ];
        }

        $configuration = $this->databaseSetting()?->configuration;

        return is_array($configuration) ? $configuration : [];
    }

    private function databaseSetting(): ?IntegrationSetting
    {
        return IntegrationSetting::query()
            ->where('provider', IntegrationSetting::GOOGLE_ANALYTICS)
            ->first();
    }

    private function markDatabaseCredentialsVerified(): void
    {
        if ($this->credentialsSource() !== 'database') {
            return;
        }

        $this->databaseSetting()?->update([
            'status' => 'verified',
            'last_tested_at' => now(),
            'last_error' => null,
        ]);
    }

    private function markDatabaseCredentialsError(string $message): void
    {
        if ($this->credentialsSource() !== 'database') {
            return;
        }

        $this->databaseSetting()?->update([
            'status' => 'error',
            'last_tested_at' => now(),
            'last_error' => $message,
        ]);
    }
}
