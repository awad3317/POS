<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Customer::create([
            'id' => 1,
            'first_name' => 'عميل نقدي',
            'last_name' => '',
            'email' => null,
            'phone' => null,
            'address' => null,
            'avatar' => '',
        ]);
    }
}