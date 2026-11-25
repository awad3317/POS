<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\StoreContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CreateSuperAdminCommand extends Command
{
    protected $signature = 'yemen-pos:create-super-admin
                            {--name= : Super Admin full name}
                            {--email= : Email address (creates new or promotes existing user)}
                            {--password= : Password (min 8 chars; optional when promoting existing user)}';

    protected $description = 'Create a new platform Super Admin (store_id = null) or promote an existing user to super_admin';

    public function handle(): int
    {
        return StoreContext::withoutScope(function (): int {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

            $email = trim((string) ($this->option('email') ?: $this->ask('Super Admin Email', 'superadmin@yemenpos.ye')));
            $existingUser = User::withoutGlobalScopes()->where('email', $email)->first();

            if ($existingUser) {
                $passwordOption = (string) ($this->option('password') ?? '');
                $nameOption = (string) ($this->option('name') ?? '');

                $updates = ['is_active' => true];
                if ($nameOption !== '') {
                    $updates['name'] = $nameOption;
                }
                if ($passwordOption !== '') {
                    if (mb_strlen($passwordOption) < 8) {
                        $this->error('Password must be at least 8 characters.');

                        return self::FAILURE;
                    }
                    $updates['password'] = Hash::make($passwordOption);
                }

                $existingUser->update($updates);
                $existingUser->assignRole('super_admin');

                $this->info("Existing user [{$existingUser->email}] promoted to [super_admin] successfully.");

                return self::SUCCESS;
            }

            $name = trim((string) ($this->option('name') ?: $this->ask('Super Admin Name', 'مدير المنصة العام')));
            $password = (string) ($this->option('password') ?: $this->secret('Super Admin Password (minimum 8 characters)'));

            $validator = Validator::make(
                compact('name', 'email', 'password'),
                [
                    'name' => ['required', 'string', 'max:191'],
                    'email' => ['required', 'email', 'max:191', 'unique:users,email'],
                    'password' => ['required', 'string', 'min:8'],
                ]
            );

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $this->error($error);
                }

                return self::FAILURE;
            }

            $user = User::withoutGlobalScopes()->create([
                'store_id' => null,
                'branch_id' => null,
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'is_active' => true,
            ]);

            $user->assignRole('super_admin');

            $this->info("Super Admin [{$user->email}] created successfully with [store_id = null].");

            return self::SUCCESS;
        });
    }
}
