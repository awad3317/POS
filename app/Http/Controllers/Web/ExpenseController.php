<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Shift;
use App\Services\ExpenseService;
use App\Support\MoneyMath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function __construct(
        protected ExpenseService $expenseService,
    ) {}

    public function index(Request $request): View
    {
        $store = $request->user()->store;
        $expenses = Expense::forStore($store->id)
            ->with(['user', 'shift.terminal'])
            ->latest('spent_at')
            ->paginate(20);

        $monthTotal = MoneyMath::sum(
            Expense::forStore($store->id)->where('spent_at', '>=', now()->startOfMonth())->pluck('amount')->all()
        );

        $openShifts = Shift::forStore($store->id)->where('status', 'open')->with('terminal')->get();

        return view('admin.expenses.index', compact('store', 'expenses', 'monthTotal', 'openShifts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_source' => ['required', 'string', 'in:cash_drawer,owner_pocket,bank'],
            'shift_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $this->expenseService->recordExpense($validated, $request->user());

        return redirect()->route('expenses.index')->with('success', 'تم تسجيل المصروف بنجاح.');
    }
}
