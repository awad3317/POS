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
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\Terminal;
use App\Models\User;
use App\Services\InventoryService;
use App\Support\MoneyMath;
use App\Support\StoreContext;
use Database\Seeders\DemoStoresSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

afterEach(function () {
    StoreContext::clear();
    Auth::forgetGuards();
});

test('offline stress test: 50 diverse invoices + offline customers + drawer payouts + shift close reconcile 100% and stay idempotent', function () {
    $this->seed(DemoStoresSeeder::class);

    $store = Store::where('slug', 'baqalat-alkhair')->firstOrFail();
    $owner = User::where('email', 'owner@alkhair.ye')->firstOrFail();
    $cashier = User::where('email', 'cashier@alkhair.ye')->firstOrFail();
    $terminal = Terminal::forStore($store->id)->where('code', 'T01')->firstOrFail();

    $rani = Product::forStore($store->id)->where('barcode', '6281007041126')->firstOrFail(); // initial 120.000 @ 300
    // Add extra stock via InventoryService so ledger sums cleanly
    app(InventoryService::class)->applyMovement($store, $terminal->branch, $rani, null, $owner, 'adjustment_in', '200.000', '250.0000', null, null, 'تعزيز مخزون لاختبار 50 فاتورة');

    $pack6 = ProductUnit::forStore($store->id)->where('product_id', $rani->id)->where('conversion_factor', '6.000')->firstOrFail(); // 1750
    $cheese = Product::forStore($store->id)->where('scale_plu_code', '10025')->firstOrFail(); // 18.500 @ 5000
    $bread = Product::forStore($store->id)->where('sku', 'QP-BREAD-01')->firstOrFail(); // 200.000 @ 50
    $existingCustomer = Customer::forStore($store->id)->firstOrFail(); // balance 15000
    $supplier = Supplier::forStore($store->id)->firstOrFail(); // balance 250000
    $worker = Employee::forStore($store->id)->whereNull('user_id')->firstOrFail(); // balance 0

    Sanctum::actingAs($cashier);

    $custAUuid = (string) Str::uuid();
    $custBUuid = (string) Str::uuid();
    $shiftUuid = (string) Str::uuid();

    $orders = [];
    $expectedRaniSold = '0.000';
    $expectedCheeseSold = '0.000';
    $expectedBreadSold = '0.000';
    $expectedCashSales = '0.0000';
    $expectedCustACredit = '0.0000';
    $expectedCustBCredit = '0.0000';
    $expectedExistingCustCredit = '0.0000';
    $expectedPaymentsCount = 0;

    for ($i = 1; $i <= 50; $i++) {
        $seq = str_pad((string) $i, 6, '0', STR_PAD_LEFT);
        $orderUuid = (string) Str::uuid();
        $soldAt = now()->subMinutes(100 - $i)->toIso8601String();
        $mod = $i % 5;

        if ($mod === 1) {
            // Cash sale with banknote change: 2 Rani singles (600), cash 1000, change 400 -> net cash 600
            $orders[] = [
                'client_uuid' => $orderUuid,
                'shift_client_uuid' => $shiftUuid,
                'invoice_number' => "INV-T01-2026-{$seq}",
                'items' => [['product_id' => $rani->id, 'quantity' => '2.000']],
                'payments' => [['client_uuid' => (string) Str::uuid(), 'method' => 'cash', 'amount' => '1000.0000']],
                'change_amount' => '400.0000',
                'credit_amount' => '0.0000',
                'sold_at' => $soldAt,
            ];
            $expectedRaniSold = MoneyMath::add($expectedRaniSold, '2.000', 3);
            $expectedCashSales = MoneyMath::add($expectedCashSales, '600.0000');
            $expectedPaymentsCount += 1;
        } elseif ($mod === 2) {
            // Kuraimi wallet + Pack of 6 (1 * 1750) -> Rani -6
            $orders[] = [
                'client_uuid' => $orderUuid,
                'shift_client_uuid' => $shiftUuid,
                'invoice_number' => "INV-T01-2026-{$seq}",
                'items' => [['product_id' => $rani->id, 'unit_id' => $pack6->id, 'quantity' => '1.000']],
                'payments' => [['client_uuid' => (string) Str::uuid(), 'method' => 'kuraimi', 'reference_number' => "KUR-{$i}", 'amount' => '1750.0000']],
                'change_amount' => '0.0000',
                'credit_amount' => '0.0000',
                'sold_at' => $soldAt,
            ];
            $expectedRaniSold = MoneyMath::add($expectedRaniSold, '6.000', 3);
            $expectedPaymentsCount += 1;
        } elseif ($mod === 3) {
            // Weighted scale cheese 0.400 kg (2000) via Jawali wallet
            $orders[] = [
                'client_uuid' => $orderUuid,
                'shift_client_uuid' => $shiftUuid,
                'invoice_number' => "INV-T01-2026-{$seq}",
                'items' => [['product_id' => $cheese->id, 'quantity' => '0.400']],
                'payments' => [['client_uuid' => (string) Str::uuid(), 'method' => 'jawali', 'reference_number' => "JAW-{$i}", 'amount' => '2000.0000']],
                'change_amount' => '0.0000',
                'credit_amount' => '0.0000',
                'sold_at' => $soldAt,
            ];
            $expectedCheeseSold = MoneyMath::add($expectedCheeseSold, '0.400', 3);
            $expectedPaymentsCount += 1;
        } elseif ($mod === 4) {
            // Mixed payment to Offline Customer A: 4 bread (200) + 1 Rani (300) = 500 -> 200 cash + 300 credit
            $orders[] = [
                'client_uuid' => $orderUuid,
                'shift_client_uuid' => $shiftUuid,
                'customer_client_uuid' => $custAUuid,
                'invoice_number' => "INV-T01-2026-{$seq}",
                'items' => [
                    ['product_id' => $bread->id, 'quantity' => '4.000'],
                    ['product_id' => $rani->id, 'quantity' => '1.000'],
                ],
                'payments' => [['client_uuid' => (string) Str::uuid(), 'method' => 'cash', 'amount' => '200.0000']],
                'change_amount' => '0.0000',
                'credit_amount' => '300.0000',
                'sold_at' => $soldAt,
            ];
            $expectedBreadSold = MoneyMath::add($expectedBreadSold, '4.000', 3);
            $expectedRaniSold = MoneyMath::add($expectedRaniSold, '1.000', 3);
            $expectedCashSales = MoneyMath::add($expectedCashSales, '200.0000');
            $expectedCustACredit = MoneyMath::add($expectedCustACredit, '300.0000');
            $expectedPaymentsCount += 1;
        } else {
            // Every 5th invoice: alternate full credit to Offline Customer B vs Existing Customer (with 2 manager overrides)
            if ($i <= 10) {
                // Manager override below-cost sale (1 Rani: 300 - 100 = 200 cash)
                $orders[] = [
                    'client_uuid' => $orderUuid,
                    'shift_client_uuid' => $shiftUuid,
                    'invoice_number' => "INV-T01-2026-{$seq}",
                    'manager_override_pin' => '1122',
                    'items' => [['product_id' => $rani->id, 'quantity' => '1.000', 'discount_amount' => '100.0000']],
                    'payments' => [['client_uuid' => (string) Str::uuid(), 'method' => 'cash', 'amount' => '200.0000']],
                    'change_amount' => '0.0000',
                    'credit_amount' => '0.0000',
                    'sold_at' => $soldAt,
                ];
                $expectedRaniSold = MoneyMath::add($expectedRaniSold, '1.000', 3);
                $expectedCashSales = MoneyMath::add($expectedCashSales, '200.0000');
                $expectedPaymentsCount += 1;
            } elseif ($i % 2 === 0) {
                // Full credit to Offline Customer B (6 bread = 300)
                $orders[] = [
                    'client_uuid' => $orderUuid,
                    'shift_client_uuid' => $shiftUuid,
                    'customer_client_uuid' => $custBUuid,
                    'invoice_number' => "INV-T01-2026-{$seq}",
                    'items' => [['product_id' => $bread->id, 'quantity' => '6.000']],
                    'payments' => [],
                    'change_amount' => '0.0000',
                    'credit_amount' => '300.0000',
                    'sold_at' => $soldAt,
                ];
                $expectedBreadSold = MoneyMath::add($expectedBreadSold, '6.000', 3);
                $expectedCustBCredit = MoneyMath::add($expectedCustBCredit, '300.0000');
            } else {
                // Full credit to Existing Customer (2 Rani = 600)
                $orders[] = [
                    'client_uuid' => $orderUuid,
                    'shift_client_uuid' => $shiftUuid,
                    'customer_id' => $existingCustomer->id,
                    'invoice_number' => "INV-T01-2026-{$seq}",
                    'items' => [['product_id' => $rani->id, 'quantity' => '2.000']],
                    'payments' => [],
                    'change_amount' => '0.0000',
                    'credit_amount' => '600.0000',
                    'sold_at' => $soldAt,
                ];
                $expectedRaniSold = MoneyMath::add($expectedRaniSold, '2.000', 3);
                $expectedExistingCustCredit = MoneyMath::add($expectedExistingCustCredit, '600.0000');
            }
        }
    }

    // Settlements: CustA pays 1000 cash, CustB pays 500 cash
    $settlement1Uuid = (string) Str::uuid();
    $settlement2Uuid = (string) Str::uuid();
    $expectedCashIn = '1500.0000';

    // Drawer Outflows: Expense 2,500 + Worker Advance 4,000 + Supplier Payment 15,000 = 21,500.0000
    $expenseUuid = (string) Str::uuid();
    $advanceUuid = (string) Str::uuid();
    $suppPayUuid = (string) Str::uuid();
    $expectedCashOut = '21500.0000';

    $openingCash = '100000.0000';
    $expectedShiftCash = MoneyMath::sub(
        MoneyMath::add(MoneyMath::add($openingCash, $expectedCashSales), $expectedCashIn),
        $expectedCashOut
    );
    $actualCash = MoneyMath::sub($expectedShiftCash, '500.0000'); // -500.0000 intentional shortage

    $payload = [
        'customers' => [
            ['client_uuid' => $custAUuid, 'name' => 'عميل ضغط أوفلاين أ', 'phone' => '770111222'],
            ['client_uuid' => $custBUuid, 'name' => 'عميل ضغط أوفلاين ب', 'phone' => '770333444'],
        ],
        'shifts' => [
            [
                'client_uuid' => $shiftUuid,
                'terminal_code' => 'T01',
                'opening_cash' => $openingCash,
                'opened_at' => now()->subHours(6)->toIso8601String(),
                'status' => 'closed',
                'actual_cash' => $actualCash,
                'closed_at' => now()->toIso8601String(),
            ],
        ],
        'orders' => $orders,
        'payments' => [
            ['client_uuid' => $settlement1Uuid, 'shift_client_uuid' => $shiftUuid, 'customer_client_uuid' => $custAUuid, 'method' => 'cash', 'amount' => '1000.0000'],
            ['client_uuid' => $settlement2Uuid, 'shift_client_uuid' => $shiftUuid, 'customer_client_uuid' => $custBUuid, 'method' => 'cash', 'amount' => '500.0000'],
        ],
        'expenses' => [
            ['client_uuid' => $expenseUuid, 'shift_client_uuid' => $shiftUuid, 'category' => 'ديزل ومستلزمات', 'amount' => '2500.0000', 'payment_source' => 'cash_drawer'],
        ],
        'employee_advances' => [
            ['client_uuid' => $advanceUuid, 'shift_client_uuid' => $shiftUuid, 'employee_id' => $worker->id, 'type' => 'advance', 'amount' => '4000.0000', 'payment_source' => 'cash_drawer'],
        ],
        'supplier_payments' => [
            ['client_uuid' => $suppPayUuid, 'shift_client_uuid' => $shiftUuid, 'supplier_id' => $supplier->id, 'method' => 'cash', 'payment_source' => 'cash_drawer', 'amount' => '15000.0000'],
        ],
        'audit_events' => [
            ['client_uuid' => (string) Str::uuid(), 'event' => 'cart_item_removed', 'description' => 'حذف اختبار ضغط 1'],
            ['client_uuid' => (string) Str::uuid(), 'event' => 'cart_item_removed', 'description' => 'حذف اختبار ضغط 2'],
        ],
    ];

    $res = $this->postJson('/api/v1/sync/push', $payload)->assertOk()->json('results');

    expect($res['customers'][$custAUuid]['status'])->toBe('synced');
    expect($res['customers'][$custBUuid]['status'])->toBe('synced');
    expect($res['shifts'][$shiftUuid]['status'])->toBe('synced');
    expect($res['shifts'][$shiftUuid]['expected_cash'])->toBe($expectedShiftCash);
    expect($res['shifts'][$shiftUuid]['difference'])->toBe('-500.0000');

    foreach ($orders as $o) {
        expect($res['orders'][$o['client_uuid']]['status'])->toBe('synced');
    }

    expect(Order::forStore($store->id)->count())->toBe(50);
    expect(Payment::forStore($store->id)->count())->toBe($expectedPaymentsCount + 2);
    expect($terminal->fresh()->last_invoice_seq)->toBe(50);

    // Exact inventory check against MoneyMath & stock_movements ledger
    expect($rani->fresh()->quantity)->toBe(MoneyMath::sub('320.000', $expectedRaniSold, 3));
    expect($cheese->fresh()->quantity)->toBe(MoneyMath::sub('18.500', $expectedCheeseSold, 3));
    expect($bread->fresh()->quantity)->toBe(MoneyMath::sub('200.000', $expectedBreadSold, 3));

    $raniMovementsSum = MoneyMath::sum(
        StockMovement::forStore($store->id)->where('product_id', $rani->id)->pluck('quantity_delta')->all(),
        3
    );
    expect($raniMovementsSum)->toBe($rani->fresh()->quantity);

    // Exact shift, customer, worker, and supplier balances
    $shift = Shift::forStore($store->id)->where('client_uuid', $shiftUuid)->firstOrFail();
    expect($shift->expected_cash)->toBe($expectedShiftCash);
    expect($shift->difference)->toBe('-500.0000');

    $custA = Customer::forStore($store->id)->where('client_uuid', $custAUuid)->firstOrFail();
    $custB = Customer::forStore($store->id)->where('client_uuid', $custBUuid)->firstOrFail();
    expect($custA->current_balance)->toBe(MoneyMath::sub($expectedCustACredit, '1000.0000'));
    expect($custB->current_balance)->toBe(MoneyMath::sub($expectedCustBCredit, '500.0000'));
    expect($existingCustomer->fresh()->current_balance)->toBe(MoneyMath::add('15000.0000', $expectedExistingCustCredit));
    expect($worker->fresh()->current_balance)->toBe('-4000.0000');
    expect($supplier->fresh()->current_balance)->toBe('235000.0000');
    expect(Activity::where('event', 'cart_item_removed')->count())->toBe(2);
    expect(Activity::where('event', 'discount_override')->count())->toBe(2);

    // Replay entire 50-invoice batch -> 100% duplicate, zero balance drift
    $replay = $this->postJson('/api/v1/sync/push', $payload)->assertOk()->json('results');
    foreach ($orders as $o) {
        expect($replay['orders'][$o['client_uuid']]['status'])->toBe('duplicate');
    }
    expect(Order::forStore($store->id)->count())->toBe(50);
    expect($rani->fresh()->quantity)->toBe(MoneyMath::sub('320.000', $expectedRaniSold, 3));
    expect($shift->fresh()->expected_cash)->toBe($expectedShiftCash);
});
