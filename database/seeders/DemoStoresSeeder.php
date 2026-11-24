<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\GlobalBarcodeCatalog;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\Terminal;
use App\Models\User;
use App\Support\StoreContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DemoStoresSeeder extends Seeder
{
    public function run(): void
    {
        StoreContext::withoutScope(function (): void {
            $this->call(RolesAndPermissionsSeeder::class);

            $this->seedGlobalBarcodeCatalog();
            $this->seedGroceryStore();
            $this->seedClothingStore();
        });
    }

    protected function seedGlobalBarcodeCatalog(): void
    {
        $items = [
            ['barcode' => '6281007041126', 'name_ar' => 'عصير راني خوخ حبيبات 240 مل', 'name_en' => 'Rani Peach 240ml', 'category_hint' => 'مشروبات', 'default_unit' => 'حبة'],
            ['barcode' => '6291100001014', 'name_ar' => 'شاي الكبوس أحمر فاخر 100 خيط', 'name_en' => 'Al Kbous Tea 100 Bags', 'category_hint' => 'مواد غذائية', 'default_unit' => 'باكت'],
            ['barcode' => '6251591112018', 'name_ar' => 'حليب ممتاز سائل 170 جرام', 'name_en' => 'Mumtaz Evaporated Milk 170g', 'category_hint' => 'ألبان ومعلبات', 'default_unit' => 'علبة'],
            ['barcode' => '8901491101813', 'name_ar' => 'أرز بسمتي مزة هندي 10 كجم', 'name_en' => 'Basmati Rice 10kg', 'category_hint' => 'حبوب وأرز', 'default_unit' => 'كيس'],
            ['barcode' => '6281022101140', 'name_ar' => 'مياه حدة معدنية 750 مل', 'name_en' => 'Haddah Water 750ml', 'category_hint' => 'مياه ومشروبات', 'default_unit' => 'قارورة'],
        ];

        foreach ($items as $item) {
            GlobalBarcodeCatalog::query()->updateOrCreate(
                ['barcode' => $item['barcode']],
                $item
            );
        }
    }

    protected function seedGroceryStore(): void
    {
        $passwordHash = Hash::make((string) env('DEMO_STORE_PASSWORD', 'YemenPos@Demo2026!'));
        $pinHash = Hash::make('1122');

        $store = Store::query()->updateOrCreate(
            ['slug' => 'baqalat-alkhair'],
            [
                'name' => 'بقالة الخير',
                'phone' => '777100200',
                'address' => 'صنعاء - شارع تعز',
                'business_type' => 'grocery',
                'currency_symbol' => 'ر.ي',
                'currency_code' => 'YER',
                'decimal_places' => 0,
                'feature_flags' => [
                    'multi_units' => true,
                    'scale_barcode' => true,
                    'quick_pick' => true,
                    'expiry_dates' => true,
                    'variants' => false,
                ],
                'receipt_settings' => [
                    'paper_width' => '80mm',
                    'header_note' => 'أهلاً بكم في بقالة الخير للمواد الغذائية',
                    'footer_note' => 'شكراً لتسوقكم معنا',
                    'show_qr' => true,
                ],
                'subscription_status' => 'active',
                'onboarding_completed_at' => now(),
            ]
        );

        $branch = Branch::query()->updateOrCreate(
            ['store_id' => $store->id, 'name' => 'الفرع الرئيسي - شارع تعز'],
            ['phone' => '777100200', 'address' => 'شارع تعز جوار جولة الثلاثين', 'is_default' => true, 'is_active' => true]
        );

        Terminal::query()->updateOrCreate(
            ['store_id' => $store->id, 'code' => 'T01'],
            ['branch_id' => $branch->id, 'name' => 'كاشير البقالة الرئيسي', 'last_invoice_seq' => 0, 'is_active' => true]
        );

        $owner = User::query()->updateOrCreate(
            ['email' => 'owner@alkhair.ye'],
            [
                'store_id' => $store->id,
                'branch_id' => $branch->id,
                'name' => 'الحاج صالح القباطي',
                'phone' => '777100200',
                'password' => $passwordHash,
                'pin_hash' => $pinHash,
                'is_active' => true,
            ]
        );
        $owner->syncRoles(['owner']);

        $cashier = User::query()->updateOrCreate(
            ['email' => 'cashier@alkhair.ye'],
            [
                'store_id' => $store->id,
                'branch_id' => $branch->id,
                'name' => 'أكرم الكاشير',
                'phone' => '771223344',
                'password' => $passwordHash,
                'pin_hash' => $pinHash,
                'is_active' => true,
            ]
        );
        $cashier->syncRoles(['cashier']);

        $catDrinks = Category::query()->updateOrCreate(
            ['store_id' => $store->id, 'name' => 'مشروبات وعصائر'],
            ['sort_order' => 1, 'is_active' => true]
        );
        $catFood = Category::query()->updateOrCreate(
            ['store_id' => $store->id, 'name' => 'مواد غذائية وأجبان'],
            ['sort_order' => 2, 'is_active' => true]
        );
        $brandHsa = Brand::query()->updateOrCreate(
            ['store_id' => $store->id, 'name' => 'مجموعة هائل سعيد'],
            ['sort_order' => 1, 'is_active' => true]
        );

        // 1. Multi-unit Grocery Product (حبة / باكت / كرتون)
        $rani = Product::query()->updateOrCreate(
            ['store_id' => $store->id, 'barcode' => '6281007041126'],
            [
                'category_id' => $catDrinks->id,
                'brand_id' => $brandHsa->id,
                'type' => 'simple',
                'name' => 'عصير راني خوخ 240 مل',
                'purchase_price' => '250.0000',
                'sale_price' => '300.0000',
                'wholesale_price' => '280.0000',
                'quantity' => '120.000',
                'min_stock_alert' => '24.000',
                'is_active' => true,
            ]
        );

        ProductUnit::query()->updateOrCreate(
            ['store_id' => $store->id, 'product_id' => $rani->id, 'unit_name' => 'حبة'],
            ['conversion_factor' => '1.000', 'barcode' => '6281007041126', 'purchase_price' => '250.0000', 'sale_price' => '300.0000', 'is_default' => true]
        );
        ProductUnit::query()->updateOrCreate(
            ['store_id' => $store->id, 'product_id' => $rani->id, 'unit_name' => 'باكت 6 حبات'],
            ['conversion_factor' => '6.000', 'barcode' => '6281007041126-P6', 'purchase_price' => '1480.0000', 'sale_price' => '1750.0000', 'is_default' => false]
        );
        ProductUnit::query()->updateOrCreate(
            ['store_id' => $store->id, 'product_id' => $rani->id, 'unit_name' => 'كرتون 24 حبة'],
            ['conversion_factor' => '24.000', 'barcode' => '6281007041126-C24', 'purchase_price' => '5900.0000', 'sale_price' => '6800.0000', 'is_default' => false]
        );

        // 2. Weighted Electronic Scale Product (PLU)
        $cheese = Product::query()->updateOrCreate(
            ['store_id' => $store->id, 'scale_plu_code' => '10025'],
            [
                'category_id' => $catFood->id,
                'type' => 'weighted',
                'name' => 'جبن بلدي تعزي (موزون بالكيلو)',
                'barcode' => '2010025000000',
                'purchase_price' => '4200.0000',
                'sale_price' => '5000.0000',
                'quantity' => '18.500',
                'min_stock_alert' => '3.000',
                'has_expiry' => true,
                'is_active' => true,
            ]
        );

        ProductBatch::query()->updateOrCreate(
            ['store_id' => $store->id, 'product_id' => $cheese->id, 'batch_number' => 'BATCH-2026-11'],
            ['expiry_date' => now()->addDays(20)->toDateString(), 'quantity' => '18.500']
        );

        // 3. Quick-Pick No-Barcode Item (خبز)
        Product::query()->updateOrCreate(
            ['store_id' => $store->id, 'sku' => 'QP-BREAD-01'],
            [
                'category_id' => $catFood->id,
                'type' => 'simple',
                'name' => 'قرص روتي طازج',
                'purchase_price' => '40.0000',
                'sale_price' => '50.0000',
                'quantity' => '200.000',
                'min_stock_alert' => '30.000',
                'is_quick_pick' => true,
                'is_active' => true,
            ]
        );

        StockMovement::query()->firstOrCreate(
            ['store_id' => $store->id, 'product_id' => $rani->id, 'type' => 'adjustment_in'],
            [
                'branch_id' => $branch->id,
                'user_id' => $owner->id,
                'quantity_delta' => '120.000',
                'balance_before' => '0.000',
                'balance_after' => '120.000',
                'unit_cost' => '250.0000',
                'note' => 'رصيد افتتاحي للبقالة',
            ]
        );

        Customer::query()->updateOrCreate(
            ['store_id' => $store->id, 'phone' => '773001122'],
            [
                'client_uuid' => (string) Str::uuid(),
                'name' => 'عبدالرحمن الجرادي',
                'whatsapp' => '967773001122',
                'address' => 'حارة الفردوس',
                'opening_balance' => '15000.0000',
                'credit_limit' => '50000.0000',
                'current_balance' => '15000.0000',
            ]
        );

        Supplier::query()->updateOrCreate(
            ['store_id' => $store->id, 'name' => 'وكالة هائل سعيد للتوزيع'],
            [
                'company_name' => 'مجموعة هائل سعيد أنعم وشركاه',
                'phone' => '01400500',
                'whatsapp' => '967770400500',
                'opening_balance' => '250000.0000',
                'current_balance' => '250000.0000',
            ]
        );

        Employee::query()->updateOrCreate(
            ['store_id' => $store->id, 'user_id' => $cashier->id],
            [
                'branch_id' => $branch->id,
                'name' => 'أكرم الكاشير',
                'phone' => '771223344',
                'job_title' => 'كاشير صباحي',
                'salary_type' => 'monthly',
                'base_salary' => '90000.0000',
                'opening_balance' => '0.0000',
                'current_balance' => '0.0000',
                'hired_at' => '2026-01-01',
                'is_active' => true,
            ]
        );

        // Non-login warehouse worker (user_id === null)
        Employee::query()->updateOrCreate(
            ['store_id' => $store->id, 'phone' => '735998877'],
            [
                'branch_id' => $branch->id,
                'user_id' => null,
                'name' => 'مبارك عامل المخزن والتوصيل',
                'job_title' => 'عامل مخزن وتوصيل',
                'salary_type' => 'daily',
                'base_salary' => '3000.0000',
                'current_balance' => '0.0000',
                'hired_at' => '2026-03-01',
                'is_active' => true,
            ]
        );
    }

    protected function seedClothingStore(): void
    {
        $passwordHash = Hash::make((string) env('DEMO_STORE_PASSWORD', 'YemenPos@Demo2026!'));
        $pinHash = Hash::make('3344');

        $store = Store::query()->updateOrCreate(
            ['slug' => 'anaqat-sanaa'],
            [
                'name' => 'أناقة صنعاء للملابس والأحذية',
                'phone' => '777888999',
                'address' => 'صنعاء - شارع حدة',
                'business_type' => 'clothing',
                'currency_symbol' => 'ر.ي',
                'currency_code' => 'YER',
                'decimal_places' => 0,
                'secondary_currency_code' => 'SAR',
                'exchange_rate' => '140.0000',
                'feature_flags' => [
                    'multi_units' => false,
                    'scale_barcode' => false,
                    'quick_pick' => false,
                    'expiry_dates' => false,
                    'variants' => true,
                    'exchange_rate' => true,
                ],
                'receipt_settings' => [
                    'paper_width' => '80mm',
                    'header_note' => 'أناقة صنعاء - أرقى الملبوسات والأحذية',
                    'footer_note' => 'الاستبدال خلال 3 أيام بإبراز الفاتورة',
                    'show_qr' => true,
                ],
                'subscription_status' => 'active',
                'onboarding_completed_at' => now(),
            ]
        );

        $branch = Branch::query()->updateOrCreate(
            ['store_id' => $store->id, 'name' => 'معرض حدة الرئيسي'],
            ['phone' => '777888999', 'address' => 'شارع حدة أمام المركز الليبي', 'is_default' => true, 'is_active' => true]
        );

        Terminal::query()->updateOrCreate(
            ['store_id' => $store->id, 'code' => 'T01'],
            ['branch_id' => $branch->id, 'name' => 'كاشير المعرض 1', 'last_invoice_seq' => 0, 'is_active' => true]
        );

        $owner = User::query()->updateOrCreate(
            ['email' => 'owner@anaqa.ye'],
            [
                'store_id' => $store->id,
                'branch_id' => $branch->id,
                'name' => 'محمد الحميري',
                'phone' => '777888999',
                'password' => $passwordHash,
                'pin_hash' => $pinHash,
                'is_active' => true,
            ]
        );
        $owner->syncRoles(['owner']);

        $cashier = User::query()->updateOrCreate(
            ['email' => 'cashier@anaqa.ye'],
            [
                'store_id' => $store->id,
                'branch_id' => $branch->id,
                'name' => 'ياسر بائع المعرض',
                'phone' => '774556677',
                'password' => $passwordHash,
                'pin_hash' => $pinHash,
                'is_active' => true,
            ]
        );
        $cashier->syncRoles(['cashier']);

        $catShoes = Category::query()->updateOrCreate(
            ['store_id' => $store->id, 'name' => 'أحذية رجالية'],
            ['sort_order' => 1, 'is_active' => true]
        );
        $brandClassic = Brand::query()->updateOrCreate(
            ['store_id' => $store->id, 'name' => 'أصالة ستايل'],
            ['sort_order' => 1, 'is_active' => true]
        );

        $shoe = Product::query()->updateOrCreate(
            ['store_id' => $store->id, 'sku' => 'SHOE-TURK-01'],
            [
                'category_id' => $catShoes->id,
                'brand_id' => $brandClassic->id,
                'type' => 'variant',
                'name' => 'حذاء جلدي تركي كلاسيك',
                'barcode' => '8690001122000',
                'purchase_price' => '11200.0000',
                'sale_price' => '16000.0000',
                'cost_currency' => 'SAR',
                'foreign_cost_price' => '80.0000',
                'profit_margin_percent' => '42.86',
                'quantity' => '15.000',
                'min_stock_alert' => '3.000',
                'is_active' => true,
            ]
        );

        $variants = [
            ['size' => '41', 'color' => 'أسود', 'attribute_label' => '41 - أسود', 'barcode' => '8690001122041', 'quantity' => '5.000'],
            ['size' => '42', 'color' => 'أسود', 'attribute_label' => '42 - أسود', 'barcode' => '8690001122042', 'quantity' => '6.000'],
            ['size' => '43', 'color' => 'بني', 'attribute_label' => '43 - بني', 'barcode' => '8690001122043', 'quantity' => '4.000'],
        ];

        foreach ($variants as $v) {
            ProductVariant::query()->updateOrCreate(
                ['store_id' => $store->id, 'product_id' => $shoe->id, 'barcode' => $v['barcode']],
                [
                    'size' => $v['size'],
                    'color' => $v['color'],
                    'attribute_label' => $v['attribute_label'],
                    'purchase_price' => '11200.0000',
                    'sale_price' => '16000.0000',
                    'quantity' => $v['quantity'],
                    'is_active' => true,
                ]
            );
        }

        Customer::query()->updateOrCreate(
            ['store_id' => $store->id, 'phone' => '770998811'],
            [
                'client_uuid' => (string) Str::uuid(),
                'name' => 'نبيل العولقي',
                'whatsapp' => '967770998811',
                'address' => 'حي الأصبحي',
                'credit_limit' => '100000.0000',
                'current_balance' => '0.0000',
            ]
        );

        Supplier::query()->updateOrCreate(
            ['store_id' => $store->id, 'name' => 'شركة النخبة لاستيراد الملابس'],
            [
                'company_name' => 'النخبة للتجارة الدولية',
                'phone' => '01223344',
                'opening_balance' => '1200000.0000',
                'current_balance' => '1200000.0000',
            ]
        );

        Employee::query()->updateOrCreate(
            ['store_id' => $store->id, 'user_id' => $cashier->id],
            [
                'branch_id' => $branch->id,
                'name' => 'ياسر بائع المعرض',
                'phone' => '774556677',
                'job_title' => 'بائع وكاشير',
                'salary_type' => 'monthly',
                'base_salary' => '100000.0000',
                'commission_percent' => '2.50',
                'opening_balance' => '0.0000',
                'current_balance' => '0.0000',
                'hired_at' => '2026-02-01',
                'is_active' => true,
            ]
        );

        Employee::query()->updateOrCreate(
            ['store_id' => $store->id, 'phone' => '733445566'],
            [
                'branch_id' => $branch->id,
                'user_id' => null,
                'name' => 'سامي خياط ومجهز المعرض',
                'job_title' => 'خياط تعديل مقاسات',
                'salary_type' => 'weekly',
                'base_salary' => '25000.0000',
                'current_balance' => '0.0000',
                'hired_at' => '2026-02-15',
                'is_active' => true,
            ]
        );
    }
}
