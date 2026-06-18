<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            ['key' => 'currency_symbol', 'value' => 'ر.ي'],
            ['key' => 'site_name', 'value' => 'تيار'],
            ['key' => 'site_description', 'value' => 'متجر تيار  للأحذية والملابس جاهز لخدمتكم'], 
            ['key' => 'site_email', 'value' => 'admin@alam-alqama.com'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(['key' => $setting['key']], ['value' => $setting['value']]);
        }
    }
}