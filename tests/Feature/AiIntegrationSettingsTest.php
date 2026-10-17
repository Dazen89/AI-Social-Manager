<?php

namespace Tests\Feature;

use App\Models\AiIntegration;
use App\Models\AiUsageRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiIntegrationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_can_open_ai_integrations(): void
    {
        $this->get(route('settings.ai.index'))
            ->assertRedirect(route('login'));

        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('settings.ai.index'))
            ->assertForbidden();

        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->get(route('settings.ai.index'))
            ->assertOk()
            ->assertSee('Integrazioni AI')
            ->assertSee('Nessuna integrazione AI')
            ->assertSee(route('settings.ai.create'), false);
    }

    public function test_creation_page_contains_providers_models_and_setup_guides(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->get(route('settings.ai.create'))
            ->assertOk()
            ->assertSee('Nuova integrazione AI')
            ->assertSee('OpenAI')
            ->assertSee('Anthropic (Claude)')
            ->assertSee('GPT-5.4 Mini')
            ->assertSee('gpt-5.4-mini')
            ->assertSee('non è la API key')
            ->assertSee('https://platform.openai.com/settings/organization/api-keys', false)
            ->assertSee('Dove trovare i parametri');
    }

    public function test_administrator_can_create_an_encrypted_active_ai_integration(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->post(route('settings.ai.store'), [
                'name' => 'OpenAI principale',
                'ai_provider' => 'openai',
                'model' => 'gpt-5.4-mini',
                'api_key' => 'synthetic-openai-secret',
            ])
            ->assertRedirect(route('settings.ai.index'));

        $integration = AiIntegration::query()->sole();

        $this->assertSame('OpenAI principale', $integration->name);
        $this->assertSame('openai', $integration->provider);
        $this->assertSame('gpt-5.4-mini', $integration->model);
        $this->assertSame('synthetic-openai-secret', $integration->api_key);
        $this->assertTrue($integration->is_active);
        $this->assertSame($admin->id, $integration->configured_by_user_id);

        $storedKey = (string) DB::table('ai_integrations')->value('api_key');
        $this->assertStringNotContainsString('synthetic-openai-secret', $storedKey);

        $this->actingAs($admin)
            ->get(route('settings.ai.index'))
            ->assertSee('OpenAI principale')
            ->assertSee('Attiva')
            ->assertDontSee('synthetic-openai-secret');
    }

    public function test_administrator_can_switch_the_only_active_integration(): void
    {
        $admin = User::factory()->administrator()->create();
        $first = $this->createIntegration($admin, isActive: true);
        $second = $this->createIntegration(
            $admin,
            name: 'OpenAI alternativa',
            provider: 'openai',
            model: 'gpt-5.4-mini',
            apiKey: 'synthetic-openai-secret',
        );

        $this->actingAs($admin)
            ->post(route('settings.ai.activate', $second))
            ->assertRedirect(route('settings.ai.index'))
            ->assertSessionHas('status', 'OpenAI alternativa è ora l’integrazione AI attiva.');

        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
        $this->assertSame(1, AiIntegration::query()->where('active_marker', 'active')->count());
    }

    public function test_saved_key_is_preserved_when_provider_does_not_change(): void
    {
        $admin = User::factory()->administrator()->create();
        $integration = $this->createIntegration($admin);

        $this->actingAs($admin)
            ->put(route('settings.ai.update', $integration), [
                'name' => 'Claude rapido',
                'ai_provider' => 'anthropic',
                'model' => 'claude-haiku-5-5',
                'api_key' => '',
            ])
            ->assertRedirect(route('settings.ai.index'));

        $this->assertSame('synthetic-anthropic-secret', $integration->fresh()->api_key);
        $this->assertSame('claude-haiku-5-5', $integration->fresh()->model);
        $this->assertSame('configured', $integration->fresh()->status);
    }

    public function test_changing_provider_requires_a_new_key(): void
    {
        $admin = User::factory()->administrator()->create();
        $integration = $this->createIntegration($admin);

        $this->actingAs($admin)
            ->put(route('settings.ai.update', $integration), [
                'name' => 'OpenAI',
                'ai_provider' => 'openai',
                'model' => 'gpt-6-luna',
                'api_key' => '',
            ])
            ->assertSessionHasErrors('api_key');

        $this->assertSame('anthropic', $integration->fresh()->provider);
    }

    public function test_model_must_belong_to_the_selected_provider(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->post(route('settings.ai.store'), [
                'ai_provider' => 'openai',
                'model' => 'claude-sonnet-5-5',
                'api_key' => 'synthetic-secret',
            ])
            ->assertSessionHasErrors('model');

        $this->assertDatabaseCount('ai_integrations', 0);
    }

    public function test_administrator_can_verify_credentials_and_the_selected_model(): void
    {
        $admin = User::factory()->administrator()->create();
        $integration = $this->createIntegration($admin);

        Http::fake([
            'https://api.anthropic.com/v1/models' => Http::response([
                'data' => [
                    ['id' => 'claude-sonnet-5-5'],
                ],
            ]),
        ]);

        $this->actingAs($admin)
            ->post(route('settings.ai.verify', $integration))
            ->assertRedirect(route('settings.ai.index'));

        $this->assertSame('verified', $integration->fresh()->status);
        $this->assertNotNull($integration->fresh()->last_tested_at);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === 'https://api.anthropic.com/v1/models'
            && $request->hasHeader('x-api-key', 'synthetic-anthropic-secret'));
    }

    public function test_failed_verification_is_visible_and_does_not_expose_the_key(): void
    {
        $admin = User::factory()->administrator()->create();
        $integration = $this->createIntegration($admin);

        Http::fake([
            'https://api.anthropic.com/v1/models' => Http::response(['error' => ['message' => 'Unauthorized']], 401),
        ]);

        $this->actingAs($admin)
            ->post(route('settings.ai.verify', $integration))
            ->assertRedirect(route('settings.ai.index'))
            ->assertSessionHas('integration_error')
            ->assertSessionMissing('synthetic-anthropic-secret');

        $this->assertSame('error', $integration->fresh()->status);

        $this->actingAs($admin)
            ->get(route('settings.ai.index'))
            ->assertSee('Verifica non riuscita')
            ->assertDontSee('synthetic-anthropic-secret');
    }

    public function test_removing_the_active_integration_activates_a_replacement(): void
    {
        $admin = User::factory()->administrator()->create();
        $active = $this->createIntegration($admin, isActive: true);
        $replacement = $this->createIntegration(
            $admin,
            name: 'OpenAI riserva',
            provider: 'openai',
            model: 'gpt-5.4-mini',
            apiKey: 'synthetic-openai-secret',
        );

        $this->actingAs($admin)
            ->delete(route('settings.ai.destroy', $active))
            ->assertRedirect(route('settings.ai.index'));

        $this->assertModelMissing($active);
        $this->assertTrue($replacement->fresh()->is_active);
    }

    public function test_dashboard_links_to_the_summary_and_shows_the_active_integration(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->createIntegration($admin, name: 'Claude principale', isActive: true);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('settings.ai.index'), false)
            ->assertSee('Claude principale')
            ->assertSee('Claude Sonnet 5.5');
    }

    public function test_integration_summary_shows_recorded_token_usage_without_claiming_an_exact_cost(): void
    {
        $admin = User::factory()->administrator()->create();
        $integration = $this->createIntegration(
            $admin,
            provider: 'openai',
            model: 'gpt-5.4-mini',
            apiKey: 'synthetic-openai-key',
            isActive: true,
        );
        AiUsageRecord::query()->create([
            'ai_integration_id' => $integration->id,
            'feature' => 'analytics_insight',
            'provider' => 'openai',
            'model' => 'gpt-5.4-mini',
            'input_tokens' => 1200,
            'output_tokens' => 450,
            'total_tokens' => 1650,
            'occurred_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('settings.ai.index'))
            ->assertOk()
            ->assertSee('Consumi e costi')
            ->assertSee('1.650')
            ->assertSee('Admin API key')
            ->assertSee('https://developers.openai.com/api/reference/resources/admin/subresources/organization/subresources/usage/methods/costs', false);
    }

    private function createIntegration(
        User $admin,
        string $name = 'Claude principale',
        string $provider = 'anthropic',
        string $model = 'claude-sonnet-5-5',
        string $apiKey = 'synthetic-anthropic-secret',
        bool $isActive = false,
    ): AiIntegration {
        return AiIntegration::query()->create([
            'name' => $name,
            'provider' => $provider,
            'model' => $model,
            'api_key' => $apiKey,
            'active_marker' => $isActive ? 'active' : null,
            'configured_by_user_id' => $admin->id,
            'configured_at' => now(),
            'status' => 'configured',
        ]);
    }
}
