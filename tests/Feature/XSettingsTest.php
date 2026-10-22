<?php

namespace Tests\Feature;

use App\Models\IntegrationSetting;
use App\Models\User;
use App\Models\XConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class XSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_can_open_x_settings(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->get(route('settings.x.edit'))
            ->assertRedirect(route('login'));

        $this->actingAs($user)
            ->get(route('settings.x.edit'))
            ->assertForbidden();

        $admin = User::factory()->administrator()->create();
        $this->useDatabaseCredentials();

        $this->actingAs($admin)
            ->get(route('settings.x.edit'))
            ->assertOk()
            ->assertSee('Pubblicazione su X')
            ->assertSee('Describe all of your use cases of X’s data and API')
            ->assertSee('This is a private administrative application')
            ->assertSee('Da recuperare nella Developer Console X')
            ->assertSee('Web App, Automated App or Bot')
            ->assertSee('Non inserire API Key, API Key Secret o Bearer Token')
            ->assertSee('https://monitor.example.com/integrations/x/callback')
            ->assertSee('copyWithFallback')
            ->assertSee("document.execCommand('copy')", false)
            ->assertSee('tweet.write')
            ->assertSee('offline.access')
            ->assertSee('Collega account X');
    }

    public function test_administrator_can_save_encrypted_x_credentials(): void
    {
        $this->useDatabaseCredentials();
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->put(route('settings.x.update'), [
                'client_id' => 'x-client-id',
                'client_secret' => 'x-client-secret',
            ])
            ->assertRedirect(route('settings.x.edit'))
            ->assertSessionHas('status');

        $setting = IntegrationSetting::query()->sole();

        $this->assertSame(IntegrationSetting::X, $setting->provider);
        $this->assertSame('x-client-id', $setting->configuration['client_id']);
        $this->assertSame('x-client-secret', $setting->configuration['client_secret']);

        $storedConfiguration = (string) DB::table('integration_settings')->value('configuration');

        $this->assertStringNotContainsString('x-client-id', $storedConfiguration);
        $this->assertStringNotContainsString('x-client-secret', $storedConfiguration);

        $this->actingAs($admin)
            ->get(route('settings.x.edit'))
            ->assertOk()
            ->assertSee('x-client-id')
            ->assertDontSee('x-client-secret');
    }

    public function test_leaving_secret_blank_preserves_the_saved_secret(): void
    {
        $this->useDatabaseCredentials();
        $admin = User::factory()->administrator()->create();
        $setting = $this->createSetting($admin);

        $this->actingAs($admin)
            ->put(route('settings.x.update'), [
                'client_id' => 'saved-x-client-id',
                'client_secret' => '',
            ])
            ->assertRedirect(route('settings.x.edit'));

        $this->assertSame('saved-x-client-secret', $setting->fresh()->configuration['client_secret']);
    }

    public function test_environment_credentials_take_precedence_and_hide_values(): void
    {
        config()->set('services.x.client_id', 'environment-x-client-id');
        config()->set('services.x.client_secret', 'environment-x-client-secret');
        config()->set('services.x.redirect_uri', 'https://monitor.example.com/integrations/x/callback');
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->get(route('settings.x.edit'))
            ->assertOk()
            ->assertSee('Gestite dall’ambiente')
            ->assertDontSee('environment-x-client-id')
            ->assertDontSee('environment-x-client-secret')
            ->assertDontSee('name="client_secret"', false);

        $this->actingAs($admin)
            ->put(route('settings.x.update'), [
                'client_id' => 'attempted-client-id',
                'client_secret' => 'attempted-client-secret',
            ])
            ->assertSessionHas('integration_error');

        $this->assertDatabaseCount('integration_settings', 0);
    }

    public function test_client_change_and_credential_removal_are_blocked_while_an_account_is_connected(): void
    {
        $this->useDatabaseCredentials();
        $admin = User::factory()->administrator()->create();
        $setting = $this->createSetting($admin);
        $this->createConnection($admin);

        $this->actingAs($admin)
            ->put(route('settings.x.update'), [
                'client_id' => 'different-client-id',
                'client_secret' => 'replacement-secret',
            ])
            ->assertSessionHasErrors('client_id');

        $this->assertSame('saved-x-client-id', $setting->fresh()->configuration['client_id']);

        $this->actingAs($admin)
            ->delete(route('settings.x.destroy'))
            ->assertSessionHas('integration_error');

        $this->assertDatabaseCount('integration_settings', 1);
    }

    public function test_database_credentials_cannot_be_introduced_while_a_token_from_an_unknown_client_exists(): void
    {
        $this->useDatabaseCredentials();
        $admin = User::factory()->administrator()->create();
        $this->createConnection($admin);

        $this->actingAs($admin)
            ->put(route('settings.x.update'), [
                'client_id' => 'new-client-id',
                'client_secret' => 'new-client-secret',
            ])
            ->assertSessionHasErrors('client_id');

        $this->assertDatabaseCount('integration_settings', 0);
    }

    private function useDatabaseCredentials(): void
    {
        config()->set('services.x.client_id');
        config()->set('services.x.client_secret');
        config()->set('services.x.redirect_uri', 'https://monitor.example.com/integrations/x/callback');
        config()->set('services.x.scopes', ['tweet.read', 'tweet.write', 'users.read', 'offline.access']);
    }

    private function createSetting(User $admin): IntegrationSetting
    {
        return IntegrationSetting::query()->create([
            'provider' => IntegrationSetting::X,
            'configuration' => [
                'client_id' => 'saved-x-client-id',
                'client_secret' => 'saved-x-client-secret',
            ],
            'configured_by_user_id' => $admin->id,
            'configured_at' => now(),
            'status' => 'configured',
        ]);
    }

    private function createConnection(User $admin): XConnection
    {
        return XConnection::query()->create([
            'authorized_by_user_id' => $admin->id,
            'x_user_id' => '123456789',
            'username' => 'example_account',
            'display_name' => 'Example Account',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'status' => 'active',
        ]);
    }
}
