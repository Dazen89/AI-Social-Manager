<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_site_management(): void
    {
        $this->get('/sites')->assertRedirect('/login');
    }

    public function test_non_administrators_cannot_access_site_management(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get('/sites')
            ->assertForbidden();
    }

    public function test_administrator_can_view_the_site_list_and_creation_form(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->get('/sites')
            ->assertOk()
            ->assertSee('Aggiungi sito');

        $this->actingAs($admin)
            ->get('/sites/create')
            ->assertOk()
            ->assertSee('Prima di iniziare')
            ->assertSee('Salva e continua')
            ->assertSee('In questa fase non servono account esterni')
            ->assertSee('inputmode="url"', false)
            ->assertDontSee('id="base_url" name="base_url" type="url"', false)
            ->assertDontSee('Google Analytics 4')
            ->assertDontSee('analytics_property_id');
    }

    public function test_administrator_creates_a_site_before_choosing_services(): void
    {
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)->post('/sites', [
            'name' => 'Example Website',
            'base_url' => 'EXAMPLE.test',
            'is_active' => '1',
        ]);

        $this->assertDatabaseHas('sites', [
            'name' => 'Example Website',
            'base_url' => 'https://example.test',
            'host' => 'example.test',
            'is_active' => true,
        ]);

        $site = Site::query()->sole();

        $response->assertRedirect(route('sites.edit', $site));
        $this->assertDatabaseCount('google_analytics_properties', 0);

        $this->actingAs($admin)
            ->get(route('sites.edit', $site))
            ->assertOk()
            ->assertSee('Servizi e integrazioni')
            ->assertSee('Google Analytics 4')
            ->assertSee('Non collegato')
            ->assertSee('Configura Google Analytics')
            ->assertSee('Apri la procedura guidata')
            ->assertDontSee('GOOGLE_ANALYTICS_CLIENT_ID');
    }

    public function test_provider_metadata_is_ignored_during_site_registration(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->post('/sites', [
            'name' => 'Pending Website',
            'base_url' => 'https://pending.example.test',
            'is_active' => '1',
            'analytics_account_id' => '123456789',
            'analytics_property_id' => '987654321',
            'analytics_data_stream_id' => '4567890123',
            'analytics_measurement_id' => 'G-ABC1234567',
        ]);

        $this->assertDatabaseCount('sites', 1);
        $this->assertDatabaseCount('google_analytics_properties', 0);
    }

    public function test_duplicate_hosts_are_rejected(): void
    {
        $admin = User::factory()->administrator()->create();
        Site::factory()->create(['host' => 'example.test', 'base_url' => 'https://example.test']);

        $this->actingAs($admin)->post('/sites', [
            'name' => 'Duplicate Website',
            'base_url' => 'http://EXAMPLE.test',
            'is_active' => '1',
        ])->assertSessionHasErrors([
            'host' => 'Questo dominio è già presente.',
        ]);

        $this->assertDatabaseCount('sites', 1);
    }

    public function test_site_urls_must_represent_an_origin(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->post('/sites', [
            'name' => 'Page URL',
            'base_url' => 'https://example.test/some-page?preview=1',
            'is_active' => '1',
        ])->assertSessionHasErrors('base_url');

        $this->assertDatabaseCount('sites', 0);
    }

    public function test_invalid_site_urls_receive_actionable_feedback(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->post('/sites', [
            'name' => 'Invalid Website',
            'base_url' => 'not a valid host',
            'is_active' => '1',
        ])->assertSessionHasErrors([
            'base_url' => 'Inserisci un indirizzo web valido, ad esempio https://www.example.com.',
        ]);

        $this->assertDatabaseCount('sites', 0);
    }

    public function test_updating_site_details_preserves_existing_provider_metadata(): void
    {
        $admin = User::factory()->administrator()->create();
        $site = Site::factory()->create();
        $site->googleAnalyticsProperty()->create([
            'account_id' => '123456789',
            'property_id' => '987654321',
            'data_stream_id' => '4567890123',
            'measurement_id' => 'G-ABC1234567',
        ]);

        $response = $this->actingAs($admin)->put(route('sites.update', $site), [
            'name' => 'Updated Website',
            'base_url' => 'https://updated.example.test',
            'is_active' => '0',
        ]);

        $response->assertRedirect(route('sites.edit', $site));
        $this->assertDatabaseHas('sites', [
            'id' => $site->id,
            'name' => 'Updated Website',
            'host' => 'updated.example.test',
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('google_analytics_properties', [
            'site_id' => $site->id,
            'property_id' => '987654321',
            'measurement_id' => 'G-ABC1234567',
        ]);

        $this->actingAs($admin)
            ->get(route('sites.edit', $site))
            ->assertOk()
            ->assertSee('Configurazione precedente')
            ->assertSee('non rappresentano ancora una connessione verificata');
    }
}
