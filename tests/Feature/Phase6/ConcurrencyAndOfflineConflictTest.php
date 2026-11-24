<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\Terminal;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Services\ReturnService;
use App\Services\ShiftService;
use App\Services\SupplierAccountService;
use App\Support\MoneyMath;
use App\Support\StoreContext;
use Database\Seeders\DemoStoresSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

afterEach(function () {
    StoreContext::clear();
    Auth::forgetGuards();
});

test('two terminals overselling last scarce units offline plus server supplier purchase reconcile with stock_movements sum', function () {
    $this->seed(DemoStoresSeeder::class);

    $store = Store::where('slug', 'baqalat-alkhair')->firstOrFail();
    $owner = User::where('email', 'owner@alkhair.ye')->firstOrFail();
    $cashier = User::where('email', 'cashier@alkhair.ye')->firstOrFail();
    $supplier = Supplier::forStore($store->id)->firstOrFail();

    $t1 = Terminal::forStore($store->id)->where('code', 'T01')->firstOrFail();
    $t2 = Terminal::create([
        'store_id' => $store->id,
        'branch_id' => $t1->branch_id,
        'code' => 'T02',
        'name' => 'كاشير 2',
        'is_active' => true,
    ]);

    $scarce = Product::create([
        'store_id' => $store->id,
        'type' => 'simple',
        'name' => 'سمن بلدي نادر 1 كجم',
        'barcode' => '6289999000111',
        'purchase_price' => '1000.0000',
        'sale_price' => '1500.0000',
        'quantity' => '0.000',
        'is_active' => true,
    ]);

    // Seed initial +2.000 via stock ledger so movement ledger starts cleanly at 0
    app(InventoryService::class)->applyMovement(
        $store,
        $t1->branch,
        $scarce,
        null,
        $owner,
        'adjustment_in',
        '2.000',
        '1000.0000',
        null,
        null,
        'رصيد افتتاحي'
    );
    expect($scarce->fresh()->quantity)->toBe('2.000');

    $s1Uuid = (string) Str::uuid();
    $s2Uuid = (string) Str::uuid();

    // Terminal 1 syncs offline sale of 2 units -> stock becomes 0.000
    Sanctum::actingAs($cashier);
    $this->postJson('/api/v1/sync/push', [
        'shifts' => [['client_uuid' => $s1Uuid, 'terminal_code' => 'T01', 'opening_cash' => '0']],
        'orders' => [[
            'client_uuid' => (string) Str::uuid(),
            'shift_client_uuid' => $s1Uuid,
            'invoice_number' => 'INV-T01-2026-000501',
            'items' => [['product_id' => $scarce->id, 'quantity' => '2.000']],
            'payments' => [['client_uuid' => (string) Str::uuid(), 'method' => 'cash', 'amount' => '3000.0000']],
        ]],
    ])->assertOk();
    expect($scarce->fresh()->quantity)->toBe('0.000');

    // Terminal 2 syncs offline sale of 2 units -> stock drops to -2.000 with negative alert
    Sanctum::actingAs($owner);
    $this->postJson('/api/v1/sync/push', [
        'shifts' => [['client_uuid' => $s2Uuid, 'terminal_code' => 'T02', 'opening_cash' => '0']],
        'orders' => [[
            'client_uuid' => (string) Str::uuid(),
            'shift_client_uuid' => $s2Uuid,
            'invoice_number' => 'INV-T02-2026-000502',
            'items' => [['product_id' => $scarce->id, 'quantity' => '2.000']],
            'payments' => [['client_uuid' => (string) Str::uuid(), 'method' => 'cash', 'amount' => '3000.0000']],
        ]],
    ])->assertOk();
    expect($scarce->fresh()->quantity)->toBe('-2.000');

    // Server records Supplier Purchase of +5.000 units @ 1200 -> stock recovers to +3.000
    app(SupplierAccountService::class)->recordPurchase([
        'supplier_id' => $supplier->id,
        'branch_id' => $t1->branch_id,
        'paid_amount' => '6000.0000',
        'credit_amount' => '0.0000',
        'payment_source' => 'owner_pocket',
        'items' => [['product_id' => $scarce->id, 'quantity' => '5.000', 'unit_cost' => '1200.0000']],
    ], $owner);

    expect($scarce->fresh()->quantity)->toBe('3.000');

    // Verify stock_movements ledger sum equals exact product quantity (3.000) and negative alert was recorded
    $movements = StockMovement::forStore($store->id)->where('product_id', $scarce->id)->get();
    $ledgerSum = MoneyMath::sum($movements->pluck('quantity_delta')->all(), 3);
    expect($ledgerSum)->toBe('3.000');
    expect($movements->where('is_negative_alert', true)->count())->toBe(1);
});

test('online credit limit is enforced while offline sync accepts credit sales, and revoked terminal tokens are blocked immediately', function () {
    $this->seed(DemoStoresSeeder::class);

    $store = Store::where('slug', 'baqalat-alkhair')->firstOrFail();
    $owner = User::where('email', 'owner@alkhair.ye')->firstOrFail();
    $cashier = User::where('email', 'cashier@alkhair.ye')->firstOrFail();
    $terminal = Terminal::forStore($store->id)->where('code', 'T01')->firstOrFail();
    $rani = Product::forStore($store->id)->where('barcode', '6281007041126')->firstOrFail(); // 300 YER
    $customer = Customer::forStore($store->id)->firstOrFail(); // balance 15000, limit 50000

    $shift = app(ShiftService::class)->openShift([
        'terminal_id' => $terminal->id,
        'opening_cash' => '0',
    ], $cashier)['shift'];

    // Online credit sale exceeding 50,000 limit (15,000 + 36,000 = 51,000 > 50,000) MUST fail for cashier
    expect(fn () => app(OrderService::class)->createSale([
        'shift_id' => $shift->id,
        'customer_id' => $customer->id,
        'is_offline_sync' => false,
        'items' => [['product_id' => $rani->id, 'quantity' => '120.000']],
        'payments' => [],
        'credit_amount' => '36000.0000',
    ], $cashier))->toThrow(ValidationException::class);

    // Offline sync DOES accept credit sale so offline transactions never get stuck
    $offlineSale = app(OrderService::class)->createSale([
        'client_uuid' => (string) Str::uuid(),
        'shift_id' => $shift->id,
        'customer_id' => $customer->id,
        'is_offline_sync' => true,
        'items' => [['product_id' => $rani->id, 'quantity' => '120.000']],
        'payments' => [],
        'credit_amount' => '36000.0000',
    ], $cashier)['order'];

    expect($customer->fresh()->current_balance)->toBe('51000.0000');

    // Over-return guard across partial returns
    app(ReturnService::class)->createReturn($offlineSale, [
        'shift_id' => $shift->id,
        'refund_method' => 'credit',
        'items' => [['product_id' => $rani->id, 'quantity' => '50.000', 'return_condition' => 'restock']],
    ], $cashier);
    expect($customer->fresh()->current_balance)->toBe('36000.0000');

    expect(fn () => app(ReturnService::class)->createReturn($offlineSale, [
        'shift_id' => $shift->id,
        'refund_method' => 'credit',
        'items' => [['product_id' => $rani->id, 'quantity' => '71.000', 'return_condition' => 'restock']],
    ], $cashier))->toThrow(ValidationException::class);

    // Terminal token revocation blocks Sync API immediately with 401
    $login = $this->postJson('/api/v1/auth/terminal-login', [
        'login' => 'cashier@alkhair.ye',
        'password' => 'YemenPos@Demo2026!',
        'terminal_code' => 'T01',
    ])->assertOk()->json();

    $token = $login['token'];
    $this->withToken($token)->getJson('/api/v1/sync/bootstrap')->assertOk();

    // Owner revokes terminal via web endpoint
    $this->actingAs($owner)->post("/settings/terminals/{$terminal->id}/revoke")->assertRedirect('/settings');
    Auth::forgetGuards();

    // Old terminal bearer token is rejected immediately
    $this->withToken($token)->getJson('/api/v1/sync/bootstrap')->assertUnauthorized();
    $this->withToken($token)->postJson('/api/v1/sync/push', [])->assertUnauthorized();
});
