<?php

namespace Tests\Feature;

use App\Models\GoogleSearchConsoleConnection;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleSearchConsoleOAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_connection_requires_configured_oauth_credentials(): void
    {
        config()->set('services.google_search_console.client_id');
        config()->set('services.google_search_console.client_secret');

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();

        $this->actingAs($admin)
            ->get(route('google-search-console.connect', $site))
            ->assertRedirect(route('sites.edit', $site))
            ->assertSessionHas('integration_error', 'Prima di collegare Search Console configura le credenziali OAuth dell’installazione.');
    }

    public function test_administrator_can_authorize_and_select_a_search_console_property(): void
    {
        $this->configureOAuth();
        $this->fakeGoogleApis();

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create([
            'host' => 'www.example.test',
            'base_url' => 'https://www.example.test',
        ]);

        $authorization = $this->actingAs($admin)
            ->get(route('google-search-console.connect', $site));

        $authorization->assertRedirectContains('https://accounts.google.com/o/oauth2/v2/auth');
        parse_str((string) parse_url($authorization->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->assertSame('https://www.googleapis.com/auth/webmasters.readonly', $query['scope']);
        $this->assertSame('https://dashboard.example.com/integrations/google-search-console/callback', $query['redirect_uri']);

        $this->get(route('google-search-console.callback', [
            'state' => $query['state'],
            'code' => 'authorization-code',
        ]))->assertRedirect(route('google-search-console.properties', $site));

        $connection = GoogleSearchConsoleConnection::query()->sole();
        $this->assertSame(['https://www.googleapis.com/auth/webmasters.readonly'], $connection->scopes);

        $this->get(route('google-search-console.properties', $site))
            ->assertOk()
            ->assertSee('sc-domain:example.test')
            ->assertSee('Compatibile con il sito')
            ->assertSee('https://other.example.test/', false)
            ->assertDontSee('sc-domain:unverified.example.test');

        $this->post(route('google-search-console.store', $site), [
            'property' => 'sc-domain:example.test',
        ])->assertRedirect(route('sites.edit', $site))
            ->assertSessionHas('status', 'Google Search Console collegata e proprietà verificata per il dominio del sito.');

        $this->assertDatabaseHas('google_search_console_properties', [
            'site_id' => $site->id,
            'google_search_console_connection_id' => $connection->id,
            'property_url' => 'sc-domain:example.test',
            'property_type' => 'domain',
            'permission_level' => 'siteOwner',
        ]);

        $this->actingAs($admin)
            ->get(route('sites.edit', $site))
            ->assertOk()
            ->assertSee('Google Search Console')
            ->assertSee('Collegata')
            ->assertSee('sc-domain:example.test');
    }

    public function test_property_selection_is_verified_against_google_response(): void
    {
        $this->configureOAuth();
        Http::fake([
            'https://www.googleapis.com/webmasters/v3/sites' => Http::response([
                'siteEntry' => [[
                    'siteUrl' => 'sc-domain:example.test',
                    'permissionLevel' => 'siteOwner',
                ]],
            ]),
        ]);

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $connection = $this->connection($admin);

        $this->withSession([
            'google_search_console_selection' => [
                'site_id' => $site->id,
                'connection_id' => $connection->id,
            ],
        ])->actingAs($admin)
            ->post(route('google-search-console.store', $site), [
                'property' => 'sc-domain:not-accessible.test',
            ])
            ->assertSessionHasErrors([
                'property' => 'La proprietà non è accessibile con l’account Google autorizzato.',
            ]);

        $this->assertDatabaseCount('google_search_console_properties', 0);
    }

    public function test_disconnect_removes_property_and_revokes_unused_connection(): void
    {
        $this->configureOAuth();
        Http::fake([
            'https://oauth2.googleapis.com/revoke' => Http::response([], 200),
        ]);

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $connection = $this->connection($admin);
        $site->googleSearchConsoleProperty()->create([
            'google_search_console_connection_id' => $connection->id,
            'property_url' => 'sc-domain:example.test',
            'property_type' => 'domain',
            'permission_level' => 'siteOwner',
            'last_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('google-search-console.destroy', $site))
            ->assertRedirect(route('sites.edit', $site));

        $this->assertDatabaseCount('google_search_console_properties', 0);
        $this->assertDatabaseCount('google_search_console_connections', 0);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://oauth2.googleapis.com/revoke'
            && $request['token'] === 'refresh-token');
    }

    public function test_expired_access_token_is_refreshed_before_loading_properties(): void
    {
        $this->configureOAuth();
        Http::fake(function (Request $request) {
            if ($request->url() === 'https://oauth2.googleapis.com/token') {
                return Http::response([
                    'access_token' => 'refreshed-access-token',
                    'expires_in' => 3600,
                ]);
            }

            if ($request->url() === 'https://www.googleapis.com/webmasters/v3/sites') {
                return Http::response(['siteEntry' => []]);
            }

            return Http::response([], 404);
        });

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $connection = $this->connection($admin);
        $connection->update(['token_expires_at' => now()->subMinute()]);

        $this->withSession([
            'google_search_console_selection' => [
                'site_id' => $site->id,
                'connection_id' => $connection->id,
            ],
        ])->actingAs($admin)
            ->get(route('google-search-console.properties', $site))
            ->assertOk();

        $this->assertSame('refreshed-access-token', $connection->fresh()->access_token);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://www.googleapis.com/webmasters/v3/sites'
            && $request->hasHeader('Authorization', 'Bearer refreshed-access-token'));
    }

    private function configureOAuth(): void
    {
        config()->set('services.google_search_console.client_id', 'test-client-id');
        config()->set('services.google_search_console.client_secret', 'test-client-secret');
        config()->set('services.google_search_console.redirect_uri', 'https://dashboard.example.com/integrations/google-search-console/callback');
        config()->set('services.google_search_console.scope', 'https://www.googleapis.com/auth/webmasters.readonly');
    }

    private function fakeGoogleApis(): void
    {
        Http::fake(function (Request $request) {
            if ($request->url() === 'https://oauth2.googleapis.com/token') {
                return Http::response([
                    'access_token' => 'access-token',
                    'refresh_token' => 'refresh-token',
                    'expires_in' => 3600,
                    'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
                ]);
            }

            if ($request->url() === 'https://www.googleapis.com/webmasters/v3/sites') {
                return Http::response([
                    'siteEntry' => [
                        [
                            'siteUrl' => 'https://other.example.test/',
                            'permissionLevel' => 'siteRestrictedUser',
                        ],
                        [
                            'siteUrl' => 'sc-domain:example.test',
                            'permissionLevel' => 'siteOwner',
                        ],
                        [
                            'siteUrl' => 'sc-domain:unverified.example.test',
                            'permissionLevel' => 'siteUnverifiedUser',
                        ],
                    ],
                ]);
            }

            return Http::response([], 404);
        });
    }

    private function connection(User $admin): GoogleSearchConsoleConnection
    {
        return GoogleSearchConsoleConnection::query()->create([
            'authorized_by_user_id' => $admin->id,
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->addHour(),
            'scopes' => ['https://www.googleapis.com/auth/webmasters.readonly'],
            'status' => 'active',
        ]);
    }
}
