<?php

use App\Models\Customer;
use App\Models\Employee;
use App\Models\EmployeeTransaction;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductUnit;
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
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

afterEach(function () {
    StoreContext::clear();
    Auth::forgetGuards();
});

test('offline batch push resolves cross-entity client_uuids and remains 100% idempotent across 5 repeated retries', function () {
    $this->seed(DemoStoresSeeder::class);

    $store = Store::where('slug', 'baqalat-alkhair')->firstOrFail();
    $cashier = User::where('email', 'cashier@alkhair.ye')->firstOrFail();
    $terminal = Terminal::forStore($store->id)->where('code', 'T01')->firstOrFail();
    $rani = Product::forStore($store->id)->where('barcode', '6281007041126')->firstOrFail(); // initial 120.000
    $pack6Unit = ProductUnit::forStore($store->id)->where('product_id', $rani->id)->where('conversion_factor', '6.000')->firstOrFail(); // price 1750
    $supplier = Supplier::forStore($store->id)->firstOrFail(); // initial 250000.0000
    $worker = Employee::forStore($store->id)->whereNull('user_id')->firstOrFail(); // initial 0.0000

    Sanctum::actingAs($cashier);

    $customerUuid = (string) Str::uuid();
    $shiftUuid = (string) Str::uuid();
    $order1Uuid = (string) Str::uuid();
    $order2Uuid = (string) Str::uuid();
    $pay1Uuid = (string) Str::uuid();
    $pay2Uuid = (string) Str::uuid();
    $settlementUuid = (string) Str::uuid();
    $expenseUuid = (string) Str::uuid();
    $advanceUuid = (string) Str::uuid();
    $suppPayUuid = (string) Str::uuid();
    $auditUuid = (string) Str::uuid();

    $payload = [
        'customers' => [
            [
                'client_uuid' => $customerUuid,
                'name' => 'عميل أوفلاين جديد',
                'phone' => '779887766',
                'credit_limit' => '100000.0000',
            ],
        ],
        'shifts' => [
            [
                'client_uuid' => $shiftUuid,
                'terminal_code' => $terminal->code,
                'opening_cash' => '50000.0000',
                'opened_at' => now()->subHours(4)->toIso8601String(),
            ],
        ],
        'orders' => [
            // Order 1: Cash sale of 1 pack-of-6 (deducts 6 base units, price 1750)
            [
                'client_uuid' => $order1Uuid,
                'shift_client_uuid' => $shiftUuid,
                'invoice_number' => 'INV-T01-2026-000101',
                'items' => [
                    [
                        'product_id' => $rani->id,
                        'unit_id' => $pack6Unit->id,
                        'quantity' => '1.000',
                    ],
                ],
                'payments' => [
                    ['client_uuid' => $pay1Uuid, 'method' => 'cash', 'amount' => '1750.0000'],
                ],
                'credit_amount' => '0.0000',
                'sold_at' => now()->subHours(3)->toIso8601String(),
            ],
            // Order 2: Credit + Cash sale to the new offline customer (2 single units = 600: 200 cash + 400 credit)
            [
                'client_uuid' => $order2Uuid,
                'shift_client_uuid' => $shiftUuid,
                'customer_client_uuid' => $customerUuid,
                'invoice_number' => 'INV-T01-2026-000102',
                'items' => [
                    [
                        'product_id' => $rani->id,
                        'quantity' => '2.000',
                    ],
                ],
                'payments' => [
                    ['client_uuid' => $pay2Uuid, 'method' => 'cash', 'amount' => '200.0000'],
                ],
                'credit_amount' => '400.0000',
                'sold_at' => now()->subHours(2)->toIso8601String(),
            ],
        ],
        'payments' => [
            // Customer pays back 150 cash on their 400 debt
            [
                'client_uuid' => $settlementUuid,
                'shift_client_uuid' => $shiftUuid,
                'customer_client_uuid' => $customerUuid,
                'method' => 'cash',
                'amount' => '150.0000',
            ],
        ],
        'expenses' => [
            [
                'client_uuid' => $expenseUuid,
                'shift_client_uuid' => $shiftUuid,
                'category' => 'أكياس وقرطاسية',
                'amount' => '1000.0000',
                'payment_source' => 'cash_drawer',
            ],
        ],
        'employee_advances' => [
            [
                'client_uuid' => $advanceUuid,
                'shift_client_uuid' => $shiftUuid,
                'employee_id' => $worker->id,
                'type' => 'advance',
                'amount' => '1500.0000',
                'payment_source' => 'cash_drawer',
            ],
        ],
        'supplier_payments' => [
            [
                'client_uuid' => $suppPayUuid,
                'shift_client_uuid' => $shiftUuid,
                'supplier_id' => $supplier->id,
                'method' => 'cash',
                'payment_source' => 'cash_drawer',
                'amount' => '5000.0000',
            ],
        ],
        'audit_events' => [
            [
                'client_uuid' => $auditUuid,
                'event' => 'cart_item_removed',
                'description' => 'Cashier removed scanned item before checkout',
                'properties' => ['barcode' => '6281007041126'],
            ],
        ],
    ];

    // First push: everything MUST be 'synced'
    $firstRes = $this->postJson('/api/v1/sync/push', $payload)->assertOk()->json('results');

    expect($firstRes['customers'][$customerUuid]['status'])->toBe('synced');
    expect($firstRes['shifts'][$shiftUuid]['status'])->toBe('synced');
    expect($firstRes['orders'][$order1Uuid]['status'])->toBe('synced');
    expect($firstRes['orders'][$order2Uuid]['status'])->toBe('synced');
    expect($firstRes['payments'][$settlementUuid]['status'])->toBe('synced');
    expect($firstRes['expenses'][$expenseUuid]['status'])->toBe('synced');
    expect($firstRes['employee_advances'][$advanceUuid]['status'])->toBe('synced');
    expect($firstRes['supplier_payments'][$suppPayUuid]['status'])->toBe('synced');
    expect($firstRes['audit_events'][$auditUuid]['status'])->toBe('synced');

    // Verify exact financial state after 1st push:
    // Stock: 120 - 6 (pack) - 2 (singles) = 112.000
    expect($rani->fresh()->quantity)->toBe('112.000');
    // Offline customer balance: +400 credit - 150 settlement = 250.0000
    $offlineCust = Customer::forStore($store->id)->where('client_uuid', $customerUuid)->firstOrFail();
    expect($offlineCust->current_balance)->toBe('250.0000');
    // Supplier: 250000 - 5000 = 245000.0000
    expect($supplier->fresh()->current_balance)->toBe('245000.0000');
    // Worker: 0 - 1500 = -1500.0000
    expect($worker->fresh()->current_balance)->toBe('-1500.0000');
    // Shift expected cash: 50000 + (1750 + 200) + 150 - (1000 + 1500 + 5000) = 44600.0000
    $syncedShift = Shift::forStore($store->id)->where('client_uuid', $shiftUuid)->firstOrFail();
    expect($syncedShift->expected_cash)->toBe('44600.0000');

    // Retry exact same payload 5 more times (simulating flaky 4G timeouts)
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $retryRes = $this->postJson('/api/v1/sync/push', $payload)->assertOk()->json('results');

        expect($retryRes['customers'][$customerUuid]['status'])->toBe('duplicate');
        expect($retryRes['shifts'][$shiftUuid]['status'])->toBe('duplicate');
        expect($retryRes['orders'][$order1Uuid]['status'])->toBe('duplicate');
        expect($retryRes['orders'][$order2Uuid]['status'])->toBe('duplicate');
        expect($retryRes['payments'][$settlementUuid]['status'])->toBe('duplicate');
        expect($retryRes['expenses'][$expenseUuid]['status'])->toBe('duplicate');
        expect($retryRes['employee_advances'][$advanceUuid]['status'])->toBe('duplicate');
        expect($retryRes['supplier_payments'][$suppPayUuid]['status'])->toBe('duplicate');
        expect($retryRes['audit_events'][$auditUuid]['status'])->toBe('duplicate');
    }

    // Zero duplicate rows and zero balance drift!
    expect(Order::forStore($store->id)->count())->toBe(2);
    expect(Payment::forStore($store->id)->count())->toBe(3); // 2 order payments + 1 credit settlement
    expect(Expense::forStore($store->id)->count())->toBe(1);
    expect(EmployeeTransaction::forStore($store->id)->count())->toBe(1);
    expect(SupplierPayment::forStore($store->id)->count())->toBe(1);

    expect($rani->fresh()->quantity)->toBe('112.000');
    expect($offlineCust->fresh()->current_balance)->toBe('250.0000');
    expect($supplier->fresh()->current_balance)->toBe('245000.0000');
    expect($worker->fresh()->current_balance)->toBe('-1500.0000');
    expect($syncedShift->fresh()->expected_cash)->toBe('44600.0000');
});
