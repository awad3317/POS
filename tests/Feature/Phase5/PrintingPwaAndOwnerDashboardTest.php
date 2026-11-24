<?php

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\Terminal;
use App\Models\User;
use App\Services\EmployeeAccountService;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Services\ShiftService;
use App\Support\StoreContext;
use App\Support\WhatsAppLink;
use Database\Seeders\DemoStoresSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

afterEach(function () {
    StoreContext::clear();
    Auth::forgetGuards();
});

test('PWA manifest.json, local icons, and sw.js caching policies are valid and referenced in /pos', function () {
    $this->seed(DemoStoresSeeder::class);

    $manifestPath = public_path('manifest.json');
    $swPath = public_path('sw.js');

    expect(File::exists($manifestPath))->toBeTrue();
    expect(File::exists($swPath))->toBeTrue();

    $manifest = json_decode(File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    expect($manifest['start_url'])->toBe('/pos');
    expect($manifest['display'])->toBe('standalone');
    expect($manifest['dir'])->toBe('rtl');
    expect($manifest['lang'])->toBe('ar');
    expect($manifest['icons'])->toBeArray()->not->toBeEmpty();

    foreach ($manifest['icons'] as $icon) {
        $rel = ltrim((string) $icon['src'], '/');
        expect(File::exists(public_path($rel)))->toBeTrue("Missing PWA icon: {$rel}");
    }

    $sw = File::get($swPath);
    expect($sw)
        ->toContain('/api/v1/sync/')
        ->toContain('/api/v1/auth/')
        ->toContain('/build/')
        ->toContain('/fonts/')
        ->toContain('/pos')
        ->toContain('fetch(request)');

    $cashier = User::where('email', 'cashier@alkhair.ye')->firstOrFail();
    $posHtml = $this->actingAs($cashier)->get('/pos')->assertOk()->getContent();
    expect($posHtml)
        ->toContain('href="/manifest.json"')
        ->toContain("navigator.serviceWorker.register('/sw.js')")
        ->toContain('/build/assets/pos-')
        ->not->toContain('fonts.googleapis.com');
});

test('/barcodes page and AJAX /barcodes/search enforce RBAC, 20-item limit, and strict store isolation', function () {
    $this->seed(DemoStoresSeeder::class);

    $store1 = Store::where('slug', 'baqalat-alkhair')->firstOrFail();
    $store2 = Store::where('slug', 'anaqat-sanaa')->firstOrFail();
    $owner1 = User::where('email', 'owner@alkhair.ye')->firstOrFail();
    $owner2 = User::where('email', 'owner@anaqa.ye')->firstOrFail();
    $cashier1 = User::where('email', 'cashier@alkhair.ye')->firstOrFail();

    // Cashier cannot access /barcodes (requires products.manage)
    $this->actingAs($cashier1)->get('/barcodes')->assertForbidden();
    $this->actingAs($cashier1)->getJson('/barcodes/search?q=عصير')->assertForbidden();

    // Seed 24 additional products in Store 1 + 1 isolated product in Store 2
    StoreContext::withoutScope(function () use ($store1, $store2): void {
        for ($i = 1; $i <= 24; $i++) {
            Product::create([
                'store_id' => $store1->id,
                'name' => "صنف ملصق رقم {$i}",
                'barcode' => '628555000' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'purchase_price' => '100.0000',
                'sale_price' => '150.0000',
                'is_active' => true,
            ]);
        }

        Product::create([
            'store_id' => $store2->id,
            'name' => 'صنف المتجر الثاني السري',
            'barcode' => 'STORE2-BARCODE-999',
            'purchase_price' => '500.0000',
            'sale_price' => '900.0000',
            'is_active' => true,
        ]);
    });

    $html = $this->actingAs($owner1)->get('/barcodes')->assertOk()->getContent();
    expect($html)
        ->toContain('dir="rtl"')
        ->toContain('/build/assets/barcodes-')
        ->not->toContain('fonts.googleapis.com')
        ->not->toContain('STORE2-BARCODE-999');

    // AJAX search returns max 20 items and zero cross-tenant leakage
    $searchRes = $this->actingAs($owner1)->getJson('/barcodes/search?q=صنف')->assertOk()->json();
    expect(count($searchRes['items']))->toBeLessThanOrEqual(20);
    expect(count($searchRes['items']))->toBe(20);

    foreach ($searchRes['items'] as $item) {
        expect($item['barcode'])->not->toBe('STORE2-BARCODE-999');
    }

    // Store 2 variant search returns clothing variants cleanly
    $store2Search = $this->actingAs($owner2)->getJson('/barcodes/search?q=8690001122041')->assertOk()->json();
    expect(count($store2Search['items']))->toBe(1);
    expect($store2Search['items'][0]['type'])->toBe('variant');
    expect($store2Search['items'][0]['subtitle'])->toBe('41 - أسود');
});

test('/owner dashboard reconciles MoneyMath totals, open drawers, and audit alerts while blocking cashier with 403', function () {
    $this->seed(DemoStoresSeeder::class);

    $store1 = Store::where('slug', 'baqalat-alkhair')->firstOrFail();
    $store2 = Store::where('slug', 'anaqat-sanaa')->firstOrFail();
    $owner1 = User::where('email', 'owner@alkhair.ye')->firstOrFail();
    $cashier1 = User::where('email', 'cashier@alkhair.ye')->firstOrFail();
    $owner2 = User::where('email', 'owner@anaqa.ye')->firstOrFail();

    $terminal1 = Terminal::forStore($store1->id)->firstOrFail();
    $rani = Product::forStore($store1->id)->where('barcode', '6281007041126')->firstOrFail(); // price 300, cost 250
    $bread = Product::forStore($store1->id)->where('sku', 'QP-BREAD-01')->firstOrFail(); // price 50
    $customer1 = Customer::forStore($store1->id)->firstOrFail(); // credit_limit 50,000
    $worker1 = Employee::forStore($store1->id)->whereNull('user_id')->firstOrFail();

    // Cashier & Storekeeper blocked with 403
    $this->actingAs($cashier1)->get('/owner')->assertForbidden();

    // Open shift with 10,000 YER
    $shift = app(ShiftService::class)->openShift([
        'terminal_id' => $terminal1->id,
        'opening_cash' => '10000.0000',
    ], $cashier1)['shift'];

    // Today Sale 1: 10 Rani (3,000 cash) + 10 Bread (500 kuraimi) + 5 Rani (1,500 credit) = 5,000
    app(OrderService::class)->createSale([
        'shift_id' => $shift->id,
        'customer_id' => $customer1->id,
        'items' => [
            ['product_id' => $rani->id, 'quantity' => '15.000'], // 4,500
            ['product_id' => $bread->id, 'quantity' => '10.000'], // 500
        ],
        'payments' => [
            ['method' => 'cash', 'amount' => '3000.0000'],
            ['method' => 'kuraimi', 'amount' => '500.0000', 'reference_number' => 'KUR-OWNER-1'],
        ],
        'credit_amount' => '1500.0000',
    ], $cashier1);

    // Today Sale 2: Below-cost sale with manager override PIN -> generates discount_override audit alert (200 cash)
    app(OrderService::class)->createSale([
        'shift_id' => $shift->id,
        'manager_override_pin' => '1122',
        'items' => [
            ['product_id' => $rani->id, 'quantity' => '1.000', 'discount_amount' => '100.0000'], // net 200 < cost 250
        ],
        'payments' => [
            ['method' => 'cash', 'amount' => '200.0000'],
        ],
    ], $cashier1);

    // Employee advance from drawer: 1,000 -> expected cash = 10,000 + 3,200 - 1,000 = 12,200
    app(EmployeeAccountService::class)->recordAdvanceOrPayout($worker1, [
        'type' => 'advance',
        'amount' => '1000.0000',
        'payment_source' => 'cash_drawer',
        'shift_id' => $shift->id,
    ], $owner1);

    // Negative stock movement alert on bread
    app(InventoryService::class)->applyMovement(
        $store1,
        $terminal1->branch,
        $bread,
        null,
        $owner1,
        'adjustment_out',
        '-500.000',
        '40.0000',
        null,
        null,
        'اختبار تنبيه سالب'
    );

    // Audit event for cart item removal in Store 1
    activity()
        ->causedBy($cashier1)
        ->performedOn($store1)
        ->withProperties(['store_id' => $store1->id])
        ->event('cart_item_removed')
        ->log('حذف صنف تجريبي من السلة');

    // Cross-tenant audit event in Store 2 that MUST NOT appear on Store 1's /owner page
    StoreContext::runAs($store2->id, function () use ($owner2, $store2): void {
        activity()
            ->causedBy($owner2)
            ->performedOn($store2)
            ->withProperties(['store_id' => $store2->id])
            ->event('cart_item_removed')
            ->log('SECRET_STORE2_AUDIT_DO_NOT_LEAK');
    });

    $html = $this->actingAs($owner1)->get('/owner')->assertOk()->getContent();

    // Today total = 5,000 + 200 = 5,200; Cash = 3,200; Wallets = 500; Credit = 1,500; Drawer = 12,200
    expect($html)
        ->toContain('dir="rtl"')
        ->toContain('5,200')
        ->toContain('3,200')
        ->toContain('500')
        ->toContain('1,500')
        ->toContain('12,200')
        ->toContain('حذف صنف تجريبي من السلة')
        ->toContain('Sale below cost approved via manager override.')
        ->not->toContain('SECRET_STORE2_AUDIT_DO_NOT_LEAK');
});

test('WhatsAppLink builds normalized Arabic wa.me links across orders, customers, suppliers, and employees', function () {
    $this->seed(DemoStoresSeeder::class);

    $store = Store::where('slug', 'baqalat-alkhair')->firstOrFail();
    $owner = User::where('email', 'owner@alkhair.ye')->firstOrFail();
    $cashier = User::where('email', 'cashier@alkhair.ye')->firstOrFail();
    $terminal = Terminal::forStore($store->id)->firstOrFail();
    $rani = Product::forStore($store->id)->where('barcode', '6281007041126')->firstOrFail();
    $customer = Customer::forStore($store->id)->firstOrFail();
    $supplier = Supplier::forStore($store->id)->firstOrFail();
    $employee = Employee::forStore($store->id)->firstOrFail();

    expect(WhatsAppLink::formatPhone('+967 773 001 122'))->toBe('967773001122');
    expect(WhatsAppLink::formatPhone('00967773001122'))->toBe('967773001122');
    expect(WhatsAppLink::formatPhone('0773001122'))->toBe('967773001122');
    expect(WhatsAppLink::formatPhone('773001122'))->toBe('967773001122');

    $shift = app(ShiftService::class)->openShift(['terminal_id' => $terminal->id, 'opening_cash' => '0'], $cashier)['shift'];
    $order = app(OrderService::class)->createSale([
        'shift_id' => $shift->id,
        'customer_id' => $customer->id,
        'items' => [['product_id' => $rani->id, 'quantity' => '2.000']],
        'payments' => [['method' => 'cash', 'amount' => '600.0000']],
    ], $cashier)['order'];

    $orderUrl = WhatsAppLink::forOrder($order, $store);
    expect($orderUrl)->toStartWith('https://wa.me/967773001122?text=');
    expect(rawurldecode($orderUrl))->toContain($order->invoice_number)->toContain($store->name);

    $custUrl = WhatsAppLink::forCustomerDebt($customer, $store);
    expect($custUrl)->toStartWith('https://wa.me/967773001122?text=');
    expect(rawurldecode($custUrl))->toContain('15,000');

    $suppUrl = WhatsAppLink::forSupplierPayment($supplier, $store);
    expect($suppUrl)->toStartWith('https://wa.me/967770400500?text=');
    expect(rawurldecode($suppUrl))->toContain('250,000');

    $empUrl = WhatsAppLink::forEmployeeStatement($employee, $store);
    expect($empUrl)->toStartWith('https://wa.me/967771223344?text=');
    expect(rawurldecode($empUrl))->toContain($employee->name);

    // Verify UI views embed wa.me links cleanly
    $this->actingAs($owner)->get("/orders/{$order->id}")->assertOk()->assertSee('https://wa.me/', false);
    $this->actingAs($owner)->get('/customers')->assertOk()->assertSee('https://wa.me/', false);
    $this->actingAs($owner)->get('/suppliers')->assertOk()->assertSee('https://wa.me/', false);
    $this->actingAs($owner)->get('/employees')->assertOk()->assertSee('https://wa.me/', false);
});
