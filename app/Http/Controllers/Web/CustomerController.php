<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shift;
use App\Services\CustomerAccountService;
use App\Support\MoneyMath;
use App\Support\WhatsAppLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerAccountService $customerAccountService,
    ) {}

    public function index(Request $request): View
    {
        $store = $request->user()->store;
        $search = trim((string) $request->query('q', ''));

        $customers = Customer::forStore($store->id)
            ->when($search !== '', fn ($q) => $q->where(fn ($s) => $s->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $totalReceivables = MoneyMath::sum(Customer::forStore($store->id)->pluck('current_balance')->all());
        $openShifts = Shift::forStore($store->id)->where('status', 'open')->with('terminal')->get();

        return view('admin.customers.index', compact('store', 'customers', 'totalReceivables', 'openShifts', 'search'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $opening = MoneyMath::money((string) ($validated['opening_balance'] ?? '0'));

        Customer::forStore($request->user()->store_id)->create([
            'store_id' => $request->user()->store_id,
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'whatsapp' => $validated['whatsapp'] ?? ($validated['phone'] ?? null),
            'address' => $validated['address'] ?? null,
            'opening_balance' => $opening,
            'credit_limit' => MoneyMath::money((string) ($validated['credit_limit'] ?? '0')),
            'current_balance' => $opening,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('customers.index')->with('success', 'تمت إضافة العميل بنجاح.');
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $customer->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'whatsapp' => $validated['whatsapp'] ?? null,
            'address' => $validated['address'] ?? null,
            'credit_limit' => MoneyMath::money((string) ($validated['credit_limit'] ?? '0')),
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('customers.index')->with('success', 'تم تحديث بيانات العميل.');
    }

    public function recordSettlement(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'string', 'in:cash,kuraimi,jawali,floosak,onecash,card,bank_transfer'],
            'shift_id' => ['nullable', 'integer'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $this->customerAccountService->recordCreditSettlement($customer, $validated, $request->user());

        return back()->with('success', 'تم تسجيل سند القبض وخصم المبلغ من رصيد العميل.');
    }

    public function statement(Request $request, Customer $customer): View
    {
        $store = $request->user()->store;
        $orders = Order::forStore($store->id)
            ->where('customer_id', $customer->id)
            ->where('credit_amount', '>', 0)
            ->orderBy('sold_at')
            ->get();

        $settlements = Payment::forStore($store->id)
            ->where('customer_id', $customer->id)
            ->where('payment_type', 'credit_settlement')
            ->orderBy('paid_at')
            ->get();

        $events = [];
        foreach ($orders as $o) {
            $signed = $o->type === 'sale' ? MoneyMath::money($o->credit_amount) : MoneyMath::negate($o->credit_amount);
            $events[] = [
                'ts' => $o->sold_at?->getTimestamp() ?? 0,
                'date' => $o->sold_at?->format('Y-m-d H:i'),
                'type' => $o->type === 'sale' ? 'فاتورة آجل' : 'مرتجع آجل',
                'reference' => $o->invoice_number,
                'signed_amount' => $signed,
            ];
        }
        foreach ($settlements as $s) {
            $events[] = [
                'ts' => $s->paid_at?->getTimestamp() ?? 0,
                'date' => $s->paid_at?->format('Y-m-d H:i'),
                'type' => "سند قبض ({$s->method})",
                'reference' => $s->reference_number ?? "PAY-{$s->id}",
                'signed_amount' => MoneyMath::negate($s->amount),
            ];
        }

        usort($events, fn ($a, $b) => $a['ts'] <=> $b['ts']);

        $running = MoneyMath::money($customer->opening_balance);
        foreach ($events as &$ev) {
            $running = MoneyMath::add($running, $ev['signed_amount']);
            $ev['running_balance'] = $running;
        }
        unset($ev);

        $whatsappUrl = WhatsAppLink::build(
            $customer->whatsapp ?: $customer->phone,
            "عزيزنا العميل / {$customer->name}\nتحية طيبة من {$store->name}\nنود تذكيركم بأن الرصيد المستحق في حسابكم هو: {$customer->current_balance} {$store->currency_symbol}.\nشكراً لتعاملكم معنا."
        );

        return view('admin.customers.statement', compact('store', 'customer', 'events', 'whatsappUrl'));
    }
}
