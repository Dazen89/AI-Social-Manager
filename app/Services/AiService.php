<?php

namespace App\Services;

use App\Exceptions\AiServiceException;
use App\Models\AiIntegration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

class AiService
{
    /**
     * Generate a text response with the AI integration selected by an administrator.
     */
    public function generateText(string $prompt, int $maxOutputTokens = 1024): string
    {
        ['provider' => $provider, 'model' => $model, 'apiKey' => $apiKey] = $this->configuration();

        $maxOutputTokens = max(1, min($maxOutputTokens, 32768));

        return match ($provider) {
            'openai' => $this->generateWithOpenAi($apiKey, $model, $prompt, $maxOutputTokens),
            'anthropic' => $this->generateWithAnthropic($apiKey, $model, $prompt, $maxOutputTokens),
            default => throw new AiServiceException('Il fornitore AI configurato non è supportato.'),
        };
    }

    /**
     * Generate a JSON object constrained by the supplied schema and return provider usage metadata.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $schema
     * @return array{data: array<string, mixed>, usage: array{input_tokens: int, output_tokens: int, total_tokens: int}, response_id: ?string}
     */
    public function generateStructured(
        AiIntegration $integration,
        string $instructions,
        array $input,
        array $schema,
        int $maxOutputTokens = 4096,
    ): array {
        ['provider' => $provider, 'model' => $model, 'apiKey' => $apiKey] = $this->configuration($integration);
        $maxOutputTokens = max(1, min($maxOutputTokens, 32768));

        try {
            $encodedInput = json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $encodedSchema = json_encode($schema, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            throw new AiServiceException('I dati da analizzare non possono essere preparati in modo sicuro.');
        }

        $payload = match ($provider) {
            'openai' => $this->send(
                'openai',
                fn () => Http::acceptJson()
                    ->withToken($apiKey)
                    ->timeout(60)
                    ->retry(2, 250, fn (Throwable $exception): bool => $this->shouldRetry($exception))
                    ->post((string) config('ai.connections.openai.endpoint'), [
                        'model' => $model,
                        'input' => [
                            [
                                'role' => 'system',
                                'content' => [['type' => 'input_text', 'text' => $instructions]],
                            ],
                            [
                                'role' => 'user',
                                'content' => [['type' => 'input_text', 'text' => $encodedInput]],
                            ],
                        ],
                        'text' => [
                            'format' => [
                                'type' => 'json_schema',
                                'name' => 'analytics_insight',
                                'strict' => true,
                                'schema' => $schema,
                            ],
                        ],
                        'max_output_tokens' => $maxOutputTokens,
                    ])
                    ->throw()
                    ->json(),
            ),
            'anthropic' => $this->send(
                'anthropic',
                fn () => Http::acceptJson()
                    ->withHeaders([
                        'x-api-key' => $apiKey,
                        'anthropic-version' => (string) config('ai.connections.anthropic.api_version'),
                    ])
                    ->timeout(60)
                    ->retry(2, 250, fn (Throwable $exception): bool => $this->shouldRetry($exception))
                    ->post((string) config('ai.connections.anthropic.endpoint'), [
                        'model' => $model,
                        'max_tokens' => $maxOutputTokens,
                        'system' => $instructions,
                        'messages' => [[
                            'role' => 'user',
                            'content' => "Restituisci esclusivamente un oggetto JSON conforme a questo JSON Schema:\n{$encodedSchema}\n\nDati da analizzare:\n{$encodedInput}",
                        ]],
                    ])
                    ->throw()
                    ->json(),
            ),
            default => throw new AiServiceException('Il fornitore AI configurato non è supportato.'),
        };

        $text = $provider === 'openai'
            ? $this->openAiOutputText($payload)
            : collect(data_get($payload, 'content', []))
                ->where('type', 'text')
                ->pluck('text')
                ->implode('');

        return [
            'data' => $this->decodeStructuredText($this->requireText($text)),
            'usage' => $this->usage($provider, $payload),
            'response_id' => filled($payload['id'] ?? null) ? (string) $payload['id'] : null,
        ];
    }

    /**
     * Verify the saved credentials and selected model without generating content.
     */
    public function testConnection(AiIntegration $integration): void
    {
        ['provider' => $provider, 'model' => $model, 'apiKey' => $apiKey] = $this->configuration($integration);
        $models = $this->send(
            $provider,
            fn () => $this->modelsRequest($provider, $apiKey)
                ->timeout(30)
                ->retry(2, 250, fn (Throwable $exception): bool => $this->shouldRetry($exception))
                ->get((string) config("ai.connections.{$provider}.models_endpoint"))
                ->throw()
                ->json(),
        );

        $availableModels = collect(data_get($models, 'data', []))
            ->pluck('id')
            ->filter()
            ->values();

        if ($availableModels->isEmpty()) {
            throw new AiServiceException("Il servizio {$provider} non ha restituito modelli verificabili.");
        }

        if (! $availableModels->contains($model)) {
            throw new AiServiceException('La API key è stata accettata, ma il modello selezionato non è disponibile per questo account.');
        }
    }

    private function generateWithOpenAi(string $apiKey, string $model, string $prompt, int $maxOutputTokens): string
    {
        $payload = $this->send(
            'openai',
            fn () => Http::acceptJson()
                ->withToken($apiKey)
                ->timeout(60)
                ->retry(2, 250, fn (Throwable $exception): bool => $this->shouldRetry($exception))
                ->post((string) config('ai.connections.openai.endpoint'), [
                    'model' => $model,
                    'input' => $prompt,
                    'max_output_tokens' => $maxOutputTokens,
                ])
                ->throw()
                ->json(),
        );

        return $this->requireText($this->openAiOutputText($payload));
    }

    private function generateWithAnthropic(string $apiKey, string $model, string $prompt, int $maxOutputTokens): string
    {
        $payload = $this->send(
            'anthropic',
            fn () => Http::acceptJson()
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => (string) config('ai.connections.anthropic.api_version'),
                ])
                ->timeout(60)
                ->retry(2, 250, fn (Throwable $exception): bool => $this->shouldRetry($exception))
                ->post((string) config('ai.connections.anthropic.endpoint'), [
                    'model' => $model,
                    'max_tokens' => $maxOutputTokens,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ])
                ->throw()
                ->json(),
        );

        $text = collect(data_get($payload, 'content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        return $this->requireText($text);
    }

    /**
     * @return array{provider: string, model: string, apiKey: string}
     */
    private function configuration(?AiIntegration $integration = null): array
    {
        $integration ??= AiIntegration::query()
            ->where('active_marker', 'active')
            ->first();

        if (! $integration) {
            throw new AiServiceException('L’integrazione AI non è configurata.');
        }

        $provider = (string) $integration->provider;
        $model = (string) $integration->model;
        $apiKey = (string) $integration->api_key;

        if (blank($provider) || blank($model) || blank($apiKey)) {
            throw new AiServiceException('La configurazione AI è incompleta.');
        }

        return compact('provider', 'model', 'apiKey');
    }

    private function modelsRequest(string $provider, string $apiKey): PendingRequest
    {
        $request = Http::acceptJson();

        return $provider === 'openai'
            ? $request->withToken($apiKey)
            : $request->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => (string) config('ai.connections.anthropic.api_version'),
            ]);
    }

    /**
     * @param  callable(): array<string, mixed>  $request
     * @return array<string, mixed>
     */
    private function send(string $provider, callable $request): array
    {
        try {
            return $request();
        } catch (ConnectionException) {
            throw new AiServiceException($this->providerName($provider).' non è raggiungibile oppure ha superato il tempo massimo di risposta. Riprova tra poco.');
        } catch (RequestException $exception) {
            throw $this->requestFailure($provider, $exception);
        }
    }

    private function requestFailure(string $provider, RequestException $exception): AiServiceException
    {
        $name = $this->providerName($provider);
        $status = $exception->response->status();
        $payload = $exception->response->json();
        $code = (string) (data_get($payload, 'error.code') ?: data_get($payload, 'error.type'));

        if (in_array($code, [
            'credit_balance_exhausted',
            'insufficient_quota',
            'organization_spend_limit_exceeded',
            'project_spend_limit_exceeded',
            'organization_usage_limit_exceeded',
        ], true)) {
            return new AiServiceException("{$name} ha accettato le credenziali, ma il progetto non dispone di credito API sufficiente oppure ha raggiunto un limite di spesa. Controlla Billing e Limits nel pannello del provider.");
        }

        if ($status === 401) {
            return new AiServiceException("{$name} non ha accettato la API key. Aggiorna la chiave e verifica nuovamente l’integrazione.");
        }

        if ($status === 403) {
            return new AiServiceException("La API key non ha i permessi necessari per usare questo modello su {$name}. Controlla progetto, ruoli e restrizioni della chiave.");
        }

        if ($status === 404) {
            return new AiServiceException("Il modello configurato non è disponibile tramite {$name}. Verifica nuovamente l’integrazione o scegli un altro modello.");
        }

        if ($status === 429) {
            return new AiServiceException("{$name} ha applicato un limite temporaneo di richieste o token. Attendi qualche minuto e riprova.");
        }

        if ($status >= 500) {
            return new AiServiceException("{$name} è temporaneamente indisponibile (errore {$status}). Riprova tra poco.");
        }

        $providerMessage = $this->safeProviderMessage((string) data_get($payload, 'error.message'));

        if (in_array($status, [400, 422], true) && filled($providerMessage)) {
            return new AiServiceException("{$name} non ha accettato la richiesta: {$providerMessage}");
        }

        return new AiServiceException("{$name} non ha completato la richiesta (errore {$status}".($code !== '' ? ", codice {$code}" : '').').');
    }

    private function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if (! $exception instanceof RequestException) {
            return false;
        }

        $status = $exception->response->status();

        if ($status >= 500) {
            return true;
        }

        if ($status !== 429) {
            return false;
        }

        $payload = $exception->response->json();
        $code = (string) (data_get($payload, 'error.code') ?: data_get($payload, 'error.type'));

        return $code === '' || in_array($code, ['rate_limit_exceeded', 'rate_limit_error', 'slow_down'], true);
    }

    private function providerName(string $provider): string
    {
        return (string) config("ai.providers.{$provider}.name", ucfirst($provider));
    }

    private function safeProviderMessage(string $message): string
    {
        $message = preg_replace('/\bsk-[A-Za-z0-9_-]{8,}\b/', '[chiave rimossa]', $message) ?? '';
        $message = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $message) ?? '';

        return Str::limit(trim($message), 500);
    }

    private function requireText(string $text): string
    {
        if (blank($text)) {
            throw new AiServiceException('Il servizio AI ha restituito una risposta senza testo.');
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function openAiOutputText(array $payload): string
    {
        return collect(data_get($payload, 'output', []))
            ->where('type', 'message')
            ->flatMap(fn (array $item): array => data_get($item, 'content', []))
            ->where('type', 'output_text')
            ->pluck('text')
            ->implode('');
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeStructuredText(string $text): array
    {
        $text = trim($text);

        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text) ?? $text;
        }

        try {
            $decoded = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new AiServiceException('Il servizio AI non ha restituito un’analisi strutturata valida.');
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new AiServiceException('Il servizio AI non ha restituito un oggetto di analisi valido.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{input_tokens: int, output_tokens: int, total_tokens: int}
     */
    private function usage(string $provider, array $payload): array
    {
        $input = (int) data_get($payload, 'usage.input_tokens', 0);
        $output = (int) data_get($payload, 'usage.output_tokens', 0);
        $total = $provider === 'openai'
            ? (int) data_get($payload, 'usage.total_tokens', $input + $output)
            : $input + $output;

        return [
            'input_tokens' => max(0, $input),
            'output_tokens' => max(0, $output),
            'total_tokens' => max(0, $total),
        ];
    }
}
