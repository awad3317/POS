<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\Terminal;
use App\Models\User;
use App\Support\StoreContext;
use Database\Seeders\DemoStoresSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

afterEach(function () {
    StoreContext::clear();
    Auth::forgetGuards();
});

test('terminal-login issues Sanctum token for valid store terminal and blocks cross-store terminal codes', function () {
    $this->seed(DemoStoresSeeder::class);

    $clothingStore = Store::where('slug', 'anaqat-sanaa')->firstOrFail();
    Terminal::create([
        'store_id' => $clothingStore->id,
        'branch_id' => $clothingStore->branches()->withoutStoreScope()->first()->id,
        'code' => 'CLOTH-T99',
        'name' => 'كاشير الملابس الخاص',
    ]);

    // Valid login for Store 1 cashier + Store 1 T01
    $ok = $this->postJson('/api/v1/auth/terminal-login', [
        'email' => 'cashier@alkhair.ye',
        'password' => 'YemenPos@Demo2026!',
        'terminal_code' => 'T01',
    ])->assertOk()->json();

    expect($ok)->toHaveKey('token');
    expect($ok['terminal']['code'])->toBe('T01');
    expect($ok['user']['roles'])->toContain('cashier');

    // Cross-store terminal code attempt (Store 1 user trying Store 2's CLOTH-T99) -> 403 Forbidden
    $this->postJson('/api/v1/auth/terminal-login', [
        'email' => 'cashier@alkhair.ye',
        'password' => 'YemenPos@Demo2026!',
        'terminal_code' => 'CLOTH-T99',
    ])->assertForbidden();

    // Invalid password -> 401 Unauthorized
    $this->postJson('/api/v1/auth/terminal-login', [
        'email' => 'cashier@alkhair.ye',
        'password' => 'WrongPassword',
        'terminal_code' => 'T01',
    ])->assertUnauthorized();
});

test('sync bootstrap strictly isolates tenant data and strips cost/salary/supplier-balance secrets for cashier role', function () {
    $this->seed(DemoStoresSeeder::class);

    $store1 = Store::where('slug', 'baqalat-alkhair')->firstOrFail();
    $store2 = Store::where('slug', 'anaqat-sanaa')->firstOrFail();
    $cashier1 = User::where('email', 'cashier@alkhair.ye')->firstOrFail();
    $owner1 = User::where('email', 'owner@alkhair.ye')->firstOrFail();
    $store2ProductIds = Product::forStore($store2->id)->pluck('id')->all();

    // 1. Cashier bootstrap
    Sanctum::actingAs($cashier1);
    $cashierPayload = $this->getJson('/api/v1/sync/bootstrap')->assertOk()->json();

    expect($cashierPayload['store']['id'])->toBe($store1->id);
    expect(count($cashierPayload['products']))->toBe(3);

    foreach ($cashierPayload['products'] as $p) {
        expect($store2ProductIds)->not->toContain($p['id']);
        expect($p)
            ->not->toHaveKey('purchase_price')
            ->not->toHaveKey('foreign_cost_price')
            ->not->toHaveKey('profit_margin_percent')
            ->not->toHaveKey('cost_currency');
    }

    foreach ($cashierPayload['units'] as $u) {
        expect($u)->not->toHaveKey('purchase_price');
    }

    // quick_payees MUST expose strictly {id, type, name}
    expect(count($cashierPayload['quick_payees']))->toBeGreaterThanOrEqual(2);
    foreach ($cashierPayload['quick_payees'] as $payee) {
        expect(array_keys($payee))->toBe(['id', 'type', 'name']);
    }

    // 2. Owner bootstrap DOES include purchase_price
    Sanctum::actingAs($owner1);
    $ownerPayload = $this->getJson('/api/v1/sync/bootstrap')->assertOk()->json();
    expect($ownerPayload['products'][0])->toHaveKey('purchase_price');
});

test('cross-tenant push attempts referencing another store product, customer, or supplier are rejected cleanly', function () {
    $this->seed(DemoStoresSeeder::class);

    $store1 = Store::where('slug', 'baqalat-alkhair')->firstOrFail();
    $store2 = Store::where('slug', 'anaqat-sanaa')->firstOrFail();
    $cashier1 = User::where('email', 'cashier@alkhair.ye')->firstOrFail();

    $store2Product = Product::forStore($store2->id)->firstOrFail();
    $store2Customer = Customer::forStore($store2->id)->firstOrFail();
    $store2Supplier = Supplier::forStore($store2->id)->firstOrFail();
    $initialStore2Qty = $store2Product->quantity;
    $initialStore2SuppBal = $store2Supplier->current_balance;

    Sanctum::actingAs($cashier1);

    $shiftUuid = (string) Str::uuid();
    $badOrderUuid = (string) Str::uuid();
    $badSuppPayUuid = (string) Str::uuid();

    $res = $this->postJson('/api/v1/sync/push', [
        'shifts' => [
            ['client_uuid' => $shiftUuid, 'terminal_code' => 'T01', 'opening_cash' => '10000'],
        ],
        'orders' => [
            [
                'client_uuid' => $badOrderUuid,
                'shift_client_uuid' => $shiftUuid,
                'customer_id' => $store2Customer->id,
                'items' => [['product_id' => $store2Product->id, 'quantity' => '1']],
                'payments' => [['method' => 'cash', 'amount' => '16000']],
            ],
        ],
        'supplier_payments' => [
            [
                'client_uuid' => $badSuppPayUuid,
                'shift_client_uuid' => $shiftUuid,
                'supplier_id' => $store2Supplier->id,
                'method' => 'cash',
                'payment_source' => 'cash_drawer',
                'amount' => '5000',
            ],
        ],
    ])->assertOk()->json('results');

    expect($res['orders'][$badOrderUuid]['status'])->toBe('error');
    expect($res['supplier_payments'][$badSuppPayUuid]['status'])->toBe('error');

    expect(Order::forStore($store1->id)->count())->toBe(0);
    expect($store2Product->fresh()->quantity)->toBe($initialStore2Qty);
    expect($store2Supplier->fresh()->current_balance)->toBe($initialStore2SuppBal);
});
