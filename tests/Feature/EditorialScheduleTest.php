<?php

namespace Tests\Feature;

use App\Models\AiIntegration;
use App\Models\EditorialContent;
use App\Models\Site;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EditorialScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_requires_admin_verified_source_and_ai(): void
    {
        $site = Site::factory()->create();
        $admin = User::factory()->administrator()->create();
        $user = User::factory()->create(['is_admin' => false]);
        $values = ['enabled' => '1', 'interval_hours' => 24, 'start_time' => '09:00', 'timezone' => 'Europe/Rome'];

        $this->actingAs($user)->put(route('sites.editorial-plan.schedule.update', $site), $values)->assertForbidden();
        $this->actingAs($admin)->put(route('sites.editorial-plan.schedule.update', $site), [
            ...$values, 'interval_hours' => 1, 'timezone' => 'Invalid/Zone',
        ])->assertSessionHasErrors(['interval_hours', 'timezone']);
        $this->actingAs($admin)->put(route('sites.editorial-plan.schedule.update', $site), $values)->assertSessionHasErrors('enabled');
        $this->assertDatabaseCount('editorial_schedules', 0);

        $this->source($site);
        $this->actingAs($admin)->put(route('sites.editorial-plan.schedule.update', $site), $values)->assertSessionHasErrors('enabled');
        $this->ai($admin);
        $this->actingAs($admin)->put(route('sites.editorial-plan.schedule.update', $site), $values)->assertSessionHas('status');
        $this->assertTrue($site->editorialSchedule->enabled);
        $this->assertNotNull($site->editorialSchedule->next_run_at);

        $this->actingAs($admin)->put(route('sites.editorial-plan.schedule.update', $site), [
            ...$values, 'enabled' => '0',
        ])->assertSessionHas('status');
        $this->assertFalse($site->editorialSchedule->fresh()->enabled);
        $this->assertNull($site->editorialSchedule->fresh()->next_run_at);
    }

    public function test_due_schedule_imports_and_proposes_without_manual_actions_or_x_writes(): void
    {
        config()->set('app.env', 'local');
        $this->travelTo(CarbonImmutable::parse('2026-10-23 10:00:00', 'UTC'));
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $this->source($site);
        $this->ai($admin);
        $this->actingAs($admin)->put(route('sites.editorial-plan.schedule.update', $site), [
            'enabled' => '1', 'interval_hours' => 24, 'start_time' => '09:00', 'timezone' => 'Europe/Rome',
        ])->assertSessionHas('status');

        Http::preventStrayRequests();
        Http::fake([
            'http://localhost:8080/api/reader/chapters/format/json?page=1&per_page=20&orderby=desc_created' => Http::response(['chapters' => [
                0 => $this->chapter(10), 2 => $this->chapter(11),
            ]]),
            'http://localhost:8080/api/reader/chapters/format/json?page=2&per_page=20&orderby=desc_created' => Http::response(['chapters' => []]),
            'https://api.openai.com/v1/responses' => Http::response([
                'id' => 'resp_scheduled_test',
                'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode([
                    'cadence' => ['posts_per_week' => 2, 'confidence' => 'low', 'rationale' => 'Dati non disponibili; provare due post a settimana.'],
                    'proposals' => [['content_id' => 1, 'post_text' => 'Nuova uscita disponibile.', 'rationale' => 'Uscita recente; dati di traffico non disponibili.', 'day_offset' => 1, 'hour' => 18]],
                ])]]]],
                'usage' => ['input_tokens' => 80, 'output_tokens' => 40, 'total_tokens' => 120],
            ]),
        ]);

        $this->artisan('editorial:run-due')->assertSuccessful();

        $this->assertDatabaseCount('editorial_contents', 2);
        $this->assertDatabaseCount('editorial_proposals', 1);
        $this->assertDatabaseCount('editorial_cadence_recommendations', 1);
        $this->assertSame('pending', EditorialContent::query()->firstOrFail()->eligibility_status);
        $schedule = $site->editorialSchedule->fresh();
        $this->assertNull($schedule->last_error);
        $this->assertSame(2, $schedule->last_imported_count);
        $this->assertSame(1, $schedule->last_proposal_count);
        $this->assertSame('2026-10-24 07:00:00', $schedule->next_run_at->format('Y-m-d H:i:s'));
        $this->actingAs($admin)->get(route('sites.editorial-plan.show', ['site' => $site, 'month' => '2026-10']))
            ->assertOk()->assertSee('Uscita recente; dati di traffico non disponibili.')->assertSee('Bozza')
            ->assertSee('2 post a settimana')->assertSee('metriche dei post X non ancora raccolte');
        $this->artisan('editorial:run-due')->assertSuccessful();
        Http::assertSentCount(3);
    }

    public function test_failed_check_is_visible_and_retried_without_publishing(): void
    {
        config()->set('app.env', 'local');
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $this->source($site);
        $this->ai($admin);
        $this->actingAs($admin)->put(route('sites.editorial-plan.schedule.update', $site), [
            'enabled' => '1', 'interval_hours' => 6, 'start_time' => '08:00', 'timezone' => 'UTC',
        ])->assertSessionHas('status');
        Http::preventStrayRequests();
        Http::fake(['http://localhost:8080/api/reader/chapters/format/json*' => Http::response(['error' => 'unavailable'], 500)]);

        $this->artisan('editorial:run-due')->assertSuccessful();

        $schedule = $site->editorialSchedule->fresh();
        $this->assertNotNull($schedule->last_error);
        $this->assertNotNull($schedule->next_run_at);
        $this->assertDatabaseCount('editorial_proposals', 0);
        $this->actingAs($admin)->get(route('sites.editorial-plan.show', $site))->assertSee('Ultimo tentativo non riuscito');

        for ($attempt = 2; $attempt <= 3; $attempt++) {
            $this->travelTo($schedule->next_run_at->addSecond());
            $this->artisan('editorial:run-due')->assertSuccessful();
            $schedule->refresh();
        }

        $this->assertFalse($schedule->enabled);
        $this->assertSame(3, $schedule->consecutive_failures);
        $this->assertNull($schedule->next_run_at);
        $this->actingAs($admin)->get(route('sites.editorial-plan.show', $site))->assertSee('Piano sospeso dopo tre errori');
    }

    private function source(Site $site): void
    {
        $site->editorialSources()->create([
            'provider' => 'foolslide', 'name' => 'Archivio', 'base_url' => 'http://localhost:8080',
            'is_active' => true, 'verification_status' => 'verified',
        ]);
    }

    private function ai(User $admin): void
    {
        AiIntegration::query()->create([
            'name' => 'AI test', 'provider' => 'openai', 'model' => 'synthetic-model',
            'api_key' => 'synthetic-key', 'active_marker' => 'active',
            'configured_by_user_id' => $admin->id, 'status' => 'verified', 'configured_at' => now(),
        ]);
    }

    private function chapter(int $id): array
    {
        return [
            'comic' => ['id' => 5, 'name' => 'Serie di prova', 'hidden' => 0],
            'chapter' => ['id' => $id, 'title' => 'Capitolo '.$id, 'href' => 'http://localhost:8080/chapter/'.$id, 'hidden' => 0, 'created' => '2026-10-22 12:00:00'],
        ];
    }
}
