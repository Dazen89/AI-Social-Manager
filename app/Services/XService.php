<?php

namespace App\Services;

use App\Exceptions\XApiException;
use App\Exceptions\XPostRejectedException;
use App\Models\IntegrationSetting;
use App\Models\XConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class XService
{
    private const POSTS_ENDPOINT = 'https://api.x.com/2/tweets';

    public const POST_LOOKUP_LIMIT = 100;

    private const UPLOAD_MEDIA_ENDPOINT = 'https://api.x.com/2/media/upload';

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
        $missingScopes = array_diff($this->scopes(), $grantedScopes, ['media.write']);

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

    public function uploadImage(XConnection $connection, string $bytes): string
    {
        $response = Http::withToken($this->validAccessToken($connection))
            ->acceptJson()->timeout(15)
            ->post(self::UPLOAD_MEDIA_ENDPOINT, [
                'media' => base64_encode($bytes),
                'media_category' => 'tweet_image',
            ]);

        $id = $response->json('data.id');
        $state = $response->json('data.processing_info.state');

        if (! $response->successful() || ! is_string($id) || ! ctype_digit($id)
            || ($state !== null && $state !== 'succeeded')) {
            throw new XApiException('X non ha confermato il caricamento dell’immagine.');
        }

        return $id;
    }

    /** @param list<string> $mediaIds */
    public function createPost(XConnection $connection, string $text, array $mediaIds = []): string
    {
        try {
            $token = $this->validAccessToken($connection);
        } catch (XApiException $exception) {
            throw new XPostRejectedException($exception->getMessage());
        } catch (ConnectionException) {
            throw new XPostRejectedException('Connessione con X interrotta durante il rinnovo dell’autorizzazione. Puoi confermare un nuovo invio.');
        }

        $payload = ['text' => $text];

        if ($mediaIds !== []) {
            $payload['media'] = ['media_ids' => $mediaIds];
        }

        $response = Http::withToken($token)
            ->acceptJson()->timeout(15)
            ->post(self::POSTS_ENDPOINT, $payload);

        $status = $response->status();
        $reason = match ($status) {
            400, 422 => 'Verifica il testo, il link e le immagini del post.',
            401 => 'Ricollega l’account X per rinnovare l’autorizzazione.',
            402 => 'Controlla credito e fatturazione nella Developer Console di X.',
            403 => 'Controlla i permessi dell’app, le restrizioni dell’account e se sul profilo esiste già un post identico.',
            404 => 'Una risorsa richiesta da X non è disponibile. Verifica gli allegati e il collegamento dell’app.',
            429 => 'È stato raggiunto un limite di richieste o di utilizzo. Controlla la Developer Console di X.',
            default => null,
        };

        if ($reason !== null) {
            throw new XPostRejectedException("X ha rifiutato la pubblicazione (HTTP {$status}). {$reason}", $status);
        }

        $id = $response->json('data.id');

        if (! $response->successful() || ! is_string($id) || $id === '') {
            throw new XApiException('X non ha confermato la pubblicazione del post.', $status);
        }

        return $id;
    }

    /**
     * @param  list<string>  $postIds
     * @return array<int|string, array<string, int>>
     */
    public function postMetrics(XConnection $connection, array $postIds): array
    {
        $request = Http::withToken($this->validAccessToken($connection))->acceptJson()->timeout(15);
        $params = ['ids' => implode(',', $postIds), 'tweet.fields' => 'public_metrics,non_public_metrics'];
        $response = $request->get(self::POSTS_ENDPOINT, $params);

        if (in_array($response->status(), [400, 403], true)) {
            $params['tweet.fields'] = 'public_metrics';
            $response = $request->get(self::POSTS_ENDPOINT, $params);
        }

        if (! $response->successful()) {
            throw new XApiException('Le metriche dei post X non sono disponibili.', $response->status());
        }

        $posts = $response->json('data');
        $result = [];

        foreach (is_array($posts) ? $posts : [] as $post) {
            $id = data_get($post, 'id');

            if (! is_string($id) || ! in_array($id, $postIds, true)) {
                continue;
            }

            $metrics = data_get($post, 'public_metrics');
            $metrics = is_array($metrics) ? $metrics : [];
            $metrics['url_link_clicks'] = data_get($post, 'non_public_metrics.url_link_clicks');
            $metrics = $this->normalizePostMetrics($metrics);

            if ($metrics !== []) {
                $result[$id] = $metrics;
            }
        }

        return $result;
    }

    /** @return array<string, int> */
    private function normalizePostMetrics(array $metrics): array
    {
        $metrics['repost_count'] = $metrics['retweet_count'] ?? $metrics['repost_count'] ?? null;
        $allowed = ['impression_count', 'like_count', 'reply_count', 'repost_count', 'quote_count', 'bookmark_count', 'url_link_clicks'];
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
