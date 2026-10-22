<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteEditorialPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_can_manage_editorial_sources(): void
    {
        $site = Site::factory()->create();
        $sourceUrl = route('sites.editorial-plan.show', $site);

        $this->get($sourceUrl)->assertRedirect('/login');

        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user)->get($sourceUrl)->assertForbidden();
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
            ->assertSee('FoOlSlide-Improved')
            ->assertSee('Aggiungerla non legge ancora contenuti');

        $this->actingAs($admin)->post(route('sites.editorial-plan.sources.store', $site), [
            'provider' => 'foolslide',
            'name' => 'Archivio traduzioni',
            'base_url' => 'https://EXAMPLE.test/slide/',
        ])->assertRedirect(route('sites.editorial-plan.show', $site));

        $this->assertDatabaseHas('editorial_sources', [
            'site_id' => $site->id,
            'provider' => 'foolslide',
            'name' => 'Archivio traduzioni',
            'base_url' => 'https://example.test/slide',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('sites.editorial-plan.show', $site))
            ->assertSee('https://example.test/slide/api/reader/chapters/format/json');
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
            ->assertRedirect(route('sites.editorial-plan.show', $site));
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
            ->assertRedirect(route('sites.editorial-plan.show', $site));

        $this->assertDatabaseHas('editorial_sources', [
            'id' => $source->id,
            'name' => 'New name',
            'base_url' => 'https://example.test/new',
        ]);
    }
}
