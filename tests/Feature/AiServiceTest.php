<?php

namespace Tests\Feature;

use App\Exceptions\AiServiceException;
use App\Models\AiIntegration;
use App\Services\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_openai_configuration_uses_the_responses_api(): void
    {
        $this->createSetting('openai', 'gpt-6.1-sol', 'synthetic-openai-key');

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => 'Risposta OpenAI',
                    ]],
                ]],
            ]),
        ]);

        $result = app(AiService::class)->generateText('Scrivi una prova.', 500);

        $this->assertSame('Risposta OpenAI', $result);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/responses'
            && $request->hasHeader('Authorization', 'Bearer synthetic-openai-key')
            && $request['model'] === 'gpt-6.1-sol'
            && $request['input'] === 'Scrivi una prova.'
            && $request['max_output_tokens'] === 500);
    }

    public function test_anthropic_configuration_uses_the_messages_api(): void
    {
        $this->createSetting('anthropic', 'claude-sonnet-5-5', 'synthetic-anthropic-key');

        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
                'content' => [[
                    'type' => 'text',
                    'text' => 'Risposta Claude',
                ]],
            ]),
        ]);

        $result = app(AiService::class)->generateText('Scrivi una prova.', 750);

        $this->assertSame('Risposta Claude', $result);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request->hasHeader('x-api-key', 'synthetic-anthropic-key')
            && $request->hasHeader('anthropic-version', '2023-06-01')
            && $request['model'] === 'claude-sonnet-5-5'
            && $request['messages'][0]['content'] === 'Scrivi una prova.'
            && $request['max_tokens'] === 750);
    }

    public function test_missing_configuration_is_reported_without_an_external_request(): void
    {
        Http::fake();

        $this->expectException(AiServiceException::class);
        $this->expectExceptionMessage('L’integrazione AI non è configurata.');

        try {
            app(AiService::class)->generateText('Non inviare.');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_openai_connection_check_uses_the_models_endpoint_and_checks_the_selected_model(): void
    {
        $this->createSetting('openai', 'gpt-5.4-mini', 'synthetic-openai-key');

        Http::fake([
            'https://api.openai.com/v1/models' => Http::response([
                'data' => [
                    ['id' => 'gpt-5.4-mini'],
                ],
            ]),
        ]);

        app(AiService::class)->testConnection(AiIntegration::query()->sole());

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === 'https://api.openai.com/v1/models'
            && $request->hasHeader('Authorization', 'Bearer synthetic-openai-key'));
    }

    public function test_connection_check_rejects_a_model_that_is_not_available_to_the_key(): void
    {
        $this->createSetting('openai', 'gpt-5.4-mini', 'synthetic-openai-key');

        Http::fake([
            'https://api.openai.com/v1/models' => Http::response([
                'data' => [
                    ['id' => 'gpt-6-luna'],
                ],
            ]),
        ]);

        $this->expectException(AiServiceException::class);
        $this->expectExceptionMessage('modello selezionato non è disponibile');

        app(AiService::class)->testConnection(AiIntegration::query()->sole());
    }

    private function createSetting(string $provider, string $model, string $apiKey): void
    {
        AiIntegration::query()->create([
            'name' => 'Test AI',
            'provider' => $provider,
            'model' => $model,
            'api_key' => $apiKey,
            'active_marker' => 'active',
            'configured_at' => now(),
            'status' => 'configured',
        ]);
    }
}
