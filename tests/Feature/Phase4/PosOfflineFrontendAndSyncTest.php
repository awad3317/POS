<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\EmployeeTransaction;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\Shift;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\Terminal;
use App\Models\User;
use App\Support\StoreContext;
use Database\Seeders\DemoStoresSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

afterEach(function () {
    StoreContext::clear();
    Auth::forgetGuards();
});

test('GET /pos enforces auth, permissions, store isolation, local assets, and zero external CDNs', function () {
    $this->seed(DemoStoresSeeder::class);

    $this->get('/pos')->assertRedirect('/login');

    $store1 = Store::where('slug', 'baqalat-alkhair')->firstOrFail();
    $store2 = Store::where('slug', 'anaqat-sanaa')->firstOrFail();
    $cashier1 = User::where('email', 'cashier@alkhair.ye')->firstOrFail();

    // Create a storekeeper in Store 1 (lacks pos.sell permission)
    $storekeeper = User::create([
        'store_id' => $store1->id,
        'branch_id' => $cashier1->branch_id,
        'name' => 'أمين مخزن',
        'email' => 'keeper@alkhair.ye',
        'password' => bcrypt('SecretPass@2026'),
        'is_active' => true,
    ]);
    $storekeeper->assignRole('storekeeper');

    $this->actingAs($storekeeper)->get('/pos')->assertForbidden();

    // Add an unmistakable terminal to Store 2 (bypassing current Auth StoreContext) to verify isolation
    $store2Terminal = StoreContext::withoutScope(fn () => Terminal::create([
        'store_id' => $store2->id,
        'branch_id' => $store2->branches()->withoutStoreScope()->first()->id,
        'code' => 'STORE2-ISOLATED-T99',
        'name' => 'جهاز المتجر الثاني المعزول',
        'is_active' => true,
    ]));
    expect($store2Terminal->store_id)->toBe($store2->id);

    $html = $this->actingAs($cashier1)->get('/pos')->assertOk()->getContent();

    expect($html)
        ->toContain('dir="rtl"')
        ->toContain('posApp(')
        ->toContain('/build/assets/pos-')
        ->not->toContain('fonts.googleapis.com')
        ->not->toContain('fonts.gstatic.com')
        ->not->toContain('https://cdn.')
        ->not->toContain('http://cdn.')
        ->not->toContain('STORE2-ISOLATED-T99')
        ->not->toContain('purchase_price');

    // Verify Vite config includes resources/js/pos.js and local POS modules + Phase 5 print contract exist
    expect(File::get(base_path('vite.config.js')))->toContain('resources/js/pos.js');
    expect(File::exists(resource_path('js/pos.js')))->toBeTrue();
    expect(File::exists(resource_path('js/pos/pos-db.js')))->toBeTrue();
    expect(File::exists(resource_path('js/pos/pos-uuid.js')))->toBeTrue();
    expect(File::exists(resource_path('js/pos/pos-barcode.js')))->toBeTrue();
    expect(File::exists(resource_path('js/pos/pos-cart-math.js')))->toBeTrue();
    expect(File::exists(resource_path('js/pos/pos-sync.js')))->toBeTrue();
    expect(File::exists(resource_path('js/pos/pos-app.js')))->toBeTrue();
    expect(File::get(resource_path('js/pos/pos-app.js')))->toContain('pos:print-receipt');
    expect(File::get(resource_path('js/pos/pos-sync.js')))->toContain('beforeunload');
    expect(File::get(resource_path('js/pos/pos-db.js')))->toContain('navigator.storage.persist');
});

test('full offline POS shift session (10 diverse orders, offline customer, drawer payouts, shift close) reconciles to 0.0000 and stays idempotent', function () {
    $this->seed(DemoStoresSeeder::class);

    $store = Store::where('slug', 'baqalat-alkhair')->firstOrFail();
    $cashier = User::where('email', 'cashier@alkhair.ye')->firstOrFail();
    $terminal = Terminal::forStore($store->id)->where('code', 'T01')->firstOrFail();

    $rani = Product::forStore($store->id)->where('barcode', '6281007041126')->firstOrFail(); // qty 120.000, cost 250, sale 300
    $pack6 = ProductUnit::forStore($store->id)->where('product_id', $rani->id)->where('conversion_factor', '6.000')->firstOrFail(); // 1750
    $carton24 = ProductUnit::forStore($store->id)->where('product_id', $rani->id)->where('conversion_factor', '24.000')->firstOrFail(); // 6800
    $cheese = Product::forStore($store->id)->where('scale_plu_code', '10025')->firstOrFail(); // qty 18.500, sale 5000
    $bread = Product::forStore($store->id)->where('sku', 'QP-BREAD-01')->firstOrFail(); // qty 200.000, sale 50
    $existingCustomer = Customer::forStore($store->id)->where('phone', '773001122')->firstOrFail(); // balance 15000.0000
    $supplier = Supplier::forStore($store->id)->firstOrFail(); // balance 250000.0000
    $worker = Employee::forStore($store->id)->whereNull('user_id')->firstOrFail(); // balance 0.0000

    // Add a variant product in Store 1 so variant sales are also tested within the same offline shift
    $cat = Category::forStore($store->id)->firstOrFail();
    $variantProd = Product::create([
        'store_id' => $store->id,
        'category_id' => $cat->id,
        'type' => 'variant',
        'name' => 'ترمس حافظ حرارة',
        'barcode' => '6289000111000',
        'purchase_price' => '1000.0000',
        'sale_price' => '1500.0000',
        'quantity' => '10.000',
        'is_active' => true,
    ]);
    $variantItem = ProductVariant::create([
        'store_id' => $store->id,
        'product_id' => $variantProd->id,
        'size' => '1 لتر',
        'color' => 'فضي',
        'attribute_label' => '1 لتر - فضي',
        'barcode' => '6289000111001',
        'purchase_price' => '1000.0000',
        'sale_price' => '1500.0000',
        'quantity' => '10.000',
        'is_active' => true,
    ]);

    // Authenticate via /api/v1/auth/terminal-login just like PosApp does
    $loginRes = $this->postJson('/api/v1/auth/terminal-login', [
        'login' => 'cashier@alkhair.ye',
        'password' => 'YemenPos@Demo2026!',
        'terminal_code' => 'T01',
    ])->assertOk()->json();

    expect($loginRes['token'])->toBeString();
    Sanctum::actingAs($cashier);

    // Bootstrap check ensures quick_payees & products match offline DB expectations
    $boot = $this->getJson('/api/v1/sync/bootstrap')->assertOk()->json();
    expect(count($boot['products']))->toBe(4);
    expect(count($boot['quick_payees']))->toBeGreaterThanOrEqual(3);

    $offlineCustUuid = (string) Str::uuid();
    $shiftUuid = (string) Str::uuid();
    $orderUuids = array_map(fn () => (string) Str::uuid(), range(1, 10));
    $settlementUuid = (string) Str::uuid();
    $expenseUuid = (string) Str::uuid();
    $advanceUuid = (string) Str::uuid();
    $supplierPayUuid = (string) Str::uuid();
    $auditUuid = (string) Str::uuid();

    // Expected Shift Cash Math:
    // Opening: 50,000.0000
    // Cash Sales: 3,500 (Order 1) + 2,000 (Order 8 net) + 200 (Order 9) = 5,700.0000
    // Cash In: 400.0000 (Settlement)
    // Cash Out: 1,200 (Expense) + 3,000 (Advance) + 10,000 (Supplier) = 14,200.0000
    // Expected Cash = 50,000 + 5,700 + 400 - 14,200 = 41,900.0000
    // Actual Cash = 41,000.0000 -> Difference = -900.0000
    $batchPayload = [
        'customers' => [
            [
                'client_uuid' => $offlineCustUuid,
                'name' => 'بقالة الأمل - عميل أوفلاين',
                'phone' => '778899001',
                'credit_limit' => '50000.0000',
            ],
        ],
        'shifts' => [
            [
                'client_uuid' => $shiftUuid,
                'terminal_code' => $terminal->code,
                'opening_cash' => '50000.0000',
                'opened_at' => now()->subHours(8)->toIso8601String(),
                'status' => 'closed',
                'actual_cash' => '41000.0000',
                'closed_at' => now()->subMinutes(5)->toIso8601String(),
                'notes' => 'إغلاق وردية أوفلاين كاملة',
            ],
        ],
        'orders' => [
            // 1. Cash with banknote change: 2 * pack6 (3,500), cash 5,000, change 1,500 -> Rani -12
            [
                'client_uuid' => $orderUuids[0],
                'shift_client_uuid' => $shiftUuid,
                'invoice_number' => 'INV-T01-2026-000001',
                'items' => [['product_id' => $rani->id, 'unit_id' => $pack6->id, 'quantity' => '2.000']],
                'payments' => [['client_uuid' => (string) Str::uuid(), 'method' => 'cash', 'amount' => '5000.0000']],
                'change_amount' => '1500.0000',
                'credit_amount' => '0.0000',
                'sold_at' => now()->subHours(7)->toIso8601String(),
            ],
            // 2. Kuraimi wallet: 1 * carton24 (6,800) -> Rani -24
            [
                'client_uuid' => $orderUuids[1],
                'shift_client_uuid' => $shiftUuid,
                'invoice_number' => 'INV-T01-2026-000002',
                'items' => [['product_id' => $rani->id, 'unit_id' => $carton24->id, 'quantity' => '1.000']],
                'payments' => [['client_uuid' => (string) Str::uuid(), 'method' => 'kuraimi', 'reference_number' => 'KUR-101', 'amount' => '6800.0000']],
                'change_amount' => '0.0000',
                'credit_amount' => '0.0000',
                'sold_at' => now()->subHours(6)->toIso8601String(),
            ],
            // 3. Jawali wallet + Scale prefix 20 (1.750 kg cheese * 5,000 = 8,750) -> Cheese -1.750
            [
                'client_uuid' => $orderUuids[2],
                'shift_client_uuid' => $shiftUuid,
                'invoice_number' => 'INV-T01-2026-000003',
                'items' => [['product_id' => $cheese->id, 'quantity' => '1.750']],
                'payments' => [['client_uuid' => (string) Str::uuid(), 'method' => 'jawali', 'reference_number' => 'JAW-202', 'amount' => '8750.0000']],
                'change_amount' => '0.0000',
                'credit_amount' => '0.0000',
                'sold_at' => now()->subHours(5)->toIso8601String(),
            ],
            // 4. Floosak wallet + Scale prefix 21 (0.500 kg cheese * 5,000 = 2,500) -> Cheese -0.500
            [
                'client_uuid' => $orderUuids[3],
                'shift_client_uuid' => $shiftUuid,
                'invoice_number' => 'INV-T01-2026-000004',
                'items' => [['product_id' => $cheese->id, 'quantity' => '0.500']],
                'payments' => [['client_uuid' => (string) Str::uuid(), 'method' => 'floosak', 'reference_number' => 'FLO-303', 'amount' => '2500.0000']],
                'change_amount' => '0.0000',
                'credit_amount' => '0.0000',
                'sold_at' => now()->subHours(4)->toIso8601String(),
            ],
            // 5. OneCash wallet + Quick-pick bread (10 * 50 = 500) -> Bread -10
            [
                'client_uuid' => $orderUuids[4],
                'shift_client_uuid' => $shiftUuid,
                'invoice_number' => 'INV-T01-2026-000005',
                'items' => [['product_id' => $bread->id, 'quantity' => '10.000']],
                'payments' => [['client_uuid' => (string) Str::uuid(), 'method' => 'onecash', 'reference_number' => 'ONE-404', 'amount' => '500.0000']],
                'change_amount' => '0.0000',
                'credit_amount' => '0.0000',
                'sold_at' => now()->subHours(4)->toIso8601String(),
            ],
            // 6. Card payment + Variant product (2 * 1,500 = 3,000) -> Variant -2
            [
                'client_uuid' => $orderUuids[5],
                'shift_client_uuid' => $shiftUuid,
                'invoice_number' => 'INV-T01-2026-000006',
                'items' => [['product_id' => $variantProd->id, 'variant_id' => $variantItem->id, 'quantity' => '2.000']],
                'payments' => [['client_uuid' => (string) Str::uuid(), 'method' => 'card', 'reference_number' => 'CRD-505', 'amount' => '3000.0000']],
                'change_amount' => '0.0000',
                'credit_amount' => '0.0000',
                'sold_at' => now()->subHours(3)->toIso8601String(),
            ],
            // 7. Full credit sale to NEW offline customer (3 single Rani * 300 = 900) -> Rani -3, OfflineCust +900
            [
                'client_uuid' => $orderUuids[6],
                'shift_client_uuid' => $shiftUuid,
                'customer_client_uuid' => $offlineCustUuid,
                'invoice_number' => 'INV-T01-2026-000007',
                'items' => [['product_id' => $rani->id, 'quantity' => '3.000']],
                'payments' => [],
                'change_amount' => '0.0000',
                'credit_amount' => '900.0000',
                'sold_at' => now()->subHours(3)->toIso8601String(),
            ],
            // 8. Mixed payment (Cash + Kuraimi + Credit to existing customer): 10 Rani (3,000) + 1kg cheese (5,000) = 8,000
            // Cash tendered 2,500 (500 change -> 2,000 net cash) + Kuraimi 3,000 + Credit 3,000
            [
                'client_uuid' => $orderUuids[7],
                'shift_client_uuid' => $shiftUuid,
                'customer_id' => $existingCustomer->id,
                'invoice_number' => 'INV-T01-2026-000008',
                'items' => [
                    ['product_id' => $rani->id, 'quantity' => '10.000'],
                    ['product_id' => $cheese->id, 'quantity' => '1.000'],
                ],
                'payments' => [
                    ['client_uuid' => (string) Str::uuid(), 'method' => 'cash', 'amount' => '2500.0000'],
                    ['client_uuid' => (string) Str::uuid(), 'method' => 'kuraimi', 'reference_number' => 'KUR-808', 'amount' => '3000.0000'],
                ],
                'change_amount' => '500.0000',
                'credit_amount' => '3000.0000',
                'sold_at' => now()->subHours(2)->toIso8601String(),
            ],
            // 9. Below-cost sale with manager_override_pin ('1122'): 1 Rani (300 - 100 discount = 200 < cost 250)
            [
                'client_uuid' => $orderUuids[8],
                'shift_client_uuid' => $shiftUuid,
                'invoice_number' => 'INV-T01-2026-000009',
                'manager_override_pin' => '1122',
                'items' => [['product_id' => $rani->id, 'quantity' => '1.000', 'discount_amount' => '100.0000']],
                'payments' => [['client_uuid' => (string) Str::uuid(), 'method' => 'cash', 'amount' => '200.0000']],
                'change_amount' => '0.0000',
                'credit_amount' => '0.0000',
                'sold_at' => now()->subHour()->toIso8601String(),
            ],
            // 10. Bank transfer + Quick-pick bread (4 * 50 = 200) -> Bread -4
            [
                'client_uuid' => $orderUuids[9],
                'shift_client_uuid' => $shiftUuid,
                'invoice_number' => 'INV-T01-2026-000010',
                'items' => [['product_id' => $bread->id, 'quantity' => '4.000']],
                'payments' => [['client_uuid' => (string) Str::uuid(), 'method' => 'bank_transfer', 'reference_number' => 'BNK-909', 'amount' => '200.0000']],
                'change_amount' => '0.0000',
                'credit_amount' => '0.0000',
                'sold_at' => now()->subMinutes(30)->toIso8601String(),
            ],
        ],
        'payments' => [
            // Offline customer pays back 400 cash -> balance drops from 900 to 500
            [
                'client_uuid' => $settlementUuid,
                'shift_client_uuid' => $shiftUuid,
                'customer_client_uuid' => $offlineCustUuid,
                'method' => 'cash',
                'amount' => '400.0000',
                'paid_at' => now()->subMinutes(20)->toIso8601String(),
            ],
        ],
        'expenses' => [
            [
                'client_uuid' => $expenseUuid,
                'shift_client_uuid' => $shiftUuid,
                'category' => 'أكياس وتغليف',
                'amount' => '1200.0000',
                'payment_source' => 'cash_drawer',
                'spent_at' => now()->subMinutes(15)->toIso8601String(),
            ],
        ],
        'employee_advances' => [
            [
                'client_uuid' => $advanceUuid,
                'shift_client_uuid' => $shiftUuid,
                'employee_id' => $worker->id,
                'type' => 'advance',
                'amount' => '3000.0000',
                'payment_source' => 'cash_drawer',
                'transaction_date' => now()->subMinutes(12)->toIso8601String(),
            ],
        ],
        'supplier_payments' => [
            [
                'client_uuid' => $supplierPayUuid,
                'shift_client_uuid' => $shiftUuid,
                'supplier_id' => $supplier->id,
                'method' => 'cash',
                'payment_source' => 'cash_drawer',
                'amount' => '10000.0000',
                'paid_at' => now()->subMinutes(10)->toIso8601String(),
            ],
        ],
        'audit_events' => [
            [
                'client_uuid' => $auditUuid,
                'event' => 'cart_item_removed',
                'description' => 'حذف الصنف (عصير راني) من السلة',
                'properties' => ['product_id' => $rani->id, 'terminal_code' => 'T01'],
            ],
        ],
    ];

    $pushResponse = $this->postJson('/api/v1/sync/push', $batchPayload)->assertOk()->json();
    $results = $pushResponse['results'];

    expect($results['customers'][$offlineCustUuid]['status'])->toBe('synced');
    expect($results['shifts'][$shiftUuid]['status'])->toBe('synced');
    expect($results['shifts'][$shiftUuid]['expected_cash'])->toBe('41900.0000');
    expect($results['shifts'][$shiftUuid]['difference'])->toBe('-900.0000');

    foreach ($orderUuids as $oUuid) {
        expect($results['orders'][$oUuid]['status'])->toBe('synced');
    }
    expect($results['payments'][$settlementUuid]['status'])->toBe('synced');
    expect($results['expenses'][$expenseUuid]['status'])->toBe('synced');
    expect($results['employee_advances'][$advanceUuid]['status'])->toBe('synced');
    expect($results['supplier_payments'][$supplierPayUuid]['status'])->toBe('synced');
    expect($results['audit_events'][$auditUuid]['status'])->toBe('synced');

    // Exact inventory balances (server + stock_balances snapshot)
    expect($rani->fresh()->quantity)->toBe('70.000'); // 120 - (12 + 24 + 3 + 10 + 1)
    expect($cheese->fresh()->quantity)->toBe('15.250'); // 18.500 - (1.750 + 0.500 + 1.000)
    expect($bread->fresh()->quantity)->toBe('186.000'); // 200 - (10 + 4)
    expect($variantItem->fresh()->quantity)->toBe('8.000'); // 10 - 2
    expect($pushResponse['stock_balances']['products'][$rani->id])->toBe('70.000');
    expect($pushResponse['stock_balances']['variants'][$variantItem->id])->toBe('8.000');

    // Exact shift, customer, worker, supplier, and audit balances
    $syncedShift = Shift::forStore($store->id)->where('client_uuid', $shiftUuid)->firstOrFail();
    expect($syncedShift->status)->toBe('closed');
    expect($syncedShift->cash_sales_total)->toBe('5700.0000');
    expect($syncedShift->cash_in_total)->toBe('400.0000');
    expect($syncedShift->cash_out_total)->toBe('14200.0000');
    expect($syncedShift->expected_cash)->toBe('41900.0000');
    expect($syncedShift->actual_cash)->toBe('41000.0000');
    expect($syncedShift->difference)->toBe('-900.0000');

    $offlineCust = Customer::forStore($store->id)->where('client_uuid', $offlineCustUuid)->firstOrFail();
    expect($offlineCust->current_balance)->toBe('500.0000');
    expect($existingCustomer->fresh()->current_balance)->toBe('18000.0000');
    expect($worker->fresh()->current_balance)->toBe('-3000.0000');
    expect($supplier->fresh()->current_balance)->toBe('240000.0000');
    expect($terminal->fresh()->last_invoice_seq)->toBe(10);

    expect(Activity::where('event', 'cart_item_removed')->where('batch_uuid', $auditUuid)->exists())->toBeTrue();
    expect(Activity::where('event', 'discount_override')->exists())->toBeTrue();

    // Idempotency replay: re-sending exact payload returns 'duplicate' with ZERO balance drift
    $replay = $this->postJson('/api/v1/sync/push', $batchPayload)->assertOk()->json('results');
    expect($replay['customers'][$offlineCustUuid]['status'])->toBe('duplicate');
    expect($replay['shifts'][$shiftUuid]['status'])->toBe('duplicate');
    foreach ($orderUuids as $oUuid) {
        expect($replay['orders'][$oUuid]['status'])->toBe('duplicate');
    }
    expect($replay['payments'][$settlementUuid]['status'])->toBe('duplicate');
    expect($replay['expenses'][$expenseUuid]['status'])->toBe('duplicate');
    expect($replay['employee_advances'][$advanceUuid]['status'])->toBe('duplicate');
    expect($replay['supplier_payments'][$supplierPayUuid]['status'])->toBe('duplicate');
    expect($replay['audit_events'][$auditUuid]['status'])->toBe('duplicate');

    expect(Order::forStore($store->id)->count())->toBe(10);
    expect(Payment::forStore($store->id)->count())->toBe(11); // 10 payments across orders (Order 7 has 0, Order 8 has 2) + 1 settlement
    expect(Expense::forStore($store->id)->count())->toBe(1);
    expect(EmployeeTransaction::forStore($store->id)->count())->toBe(1);
    expect(SupplierPayment::forStore($store->id)->count())->toBe(1);
    expect($rani->fresh()->quantity)->toBe('70.000');
    expect($offlineCust->fresh()->current_balance)->toBe('500.0000');
    expect($syncedShift->fresh()->expected_cash)->toBe('41900.0000');
});
