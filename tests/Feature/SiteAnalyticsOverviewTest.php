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

    public function test_diagnostic_command_requests_only_one_minimal_report_and_reuses_its_measurement(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://analyticsdata.googleapis.com/*' => Http::response(['reports' => [[]]])]);
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $this->connectAnalytics($admin, $site);

        $this->artisan('analytics:diagnose')->expectsOutputToContain('Report minimale riuscito.')->assertSuccessful();
        $this->artisan('analytics:diagnose', ['site' => $site->id])->assertSuccessful();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => count($request['requests']) === 1
            && $request['requests'][0]['metrics'] === [['name' => 'sessions']]
            && $request['requests'][0]['dateRanges'] === [['startDate' => 'yesterday', 'endDate' => 'yesterday']]);
    }

    public function test_diagnostic_command_respects_shared_cooldown_after_server_errors(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://analyticsdata.googleapis.com/*' => Http::response([], 503)]);
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $this->connectAnalytics($admin, $site);
        $this->artisan('analytics:diagnose')->expectsOutputToContain('HTTP 503')->assertFailed();
        $this->artisan('analytics:diagnose')->expectsOutputToContain('Nuove letture sospese per 5 minuti')->assertFailed();
        Http::assertSentCount(1);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('connectionFailures')]
    public function test_connection_errors_are_classified_without_exposing_raw_details(int $errno, string $kind, string $label): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        $calls = 0;
        Http::fake(function ($request, $options) use ($errno, &$calls) {
            $calls++;
            $this->assertSame(20, $options['timeout']);
            return Http::failedConnection("cURL error {$errno}: synthetic-private-address synthetic-token")($request);
        });
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $this->connectAnalytics($admin, $site);

        $this->actingAs($admin)->get(route('sites.show', $site))->assertOk()
            ->assertSee($label)->assertSee('durante la lettura dei report')
            ->assertDontSee('synthetic-private-address')->assertDontSee('synthetic-token')
            ->assertViewHas('analyticsQuota', fn ($diagnostics) =>
                $diagnostics['request_error']['type'] === $kind
                && $diagnostics['request_error']['http_status'] === null
                && $diagnostics['request_error']['phase'] === 'reports');
        $this->get(route('sites.show', $site))->assertOk()->assertSee($label);
        $this->assertSame(1, $calls);
        $diagnostics = app(\App\Services\GoogleAnalyticsReportService::class)->quotaDiagnostics($site->googleAnalyticsProperty);
        $this->assertStringNotContainsString('synthetic-token', json_encode($diagnostics));
        $this->travel(6)->minutes();
        $this->get(route('sites.show', $site))->assertOk();
        $this->assertSame(2, $calls);
    }

    public static function connectionFailures(): array
    {
        return [
            'timeout' => [28, 'timeout', 'Timeout nella comunicazione'],
            'dns' => [6, 'network', 'Errore DNS'],
            'tls' => [60, 'network', 'Errore TLS'],
            'unknown' => [0, 'network', 'Errore di rete'],
        ];
    }

    public function test_network_failure_during_token_refresh_is_distinguished_from_report_reading(): void
    {
        Http::preventStrayRequests();
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $this->connectAnalytics($admin, $site);
        $this->mock(\App\Services\GoogleAnalyticsService::class)
            ->shouldReceive('validAccessToken')->once()
            ->andThrow(new \Illuminate\Http\Client\ConnectionException('cURL error 28: synthetic-private-address'));

        $this->actingAs($admin)->get(route('sites.show', $site))->assertOk()
            ->assertSee('durante il rinnovo dell’autorizzazione')
            ->assertDontSee('synthetic-private-address')
            ->assertViewHas('analyticsQuota', fn ($diagnostics) =>
                $diagnostics['request_error']['type'] === 'timeout'
                && $diagnostics['request_error']['phase'] === 'authorization');
        Http::assertNothingSent();
    }

    public function test_server_error_keeps_http_status_and_recovers_after_the_pause(): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        Http::fakeSequence()->push(['error' => ['message' => 'synthetic-private-message']], 503)
            ->push($this->reportPayload());
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $this->connectAnalytics($admin, $site);
        $this->actingAs($admin)->get(route('sites.show', $site))->assertOk()
            ->assertSee('HTTP 503')->assertSee('Errore del servizio Google')
            ->assertDontSee('synthetic-private-message')
            ->assertViewHas('analyticsQuota', fn ($diagnostics) => $diagnostics['request_error']['http_status'] === 503);
        $this->get(route('sites.show', $site))->assertOk()->assertSee('HTTP 503');
        Http::assertSentCount(1);
        $this->travel(6)->minutes();
        $this->get(route('sites.show', $site))->assertOk()->assertDontSee('Ultimo problema di lettura');
        Http::assertSentCount(2);
    }

    public function test_consecutive_errors_increase_the_shared_pause_and_success_resets_it(): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        Http::fakeSequence()->push([], 503)->push([], 500)->push([], 503)->push([], 503)
            ->push($this->reportPayload())->push([], 503);
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $this->connectAnalytics($admin, $site);
        GoogleAnalyticsConnection::query()->update(['token_expires_at' => now()->addDays(2)]);
        $this->actingAs($admin);

        foreach ([5, 15, 30, 60] as $index => $delay) {
            $this->get(route('sites.show', $site))->assertOk()
                ->assertSee("Nuove letture sospese per {$delay} minuti.")
                ->assertViewHas('analyticsQuota', fn ($diagnostics) =>
                    $diagnostics['request_error']['retry_at'] === now()->addMinutes($delay)->toIso8601String());
            $this->get(route('sites.show', ['site' => $site, 'range' => 90]))->assertOk();
            Http::assertSentCount($index + 1);
            $this->travel($delay + 1)->minutes();
        }

        $this->get(route('sites.show', $site))->assertOk()->assertDontSee('Ultimo problema di lettura');
        $this->travel(11)->minutes();
        $this->get(route('sites.show', $site))->assertOk()->assertSee('Nuove letture sospese per 5 minuti.');
        Http::assertSentCount(6);
    }

    public function test_low_quota_pauses_other_ranges_and_keeps_the_last_successful_report(): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        $payload = $this->reportPayload();
        $payload['reports'][0]['propertyQuota'] = [
            'tokensPerProjectPerHour' => ['remaining' => 50],
        ];
        Http::fakeSequence()->push($payload)->push($this->reportPayload());
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $this->connectAnalytics($admin, $site);
        GoogleAnalyticsConnection::query()->update(['token_expires_at' => now()->addDays(2)]);

        $this->actingAs($admin)->get(route('sites.show', $site))->assertOk()
            ->assertSee('Pausa preventiva per quota residua bassa')->assertSee('Panoramica del traffico');
        $this->get(route('sites.show', ['site' => $site, 'range' => 90]))->assertOk()
            ->assertSee('Aggiornamento sospeso per un’ora');
        $this->travel(11)->minutes();
        $this->get(route('sites.show', $site))->assertOk()->assertSee('Visualizzi l’ultimo report disponibile');
        Http::assertSentCount(1);
        $this->travel(50)->minutes();
        $this->get(route('sites.show', $site))->assertOk()->assertDontSee('Pausa preventiva per quota residua bassa');
        Http::assertSentCount(2);
    }

    public function test_quota_panel_uses_reported_balances_and_does_not_make_extra_requests(): void
    {
        $payload = $this->reportPayload();
        $payload['reports'][0]['propertyQuota'] = [
            'tokensPerDay' => ['consumed' => 5, 'remaining' => 1000],
            'tokensPerProjectPerHour' => ['remaining' => 0],
            'concurrentRequests' => ['remaining' => 9],
        ];
        $payload['reports'][1]['propertyQuota'] = ['tokensPerDay' => ['remaining' => 950]];
        Http::preventStrayRequests();
        Http::fake(['https://analyticsdata.googleapis.com/*' => Http::response($payload)]);
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $this->connectAnalytics($admin, $site);

        $this->actingAs($admin)->get(route('sites.show', $site))->assertOk()
            ->assertSee('Quote Google Analytics')->assertSee('950')
            ->assertViewHas('analyticsQuota', fn ($diagnostics) => $diagnostics['quotas'] === [
                ['label' => 'Token giornalieri della proprietà', 'remaining' => 950],
                ['label' => 'Token orari del progetto per questa proprietà', 'remaining' => 0],
                ['label' => 'Richieste contemporanee', 'remaining' => 9],
            ]);
        $this->get(route('sites.show', $site))->assertOk()->assertSee('950');
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => collect($request['requests'])->every(
            fn ($report) => $report['returnPropertyQuota'] === true,
        ));
    }

    public function test_quota_error_identifies_known_limits_without_exposing_provider_metadata(): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        Http::fakeSequence()->push(['error' => [
            'status' => 'RESOURCE_EXHAUSTED',
            'message' => 'Synthetic private provider detail',
            'details' => [[
                'metadata' => ['quota_metric' => 'analyticsdata.googleapis.com/tokens_per_project_per_hour',
                    'consumer' => 'synthetic-private-project'],
            ]],
        ]], 429)->push($this->reportPayload());
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $this->connectAnalytics($admin, $site);

        $this->actingAs($admin)->get(route('sites.show', $site))->assertOk()
            ->assertSee('Token orari del progetto per questa proprietà')
            ->assertSee('Nuove letture sospese')->assertDontSee('synthetic-private-project')
            ->assertDontSee('Synthetic private provider detail')
            ->assertViewHas('analyticsQuota', fn ($diagnostics) => $diagnostics['error']['http_status'] === 429
                && $diagnostics['error']['retry_at'] === now()->addMinutes(15)->toIso8601String());
        $this->get(route('sites.show', $site))->assertOk()->assertSee('Token orari del progetto per questa proprietà');
        Http::assertSentCount(1);
        $diagnostics = app(\App\Services\GoogleAnalyticsReportService::class)->quotaDiagnostics($site->googleAnalyticsProperty);
        $this->assertStringNotContainsString('synthetic-private-project', json_encode($diagnostics));
        $this->travel(16)->minutes();
        $this->get(route('sites.show', $site))->assertOk()->assertDontSee('Ultimo rifiuto per quota');
        Http::assertSentCount(2);
    }

    public function test_quota_error_blocks_other_ranges_and_insight_requests_until_cooldown_expires(): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        Http::fakeSequence()->push(['error' => ['status' => 'RESOURCE_EXHAUSTED']], 429)
            ->push($this->reportPayload());
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $this->connectAnalytics($admin, $site);

        $this->actingAs($admin)->get(route('sites.show', $site))->assertOk()->assertSee('15 minuti')
            ->assertSee('senza specificare il limite raggiunto');
        $this->get(route('sites.show', ['site' => $site, 'range' => 90]))->assertOk()->assertSee('15 minuti');

        try {
            app(\App\Services\GoogleAnalyticsReportService::class)->insightData($site->googleAnalyticsProperty, $site->host, 28);
            $this->fail('Insight requests must respect the property cooldown.');
        } catch (\App\Exceptions\GoogleAnalyticsReportException $exception) {
            $this->assertStringContainsString('15 minuti', $exception->getMessage());
        }

        Http::assertSentCount(1);
        $this->travel(16)->minutes();
        $this->get(route('sites.show', $site))->assertOk()->assertSee('Panoramica del traffico');
        Http::assertSentCount(2);
    }

    public function test_last_successful_report_is_shown_during_quota_exhaustion_with_original_timestamp(): void
    {
        $this->freezeTime();
        Http::preventStrayRequests();
        $payload = $this->reportPayload();
        $payload['reports'][0]['propertyQuota'] = ['tokensPerDay' => ['remaining' => 199500]];
        Http::fakeSequence()->push($payload)->push([], 429);
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $this->connectAnalytics($admin, $site);
        $fetchedAt = now()->toIso8601String();

        $this->actingAs($admin)->get(route('sites.show', $site))->assertOk();
        $this->travel(11)->minutes();
        $this->get(route('sites.show', $site))->assertOk()
            ->assertSee('Visualizzi l’ultimo report disponibile')
            ->assertSee('1.234')
            ->assertSee('199.500')
            ->assertViewHas('analyticsQuota', fn ($diagnostics) => $diagnostics['measured_at'] === $fetchedAt
                && $diagnostics['error']['http_status'] === 429)
            ->assertViewHas('overview', fn (array $report) => $report['is_stale'] && $report['fetched_at'] === $fetchedAt);
        $this->get(route('sites.show', $site))->assertOk()->assertSee('Visualizzi l’ultimo report disponibile');
        Http::assertSentCount(2);
    }

    public function test_simultaneous_report_read_does_not_call_google(): void
    {
        Http::preventStrayRequests();
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $this->connectAnalytics($admin, $site);
        $lock = Cache::lock('ga4-report-lock.'.sha1('987654'), 60);
        $lock->get();

        try {
            $this->actingAs($admin)->get(route('sites.show', $site))->assertOk()->assertSee('È già in corso');
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }
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
