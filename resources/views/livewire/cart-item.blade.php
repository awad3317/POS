<tr class="odd:bg-white even:bg-gray-100" dir="rtl">
    @if($cartItem)
    <td class="px-2 py-1 border-l text-right dark:text-gray-800 whitespace-normal break-words font-medium">{{ $cartItem->name }}</td>
    <td class="px-2 py-1 border-l text-center dark:text-gray-800">{{ number_format($cartItem->price, 2) }}</td>
    
    <!-- 👈 حقل الخصم المباشر المربوط بـ Livewire Component -->
    <td class="px-2 py-1 border-l text-center dark:text-gray-800">
        <div class="w-24 mx-auto">
            <input type="number" step="0.01" min="0" wire:model.live.debounce.500ms="discount" 
                class="p-1 bg-white text-center block w-full text-sm text-red-600 border border-red-300 font-semibold rounded-md focus:ring-red-500 focus:border-red-500 dark:border-gray-600" 
                placeholder="0.00" />
        </div>
    </td>

    <td class="px-2 py-1 border-l text-center dark:text-gray-800">
        <div class="flex items-center gap-1 dark:text-gray-800 w-32 mx-auto">
            <input type="number" min="1" wire:model.live.debounce.250ms="quantity" class="p-1 bg-white text-center block w-full text-sm text-gray-900 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 dark:border-gray-600" />
            
            <button wire:click="removeFromCart" wire:loading.attr="disabled" class="p-2 text-white bg-red-500 rounded hover:bg-red-600 transition-colors" title="حذف المنتج">
                <svg wire:loading.remove wire:target='removeFromCart' xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                <span wire:loading wire:target='removeFromCart' class="w-4 h-4 border-2 border-t-red-100 border-transparent rounded-full animate-spin"></span>
            </button>
        </div>
    </td>

    @php
        $current_discount = $cartItem->discount ?? 0;
        $item_total_with_discount = ($cartItem->price - $current_discount) * $cartItem->quantity;
    @endphp
    <td class="px-2 py-1 text-center font-semibold text-gray-900">{{ number_format($item_total_with_discount, 2, '.', '') }}</td>
    @endif
</tr>