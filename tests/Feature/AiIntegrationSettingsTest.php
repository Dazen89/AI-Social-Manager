<?php

namespace Tests\Feature;

use App\Models\IntegrationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AiIntegrationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_can_open_ai_integration_settings(): void
    {
        $this->get(route('settings.ai.edit'))
            ->assertRedirect(route('login'));

        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('settings.ai.edit'))
            ->assertForbidden();

        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->get(route('settings.ai.edit'))
            ->assertOk()
            ->assertSee('Integrazioni AI')
            ->assertSee('OpenAI')
            ->assertSee('Anthropic (Claude)')
            ->assertSee('GPT-5.4 Mini')
            ->assertSee('gpt-5.4-mini')
            ->assertSee('Dove trovare i parametri')
            ->assertSee('https://developers.openai.com/api/docs/quickstart', false)
            ->assertSee('https://platform.claude.com/docs/en/get-api-key', false);
    }

    public function test_administrator_can_save_an_encrypted_ai_configuration(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->put(route('settings.ai.update'), [
                'ai_provider' => 'openai',
                'model' => 'gpt-5.4-mini',
                'api_key' => 'synthetic-openai-secret',
            ])
            ->assertRedirect(route('settings.ai.edit'))
            ->assertSessionHas('status', 'Integrazione AI salvata. La API key è memorizzata in forma cifrata.');

        $setting = IntegrationSetting::query()->sole();

        $this->assertSame(IntegrationSetting::AI, $setting->provider);
        $this->assertSame($admin->id, $setting->configured_by_user_id);
        $this->assertSame('openai', $setting->configuration['provider']);
        $this->assertSame('gpt-5.4-mini', $setting->configuration['model']);
        $this->assertSame('synthetic-openai-secret', $setting->configuration['api_key']);

        $storedConfiguration = (string) DB::table('integration_settings')->value('configuration');

        $this->assertStringNotContainsString('synthetic-openai-secret', $storedConfiguration);

        $this->actingAs($admin)
            ->get(route('settings.ai.edit'))
            ->assertOk()
            ->assertSee('gpt-5.4-mini')
            ->assertDontSee('synthetic-openai-secret');
    }

    public function test_saved_key_is_preserved_when_provider_does_not_change(): void
    {
        $admin = User::factory()->administrator()->create();
        $setting = $this->createSetting($admin);

        $this->actingAs($admin)
            ->put(route('settings.ai.update'), [
                'ai_provider' => 'anthropic',
                'model' => 'claude-haiku-5-5',
                'api_key' => '',
            ])
            ->assertRedirect(route('settings.ai.edit'));

        $this->assertSame('synthetic-anthropic-secret', $setting->fresh()->configuration['api_key']);
        $this->assertSame('claude-haiku-5-5', $setting->fresh()->configuration['model']);
    }

    public function test_changing_provider_requires_a_new_key(): void
    {
        $admin = User::factory()->administrator()->create();
        $setting = $this->createSetting($admin);

        $this->actingAs($admin)
            ->put(route('settings.ai.update'), [
                'ai_provider' => 'openai',
                'model' => 'gpt-6-luna',
                'api_key' => '',
            ])
            ->assertSessionHasErrors('api_key');

        $this->assertSame('anthropic', $setting->fresh()->configuration['provider']);
    }

    public function test_model_must_belong_to_the_selected_provider(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->put(route('settings.ai.update'), [
                'ai_provider' => 'openai',
                'model' => 'claude-sonnet-5-5',
                'api_key' => 'synthetic-secret',
            ])
            ->assertSessionHasErrors('model');

        $this->assertDatabaseCount('integration_settings', 0);
    }

    public function test_administrator_can_remove_the_ai_configuration(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->createSetting($admin);

        $this->actingAs($admin)
            ->delete(route('settings.ai.destroy'))
            ->assertRedirect(route('settings.ai.edit'))
            ->assertSessionHas('status', 'Configurazione AI rimossa.');

        $this->assertDatabaseCount('integration_settings', 0);
    }

    public function test_dashboard_links_to_ai_integrations_and_shows_the_selected_model(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->createSetting($admin);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('settings.ai.edit'), false)
            ->assertSee('Anthropic (Claude)')
            ->assertSee('Claude Sonnet 5.5');
    }

    private function createSetting(User $admin): IntegrationSetting
    {
        return IntegrationSetting::query()->create([
            'provider' => IntegrationSetting::AI,
            'configuration' => [
                'provider' => 'anthropic',
                'model' => 'claude-sonnet-5-5',
                'api_key' => 'synthetic-anthropic-secret',
            ],
            'configured_by_user_id' => $admin->id,
            'configured_at' => now(),
            'status' => 'configured',
        ]);
    }
}
