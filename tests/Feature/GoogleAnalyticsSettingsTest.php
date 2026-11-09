<?php

namespace Tests\Feature;

use App\Models\GoogleAnalyticsConnection;
use App\Models\IntegrationSetting;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAnalyticsSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_can_open_google_analytics_settings(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $site = Site::factory()->create();

        $this->get(route('google-analytics.settings.edit', $site))
            ->assertRedirect(route('login'));

        $this->actingAs($user)
            ->get(route('google-analytics.settings.edit', $site))
            ->assertForbidden();

        $admin = User::factory()->administrator()->create();

        config()->set('services.google_analytics.redirect_uri', 'https://dashboard.example.com/integrations/google-analytics/callback');

        $this->actingAs($admin)
            ->get(route('google-analytics.settings.edit', $site))
            ->assertOk()
            ->assertSee(route('sites.edit', $site).'#integrations-heading', false)
            ->assertSee(route('google-search-console.settings.edit', $site), false)
            ->assertSee('Sei già nella schermata giusta')
            ->assertSee('Configura schermata di consenso')
            ->assertSee('Ignora invece')
            ->assertSee('Google Analytics Admin API')
            ->assertSee('Google Analytics Data API')
            ->assertSee('Crea credenziali → ID client OAuth')
            ->assertSee('https://dashboard.example.com/integrations/google-analytics/callback')
            ->assertSee('https://www.googleapis.com/auth/analytics.readonly')
            ->assertSee('Client ID')
            ->assertSee('Client Secret');
    }

    public function test_the_configured_callback_is_shown_without_forcing_localhost(): void
    {
        config()->set('services.google_analytics.redirect_uri', 'http://monitor.test/integrations/google-analytics/callback');

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();

        $this->actingAs($admin)
            ->get(route('google-analytics.settings.edit', $site))
            ->assertOk()
            ->assertSee('http://monitor.test/integrations/google-analytics/callback')
            ->assertSee('Copia URI')
            ->assertDontSee('localhost:8000')
            ->assertDontSee('Questo callback verrà rifiutato da Google');
    }

    public function test_credentials_can_be_saved_with_the_configured_callback(): void
    {
        $this->useDatabaseCredentials();
        config()->set('services.google_analytics.redirect_uri', 'https://monitor.test/integrations/google-analytics/callback');

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();

        $this->actingAs($admin)
            ->put(route('google-analytics.settings.update', $site), [
                'client_id' => 'client-id.apps.googleusercontent.com',
                'client_secret' => 'client-secret',
            ])
            ->assertRedirect(route('sites.edit', $site))
            ->assertSessionHas('status', 'Credenziali Google Analytics salvate. Ora puoi collegare l’account Google.')
            ->assertSessionMissing('integration_error');

        $this->assertDatabaseHas('integration_settings', [
            'provider' => IntegrationSetting::GOOGLE_ANALYTICS,
            'status' => 'configured',
        ]);
    }

    public function test_administrator_can_save_shared_encrypted_credentials_from_a_site(): void
    {
        $this->useDatabaseCredentials();

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();

        $this->actingAs($admin)
            ->put(route('google-analytics.settings.update', $site), [
                'client_id' => 'database-client-id.apps.googleusercontent.com',
                'client_secret' => 'database-client-secret',
            ])
            ->assertRedirect(route('sites.edit', $site))
            ->assertSessionHas('status', 'Credenziali Google Analytics salvate. Ora puoi collegare l’account Google.');

        $setting = IntegrationSetting::query()->sole();

        $this->assertSame(IntegrationSetting::GOOGLE_ANALYTICS, $setting->provider);
        $this->assertSame($admin->id, $setting->configured_by_user_id);
        $this->assertSame('configured', $setting->status);
        $this->assertSame('database-client-id.apps.googleusercontent.com', $setting->configuration['client_id']);
        $this->assertSame('database-client-secret', $setting->configuration['client_secret']);

        $storedConfiguration = (string) DB::table('integration_settings')->value('configuration');

        $this->assertStringNotContainsString('database-client-id', $storedConfiguration);
        $this->assertStringNotContainsString('database-client-secret', $storedConfiguration);

        $otherSite = Site::factory()->create();

        $this->get(route('sites.edit', $otherSite))
            ->assertOk()
            ->assertSee(route('google-analytics.settings.edit', $otherSite), false);

        $this->get(route('google-analytics.settings.edit', $otherSite))
            ->assertOk()
            ->assertSee('database-client-id.apps.googleusercontent.com')
            ->assertDontSee('database-client-secret');
    }

    public function test_leaving_the_secret_blank_preserves_the_saved_secret(): void
    {
        $this->useDatabaseCredentials();

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $setting = $this->createSetting($admin);

        $this->actingAs($admin)
            ->put(route('google-analytics.settings.update', $site), [
                'client_id' => 'saved-client-id.apps.googleusercontent.com',
                'client_secret' => '',
            ])
            ->assertRedirect(route('sites.edit', $site));

        $this->assertSame('saved-client-secret', $setting->fresh()->configuration['client_secret']);
    }

    public function test_environment_credentials_take_precedence_and_make_the_page_read_only(): void
    {
        config()->set('services.google_analytics.client_id', 'environment-client-id');
        config()->set('services.google_analytics.client_secret', 'environment-client-secret');

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();

        $this->actingAs($admin)
            ->get(route('google-analytics.settings.edit', $site))
            ->assertOk()
            ->assertSee('Gestite dall’ambiente')
            ->assertDontSee('environment-client-id')
            ->assertDontSee('environment-client-secret')
            ->assertDontSee('name="client_secret"', false);

        $this->actingAs($admin)
            ->put(route('google-analytics.settings.update', $site), [
                'client_id' => 'attempted-client-id',
                'client_secret' => 'attempted-client-secret',
            ])
            ->assertSessionHas('integration_error');

        $this->assertDatabaseCount('integration_settings', 0);
    }

    public function test_client_id_change_and_configuration_removal_are_blocked_while_connections_exist(): void
    {
        $this->useDatabaseCredentials();

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $setting = $this->createSetting($admin);

        GoogleAnalyticsConnection::query()->create([
            'authorized_by_user_id' => $admin->id,
            'access_token' => 'encrypted-by-the-model',
            'refresh_token' => 'encrypted-by-the-model',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->put(route('google-analytics.settings.update', $site), [
                'client_id' => 'different-client-id.apps.googleusercontent.com',
                'client_secret' => 'replacement-secret',
            ])
            ->assertSessionHasErrors('client_id');

        $this->assertSame('saved-client-id.apps.googleusercontent.com', $setting->fresh()->configuration['client_id']);

        $this->actingAs($admin)
            ->delete(route('google-analytics.settings.destroy', $site))
            ->assertSessionHas('integration_error');

        $this->assertDatabaseCount('integration_settings', 1);
    }

    public function test_database_credentials_are_used_and_marked_verified_by_oauth(): void
    {
        $this->useDatabaseCredentials();
        config()->set('services.google_analytics.redirect_uri', 'https://dashboard.example.com/integrations/google-analytics/callback');

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token',
                'refresh_token' => 'refresh-token',
                'expires_in' => 3600,
                'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
            ]),
        ]);

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $setting = $this->createSetting($admin);

        $authorizationResponse = $this->actingAs($admin)
            ->get(route('google-analytics.connect', $site));
        parse_str((string) parse_url($authorizationResponse->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->assertSame('saved-client-id.apps.googleusercontent.com', $query['client_id']);

        $this->get(route('google-analytics.callback', [
            'state' => $query['state'],
            'code' => 'authorization-code',
        ]))->assertRedirect(route('google-analytics.properties', $site));

        $this->assertSame('verified', $setting->fresh()->status);
        $this->assertNotNull($setting->fresh()->last_tested_at);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://oauth2.googleapis.com/token'
            && $request['client_id'] === 'saved-client-id.apps.googleusercontent.com'
            && $request['client_secret'] === 'saved-client-secret');
    }

    private function useDatabaseCredentials(): void
    {
        config()->set('services.google_analytics.client_id');
        config()->set('services.google_analytics.client_secret');
        config()->set('services.google_analytics.redirect_uri', 'http://localhost:8000/integrations/google-analytics/callback');
        config()->set('services.google_analytics.scope', 'https://www.googleapis.com/auth/analytics.readonly');
    }

    private function createSetting(User $admin): IntegrationSetting
    {
        return IntegrationSetting::query()->create([
            'provider' => IntegrationSetting::GOOGLE_ANALYTICS,
            'configuration' => [
                'client_id' => 'saved-client-id.apps.googleusercontent.com',
                'client_secret' => 'saved-client-secret',
            ],
            'configured_by_user_id' => $admin->id,
            'configured_at' => now(),
            'status' => 'configured',
        ]);
    }
}
