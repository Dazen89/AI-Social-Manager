<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_can_manage_users_and_see_the_sidebar_link(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->get(route('users.index'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create());
        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('users.edit', $admin))->assertForbidden();
        $this->post(route('users.store'))->assertForbidden();
        $this->patch(route('users.update', $admin))->assertForbidden();
        $this->patch(route('users.access.update', $admin), ['enabled' => false])->assertForbidden();

        $this->actingAs($admin)->get(route('users.index'))->assertOk()
            ->assertSee($admin->email)
            ->assertSeeInOrder(['href="'.route('users.index').'"', 'href="'.route('settings.index').'"'], false)
            ->assertDontSee(route('users.access.update', $admin));
        $this->get(route('users.create'))->assertOk()->assertSee('Crea amministratore');
        $this->get(route('users.edit', $admin))->assertOk()->assertSee('Nuova password')->assertDontSee($admin->password);
    }

    public function test_an_administrator_can_create_a_user_and_update_their_details(): void
    {
        $admin = User::factory()->administrator()->create();
        $password = 'test-password-147';
        $details = ['name' => 'Secondo amministratore', 'email' => ' SECOND@example.test '];
        $this->actingAs($admin)->post(route('users.store'), $details + ['password' => $password])
            ->assertSessionHasErrors('password');
        $this->post(route('users.store'), $details + ['password' => $password, 'password_confirmation' => $password])
            ->assertRedirect(route('users.index'));

        $account = User::query()->where('email', 'second@example.test')->sole();
        $this->assertTrue($account->is_admin);
        $this->assertTrue(Hash::check($password, $account->password));

        $update = ['name' => 'Account aggiornato', 'email' => 'updated@example.test', 'password' => ''];
        $this->patch(route('users.update', $account), [...$update, 'email' => $admin->email])->assertSessionHasErrors('email');
        $this->patch(route('users.update', $account), $update)->assertRedirect(route('users.index'));
        $this->assertSame($account->password, $account->fresh()->password);

        $this->post(route('logout'));
        $this->post(route('login.store'), ['email' => $update['email'], 'password' => $password])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($account);
    }

    public function test_suspending_and_reactivating_another_admin_does_not_restore_old_sessions(): void
    {
        $admin = User::factory()->administrator()->create();
        $account = User::factory()->administrator()->create(['remember_token' => 'synthetic-remember-token']);
        $this->actingAs($admin)->patch(route('users.access.update', $admin), ['enabled' => false])->assertForbidden();
        $this->patch(route('users.access.update', $account), ['enabled' => false])->assertRedirect(route('users.index'));
        $this->assertFalse($account->fresh()->is_admin);
        $this->assertNull($account->fresh()->remember_token);

        $this->patch(route('users.access.update', $account), ['enabled' => true])->assertRedirect(route('users.index'));
        $this->assertTrue($account->fresh()->is_admin);
        $this->actingAs($account->fresh())->withSession([User::SESSION_VERSION_KEY => 0]);
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_changing_your_password_keeps_the_current_session_and_invalidates_older_ones(): void
    {
        $admin = User::factory()->administrator()->create(['remember_token' => 'synthetic-remember-token']);
        $password = 'test-password-258';
        $this->actingAs($admin)->patch(route('users.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'password' => $password, 'password_confirmation' => $password,
        ])->assertRedirect(route('users.index'))->assertSessionHas(User::SESSION_VERSION_KEY, 1);

        $admin->refresh();
        $this->assertTrue(Hash::check($password, $admin->password));
        $this->assertNull($admin->remember_token);
        $this->get(route('users.index'))->assertOk();
        $this->withSession([User::SESSION_VERSION_KEY => 0])->get(route('users.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
