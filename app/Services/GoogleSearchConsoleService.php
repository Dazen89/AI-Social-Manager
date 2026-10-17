<?php

namespace App\Services;

use App\Exceptions\GoogleSearchConsoleException;
use App\Models\GoogleSearchConsoleConnection;
use App\Models\IntegrationSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleSearchConsoleService
{
    private const AUTHORIZATION_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    private const REVOCATION_ENDPOINT = 'https://oauth2.googleapis.com/revoke';

    private const SITES_ENDPOINT = 'https://www.googleapis.com/webmasters/v3/sites';

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
        return filled(config('services.google_search_console.client_id'))
            && filled(config('services.google_search_console.client_secret'));
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
        return (string) (config('services.google_search_console.redirect_uri') ?: route('google-search-console.callback'));
    }

    public function authorizationUrl(string $state): string
    {
        $credentials = $this->credentials();

        $query = http_build_query([
            'client_id' => $credentials['client_id'] ?? null,
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => config('services.google_search_console.scope'),
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

            throw new GoogleSearchConsoleException('Google non ha accettato il codice di autorizzazione. Controlla Client ID, Client Secret e URI di reindirizzamento, poi riprova.');
        }

        $tokens = $response->json();

        if (! is_array($tokens) || blank($tokens['access_token'] ?? null) || blank($tokens['refresh_token'] ?? null)) {
            $this->markDatabaseCredentialsError('Google non ha restituito un accesso OAuth permanente.');

            throw new GoogleSearchConsoleException('Google non ha restituito un accesso permanente. Riprova e conferma nuovamente il consenso.');
        }

        $grantedScopes = explode(' ', (string) ($tokens['scope'] ?? ''));

        if (! in_array(config('services.google_search_console.scope'), $grantedScopes, true)) {
            $this->markDatabaseCredentialsError('Il permesso webmasters.readonly non è stato concesso.');

            throw new GoogleSearchConsoleException('Google non ha concesso il permesso di sola lettura Search Console. Riprova e accetta l’autorizzazione richiesta.');
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
     * @return array<int, array{site_url: string, permission_level: string, property_type: string, host: string|null, matches_host: bool}>
     */
    public function sites(GoogleSearchConsoleConnection $connection, string $registeredHost): array
    {
        $payload = $this->get($connection, self::SITES_ENDPOINT);

        return collect(Arr::wrap($payload['siteEntry'] ?? []))
            ->filter(fn (mixed $site): bool => is_array($site)
                && filled($site['siteUrl'] ?? null)
                && in_array($site['permissionLevel'] ?? null, [
                    'siteOwner',
                    'siteFullUser',
                    'siteRestrictedUser',
                ], true))
            ->map(function (array $site) use ($registeredHost): array {
                $siteUrl = (string) $site['siteUrl'];
                $isDomainProperty = Str::startsWith($siteUrl, 'sc-domain:');
                $propertyHost = $isDomainProperty
                    ? Str::lower(Str::after($siteUrl, 'sc-domain:'))
                    : Str::lower((string) parse_url($siteUrl, PHP_URL_HOST));
                $host = Str::lower($registeredHost);
                $matchesHost = $isDomainProperty
                    ? $host === $propertyHost || Str::endsWith($host, '.'.$propertyHost)
                    : $host === $propertyHost;

                return [
                    'site_url' => $siteUrl,
                    'permission_level' => (string) ($site['permissionLevel'] ?? 'unknown'),
                    'property_type' => $isDomainProperty ? 'domain' : 'url_prefix',
                    'host' => filled($propertyHost) ? $propertyHost : null,
                    'matches_host' => $matchesHost,
                ];
            })
            ->sortBy([
                [fn (array $site): int => $site['matches_host'] ? 0 : 1, 'asc'],
                [fn (array $site): string => $site['site_url'], 'asc'],
            ])
            ->values()
            ->all();
    }

    public function revoke(GoogleSearchConsoleConnection $connection): void
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

    public function validAccessToken(GoogleSearchConsoleConnection $connection): string
    {
        if ($connection->token_expires_at?->isAfter(now()->addMinute())) {
            return $connection->access_token;
        }

        if (blank($connection->refresh_token)) {
            throw new GoogleSearchConsoleException('L’autorizzazione Google è scaduta. Collega nuovamente Search Console.');
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

            throw new GoogleSearchConsoleException('L’autorizzazione Google non può essere rinnovata. Collega nuovamente Search Console.');
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
     * @return array<string, mixed>
     */
    private function get(GoogleSearchConsoleConnection $connection, string $url): array
    {
        $response = Http::acceptJson()
            ->withToken($this->validAccessToken($connection))
            ->timeout(15)
            ->retry(2, 250, throw: false)
            ->get($url);

        if ($response->failed()) {
            $connection->update([
                'status' => 'error',
                'last_error' => 'Google Search Console non è raggiungibile o l’autorizzazione non è più valida.',
            ]);

            throw new GoogleSearchConsoleException('Search Console non è raggiungibile o l’autorizzazione non è più valida. Controlla che la Search Console API sia abilitata e riprova.');
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new GoogleSearchConsoleException('Search Console ha restituito una risposta non valida. Riprova più tardi.');
        }

        return $payload;
    }

    /**
     * @return array{client_id?: string, client_secret?: string}
     */
    private function credentials(): array
    {
        if ($this->environmentCredentialsAreConfigured()) {
            return [
                'client_id' => (string) config('services.google_search_console.client_id'),
                'client_secret' => (string) config('services.google_search_console.client_secret'),
            ];
        }

        $configuration = $this->databaseSetting()?->configuration;

        return is_array($configuration) ? $configuration : [];
    }

    private function databaseSetting(): ?IntegrationSetting
    {
        return IntegrationSetting::query()
            ->where('provider', IntegrationSetting::GOOGLE_SEARCH_CONSOLE)
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
