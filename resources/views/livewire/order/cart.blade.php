<div class="w-full" dir="rtl">
@if (session()->has('error'))
    <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-gray-800 dark:text-red-400 font-medium text-right" dir="rtl">
        {{ session('error') }}
    </div>
@endif

    <div class="overflow-x-auto md:overflow-x-none">
        <table class="min-w-[600px] min-w-full border border-gray-300 text-right">
            <thead>
                <tr class="bg-gray-200">
                    <th class="px-2 py-2 border border-gray-400 text-right w-2/5 dark:text-gray-800">المنتج</th>
                    <th class="px-2 py-2 border border-gray-400 text-center w-1/6 dark:text-gray-800">السعر</th>
                    <th class="px-2 py-2 border border-gray-400 text-center w-1/6 dark:text-gray-800">الخصم</th>
                    <th class="px-2 py-2 border border-gray-400 text-center w-1/6 dark:text-gray-800">الكمية</th>
                    <th class="px-2 py-2 border border-gray-400 text-center w-1/6 dark:text-gray-800">الإجمالي</th>
                </tr>
            </thead>
            <tbody>
            @if ( !is_countable($cartItems) || count($cartItems) < 1)
                <tr class="min-h-32">
                    <td colspan="5" class="p-4 text-center text-gray-500 font-medium">لا توجد منتجات في هذا الطلب حالياً.</td>
                </tr>
            @else
                @php 
                    $total_price = 0;
                    $total_discount = 0;
                    $grand_total = 0;
                @endphp
                
                @foreach ($cartItems as $item) 
                @php 
                    $discount = $item->discount ?? 0; 
                    $item_total = $item->price * $item->quantity;
                    $item_total_after_discount = ($item->price - $discount) * $item->quantity;
                    
                    $total_price += $item_total;
                    $total_discount += ($discount * $item->quantity);
                    $grand_total += $item_total_after_discount;
                @endphp
                    <livewire:order.cart-item :cartItem="$item" :currency_symbol="$currency_symbol" :order-id="$orderId" :key="$item->id" />
                @endforeach
                
                <tr class="border-gray-400 border">
                    <td colspan="3" class="px-4 py-2 border-l text-left font-semibold">المجموع الإجمالي (قبل الخصم)</td>
                    <td colspan="2" class="px-4 py-2 text-center font-semibold">{{ $currency_symbol }}{{ number_format($total_price, 2) }}</td>
                </tr>

                @if ($total_discount > 0)
                    <tr class="border-gray-400 border text-red-600">
                        <td colspan="3" class="px-4 py-2 border-l text-left font-semibold">إجمالي الخصومات الممنوحة</td>
                        <td colspan="2" class="px-4 py-2 text-center font-semibold">- {{ $currency_symbol }}{{ number_format($total_discount, 2) }}</td>
                    </tr>
                @endif

                <tr class="bg-gray-100 border-gray-400 border">
                    <td colspan="3" class="px-4 py-2 border-l text-left font-bold text-lg">المجموع الصافي المطالب به</td>
                    <td colspan="2" class="px-4 py-2 text-center font-bold text-lg text-green-600">{{ $currency_symbol }}{{ number_format($grand_total, 2) }}</td>
                </tr>

            @endif
            </tbody>
        </table>
    </div>

    <button wire:click="checkout" class="bg-green-500 rounded text-white px-6 py-2 mt-3 hover:bg-green-600 transition-colors flex items-center gap-2" title="تأكيد والرجوع">
        <svg class="size-6 h-6 w-6 transform rotate-180" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
        </svg>              
        <span class="font-medium">تأكيد وتحديث الطلب</span>
    </button>

</div>