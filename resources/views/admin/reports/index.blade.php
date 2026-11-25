@extends('layouts.app')
@section('title', 'التقارير المالية وصافي الربح')
@section('header', 'التقارير المالية الحقيقية وصافي الربح والموقف المالي')
@section('content')
@php $sym = $store->currency_symbol; @endphp

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6 text-xs">
    <div class="bg-white rounded-2xl border p-4">
        <div class="text-slate-500">صافي المبيعات (بعد المرتجعات)</div>
        <div class="text-xl font-bold text-slate-900 mt-1">{{ format_money($metrics['net_sales'], $store->decimal_places) }} {{ $sym }}</div>
    </div>
    <div class="bg-white rounded-2xl border p-4">
        <div class="text-slate-500">تكلفة البضاعة المباعة (COGS وقت البيع)</div>
        <div class="text-xl font-bold text-amber-700 mt-1">{{ format_money($metrics['net_cogs'], $store->decimal_places) }} {{ $sym }}</div>
    </div>
    <div class="bg-white rounded-2xl border p-4">
        <div class="text-slate-500">المصروفات + استحقاقات الرواتب</div>
        <div class="text-xl font-bold text-rose-600 mt-1">{{ format_money(\App\Support\MoneyMath::add($metrics['expenses_total'], $metrics['accrued_salaries']), $store->decimal_places) }} {{ $sym }}</div>
    </div>
    <div class="bg-emerald-600 text-white rounded-2xl p-4">
        <div class="text-emerald-100">صافي الربح الفعلي</div>
        <div class="text-xl font-bold mt-1">{{ format_money($metrics['net_profit'], $store->decimal_places) }} {{ $sym }}</div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 text-xs">
    <div class="bg-white rounded-2xl border p-5">
        <h3 class="font-bold text-sm mb-3 text-rose-700">تنبيهات المخزون السالب (تعارضات البيع الأوفلاين)</h3>
        @forelse($negativeAlerts as $al)
            <div class="py-2 border-b flex justify-between">
                <span>{{ $al->product?->name }}</span>
                <span class="font-bold text-rose-600">الرصيد: {{ format_qty($al->balance_after) }}</span>
            </div>
        @empty
            <p class="text-slate-400">لا توجد أي تنبيهات مخزون سالب.</p>
        @endforelse
    </div>

    <div class="bg-white rounded-2xl border p-5">
        <h3 class="font-bold text-sm mb-3">قائمة النواقص لطلبية المورد</h3>
        @forelse($lowStockProducts as $lp)
            <div class="py-2 border-b flex justify-between">
                <span>{{ $lp->name }}</span>
                <span class="font-bold text-amber-700">المتبقي: {{ format_qty($lp->quantity) }} (الحد: {{ format_qty($lp->min_stock_alert) }})</span>
            </div>
        @empty
            <p class="text-slate-400">جميع الأصناف فوق حد الطلب.</p>
        @endforelse
    </div>
</div>
@endsection
