<?php

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\Terminal;
use App\Models\User;
use App\Services\CustomerAccountService;
use App\Services\EmployeeAccountService;
use App\Services\ExpenseService;
use App\Services\OrderService;
use App\Services\ReturnService;
use App\Services\ShiftService;
use App\Services\SupplierAccountService;
use App\Support\StoreContext;
use Database\Seeders\DemoStoresSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

afterEach(function () {
    StoreContext::clear();
    Auth::logout();
});

test('supplier lifecycle (credit purchase + WAC update + drawer payout + purchase return) reconciles to 0.0000', function () {
    $this->seed(DemoStoresSeeder::class);

    $store = Store::where('slug', 'baqalat-alkhair')->firstOrFail();
    $owner = User::where('email', 'owner@alkhair.ye')->firstOrFail();
    $terminal = Terminal::forStore($store->id)->firstOrFail();
    $supplier = Supplier::forStore($store->id)->firstOrFail(); // opening & current = 250,000.0000
    $rani = Product::forStore($store->id)->where('barcode', '6281007041126')->firstOrFail(); // qty 120 @ 250.0000

    $shift = app(ShiftService::class)->openShift(['terminal_id' => $terminal->id, 'opening_cash' => '100000.0000'], $owner)['shift'];
    $service = app(SupplierAccountService::class);

    // 1. Purchase 80 units @ 300.0000 = 24,000 (4,000 paid from owner_pocket, 20,000 on credit)
    // WAC = ((120 * 250) + (80 * 300)) / 200 = (30,000 + 24,000) / 200 = 270.0000
    $purchase = $service->recordPurchase([
        'supplier_id' => $supplier->id,
        'branch_id' => $terminal->branch_id,
        'paid_amount' => '4000.0000',
        'credit_amount' => '20000.0000',
        'payment_source' => 'owner_pocket',
        'items' => [
            ['product_id' => $rani->id, 'quantity' => '80.000', 'unit_cost' => '300.0000'],
        ],
    ], $owner);

    expect($rani->fresh()->quantity)->toBe('200.000');
    expect($rani->fresh()->purchase_price)->toBe('270.0000');
    expect($supplier->fresh()->current_balance)->toBe('270000.0000');

    // 2. Pay supplier 50,000 from cash_drawer -> supplier balance = 220,000.0000
    $service->recordSupplierPayment($supplier, [
        'shift_id' => $shift->id,
        'method' => 'cash',
        'payment_source' => 'cash_drawer',
        'amount' => '50000.0000',
    ], $owner);

    expect($supplier->fresh()->current_balance)->toBe('220000.0000');

    // 3. Return 10 damaged/excess units from purchase @ 300 = 3,000 deducted from supplier credit -> 217,000.0000
    $service->recordPurchaseReturn($purchase, [
        'paid_amount' => '0.0000',
        'credit_amount' => '3000.0000',
        'items' => [
            ['product_id' => $rani->id, 'quantity' => '10.000'],
        ],
    ], $owner);

    expect($rani->fresh()->quantity)->toBe('190.000');
    expect($supplier->fresh()->current_balance)->toBe('217000.0000');

    $statement = $service->getSupplierStatement($supplier->fresh());
    expect($statement['opening_balance'])->toBe('250000.0000');
    expect($statement['closing_balance'])->toBe('217000.0000');
    expect($statement['closing_balance'])->toBe($supplier->fresh()->current_balance);
});

test('employee lifecycle (salary accrual + duplicate guard + drawer advance + bonus/deduction + shift shortage transfer) reconciles to 0.0000', function () {
    $this->seed(DemoStoresSeeder::class);

    $store = Store::where('slug', 'baqalat-alkhair')->firstOrFail();
    $owner = User::where('email', 'owner@alkhair.ye')->firstOrFail();
    $cashierUser = User::where('email', 'cashier@alkhair.ye')->firstOrFail();
    $terminal = Terminal::forStore($store->id)->firstOrFail();
    $akramEmp = Employee::forStore($store->id)->where('user_id', $cashierUser->id)->firstOrFail(); // base_salary 90,000

    $empService = app(EmployeeAccountService::class);
    $shiftService = app(ShiftService::class);

    // 1. Accrue November 2026 salaries (+90,000 for Akram)
    $firstRun = $empService->accrueSalaries($store, '2026-11', $owner);
    expect($firstRun['accrued_count'])->toBe(2);

    // Duplicate period accrual MUST skip all already-accrued employees
    $secondRun = $empService->accrueSalaries($store, '2026-11', $owner);
    expect($secondRun['accrued_count'])->toBe(0);
    expect($akramEmp->fresh()->current_balance)->toBe('90000.0000');

    // 2. Open shift as Akram, take 10,000 advance from drawer, close shift with 1,500 cash shortage
    $shift = $shiftService->openShift(['terminal_id' => $terminal->id, 'opening_cash' => '30000.0000'], $cashierUser)['shift'];

    $empService->recordAdvanceOrPayout($akramEmp, [
        'shift_id' => $shift->id,
        'type' => 'advance',
        'payment_source' => 'cash_drawer',
        'amount' => '10000.0000',
    ], $owner);

    // Bonus +5,000 and Deduction -2,000
    $empService->recordDeductionOrBonus($akramEmp, 'bonus', '5000.0000', $owner, 'مكافأة انضباط');
    $empService->recordDeductionOrBonus($akramEmp, 'deduction', '2000.0000', $owner, 'تأخير');

    // Expected cash in shift = 30,000 - 10,000 = 20,000; Akram counts 18,500 -> difference = -1,500.0000
    $closedShift = $shiftService->closeShift($shift, '18500.0000', $owner);
    expect($closedShift->difference)->toBe('-1500.0000');

    // Transfer -1,500 shortage to Akram's employee account
    $empService->transferShiftShortageToCashier($closedShift, $owner);

    // Duplicate shortage transfer MUST be blocked
    expect(fn () => $empService->transferShiftShortageToCashier($closedShift, $owner))
        ->toThrow(ValidationException::class);

    // Expected Akram balance: 90,000 - 10,000 + 5,000 - 2,000 - 1,500 = 81,500.0000
    expect($akramEmp->fresh()->current_balance)->toBe('81500.0000');

    $statement = $empService->getEmployeeStatement($akramEmp->fresh());
    expect($statement['closing_balance'])->toBe('81500.0000');
    expect($statement['closing_balance'])->toBe($akramEmp->fresh()->current_balance);
});

test('ShiftService expected_cash reconciles every cash inflow and outflow to the exact Riyal', function () {
    $this->seed(DemoStoresSeeder::class);

    $store = Store::where('slug', 'baqalat-alkhair')->firstOrFail();
    $owner = User::where('email', 'owner@alkhair.ye')->firstOrFail();
    $terminal = Terminal::forStore($store->id)->firstOrFail();
    $product = Product::forStore($store->id)->where('barcode', '6281007041126')->firstOrFail(); // price 300
    $customer = Customer::forStore($store->id)->firstOrFail(); // debt 15,000
    $supplier = Supplier::forStore($store->id)->firstOrFail();
    $worker = Employee::forStore($store->id)->whereNull('user_id')->firstOrFail();

    $shiftService = app(ShiftService::class);
    $shift = $shiftService->openShift([
        'terminal_id' => $terminal->id,
        'opening_cash' => '50000.0000',
    ], $owner)['shift'];

    // 1. Cash Sale: 60 units * 300 = 18,000 (tendered 20,000, change 2,000 -> +18,000 net cash)
    $sale = app(OrderService::class)->createSale([
        'shift_id' => $shift->id,
        'items' => [['product_id' => $product->id, 'quantity' => '60']],
        'payments' => [['method' => 'cash', 'amount' => '20000.0000']],
        'change_amount' => '2000.0000',
    ], $owner)['order'];

    // 2. Customer Cash Debt Collection (+7,000)
    app(CustomerAccountService::class)->recordCreditSettlement($customer, [
        'shift_id' => $shift->id,
        'method' => 'cash',
        'amount' => '7000.0000',
    ], $owner);

    // 3. Cash Sales Return: 10 units * 300 = -3,000
    app(ReturnService::class)->createReturn($sale, [
        'shift_id' => $shift->id,
        'refund_method' => 'cash',
        'items' => [['product_id' => $product->id, 'quantity' => '10', 'return_condition' => 'restock']],
    ], $owner);

    // 4. Cash-drawer Expense (-2,500)
    app(ExpenseService::class)->recordExpense([
        'shift_id' => $shift->id,
        'category' => 'ديزل مولد',
        'amount' => '2500.0000',
        'payment_source' => 'cash_drawer',
    ], $owner);

    // 5. Cash-drawer Supplier Payout (-10,000)
    app(SupplierAccountService::class)->recordSupplierPayment($supplier, [
        'shift_id' => $shift->id,
        'method' => 'cash',
        'payment_source' => 'cash_drawer',
        'amount' => '10000.0000',
    ], $owner);

    // 6. Cash-drawer Employee Advance (-4,500)
    app(EmployeeAccountService::class)->recordAdvanceOrPayout($worker, [
        'shift_id' => $shift->id,
        'type' => 'advance',
        'payment_source' => 'cash_drawer',
        'amount' => '4500.0000',
    ], $owner);

    // Expected = 50,000 + 18,000 + 7,000 - 3,000 - (2,500 + 10,000 + 4,500) = 55,000.0000
    $freshShift = $shift->fresh();
    expect($freshShift->cash_sales_total)->toBe('18000.0000');
    expect($freshShift->cash_in_total)->toBe('7000.0000');
    expect($freshShift->cash_returns_total)->toBe('3000.0000');
    expect($freshShift->cash_out_total)->toBe('17000.0000');
    expect($freshShift->expected_cash)->toBe('55000.0000');

    $closed = $shiftService->closeShift($freshShift, '54000.0000', $owner);
    expect($closed->difference)->toBe('-1000.0000');
});
