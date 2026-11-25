<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeTransaction;
use App\Models\Shift;
use App\Models\User;
use App\Services\EmployeeAccountService;
use App\Support\MoneyMath;
use App\Support\WhatsAppLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function __construct(
        protected EmployeeAccountService $employeeAccountService,
    ) {}

    public function index(Request $request): View
    {
        $store = $request->user()->store;
        $monthStart = now()->startOfMonth();

        $employees = Employee::forStore($store->id)
            ->with('user')
            ->withSum([
                'transactions as month_advances' => fn ($q) => $q->where('type', 'advance')->where('transaction_date', '>=', $monthStart),
            ], 'amount')
            ->latest('id')
            ->get();

        $storeUsers = User::forStore($store->id)->get(['id', 'name', 'email']);
        $openShifts = Shift::forStore($store->id)->where('status', 'open')->with('terminal')->get();

        return view('admin.employees.index', compact('store', 'employees', 'storeUsers', 'openShifts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'user_id' => ['nullable', 'integer'],
            'phone' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'salary_type' => ['required', 'string', 'in:monthly,weekly,daily,commission_only'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'opening_balance' => ['nullable', 'numeric'],
            'hired_at' => ['nullable', 'date'],
        ]);

        $user = $request->user();
        $opening = MoneyMath::money((string) ($validated['opening_balance'] ?? '0'));

        Employee::forStore($user->store_id)->create([
            'store_id' => $user->store_id,
            'branch_id' => $user->branch_id,
            'user_id' => $validated['user_id'] ?? null,
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'whatsapp' => $validated['whatsapp'] ?? ($validated['phone'] ?? null),
            'job_title' => $validated['job_title'] ?? null,
            'salary_type' => $validated['salary_type'],
            'base_salary' => MoneyMath::money((string) $validated['base_salary']),
            'commission_percent' => $validated['commission_percent'] ?? '0.00',
            'opening_balance' => $opening,
            'current_balance' => $opening,
            'hired_at' => $validated['hired_at'] ?? now()->toDateString(),
            'is_active' => true,
        ]);

        return redirect()->route('employees.index')->with('success', 'تمت إضافة ملف العامل بنجاح.');
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'salary_type' => ['required', 'string', 'in:monthly,weekly,daily,commission_only'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $employee->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'whatsapp' => $validated['whatsapp'] ?? null,
            'job_title' => $validated['job_title'] ?? null,
            'salary_type' => $validated['salary_type'],
            'base_salary' => MoneyMath::money((string) $validated['base_salary']),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('employees.index')->with('success', 'تم تحديث بيانات العامل.');
    }

    public function recordTransaction(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:advance,salary_payout,bonus,deduction'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_source' => ['nullable', 'string', 'in:cash_drawer,owner_pocket,kuraimi,jawali,floosak,bank'],
            'shift_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        if (in_array($validated['type'], ['advance', 'salary_payout'], true)) {
            $validated['payment_source'] = $validated['payment_source'] ?? 'owner_pocket';
            $this->employeeAccountService->recordAdvanceOrPayout($employee, $validated, $request->user());
        } else {
            $this->employeeAccountService->recordDeductionOrBonus(
                $employee,
                $validated['type'],
                (string) $validated['amount'],
                $request->user(),
                $validated['notes'] ?? null
            );
        }

        return back()->with('success', 'تم تسجيل الحركة في دفتر حساب العامل بنجاح.');
    }

    public function accrueSalaries(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'period_label' => ['required', 'string', 'max:50'],
        ]);

        $user = $request->user();
        $res = $this->employeeAccountService->accrueSalaries($user->store, $validated['period_label'], $user);

        return redirect()->route('employees.index')->with(
            'success',
            "تم اعتماد رواتب الفترة ({$validated['period_label']}): {$res['accrued_count']} عامل مضاف، {$res['skipped_count']} متخطى."
        );
    }

    public function statement(Request $request, Employee $employee): View
    {
        $store = $request->user()->store;
        $from = $request->query('from');
        $to = $request->query('to');

        $statement = $this->employeeAccountService->getEmployeeStatement($employee, $from, $to);

        $whatsappUrl = WhatsAppLink::forEmployeeStatement($employee, $store, $statement['closing_balance']);

        return view('admin.employees.statement', compact('store', 'employee', 'statement', 'whatsappUrl', 'from', 'to'));
    }
}
