<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\EmployeeTransaction;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shift;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Support\MoneyMath;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class ReportController extends Controller
{
    public function dashboard(Request $request): View
    {
        $user = $request->user();
        $store = $user->store;
        $canViewProfit = $user->can('reports.view_profit');

        $todaySales = MoneyMath::sum(
            Order::forStore($store->id)->where('type', 'sale')->whereDate('sold_at', today())->pluck('total_amount')->all()
        );
        $todayOrdersCount = Order::forStore($store->id)->where('type', 'sale')->whereDate('sold_at', today())->count();
        $openShifts = Shift::forStore($store->id)->where('status', 'open')->with(['user', 'terminal'])->get();
        $lowStockCount = Product::forStore($store->id)->whereColumn('quantity', '<=', 'min_stock_alert')->count();

        $metrics = $canViewProfit ? $this->calculateFinancialMetrics($store->id) : null;

        return view('admin.dashboard', compact(
            'store',
            'canViewProfit',
            'todaySales',
            'todayOrdersCount',
            'openShifts',
            'lowStockCount',
            'metrics'
        ));
    }

    public function index(Request $request): View
    {
        $store = $request->user()->store;
        $metrics = $this->calculateFinancialMetrics($store->id);

        $lowStockProducts = Product::forStore($store->id)
            ->whereColumn('quantity', '<=', 'min_stock_alert')
            ->orderBy('quantity')
            ->limit(20)
            ->get();

        $negativeAlerts = StockMovement::forStore($store->id)
            ->where('is_negative_alert', true)
            ->with(['product', 'user'])
            ->latest('id')
            ->limit(20)
            ->get();

        $soldProductIdsLast30 = OrderItem::forStore($store->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->pluck('product_id')
            ->unique();

        $slowMovingProducts = Product::forStore($store->id)
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->whereNotIn('id', $soldProductIdsLast30)
            ->limit(20)
            ->get();

        return view('admin.reports.index', compact(
            'store',
            'metrics',
            'lowStockProducts',
            'negativeAlerts',
            'slowMovingProducts'
        ));
    }

    public function ownerDashboard(Request $request): View
    {
        $store = $request->user()->store;
        $metrics = $this->calculateFinancialMetrics($store->id);

        $todayOrdersQuery = Order::forStore($store->id)
            ->where('type', 'sale')
            ->whereDate('sold_at', today());

        $todayOrdersCount = (clone $todayOrdersQuery)->count();
        $todaySaleOrderIds = (clone $todayOrdersQuery)->pluck('id');
        $todaySales = MoneyMath::sum((clone $todayOrdersQuery)->pluck('total_amount')->all());
        $todayCreditSales = MoneyMath::sum((clone $todayOrdersQuery)->pluck('credit_amount')->all());

        $todayCashSales = $todaySaleOrderIds->isEmpty()
            ? '0.0000'
            : MoneyMath::sum(
                Payment::forStore($store->id)
                    ->where('payment_type', 'order_payment')
                    ->whereIn('order_id', $todaySaleOrderIds)
                    ->where('method', 'cash')
                    ->pluck('amount')
                    ->all()
            );

        $todayWalletSales = $todaySaleOrderIds->isEmpty()
            ? '0.0000'
            : MoneyMath::sum(
                Payment::forStore($store->id)
                    ->where('payment_type', 'order_payment')
                    ->whereIn('order_id', $todaySaleOrderIds)
                    ->where('method', '!=', 'cash')
                    ->pluck('amount')
                    ->all()
            );

        $openShifts = Shift::forStore($store->id)
            ->where('status', 'open')
            ->with(['terminal', 'user'])
            ->orderByDesc('opened_at')
            ->get();

        $monthEmployeeAdvances = MoneyMath::sum(
            EmployeeTransaction::forStore($store->id)
                ->where('type', 'advance')
                ->where('transaction_date', '>=', now()->startOfMonth())
                ->pluck('amount')
                ->all()
        );

        $lowStockProducts = Product::forStore($store->id)
            ->where('is_active', true)
            ->whereColumn('quantity', '<=', 'min_stock_alert')
            ->orderBy('quantity')
            ->limit(15)
            ->get();

        $negativeAlerts = StockMovement::forStore($store->id)
            ->where('is_negative_alert', true)
            ->with(['product', 'user'])
            ->latest('id')
            ->limit(15)
            ->get();

        $storeUserIds = User::forStore($store->id)->withTrashed()->pluck('id');
        $auditAlerts = Activity::query()
            ->whereIn('event', ['discount_override', 'cart_item_removed'])
            ->where('causer_type', User::class)
            ->whereIn('causer_id', $storeUserIds)
            ->with('causer')
            ->latest('id')
            ->limit(20)
            ->get();

        return view('admin.owner.index', compact(
            'store',
            'metrics',
            'todaySales',
            'todayOrdersCount',
            'todayCashSales',
            'todayWalletSales',
            'todayCreditSales',
            'openShifts',
            'monthEmployeeAdvances',
            'lowStockProducts',
            'negativeAlerts',
            'auditAlerts'
        ));
    }

    /**
     * @return array<string, string>
     */
    protected function calculateFinancialMetrics(int $storeId): array
    {
        $grossSales = MoneyMath::sum(Order::forStore($storeId)->where('type', 'sale')->pluck('total_amount')->all());
        $salesReturns = MoneyMath::sum(Order::forStore($storeId)->where('type', 'return')->pluck('total_amount')->all());
        $netSales = MoneyMath::sub($grossSales, $salesReturns);

        $saleOrderIds = Order::forStore($storeId)->where('type', 'sale')->pluck('id');
        $returnOrderIds = Order::forStore($storeId)->where('type', 'return')->pluck('id');

        $saleCogs = '0.0000';
        foreach (OrderItem::forStore($storeId)->whereIn('order_id', $saleOrderIds)->get(['quantity', 'unit_cost']) as $it) {
            $saleCogs = MoneyMath::add($saleCogs, MoneyMath::mul($it->unit_cost, $it->quantity));
        }

        $returnCogs = '0.0000';
        foreach (OrderItem::forStore($storeId)->whereIn('order_id', $returnOrderIds)->where('return_condition', 'restock')->get(['quantity', 'unit_cost']) as $rit) {
            $returnCogs = MoneyMath::add($returnCogs, MoneyMath::mul($rit->unit_cost, $rit->quantity));
        }

        $netCogs = MoneyMath::sub($saleCogs, $returnCogs);
        $grossProfit = MoneyMath::sub($netSales, $netCogs);

        $expensesTotal = MoneyMath::sum(Expense::forStore($storeId)->pluck('amount')->all());
        $accruedSalaries = MoneyMath::sum(
            EmployeeTransaction::forStore($storeId)->whereIn('type', ['salary_accrual', 'bonus'])->pluck('amount')->all()
        );

        $netProfit = MoneyMath::sub(MoneyMath::sub($grossProfit, $expensesTotal), $accruedSalaries);

        // Store Financial Position
        $cashInDrawers = MoneyMath::sum(Shift::forStore($storeId)->where('status', 'open')->pluck('expected_cash')->all());
        $customerReceivables = MoneyMath::sum(Customer::forStore($storeId)->pluck('current_balance')->all());
        $supplierPayables = MoneyMath::sum(Supplier::forStore($storeId)->pluck('current_balance')->all());
        $employeeNetPayables = MoneyMath::sum(Employee::forStore($storeId)->pluck('current_balance')->all());

        $netPosition = MoneyMath::sub(
            MoneyMath::sub(MoneyMath::add($cashInDrawers, $customerReceivables), $supplierPayables),
            $employeeNetPayables
        );

        return [
            'gross_sales' => $grossSales,
            'sales_returns' => $salesReturns,
            'net_sales' => $netSales,
            'net_cogs' => $netCogs,
            'gross_profit' => $grossProfit,
            'expenses_total' => $expensesTotal,
            'accrued_salaries' => $accruedSalaries,
            'net_profit' => $netProfit,
            'cash_in_drawers' => $cashInDrawers,
            'customer_receivables' => $customerReceivables,
            'supplier_payables' => $supplierPayables,
            'employee_net_payables' => $employeeNetPayables,
            'net_position' => $netPosition,
        ];
    }
}
