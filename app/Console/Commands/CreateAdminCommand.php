<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminCommand extends Command
{
    protected $signature = 'yemen-pos:create-admin
                            {--name= : Admin full name}
                            {--email= : Admin email address}
                            {--password= : Admin password (min 8 chars)}';

    protected $description = 'Create or update an initial Yemen POS administrator securely';

    public function handle(): int
    {
        $name = (string) ($this->option('name') ?: $this->ask('Admin Name', (string) env('INITIAL_ADMIN_NAME', 'Store Owner')));
        $email = (string) ($this->option('email') ?: $this->ask('Admin Email', (string) env('INITIAL_ADMIN_EMAIL', 'admin@yemenpos.local')));
        $password = (string) ($this->option('password') ?: $this->secret('Admin Password (minimum 8 characters)'));

        $validator = Validator::make(
            compact('name', 'email', 'password'),
            [
                'name' => ['required', 'string', 'max:191'],
                'email' => ['required', 'email', 'max:191'],
                'password' => ['required', 'string', 'min:8'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
            ]
        );

        $this->info("Admin user [{$user->email}] saved successfully.");

        return self::SUCCESS;
    }
}
