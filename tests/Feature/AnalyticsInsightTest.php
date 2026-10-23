<?php

namespace Tests\Feature;

use App\Models\AiIntegration;
use App\Models\AiUsageRecord;
use App\Models\AnalyticsInsightReport;
use App\Models\GoogleAnalyticsConnection;
use App\Models\GoogleSearchConsoleConnection;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnalyticsInsightTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_verified_active_integration_generates_and_saves_a_structured_analytics_analysis(): void
    {
        Http::fake([
            'https://analyticsdata.googleapis.com/*' => Http::sequence()
                ->push($this->overviewPayload())
                ->push($this->insightPayload()),
            'https://api.openai.com/v1/responses' => Http::response([
                'id' => 'resp_synthetic_123',
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode($this->analysis()),
                    ]],
                ]],
                'usage' => [
                    'input_tokens' => 1200,
                    'output_tokens' => 450,
                    'total_tokens' => 1650,
                ],
            ]),
        ]);

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create([
            'base_url' => 'https://analytics.example.test',
            'host' => 'analytics.example.test',
        ]);
        $this->connectAnalytics($admin, $site);
        $integration = $this->createAiIntegration($admin);

        $this->actingAs($admin)
            ->post(route('sites.analytics-insights.store', $site), ['range' => 28])
            ->assertRedirect(route('sites.show', ['site' => $site, 'range' => 28]))
            ->assertSessionHas('analytics_insight_status', 'Analisi AI completata e salvata.');

        $report = AnalyticsInsightReport::query()->sole();
        $this->assertSame('Il traffico cresce, ma va verificata la qualità delle visite.', $report->analysis['summary']);
        $this->assertSame('/articles/example', data_get($report->source_snapshot, 'landing_pages.0.path'));
        $this->assertSame(1650, $report->total_tokens);
        $this->assertSame($integration->id, $report->ai_integration_id);

        $usage = AiUsageRecord::query()->sole();
        $this->assertSame('analytics_insight', $usage->feature);
        $this->assertSame(1200, $usage->input_tokens);
        $this->assertSame(450, $usage->output_tokens);
        $this->assertSame('resp_synthetic_123', $usage->response_id);

        $storedAnalysis = (string) DB::table('analytics_insight_reports')->value('analysis');
        $this->assertStringNotContainsString('Il traffico cresce', $storedAnalysis);

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://api.openai.com/v1/responses') {
                return false;
            }

            $encodedRequest = json_encode($request->data());

            return data_get($request->data(), 'text.format.type') === 'json_schema'
                && data_get($request->data(), 'text.format.strict') === true
                && ! str_contains((string) $encodedRequest, 'utm_source');
        });
        $googleRequests = collect(Http::recorded())
            ->map(fn (array $exchange): Request => $exchange[0])
            ->filter(fn (Request $request): bool => str_contains($request->url(), 'analyticsdata.googleapis.com'));
        $this->assertCount(2, $googleRequests);
        $this->assertTrue($googleRequests->every(
            fn (Request $request): bool => count($request->data()['requests'] ?? []) <= 5,
        ));

        $this->actingAs($admin)
            ->get(route('sites.show', ['site' => $site, 'range' => 28]))
            ->assertOk()
            ->assertSee('I numeri tradotti in decisioni')
            ->assertSee('Il traffico cresce, ma va verificata la qualità delle visite.')
            ->assertSee('Controlla le landing page in crescita')
            ->assertSee('1.650 token registrati');
    }

    public function test_unchanged_data_reuses_the_saved_analysis_without_spending_more_tokens(): void
    {
        Http::fake([
            'https://analyticsdata.googleapis.com/*' => Http::sequence()
                ->push($this->overviewPayload())
                ->push($this->insightPayload()),
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'type' => 'message',
                    'content' => [['type' => 'output_text', 'text' => json_encode($this->analysis())]],
                ]],
                'usage' => ['input_tokens' => 100, 'output_tokens' => 50, 'total_tokens' => 150],
            ]),
        ]);

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $this->connectAnalytics($admin, $site);
        $this->createAiIntegration($admin);

        $this->actingAs($admin)->post(route('sites.analytics-insights.store', $site), ['range' => 28]);
        $this->actingAs($admin)
            ->post(route('sites.analytics-insights.store', $site), ['range' => 28])
            ->assertSessionHas('analytics_insight_status', 'I dati non sono cambiati: mostro l’analisi già disponibile senza effettuare una nuova chiamata AI.');

        $this->assertDatabaseCount('analytics_insight_reports', 1);
        $this->assertDatabaseCount('ai_usage_records', 1);
        Http::assertSentCount(3);
    }

    public function test_connected_search_console_data_is_included_in_the_encrypted_ai_snapshot(): void
    {
        Http::fake([
            'https://analyticsdata.googleapis.com/*' => Http::sequence()
                ->push($this->overviewPayload())
                ->push($this->insightPayload()),
            'https://www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::sequence()
                ->push(['rows' => [['clicks' => 80, 'impressions' => 2000, 'ctr' => 0.04, 'position' => 7.2]]])
                ->push(['rows' => [['keys' => ['2026-10-19'], 'clicks' => 8, 'impressions' => 200, 'ctr' => 0.04, 'position' => 7.2]]])
                ->push(['rows' => [['keys' => ['https://analytics.example.test/articles/example?private=1'], 'clicks' => 50, 'impressions' => 1200, 'ctr' => 0.04, 'position' => 6.2]]])
                ->push(['rows' => [
                    ['keys' => ['example topic'], 'clicks' => 20, 'impressions' => 500, 'ctr' => 0.04, 'position' => 8.1],
                    ['keys' => ['person@example.test'], 'clicks' => 20, 'impressions' => 500, 'ctr' => 0.04, 'position' => 8.1],
                ]]),
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'type' => 'message',
                    'content' => [['type' => 'output_text', 'text' => json_encode($this->analysis())]],
                ]],
                'usage' => ['input_tokens' => 100, 'output_tokens' => 50, 'total_tokens' => 150],
            ]),
        ]);

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create([
            'host' => 'analytics.example.test',
            'base_url' => 'https://analytics.example.test',
        ]);
        $this->connectAnalytics($admin, $site);
        $this->connectSearchConsole($admin, $site);
        $this->createAiIntegration($admin);

        $this->actingAs($admin)
            ->post(route('sites.analytics-insights.store', $site), ['range' => 28])
            ->assertSessionHas('analytics_insight_status', 'Analisi AI completata e salvata.');

        $snapshot = AnalyticsInsightReport::query()->sole()->source_snapshot;
        $this->assertSame('available', data_get($snapshot, 'search_console.status'));
        $this->assertSame(80, data_get($snapshot, 'search_console.summary.clicks'));
        $this->assertSame('/articles/example', data_get($snapshot, 'search_console.top_pages.0.key'));
        $this->assertSame('example topic', data_get($snapshot, 'search_console.top_queries.0.key'));
        $this->assertCount(1, data_get($snapshot, 'search_console.top_queries'));
        $this->assertStringNotContainsString('example topic', (string) DB::table('analytics_insight_reports')->value('source_snapshot'));

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.openai.com/v1/responses'
                && str_contains((string) json_encode($request->data()), 'example topic')
                && ! str_contains((string) json_encode($request->data()), 'person@example.test')
                && ! str_contains((string) json_encode($request->data()), 'private=1');
        });
    }

    public function test_search_console_failure_is_explicit_and_does_not_prevent_ga4_analysis(): void
    {
        Http::fake([
            'https://analyticsdata.googleapis.com/*' => Http::sequence()
                ->push($this->overviewPayload())
                ->push($this->insightPayload()),
            'https://www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response(['error' => ['code' => 429]], 429),
            'https://api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'type' => 'message',
                    'content' => [['type' => 'output_text', 'text' => json_encode($this->analysis())]],
                ]],
                'usage' => ['input_tokens' => 100, 'output_tokens' => 50, 'total_tokens' => 150],
            ]),
        ]);

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $this->connectAnalytics($admin, $site);
        $this->connectSearchConsole($admin, $site);
        $this->createAiIntegration($admin);

        $this->actingAs($admin)
            ->post(route('sites.analytics-insights.store', $site), ['range' => 28])
            ->assertSessionHas('analytics_insight_status', 'Analisi AI completata e salvata.');

        $this->assertSame('unavailable', data_get(AnalyticsInsightReport::query()->sole()->source_snapshot, 'search_console.status'));
    }

    public function test_analysis_requires_a_verified_ai_integration(): void
    {
        Http::fake();

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $this->connectAnalytics($admin, $site);
        $this->createAiIntegration($admin, 'configured');

        $this->actingAs($admin)
            ->post(route('sites.analytics-insights.store', $site), ['range' => 28])
            ->assertSessionHas('analytics_insight_error', 'L’integrazione AI attiva deve essere verificata prima di generare l’analisi.');

        Http::assertNothingSent();
    }

    /** @return array<string, mixed> */
    private function analysis(): array
    {
        return [
            'summary' => 'Il traffico cresce, ma va verificata la qualità delle visite.',
            'findings' => [[
                'type' => 'opportunity',
                'priority' => 'high',
                'title' => 'Più sessioni rispetto al periodo precedente',
                'explanation' => 'La crescita è positiva e merita un controllo sulle pagine che la sostengono.',
                'evidence' => ['2.345 sessioni contro 2.000, +17,25%.'],
                'recommended_action' => 'Controlla le landing page in crescita e aggiorna i contenuti più rilevanti.',
                'confidence' => 'high',
            ]],
            'next_actions' => [[
                'title' => 'Esamina le landing page',
                'why' => 'Aiuta a capire quali contenuti sostengono la crescita.',
                'effort' => 'low',
                'priority' => 'high',
            ]],
            'things_to_avoid' => [[
                'title' => 'Non attribuire la crescita a una singola modifica',
                'reason' => 'GA4 mostra una correlazione temporale, non una causa.',
            ]],
            'limitations' => ['GA4 non mostra query, impressioni, CTR o posizione organica.'],
        ];
    }

    /** @return array<string, mixed> */
    private function overviewPayload(): array
    {
        return [
            'reports' => [
                $this->metricReport(
                    ['activeUsers', 'sessions', 'screenPageViews', 'engagedSessions', 'engagementRate', 'averageSessionDuration'],
                    ['1234', '2345', '6789', '1800', '0.7676', '95.4'],
                ),
                [
                    'dimensionHeaders' => $this->headers(['date']),
                    'metricHeaders' => $this->headers(['sessions', 'activeUsers', 'screenPageViews']),
                    'rows' => [[
                        'dimensionValues' => $this->values(['20261016']),
                        'metricValues' => $this->values(['120', '90', '280']),
                    ]],
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
                    'rows' => [[
                        'dimensionValues' => $this->values(['Italy', 'desktop']),
                        'metricValues' => $this->values(['1000']),
                    ]],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function insightPayload(): array
    {
        return [
            'reports' => [
                $this->metricReport(
                    ['activeUsers', 'sessions', 'screenPageViews', 'engagedSessions', 'engagementRate', 'averageSessionDuration'],
                    ['1000', '2000', '6000', '1400', '0.7', '80'],
                ),
                [
                    'dimensionHeaders' => $this->headers(['landingPagePlusQueryString']),
                    'metricHeaders' => $this->headers(['sessions', 'activeUsers', 'engagementRate', 'averageSessionDuration']),
                    'rows' => [[
                        'dimensionValues' => $this->values(['/articles/example?utm_source=private-campaign']),
                        'metricValues' => $this->values(['800', '700', '0.82', '110']),
                    ]],
                ],
                [
                    'dimensionHeaders' => $this->headers(['deviceCategory']),
                    'metricHeaders' => $this->headers(['sessions', 'activeUsers', 'engagementRate', 'averageSessionDuration']),
                    'rows' => [[
                        'dimensionValues' => $this->values(['desktop']),
                        'metricValues' => $this->values(['1000', '800', '0.8', '100']),
                    ]],
                ],
            ],
        ];
    }

    /** @param array<int, string> $metrics @param array<int, string> $values */
    private function metricReport(array $metrics, array $values): array
    {
        return [
            'metricHeaders' => $this->headers($metrics),
            'rows' => [['metricValues' => $this->values($values)]],
        ];
    }

    /** @param array<int, string> $names */
    private function headers(array $names): array
    {
        return array_map(fn (string $name): array => ['name' => $name], $names);
    }

    /** @param array<int, string> $values */
    private function values(array $values): array
    {
        return array_map(fn (string $value): array => ['value' => $value], $values);
    }

    private function connectAnalytics(User $admin, Site $site): void
    {
        $connection = GoogleAnalyticsConnection::query()->create([
            'authorized_by_user_id' => $admin->id,
            'access_token' => 'synthetic-access-token',
            'refresh_token' => 'synthetic-refresh-token',
            'token_expires_at' => now()->addHour(),
            'scopes' => ['https://www.googleapis.com/auth/analytics.readonly'],
            'status' => 'active',
        ]);

        $site->googleAnalyticsProperty()->create([
            'google_analytics_connection_id' => $connection->id,
            'account_id' => '123456',
            'property_id' => '987654',
            'property_name' => 'Synthetic Property',
            'last_verified_at' => now(),
        ]);
    }

    private function connectSearchConsole(User $admin, Site $site): void
    {
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
    }

    private function createAiIntegration(User $admin, string $status = 'verified'): AiIntegration
    {
        return AiIntegration::query()->create([
            'name' => 'OpenAI test',
            'provider' => 'openai',
            'model' => 'gpt-5.4-mini',
            'api_key' => 'synthetic-openai-key',
            'active_marker' => 'active',
            'configured_by_user_id' => $admin->id,
            'configured_at' => now(),
            'last_tested_at' => $status === 'verified' ? now() : null,
            'status' => $status,
        ]);
    }
}
