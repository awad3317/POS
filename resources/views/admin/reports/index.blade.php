@extends('layouts.app')
@section('title', 'التقارير المالية وصافي الربح')
@section('header', 'التقارير المالية الحقيقية وصافي الربح والموقف المالي')

@section('content')
@php
    $sym = $store->currency_symbol;
    $totalExpensesAndSalaries = \App\Support\MoneyMath::add($metrics['expenses_total'], $metrics['accrued_salaries']);
@endphp

<div class="space-y-6">

    <!-- Financial KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <!-- Net Sales -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-xs p-5 flex flex-col justify-between gap-4">
            <div class="flex items-center justify-between gap-3">
                <span class="text-xs font-semibold text-gray-500">صافي المبيعات (بعد المرتجعات)</span>
                <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 3v18h18" />
                        <path d="m19 9-5 5-4-4-3 3" />
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-extrabold text-slate-900 tabular-nums tracking-tight">
                {{ format_money($metrics['net_sales'], $store->decimal_places) }}
                <span class="text-xs font-semibold text-gray-400">{{ $sym }}</span>
            </div>
        </div>

        <!-- COGS -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-xs p-5 flex flex-col justify-between gap-4">
            <div class="flex items-center justify-between gap-3">
                <span class="text-xs font-semibold text-gray-500">تكلفة البضاعة المباعة (COGS وقت البيع)</span>
                <div class="w-10 h-10 rounded-xl bg-amber-50 border border-amber-200/70 text-amber-700 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m7.5 4.27 9 5.15" />
                        <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z" />
                        <path d="m3.3 7 8.7 5 8.7-5" />
                        <path d="M12 22V12" />
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-extrabold text-amber-700 tabular-nums tracking-tight">
                {{ format_money($metrics['net_cogs'], $store->decimal_places) }}
                <span class="text-xs font-semibold text-amber-600/75">{{ $sym }}</span>
            </div>
        </div>

        <!-- Expenses + Accrued Salaries -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-xs p-5 flex flex-col justify-between gap-4">
            <div class="flex items-center justify-between gap-3">
                <span class="text-xs font-semibold text-gray-500">المصروفات + استحقاقات الرواتب</span>
                <div class="w-10 h-10 rounded-xl bg-rose-50 border border-rose-200/70 text-rose-600 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z" />
                        <path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8" />
                        <path d="M12 17.5v-11" />
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-extrabold text-rose-600 tabular-nums tracking-tight">
                {{ format_money($totalExpensesAndSalaries, $store->decimal_places) }}
                <span class="text-xs font-semibold text-rose-500/80">{{ $sym }}</span>
            </div>
        </div>

        <!-- Net Profit -->
        <div class="rounded-2xl border border-emerald-600 bg-emerald-600 text-white shadow-xs p-5 flex flex-col justify-between gap-4">
            <div class="flex items-center justify-between gap-3">
                <span class="text-xs font-semibold text-emerald-50">صافي الربح الفعلي</span>
                <div class="w-10 h-10 rounded-xl bg-white/15 text-white flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-extrabold text-white tabular-nums tracking-tight">
                {{ format_money($metrics['net_profit'], $store->decimal_places) }}
                <span class="text-xs font-semibold text-emerald-100">{{ $sym }}</span>
            </div>
        </div>
    </div>

    <!-- Inventory Alerts & Low Stock Lists -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

        <!-- Negative Stock Alerts Card -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-xs overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-xl bg-rose-50 border border-rose-200/70 text-rose-600 flex items-center justify-center shrink-0">
                        <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
                            <path d="M12 9v4" />
                            <path d="M12 17h.01" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-sm font-bold text-slate-900 truncate">تنبيهات المخزون السالب</h3>
                        <p class="text-[11px] text-gray-500 mt-0.5">تعارضات البيع الأوفلاين التي تتطلب مراجعة</p>
                    </div>
                </div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold shrink-0 tabular-nums {{ $negativeAlerts->count() > 0 ? 'bg-rose-50 text-rose-700 border border-rose-200/70' : 'bg-gray-100 text-gray-600' }}">
                    {{ $negativeAlerts->count() }} تنبيه
                </span>
            </div>

            <div class="divide-y divide-gray-100 px-5 text-xs">
                @forelse ($negativeAlerts as $al)
                    <div class="py-3.5 flex items-center justify-between gap-3">
                        <span class="font-semibold text-slate-800 truncate">
                            {{ $al->product?->name }}
                        </span>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200/70 shrink-0 tabular-nums">
                            <span>الرصيد:</span>
                            <span>{{ format_qty($al->balance_after) }}</span>
                        </span>
                    </div>
                @empty
                    <div class="py-12 text-center">
                        <div class="w-11 h-11 rounded-2xl bg-emerald-50 text-emerald-600 mx-auto mb-3 flex items-center justify-center">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 6 9 17l-5-5" />
                            </svg>
                        </div>
                        <p class="text-xs font-medium text-gray-500">لا توجد أي تنبيهات مخزون سالب.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Low Stock Products Card -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-xs overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-xl bg-amber-50 border border-amber-200/70 text-amber-700 flex items-center justify-center shrink-0">
                        <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z" />
                            <path d="M3 6h18" />
                            <path d="M16 10a4 4 0 0 1-8 0" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-sm font-bold text-slate-900 truncate">قائمة النواقص لطلبية المورد</h3>
                        <p class="text-[11px] text-gray-500 mt-0.5">الأصناف التي وصلت إلى حد إعادة الطلب أو أقل</p>
                    </div>
                </div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold shrink-0 tabular-nums {{ $lowStockProducts->count() > 0 ? 'bg-amber-50 text-amber-800 border border-amber-200/70' : 'bg-gray-100 text-gray-600' }}">
                    {{ $lowStockProducts->count() }} صنف
                </span>
            </div>

            <div class="divide-y divide-gray-100 px-5 text-xs">
                @forelse ($lowStockProducts as $lp)
                    <div class="py-3.5 flex items-center justify-between gap-3">
                        <span class="font-semibold text-slate-800 truncate">
                            {{ $lp->name }}
                        </span>
                        <div class="flex items-center gap-2 shrink-0 tabular-nums">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-800 border border-amber-200/70">
                                <span>المتبقي:</span>
                                <span>{{ format_qty($lp->quantity) }}</span>
                            </span>
                            <span class="text-[11px] text-gray-500">
                                (الحد: {{ format_qty($lp->min_stock_alert) }})
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="py-12 text-center">
                        <div class="w-11 h-11 rounded-2xl bg-emerald-50 text-emerald-600 mx-auto mb-3 flex items-center justify-center">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 6 9 17l-5-5" />
                            </svg>
                        </div>
                        <p class="text-xs font-medium text-gray-500">جميع الأصناف فوق حد الطلب.</p>
                    </div>
                @endforelse
            </div>
        </div>

    </div>

</div>
@endsection