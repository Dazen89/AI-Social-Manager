<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

class InitialAdminSeeder extends Seeder
{
    public function run(): void
    {
        $name = trim((string) config('initial-admin.name'));
        $email = Str::lower(trim((string) config('initial-admin.email')));
        $password = (string) config('initial-admin.password');

        if ($name === '' && $email === '' && $password === '') {
            $this->command?->warn('Initial admin not created: configure INITIAL_ADMIN_NAME, INITIAL_ADMIN_EMAIL and INITIAL_ADMIN_PASSWORD.');

            return;
        }

        if ($name === '' || $email === '' || $password === '') {
            throw new InvalidArgumentException('All initial admin environment values must be configured.');
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('INITIAL_ADMIN_EMAIL must be a valid email address.');
        }

        if (mb_strlen($password) < 12) {
            throw new InvalidArgumentException('INITIAL_ADMIN_PASSWORD must contain at least 12 characters.');
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $wasRecentlyCreated = ! $user->exists;

        $user->name = $name;
        $user->is_admin = true;

        if ($wasRecentlyCreated) {
            $user->password = Hash::make($password);
        }

        $user->save();

        $action = $wasRecentlyCreated ? 'created' : 'confirmed';
        $this->command?->info("Initial administrator {$action}.");
    }
}
