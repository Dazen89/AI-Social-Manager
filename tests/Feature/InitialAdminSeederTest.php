<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\InitialAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InitialAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_initial_administrator_from_configuration(): void
    {
        config()->set('initial-admin', [
            'name' => 'Initial Admin',
            'email' => 'admin@example.test',
            'password' => 'a-secure-password',
        ]);

        $this->seed(InitialAdminSeeder::class);

        $user = User::query()->sole();

        $this->assertSame('Initial Admin', $user->name);
        $this->assertSame('admin@example.test', $user->email);
        $this->assertTrue($user->is_admin);
        $this->assertTrue(Hash::check('a-secure-password', $user->password));
    }

    public function test_rerunning_the_seeder_does_not_change_an_existing_password(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.test',
            'password' => 'existing-password',
            'is_admin' => false,
        ]);

        config()->set('initial-admin', [
            'name' => 'Initial Admin',
            'email' => $user->email,
            'password' => 'a-different-password',
        ]);

        $this->seed(InitialAdminSeeder::class);

        $user->refresh();

        $this->assertTrue($user->is_admin);
        $this->assertTrue(Hash::check('existing-password', $user->password));
        $this->assertFalse(Hash::check('a-different-password', $user->password));
    }
}
