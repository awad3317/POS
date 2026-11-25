@extends('layouts.app')
@section('title', 'تفاصيل الفاتورة')
@section('header', 'فاتورة رقم: ' . $order->invoice_number)
@section('breadcrumb', 'الرئيسية / الفواتير والمرتجعات / ' . $order->invoice_number)

@section('content')
@php $sym = $store->currency_symbol; @endphp

<div class="space-y-5">

    <div class="flex items-center justify-between gap-3 print:hidden">
        <a href="{{ route('orders.index') }}"
           class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-2xs transition hover:bg-gray-50">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
            <span>العودة لسجل الفواتير</span>
        </a>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Invoice Details --}}
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs lg:col-span-2">
            <div class="grid grid-cols-2 gap-4 border-b border-gray-100 bg-gray-50/60 px-5 py-4 text-xs sm:grid-cols-4 sm:px-6">
                <div>
                    <div class="text-[11px] text-gray-400">العميل</div>
                    <div class="mt-0.5 font-bold text-gray-900">{{ $order->customer?->name ?? 'عميل نقدي' }}</div>
                </div>
                <div>
                    <div class="text-[11px] text-gray-400">الكاشير</div>
                    <div class="mt-0.5 font-bold text-gray-900">{{ $order->user?->name }}</div>
                </div>
                <div>
                    <div class="text-[11px] text-gray-400">الحالة</div>
                    <div class="mt-0.5 font-bold text-gray-900">{{ $order->status }}</div>
                </div>
                <div>
                    <div class="text-[11px] text-gray-400">التاريخ</div>
                    <div class="mt-0.5 font-bold text-gray-900 tabular-nums" dir="ltr">{{ $order->sold_at?->format('Y-m-d H:i') }}</div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-right text-xs sm:text-[13px]">
                    <thead class="border-b border-gray-100 bg-gray-50/80 text-[11px] font-semibold text-gray-500">
                        <tr>
                            <th class="whitespace-nowrap px-5 py-3">الصنف</th>
                            <th class="whitespace-nowrap px-4 py-3">الوحدة</th>
                            <th class="whitespace-nowrap px-4 py-3">الكمية</th>
                            <th class="whitespace-nowrap px-4 py-3">السعر</th>
                            <th class="whitespace-nowrap px-4 py-3">الخصم</th>
                            <th class="whitespace-nowrap px-5 py-3 text-left">الصافي</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($order->items as $it)
                            <tr class="hover:bg-gray-50/60">
                                <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $it->product_name }}</td>
                                <td class="whitespace-nowrap px-4 py-3.5 text-gray-600">{{ $it->unit_name_snapshot }}</td>
                                <td class="whitespace-nowrap px-4 py-3.5 font-semibold text-gray-800 tabular-nums">{{ format_qty($it->quantity) }}</td>
                                <td class="whitespace-nowrap px-4 py-3.5 text-gray-700 tabular-nums">{{ format_money($it->unit_price, $store->decimal_places) }}</td>
                                <td class="whitespace-nowrap px-4 py-3.5 font-medium text-rose-600 tabular-nums">{{ format_money($it->discount_amount, $store->decimal_places) }}</td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-left font-bold text-gray-900 tabular-nums">
                                    {{ format_money($it->net_total, $store->decimal_places) }} {{ $sym }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 bg-gray-50/50 px-5 py-4 sm:px-6">
                <div class="text-sm font-bold text-gray-800">
                    <span>صافي الفاتورة النهائي:</span>
                    <span class="mr-2 text-lg font-extrabold text-emerald-700 tabular-nums">
                        {{ format_money($order->total_amount, $store->decimal_places) }} {{ $sym }}
                    </span>
                </div>

                <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-emerald-700 print:hidden">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                    <span>إرسال الفاتورة عبر واتساب</span>
                </a>
            </div>
        </section>

        {{-- Return Form (Exact Original Logic) --}}
        @if($order->type === 'sale' && $order->status !== 'refunded')
            @can('pos.return')
            <section class="overflow-hidden rounded-2xl border border-rose-200 bg-white shadow-xs print:hidden">
                <div class="border-b border-rose-100 bg-rose-50/60 px-5 py-4">
                    <h3 class="text-sm font-bold text-rose-800">إصدار سند مرتجع مبيعات</h3>
                </div>

                <form method="POST" action="{{ route('orders.return', $order->id) }}" class="space-y-3.5 p-5 text-xs">
                    @csrf

                    @foreach($order->items as $idx => $it)
                        <div class="space-y-2 rounded-xl border border-gray-200 bg-gray-50/70 p-3">
                            <div class="font-semibold text-gray-900">
                                {{ $it->product_name }}
                                <span class="text-gray-500">(المباع: {{ format_qty($it->quantity) }})</span>
                            </div>
                            <input type="hidden" name="items[{{ $idx }}][order_item_id]" value="{{ $it->id }}">
                            <input type="hidden" name="items[{{ $idx }}][product_id]" value="{{ $it->product_id }}">
                            <div class="grid grid-cols-2 gap-2">
                                <input type="number" step="any" min="0" max="{{ format_qty($it->quantity) }}"
                                       name="items[{{ $idx }}][quantity]" value="0"
                                       placeholder="كمية الإرجاع"
                                       class="h-9 w-full rounded-lg border border-gray-300 bg-white px-2.5 text-xs text-gray-900 focus:border-rose-500 focus:outline-none">
                                <select name="items[{{ $idx }}][return_condition]"
                                        class="h-9 w-full rounded-lg border border-gray-300 bg-white px-2 text-xs text-gray-800 focus:border-rose-500 focus:outline-none">
                                    <option value="restock">سليم (يعود للمخزن)</option>
                                    <option value="damaged">تالف (لا يضاف للمخزن)</option>
                                </select>
                            </div>
                        </div>
                    @endforeach

                    <div>
                        <label class="mb-1.5 block font-semibold text-gray-700">طريقة رد المبلغ</label>
                        <select name="refund_method"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-800 focus:border-rose-500 focus:outline-none">
                            <option value="cash">نقدي من درج الوردية</option>
                            <option value="credit">خصم من دين العميل الآجل</option>
                            <option value="kuraimi">حوالة الكريمي</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block font-semibold text-gray-700">الوردية المفتوحة</label>
                        <select name="shift_id"
                                class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-800 focus:border-rose-500 focus:outline-none">
                            <option value="{{ $order->shift_id }}">نفس وردية الفاتورة (#{{ $order->shift_id }})</option>
                            @foreach($openShifts as $sh)
                                <option value="{{ $sh->id }}">{{ $sh->terminal?->name }} (#{{ $sh->id }})</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit"
                            class="h-10 w-full rounded-lg bg-rose-600 text-xs font-bold text-white shadow-xs transition hover:bg-rose-700">
                        اعتماد سند المرتجع
                    </button>
                </form>
            </section>
            @endcan
        @endif

    </div>
</div>
@endsection