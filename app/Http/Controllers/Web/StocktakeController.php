<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\InventoryService;
use App\Support\MoneyMath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StocktakeController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService,
    ) {}

    public function index(Request $request): View
    {
        $store = $request->user()->store;
        $products = Product::forStore($store->id)->orderBy('name')->get(['id', 'name', 'barcode', 'quantity', 'purchase_price']);
        $recentMovements = StockMovement::forStore($store->id)
            ->with(['product', 'user'])
            ->latest('id')
            ->limit(30)
            ->get();

        return view('admin.stocktake.index', compact('store', 'products', 'recentMovements'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer'],
            'mode' => ['required', 'string', 'in:stocktake,adjustment_in,adjustment_out'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $store = $user->store;
        $product = Product::forStore($store->id)->findOrFail($validated['product_id']);
        $branch = Branch::forStore($store->id)->find($user->branch_id) ?? Branch::forStore($store->id)->firstOrFail();

        $inputQty = MoneyMath::qty((string) $validated['quantity']);

        if ($validated['mode'] === 'stocktake') {
            $delta = MoneyMath::sub($inputQty, $product->quantity, 3);
            $type = 'stocktake';
        } elseif ($validated['mode'] === 'adjustment_in') {
            $delta = $inputQty;
            $type = 'adjustment_in';
        } else {
            $delta = MoneyMath::negate($inputQty, 3);
            $type = 'adjustment_out';
        }

        if (MoneyMath::isZero($delta)) {
            return back()->with('success', 'الكمية الفعلية مطابقة للرصيد الدفتري بالفعل.');
        }

        if ($type !== 'stocktake' && ! MoneyMath::isPositive($inputQty)) {
            throw ValidationException::withMessages(['quantity' => 'يجب أن تكون كمية التسوية أكبر من صفر.']);
        }

        $this->inventoryService->applyMovement(
            store: $store,
            branch: $branch,
            product: $product,
            variant: null,
            actor: $user,
            type: $type,
            quantityDelta: $delta,
            unitCost: $product->purchase_price,
            referenceType: Product::class,
            referenceId: $product->id,
            note: $validated['note'] ?? 'تسوية جرد مخزني',
        );

        return redirect()->route('stocktake.index')->with('success', 'تم تسجيل قيد الجرد وتحديث رصيد المخزون بنجاح.');
    }

    public function stockCard(Request $request, Product $product): View
    {
        $store = $request->user()->store;
        $movements = StockMovement::forStore($store->id)
            ->where('product_id', $product->id)
            ->with(['user', 'variant'])
            ->orderByDesc('id')
            ->paginate(30);

        return view('admin.stocktake.stock-card', compact('store', 'product', 'movements'));
    }
}
