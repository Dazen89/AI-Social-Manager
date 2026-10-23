<?php

namespace Tests\Feature;

use App\Models\EditorialProposal;
use App\Models\Site;
use App\Models\User;
use App\Models\XConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EditorialAutoPublishTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_environment_never_publishes_even_if_the_gate_is_configured(): void
    {
        $proposal = $this->dueProposal();
        config()->set('services.x.auto_publish_allowed', true);
        Http::preventStrayRequests();

        $this->artisan('editorial:publish-due')->assertSuccessful();

        $this->assertSame('approved', $proposal->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_fake_transport_publishes_only_once_and_records_the_x_id(): void
    {
        $proposal = $this->dueProposal();
        config()->set('services.x.auto_publish_allowed', true);
        config()->set('services.x.test_fake_publish', true);
        Http::preventStrayRequests();
        Http::fake(['https://api.x.com/2/tweets' => Http::response(['data' => ['id' => 'post-123']])]);

        $this->artisan('editorial:publish-due')->assertSuccessful();
        $this->artisan('editorial:publish-due')->assertSuccessful();

        $proposal->refresh();
        $this->assertSame('published', $proposal->status);
        $this->assertSame('post-123', $proposal->x_post_id);
        $this->assertNotNull($proposal->published_at);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.x.com/2/tweets'
            && $request['text'] === $proposal->post_text);
    }

    public function test_uncertain_api_result_is_not_retried_automatically(): void
    {
        $proposal = $this->dueProposal();
        config()->set('services.x.auto_publish_allowed', true);
        config()->set('services.x.test_fake_publish', true);
        Http::preventStrayRequests();
        Http::fake(['https://api.x.com/2/tweets' => Http::response(['error' => 'uncertain'], 503)]);

        $this->artisan('editorial:publish-due')->assertSuccessful();
        $this->artisan('editorial:publish-due')->assertSuccessful();

        $this->assertSame('delivery_uncertain', $proposal->fresh()->status);
        Http::assertSentCount(1);
    }

    private function dueProposal(): EditorialProposal
    {
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create(['host' => 'example.test', 'base_url' => 'https://example.test']);
        $source = $site->editorialSources()->create([
            'provider' => 'foolslide', 'name' => 'Archivio', 'base_url' => 'https://example.test',
            'is_active' => true, 'verification_status' => 'verified',
            'promotion_authorized_at' => now(), 'promotion_authorized_by_user_id' => $admin->id,
        ]);
        $content = $source->contents()->create([
            'site_id' => $site->id, 'external_id' => '42', 'series_title' => 'Serie',
            'title' => 'Capitolo 42', 'public_url' => 'https://example.test/chapter/42',
            'eligibility_status' => 'eligible', 'last_seen_at' => now(),
        ]);
        $site->editorialSchedule()->create([
            'enabled' => true, 'x_autopublish_enabled' => true, 'interval_hours' => 24,
            'start_time' => '09:00', 'timezone' => 'UTC', 'next_run_at' => now()->addDay(),
        ]);
        XConnection::query()->create([
            'authorized_by_user_id' => $admin->id, 'x_user_id' => '123', 'username' => 'publisher',
            'display_name' => 'Publisher', 'access_token' => 'synthetic-token',
            'token_expires_at' => now()->addDay(), 'scopes' => ['tweet.read', 'tweet.write'], 'status' => 'active',
        ]);

        return $site->editorialProposals()->create([
            'editorial_content_id' => $content->id, 'platform' => 'x', 'status' => 'approved',
            'post_text' => 'Nuovo capitolo. https://example.test/chapter/42',
            'rationale' => 'Uscita recente.', 'proposed_for' => now()->subMinute(),
        ]);
    }
}
