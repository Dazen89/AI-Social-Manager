<?php

namespace Tests\Feature;

use App\Models\GoogleSearchConsoleConnection;
use App\Models\Site;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleSearchConsoleReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->travelTo(CarbonImmutable::parse('2026-10-23 12:00:00 UTC'));
    }

    public function test_connected_site_reads_and_caches_final_search_data_for_its_host(): void
    {
        Http::fake([
            'https://www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::sequence()
                ->push(['rows' => [$this->row([], 120, 3000, 0.04, 7.2)]])
                ->push(['rows' => [$this->row(['2026-10-20'], 12, 300, 0.04, 7.2)]])
                ->push(['rows' => [$this->row(['https://www.example.test/articles/one?utm_source=secret'], 80, 2000, 0.04, 6.2)]])
                ->push(['rows' => [$this->row(['example topic'], 20, 500, 0.04, 8.1)]]),
        ]);

        [$admin, $site] = $this->connectedSite();

        $this->actingAs($admin)
            ->get(route('sites.show', ['site' => $site, 'range' => 7]))
            ->assertOk()
            ->assertSee('Visibilità nella ricerca Google')
            ->assertSee('3.000')
            ->assertSee('4,0%')
            ->assertSee('/articles/one')
            ->assertSee('example topic')
            ->assertDontSee('utm_source=secret');

        $this->actingAs($admin)->get(route('sites.show', ['site' => $site, 'range' => 7]))->assertOk();
        Http::assertSentCount(4);
        Http::assertSent(function (Request $request): bool {
            return str_contains($request->url(), rawurlencode('sc-domain:example.test'))
                && $request->hasHeader('Authorization', 'Bearer search-access-token')
                && data_get($request->data(), 'startDate') === '2026-10-14'
                && data_get($request->data(), 'endDate') === '2026-10-20'
                && data_get($request->data(), 'dataState') === 'final'
                && data_get($request->data(), 'dimensionFilterGroups.0.filters.0.expression') === '^https?://www\\.example\\.test(?::[0-9]+)?/';
        });
    }

    public function test_empty_search_data_is_not_displayed_as_zero(): void
    {
        Http::fake([
            'https://www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response([]),
        ]);
        [$admin, $site] = $this->connectedSite();

        $this->actingAs($admin)
            ->get(route('sites.show', $site))
            ->assertOk()
            ->assertSee('non ci sono dati finalizzati')
            ->assertDontSee('Posizione media');

        Http::assertSentCount(1);
    }

    public function test_search_quota_error_does_not_break_the_rest_of_the_site_overview(): void
    {
        Http::fake([
            'https://www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response(['error' => ['code' => 429]], 429),
        ]);
        [$admin, $site] = $this->connectedSite();

        $this->actingAs($admin)
            ->get(route('sites.show', $site))
            ->assertOk()
            ->assertSee('Quota Search Console temporaneamente esaurita');

        $this->actingAs($admin)->get(route('sites.show', $site))->assertOk();
        Http::assertSentCount(1);
    }

    public function test_google_quota_reason_is_recognized_even_with_a_403_response(): void
    {
        Http::fake([
            'https://www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response([
                'error' => ['errors' => [['reason' => 'quotaExceeded']]],
            ], 403),
        ]);
        [$admin, $site] = $this->connectedSite();

        $this->actingAs($admin)
            ->get(route('sites.show', $site))
            ->assertOk()
            ->assertSee('Quota Search Console temporaneamente esaurita');
    }

    /** @return array{User, Site} */
    private function connectedSite(): array
    {
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create([
            'host' => 'www.example.test',
            'base_url' => 'https://www.example.test',
        ]);
        $connection = GoogleSearchConsoleConnection::query()->create([
            'authorized_by_user_id' => $admin->id,
            'access_token' => 'search-access-token',
            'refresh_token' => 'search-refresh-token',
            'token_expires_at' => now()->addHour(),
            'scopes' => ['https://www.googleapis.com/auth/webmasters.readonly'],
            'status' => 'active',
        ]);
        $site->googleSearchConsoleProperty()->create([
            'google_search_console_connection_id' => $connection->id,
            'property_url' => 'sc-domain:example.test',
            'property_type' => 'domain',
            'permission_level' => 'siteOwner',
            'last_verified_at' => now(),
        ]);

        return [$admin, $site];
    }

    /** @param array<int, string> $keys
     *  @return array<string, mixed>
     */
    private function row(array $keys, int $clicks, int $impressions, float $ctr, float $position): array
    {
        return compact('keys', 'clicks', 'impressions', 'ctr', 'position');
    }
}
