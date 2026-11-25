@extends('layouts.app')
@section('title', 'تفاصيل الفاتورة')
@section('header', 'فاتورة رقم: ' . $order->invoice_number)
@section('content')
@php $sym = $store->currency_symbol; @endphp

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-5 space-y-4">
        <div class="flex flex-wrap justify-between text-xs border-b pb-3">
            <div>العميل: <strong>{{ $order->customer?->name ?? 'عميل نقدي' }}</strong></div>
            <div>الكاشير: <strong>{{ $order->user?->name }}</strong></div>
            <div>الحالة: <strong>{{ $order->status }}</strong></div>
            <div>التاريخ: <strong>{{ $order->sold_at?->format('Y-m-d H:i') }}</strong></div>
        </div>

        <table class="w-full text-xs text-right">
            <thead class="bg-slate-50 border-b">
                <tr>
                    <th class="p-2.5">الصنف</th>
                    <th class="p-2.5">الوحدة</th>
                    <th class="p-2.5">الكمية</th>
                    <th class="p-2.5">السعر</th>
                    <th class="p-2.5">الخصم</th>
                    <th class="p-2.5">الصافي</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($order->items as $it)
                <tr>
                    <td class="p-2.5 font-semibold">{{ $it->product_name }}</td>
                    <td class="p-2.5">{{ $it->unit_name_snapshot }}</td>
                    <td class="p-2.5">{{ format_qty($it->quantity) }}</td>
                    <td class="p-2.5">{{ format_money($it->unit_price, $store->decimal_places) }}</td>
                    <td class="p-2.5 text-rose-600">{{ format_money($it->discount_amount, $store->decimal_places) }}</td>
                    <td class="p-2.5 font-bold">{{ format_money($it->net_total, $store->decimal_places) }} {{ $sym }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="border-t pt-3 flex flex-wrap items-center justify-between gap-2 text-sm font-bold">
            <div>
                <span>صافي الفاتورة النهائي:</span>
                <span class="text-emerald-700 mr-2">{{ format_money($order->total_amount, $store->decimal_places) }} {{ $sym }}</span>
            </div>
            <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer"
               class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold">
                <span>📱 إرسال الفاتورة عبر واتساب</span>
            </a>
        </div>
    </div>

    @if($order->type === 'sale' && $order->status !== 'refunded')
    @can('pos.return')
    <div class="bg-white rounded-2xl border border-slate-200 p-5">
        <h3 class="font-bold text-sm mb-3 text-rose-700">إصدار سند مرتجع مبيعات</h3>
        <form method="POST" action="{{ route('orders.return', $order->id) }}" class="space-y-3 text-xs">
            @csrf
            @foreach($order->items as $idx => $it)
            <div class="p-2.5 rounded-xl bg-slate-50 space-y-1.5">
                <div class="font-semibold">{{ $it->product_name }} (المباع: {{ format_qty($it->quantity) }})</div>
                <input type="hidden" name="items[{{ $idx }}][order_item_id]" value="{{ $it->id }}">
                <input type="hidden" name="items[{{ $idx }}][product_id]" value="{{ $it->product_id }}">
                <div class="grid grid-cols-2 gap-2">
                    <input type="number" step="any" min="0" max="{{ format_qty($it->quantity) }}" name="items[{{ $idx }}][quantity]" value="0" class="border bg-white rounded-lg px-2 py-1" placeholder="كمية الإرجاع">
                    <select name="items[{{ $idx }}][return_condition]" class="border bg-white rounded-lg px-2 py-1">
                        <option value="restock">سليم (يعود للمخزن)</option>
                        <option value="damaged">تالف (لا يضاف للمخزن)</option>
                    </select>
                </div>
            </div>
            @endforeach

            <div>
                <label class="block font-semibold mb-1">طريقة رد المبلغ</label>
                <select name="refund_method" class="w-full border rounded-xl px-3 py-2">
                    <option value="cash">نقدي من درج الوردية</option>
                    <option value="credit">خصم من دين العميل الآجل</option>
                    <option value="kuraimi">حوالة الكريمي</option>
                </select>
            </div>

            <div>
                <label class="block font-semibold mb-1">الوردية المفتوحة</label>
                <select name="shift_id" class="w-full border rounded-xl px-3 py-2">
                    <option value="{{ $order->shift_id }}">نفس وردية الفاتورة (#{{ $order->shift_id }})</option>
                    @foreach($openShifts as $sh)
                        <option value="{{ $sh->id }}">{{ $sh->terminal?->name }} (#{{ $sh->id }})</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="w-full py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold">اعتماد سند المرتجع</button>
        </form>
    </div>
    @endcan
    @endif
</div>
@endsection
