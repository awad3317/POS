<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Shift;
use App\Models\Supplier;
use App\Services\SupplierAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    public function __construct(
        protected SupplierAccountService $supplierAccountService,
    ) {}

    public function index(Request $request): View
    {
        $store = $request->user()->store;
        $purchases = Purchase::forStore($store->id)
            ->with(['supplier', 'user', 'items.product'])
            ->latest('id')
            ->paginate(20);

        return view('admin.purchases.index', compact('store', 'purchases'));
    }

    public function create(Request $request): View
    {
        $store = $request->user()->store;
        $suppliers = Supplier::forStore($store->id)->orderBy('name')->get();
        $products = Product::forStore($store->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'barcode', 'purchase_price']);
        $openShifts = Shift::forStore($store->id)->where('status', 'open')->with('terminal')->get();

        return view('admin.purchases.create', compact('store', 'suppliers', 'products', 'openShifts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['nullable', 'integer'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'credit_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string'],
            'payment_source' => ['nullable', 'string', 'in:cash_drawer,owner_pocket,bank'],
            'shift_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.unit_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $this->supplierAccountService->recordPurchase($validated, $request->user());

        return redirect()->route('purchases.index')->with('success', 'تم اعتماد فاتورة المشتريات وتحديث المخزون ومتوسط التكلفة.');
    }

    public function recordReturn(Request $request, Purchase $purchase): RedirectResponse
    {
        $validated = $request->validate([
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'credit_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $this->supplierAccountService->recordPurchaseReturn($purchase, $validated, $request->user());

        return redirect()->route('purchases.index')->with('success', 'تم تسجيل مرتجع المشتريات وتحديث المخزون وحساب المورد.');
    }
}
