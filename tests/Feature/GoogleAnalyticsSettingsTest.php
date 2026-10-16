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

        $this->get(route('settings.google-analytics.edit'))
            ->assertRedirect(route('login'));

        $this->actingAs($user)
            ->get(route('settings.google-analytics.edit'))
            ->assertForbidden();

        $admin = User::factory()->administrator()->create();

        config()->set('services.google_analytics.redirect_uri', 'https://dashboard.example.com/integrations/google-analytics/callback');

        $this->actingAs($admin)
            ->get(route('settings.google-analytics.edit'))
            ->assertOk()
            ->assertSee('Sei già nella schermata giusta')
            ->assertSee('Configura schermata di consenso')
            ->assertSee('Ignora invece')
            ->assertSee('Google Analytics Admin API')
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

        $this->actingAs($admin)
            ->get(route('settings.google-analytics.edit'))
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

        $this->actingAs($admin)
            ->put(route('settings.google-analytics.update'), [
                'client_id' => 'client-id.apps.googleusercontent.com',
                'client_secret' => 'client-secret',
            ])
            ->assertRedirect(route('settings.google-analytics.edit'))
            ->assertSessionHas('status', 'Credenziali Google Analytics salvate in forma cifrata. Completa un collegamento per verificarle.')
            ->assertSessionMissing('integration_error');

        $this->assertDatabaseHas('integration_settings', [
            'provider' => IntegrationSetting::GOOGLE_ANALYTICS,
            'status' => 'configured',
        ]);
    }

    public function test_administrator_can_save_encrypted_credentials_from_the_dashboard(): void
    {
        $this->useDatabaseCredentials();

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();

        $this->actingAs($admin)
            ->put(route('settings.google-analytics.update'), [
                'client_id' => 'database-client-id.apps.googleusercontent.com',
                'client_secret' => 'database-client-secret',
                'return_site_id' => $site->id,
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

        $this->actingAs($admin)
            ->get(route('settings.google-analytics.edit'))
            ->assertOk()
            ->assertSee('database-client-id.apps.googleusercontent.com')
            ->assertDontSee('database-client-secret');
    }

    public function test_leaving_the_secret_blank_preserves_the_saved_secret(): void
    {
        $this->useDatabaseCredentials();

        $admin = User::factory()->administrator()->create();
        $setting = $this->createSetting($admin);

        $this->actingAs($admin)
            ->put(route('settings.google-analytics.update'), [
                'client_id' => 'saved-client-id.apps.googleusercontent.com',
                'client_secret' => '',
            ])
            ->assertRedirect(route('settings.google-analytics.edit'));

        $this->assertSame('saved-client-secret', $setting->fresh()->configuration['client_secret']);
    }

    public function test_environment_credentials_take_precedence_and_make_the_page_read_only(): void
    {
        config()->set('services.google_analytics.client_id', 'environment-client-id');
        config()->set('services.google_analytics.client_secret', 'environment-client-secret');

        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->get(route('settings.google-analytics.edit'))
            ->assertOk()
            ->assertSee('Gestite dall’ambiente')
            ->assertDontSee('environment-client-id')
            ->assertDontSee('environment-client-secret')
            ->assertDontSee('name="client_secret"', false);

        $this->actingAs($admin)
            ->put(route('settings.google-analytics.update'), [
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
        $setting = $this->createSetting($admin);

        GoogleAnalyticsConnection::query()->create([
            'authorized_by_user_id' => $admin->id,
            'access_token' => 'encrypted-by-the-model',
            'refresh_token' => 'encrypted-by-the-model',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->put(route('settings.google-analytics.update'), [
                'client_id' => 'different-client-id.apps.googleusercontent.com',
                'client_secret' => 'replacement-secret',
            ])
            ->assertSessionHasErrors('client_id');

        $this->assertSame('saved-client-id.apps.googleusercontent.com', $setting->fresh()->configuration['client_id']);

        $this->actingAs($admin)
            ->delete(route('settings.google-analytics.destroy'))
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
