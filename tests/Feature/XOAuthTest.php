<?php

namespace Tests\Feature;

use App\Models\IntegrationSetting;
use App\Models\Site;
use App\Models\User;
use App\Models\XConnection;
use App\Services\XService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class XOAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_connect_uses_pkce_and_the_minimum_publishing_scopes(): void
    {
        $this->configureX();
        $admin = User::factory()->administrator()->create();
        $this->createSetting($admin);

        $response = $this->actingAs($admin)->get(route('x.connect'));

        $response->assertRedirectContains('https://x.com/i/oauth2/authorize?');
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->assertSame('code', $query['response_type']);
        $this->assertSame('saved-x-client-id', $query['client_id']);
        $this->assertSame('https://monitor.example.com/integrations/x/callback', $query['redirect_uri']);
        $this->assertSame('tweet.read tweet.write users.read offline.access media.write', $query['scope']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertNotSame('', $query['code_challenge']);
        $this->assertNotSame('', $query['state']);
    }

    #[DataProvider('previousXAccounts')]
    public function test_callback_verifies_the_account_and_keeps_publishing_enabled_only_for_the_same_identity(?string $previousAccount, bool $expectedEnabled): void
    {
        $this->configureX();
        Http::fake([
            'https://api.x.com/2/oauth2/token' => Http::response([
                'token_type' => 'bearer',
                'expires_in' => 7200,
                'access_token' => 'secret-access-token',
                'scope' => 'tweet.read tweet.write users.read offline.access media.write',
                'refresh_token' => 'secret-refresh-token',
            ]),
            'https://api.x.com/2/users/me*' => Http::response([
                'data' => [
                    'id' => '987654321',
                    'name' => 'Publishing Account',
                    'username' => 'publisher',
                    'profile_image_url' => 'https://pbs.twimg.com/profile_images/example.jpg',
                ],
            ]),
        ]);

        $admin = User::factory()->administrator()->create();
        $setting = $this->createSetting($admin);
        if ($previousAccount !== null) {
            XConnection::query()->create([
                'x_user_id' => $previousAccount, 'username' => 'previous', 'display_name' => 'Previous account',
                'access_token' => 'previous-token', 'status' => 'active',
                'manual_publishing_enabled' => true, 'automatic_publishing_enabled' => true,
            ]);
            $schedule = Site::factory()->create()->editorialSchedule()->create([
                'enabled' => true, 'x_autopublish_enabled' => true, 'interval_hours' => 24,
                'start_time' => '09:00', 'timezone' => 'UTC',
            ]);
        }
        $authorization = $this->actingAs($admin)->get(route('x.connect'));
        parse_str((string) parse_url($authorization->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->get(route('x.callback', [
            'state' => $query['state'],
            'code' => 'short-lived-authorization-code',
        ]))
            ->assertRedirect(route('settings.x.edit'))
            ->assertSessionHas('status', 'Account @publisher collegato e verificato.');

        $connection = XConnection::query()->sole();

        $this->assertSame('987654321', $connection->x_user_id);
        $this->assertSame($expectedEnabled, $connection->manual_publishing_enabled);
        $this->assertSame($expectedEnabled, $connection->automatic_publishing_enabled);
        if (isset($schedule)) {
            $this->assertSame($expectedEnabled, $schedule->fresh()->x_autopublish_enabled);
        }
        $this->assertSame('publisher', $connection->username);
        $this->assertSame('Publishing Account', $connection->display_name);
        $this->assertSame('secret-access-token', $connection->access_token);
        $this->assertSame('secret-refresh-token', $connection->refresh_token);
        $this->assertSame('verified', $setting->fresh()->status);
        $this->assertNotNull($setting->fresh()->last_tested_at);

        $stored = DB::table('x_connections')->first();
        $this->assertStringNotContainsString('secret-access-token', (string) $stored->access_token);
        $this->assertStringNotContainsString('secret-refresh-token', (string) $stored->refresh_token);

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://api.x.com/2/oauth2/token') {
                return false;
            }

            return $request['grant_type'] === 'authorization_code'
                && $request['code'] === 'short-lived-authorization-code'
                && filled($request['code_verifier'])
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('saved-x-client-id:saved-x-client-secret'));
        });

        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://api.x.com/2/users/me')
            && $request->hasHeader('Authorization', 'Bearer secret-access-token'));
    }

    public static function previousXAccounts(): array
    {
        return ['first connection' => [null, false], 'same account' => ['987654321', true], 'different account' => ['123456789', false]];
    }

    public function test_only_administrators_can_enable_publication_for_the_current_account_with_write_access(): void
    {
        $connection = XConnection::query()->create([
            'x_user_id' => '987654321', 'username' => 'publisher', 'display_name' => 'Publisher',
            'access_token' => 'synthetic-token', 'scopes' => ['tweet.read'], 'status' => 'active',
        ]);
        $route = route('x.publishing.update');
        $activation = ['account' => $connection->x_user_id, 'mode' => 'manual', 'enabled' => '1'];
        Http::preventStrayRequests();

        $this->actingAs(User::factory()->create(['is_admin' => false]))->patch($route, $activation)->assertForbidden();
        $this->actingAs(User::factory()->administrator()->create())->patch($route, $activation)->assertSessionHas('integration_error');
        $connection->update(['scopes' => ['tweet.write']]);
        $this->patch($route, [...$activation, 'account' => '123456789'])->assertSessionHas('integration_error');
        $this->assertFalse($connection->fresh()->manual_publishing_enabled);

        $this->get(route('settings.x.edit'))->assertOk()->assertSee('Abilita pubblicazione manuale')->assertSee('Abilita pubblicazione automatica');
        $this->patch($route, $activation)->assertRedirect(route('settings.x.edit'))->assertSessionHas('status');
        $this->assertTrue($connection->fresh()->manual_publishing_enabled);
        $this->assertFalse($connection->fresh()->automatic_publishing_enabled);
        $this->get(route('settings.x.edit'))->assertOk()->assertSee('Sospendi pubblicazione manuale')->assertSee('Abilita pubblicazione automatica');
        Http::assertNothingSent();
    }

    public function test_post_metrics_use_the_repost_count_returned_by_x(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://api.x.com/2/tweets/123*' => Http::response([
            'data' => ['public_metrics' => [
                'impression_count' => 250,
                'retweet_count' => 4,
                'reply_count' => 0,
            ]],
        ])]);
        $connection = new XConnection([
            'access_token' => 'synthetic-token',
            'token_expires_at' => now()->addHour(),
        ]);

        $this->assertSame([
            'impression_count' => 250,
            'reply_count' => 0,
            'repost_count' => 4,
        ], app(XService::class)->postPublicMetrics($connection, '123'));

        Http::assertSentCount(1);
    }

    public function test_callback_rejects_an_invalid_state_without_calling_x(): void
    {
        $this->configureX();
        Http::fake();
        $admin = User::factory()->administrator()->create();
        $this->createSetting($admin);

        $this->actingAs($admin)->get(route('x.connect'));

        $this->get(route('x.callback', [
            'state' => 'tampered-state',
            'code' => 'authorization-code',
        ]))
            ->assertRedirect(route('settings.x.edit'))
            ->assertSessionHas('integration_error');

        $this->assertDatabaseCount('x_connections', 0);
        Http::assertNothingSent();
    }

    public function test_missing_required_scope_prevents_the_connection(): void
    {
        $this->configureX();
        Http::fake([
            'https://api.x.com/2/oauth2/token' => Http::response([
                'expires_in' => 7200,
                'access_token' => 'access-token',
                'refresh_token' => 'refresh-token',
                'scope' => 'tweet.read users.read offline.access',
            ]),
        ]);
        $admin = User::factory()->administrator()->create();
        $setting = $this->createSetting($admin);
        $authorization = $this->actingAs($admin)->get(route('x.connect'));
        parse_str((string) parse_url($authorization->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->get(route('x.callback', [
            'state' => $query['state'],
            'code' => 'authorization-code',
        ]))
            ->assertRedirect(route('settings.x.edit'))
            ->assertSessionHas('integration_error');

        $this->assertDatabaseCount('x_connections', 0);
        $this->assertSame('error', $setting->fresh()->status);
    }

    public function test_disconnecting_revokes_the_token_and_removes_the_local_account(): void
    {
        $this->configureX();
        Http::fake([
            'https://api.x.com/2/oauth2/revoke' => Http::response([], 200),
        ]);
        $admin = User::factory()->administrator()->create();
        $this->createSetting($admin);
        Site::factory()->create();
        XConnection::query()->create([
            'authorized_by_user_id' => $admin->id,
            'x_user_id' => '987654321',
            'username' => 'publisher',
            'display_name' => 'Publishing Account',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('aria-label="Social (X) — Collegato"', false);

        $this->delete(route('x.destroy'))
            ->assertRedirect(route('settings.x.edit'))
            ->assertSessionHas('status', 'Account X scollegato e autorizzazione revocata.');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('aria-label="Social (X) — Non collegato"', false);

        $this->assertDatabaseCount('x_connections', 0);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.x.com/2/oauth2/revoke'
            && $request['token'] === 'refresh-token');
    }

    private function configureX(): void
    {
        config()->set('services.x.client_id');
        config()->set('services.x.client_secret');
        config()->set('services.x.redirect_uri', 'https://monitor.example.com/integrations/x/callback');
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
}
