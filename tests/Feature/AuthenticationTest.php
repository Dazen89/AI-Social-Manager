<?php

namespace Tests\Feature;

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
            ->assertSee('Area di monitoraggio');
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
