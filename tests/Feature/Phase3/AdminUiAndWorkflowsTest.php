<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\EmployeeTransaction;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shift;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\Terminal;
use App\Models\User;
use App\Services\OrderService;
use App\Services\ShiftService;
use App\Support\StoreContext;
use Database\Seeders\DemoStoresSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

afterEach(function () {
    StoreContext::clear();
    Auth::forgetGuards();
});

test('guest redirects to /login and un-onboarded store owner completes 60-second onboarding wizard cleanly', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->get('/dashboard')->assertRedirect('/login');

    $newStore = Store::create([
        'name' => 'متجر قيد التأسيس',
        'slug' => 'new-yemen-store',
        'onboarding_completed_at' => null,
    ]);

    $owner = User::create([
        'store_id' => $newStore->id,
        'name' => 'مالك جديد',
        'email' => 'newowner@yemenpos.ye',
        'password' => Hash::make('StrongPass@2026'),
        'is_active' => true,
    ]);
    $owner->assignRole('owner');

    // Login via web session -> redirected to /onboarding because onboarding_completed_at is null
    $this->post('/login', [
        'login' => 'newowner@yemenpos.ye',
        'password' => 'StrongPass@2026',
    ])->assertRedirect('/onboarding');

    $this->actingAs($owner)->get('/dashboard')->assertRedirect('/onboarding');
    $this->actingAs($owner)->get('/onboarding')->assertOk()->assertSee('dir="rtl"', false);

    // Submit 60-second onboarding wizard as grocery
    $this->actingAs($owner)->post('/onboarding', [
        'name' => 'سوبرماركت السعيدة',
        'phone' => '777555444',
        'address' => 'تعز - شارع جمال',
        'currency_code' => 'YER',
        'decimal_places' => 0,
        'business_type' => 'grocery',
        'paper_width' => '80mm',
        'header_note' => 'أهلاً بكم في السعيدة',
        'footer_note' => 'شكراً لكم',
    ])->assertRedirect('/dashboard');

    $freshStore = $newStore->fresh();
    expect($freshStore->onboarding_completed_at)->not->toBeNull();
    expect($freshStore->name)->toBe('سوبرماركت السعيدة');
    expect($freshStore->feature_flags['multi_units'])->toBeTrue();
    expect($freshStore->feature_flags['scale_plu'])->toBeTrue();
    expect(Category::forStore($freshStore->id)->count())->toBeGreaterThanOrEqual(5);
    expect(Terminal::forStore($freshStore->id)->where('code', 'T01')->exists())->toBeTrue();
});

test('all admin screens render HTTP 200 with RTL Arabic layout and zero external font CDNs for store owner', function () {
    $this->seed(DemoStoresSeeder::class);
    $owner = User::where('email', 'owner@alkhair.ye')->firstOrFail();

    $routes = [
        '/dashboard',
        '/products',
        '/products/create',
        '/suppliers',
        '/purchases',
        '/purchases/create',
        '/stocktake',
        '/employees',
        '/customers',
        '/orders',
        '/shifts',
        '/expenses',
        '/reports',
        '/settings',
    ];

    foreach ($routes as $uri) {
        $html = $this->actingAs($owner)->get($uri)->assertOk()->getContent();
        expect($html)
            ->toContain('dir="rtl"')
            ->not->toContain('fonts.googleapis.com');
    }
});

test('RBAC blocks cashier from management screens with 403 and orders ledger has zero edit or delete routes', function () {
    $this->seed(DemoStoresSeeder::class);

    $store = Store::where('slug', 'baqalat-alkhair')->firstOrFail();
    $cashier = User::where('email', 'cashier@alkhair.ye')->firstOrFail();
    $terminal = Terminal::forStore($store->id)->firstOrFail();
    $product = Product::forStore($store->id)->where('barcode', '6281007041126')->firstOrFail();

    foreach (['/suppliers', '/employees', '/reports', '/settings'] as $restrictedUri) {
        $this->actingAs($cashier)->get($restrictedUri)->assertForbidden();
    }

    $shift = app(ShiftService::class)->openShift(['terminal_id' => $terminal->id, 'opening_cash' => '10000'], $cashier)['shift'];
    $order = app(OrderService::class)->createSale([
        'shift_id' => $shift->id,
        'items' => [['product_id' => $product->id, 'quantity' => '1']],
        'payments' => [['method' => 'cash', 'amount' => '300']],
    ], $cashier)['order'];

    $owner = User::where('email', 'owner@alkhair.ye')->firstOrFail();
    $delStatus = $this->actingAs($owner)->delete("/orders/{$order->id}")->getStatusCode();
    $putStatus = $this->actingAs($owner)->put("/orders/{$order->id}", [])->getStatusCode();

    expect($delStatus)->toBeIn([404, 405]);
    expect($putStatus)->toBeIn([404, 405]);
});

test('products support global barcode auto-lookup, soft-deleted barcode reuse, and UTF-8 BOM CSV round-trip', function () {
    $this->seed(DemoStoresSeeder::class);

    $store = Store::where('slug', 'baqalat-alkhair')->firstOrFail();
    $owner = User::where('email', 'owner@alkhair.ye')->firstOrFail();

    // 1. Global barcode lookup
    $this->actingAs($owner)
        ->getJson('/products/barcode-lookup?barcode=6291100001014')
        ->assertOk()
        ->assertJson([
            'found' => true,
            'name_ar' => 'شاي الكبوس أحمر فاخر 100 خيط',
        ]);

    // 2. Duplicate active barcode in same store fails validation
    $this->actingAs($owner)->post('/products', [
        'name' => 'منتج مكرر الباركود',
        'type' => 'simple',
        'barcode' => '6281007041126',
        'purchase_price' => '200',
        'sale_price' => '250',
    ])->assertSessionHasErrors('barcode');

    // 3. Soft-deleting product allows reusing its barcode cleanly
    $tempProduct = Product::forStore($store->id)->create([
        'store_id' => $store->id,
        'name' => 'صنف مؤقت',
        'barcode' => '777000111222',
        'purchase_price' => '500.0000',
        'sale_price' => '700.0000',
    ]);

    $this->actingAs($owner)->delete("/products/{$tempProduct->id}")->assertRedirect('/products');
    expect($tempProduct->fresh()->trashed())->toBeTrue();

    $this->actingAs($owner)->post('/products', [
        'name' => 'صنف جديد بنفس الباركود المؤرشف',
        'type' => 'simple',
        'barcode' => '777000111222',
        'purchase_price' => '550',
        'sale_price' => '750',
        'quantity' => '15',
    ])->assertRedirect('/products');

    expect(Product::forStore($store->id)->where('barcode', '777000111222')->value('quantity'))->toBe('15.000');

    // 4. Native UTF-8 BOM CSV Export & Import Round-Trip
    $exportResponse = $this->actingAs($owner)->get('/products/export-csv')->assertOk();
    $csvContent = $exportResponse->streamedContent();
    expect(str_starts_with($csvContent, "\xEF\xBB\xBF"))->toBeTrue();

    $importPayload = "\xEF\xBB\xBFbarcode,name,sku,type,purchase_price,sale_price,quantity,min_stock_alert,tax_rate\n"
        . "6281007041126,عصير راني خوخ محدث,RANI-UPD,simple,260.5000,320.0000,150.000,20.000,0.00\n"
        . "6291100001014,شاي الكبوس 100 خيط,KBOUS-100,simple,1100.0000,1350.0000,40.000,10.000,0.00\n";

    $csvFile = UploadedFile::fake()->createWithContent('products_import.csv', $importPayload);

    $this->actingAs($owner)
        ->post('/products/import-csv', ['csv_file' => $csvFile])
        ->assertRedirect('/products');

    $updatedRani = Product::forStore($store->id)->where('barcode', '6281007041126')->firstOrFail();
    expect($updatedRani->purchase_price)->toBe('260.5000');
    expect($updatedRani->sale_price)->toBe('320.0000');
    expect($updatedRani->quantity)->toBe('150.000');

    $importedTea = Product::forStore($store->id)->where('barcode', '6291100001014')->firstOrFail();
    expect($importedTea->sale_price)->toBe('1350.0000');
    expect($importedTea->quantity)->toBe('40.000');
});

test('end-to-end admin financial workflows execute accurately via Phase 2 services', function () {
    $this->seed(DemoStoresSeeder::class);

    $grocery = Store::where('slug', 'baqalat-alkhair')->firstOrFail();
    $owner = User::where('email', 'owner@alkhair.ye')->firstOrFail();
    $cashier = User::where('email', 'cashier@alkhair.ye')->firstOrFail();
    $terminal = Terminal::forStore($grocery->id)->firstOrFail();
    $rani = Product::forStore($grocery->id)->where('barcode', '6281007041126')->firstOrFail(); // qty 120, sale 300
    $supplier = Supplier::forStore($grocery->id)->firstOrFail(); // 250,000
    $customer = Customer::forStore($grocery->id)->firstOrFail(); // 15,000
    $akramEmp = Employee::forStore($grocery->id)->where('user_id', $cashier->id)->firstOrFail(); // 0

    // 1. Purchase + Supplier Payment via Web Routes
    $this->actingAs($owner)->post('/purchases', [
        'supplier_id' => $supplier->id,
        'paid_amount' => '0',
        'credit_amount' => '25000',
        'payment_source' => 'owner_pocket',
        'items' => [
            ['product_id' => $rani->id, 'quantity' => '100', 'unit_cost' => '250'],
        ],
    ])->assertRedirect('/purchases');

    expect($supplier->fresh()->current_balance)->toBe('275000.0000');
    expect($rani->fresh()->quantity)->toBe('220.000');

    $this->actingAs($owner)->post("/suppliers/{$supplier->id}/payments", [
        'amount' => '75000',
        'method' => 'kuraimi',
        'payment_source' => 'bank',
        'reference_number' => 'KUR-5511',
    ])->assertRedirect();

    expect($supplier->fresh()->current_balance)->toBe('200000.0000');
    $this->actingAs($owner)->get("/suppliers/{$supplier->id}/statement")->assertOk()->assertSee('https://wa.me/', false);

    // 2. Employee Salary Accrual + Advance via Web Routes
    $this->actingAs($owner)->post('/employees/accrue-salaries', [
        'period_label' => '2026-11',
    ])->assertRedirect('/employees');

    $this->actingAs($owner)->post("/employees/{$akramEmp->id}/transactions", [
        'type' => 'advance',
        'amount' => '12000',
        'payment_source' => 'owner_pocket',
        'notes' => 'سلفة منتصف الشهر',
    ])->assertRedirect();

    expect($akramEmp->fresh()->current_balance)->toBe('78000.0000');
    $this->actingAs($owner)->get("/employees/{$akramEmp->id}/statement")->assertOk()->assertSee('https://wa.me/', false);

    // 3. Customer Credit Settlement via Web Route
    $this->actingAs($owner)->post("/customers/{$customer->id}/settlements", [
        'amount' => '5000',
        'method' => 'jawali',
    ])->assertRedirect();

    expect($customer->fresh()->current_balance)->toBe('10000.0000');
    $this->actingAs($owner)->get("/customers/{$customer->id}/statement")->assertOk()->assertSee('https://wa.me/', false);

    // 4. Order Return + Shift Close + Transfer Shortage via Web Routes
    $shift = app(ShiftService::class)->openShift(['terminal_id' => $terminal->id, 'opening_cash' => '20000'], $cashier)['shift'];
    $sale = app(OrderService::class)->createSale([
        'shift_id' => $shift->id,
        'items' => [['product_id' => $rani->id, 'quantity' => '4']], // 1200 YER, stock -> 216
        'payments' => [['method' => 'cash', 'amount' => '1200']],
    ], $cashier)['order'];

    $this->actingAs($owner)->post("/orders/{$sale->id}/return", [
        'shift_id' => $shift->id,
        'refund_method' => 'cash',
        'items' => [
            [
                'order_item_id' => $sale->items->first()->id,
                'product_id' => $rani->id,
                'quantity' => '1',
                'return_condition' => 'restock',
            ],
        ],
    ])->assertRedirect();

    expect($sale->fresh()->status)->toBe('partially_refunded');
    expect($rani->fresh()->quantity)->toBe('217.000');

    // Shift expected cash = 20,000 + 1,200 - 300 = 20,900; close with 20,000 (-900 shortage)
    $this->actingAs($owner)->post("/shifts/{$shift->id}/close", [
        'actual_cash' => '20000',
    ])->assertRedirect("/shifts/{$shift->id}");

    expect(Shift::findOrFail($shift->id)->difference)->toBe('-900.0000');

    $this->actingAs($owner)->post("/shifts/{$shift->id}/transfer-shortage")->assertRedirect("/shifts/{$shift->id}");
    expect($akramEmp->fresh()->current_balance)->toBe('77100.0000');
    expect(EmployeeTransaction::forStore($grocery->id)->where('type', 'shift_shortage')->exists())->toBeTrue();

    // 5. Clothing Store Exchange Rate Update & Terminal Revocation
    $clothing = Store::where('slug', 'anaqat-sanaa')->firstOrFail();
    $clothingOwner = User::where('email', 'owner@anaqa.ye')->firstOrFail();
    $shoe = Product::forStore($clothing->id)->where('sku', 'SHOE-TURK-01')->firstOrFail();

    $this->actingAs($clothingOwner)->post('/settings/exchange-rate', [
        'exchange_rate' => '155',
    ])->assertRedirect('/settings');

    expect($shoe->fresh()->purchase_price)->toBe('12400.0000'); // 80 SAR * 155

    // Issue token via terminal-login then revoke terminal via web route
    $loginJson = $this->postJson('/api/v1/auth/terminal-login', [
        'email' => 'cashier@anaqa.ye',
        'password' => 'YemenPos@Demo2026!',
        'terminal_code' => 'T01',
    ])->assertOk()->json();

    $clothingTerminal = Terminal::forStore($clothing->id)->where('code', 'T01')->firstOrFail();
    $tokenId = $clothingTerminal->sanctum_token_id;
    expect($tokenId)->not->toBeNull();

    $this->actingAs($clothingOwner)->post("/settings/terminals/{$clothingTerminal->id}/revoke")
        ->assertRedirect('/settings');

    expect($clothingTerminal->fresh()->is_active)->toBeFalse();
    expect(PersonalAccessToken::find($tokenId))->toBeNull();
});

test('UI formatting helpers trim trailing zeros on quantities, money inputs, and render Arabic product type badges', function () {
    $this->seed(DemoStoresSeeder::class);

    expect(\App\Support\MoneyMath::formatQty('20.000'))->toBe('20');
    expect(\App\Support\MoneyMath::formatQty('2.500'))->toBe('2.5');
    expect(\App\Support\MoneyMath::formatQty('0.375'))->toBe('0.375');
    expect(\App\Support\MoneyMath::formatMoney('7000.0000', 0))->toBe('7,000');
    expect(\App\Support\MoneyMath::formatMoney('7000.0000', 2))->toBe('7,000.00');
    expect(\App\Support\MoneyMath::formatMoney('7000.0000', null, true))->toBe('7000');
    expect(\App\Support\MoneyMath::formatInput('0.0000'))->toBe('0');

    // Grocery store: simple ('عادي') and weighted ('موزون') products, clean stock & input values
    $groceryOwner = User::where('email', 'owner@alkhair.ye')->firstOrFail();
    $groceryStore = Store::where('slug', 'baqalat-alkhair')->firstOrFail();
    $rani = Product::forStore($groceryStore->id)->where('barcode', '6281007041126')->firstOrFail();

    $productsHtml = $this->actingAs($groceryOwner)->get('/products')->assertOk()->getContent();
    expect($productsHtml)
        ->toContain('عادي')
        ->toContain('موزون')
        ->toContain('18.5')
        ->toContain('5,000')
        ->not->toContain('120.000')
        ->not->toContain('18.500')
        ->not->toContain('5000.0000');

    $editHtml = $this->actingAs($groceryOwner)->get("/products/{$rani->id}/edit")->assertOk()->getContent();
    expect($editHtml)
        ->toContain('value="250"')
        ->toContain('value="300"')
        ->not->toContain('value="250.0000"')
        ->not->toContain('value="300.0000"');

    // Clothing store: variant ('متغيرات') badge
    $clothingOwner = User::where('email', 'owner@anaqa.ye')->firstOrFail();
    $clothingProductsHtml = $this->actingAs($clothingOwner)->get('/products')->assertOk()->getContent();
    expect($clothingProductsHtml)->toContain('متغيرات');
});
