<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shift;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Terminal;
use App\Models\User;
use App\Services\ExchangeRateService;
use App\Services\OrderService;
use App\Services\ReturnService;
use App\Services\ShiftService;
use App\Support\MoneyMath;
use App\Support\StoreContext;
use Database\Seeders\DemoStoresSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

afterEach(function () {
    StoreContext::clear();
    Auth::logout();
});

test('12.5M YER wholesale invoice with item discount, order discount, tax, mixed payment, and historical COGS has 0.0000 discrepancy', function () {
    $this->seed(DemoStoresSeeder::class);

    $store = Store::where('slug', 'baqalat-alkhair')->firstOrFail();
    $cashier = User::where('email', 'cashier@alkhair.ye')->firstOrFail();
    $terminal = Terminal::forStore($store->id)->where('code', 'T01')->firstOrFail();
    $customer = Customer::forStore($store->id)->firstOrFail();
    $customer->update(['credit_limit' => '10000000.0000', 'current_balance' => '0.0000']);

    $product = Product::create([
        'store_id' => $store->id,
        'type' => 'simple',
        'name' => 'أرز بسمتي جملة (طن)',
        'barcode' => '9900112233445',
        'purchase_price' => '950000.0000',
        'sale_price' => '1250000.0000',
        'quantity' => '50.000',
        'tax_rate' => '5.00',
        'is_active' => true,
    ]);

    $shift = app(ShiftService::class)->openShift([
        'terminal_id' => $terminal->id,
        'opening_cash' => '100000.0000',
    ], $cashier)['shift'];

    // 10 units * 1,250,000 = 12,500,000.0000 subtotal
    // Item discount = 250,000.0000 -> taxable = 12,250,000.0000
    // Tax 5% = 612,500.0000 -> line_net = 12,862,500.0000
    // Order discount = 150,000.0000 -> total_amount = 12,712,500.0000
    $result = app(OrderService::class)->createSale([
        'shift_id' => $shift->id,
        'terminal_id' => $terminal->id,
        'customer_id' => $customer->id,
        'order_discount_amount' => '150000.0000',
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => '10.000',
                'unit_price' => '1250000.0000',
                'discount_amount' => '250000.0000',
                'tax_rate' => '5.00',
            ],
        ],
        'payments' => [
            ['method' => 'cash', 'amount' => '5500000.0000'], // 500,000 change -> 5,000,000 net cash
            ['method' => 'kuraimi', 'amount' => '4712500.0000', 'reference_number' => 'KUR-998811'],
        ],
        'change_amount' => '500000.0000',
        'credit_amount' => '3000000.0000',
    ], $cashier);

    $order = $result['order'];

    expect($order->subtotal)->toBe('12500000.0000');
    expect($order->item_discount_total)->toBe('250000.0000');
    expect($order->order_discount_amount)->toBe('150000.0000');
    expect($order->tax_total)->toBe('612500.0000');
    expect($order->total_amount)->toBe('12712500.0000');
    expect($order->paid_amount)->toBe('9712500.0000');
    expect($order->credit_amount)->toBe('3000000.0000');
    expect($order->change_amount)->toBe('500000.0000');
    expect(MoneyMath::sub(MoneyMath::add($order->paid_amount, $order->credit_amount), $order->total_amount))->toBe('0.0000');

    expect($customer->fresh()->current_balance)->toBe('3000000.0000');
    expect($product->fresh()->quantity)->toBe('40.000');
    expect($shift->fresh()->cash_sales_total)->toBe('5000000.0000');
    expect($shift->fresh()->expected_cash)->toBe('5100000.0000');

    // Change product purchase_price later -> historical unit_cost on order_items MUST remain 950000.0000
    $product->update(['purchase_price' => '1100000.0000']);
    expect($order->items->first()->fresh()->unit_cost)->toBe('950000.0000');
});

test('OrderService rejects negative discounts and unbalanced payment equations', function () {
    $this->seed(DemoStoresSeeder::class);

    $store = Store::where('slug', 'baqalat-alkhair')->firstOrFail();
    $cashier = User::where('email', 'cashier@alkhair.ye')->firstOrFail();
    $terminal = Terminal::forStore($store->id)->firstOrFail();
    $product = Product::forStore($store->id)->where('barcode', '6281007041126')->firstOrFail();
    $shift = app(ShiftService::class)->openShift(['terminal_id' => $terminal->id, 'opening_cash' => '0'], $cashier)['shift'];

    // Negative item discount (-500) must fail
    expect(fn () => app(OrderService::class)->createSale([
        'shift_id' => $shift->id,
        'items' => [['product_id' => $product->id, 'quantity' => '1', 'discount_amount' => '-500']],
        'payments' => [['method' => 'cash', 'amount' => '800']],
    ], $cashier))->toThrow(ValidationException::class);

    // Unbalanced payment (paying 200 for a 300 order with 0 credit) must fail
    expect(fn () => app(OrderService::class)->createSale([
        'shift_id' => $shift->id,
        'items' => [['product_id' => $product->id, 'quantity' => '1']],
        'payments' => [['method' => 'cash', 'amount' => '200']],
        'credit_amount' => '0',
    ], $cashier))->toThrow(ValidationException::class);
});

test('selling below purchase cost blocks cashier unless valid manager override PIN is provided and audited', function () {
    $this->seed(DemoStoresSeeder::class);

    $store = Store::where('slug', 'baqalat-alkhair')->firstOrFail();
    $owner = User::where('email', 'owner@alkhair.ye')->firstOrFail();
    $cashier = User::where('email', 'cashier@alkhair.ye')->firstOrFail();
    $terminal = Terminal::forStore($store->id)->firstOrFail();
    $product = Product::forStore($store->id)->where('barcode', '6281007041126')->firstOrFail(); // cost 250, sale 300
    $shift = app(ShiftService::class)->openShift(['terminal_id' => $terminal->id, 'opening_cash' => '10000'], $cashier)['shift'];

    // Cashier attempts discount of 100 (net 200 < cost 250) without manager PIN -> Blocked!
    expect(fn () => app(OrderService::class)->createSale([
        'shift_id' => $shift->id,
        'items' => [['product_id' => $product->id, 'quantity' => '1', 'discount_amount' => '100']],
        'payments' => [['method' => 'cash', 'amount' => '200']],
    ], $cashier))->toThrow(ValidationException::class);

    // Cashier provides wrong manager PIN -> Blocked!
    expect(fn () => app(OrderService::class)->createSale([
        'shift_id' => $shift->id,
        'manager_override_pin' => '9999',
        'items' => [['product_id' => $product->id, 'quantity' => '1', 'discount_amount' => '100']],
        'payments' => [['method' => 'cash', 'amount' => '200']],
    ], $cashier))->toThrow(ValidationException::class);

    // Cashier provides valid owner PIN ('1122' from DemoStoresSeeder) -> Approved & Audited!
    $approved = app(OrderService::class)->createSale([
        'shift_id' => $shift->id,
        'manager_override_pin' => '1122',
        'items' => [['product_id' => $product->id, 'quantity' => '1', 'discount_amount' => '100']],
        'payments' => [['method' => 'cash', 'amount' => '200']],
    ], $cashier)['order'];

    expect($approved->discount_override_by)->toBe($owner->id);
    expect(Activity::where('event', 'discount_override')->where('subject_id', $approved->id)->exists())->toBeTrue();
});

test('ReturnService handles partial restock returns, damaged returns without inflating stock, and blocks over-returns', function () {
    $this->seed(DemoStoresSeeder::class);

    $store = Store::where('slug', 'baqalat-alkhair')->firstOrFail();
    $cashier = User::where('email', 'cashier@alkhair.ye')->firstOrFail();
    $terminal = Terminal::forStore($store->id)->firstOrFail();
    $product = Product::forStore($store->id)->where('barcode', '6281007041126')->firstOrFail(); // initial 120.000
    $shift = app(ShiftService::class)->openShift(['terminal_id' => $terminal->id, 'opening_cash' => '10000'], $cashier)['shift'];

    // Sell 3 units -> stock drops from 120 to 117
    $sale = app(OrderService::class)->createSale([
        'shift_id' => $shift->id,
        'items' => [['product_id' => $product->id, 'quantity' => '3']],
        'payments' => [['method' => 'cash', 'amount' => '900']],
    ], $cashier)['order'];

    expect($product->fresh()->quantity)->toBe('117.000');

    // 1. Partial return of 1 unit (restock) -> stock rises to 118, status = partially_refunded
    app(ReturnService::class)->createReturn($sale, [
        'shift_id' => $shift->id,
        'refund_method' => 'cash',
        'items' => [['product_id' => $product->id, 'quantity' => '1', 'return_condition' => 'restock']],
    ], $cashier);

    expect($product->fresh()->quantity)->toBe('118.000');
    expect($sale->fresh()->status)->toBe('partially_refunded');

    // 2. Attempting to return 3 more units when only 2 remain must fail!
    expect(fn () => app(ReturnService::class)->createReturn($sale, [
        'shift_id' => $shift->id,
        'refund_method' => 'cash',
        'items' => [['product_id' => $product->id, 'quantity' => '3', 'return_condition' => 'restock']],
    ], $cashier))->toThrow(ValidationException::class);

    // 3. Return remaining 2 units as damaged -> sellable stock stays 118.000, status = refunded
    app(ReturnService::class)->createReturn($sale, [
        'shift_id' => $shift->id,
        'refund_method' => 'cash',
        'items' => [['product_id' => $product->id, 'quantity' => '2', 'return_condition' => 'damaged']],
    ], $cashier);

    expect($product->fresh()->quantity)->toBe('118.000');
    expect($sale->fresh()->status)->toBe('refunded');
    expect($shift->fresh()->cash_returns_total)->toBe('900.0000');
});

test('offline oversell across two terminals accepts both financial sales, flags is_negative_alert, and matches stock ledger sum', function () {
    $this->seed(DemoStoresSeeder::class);

    $store = Store::where('slug', 'baqalat-alkhair')->firstOrFail();
    $owner = User::where('email', 'owner@alkhair.ye')->firstOrFail();
    $cashier = User::where('email', 'cashier@alkhair.ye')->firstOrFail();
    $t1 = Terminal::forStore($store->id)->where('code', 'T01')->firstOrFail();
    $t2 = Terminal::create(['store_id' => $store->id, 'branch_id' => $t1->branch_id, 'code' => 'T02', 'name' => 'كاشير 2']);

    $scarceProduct = Product::create([
        'store_id' => $store->id,
        'name' => 'علبة عسل نادرة',
        'purchase_price' => '10000.0000',
        'sale_price' => '15000.0000',
        'quantity' => '0.000',
    ]);

    // Seed initial +1.000 movement so ledger starts at 0 and sums cleanly
    app(\App\Services\InventoryService::class)->applyMovement(
        $store,
        $t1->branch,
        $scarceProduct,
        null,
        $owner,
        'adjustment_in',
        '1.000',
        '10000.0000'
    );
    expect($scarceProduct->fresh()->quantity)->toBe('1.000');

    $s1 = app(ShiftService::class)->openShift(['terminal_id' => $t1->id, 'opening_cash' => '0'], $cashier)['shift'];
    $s2 = app(ShiftService::class)->openShift(['terminal_id' => $t2->id, 'opening_cash' => '0'], $owner)['shift'];

    // Both terminals sell 1 unit offline
    app(OrderService::class)->createSale([
        'client_uuid' => (string) Str::uuid(),
        'shift_id' => $s1->id,
        'terminal_id' => $t1->id,
        'is_offline_sync' => true,
        'items' => [['product_id' => $scarceProduct->id, 'quantity' => '1']],
        'payments' => [['method' => 'cash', 'amount' => '15000']],
    ], $cashier);

    app(OrderService::class)->createSale([
        'client_uuid' => (string) Str::uuid(),
        'shift_id' => $s2->id,
        'terminal_id' => $t2->id,
        'is_offline_sync' => true,
        'items' => [['product_id' => $scarceProduct->id, 'quantity' => '1']],
        'payments' => [['method' => 'cash', 'amount' => '15000']],
    ], $owner);

    expect($scarceProduct->fresh()->quantity)->toBe('-1.000');

    $movements = StockMovement::forStore($store->id)->where('product_id', $scarceProduct->id)->get();
    $ledgerSum = MoneyMath::sum($movements->pluck('quantity_delta')->all(), 3);
    expect($ledgerSum)->toBe($scarceProduct->fresh()->quantity);
    expect($movements->where('is_negative_alert', true)->count())->toBe(1);
});

test('ExchangeRateService reprices SAR clothing products and variants atomically and writes audit log', function () {
    $this->seed(DemoStoresSeeder::class);

    $clothing = Store::where('slug', 'anaqat-sanaa')->firstOrFail();
    $owner = User::where('email', 'owner@anaqa.ye')->firstOrFail();
    $shoe = Product::forStore($clothing->id)->where('sku', 'SHOE-TURK-01')->firstOrFail(); // 80 SAR, margin 42.86%

    // Update SAR exchange rate from 140 to 150 YER
    // New cost = 80 * 150 = 12,000.0000; Margin = 12,000 * 42.86% = 5,143.2000 -> Sale = 17,143.2000
    $count = app(ExchangeRateService::class)->updateStoreExchangeRate($clothing, '150.0000', $owner);

    expect($count)->toBe(1);
    expect($clothing->fresh()->exchange_rate)->toBe('150.0000');
    expect($shoe->fresh()->purchase_price)->toBe('12000.0000');
    expect($shoe->fresh()->sale_price)->toBe('17143.2000');

    $variant = ProductVariant::forStore($clothing->id)->where('product_id', $shoe->id)->firstOrFail();
    expect($variant->purchase_price)->toBe('12000.0000');
    expect($variant->sale_price)->toBe('17143.2000');

    expect(Activity::where('event', 'exchange_rate_updated')->exists())->toBeTrue();
});
