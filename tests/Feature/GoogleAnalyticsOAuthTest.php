<?php

namespace Tests\Feature;

use App\Models\GoogleAnalyticsConnection;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAnalyticsOAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_oauth_credentials_produce_actionable_feedback(): void
    {
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();

        config()->set('services.google_analytics.client_id');
        config()->set('services.google_analytics.client_secret');

        $this->actingAs($admin)
            ->get(route('google-analytics.connect', $site))
            ->assertRedirect(route('sites.edit', $site))
            ->assertSessionHas(
                'integration_error',
                'Prima di collegare Google Analytics configura le credenziali OAuth dell’installazione.',
            );

        Http::assertNothingSent();
    }

    public function test_connect_redirects_to_google_with_read_only_offline_access_and_state(): void
    {
        $this->configureOAuth();

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();

        $response = $this->actingAs($admin)->get(route('google-analytics.connect', $site));
        $location = $response->headers->get('Location');

        $this->assertIsString($location);
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertSame('test-client-id', $query['client_id']);
        $this->assertSame('https://dashboard.example.com/integrations/google-analytics/callback', $query['redirect_uri']);
        $this->assertSame('https://www.googleapis.com/auth/analytics.readonly', $query['scope']);
        $this->assertSame('offline', $query['access_type']);
        $this->assertSame('code', $query['response_type']);
        $this->assertNotEmpty($query['state']);
        $this->assertSame(
            hash('sha256', $query['state']),
            session('google_analytics_oauth.state_hash'),
        );
    }

    public function test_callback_rejects_an_invalid_oauth_state(): void
    {
        $this->configureOAuth();
        Http::fake();

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();

        $this->actingAs($admin)->get(route('google-analytics.connect', $site));

        $this->get(route('google-analytics.callback', [
            'state' => 'tampered-state',
            'code' => 'authorization-code',
        ]))->assertRedirect(route('sites.index'));

        $this->assertDatabaseCount('google_analytics_connections', 0);
        Http::assertNothingSent();
    }

    public function test_administrator_can_authorize_google_and_select_a_verified_property(): void
    {
        $this->configureOAuth();
        $this->fakeGoogleApis();

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create([
            'base_url' => 'https://example.test',
            'host' => 'example.test',
        ]);

        $authorizationResponse = $this->actingAs($admin)->get(route('google-analytics.connect', $site));
        parse_str((string) parse_url($authorizationResponse->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->get(route('google-analytics.callback', [
            'state' => $query['state'],
            'code' => 'authorization-code',
        ]))->assertRedirect(route('google-analytics.properties', $site));

        $connection = GoogleAnalyticsConnection::query()->sole();

        $this->assertSame($admin->id, $connection->authorized_by_user_id);
        $this->assertSame('access-token', $connection->access_token);
        $this->assertSame('refresh-token', $connection->refresh_token);
        $this->assertNotSame(
            'refresh-token',
            DB::table('google_analytics_connections')->value('refresh_token'),
        );

        $this->get(route('google-analytics.properties', $site))
            ->assertOk()
            ->assertSee('Example Analytics Account')
            ->assertSee('Example Web Property');

        $this->post(route('google-analytics.store', $site), [
            'property' => '123456:987654',
        ])->assertRedirect(route('sites.edit', $site));

        $this->assertDatabaseHas('google_analytics_properties', [
            'site_id' => $site->id,
            'google_analytics_connection_id' => $connection->id,
            'account_id' => '123456',
            'account_name' => 'Example Analytics Account',
            'property_id' => '987654',
            'property_name' => 'Example Web Property',
            'data_stream_id' => '456789',
            'measurement_id' => 'G-ABC1234567',
        ]);

        $this->get(route('sites.edit', $site))
            ->assertOk()
            ->assertSee('Collegato')
            ->assertSee('Example Web Property')
            ->assertSee('G-ABC1234567');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://oauth2.googleapis.com/token'
            && $request['grant_type'] === 'authorization_code');
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/v1beta/accountSummaries')
            && $request->hasHeader('Authorization', 'Bearer access-token'));
    }

    public function test_property_selection_is_checked_against_google_response(): void
    {
        $this->configureOAuth();
        $this->fakeGoogleApis();

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $connection = $this->connection($admin);

        $this->withSession([
            'google_analytics_selection' => [
                'site_id' => $site->id,
                'connection_id' => $connection->id,
            ],
        ])->actingAs($admin)
            ->post(route('google-analytics.store', $site), [
                'property' => '123456:111111',
            ])
            ->assertSessionHasErrors([
                'property' => 'La proprietà non è accessibile con l’account Google autorizzato.',
            ]);

        $this->assertDatabaseCount('google_analytics_properties', 0);
    }

    public function test_disconnect_removes_local_tokens_and_revokes_an_unused_connection(): void
    {
        $this->configureOAuth();
        Http::fake([
            'https://oauth2.googleapis.com/revoke' => Http::response([], 200),
        ]);

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $connection = $this->connection($admin);

        $site->googleAnalyticsProperty()->create([
            'google_analytics_connection_id' => $connection->id,
            'account_id' => '123456',
            'property_id' => '987654',
            'last_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('google-analytics.destroy', $site))
            ->assertRedirect(route('sites.edit', $site));

        $this->assertDatabaseCount('google_analytics_properties', 0);
        $this->assertDatabaseCount('google_analytics_connections', 0);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://oauth2.googleapis.com/revoke'
            && $request['token'] === 'refresh-token');
    }

    public function test_cancelling_property_selection_removes_the_pending_connection(): void
    {
        $this->configureOAuth();
        Http::fake([
            'https://oauth2.googleapis.com/revoke' => Http::response([], 200),
        ]);

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $connection = $this->connection($admin);

        $this->withSession([
            'google_analytics_selection' => [
                'site_id' => $site->id,
                'connection_id' => $connection->id,
            ],
        ])->actingAs($admin)
            ->delete(route('google-analytics.cancel', $site))
            ->assertRedirect(route('sites.edit', $site));

        $this->assertDatabaseCount('google_analytics_connections', 0);
        $this->assertNull(session('google_analytics_selection'));
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

            if (str_contains($request->url(), '/v1beta/accountSummaries')) {
                return Http::response(['accountSummaries' => []]);
            }

            return Http::response([], 404);
        });

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $connection = $this->connection($admin);
        $connection->update(['token_expires_at' => now()->subMinute()]);

        $this->withSession([
            'google_analytics_selection' => [
                'site_id' => $site->id,
                'connection_id' => $connection->id,
            ],
        ])->actingAs($admin)
            ->get(route('google-analytics.properties', $site))
            ->assertOk();

        $this->assertSame('refreshed-access-token', $connection->fresh()->access_token);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://oauth2.googleapis.com/token'
            && $request['grant_type'] === 'refresh_token');
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/v1beta/accountSummaries')
            && $request->hasHeader('Authorization', 'Bearer refreshed-access-token'));
    }

    private function configureOAuth(): void
    {
        config()->set('services.google_analytics.client_id', 'test-client-id');
        config()->set('services.google_analytics.client_secret', 'test-client-secret');
        config()->set('services.google_analytics.redirect_uri', 'https://dashboard.example.com/integrations/google-analytics/callback');
        config()->set('services.google_analytics.scope', 'https://www.googleapis.com/auth/analytics.readonly');
    }

    private function fakeGoogleApis(): void
    {
        Http::fake(function (Request $request) {
            if ($request->url() === 'https://oauth2.googleapis.com/token') {
                return Http::response([
                    'access_token' => 'access-token',
                    'refresh_token' => 'refresh-token',
                    'expires_in' => 3600,
                    'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
                ]);
            }

            if (str_contains($request->url(), '/v1beta/accountSummaries')) {
                return Http::response([
                    'accountSummaries' => [[
                        'account' => 'accounts/123456',
                        'displayName' => 'Example Analytics Account',
                        'propertySummaries' => [[
                            'property' => 'properties/987654',
                            'displayName' => 'Example Web Property',
                        ]],
                    ]],
                ]);
            }

            if (str_contains($request->url(), '/v1beta/properties/987654/dataStreams')) {
                return Http::response([
                    'dataStreams' => [[
                        'name' => 'properties/987654/dataStreams/456789',
                        'type' => 'WEB_DATA_STREAM',
                        'displayName' => 'Example Web Stream',
                        'webStreamData' => [
                            'measurementId' => 'G-ABC1234567',
                            'defaultUri' => 'https://example.test',
                        ],
                    ]],
                ]);
            }

            return Http::response([], 404);
        });
    }

    private function connection(User $admin): GoogleAnalyticsConnection
    {
        return GoogleAnalyticsConnection::query()->create([
            'authorized_by_user_id' => $admin->id,
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->addHour(),
            'scopes' => ['https://www.googleapis.com/auth/analytics.readonly'],
            'status' => 'active',
        ]);
    }
}
