<?php

namespace App\Services;

use App\Exceptions\AiServiceException;
use App\Models\AiIntegration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

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
     * Verify the saved credentials and selected model without generating content.
     */
    public function testConnection(AiIntegration $integration): void
    {
        ['provider' => $provider, 'model' => $model, 'apiKey' => $apiKey] = $this->configuration($integration);
        $models = $this->send(
            $provider,
            fn () => $this->modelsRequest($provider, $apiKey)
                ->timeout(30)
                ->retry(2, 250)
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
                ->retry(2, 250)
                ->post((string) config('ai.connections.openai.endpoint'), [
                    'model' => $model,
                    'input' => $prompt,
                    'max_output_tokens' => $maxOutputTokens,
                ])
                ->throw()
                ->json(),
        );

        $text = collect(data_get($payload, 'output', []))
            ->where('type', 'message')
            ->flatMap(fn (array $item): array => data_get($item, 'content', []))
            ->where('type', 'output_text')
            ->pluck('text')
            ->implode('');

        return $this->requireText($text);
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
                ->retry(2, 250)
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
        } catch (ConnectionException|RequestException) {
            throw new AiServiceException("Il servizio {$provider} non ha completato la richiesta.");
        }
    }

    private function requireText(string $text): string
    {
        if (blank($text)) {
            throw new AiServiceException('Il servizio AI ha restituito una risposta senza testo.');
        }

        return $text;
    }
}
