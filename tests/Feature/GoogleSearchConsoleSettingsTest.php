<?php

namespace Tests\Feature;

use App\Models\GoogleSearchConsoleConnection;
use App\Models\IntegrationSetting;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GoogleSearchConsoleSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_can_open_search_console_settings(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $site = Site::factory()->create();

        $this->get(route('google-search-console.settings.edit', $site))
            ->assertRedirect(route('login'));

        $this->actingAs($user)
            ->get(route('google-search-console.settings.edit', $site))
            ->assertForbidden();

        $admin = User::factory()->administrator()->create();
        $this->configureDatabaseCredentials();

        $this->actingAs($admin)
            ->get(route('google-search-console.settings.edit', $site))
            ->assertOk()
            ->assertSee(route('sites.edit', $site).'#integrations-heading', false)
            ->assertSee(route('google-analytics.settings.edit', $site), false)
            ->assertSee('Google Search Console')
            ->assertSee('Hai già configurato Google Analytics?')
            ->assertSee('Google Search Console API')
            ->assertSee('https://www.googleapis.com/auth/webmasters.readonly')
            ->assertSee('https://dashboard.example.com/integrations/google-search-console/callback')
            ->assertSee('Client ID')
            ->assertSee('Client Secret')
            ->assertDontSee('GOOGLE_SEARCH_CONSOLE_CLIENT_SECRET');
    }

    public function test_administrator_can_save_encrypted_search_console_credentials(): void
    {
        $this->configureDatabaseCredentials();

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();

        $this->actingAs($admin)
            ->put(route('google-search-console.settings.update', $site), [
                'client_id' => 'search-client.apps.googleusercontent.com',
                'client_secret' => 'search-client-secret',
            ])
            ->assertRedirect(route('sites.edit', $site))
            ->assertSessionHas('status', 'Credenziali Search Console salvate. Ora puoi collegare l’account Google.');

        $setting = IntegrationSetting::query()->sole();

        $this->assertSame(IntegrationSetting::GOOGLE_SEARCH_CONSOLE, $setting->provider);
        $this->assertSame('search-client.apps.googleusercontent.com', $setting->configuration['client_id']);
        $this->assertSame('search-client-secret', $setting->configuration['client_secret']);

        $storedConfiguration = (string) DB::table('integration_settings')->value('configuration');

        $this->assertStringNotContainsString('search-client.apps.googleusercontent.com', $storedConfiguration);
        $this->assertStringNotContainsString('search-client-secret', $storedConfiguration);
    }

    public function test_client_id_change_is_blocked_while_search_console_connections_exist(): void
    {
        $this->configureDatabaseCredentials();

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $setting = IntegrationSetting::query()->create([
            'provider' => IntegrationSetting::GOOGLE_SEARCH_CONSOLE,
            'configuration' => [
                'client_id' => 'saved-client.apps.googleusercontent.com',
                'client_secret' => 'saved-secret',
            ],
            'configured_by_user_id' => $admin->id,
            'status' => 'configured',
        ]);
        GoogleSearchConsoleConnection::query()->create([
            'authorized_by_user_id' => $admin->id,
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->put(route('google-search-console.settings.update', $site), [
                'client_id' => 'different-client.apps.googleusercontent.com',
                'client_secret' => 'replacement-secret',
            ])
            ->assertSessionHasErrors('client_id');

        $this->get(route('google-search-console.settings.edit', $site))
            ->assertOk()
            ->assertSee('scollega prima Search Console da tutti i siti')
            ->assertDontSee('Rimuovi credenziali OAuth');

        $this->delete(route('google-search-console.settings.destroy', $site))
            ->assertSessionHas('integration_error');

        $this->assertSame('saved-client.apps.googleusercontent.com', $setting->fresh()->configuration['client_id']);
    }

    public function test_saved_credentials_can_be_reached_corrected_and_removed_before_connection(): void
    {
        $this->configureDatabaseCredentials();

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $setting = IntegrationSetting::query()->create([
            'provider' => IntegrationSetting::GOOGLE_SEARCH_CONSOLE,
            'configuration' => [
                'client_id' => 'wrong-client.apps.googleusercontent.com',
                'client_secret' => 'wrong-secret',
            ],
            'configured_by_user_id' => $admin->id,
            'status' => 'configured',
        ]);

        $this->actingAs($admin)
            ->get(route('sites.edit', $site))
            ->assertOk()
            ->assertSee('Collega Search Console')
            ->assertSee('Credenziali OAuth e guida')
            ->assertSee(route('google-search-console.settings.edit', $site), false);

        $this->get(route('google-search-console.settings.edit', $site))
            ->assertOk()
            ->assertSee('wrong-client.apps.googleusercontent.com')
            ->assertSee('Rimuovi credenziali OAuth')
            ->assertDontSee('wrong-secret');

        $this->put(route('google-search-console.settings.update', $site), [
            'client_id' => 'correct-client.apps.googleusercontent.com',
            'client_secret' => 'correct-secret',
        ])->assertRedirect(route('sites.edit', $site));

        $this->assertSame('correct-client.apps.googleusercontent.com', $setting->fresh()->configuration['client_id']);
        $this->assertSame('correct-secret', $setting->fresh()->configuration['client_secret']);

        $this->delete(route('google-search-console.settings.destroy', $site))
            ->assertRedirect(route('google-search-console.settings.edit', $site));

        $this->assertDatabaseMissing('integration_settings', ['provider' => IntegrationSetting::GOOGLE_SEARCH_CONSOLE]);
    }

    private function configureDatabaseCredentials(): void
    {
        config()->set('services.google_search_console.client_id');
        config()->set('services.google_search_console.client_secret');
        config()->set('services.google_search_console.redirect_uri', 'https://dashboard.example.com/integrations/google-search-console/callback');
        config()->set('services.google_search_console.scope', 'https://www.googleapis.com/auth/webmasters.readonly');
    }
}
