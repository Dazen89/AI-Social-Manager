<?php

namespace Tests\Feature;

use App\Models\InstallationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_from_the_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_login_screen_is_available_to_guests(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Accedi al servizio');
    }

    public function test_user_can_log_in_with_valid_credentials(): void
    {
        $user = User::factory()->administrator()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_authenticated_administrator_can_view_the_dashboard(): void
    {
        $user = User::factory()->administrator()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Area di monitoraggio')
            ->assertSee('Nessun sito registrato')
            ->assertSee(route('sites.create'), false)
            ->assertSee('href="'.route('settings.index').'" class="ui-nav-link"', false);
    }

    public function test_general_settings_show_installation_details_to_administrators(): void
    {
        $this->get(route('settings.index'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('settings.index'))
            ->assertForbidden();

        config()->set('app.name', 'Installazione di esempio');
        config()->set('app.url', 'https://monitor.example.test');

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Informazioni sull’installazione')
            ->assertSee('Installazione di esempio')
            ->assertSee('https://monitor.example.test')
            ->assertSee('Fuso orario dell’applicazione')
            ->assertDontSee('Google Analytics')
            ->assertDontSee('Google Search Console');
    }

    public function test_administrator_can_change_the_application_name_for_the_workspace_and_login(): void
    {
        $endpoint = route('settings.update');
        $this->patch($endpoint, ['app_name' => 'Monitor interno'])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->patch($endpoint, ['app_name' => 'Monitor interno'])->assertForbidden();

        $this->actingAs(User::factory()->administrator()->create());
        $this->from(route('settings.index'))->patch($endpoint, ['app_name' => '   '])->assertSessionHasErrors('app_name');
        $this->patch($endpoint, ['app_name' => str_repeat('a', 81)])->assertSessionHasErrors('app_name');
        $this->assertDatabaseCount('installation_settings', 0);

        $this->patch($endpoint, ['app_name' => 'Monitor interno'])->assertRedirect(route('settings.index'));
        $this->patch($endpoint, ['app_name' => '  Officina & Monitor  '])->assertRedirect(route('settings.index'));
        $this->assertDatabaseCount('installation_settings', 1);
        $this->assertDatabaseHas('installation_settings', ['key' => InstallationSetting::APP_NAME, 'value' => 'Officina & Monitor']);

        config()->set('app.name', 'Nome del server');
        $this->get(route('settings.index'))->assertOk()->assertSee('value="Officina &amp; Monitor"', false);
        $this->get(route('dashboard'))->assertOk()
            ->assertSee('<title>Dashboard — Officina &amp; Monitor</title>', false)
            ->assertSee('title="Officina &amp; Monitor">Officina &amp; Monitor</span>', false);

        $this->post(route('logout'))->assertRedirect(route('login'));
        config()->set('app.name', 'Nome del server');
        $this->get(route('login'))->assertOk()
            ->assertSee('<title>Accedi — Officina &amp; Monitor</title>', false)
            ->assertSee('title="Officina &amp; Monitor">Officina &amp; Monitor</span>', false);
    }

    public function test_user_cannot_log_in_with_invalid_credentials(): void
    {
        $user = User::factory()->administrator()->create();

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/login')->assertSessionHasErrors('email');
    }

    public function test_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->administrator()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }

    public function test_public_registration_is_not_available(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_non_administrator_cannot_log_in(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_authenticated_non_administrator_cannot_access_the_dashboard(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertForbidden();
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        $user = User::factory()->administrator()->create();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringStartsWith('Troppi tentativi.', session('errors')->first('email'));
        $this->assertGuest();
    }
}
