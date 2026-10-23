<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\User;
use App\Services\PublicHostResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class SiteEditorialPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_promotion_requires_explicit_admin_authorization_and_can_be_revoked(): void
    {
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create(['host' => 'example.test', 'base_url' => 'https://example.test']);
        $source = $site->editorialSources()->create([
            'provider' => 'foolslide', 'name' => 'Archivio', 'base_url' => 'https://example.test',
            'is_active' => true, 'verification_status' => 'verified',
        ]);
        $content = $source->contents()->create([
            'site_id' => $site->id, 'external_id' => '1', 'series_title' => 'Serie',
            'title' => 'Capitolo 1', 'public_url' => 'https://example.test/chapter/1',
            'last_seen_at' => now(),
        ]);

        $this->actingAs($admin)->patch(route('sites.editorial-plan.sources.promotion-authorization', [$site, $source]), ['authorized' => '1'])
            ->assertSessionHas('status');
        $this->assertNotNull($source->fresh()->promotion_authorized_at);
        $this->assertSame('eligible', $content->fresh()->eligibility_status);

        $this->actingAs($admin)->patch(route('sites.editorial-plan.sources.promotion-authorization', [$site, $source]), ['authorized' => '0'])
            ->assertSessionHas('status');
        $this->assertNull($source->fresh()->promotion_authorized_at);
    }

    public function test_only_administrators_can_manage_editorial_sources(): void
    {
        $site = Site::factory()->create();
        $sourceUrl = route('sites.editorial-plan.show', $site);

        $this->get($sourceUrl)->assertRedirect('/login');
        $this->get(route('sites.editorial-plan.sources.index', $site))->assertRedirect('/login');

        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user)->get($sourceUrl)->assertForbidden();
        $this->actingAs($user)->get(route('sites.editorial-plan.sources.index', $site))->assertForbidden();
        $this->actingAs($user)->post(route('sites.editorial-plan.sources.store', $site), [
            'provider' => 'foolslide',
            'name' => 'FoOlSlide',
            'base_url' => $site->base_url,
        ])->assertForbidden();

        $this->assertDatabaseCount('editorial_sources', 0);
    }

    public function test_administrator_can_add_a_foolslide_source_without_importing_or_publishing(): void
    {
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create([
            'base_url' => 'https://example.test',
            'host' => 'example.test',
        ]);

        $this->actingAs($admin)
            ->get(route('sites.editorial-plan.show', $site))
            ->assertOk()
            ->assertSee('Piano editoriale')
            ->assertSee('Calendario')
            ->assertSee('Proposte editoriali')
            ->assertSee('Gestisci fonti')
            ->assertDontSee('Aggiungi una fonte');

        $this->actingAs($admin)
            ->get(route('sites.editorial-plan.sources.index', $site))
            ->assertOk()
            ->assertSee('Fonti editoriali')
            ->assertSee('FoOlSlide-Improved')
            ->assertSee('L’autorizzazione della fonte attesta');

        $this->actingAs($admin)->post(route('sites.editorial-plan.sources.store', $site), [
            'provider' => 'foolslide',
            'name' => 'Archivio traduzioni',
            'base_url' => 'https://EXAMPLE.test/slide/',
        ])->assertRedirect(route('sites.editorial-plan.sources.index', $site));

        $this->assertDatabaseHas('editorial_sources', [
            'site_id' => $site->id,
            'provider' => 'foolslide',
            'name' => 'Archivio traduzioni',
            'base_url' => 'https://example.test/slide',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('sites.editorial-plan.sources.index', $site))
            ->assertSee('https://example.test/slide/api/reader/chapters/format/json');

        $this->actingAs($admin)
            ->get(route('sites.editorial-plan.show', $site))
            ->assertSee('Fonti configurate: 1')
            ->assertDontSee('Archivio traduzioni');
    }

    public function test_calendar_navigates_months_without_showing_unavailable_posts(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-22 12:00:00'));

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();

        $this->actingAs($admin)
            ->get(route('sites.editorial-plan.show', $site))
            ->assertOk()
            ->assertSee('ottobre 2026')
            ->assertSee('datetime="2026-10-22"', false)
            ->assertSee('Nessun post proposto in questo mese')
            ->assertSee('Nessuna proposta disponibile');

        $this->actingAs($admin)
            ->get(route('sites.editorial-plan.show', ['site' => $site, 'month' => '2026-11']))
            ->assertOk()
            ->assertSee('novembre 2026')
            ->assertSee('datetime="2026-11-01"', false)
            ->assertDontSee('datetime="2026-10-22"', false);

        $this->actingAs($admin)
            ->get(route('sites.editorial-plan.show', ['site' => $site, 'month' => 'invalid']))
            ->assertOk()
            ->assertSee('ottobre 2026');
    }

    public function test_source_must_belong_to_the_site_and_not_be_duplicated(): void
    {
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create(['base_url' => 'https://example.test', 'host' => 'example.test']);
        $endpoint = route('sites.editorial-plan.sources.store', $site);

        $this->actingAs($admin)->post($endpoint, [
            'provider' => 'foolslide',
            'name' => 'Other site',
            'base_url' => 'https://other.example.test/slide',
        ])->assertSessionHasErrors('base_url');

        $this->actingAs($admin)->post($endpoint, [
            'provider' => 'foolslide',
            'name' => 'Invalid endpoint',
            'base_url' => 'https://example.test/slide?token=private',
        ])->assertSessionHasErrors('base_url');

        $this->actingAs($admin)->post($endpoint, [
            'provider' => 'unknown',
            'name' => 'Unknown',
            'base_url' => 'https://example.test/slide',
        ])->assertSessionHasErrors('provider');

        $this->actingAs($admin)->post($endpoint, [
            'provider' => 'foolslide',
            'name' => 'Insecure',
            'base_url' => 'http://example.test/slide',
        ])->assertSessionHasErrors('base_url');

        $this->actingAs($admin)->post($endpoint, [
            'provider' => 'foolslide',
            'name' => 'First',
            'base_url' => 'https://example.test/slide',
        ])->assertRedirect();

        $this->actingAs($admin)->post($endpoint, [
            'provider' => 'foolslide',
            'name' => 'Duplicate',
            'base_url' => 'https://EXAMPLE.test/slide/',
        ])->assertSessionHasErrors('base_url');

        $this->assertDatabaseCount('editorial_sources', 1);
    }

    public function test_local_docker_source_can_be_added_and_verified_for_a_public_site(): void
    {
        config()->set('app.env', 'local');

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create([
            'base_url' => 'https://example.test',
            'host' => 'example.test',
        ]);

        $this->actingAs($admin)->post(route('sites.editorial-plan.sources.store', $site), [
            'provider' => 'foolslide',
            'name' => 'FoOlSlide locale',
            'base_url' => 'http://LOCALHOST:8080/',
        ])->assertRedirect(route('sites.editorial-plan.sources.index', $site));

        $source = $site->editorialSources()->firstOrFail();
        $this->assertSame('http://localhost:8080', $source->base_url);

        Http::preventStrayRequests();
        Http::fake([
            'http://localhost:8080/api/reader/chapters/format/json*' => Http::response([
                'chapters' => [[
                    'comic' => ['id' => 10, 'name' => 'Example'],
                    'chapter' => ['id' => 20, 'name' => 'Chapter'],
                ]],
            ]),
        ]);

        $this->actingAs($admin)
            ->post(route('sites.editorial-plan.sources.verify', [$site, $source]))
            ->assertSessionHas('status');

        $this->assertSame('verified', $source->fresh()->verification_status);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request->url() === 'http://localhost:8080/api/reader/chapters/format/json?page=1&per_page=1');
    }

    public function test_local_http_exception_does_not_allow_other_loopback_urls_or_production(): void
    {
        config()->set('app.env', 'local');

        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create([
            'base_url' => 'http://localhost:3000',
            'host' => 'localhost',
        ]);
        $endpoint = route('sites.editorial-plan.sources.store', $site);

        foreach (['http://localhost:80', 'http://127.0.0.1:8080', 'http://localhost:8080?token=private', 'http://example.test:8080'] as $url) {
            $this->actingAs($admin)->post($endpoint, [
                'provider' => 'foolslide',
                'name' => 'Non ammessa',
                'base_url' => $url,
            ])->assertSessionHasErrors('base_url');
        }

        $this->actingAs($admin)->post($endpoint, [
            'provider' => 'foolslide',
            'name' => 'Porta Docker diversa',
            'base_url' => 'http://localhost:9000',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('editorial_sources', [
            'site_id' => $site->id,
            'base_url' => 'http://localhost:9000',
        ]);

        config()->set('app.env', 'production');

        $this->actingAs($admin)->post($endpoint, [
            'provider' => 'foolslide',
            'name' => 'Non ammessa',
            'base_url' => 'http://localhost:8080',
        ])->assertSessionHasErrors('base_url');

        $source = $site->editorialSources()->create([
            'provider' => 'foolslide',
            'name' => 'Fonte locale esistente',
            'base_url' => 'http://localhost:8080',
            'is_active' => true,
        ]);
        Http::preventStrayRequests();

        $this->actingAs($admin)
            ->post(route('sites.editorial-plan.sources.verify', [$site, $source]))
            ->assertSessionHas('verification_error');

        Http::assertNothingSent();
        $this->assertSame('failed', $source->fresh()->verification_status);
    }

    public function test_source_activation_is_scoped_to_its_site(): void
    {
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $otherSite = Site::factory()->create();
        $source = $site->editorialSources()->create([
            'provider' => 'foolslide',
            'name' => 'FoOlSlide',
            'base_url' => $site->base_url,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->patch(route('sites.editorial-plan.sources.toggle', [$otherSite, $source]))
            ->assertNotFound();
        $this->assertTrue($source->fresh()->is_active);

        $this->actingAs($admin)
            ->patch(route('sites.editorial-plan.sources.toggle', [$site, $source]))
            ->assertRedirect(route('sites.editorial-plan.sources.index', $site));
        $this->assertFalse($source->fresh()->is_active);
    }

    public function test_administrator_can_correct_a_source_but_not_edit_another_sites_source(): void
    {
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create(['base_url' => 'https://example.test', 'host' => 'example.test']);
        $otherSite = Site::factory()->create();
        $source = $site->editorialSources()->create([
            'provider' => 'foolslide',
            'name' => 'Old name',
            'base_url' => 'https://example.test/old',
            'is_active' => true,
        ]);
        $payload = [
            'provider' => 'foolslide',
            'name' => 'New name',
            'base_url' => 'https://example.test/new',
            'source_id' => $source->id,
        ];

        $this->actingAs($admin)
            ->put(route('sites.editorial-plan.sources.update', [$otherSite, $source]), $payload)
            ->assertForbidden();

        $this->actingAs($admin)
            ->put(route('sites.editorial-plan.sources.update', [$site, $source]), $payload)
            ->assertRedirect(route('sites.editorial-plan.sources.index', $site));

        $this->assertDatabaseHas('editorial_sources', [
            'id' => $source->id,
            'name' => 'New name',
            'base_url' => 'https://example.test/new',
        ]);
    }

    public function test_verification_reads_a_small_chapter_page_and_records_success(): void
    {
        [$admin, $site, $source] = $this->configuredSource();
        $this->mockPublicAddress();
        Http::preventStrayRequests();
        Http::fake([
            'https://example.test/api/reader/chapters/format/json*' => Http::response([
                'chapters' => [[
                    'comic' => ['id' => 10, 'name' => 'Example'],
                    'chapter' => ['id' => 20, 'name' => 'Chapter'],
                ]],
            ]),
        ]);

        $this->actingAs($admin)
            ->post(route('sites.editorial-plan.sources.verify', [$site, $source]))
            ->assertRedirect(route('sites.editorial-plan.sources.index', $site))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('editorial_sources', [
            'id' => $source->id,
            'verification_status' => 'verified',
        ]);
        $this->assertNotNull($source->fresh()->last_checked_at);
        $this->assertNotNull($source->fresh()->last_verified_at);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request->method() === 'GET'
            && $request->url() === 'https://example.test/api/reader/chapters/format/json?page=1&per_page=1');

        $this->actingAs($admin)
            ->get(route('sites.editorial-plan.sources.index', $site))
            ->assertSee('API verificata')
            ->assertSee('Ultimo controllo');
    }

    public function test_empty_chapter_list_is_checked_against_the_count_endpoint(): void
    {
        [$admin, $site, $source] = $this->configuredSource();
        $this->mockPublicAddress();
        Http::preventStrayRequests();
        Http::fake([
            'https://example.test/api/reader/chapters/format/json*' => Http::response(['error' => 'No comics'], 404),
            'https://example.test/api/reader/comics/count/comics/format/json' => Http::response(['count' => 0]),
        ]);

        $this->actingAs($admin)
            ->post(route('sites.editorial-plan.sources.verify', [$site, $source]))
            ->assertSessionHas('status');

        $this->assertSame('verified', $source->fresh()->verification_status);
        Http::assertSentCount(2);
    }

    public function test_unexpected_response_is_a_failure_and_does_not_store_external_content(): void
    {
        [$admin, $site, $source] = $this->configuredSource();
        $this->mockPublicAddress();
        Http::preventStrayRequests();
        Http::fake([
            '*' => Http::response('<html>private external content</html>', 200),
        ]);

        $this->actingAs($admin)
            ->post(route('sites.editorial-plan.sources.verify', [$site, $source]))
            ->assertSessionHas('verification_error');

        $source->refresh();
        $this->assertSame('failed', $source->verification_status);
        $this->assertStringNotContainsString('private external content', $source->verification_message);
        $this->assertNull($source->last_verified_at);
        Http::assertSentCount(1);
    }

    public function test_verification_never_follows_redirects(): void
    {
        [$admin, $site, $source] = $this->configuredSource();
        $this->mockPublicAddress();
        Http::preventStrayRequests();
        Http::fake([
            '*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/private']),
        ]);

        $this->actingAs($admin)
            ->post(route('sites.editorial-plan.sources.verify', [$site, $source]))
            ->assertSessionHas('verification_error');

        $this->assertSame('failed', $source->fresh()->verification_status);
        Http::assertSentCount(1);
    }

    public function test_large_response_is_rejected_without_storing_it(): void
    {
        [$admin, $site, $source] = $this->configuredSource();
        $this->mockPublicAddress();
        Http::preventStrayRequests();
        Http::fake([
            '*' => Http::response(str_repeat('x', 300000), 200, ['Content-Length' => '300000']),
        ]);

        $this->actingAs($admin)
            ->post(route('sites.editorial-plan.sources.verify', [$site, $source]))
            ->assertSessionHas('verification_error');

        $this->assertSame('failed', $source->fresh()->verification_status);
        $this->assertStringNotContainsString('xxx', $source->fresh()->verification_message);
        Http::assertSentCount(1);
    }

    public function test_inactive_or_other_sites_source_cannot_be_verified(): void
    {
        [$admin, $site, $source] = $this->configuredSource();
        $otherSite = Site::factory()->create();
        Http::preventStrayRequests();

        $this->actingAs($admin)
            ->post(route('sites.editorial-plan.sources.verify', [$otherSite, $source]))
            ->assertNotFound();

        $source->update(['is_active' => false]);
        $this->actingAs($admin)
            ->post(route('sites.editorial-plan.sources.verify', [$site, $source]))
            ->assertSessionHas('verification_error');

        Http::assertNothingSent();
        $this->assertSame('not_checked', $source->fresh()->verification_status);
    }

    public function test_private_address_and_plain_http_are_rejected_before_any_request(): void
    {
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create(['base_url' => 'https://127.0.0.1', 'host' => '127.0.0.1']);
        $source = $site->editorialSources()->create([
            'provider' => 'foolslide',
            'name' => 'Local source',
            'base_url' => 'https://127.0.0.1',
            'is_active' => true,
        ]);
        Http::preventStrayRequests();

        $this->actingAs($admin)
            ->post(route('sites.editorial-plan.sources.verify', [$site, $source]))
            ->assertSessionHas('verification_error');
        $this->assertSame('failed', $source->fresh()->verification_status);

        $source->update(['base_url' => 'http://127.0.0.1']);
        $this->actingAs($admin)
            ->post(route('sites.editorial-plan.sources.verify', [$site, $source]))
            ->assertSessionHas('verification_error');

        Http::assertNothingSent();
    }

    public function test_changing_source_url_clears_prior_verification(): void
    {
        [$admin, $site, $source] = $this->configuredSource();
        $source->update([
            'verification_status' => 'verified',
            'verification_message' => 'API verified',
            'last_checked_at' => now(),
            'last_verified_at' => now(),
        ]);

        $this->actingAs($admin)->put(route('sites.editorial-plan.sources.update', [$site, $source]), [
            'provider' => 'foolslide',
            'name' => 'New name',
            'base_url' => 'https://example.test/new-path',
        ])->assertRedirect();

        $source->refresh();
        $this->assertSame('not_checked', $source->verification_status);
        $this->assertNull($source->last_checked_at);
        $this->assertNull($source->last_verified_at);
    }

    public function test_resolver_rejects_private_and_reserved_addresses(): void
    {
        $resolver = new PublicHostResolver;

        foreach (['127.0.0.1', '10.0.0.1', '100.64.0.1', '169.254.1.1', '192.168.1.1', '198.18.0.1', '203.0.113.1', '::1', 'fc00::1', '2001:db8::1', '::ffff:127.0.0.1'] as $address) {
            $this->assertFalse($resolver->isPublicAddress($address), $address);
        }

        $this->assertTrue($resolver->isPublicAddress('8.8.8.8'));
        $this->assertTrue($resolver->isPublicAddress('2606:4700:4700::1111'));
    }

    private function configuredSource(): array
    {
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create(['base_url' => 'https://example.test', 'host' => 'example.test']);
        $source = $site->editorialSources()->create([
            'provider' => 'foolslide',
            'name' => 'FoOlSlide',
            'base_url' => 'https://example.test',
            'is_active' => true,
        ]);

        return [$admin, $site, $source];
    }

    private function mockPublicAddress(): void
    {
        $resolver = Mockery::mock(PublicHostResolver::class);
        $resolver->shouldReceive('resolve')->once()->with('example.test')->andReturn('8.8.8.8');
        $this->app->instance(PublicHostResolver::class, $resolver);
    }
}
