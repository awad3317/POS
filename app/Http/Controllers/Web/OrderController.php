<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Shift;
use App\Services\ReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        protected ReturnService $returnService,
    ) {}

    public function index(Request $request): View
    {
        $store = $request->user()->store;
        $status = $request->query('status');
        $type = $request->query('type');

        $orders = Order::forStore($store->id)
            ->with(['customer', 'user', 'terminal'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.orders.index', compact('store', 'orders', 'status', 'type'));
    }

    public function show(Request $request, Order $order): View
    {
        $store = $request->user()->store;
        $order->load(['customer', 'user', 'terminal', 'items', 'payments', 'returns.items']);
        $openShifts = Shift::forStore($store->id)->where('status', 'open')->with('terminal')->get();

        return view('admin.orders.show', compact('store', 'order', 'openShifts'));
    }

    public function recordReturn(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'shift_id' => ['nullable', 'integer'],
            'refund_method' => ['required', 'string', 'in:cash,credit,kuraimi,jawali,floosak'],
            'notes' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['nullable', 'integer'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
            'items.*.return_condition' => ['required', 'string', 'in:restock,damaged'],
        ]);

        // Filter out lines where user kept quantity = 0 in the return table
        $validated['items'] = array_values(array_filter(
            $validated['items'],
            fn (array $item) => (float) ($item['quantity'] ?? 0) > 0
        ));

        $result = $this->returnService->createReturn($order, $validated, $request->user());

        return redirect()
            ->route('orders.show', $result['order']->id)
            ->with('success', "تم إصدار سند المرتجع رقم {$result['order']->invoice_number} بنجاح.");
    }
}
