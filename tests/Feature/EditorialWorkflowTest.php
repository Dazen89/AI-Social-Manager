<?php

namespace Tests\Feature;

use App\Jobs\RunEditorialAnalysis;
use App\Models\AiIntegration;
use App\Models\EditorialContent;
use App\Models\EditorialSource;
use App\Models\Site;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EditorialWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_is_bounded_deduplicated_and_requires_manual_eligibility(): void
    {
        config()->set('app.env', 'local');
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $source = $this->source($site);
        Http::preventStrayRequests();
        Http::fake(['http://localhost:8080/api/reader/chapters/format/json*' => Http::response([
            'chapters' => [
                $this->chapter(10, 'https://evil.example/chapter'),
                $this->chapter(11, 'http://localhost:8080/chapter/11', true),
                $this->chapter(12, 'http://localhost:8080/chapter/12'),
            ],
        ])]);

        $this->actingAs($admin)->post(route('sites.editorial-plan.sources.import', [$site, $source]))->assertSessionHas('status');
        $this->actingAs($admin)->post(route('sites.editorial-plan.sources.import', [$site, $source]))->assertSessionHas('status');

        $this->assertDatabaseCount('editorial_contents', 1);
        $item = EditorialContent::query()->sole();
        $this->assertSame('pending', $item->eligibility_status);
        $this->assertSame('12', $item->external_id);
        $this->actingAs($admin)->get(route('sites.editorial-plan.contents.index', $site))
            ->assertOk()->assertSee('Conferma idoneità');
        Http::assertSentCount(4);
    }

    public function test_sparse_numeric_chapter_object_from_foolslide_is_imported(): void
    {
        config()->set('app.env', 'local');
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $source = $this->source($site);
        Http::preventStrayRequests();
        Http::fake([
            'http://localhost:8080/api/reader/chapters/format/json?page=1&per_page=20&orderby=desc_created' => Http::response([
                'chapters' => [0 => $this->chapter(12, 'http://localhost:8080/chapter/12'), 2 => $this->chapter(13, 'http://localhost:8080/chapter/13')],
            ]),
            'http://localhost:8080/api/reader/chapters/format/json?page=2&per_page=20&orderby=desc_created' => Http::response(['chapters' => []]),
        ]);

        $this->actingAs($admin)->post(route('sites.editorial-plan.sources.import', [$site, $source]))
            ->assertSessionHas('status');

        $this->assertDatabaseCount('editorial_contents', 2);
        Http::assertSentCount(2);
    }

    public function test_pending_content_can_become_an_ai_draft_but_approval_requires_eligibility_and_does_not_publish(): void
    {
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $source = $this->source($site);
        $eligible = $this->content($site, $source, '1');
        $pending = $this->content($site, $source, '2');
        $pending->update(['eligibility_status' => 'excluded']);
        $this->ai($admin);
        Http::preventStrayRequests();
        Http::fake(['https://api.openai.com/v1/responses' => Http::response([
            'id' => 'resp_editorial_test',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode([
                'cadence' => ['posts_per_week' => 7, 'confidence' => 'low', 'rationale' => 'Dati GA4, Search Console e X assenti: mantenere il piano giornaliero e misurare.'],
                'proposals' => [['content_id' => $eligible->id, 'post_text' => 'Nuova uscita disponibile.', 'rationale' => 'Uscita recente; dati di performance non disponibili.', 'day_offset' => 1, 'hour' => 18]],
            ])]]]],
            'usage' => ['input_tokens' => 80, 'output_tokens' => 40, 'total_tokens' => 120],
        ])]);

        $this->actingAs($admin)->post(route('sites.editorial-plan.proposals.generate', $site))
            ->assertSessionHas('status');

        $proposal = $eligible->proposal()->sole();
        $this->assertSame(7, $site->editorialCadenceRecommendations()->sole()->posts_per_week);
        $this->assertSame('low', $site->editorialCadenceRecommendations()->sole()->confidence);
        $this->assertSame('draft', $proposal->status);
        $this->assertStringEndsWith($eligible->public_url, $proposal->post_text);
        $this->assertNull($pending->proposal);
        $this->actingAs($admin)->patch(route('sites.editorial-plan.proposals.review', [$site, $proposal]), ['status' => 'approved'])
            ->assertSessionHasErrors('status');
        $this->actingAs($admin)->patch(route('sites.editorial-plan.contents.eligibility', [$site, $eligible]), ['status' => 'eligible'])
            ->assertSessionHas('status');
        $this->actingAs($admin)->patch(route('sites.editorial-plan.proposals.review', [$site, $proposal]), ['status' => 'approved'])
            ->assertSessionHas('status');
        $this->assertSame('approved', $proposal->fresh()->status);
        $this->actingAs($admin)->get(route('sites.editorial-plan.show', $site))
            ->assertOk()->assertSee('Nuova uscita disponibile.')->assertSee('Approvata')
            ->assertSee('Cadenza editoriale giornaliera')->assertSee('mantenere il piano giornaliero');
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/responses');
    }

    public function test_italian_confidence_is_normalized_and_schema_requests_canonical_values(): void
    {
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $source = $this->source($site);
        $content = $this->content($site, $source, '3');
        $this->ai($admin);
        Http::preventStrayRequests();
        Http::fake(['https://api.openai.com/v1/responses' => Http::response([
            'id' => 'resp_italian_confidence_test',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode([
                'cadence' => ['posts_per_week' => 7, 'confidence' => 'Bassa', 'rationale' => 'I dati disponibili non bastano per una stima affidabile.'],
                'proposals' => [['content_id' => $content->id, 'post_text' => 'Nuovo capitolo disponibile.', 'rationale' => 'Uscita recente.', 'day_offset' => 1, 'hour' => 18]],
            ])]]]],
            'usage' => ['input_tokens' => 30, 'output_tokens' => 15, 'total_tokens' => 45],
        ])]);

        $this->actingAs($admin)->post(route('sites.editorial-plan.proposals.generate', $site))
            ->assertSessionHas('status');

        $this->assertSame('low', $site->editorialCadenceRecommendations()->sole()->confidence);
        $this->assertSame('draft', $content->proposal()->sole()->status);
        Http::assertSent(fn ($request) => data_get($request->data(), 'text.format.schema.properties.cadence.properties.confidence.enum') === ['low', 'medium', 'high']);
        Http::assertSent(fn ($request) => data_get($request->data(), 'text.format.schema.properties.cadence.properties.posts_per_week.enum') === [7]
            && data_get($request->data(), 'text.format.schema.properties.proposals.minItems') === 1
            && data_get($request->data(), 'text.format.schema.properties.proposals.maxItems') === 1
            && data_get($request->data(), 'text.format.schema.properties.proposals.items.properties.content_id.enum') === [$content->id]
            && data_get($request->data(), 'text.format.schema.properties.proposals.items.properties.day_offset.enum') === range(1, 14)
            && data_get($request->data(), 'text.format.schema.properties.proposals.items.properties.hour.enum') === range(8, 22));
    }

    public function test_out_of_range_ai_cadence_does_not_save_a_draft(): void
    {
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $source = $this->source($site);
        $content = $this->content($site, $source, '4');
        $this->ai($admin);
        Http::preventStrayRequests();
        Http::fake(['https://api.openai.com/v1/responses' => Http::response([
            'id' => 'resp_invalid_cadence_test',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode([
                'cadence' => ['posts_per_week' => 20, 'confidence' => 'low', 'rationale' => 'Frequenza eccessiva.'],
                'proposals' => [['content_id' => $content->id, 'post_text' => 'Nuovo capitolo disponibile.', 'rationale' => 'Uscita recente.', 'day_offset' => 1, 'hour' => 18]],
            ])]]]],
            'usage' => ['input_tokens' => 30, 'output_tokens' => 15, 'total_tokens' => 45],
        ])]);

        $this->actingAs($admin)->post(route('sites.editorial-plan.proposals.generate', $site))
            ->assertSessionHas('workflow_error', 'Il servizio AI non ha rispettato la cadenza giornaliera di 7 post a settimana. Nessuna bozza è stata salvata.');

        $this->assertDatabaseCount('editorial_cadence_recommendations', 0);
        $this->assertDatabaseCount('editorial_proposals', 0);
    }

    public function test_daily_plan_prioritizes_recent_releases_and_fills_fourteen_days_with_older_work(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-06 10:00:00', 'UTC'));
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $source = $this->source($site);
        $contents = collect(range(1, 14))->map(function (int $number) use ($site, $source): EditorialContent {
            $content = $this->content($site, $source, (string) $number);
            $content->update(['released_at' => now()->subDays($number)]);

            return $content;
        });
        $expectedIds = $contents->pluck('id')->all();
        $this->ai($admin);
        Http::preventStrayRequests();
        Http::fake(['https://api.openai.com/v1/responses' => Http::response([
            'id' => 'resp_daily_plan_test',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode([
                'cadence' => ['posts_per_week' => 7, 'confidence' => 'low', 'rationale' => 'Piano giornaliero; metriche non disponibili.'],
                'proposals' => $contents->values()->map(fn (EditorialContent $content, int $index): array => [
                    'content_id' => $content->id,
                    'post_text' => 'Lavoro disponibile '.$content->external_id.'.',
                    'rationale' => $index === 0 ? 'Uscita più recente.' : 'Archivio ordinato per data di uscita.',
                    'day_offset' => $index + 1,
                    'hour' => 18,
                ])->all(),
            ])]]]],
            'usage' => ['input_tokens' => 200, 'output_tokens' => 100, 'total_tokens' => 300],
        ])]);

        $this->actingAs($admin)->post(route('sites.editorial-plan.proposals.generate', $site))
            ->assertSessionHas('status');

        $this->assertDatabaseCount('editorial_proposals', 14);
        $this->assertSame(7, $site->editorialCadenceRecommendations()->sole()->posts_per_week);
        $this->assertSame(
            $expectedIds,
            $site->editorialProposals()->orderBy('proposed_for')->pluck('editorial_content_id')->all(),
        );
        $this->assertCount(
            14,
            $site->editorialProposals()->get()->map(fn ($proposal) => $proposal->proposed_for->toDateString())->unique(),
        );
        Http::assertSent(fn ($request) => data_get($request->data(), 'text.format.schema.properties.proposals.minItems') === 14
            && data_get($request->data(), 'text.format.schema.properties.proposals.maxItems') === 14
            && data_get($request->data(), 'text.format.schema.properties.proposals.items.properties.content_id.enum') === $expectedIds);
    }

    public function test_incomplete_daily_plan_is_rejected_without_saving_drafts(): void
    {
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $source = $this->source($site);
        $older = $this->content($site, $source, '1');
        $newer = $this->content($site, $source, '2');
        $older->update(['released_at' => now()->subDay()]);
        $this->ai($admin);
        Http::preventStrayRequests();
        Http::fake(['https://api.openai.com/v1/responses' => Http::response([
            'id' => 'resp_incomplete_daily_plan_test',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode([
                'cadence' => ['posts_per_week' => 7, 'confidence' => 'low', 'rationale' => 'Piano giornaliero.'],
                'proposals' => [[
                    'content_id' => $newer->id,
                    'post_text' => 'Nuova uscita disponibile.',
                    'rationale' => 'Uscita recente.',
                    'day_offset' => 1,
                    'hour' => 18,
                ]],
            ])]]]],
            'usage' => ['input_tokens' => 30, 'output_tokens' => 15, 'total_tokens' => 45],
        ])]);

        $this->actingAs($admin)->post(route('sites.editorial-plan.proposals.generate', $site))
            ->assertSessionHas('workflow_error', 'Il servizio AI non ha coperto tutti i giorni disponibili del piano editoriale. Nessuna bozza è stata salvata.');

        $this->assertDatabaseCount('editorial_cadence_recommendations', 0);
        $this->assertDatabaseCount('editorial_proposals', 0);
    }

    public function test_invalid_candidate_and_schedule_are_reported_without_saving_drafts(): void
    {
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $source = $this->source($site);
        $content = $this->content($site, $source, '5');
        $this->ai($admin);
        Http::preventStrayRequests();
        $response = fn (int $id, int $offset): array => [
            'id' => 'resp_invalid_proposal_test',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode([
                'cadence' => ['posts_per_week' => 7, 'confidence' => 'low', 'rationale' => 'Test prudente.'],
                'proposals' => [['content_id' => $id, 'post_text' => 'Nuovo capitolo disponibile.', 'rationale' => 'Uscita recente.', 'day_offset' => $offset, 'hour' => 18]],
            ])]]]],
            'usage' => ['input_tokens' => 30, 'output_tokens' => 15, 'total_tokens' => 45],
        ];
        Http::fakeSequence()->push($response($content->id + 100, 1))->push($response($content->id, 0));

        $this->actingAs($admin)->post(route('sites.editorial-plan.proposals.generate', $site))
            ->assertSessionHas('workflow_error', 'Il servizio AI ha scelto un capitolo che non è tra i candidati. Nessuna bozza è stata salvata.');
        $this->actingAs($admin)->post(route('sites.editorial-plan.proposals.generate', $site))
            ->assertSessionHas('workflow_error', 'Il servizio AI ha scelto una data o un’ora fuori dai limiti consentiti. Nessuna bozza è stata salvata.');

        $this->assertDatabaseCount('editorial_cadence_recommendations', 0);
        $this->assertDatabaseCount('editorial_proposals', 0);
    }

    public function test_other_sites_and_non_admins_cannot_change_editorial_items(): void
    {
        $admin = User::factory()->administrator()->create();
        $other = User::factory()->create(['is_admin' => false]);
        $site = Site::factory()->create();
        $anotherSite = Site::factory()->create();
        $source = $this->source($site);
        $item = $this->content($site, $source, '1');

        $this->actingAs($other)->patch(route('sites.editorial-plan.contents.eligibility', [$site, $item]), ['status' => 'eligible'])
            ->assertForbidden();
        $this->actingAs($admin)->patch(route('sites.editorial-plan.contents.eligibility', [$anotherSite, $item]), ['status' => 'eligible'])
            ->assertNotFound();
        $this->actingAs($admin)->post(route('sites.editorial-plan.sources.import', [$anotherSite, $source]))
            ->assertNotFound();
        $this->assertSame('pending', $item->fresh()->eligibility_status);
    }

    public function test_imported_releases_can_be_reviewed_below_the_post_calendar(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-23 12:00:00', 'UTC'));
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $source = $this->source($site);
        $current = $this->content($site, $source, '12');
        $older = $this->content($site, $source, '13');
        $current->update(['released_at' => '2026-10-22 12:00:00']);
        $older->update(['released_at' => '2026-09-22 12:00:00']);

        $this->actingAs($admin)->get(route('sites.editorial-plan.show', $site))
            ->assertOk()
            ->assertSee('Non hai ancora attivato i controlli periodici')
            ->assertSee('Capitoli letti dalle fonti')
            ->assertSee('Capitolo 12')
            ->assertSee('Serie di prova')
            ->assertSee('Capitolo 13')
            ->assertDontSee('Uscita · Capitolo');

        $this->actingAs($admin)->get(route('sites.editorial-plan.show', ['site' => $site, 'month' => '2026-09']))
            ->assertOk()->assertSee('Capitolo 13')->assertSee('Capitolo 12');
    }

    public function test_chapter_review_distinguishes_two_chapters_of_the_same_series(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-23 12:00:00', 'UTC'));
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $source = $this->source($site);
        $first = $this->content($site, $source, '12');
        $second = $this->content($site, $source, '13');
        $first->update(['released_at' => '2026-10-22 12:00:00']);
        $second->update(['released_at' => '2026-10-22 13:00:00']);

        $this->actingAs($admin)->get(route('sites.editorial-plan.show', $site))
            ->assertOk()->assertSee('Capitolo 12')->assertSee('Capitolo 13')
            ->assertSee('Nessun post proposto');
    }

    public function test_manual_control_reanalyses_known_chapters_even_with_no_new_releases(): void
    {
        config()->set('app.env', 'local');
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $source = $this->source($site);
        $content = $this->content($site, $source, '12');
        $this->ai($admin);
        Http::preventStrayRequests();
        Http::fake([
            'http://localhost:8080/api/reader/chapters/format/json?page=1&per_page=20&orderby=desc_created' => Http::response(['chapters' => [$this->chapter(12, 'http://localhost:8080/chapter/12')]]),
            'http://localhost:8080/api/reader/chapters/format/json?page=2&per_page=20&orderby=desc_created' => Http::response(['chapters' => []]),
            'https://api.openai.com/v1/responses' => Http::response([
                'id' => 'resp_manual_test',
                'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode([
                    'cadence' => ['posts_per_week' => 7, 'confidence' => 'low', 'rationale' => 'Nessun dato di performance: mantenere il piano giornaliero e misurare.'],
                    'proposals' => [['content_id' => $content->id, 'post_text' => 'Capitolo disponibile.', 'rationale' => 'Unico lavoro disponibile.', 'day_offset' => 1, 'hour' => 18]],
                ])]]]],
                'usage' => ['input_tokens' => 30, 'output_tokens' => 15, 'total_tokens' => 45],
            ]),
        ]);

        $this->actingAs($admin)->post(route('sites.editorial-plan.run', $site))->assertSessionHas('status');
        $this->actingAs($admin)->post(route('sites.editorial-plan.run', $site))->assertSessionHas('status');

        $this->assertDatabaseCount('editorial_contents', 1);
        $this->assertDatabaseCount('editorial_cadence_recommendations', 2);
        $this->assertDatabaseCount('ai_usage_records', 2);
        $this->assertNotNull($site->editorialSchedule()->firstOrFail()->last_run_at);
        Http::assertSentCount(6);
    }

    public function test_manual_analysis_is_queued_without_holding_open_the_web_request(): void
    {
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        Queue::fake();

        $this->actingAs($admin)->post(route('sites.editorial-plan.run', $site))
            ->assertSessionHas('status', 'Analisi avviata in background. Il calendario si aggiornerà al completamento; puoi continuare a usare la dashboard.');

        Queue::assertPushed(RunEditorialAnalysis::class, fn (RunEditorialAnalysis $job): bool => $job->siteId === $site->id);
        $this->assertDatabaseCount('editorial_schedules', 0);
    }

    public function test_ai_automatically_approves_an_authorized_source_when_publication_is_enabled(): void
    {
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $source = $this->source($site);
        $source->update(['promotion_authorized_at' => now(), 'promotion_authorized_by_user_id' => $admin->id]);
        $content = $this->content($site, $source, '7');
        $content->update(['eligibility_status' => 'eligible']);
        $site->editorialSchedule()->create([
            'enabled' => true, 'x_autopublish_enabled' => true, 'interval_hours' => 24,
            'start_time' => '09:00', 'timezone' => 'UTC', 'next_run_at' => now()->addDay(),
        ]);
        $this->ai($admin);
        Http::preventStrayRequests();
        Http::fake(['https://api.openai.com/v1/responses' => Http::response([
            'id' => 'resp_auto_approval_test',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode([
                'cadence' => ['posts_per_week' => 7, 'confidence' => 'low', 'rationale' => 'Test prudente.'],
                'proposals' => [['content_id' => $content->id, 'post_text' => 'Capitolo disponibile.', 'rationale' => 'Uscita recente.', 'day_offset' => 1, 'hour' => 18]],
            ])]]]],
            'usage' => ['input_tokens' => 30, 'output_tokens' => 15, 'total_tokens' => 45],
        ])]);

        $this->actingAs($admin)->post(route('sites.editorial-plan.proposals.generate', $site))->assertSessionHas('status');

        $this->assertSame('approved', $content->proposal()->sole()->status);
        Http::assertSentCount(1);
    }

    private function source(Site $site): EditorialSource
    {
        return $site->editorialSources()->create([
            'provider' => 'foolslide', 'name' => 'Archivio', 'base_url' => 'http://localhost:8080',
            'is_active' => true, 'verification_status' => 'verified',
        ]);
    }

    private function content(Site $site, EditorialSource $source, string $id): EditorialContent
    {
        return $source->contents()->create([
            'site_id' => $site->id, 'external_id' => $id, 'series_title' => 'Serie di prova',
            'title' => 'Capitolo '.$id, 'public_url' => 'http://localhost:8080/chapter/'.$id,
            'released_at' => now(), 'last_seen_at' => now(),
        ]);
    }

    private function chapter(int $id, string $url, bool $hidden = false): array
    {
        return [
            'comic' => ['id' => 5, 'name' => 'Serie di prova', 'hidden' => 0],
            'chapter' => ['id' => $id, 'title' => 'Capitolo '.$id, 'href' => $url, 'hidden' => (int) $hidden, 'created' => '2026-10-22 12:00:00'],
        ];
    }

    private function ai(User $admin): void
    {
        AiIntegration::query()->create([
            'name' => 'AI test', 'provider' => 'openai', 'model' => 'synthetic-model',
            'api_key' => 'synthetic-key', 'active_marker' => 'active',
            'configured_by_user_id' => $admin->id, 'status' => 'verified', 'configured_at' => now(),
        ]);
    }
}
