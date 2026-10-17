<?php

namespace Tests\Feature;

use App\Models\GoogleAnalyticsConnection;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SiteAnalyticsOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_guests_cannot_open_a_site_overview(): void
    {
        $site = Site::factory()->create();

        $this->get(route('sites.show', $site))
            ->assertRedirect(route('login'));
    }

    public function test_unconnected_site_explains_the_required_next_step_without_calling_google(): void
    {
        Http::fake();

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();

        $this->actingAs($admin)
            ->get(route('sites.show', $site))
            ->assertOk()
            ->assertSee('Collega Google Analytics per vedere i dati')
            ->assertSee('Vai ai collegamenti');

        Http::assertNothingSent();
    }

    public function test_connected_site_displays_a_hostname_filtered_ga4_overview(): void
    {
        Http::fake([
            'https://analyticsdata.googleapis.com/v1beta/properties/987654:batchRunReports' => Http::response($this->reportPayload()),
        ]);

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create([
            'name' => 'Example Website',
            'base_url' => 'https://analytics.example.test',
            'host' => 'analytics.example.test',
        ]);
        $this->connectAnalytics($admin, $site);

        $this->actingAs($admin)
            ->get(route('sites.show', ['site' => $site, 'range' => 28]))
            ->assertOk()
            ->assertSee('Panoramica del traffico')
            ->assertSee('1.234')
            ->assertSee('2.345')
            ->assertSee('Example article')
            ->assertSee('/articles/example')
            ->assertSee('Organic Search')
            ->assertSee('desktop')
            ->assertSee('Italy');

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $request->url() === 'https://analyticsdata.googleapis.com/v1beta/properties/987654:batchRunReports'
                && $request->hasHeader('Authorization', 'Bearer access-token')
                && count($data['requests'] ?? []) === 5
                && data_get($data, 'requests.0.dateRanges.0.startDate') === '27daysAgo'
                && data_get($data, 'requests.0.dimensionFilter.filter.fieldName') === 'hostName'
                && data_get($data, 'requests.0.dimensionFilter.filter.stringFilter.value') === 'analytics.example.test';
        });
    }

    public function test_report_response_is_cached_for_ten_minutes(): void
    {
        Http::fake([
            'https://analyticsdata.googleapis.com/v1beta/properties/987654:batchRunReports' => Http::response($this->reportPayload()),
        ]);

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $this->connectAnalytics($admin, $site);

        $this->actingAs($admin)->get(route('sites.show', $site))->assertOk();
        $this->actingAs($admin)->get(route('sites.show', $site))->assertOk();

        Http::assertSentCount(1);
    }

    public function test_disabled_data_api_produces_an_actionable_error(): void
    {
        Http::fake([
            'https://analyticsdata.googleapis.com/*' => Http::response([
                'error' => [
                    'code' => 403,
                    'message' => 'Google Analytics Data API has not been used in project before or it is disabled.',
                    'status' => 'PERMISSION_DENIED',
                    'details' => [[
                        'reason' => 'SERVICE_DISABLED',
                        'metadata' => ['service' => 'analyticsdata.googleapis.com'],
                    ]],
                ],
            ], 403),
        ]);

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $this->connectAnalytics($admin, $site);

        $this->actingAs($admin)
            ->get(route('sites.show', $site))
            ->assertOk()
            ->assertSee('La connessione è valida')
            ->assertSee('Abilita Google Analytics Data API')
            ->assertSee('https://console.cloud.google.com/apis/library/analyticsdata.googleapis.com', false);
    }

    /**
     * @return array<string, mixed>
     */
    private function reportPayload(): array
    {
        return [
            'reports' => [
                [
                    'metricHeaders' => $this->headers([
                        'activeUsers',
                        'sessions',
                        'screenPageViews',
                        'engagedSessions',
                        'engagementRate',
                        'averageSessionDuration',
                    ]),
                    'rows' => [[
                        'metricValues' => $this->values(['1234', '2345', '6789', '1800', '0.7676', '95.4']),
                    ]],
                ],
                [
                    'dimensionHeaders' => $this->headers(['date']),
                    'metricHeaders' => $this->headers(['sessions', 'activeUsers', 'screenPageViews']),
                    'rows' => [
                        [
                            'dimensionValues' => $this->values(['20261015']),
                            'metricValues' => $this->values(['100', '75', '240']),
                        ],
                        [
                            'dimensionValues' => $this->values(['20261016']),
                            'metricValues' => $this->values(['120', '90', '280']),
                        ],
                    ],
                ],
                [
                    'dimensionHeaders' => $this->headers(['pagePathPlusQueryString', 'pageTitle']),
                    'metricHeaders' => $this->headers(['screenPageViews', 'activeUsers']),
                    'rows' => [[
                        'dimensionValues' => $this->values(['/articles/example', 'Example article']),
                        'metricValues' => $this->values(['950', '700']),
                    ]],
                ],
                [
                    'dimensionHeaders' => $this->headers(['sessionDefaultChannelGroup']),
                    'metricHeaders' => $this->headers(['sessions', 'activeUsers', 'engagementRate']),
                    'rows' => [[
                        'dimensionValues' => $this->values(['Organic Search']),
                        'metricValues' => $this->values(['1200', '900', '0.81']),
                    ]],
                ],
                [
                    'dimensionHeaders' => $this->headers(['country', 'deviceCategory']),
                    'metricHeaders' => $this->headers(['sessions']),
                    'rows' => [
                        [
                            'dimensionValues' => $this->values(['Italy', 'desktop']),
                            'metricValues' => $this->values(['1000']),
                        ],
                        [
                            'dimensionValues' => $this->values(['Italy', 'mobile']),
                            'metricValues' => $this->values(['600']),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<int, string>  $names
     * @return array<int, array{name: string}>
     */
    private function headers(array $names): array
    {
        return array_map(fn (string $name): array => ['name' => $name], $names);
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, array{value: string}>
     */
    private function values(array $values): array
    {
        return array_map(fn (string $value): array => ['value' => $value], $values);
    }

    private function connectAnalytics(User $admin, Site $site): void
    {
        $connection = GoogleAnalyticsConnection::query()->create([
            'authorized_by_user_id' => $admin->id,
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->addHour(),
            'scopes' => ['https://www.googleapis.com/auth/analytics.readonly'],
            'status' => 'active',
        ]);

        $site->googleAnalyticsProperty()->create([
            'google_analytics_connection_id' => $connection->id,
            'account_id' => '123456',
            'account_name' => 'Example Analytics Account',
            'property_id' => '987654',
            'property_name' => 'Example Web Property',
            'data_stream_id' => '456789',
            'measurement_id' => 'G-ABC1234567',
            'last_verified_at' => now(),
        ]);
    }
}
