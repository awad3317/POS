<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'pos.sell',
            'pos.return',
            'pos.override_discounts',
            'products.view_cost',
            'products.manage',
            'suppliers.manage',
            'employees.manage',
            'reports.view_profit',
            'settings.manage',
            'shifts.manage',
            'drawer.quick_payout',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $owner = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $owner->syncPermissions($permissions);

        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $manager->syncPermissions([
            'pos.sell',
            'pos.return',
            'pos.override_discounts',
            'products.view_cost',
            'products.manage',
            'suppliers.manage',
            'employees.manage',
            'reports.view_profit',
            'shifts.manage',
            'drawer.quick_payout',
        ]);

        $storekeeper = Role::firstOrCreate(['name' => 'storekeeper', 'guard_name' => 'web']);
        $storekeeper->syncPermissions([
            'products.view_cost',
            'products.manage',
            'suppliers.manage',
        ]);

        $cashier = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
        $cashier->syncPermissions([
            'pos.sell',
            'pos.return',
            'drawer.quick_payout',
        ]);
    }
}
