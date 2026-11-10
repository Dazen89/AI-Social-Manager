<?php

namespace Tests\Feature;

use App\Models\EditorialProposal;
use App\Models\Site;
use App\Models\User;
use App\Models\XConnection;
use App\Services\PublicHostResolver;
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

    public function test_proposal_previews_three_images_and_publishes_up_to_four_manually_approved_images(): void
    {
        $proposal = $this->dueProposal();
        $proposal->update(['status' => 'draft']);
        $admin = User::query()->sole();
        $connection = XConnection::query()->sole();
        $connection->update(['scopes' => ['tweet.read', 'tweet.write', 'media.write']]);
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACklEQVR42mNgAAAAAgABSK+kcQAAAABJRU5ErkJggg==');
        $this->mock(PublicHostResolver::class)->shouldReceive('resolve')->with('example.test')->andReturn('93.184.216.34');
        Http::preventStrayRequests();
        Http::fake([
            'https://example.test/api/reader/chapter/format/json?id=42' => Http::response([
                'comic' => ['hidden' => '0', 'adult' => '0'],
                'chapter' => ['id' => 42, 'hidden' => '0'],
                'pages' => array_map(fn (int $id): array => [
                    'id' => $id, 'chapter_id' => 42, 'hidden' => $id === 6 ? '1' : '0',
                    'url' => "https://example.test/pages/{$id}.png", 'mime' => 'image/png', 'size' => 256,
                    'thumb_url' => "https://example.test/thumbs/{$id}.png",
                ], range(1, 6)),
            ]),
            'https://example.test/pages/*.png' => Http::response($bytes, 200, ['Content-Type' => 'image/png']),
            'https://api.x.com/2/media/upload' => Http::sequence()
                ->push(['data' => ['id' => '101']])
                ->push(['data' => ['id' => '102']])
                ->push(['data' => ['id' => '103']])
                ->push(['data' => ['id' => '104']]),
            'https://api.x.com/2/tweets' => Http::response(['data' => ['id' => 'post-123']], 201),
        ]);
        $overview = route('sites.editorial-plan.show', $proposal->site);
        $preview = route('sites.editorial-plan.proposals.images.preview', [$proposal->site, $proposal]);
        $edit = route('sites.editorial-plan.proposals.images.edit', [$proposal->site, $proposal]);
        $save = route('sites.editorial-plan.proposals.images.update', [$proposal->site, $proposal]);

        $this->actingAs($admin)->get($overview)->assertOk()->assertSee('data-image-preview="'.$preview.'"', false);
        Http::assertNothingSent();
        $thumbnails = $this->get($preview)->assertOk()->assertSee('Immagini suggerite');
        $this->assertSame(3, substr_count($thumbnails->getContent(), '<img '));
        $this->assertNull($proposal->fresh()->image_attachments);

        $response = $this->get($edit)->assertOk()->assertSee('Pagina 5')->assertDontSee('Pagina 6')
            ->assertSee('data-image-limit="4"', false);
        $this->assertSame(3, preg_match_all('/name="image_ids\[\]" value="(\d+)" checked/', $response->getContent(), $checked));
        foreach ($checked[1] as $id) {
            $thumbnails->assertSee('https://example.test/thumbs/'.$id.'.png');
        }

        $this->from($edit)->patch($save, ['image_ids' => [1, 2, 3, 4, 5], 'images_confirmed' => '1'])
            ->assertSessionHasErrors(['image_ids' => 'Puoi selezionare al massimo 4 immagini.']);
        $this->from($edit)->patch($save, ['image_ids' => [6], 'images_confirmed' => '1'])->assertSessionHasErrors('image_ids');
        $this->from($edit)->patch($save, ['image_ids' => [1, 2, 4, 5]])->assertSessionHasErrors('images_confirmed');
        $this->from($edit)->patch($save, ['image_ids' => [1, 2, 4, 5], 'images_confirmed' => '1'])
            ->assertSessionHas('status');
        $this->assertSame([1, 2, 4, 5], array_column($proposal->fresh()->image_attachments, 'id'));
        $this->assertSame('draft', $proposal->fresh()->status);
        $this->get($overview)->assertOk()->assertDontSee($preview)->assertSee('https://example.test/thumbs/5.png');

        $this->patch(route('sites.editorial-plan.proposals.review', [$proposal->site, $proposal]), ['status' => 'approved'])
            ->assertSessionHas('status');

        config()->set('services.x.auto_publish_allowed', true);
        config()->set('services.x.test_fake_publish', true);
        $this->artisan('editorial:publish-due')->assertSuccessful();
        $this->artisan('editorial:publish-due')->assertSuccessful();

        $this->assertSame('published', $proposal->fresh()->status);
        $this->assertCount(4, Http::recorded(fn ($request) => $request->url() === 'https://api.x.com/2/media/upload'));
        Http::assertSent(fn ($request) => $request->url() === 'https://api.x.com/2/media/upload'
            && $request['media_category'] === 'tweet_image' && $request['media'] === base64_encode($bytes));
        Http::assertSent(fn ($request) => $request->url() === 'https://api.x.com/2/tweets'
            && data_get($request->data(), 'media.media_ids') === ['101', '102', '103', '104']);
    }

    public function test_an_approved_post_with_images_requires_the_media_scope(): void
    {
        $proposal = $this->dueProposal();
        $proposal->update([
            'reviewed_by_user_id' => User::query()->sole()->id,
            'image_attachments' => [['id' => 1, 'url' => 'https://example.test/pages/1.png', 'sha256' => str_repeat('a', 64)]],
        ]);
        config()->set('services.x.auto_publish_allowed', true);
        config()->set('services.x.test_fake_publish', true);
        Http::preventStrayRequests();

        $this->artisan('editorial:publish-due')->assertSuccessful();

        $this->assertSame('draft', $proposal->fresh()->status);
        $this->assertStringContainsString('media.write', $proposal->fresh()->delivery_error);
        $this->assertNull($proposal->fresh()->delivery_attempted_at);
        Http::assertNothingSent();
    }

    public function test_a_changed_image_returns_the_proposal_to_draft_before_any_x_write(): void
    {
        $proposal = $this->dueProposal();
        $url = 'https://example.test/pages/1.png';
        $proposal->update([
            'reviewed_by_user_id' => User::query()->sole()->id,
            'image_attachments' => [['id' => 1, 'url' => $url, 'sha256' => str_repeat('a', 64)]],
        ]);
        XConnection::query()->sole()->update(['scopes' => ['tweet.read', 'tweet.write', 'media.write']]);
        config()->set('services.x.auto_publish_allowed', true);
        config()->set('services.x.test_fake_publish', true);
        $this->mock(PublicHostResolver::class)->shouldReceive('resolve')->andReturn('93.184.216.34');
        Http::preventStrayRequests();
        Http::fake([
            'https://example.test/api/reader/chapter/format/json?id=42' => Http::response([
                'comic' => ['hidden' => 0, 'adult' => 0],
                'chapter' => ['id' => 42, 'hidden' => 0],
                'pages' => [['id' => 1, 'chapter_id' => 42, 'hidden' => 0, 'url' => $url, 'mime' => 'image/png']],
            ]),
            $url => Http::response(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACklEQVR42mNgAAAAAgABSK+kcQAAAABJRU5ErkJggg==')),
        ]);

        $this->artisan('editorial:publish-due')->assertSuccessful();

        $this->assertSame('draft', $proposal->fresh()->status);
        $this->assertStringContainsString('Un’immagine selezionata è cambiata', $proposal->fresh()->delivery_error);
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
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
