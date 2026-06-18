<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        $realProducts = [
            [
                'name' => 'ثوب رجالي أبيض فاخر',
                'description' => 'ثوب رجالي قطن ممتاز جاهز',
                'price' => 88.71,
                'regular_price' => 100.00,
            ],
            [
                'name' => 'فستان بناتي عمر 10 سنوات',
                'description' => 'فستان حفلات بناتي ألوان زاهية',
                'price' => 126.00,
                'regular_price' => 150.00,
            ],
            [
                'name' => 'حذاء أبيض كاجوال رجالي',
                'description' => 'حذاء رياضي مريح وخفيف الوزن',
                'price' => 60.64,
                'regular_price' => 75.00,
            ],
            [
                'name' => 'جزمة ولادي لون أسود',
                'description' => 'جزمة رسمية جلد مريحة للأطفال',
                'price' => 44.29,
                'regular_price' => 55.00,
            ]
        ];

        foreach ($realProducts as $item) {
            Product::create([
                'name' => $item['name'],
                'description' => $item['description'],
                'image' => '',
                'quantity' => 50,
                'barcode' => $faker->unique()->ean13, 
                'regular_price' => $item['regular_price'],
                'price' => $item['price'],
                'status' => true,
                'tax' => 0.00,
                'is_custom_product' => false
            ]);
        }
    }
}