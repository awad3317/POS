<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\Supplier;
use App\Services\SupplierAccountService;
use App\Support\MoneyMath;
use App\Support\WhatsAppLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function __construct(
        protected SupplierAccountService $supplierAccountService,
    ) {}

    public function index(Request $request): View
    {
        $store = $request->user()->store;
        $suppliers = Supplier::forStore($store->id)
            ->withSum(['purchases as total_purchases' => fn ($q) => $q->where('type', 'purchase')], 'total_amount')
            ->withSum('payments as total_payments', 'amount')
            ->latest('id')
            ->paginate(20);

        $totalPayables = MoneyMath::sum(Supplier::forStore($store->id)->pluck('current_balance')->all());
        $openShifts = Shift::forStore($store->id)->where('status', 'open')->with('terminal')->get();

        return view('admin.suppliers.index', compact('store', 'suppliers', 'totalPayables', 'openShifts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'company_name' => ['nullable', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $opening = MoneyMath::money((string) ($validated['opening_balance'] ?? '0'));

        Supplier::forStore($request->user()->store_id)->create([
            'store_id' => $request->user()->store_id,
            'name' => $validated['name'],
            'company_name' => $validated['company_name'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'whatsapp' => $validated['whatsapp'] ?? ($validated['phone'] ?? null),
            'address' => $validated['address'] ?? null,
            'opening_balance' => $opening,
            'current_balance' => $opening,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('suppliers.index')->with('success', 'تمت إضافة المورد بنجاح.');
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'company_name' => ['nullable', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $supplier->update($validated);

        return redirect()->route('suppliers.index')->with('success', 'تم تحديث بيانات المورد.');
    }

    public function recordPayment(Request $request, Supplier $supplier): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'string', 'in:cash,kuraimi,jawali,floosak,onecash,bank_transfer'],
            'payment_source' => ['required', 'string', 'in:cash_drawer,owner_pocket,bank'],
            'shift_id' => ['nullable', 'integer'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->supplierAccountService->recordSupplierPayment($supplier, $validated, $request->user());

        return back()->with('success', 'تم تسجيل سند صرف المورد وتحديث رصيده بنجاح.');
    }

    public function statement(Request $request, Supplier $supplier): View
    {
        $store = $request->user()->store;
        $from = $request->query('from');
        $to = $request->query('to');

        $statement = $this->supplierAccountService->getSupplierStatement($supplier, $from, $to);

        $whatsappUrl = WhatsAppLink::forSupplierPayment($supplier, $store, $statement['closing_balance']);

        return view('admin.suppliers.statement', compact('store', 'supplier', 'statement', 'whatsappUrl', 'from', 'to'));
    }
}
