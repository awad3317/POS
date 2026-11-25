@extends('layouts.app')
@section('title', 'تقرير الوردية Z-Report')
@section('header', 'تقرير جرد الوردية #' . $shift->id)
@section('content')
@php $sym = $store->currency_symbol; @endphp

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-6 space-y-3 text-xs">
        <div class="flex justify-between border-b pb-3">
            <span>الكاشير: <strong>{{ $shift->user?->name }}</strong></span>
            <span>الجهاز: <strong>{{ $shift->terminal?->name }}</strong></span>
            <span>الحالة: <strong>{{ $shift->status === 'open' ? 'مفتوحة' : 'مغلقة' }}</strong></span>
        </div>

        <div class="space-y-2 text-sm">
            <div class="flex justify-between"><span>عهدة البداية (Opening Cash):</span><strong>{{ format_money($shift->opening_cash, $store->decimal_places) }} {{ $sym }}</strong></div>
            <div class="flex justify-between text-emerald-700"><span>+ صافي المبيعات النقدية:</span><strong>{{ format_money($shift->cash_sales_total, $store->decimal_places) }} {{ $sym }}</strong></div>
            <div class="flex justify-between text-emerald-700"><span>+ مقبوضات ديون العملاء نقداً:</span><strong>{{ format_money($shift->cash_in_total, $store->decimal_places) }} {{ $sym }}</strong></div>
            <div class="flex justify-between text-rose-600"><span>- مرتجعات المبيعات النقدية:</span><strong>{{ format_money($shift->cash_returns_total, $store->decimal_places) }} {{ $sym }}</strong></div>
            <div class="flex justify-between text-rose-600"><span>- مسحوبات الدرج (مصروفات + موردين + سلف عمال):</span><strong>{{ format_money($shift->cash_out_total, $store->decimal_places) }} {{ $sym }}</strong></div>
            <div class="flex justify-between border-t pt-2 text-base font-bold"><span>= النقد المتوقع في الدرج:</span><span>{{ format_money($shift->expected_cash, $store->decimal_places) }} {{ $sym }}</span></div>

            @if($shift->status === 'closed')
                <div class="flex justify-between"><span>النقد الفعلي المعدود:</span><strong>{{ format_money($shift->actual_cash, $store->decimal_places) }} {{ $sym }}</strong></div>
                <div class="flex justify-between font-bold {{ $shift->difference !== null && \App\Support\MoneyMath::isNegative($shift->difference) ? 'text-rose-600' : 'text-emerald-700' }}">
                    <span>الفارق (العجز / الزيادة):</span><span>{{ format_money($shift->difference, $store->decimal_places) }} {{ $sym }}</span>
                </div>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 p-5 space-y-4 text-xs">
        @if($shift->status === 'open')
            <h3 class="font-bold text-sm">إغلاق الوردية وجرد النقد</h3>
            <form method="POST" action="{{ route('shifts.close', $shift->id) }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block font-semibold mb-1">النقد الفعلي الموجود بالدرج *</label>
                    <input type="number" step="any" name="actual_cash" required class="w-full border rounded-xl px-3 py-2">
                </div>
                <input type="text" name="notes" placeholder="ملاحظات الإغلاق" class="w-full border rounded-xl px-3 py-2">
                <button type="submit" class="w-full py-2.5 rounded-xl bg-slate-900 text-white font-bold">إغلاق الوردية واعتماد الجرد</button>
            </form>
        @elseif($shift->difference !== null && \App\Support\MoneyMath::isNegative($shift->difference))
            @can('employees.manage')
            <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 space-y-3">
                <div class="font-bold text-rose-800">يوجد عجز نقدي بمقدار {{ format_money($shift->difference, $store->decimal_places) }} {{ $sym }}</div>
                <form method="POST" action="{{ route('shifts.transfer-shortage', $shift->id) }}">
                    @csrf
                    <button type="submit" class="w-full py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold">
                        ترحيل العجز لحساب الكاشير
                    </button>
                </form>
            </div>
            @endcan
        @endif
    </div>
</div>
@endsection
