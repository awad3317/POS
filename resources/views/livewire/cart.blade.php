<div class="w-full" dir="rtl">

    @if (session()->has('error'))
        <p class="text-red-500 text-right mb-2 font-medium">{{ session('error') }}</p>
    @endif

    <div class="overflow-x-auto md:overflow-x-none">
        <table class="min-w-[600px] min-w-full border border-gray-300 text-right">
            <thead>
                <tr class="bg-gray-200">
                    <th class="px-2 py-2 border border-gray-400 text-right w-3/5 dark:text-gray-800">المنتج</th>
                    <th class="px-2 py-2 border border-gray-400 text-center w-1/6 dark:text-gray-800">السعر</th>
                    <th class="px-2 py-2 border border-gray-400 text-center w-1/6 dark:text-gray-800">الضريبة (%)</th>
                    <th class="px-2 py-2 border border-gray-400 text-center w-1/6 dark:text-gray-800">الكمية</th>
                    <th class="px-2 py-2 border border-gray-400 text-center w-1/6 dark:text-gray-800">الإجمالي</th>
                </tr>
            </thead>
            <tbody>
            @if ( !is_countable($cartItems) || count($cartItems) < 1)
                <tr class="min-h-32">
                    <td colspan="5" class="p-4 text-center text-gray-500 font-medium">السلة فارغة. أضف بعض المنتجات للبدء.</td>
                </tr>
            @else
                @php 
                    $total_price = 0;
                    $total_tax = [];
                    $grand_total = 0;
                @endphp

                @foreach($cartItems as $item)
                    @php 
                        $tax = $item->tax;
                        $item_total = $item->price * $item->quantity;
                        $tax_amount = ($item_total * $tax) / 100;
                        $item_total_with_tax = $item_total + $tax_amount;
                        $total_price += $item_total;
                        $total_tax[$tax] = ($total_tax[$tax] ?? 0) + $tax_amount;
                        $grand_total += $item_total_with_tax;
                    @endphp
                    <livewire:cart-item :cartItem="$item" :currency_symbol="$currency_symbol" :key="$item->id" />
                @endforeach
                
                <tr class="border-gray-400 border">
                    <td colspan="3" class="px-4 py-2 border-l text-left font-semibold">المجموع الفرعي</td>
                    <td colspan="2" class="px-4 py-2 text-center font-semibold">{{ $currency_symbol }}{{ number_format($total_price, 2) }}</td>
                </tr>

                @foreach ($total_tax as $rate => $amount)
                    <tr class="border-gray-400 border">
                        <td colspan="3" class="px-4 py-2 border-l text-left font-semibold">ضريبة القيمة المضافة @ {{ $rate }}%</td>
                        <td colspan="2" class="px-4 py-2 text-center font-semibold">{{ $currency_symbol }}{{ number_format($amount, 2) }}</td>
                    </tr>
                @endforeach

                <tr class="bg-gray-100 border-gray-400 border">
                    <td colspan="3" class="px-4 py-2 border-l text-left font-bold text-lg">المجموع الكلي</td>
                    <td colspan="2" class="px-4 py-2 text-center font-bold text-lg text-green-600">{{ $currency_symbol }}{{ number_format($grand_total, 2) }}</td>
                </tr>
 
            @endif
            </tbody>
        </table>
    </div>

    <button wire:click="checkout" wire:loading.attr="disabled" class="bg-green-600 rounded text-white px-6 py-2 mt-3 hover:bg-green-700 font-medium transition-colors">
        <span wire:loading.remove wire:target='checkout'>حفظ وتأكيد الطلب</span>
        <span wire:loading wire:target='checkout' class="w-4 h-4 border-2 border-t-red-100 border-transparent rounded-full animate-spin"></span>
    </button>
</div>