@extends('layouts.app')
@section('title', 'الرئيسية')
@section('header', 'نظرة عامة على المتجر')
@section('content')
@php $sym = $store->currency_symbol; @endphp

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-2xl border border-slate-200 p-4">
        <div class="text-xs text-slate-500">مبيعات اليوم ({{ $todayOrdersCount }} فاتورة)</div>
        <div class="text-2xl font-bold text-emerald-600 mt-1">{{ format_money($todaySales, $store->decimal_places) }} {{ $sym }}</div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 p-4">
        <div class="text-xs text-slate-500">الورديات المفتوحة حالياً</div>
        <div class="text-2xl font-bold text-slate-900 mt-1">{{ $openShifts->count() }}</div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 p-4">
        <div class="text-xs text-slate-500">أصناف وصلت حد النواقص</div>
        <div class="text-2xl font-bold {{ $lowStockCount > 0 ? 'text-amber-600' : 'text-slate-900' }} mt-1">{{ $lowStockCount }}</div>
    </div>
    @if($canViewProfit && $metrics)
    <div class="bg-white rounded-2xl border border-slate-200 p-4">
        <div class="text-xs text-slate-500">صافي الربح التراكمي</div>
        <div class="text-2xl font-bold text-indigo-600 mt-1">{{ format_money($metrics['net_profit'], $store->decimal_places) }} {{ $sym }}</div>
    </div>
    @endif
</div>

@if($canViewProfit && $metrics)
<div class="bg-white rounded-2xl border border-slate-200 p-5 mb-6">
    <h2 class="text-sm font-bold text-slate-800 mb-3">ملخص الموقف المالي اللحظي للمحل</h2>
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 text-sm">
        <div class="p-3 rounded-xl bg-slate-50">
            <div class="text-xs text-slate-500">النقد المتوقع في الأدراج</div>
            <div class="font-bold mt-1">{{ format_money($metrics['cash_in_drawers'], $store->decimal_places) }} {{ $sym }}</div>
        </div>
        <div class="p-3 rounded-xl bg-emerald-50/60">
            <div class="text-xs text-emerald-700">لنا عند العملاء (ديون)</div>
            <div class="font-bold text-emerald-800 mt-1">{{ format_money($metrics['customer_receivables'], $store->decimal_places) }} {{ $sym }}</div>
        </div>
        <div class="p-3 rounded-xl bg-rose-50/60">
            <div class="text-xs text-rose-700">علينا للموردين</div>
            <div class="font-bold text-rose-800 mt-1">{{ format_money($metrics['supplier_payables'], $store->decimal_places) }} {{ $sym }}</div>
        </div>
        <div class="p-3 rounded-xl bg-amber-50/60">
            <div class="text-xs text-amber-700">صافي مستحقات العمال</div>
            <div class="font-bold text-amber-800 mt-1">{{ format_money($metrics['employee_net_payables'], $store->decimal_places) }} {{ $sym }}</div>
        </div>
        <div class="p-3 rounded-xl bg-slate-900 text-white col-span-2 lg:col-span-1">
            <div class="text-xs text-slate-300">صافي الموقف المالي</div>
            <div class="font-bold mt-1">{{ format_money($metrics['net_position'], $store->decimal_places) }} {{ $sym }}</div>
        </div>
    </div>
</div>
@endif

<div class="bg-white rounded-2xl border border-slate-200 p-5">
    <h2 class="text-sm font-bold text-slate-800 mb-3">الورديات المفتوحة الآن</h2>
    @if($openShifts->isEmpty())
        <p class="text-xs text-slate-500">لا توجد ورديات مفتوحة حالياً. يمكنك فتح وردية من قسم الورديات أو من شاشة الكاشير.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-right">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="p-2.5">الجهاز</th>
                        <th class="p-2.5">الكاشير</th>
                        <th class="p-2.5">وقت الفتح</th>
                        <th class="p-2.5">النقد المتوقع بالدرج</th>
                        <th class="p-2.5">تفاصيل</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($openShifts as $sh)
                    <tr>
                        <td class="p-2.5 font-semibold">{{ $sh->terminal?->name }}</td>
                        <td class="p-2.5">{{ $sh->user?->name }}</td>
                        <td class="p-2.5">{{ $sh->opened_at?->format('Y-m-d H:i') }}</td>
                        <td class="p-2.5 font-bold text-emerald-700">{{ format_money($sh->expected_cash, $store->decimal_places) }} {{ $sym }}</td>
                        <td class="p-2.5"><a href="{{ route('shifts.show', $sh->id) }}" class="text-emerald-600 font-semibold">عرض</a></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
