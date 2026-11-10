<?php

namespace Tests\Feature;

use App\Models\EditorialProposal;
use App\Models\Site;
use App\Models\User;
use App\Models\XConnection;
use App\Services\PublicHostResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EditorialAutoPublishTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_environment_never_publishes_even_if_the_gate_is_configured(): void
    {
        $proposal = $this->dueProposal();
        Http::preventStrayRequests();

        $this->artisan('editorial:publish-due')->assertSuccessful();

        $this->assertSame('approved', $proposal->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_fake_transport_publishes_only_once_and_records_the_x_id(): void
    {
        $proposal = $this->dueProposal();
        XConnection::query()->sole()->update(['manual_publishing_enabled' => false]);
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

    public function test_suspending_automatic_publishing_preserves_manual_publishing_and_the_site_schedule(): void
    {
        $proposal = $this->dueProposal();
        $connection = XConnection::query()->sole();
        config()->set('services.x.test_fake_publish', true);
        Http::preventStrayRequests();

        $this->actingAs(User::query()->sole())->patch(route('x.publishing.update'), [
            'account' => $connection->x_user_id, 'mode' => 'automatic', 'enabled' => '0',
        ])->assertSessionHas('status');

        $this->artisan('editorial:publish-due')->assertSuccessful();

        $this->assertFalse($connection->fresh()->automatic_publishing_enabled);
        $this->assertTrue($connection->fresh()->manual_publishing_enabled);
        $this->assertTrue($proposal->site->editorialSchedule()->sole()->x_autopublish_enabled);
        $this->assertSame('approved', $proposal->fresh()->status);
        $this->assertNull($proposal->fresh()->delivery_attempted_at);
        Http::assertNothingSent();
    }

    public function test_uncertain_api_result_is_not_retried_automatically(): void
    {
        $proposal = $this->dueProposal();
        config()->set('services.x.test_fake_publish', true);
        Http::preventStrayRequests();
        Http::fake(['https://api.x.com/2/tweets' => Http::response(['error' => 'uncertain'], 503)]);

        $this->artisan('editorial:publish-due')->assertSuccessful();
        $this->artisan('editorial:publish-due')->assertSuccessful();

        $this->assertSame('delivery_uncertain', $proposal->fresh()->status);
        $this->assertStringContainsString('HTTP 503', $proposal->fresh()->delivery_error);
        Http::assertSentCount(1);
    }

    public function test_a_rejected_manual_post_returns_to_draft_with_a_safe_error_message(): void
    {
        $proposal = $this->dueProposal();
        $proposal->update(['status' => 'draft', 'image_attachments' => []]);
        $endpoint = route('sites.editorial-plan.proposals.publish.confirm', [$proposal->site, $proposal]);
        config()->set('services.x.test_fake_publish', true);
        Http::preventStrayRequests();
        Http::fake(['https://api.x.com/2/tweets' => Http::response(['detail' => 'Synthetic provider detail'], 403)]);

        $confirmation = $this->actingAs(User::query()->sole())->get($endpoint)->assertOk()->viewData('confirmation');
        $this->post($endpoint, ['confirmation' => $confirmation])->assertSessionHas('workflow_error', fn (string $message) => str_contains($message, 'HTTP 403'));
        $this->artisan('editorial:publish-due')->assertSuccessful();

        $proposal->refresh();
        $this->assertSame('draft', $proposal->status);
        $this->assertNull($proposal->delivery_attempted_at);
        $this->assertNull($proposal->x_post_id);
        $this->assertStringContainsString('HTTP 403', $proposal->delivery_error);
        $this->assertStringNotContainsString('Synthetic provider detail', $proposal->delivery_error);
        Http::assertSentCount(1);
    }

    public function test_expired_authorization_returns_the_post_to_draft_before_sending(): void
    {
        $proposal = $this->dueProposal();
        XConnection::query()->sole()->update(['token_expires_at' => now()->subMinute(), 'refresh_token' => null]);
        config()->set('services.x.test_fake_publish', true);
        Http::preventStrayRequests();

        $this->artisan('editorial:publish-due')->assertSuccessful();

        $this->assertSame('draft', $proposal->fresh()->status);
        $this->assertStringContainsString('L’autorizzazione X è scaduta', $proposal->fresh()->delivery_error);
        Http::assertNothingSent();
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

    #[DataProvider('manualImageSelections')]
    public function test_publish_now_sends_the_confirmed_text_and_images_without_an_automatic_schedule(string $selection, int $imageCount): void
    {
        $proposal = $this->dueProposal();
        $proposal->site->editorialSchedule()->delete();
        $proposal->content->source->update(['promotion_authorized_at' => null]);
        $proposal->update(['status' => 'draft', 'proposed_for' => now()->addMonth()]);
        $admin = User::query()->sole();
        XConnection::query()->sole()->update(['scopes' => ['tweet.write', 'media.write'], 'automatic_publishing_enabled' => false]);
        config()->set('services.x.test_fake_publish', true);
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACklEQVR42mNgAAAAAgABSK+kcQAAAABJRU5ErkJggg==');
        $pages = array_map(fn (int $id): array => [
            'id' => $id, 'chapter_id' => 42, 'hidden' => 0, 'mime' => 'image/png',
            'url' => "https://example.test/pages/{$id}.png", 'thumb_url' => "https://example.test/thumbs/{$id}.png",
        ], range(1, 5));

        if ($selection === 'saved') {
            $proposal->update(['image_attachments' => array_map(fn (array $page): array => [
                'id' => $page['id'], 'url' => $page['url'], 'thumbnail' => $page['thumb_url'], 'sha256' => hash('sha256', $bytes),
            ], array_slice($pages, 1, $imageCount))]);
        }

        $this->mock(PublicHostResolver::class)->shouldReceive('resolve')->with('example.test')->andReturn('93.184.216.34');
        Http::preventStrayRequests();
        $uploads = Http::sequence();
        $mediaIds = [];

        for ($index = 0; $index < $imageCount; $index++) {
            $mediaIds[] = $id = (string) (101 + $index);
            $uploads->push(['data' => ['id' => $id]]);
        }

        Http::fake([
            'https://example.test/api/reader/chapter/format/json?id=42' => $selection === 'text only' ? Http::response([], 503) : Http::response([
                'comic' => ['hidden' => 0, 'adult' => 0], 'chapter' => ['id' => 42, 'hidden' => 0], 'pages' => $pages,
            ]),
            'https://example.test/pages/*.png' => Http::response($bytes, 200, ['Content-Type' => 'image/png']),
            'https://api.x.com/2/media/upload' => $uploads,
            'https://api.x.com/2/tweets' => Http::response(['data' => ['id' => 'post-123']], 201),
        ]);

        $overview = route('sites.editorial-plan.show', $proposal->site);
        $endpoint = route('sites.editorial-plan.proposals.publish.confirm', [$proposal->site, $proposal]);
        $this->actingAs($admin)->get($overview)->assertOk()->assertSee('Pubblica ora')->assertSee($endpoint);

        if ($selection === 'text only') {
            $this->get($endpoint)->assertOk()->assertDontSee('Conferma e pubblica su X');
            $this->get(route('sites.editorial-plan.proposals.images.edit', [$proposal->site, $proposal]))
                ->assertOk()->assertSee('Usa solo testo');
            $this->patch(route('sites.editorial-plan.proposals.images.update', [$proposal->site, $proposal]))->assertSessionHas('status');
        }

        $preview = $this->get($endpoint)->assertOk()->assertSee($proposal->post_text)->assertSee('@publisher');
        $this->assertCount($imageCount, $preview->viewData('attachments'));
        $this->assertSame('draft', $proposal->fresh()->status);
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');

        if ($selection === 'suggested') {
            $thumbnails = $this->get(route('sites.editorial-plan.proposals.images.preview', [$proposal->site, $proposal]));
            foreach ($preview->viewData('attachments') as $attachment) {
                $thumbnails->assertSee($attachment['thumbnail']);
            }
        }

        $confirmation = ['confirmation' => $preview->viewData('confirmation')];
        if ($imageCount > 0) {
            $this->from($endpoint)->post($endpoint, $confirmation)->assertSessionHasErrors('images_confirmed');
            Http::assertNotSent(fn ($request) => $request->method() === 'POST');
            $confirmation['images_confirmed'] = '1';
        }

        $this->post($endpoint, $confirmation)->assertSessionHas('status', 'Post pubblicato su X.');
        $this->post($endpoint, $confirmation)->assertSessionHas('workflow_error');
        $proposal->refresh();
        $this->assertSame('published', $proposal->status);
        $this->assertSame($admin->id, $proposal->reviewed_by_user_id);
        $this->assertSame(array_column($preview->viewData('attachments'), 'id'), array_column($proposal->image_attachments, 'id'));
        $this->assertCount($imageCount, Http::recorded(fn ($request) => $request->url() === 'https://api.x.com/2/media/upload'));
        $posts = Http::recorded(fn ($request) => $request->url() === 'https://api.x.com/2/tweets');
        $this->assertCount(1, $posts);
        $sent = $posts->first()[0];
        $this->assertSame($proposal->post_text, $sent['text']);
        $this->assertSame($mediaIds, data_get($sent->data(), 'media.media_ids', []));
        $this->get($overview)->assertOk()->assertSee('Pubblicata su X')->assertDontSee($endpoint);
    }

    public static function manualImageSelections(): array
    {
        return [['suggested', 3], ['saved', 4], ['text only', 0]];
    }

    public function test_manual_confirmation_cannot_send_a_changed_proposal_or_bypass_the_gate(): void
    {
        $proposal = $this->dueProposal();
        $proposal->update(['status' => 'draft', 'image_attachments' => []]);
        $connection = XConnection::query()->sole();
        $connection->update(['manual_publishing_enabled' => false]);
        $endpoint = route('sites.editorial-plan.proposals.publish.confirm', [$proposal->site, $proposal]);
        $toggle = route('x.publishing.update');
        $manual = ['account' => $connection->x_user_id, 'mode' => 'manual'];
        Http::preventStrayRequests();
        $this->actingAs(User::query()->sole());

        config()->set('services.x.test_fake_publish', true);
        $this->get($endpoint)->assertOk()->assertSee('Abilita la pubblicazione manuale dell’account X')->assertDontSee('Conferma e pubblica su X');
        $this->patch($toggle, $manual + ['enabled' => '1'])->assertSessionHas('status');
        $preview = $this->get($endpoint)->assertOk();
        $confirmation = ['confirmation' => $preview->viewData('confirmation')];

        $this->patch($toggle, $manual + ['enabled' => '0'])->assertSessionHas('status');
        $this->post($endpoint, $confirmation)->assertSessionHas('workflow_error');
        $this->assertTrue($connection->fresh()->automatic_publishing_enabled);
        $this->patch($toggle, $manual + ['enabled' => '1'])->assertSessionHas('status');
        $proposal->update(['post_text' => 'Testo rivisto. '.$proposal->content->public_url]);
        $this->post($endpoint, $confirmation)
            ->assertSessionHas('workflow_error', 'La proposta è cambiata. Riapri l’anteprima prima di pubblicare.');
        $this->assertSame('draft', $proposal->fresh()->status);
        Http::assertNothingSent();
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
            'manual_publishing_enabled' => true, 'automatic_publishing_enabled' => true,
        ]);

        return $site->editorialProposals()->create([
            'editorial_content_id' => $content->id, 'platform' => 'x', 'status' => 'approved',
            'post_text' => 'Nuovo capitolo. https://example.test/chapter/42',
            'rationale' => 'Uscita recente.', 'proposed_for' => now()->subMinute(),
        ]);
    }
}
