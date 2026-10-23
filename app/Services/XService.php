<?php

namespace App\Services;

use App\Exceptions\XApiException;
use App\Models\IntegrationSetting;
use App\Models\XConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class XService
{
    private const CREATE_POST_ENDPOINT = 'https://api.x.com/2/tweets';

    private const AUTHORIZATION_ENDPOINT = 'https://x.com/i/oauth2/authorize';

    private const TOKEN_ENDPOINT = 'https://api.x.com/2/oauth2/token';

    private const REVOCATION_ENDPOINT = 'https://api.x.com/2/oauth2/revoke';

    private const CURRENT_USER_ENDPOINT = 'https://api.x.com/2/users/me';

    public function credentialsAreConfigured(): bool
    {
        $credentials = $this->credentials();

        return filled($credentials['client_id'] ?? null)
            && filled($credentials['client_secret'] ?? null)
            && filled($this->redirectUri());
    }

    public function environmentCredentialsAreConfigured(): bool
    {
        return filled(config('services.x.client_id'))
            && filled(config('services.x.client_secret'));
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
        return (string) (config('services.x.redirect_uri') ?: route('x.callback'));
    }

    /**
     * @return array<int, string>
     */
    public function scopes(): array
    {
        return array_values(array_filter((array) config('services.x.scopes', []), 'is_string'));
    }

    public function authorizationUrl(string $state, string $codeChallenge): string
    {
        $credentials = $this->credentials();

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $credentials['client_id'] ?? null,
            'redirect_uri' => $this->redirectUri(),
            'scope' => implode(' ', $this->scopes()),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);

        return self::AUTHORIZATION_ENDPOINT.'?'.$query;
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int, scope: string}
     */
    public function exchangeAuthorizationCode(string $code, string $codeVerifier): array
    {
        $response = $this->oauthRequest()
            ->post(self::TOKEN_ENDPOINT, [
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->redirectUri(),
                'code_verifier' => $codeVerifier,
            ]);

        if ($response->failed()) {
            $this->markDatabaseCredentialsError('X non ha accettato le credenziali OAuth configurate.');

            throw new XApiException('X non ha accettato il codice di autorizzazione. Controlla Client ID, Client Secret e callback, poi riprova.');
        }

        $tokens = $response->json();

        if (! is_array($tokens) || blank($tokens['access_token'] ?? null) || blank($tokens['refresh_token'] ?? null)) {
            $this->markDatabaseCredentialsError('X non ha restituito un accesso OAuth rinnovabile.');

            throw new XApiException('X non ha restituito un accesso permanente. Verifica che lo scope offline.access sia abilitato e riprova.');
        }

        $scope = (string) ($tokens['scope'] ?? '');
        $grantedScopes = preg_split('/\s+/', trim($scope)) ?: [];
        $missingScopes = array_diff($this->scopes(), $grantedScopes);

        if ($missingScopes !== []) {
            $this->markDatabaseCredentialsError('X non ha concesso tutti i permessi necessari alla pubblicazione automatica.');

            throw new XApiException('X non ha concesso tutti i permessi richiesti. Riprova accettando l’accesso in lettura, scrittura e offline.');
        }

        $this->markDatabaseCredentialsVerified();

        return [
            'access_token' => (string) $tokens['access_token'],
            'refresh_token' => (string) $tokens['refresh_token'],
            'expires_in' => max(1, (int) ($tokens['expires_in'] ?? 7200)),
            'scope' => $scope,
        ];
    }

    /**
     * @return array{x_user_id: string, username: string, display_name: string, profile_image_url: string|null}
     */
    public function authenticatedAccount(string $accessToken): array
    {
        $response = Http::acceptJson()
            ->withToken($accessToken)
            ->timeout(15)
            ->retry(2, 250, throw: false)
            ->get(self::CURRENT_USER_ENDPOINT, [
                'user.fields' => 'profile_image_url',
            ]);

        $account = $response->json('data');

        if ($response->failed() || ! is_array($account) || blank($account['id'] ?? null) || blank($account['username'] ?? null)) {
            throw new XApiException('Non è stato possibile verificare l’account X autorizzato. Riprova il collegamento.');
        }

        return [
            'x_user_id' => (string) $account['id'],
            'username' => (string) $account['username'],
            'display_name' => (string) ($account['name'] ?? $account['username']),
            'profile_image_url' => filled($account['profile_image_url'] ?? null)
                ? (string) $account['profile_image_url']
                : null,
        ];
    }

    public function validAccessToken(XConnection $connection): string
    {
        if ($connection->token_expires_at?->isAfter(now()->addMinute())) {
            return $connection->access_token;
        }

        if (blank($connection->refresh_token)) {
            throw new XApiException('L’autorizzazione X è scaduta. Collega nuovamente l’account.');
        }

        $response = $this->oauthRequest()->post(self::TOKEN_ENDPOINT, [
            'refresh_token' => $connection->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed() || blank($response->json('access_token'))) {
            $connection->update([
                'status' => 'error',
                'last_error' => 'L’autorizzazione X non può essere rinnovata.',
            ]);

            throw new XApiException('L’autorizzazione X non può essere rinnovata. Collega nuovamente l’account.');
        }

        $connection->update([
            'access_token' => (string) $response->json('access_token'),
            'refresh_token' => (string) ($response->json('refresh_token') ?: $connection->refresh_token),
            'token_expires_at' => now()->addSeconds(max(1, (int) $response->json('expires_in', 7200))),
            'scopes' => filled($response->json('scope'))
                ? preg_split('/\s+/', trim((string) $response->json('scope')))
                : $connection->scopes,
            'status' => 'active',
            'last_error' => null,
        ]);

        return $connection->access_token;
    }

    public function createPost(XConnection $connection, string $text): string
    {
        $response = Http::withToken($this->validAccessToken($connection))
            ->acceptJson()->timeout(15)
            ->post(self::CREATE_POST_ENDPOINT, ['text' => $text]);

        $id = $response->json('data.id');

        if ($response->failed() || ! is_string($id) || $id === '') {
            throw new XApiException('X non ha confermato la pubblicazione del post.');
        }

        return $id;
    }

    /** @return array<string, int> */
    public function postPublicMetrics(XConnection $connection, string $postId): array
    {
        $response = Http::withToken($this->validAccessToken($connection))
            ->acceptJson()->timeout(15)
            ->get(self::CREATE_POST_ENDPOINT.'/'.$postId, ['tweet.fields' => 'public_metrics']);
        $metrics = $response->json('data.public_metrics');

        if ($response->failed() || ! is_array($metrics)) {
            throw new XApiException('Le metriche del post X non sono disponibili.');
        }

        $allowed = ['impression_count', 'like_count', 'reply_count', 'repost_count', 'quote_count', 'bookmark_count'];
        $result = [];

        foreach ($allowed as $field) {
            if (isset($metrics[$field]) && filter_var($metrics[$field], FILTER_VALIDATE_INT) !== false) {
                $result[$field] = max(0, (int) $metrics[$field]);
            }
        }

        return $result;
    }

    public function revoke(XConnection $connection): bool
    {
        $token = $connection->refresh_token ?: $connection->access_token;

        if (blank($token) || ! $this->credentialsAreConfigured()) {
            return false;
        }

        return $this->oauthRequest()
            ->post(self::REVOCATION_ENDPOINT, ['token' => $token])
            ->successful();
    }

    private function oauthRequest(): PendingRequest
    {
        $credentials = $this->credentials();

        return Http::asForm()
            ->acceptJson()
            ->withBasicAuth(
                (string) ($credentials['client_id'] ?? ''),
                (string) ($credentials['client_secret'] ?? ''),
            )
            ->timeout(15);
    }

    /**
     * @return array{client_id?: string, client_secret?: string}
     */
    private function credentials(): array
    {
        if ($this->environmentCredentialsAreConfigured()) {
            return [
                'client_id' => (string) config('services.x.client_id'),
                'client_secret' => (string) config('services.x.client_secret'),
            ];
        }

        $configuration = $this->databaseSetting()?->configuration;

        return is_array($configuration) ? $configuration : [];
    }

    private function databaseSetting(): ?IntegrationSetting
    {
        return IntegrationSetting::query()
            ->where('provider', IntegrationSetting::X)
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
