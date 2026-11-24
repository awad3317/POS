<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\Terminal;
use App\Services\EmployeeAccountService;
use App\Services\ShiftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShiftController extends Controller
{
    public function __construct(
        protected ShiftService $shiftService,
        protected EmployeeAccountService $employeeAccountService,
    ) {}

    public function index(Request $request): View
    {
        $store = $request->user()->store;
        $shifts = Shift::forStore($store->id)
            ->with(['user', 'terminal', 'branch'])
            ->latest('id')
            ->paginate(20);

        $terminals = Terminal::forStore($store->id)->where('is_active', true)->get();

        return view('admin.shifts.index', compact('store', 'shifts', 'terminals'));
    }

    public function open(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'terminal_id' => ['required', 'integer'],
            'opening_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $res = $this->shiftService->openShift($validated, $request->user());

        return redirect()->route('shifts.show', $res['shift']->id)->with('success', 'تم فتح الوردية بنجاح.');
    }

    public function show(Request $request, Shift $shift): View
    {
        $store = $request->user()->store;
        $shift->load(['user', 'terminal', 'branch', 'orders', 'expenses', 'supplierPayments', 'employeeTransactions']);

        return view('admin.shifts.show', compact('store', 'shift'));
    }

    public function close(Request $request, Shift $shift): RedirectResponse
    {
        $validated = $request->validate([
            'actual_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $this->shiftService->closeShift($shift, (string) $validated['actual_cash'], $request->user(), $validated['notes'] ?? null);

        return redirect()->route('shifts.show', $shift->id)->with('success', 'تم إغلاق الوردية وإصدار تقرير الجرد Z-Report.');
    }

    public function transferShortage(Request $request, Shift $shift): RedirectResponse
    {
        $this->employeeAccountService->transferShiftShortageToCashier(
            $shift,
            $request->user(),
            $request->input('notes')
        );

        return redirect()->route('shifts.show', $shift->id)->with('success', 'تم ترحيل عجز الصندوق إلى حساب الكاشير بنجاح.');
    }
}
