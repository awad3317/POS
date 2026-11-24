<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\EmployeeTransaction;
use App\Models\Expense;
use App\Models\GlobalBarcodeCatalog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\Shift;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\Terminal;
use App\Models\User;
use App\Support\StoreContext;
use Database\Seeders\DemoStoresSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

afterEach(function () {
    StoreContext::clear();
    Auth::logout();
});

test('all 24 domain tables, Spatie RBAC & audit tables, and Sanctum tables exist', function () {
    $expectedTables = [
        // 24 Domain Tables
        'stores',
        'branches',
        'terminals',
        'users',
        'categories',
        'brands',
        'products',
        'product_variants',
        'product_units',
        'product_batches',
        'customers',
        'shifts',
        'orders',
        'order_items',
        'payments',
        'suppliers',
        'purchases',
        'purchase_items',
        'supplier_payments',
        'employees',
        'employee_transactions',
        'stock_movements',
        'expenses',
        'global_barcode_catalog',
        // Spatie & Core Tables
        'roles',
        'permissions',
        'model_has_roles',
        'model_has_permissions',
        'role_has_permissions',
        'activity_log',
        'personal_access_tokens',
        'cache',
        'jobs',
        'sessions',
    ];

    foreach ($expectedTables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Expected table [{$table}] does not exist.");
    }
});

test('migrations strictly obey financial constitution: zero float/double and zero cascadeOnDelete on domain tables', function () {
    $migrationFiles = File::allFiles(database_path('migrations'));
    expect(count($migrationFiles))->toBeGreaterThanOrEqual(8);

    foreach ($migrationFiles as $file) {
        $contents = File::get($file->getPathname());
        $filename = $file->getFilename();

        expect($contents)
            ->not->toContain('->float(', "Forbidden float column in {$filename}")
            ->not->toContain('->double(', "Forbidden double column in {$filename}");

        // Domain migrations (excluding Spatie's published pivot table migration stub)
        if (! str_contains($filename, 'create_permission_tables')) {
            expect($contents)
                ->not->toContain('cascadeOnDelete', "Forbidden cascadeOnDelete in {$filename}")
                ->not->toContain("onDelete('cascade')", "Forbidden onDelete('cascade') in {$filename}")
                ->not->toContain('onDelete("cascade")', "Forbidden onDelete(\"cascade\") in {$filename}");
        }
    }
});

test('restrictOnDelete blocks hard deletion of linked financial entities while SoftDeletes preserves history', function () {
    $store = Store::create(['name' => 'متجر الاختبار', 'slug' => 'test-store', 'business_type' => 'grocery']);
    $branch = Branch::create(['store_id' => $store->id, 'name' => 'الفرع الأول', 'is_default' => true]);
    $terminal = Terminal::create(['store_id' => $store->id, 'branch_id' => $branch->id, 'code' => 'T01', 'name' => 'كاشير 1']);

    $user = User::create([
        'store_id' => $store->id,
        'branch_id' => $branch->id,
        'name' => 'كاشير الاختبار',
        'email' => 'cashier@test.ye',
        'password' => Hash::make('Secret@1234'),
    ]);

    $customer = Customer::create(['store_id' => $store->id, 'client_uuid' => (string) Str::uuid(), 'name' => 'عميل تجريبي']);
    $supplier = Supplier::create(['store_id' => $store->id, 'name' => 'مورد تجريبي']);
    $employee = Employee::create(['store_id' => $store->id, 'branch_id' => $branch->id, 'name' => 'عامل تجريبي']);
    $product = Product::create(['store_id' => $store->id, 'name' => 'منتج تجريبي', 'purchase_price' => '1000.0000', 'sale_price' => '1200.0000', 'quantity' => '10.000']);

    $shift = Shift::create([
        'store_id' => $store->id,
        'branch_id' => $branch->id,
        'terminal_id' => $terminal->id,
        'user_id' => $user->id,
        'client_uuid' => (string) Str::uuid(),
        'opened_at' => now(),
        'opening_cash' => '50000.0000',
    ]);

    $order = Order::create([
        'store_id' => $store->id,
        'branch_id' => $branch->id,
        'terminal_id' => $terminal->id,
        'shift_id' => $shift->id,
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'client_uuid' => (string) Str::uuid(),
        'invoice_number' => 'INV-T01-2026-000001',
        'subtotal' => '1200.0000',
        'total_amount' => '1200.0000',
        'paid_amount' => '1200.0000',
        'sold_at' => now(),
    ]);

    OrderItem::create([
        'store_id' => $store->id,
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => '1.000',
        'unit_cost' => '1000.0000',
        'unit_price' => '1200.0000',
        'net_total' => '1200.0000',
    ]);

    SupplierPayment::create([
        'store_id' => $store->id,
        'branch_id' => $branch->id,
        'supplier_id' => $supplier->id,
        'shift_id' => $shift->id,
        'user_id' => $user->id,
        'client_uuid' => (string) Str::uuid(),
        'method' => 'cash',
        'payment_source' => 'cash_drawer',
        'amount' => '5000.0000',
        'balance_before' => '10000.0000',
        'balance_after' => '5000.0000',
        'paid_at' => now(),
    ]);

    EmployeeTransaction::create([
        'store_id' => $store->id,
        'branch_id' => $branch->id,
        'employee_id' => $employee->id,
        'shift_id' => $shift->id,
        'user_id' => $user->id,
        'client_uuid' => (string) Str::uuid(),
        'type' => 'advance',
        'payment_source' => 'cash_drawer',
        'amount' => '2000.0000',
        'signed_amount' => '-2000.0000',
        'balance_before' => '0.0000',
        'balance_after' => '-2000.0000',
        'transaction_date' => now(),
    ]);

    StockMovement::create([
        'store_id' => $store->id,
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'user_id' => $user->id,
        'type' => 'sale',
        'quantity_delta' => '-1.000',
        'balance_before' => '10.000',
        'balance_after' => '9.000',
        'unit_cost' => '1000.0000',
    ]);

    // Hard deletion (forceDelete) MUST be blocked by database foreign key restrictOnDelete
    expect(fn () => $product->forceDelete())->toThrow(QueryException::class);
    expect(fn () => $customer->forceDelete())->toThrow(QueryException::class);
    expect(fn () => $supplier->forceDelete())->toThrow(QueryException::class);
    expect(fn () => $employee->forceDelete())->toThrow(QueryException::class);
    expect(fn () => $user->forceDelete())->toThrow(QueryException::class);

    // SoftDeletes MUST succeed on fresh instances while preserving all historical financial rows
    expect($product->fresh()->delete())->toBeTrue();
    expect($customer->fresh()->delete())->toBeTrue();
    expect($supplier->fresh()->delete())->toBeTrue();
    expect($employee->fresh()->delete())->toBeTrue();
    expect($user->fresh()->delete())->toBeTrue();

    expect($product->fresh()->trashed())->toBeTrue();
    expect(Order::withoutStoreScope()->count())->toBe(1);
    expect(OrderItem::withoutStoreScope()->count())->toBe(1);
    expect(SupplierPayment::withoutStoreScope()->count())->toBe(1);
    expect(EmployeeTransaction::withoutStoreScope()->count())->toBe(1);
    expect(StockMovement::withoutStoreScope()->count())->toBe(1);
});

test('high-precision Yemeni Rial DECIMAL(19,4) and weighted quantity DECIMAL(12,3) store exact values without float rounding', function () {
    $store = Store::create(['name' => 'متجر الدقة', 'slug' => 'precision-store']);
    $branch = Branch::create(['store_id' => $store->id, 'name' => 'الفرع 1']);
    $terminal = Terminal::create(['store_id' => $store->id, 'branch_id' => $branch->id, 'code' => 'T01', 'name' => 'كاشير']);
    $user = User::create(['store_id' => $store->id, 'branch_id' => $branch->id, 'name' => 'مستخدم', 'email' => 'p@test.ye', 'password' => Hash::make('Secret@1234')]);
    $product = Product::create(['store_id' => $store->id, 'name' => 'عسل سدر دوعني', 'purchase_price' => '50000.0000', 'sale_price' => '80971.6599']);
    $shift = Shift::create(['store_id' => $store->id, 'branch_id' => $branch->id, 'terminal_id' => $terminal->id, 'user_id' => $user->id, 'client_uuid' => (string) Str::uuid(), 'opened_at' => now()]);

    $order = Order::create([
        'store_id' => $store->id,
        'branch_id' => $branch->id,
        'terminal_id' => $terminal->id,
        'shift_id' => $shift->id,
        'user_id' => $user->id,
        'client_uuid' => (string) Str::uuid(),
        'invoice_number' => 'INV-BIG-0001',
        'subtotal' => '999999999999.7500',
        'total_amount' => '999999999999.7500',
        'paid_amount' => '999999999999.7500',
        'sold_at' => now(),
    ]);

    $item = OrderItem::create([
        'store_id' => $store->id,
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => '12.375',
        'unit_cost' => '50000.0000',
        'unit_price' => '80971.6599',
        'net_total' => '999999999999.7500',
    ]);

    expect($order->fresh()->total_amount)->toBe('999999999999.7500');
    expect($item->fresh()->quantity)->toBe('12.375');
});

test('client_uuid unique constraints enforce offline sync idempotency across all 7 syncable tables', function () {
    $store = Store::create(['name' => 'متجر المزامنة', 'slug' => 'sync-store']);
    $branch = Branch::create(['store_id' => $store->id, 'name' => 'فرع']);
    $terminal = Terminal::create(['store_id' => $store->id, 'branch_id' => $branch->id, 'code' => 'T01', 'name' => 'كاشير']);
    $user = User::create(['store_id' => $store->id, 'branch_id' => $branch->id, 'name' => 'كاشير', 'email' => 's@test.ye', 'password' => Hash::make('Secret@1234')]);
    $supplier = Supplier::create(['store_id' => $store->id, 'name' => 'مورد']);
    $employee = Employee::create(['store_id' => $store->id, 'name' => 'عامل']);

    $uuid = (string) Str::uuid();

    // 1. customers
    Customer::create(['store_id' => $store->id, 'client_uuid' => $uuid, 'name' => 'عميل 1']);
    expect(fn () => Customer::create(['store_id' => $store->id, 'client_uuid' => $uuid, 'name' => 'عميل مكرر']))->toThrow(QueryException::class);

    // 2. shifts
    $shift = Shift::create(['store_id' => $store->id, 'branch_id' => $branch->id, 'terminal_id' => $terminal->id, 'user_id' => $user->id, 'client_uuid' => $uuid, 'opened_at' => now()]);
    expect(fn () => Shift::create(['store_id' => $store->id, 'branch_id' => $branch->id, 'terminal_id' => $terminal->id, 'user_id' => $user->id, 'client_uuid' => $uuid, 'opened_at' => now()]))->toThrow(QueryException::class);

    // 3. orders
    $order = Order::create(['store_id' => $store->id, 'branch_id' => $branch->id, 'terminal_id' => $terminal->id, 'shift_id' => $shift->id, 'user_id' => $user->id, 'client_uuid' => $uuid, 'invoice_number' => 'INV-1', 'subtotal' => '100.0000', 'total_amount' => '100.0000', 'sold_at' => now()]);
    expect(fn () => Order::create(['store_id' => $store->id, 'branch_id' => $branch->id, 'terminal_id' => $terminal->id, 'shift_id' => $shift->id, 'user_id' => $user->id, 'client_uuid' => $uuid, 'invoice_number' => 'INV-2', 'subtotal' => '100.0000', 'total_amount' => '100.0000', 'sold_at' => now()]))->toThrow(QueryException::class);

    // 4. payments
    Payment::create(['store_id' => $store->id, 'branch_id' => $branch->id, 'shift_id' => $shift->id, 'order_id' => $order->id, 'user_id' => $user->id, 'client_uuid' => $uuid, 'payment_type' => 'order_payment', 'method' => 'cash', 'amount' => '100.0000', 'paid_at' => now()]);
    expect(fn () => Payment::create(['store_id' => $store->id, 'branch_id' => $branch->id, 'shift_id' => $shift->id, 'order_id' => $order->id, 'user_id' => $user->id, 'client_uuid' => $uuid, 'payment_type' => 'order_payment', 'method' => 'cash', 'amount' => '100.0000', 'paid_at' => now()]))->toThrow(QueryException::class);

    // 5. supplier_payments
    SupplierPayment::create(['store_id' => $store->id, 'branch_id' => $branch->id, 'supplier_id' => $supplier->id, 'user_id' => $user->id, 'client_uuid' => $uuid, 'method' => 'cash', 'payment_source' => 'owner_pocket', 'amount' => '500.0000', 'balance_before' => '500.0000', 'balance_after' => '0.0000', 'paid_at' => now()]);
    expect(fn () => SupplierPayment::create(['store_id' => $store->id, 'branch_id' => $branch->id, 'supplier_id' => $supplier->id, 'user_id' => $user->id, 'client_uuid' => $uuid, 'method' => 'cash', 'payment_source' => 'owner_pocket', 'amount' => '500.0000', 'balance_before' => '500.0000', 'balance_after' => '0.0000', 'paid_at' => now()]))->toThrow(QueryException::class);

    // 6. employee_transactions
    EmployeeTransaction::create(['store_id' => $store->id, 'employee_id' => $employee->id, 'user_id' => $user->id, 'client_uuid' => $uuid, 'type' => 'advance', 'payment_source' => 'cash_drawer', 'amount' => '1000.0000', 'signed_amount' => '-1000.0000', 'balance_before' => '0.0000', 'balance_after' => '-1000.0000', 'transaction_date' => now()]);
    expect(fn () => EmployeeTransaction::create(['store_id' => $store->id, 'employee_id' => $employee->id, 'user_id' => $user->id, 'client_uuid' => $uuid, 'type' => 'advance', 'payment_source' => 'cash_drawer', 'amount' => '1000.0000', 'signed_amount' => '-1000.0000', 'balance_before' => '0.0000', 'balance_after' => '-1000.0000', 'transaction_date' => now()]))->toThrow(QueryException::class);

    // 7. expenses
    Expense::create(['store_id' => $store->id, 'branch_id' => $branch->id, 'user_id' => $user->id, 'client_uuid' => $uuid, 'category' => 'كهرباء', 'amount' => '3000.0000', 'payment_source' => 'cash_drawer', 'spent_at' => now()]);
    expect(fn () => Expense::create(['store_id' => $store->id, 'branch_id' => $branch->id, 'user_id' => $user->id, 'client_uuid' => $uuid, 'category' => 'كهرباء', 'amount' => '3000.0000', 'payment_source' => 'cash_drawer', 'spent_at' => now()]))->toThrow(QueryException::class);
});

test('BelongsToStore trait enforces strict bidirectional multi-tenant isolation across stores', function () {
    $this->seed(DemoStoresSeeder::class);

    $store1 = Store::where('slug', 'baqalat-alkhair')->firstOrFail();
    $store2 = Store::where('slug', 'anaqat-sanaa')->firstOrFail();

    $store1Owner = User::where('email', 'owner@alkhair.ye')->firstOrFail();
    $store2Owner = User::where('email', 'owner@anaqa.ye')->firstOrFail();

    // Authenticate as Store 1 (Grocery)
    $this->actingAs($store1Owner);

    expect(Product::count())->toBe(3);
    expect(Product::pluck('store_id')->unique()->all())->toBe([$store1->id]);
    expect(Customer::count())->toBe(1);
    expect(Customer::where('store_id', $store2->id)->count())->toBe(0);
    expect(Supplier::pluck('store_id')->unique()->all())->toBe([$store1->id]);
    expect(Employee::pluck('store_id')->unique()->all())->toBe([$store1->id]);

    // Attempting to spoof store_id during creation while authenticated as Store 1 must force store_id = Store 1
    $spoofedCustomer = Customer::create(['store_id' => $store2->id, 'name' => 'عميل محمي']);
    expect($spoofedCustomer->store_id)->toBe($store1->id);

    // Switch authentication to Store 2 (Clothing)
    $this->actingAs($store2Owner);

    expect(Product::count())->toBe(1);
    expect(Product::pluck('store_id')->unique()->all())->toBe([$store2->id]);
    expect(Customer::count())->toBe(1);
    expect(Customer::pluck('store_id')->unique()->all())->toBe([$store2->id]);
    expect(Supplier::pluck('store_id')->unique()->all())->toBe([$store2->id]);
    expect(Employee::pluck('store_id')->unique()->all())->toBe([$store2->id]);
});

test('DemoStoresSeeder populates complete Yemeni grocery multi-units/PLU, clothing variants, suppliers, non-login employees, and global catalog', function () {
    $this->seed(DemoStoresSeeder::class);

    $grocery = Store::where('slug', 'baqalat-alkhair')->firstOrFail();
    $clothing = Store::where('slug', 'anaqat-sanaa')->firstOrFail();

    expect($grocery->business_type)->toBe('grocery');
    expect($clothing->business_type)->toBe('clothing');
    expect(GlobalBarcodeCatalog::count())->toBeGreaterThanOrEqual(5);

    // Grocery multi-units & scale PLU
    expect(ProductUnit::forStore($grocery->id)->count())->toBe(3);
    expect(Product::forStore($grocery->id)->whereNotNull('scale_plu_code')->exists())->toBeTrue();
    expect(Product::forStore($grocery->id)->where('is_quick_pick', true)->exists())->toBeTrue();

    // Clothing variants & secondary currency
    expect($clothing->secondary_currency_code)->toBe('SAR');
    expect(ProductVariant::forStore($clothing->id)->count())->toBe(3);

    // Suppliers & Employees (including non-login workers with user_id === null)
    expect(Supplier::forStore($grocery->id)->count())->toBeGreaterThanOrEqual(1);
    expect(Supplier::forStore($clothing->id)->count())->toBeGreaterThanOrEqual(1);
    expect(Employee::forStore($grocery->id)->whereNull('user_id')->exists())->toBeTrue();
    expect(Employee::forStore($clothing->id)->whereNull('user_id')->exists())->toBeTrue();
});
