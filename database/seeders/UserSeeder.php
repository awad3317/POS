<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds safely without any hardcoded credentials.
     */
    public function run(): void
    {
        $name = (string) env('INITIAL_ADMIN_NAME', 'Store Owner');
        $email = (string) env('INITIAL_ADMIN_EMAIL', 'admin@yemenpos.local');
        $password = (string) env('INITIAL_ADMIN_PASSWORD', '');

        if ($password === '') {
            if (app()->isProduction()) {
                throw new RuntimeException(
                    'INITIAL_ADMIN_PASSWORD must be set in .env before seeding in production, or run php artisan yemen-pos:create-admin.'
                );
            }

            $this->command?->warn('Skipping UserSeeder: INITIAL_ADMIN_PASSWORD is empty. Use `php artisan yemen-pos:create-admin` to create an admin account.');

            return;
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
            ]
        );
    }
}
