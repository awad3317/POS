@extends('layouts.app')
@section('title', 'الرئيسية')
@section('header', 'نظرة عامة على المتجر')
@section('breadcrumb', 'الرئيسية / لوحة التحكم')

@section('content')
@php
    $sym = $store->currency_symbol;
    $dp  = $store->decimal_places;
    $kpiCols = ($canViewProfit && $metrics) ? 'xl:grid-cols-4' : 'xl:grid-cols-3';
@endphp

<div class="space-y-6">

    {{-- Top Quick Actions Bar --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-xs font-medium text-gray-500">
                متجر <span class="font-bold text-gray-800">{{ $store->name }}</span>
                <span class="mx-1.5 text-gray-300">•</span>
                <span>{{ now()->format('Y-m-d') }}</span>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @can('products.manage')
            <a href="{{ route('products.create') }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-xs transition hover:border-gray-300 hover:bg-gray-50">
                <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                <span>إضافة منتج</span>
            </a>
            @endcan

            @can('suppliers.manage')
            <a href="{{ route('purchases.create') }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-xs transition hover:border-gray-300 hover:bg-gray-50">
                <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                </svg>
                <span>فاتورة مشتريات</span>
            </a>
            @endcan

            @can('reports.view_profit')
            <a href="{{ route('owner.dashboard') }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                <span>لوحة المالك السريعة</span>
            </a>
            @endcan
        </div>
    </div>

    {{-- Row 1: Primary KPI Cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 {{ $kpiCols }} sm:gap-5">

        {{-- KPI 1: Today Sales (Hero Emerald Card) --}}
        <a href="{{ route('orders.index') }}"
           class="group relative overflow-hidden rounded-2xl bg-gradient-to-bl from-emerald-600 to-emerald-700 p-5 text-white shadow-sm transition hover:shadow-md">
            <div class="pointer-events-none absolute -bottom-6 -left-6 h-28 w-28 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -top-8 left-12 h-20 w-20 rounded-full bg-white/5"></div>

            <div class="relative flex items-start justify-between gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/15 backdrop-blur-xs">
                    <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <span class="inline-flex items-center rounded-full bg-white/15 px-2.5 py-1 text-[11px] font-semibold text-emerald-50">
                    {{ $todayOrdersCount }} فاتورة
                </span>
            </div>

            <div class="relative mt-4">
                <div class="text-xs font-medium text-emerald-100">مبيعات اليوم</div>
                <div class="mt-1 flex items-baseline gap-1.5 tabular-nums">
                    <span class="text-2xl font-extrabold tracking-tight sm:text-3xl">
                        {{ format_money($todaySales, $dp) }}
                    </span>
                    <span class="text-xs font-semibold text-emerald-100">{{ $sym }}</span>
                </div>
            </div>
        </a>

        {{-- KPI 2: Open Shifts --}}
        <a href="{{ route('shifts.index') }}"
           class="group rounded-2xl border border-gray-200 bg-white p-5 shadow-xs transition hover:border-gray-300 hover:shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-sky-50 text-sky-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.85">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $openShifts->count() > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $openShifts->count() > 0 ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
                    {{ $openShifts->count() > 0 ? 'قيد العمل' : 'مغلقة' }}
                </span>
            </div>

            <div class="mt-4">
                <div class="text-xs font-medium text-gray-500">الورديات المفتوحة حالياً</div>
                <div class="mt-1 flex items-baseline justify-between">
                    <div class="flex items-baseline gap-1.5 tabular-nums">
                        <span class="text-2xl font-extrabold text-gray-900 sm:text-3xl">{{ $openShifts->count() }}</span>
                        <span class="text-xs font-medium text-gray-500">وردية</span>
                    </div>
                    <span class="text-[11px] font-semibold text-sky-600 opacity-0 transition group-hover:opacity-100">إدارة ←</span>
                </div>
            </div>
        </a>

        {{-- KPI 3: Low Stock Products --}}
        <a href="{{ auth()->user()?->can('products.manage') ? route('products.index', ['filter' => 'low_stock']) : '#' }}"
           class="group rounded-2xl border border-gray-200 bg-white p-5 shadow-xs transition hover:border-gray-300 hover:shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl {{ $lowStockCount > 0 ? 'bg-amber-50 text-amber-600' : 'bg-emerald-50 text-emerald-600' }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.85">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $lowStockCount > 0 ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' }}">
                    {{ $lowStockCount > 0 ? 'يحتاج تزويد' : 'المخزون آمن' }}
                </span>
            </div>

            <div class="mt-4">
                <div class="text-xs font-medium text-gray-500">أصناف وصلت حد النواقص</div>
                <div class="mt-1 flex items-baseline justify-between">
                    <div class="flex items-baseline gap-1.5 tabular-nums">
                        <span class="text-2xl font-extrabold {{ $lowStockCount > 0 ? 'text-amber-600' : 'text-gray-900' }} sm:text-3xl">
                            {{ $lowStockCount }}
                        </span>
                        <span class="text-xs font-medium text-gray-500">صنف</span>
                    </div>
                    @if($lowStockCount > 0)
                        <span class="text-[11px] font-semibold text-amber-600 opacity-0 transition group-hover:opacity-100">عرض النواقص ←</span>
                    @endif
                </div>
            </div>
        </a>

        {{-- KPI 4: Cumulative Net Profit (Executive Dark Card) --}}
        @if($canViewProfit && $metrics)
        <a href="{{ route('reports.index') }}"
           class="group relative overflow-hidden rounded-2xl bg-slate-900 p-5 text-white shadow-sm transition hover:bg-slate-800 hover:shadow-md">
            <div class="pointer-events-none absolute -bottom-6 -left-6 h-24 w-24 rounded-full bg-indigo-500/15"></div>

            <div class="relative flex items-start justify-between gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/10 text-emerald-400">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                </div>
                <span class="inline-flex items-center rounded-full bg-white/10 px-2.5 py-1 text-[11px] font-semibold text-slate-200">
                    التقارير ←
                </span>
            </div>

            <div class="relative mt-4">
                <div class="text-xs font-medium text-slate-400">صافي الربح التراكمي</div>
                <div class="mt-1 flex items-baseline gap-1.5 tabular-nums">
                    <span class="text-2xl font-extrabold tracking-tight sm:text-3xl">
                        {{ format_money($metrics['net_profit'], $dp) }}
                    </span>
                    <span class="text-xs font-semibold text-slate-400">{{ $sym }}</span>
                </div>
            </div>
        </a>
        @endif
    </div>

    {{-- Row 2: Live Financial Position Summary --}}
    @if($canViewProfit && $metrics)
    <section class="rounded-2xl border border-gray-200 bg-white shadow-xs">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 sm:px-6">
            <div>
                <h2 class="text-sm font-bold text-gray-900 sm:text-base">ملخص الموقف المالي اللحظي للمحل</h2>
                <p class="mt-0.5 text-xs text-gray-500">تجميع فوري للسيولة في الأدراج والذمم المدينة والدائنة ومستحقات العمال</p>
            </div>
            <a href="{{ route('reports.index') }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50">
                <span>التقرير المالي الكامل</span>
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
        </div>

        <div class="grid grid-cols-1 gap-3.5 p-5 sm:grid-cols-2 lg:grid-cols-5 sm:p-6">
            {{-- Cash in Drawers --}}
            <a href="{{ route('shifts.index') }}"
               class="flex flex-col justify-between rounded-xl border border-gray-100 bg-gray-50/70 p-4 transition hover:border-gray-200 hover:bg-gray-50">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-600">النقد في الأدراج</span>
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white text-gray-600 shadow-2xs">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </span>
                </div>
                <div class="mt-3 tabular-nums">
                    <div class="text-lg font-extrabold text-gray-900">{{ format_money($metrics['cash_in_drawers'], $dp) }} <span class="text-xs font-semibold text-gray-500">{{ $sym }}</span></div>
                    <div class="mt-0.5 text-[11px] text-gray-400">المتوقع بالورديات المفتوحة</div>
                </div>
            </a>

            {{-- Customer Receivables --}}
            <a href="{{ route('customers.index') }}"
               class="flex flex-col justify-between rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 transition hover:border-emerald-200 hover:bg-emerald-50">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-emerald-800">لنا عند العملاء</span>
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white text-emerald-600 shadow-2xs">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </span>
                </div>
                <div class="mt-3 tabular-nums">
                    <div class="text-lg font-extrabold text-emerald-800">{{ format_money($metrics['customer_receivables'], $dp) }} <span class="text-xs font-semibold text-emerald-600">{{ $sym }}</span></div>
                    <div class="mt-0.5 text-[11px] text-emerald-700/70">ديون ومبيعات آجلة</div>
                </div>
            </a>

            {{-- Supplier Payables --}}
            <a href="{{ auth()->user()?->can('suppliers.manage') ? route('suppliers.index') : '#' }}"
               class="flex flex-col justify-between rounded-xl border border-rose-100 bg-rose-50/50 p-4 transition hover:border-rose-200 hover:bg-rose-50">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-rose-800">علينا للموردين</span>
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white text-rose-600 shadow-2xs">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                        </svg>
                    </span>
                </div>
                <div class="mt-3 tabular-nums">
                    <div class="text-lg font-extrabold text-rose-800">{{ format_money($metrics['supplier_payables'], $dp) }} <span class="text-xs font-semibold text-rose-600">{{ $sym }}</span></div>
                    <div class="mt-0.5 text-[11px] text-rose-700/70">ذمم فواتير الشراء</div>
                </div>
            </a>

            {{-- Employee Net Payables --}}
            <a href="{{ auth()->user()?->can('employees.manage') ? route('employees.index') : '#' }}"
               class="flex flex-col justify-between rounded-xl border border-amber-100 bg-amber-50/50 p-4 transition hover:border-amber-200 hover:bg-amber-50">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-amber-800">مستحقات العمال</span>
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white text-amber-600 shadow-2xs">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0"/>
                        </svg>
                    </span>
                </div>
                <div class="mt-3 tabular-nums">
                    <div class="text-lg font-extrabold text-amber-800">{{ format_money($metrics['employee_net_payables'], $dp) }} <span class="text-xs font-semibold text-amber-600">{{ $sym }}</span></div>
                    <div class="mt-0.5 text-[11px] text-amber-700/70">صافي الرواتب والسلف</div>
                </div>
            </a>

            {{-- Net Financial Position --}}
            <div class="flex flex-col justify-between rounded-xl bg-slate-900 p-4 text-white sm:col-span-2 lg:col-span-1">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-300">صافي الموقف المالي</span>
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/10 text-emerald-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                        </svg>
                    </span>
                </div>
                <div class="mt-3 tabular-nums">
                    <div class="text-lg font-extrabold text-white">{{ format_money($metrics['net_position'], $dp) }} <span class="text-xs font-semibold text-slate-400">{{ $sym }}</span></div>
                    <div class="mt-0.5 text-[11px] text-slate-400">السيولة + لنا − علينا</div>
                </div>
            </div>
        </div>
    </section>
    @endif

    {{-- Row 3: Open Shifts Table --}}
    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 sm:px-6">
            <div class="flex items-center gap-2.5">
                <h2 class="text-sm font-bold text-gray-900 sm:text-base">الورديات المفتوحة الآن</h2>
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $openShifts->count() > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $openShifts->count() > 0 ? 'bg-emerald-500 animate-pulse' : 'bg-gray-400' }}"></span>
                    {{ $openShifts->count() }} نشطة
                </span>
            </div>

            <a href="{{ route('shifts.index') }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50">
                <span>كل الورديات والصناديق</span>
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
        </div>

        @if($openShifts->isEmpty())
            <div class="flex flex-col items-center justify-center px-6 py-12 text-center">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="mt-3 text-sm font-bold text-gray-900">لا توجد ورديات مفتوحة حالياً</h3>
                <p class="mt-1 max-w-sm text-xs text-gray-500">يمكنك فتح وردية جديدة للبدء بتسجيل المبيعات وحركات الصندوق من قسم الورديات أو مباشرة من شاشة الكاشير.</p>
                <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                    <a href="{{ route('shifts.index') }}"
                       class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2 text-xs font-bold text-white transition hover:bg-slate-800">
                        فتح وردية جديدة
                    </a>
                    @can('pos.sell')
                    <a href="{{ route('pos.index') }}"
                       class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-emerald-700">
                        افتح شاشة الكاشير
                    </a>
                    @endcan
                </div>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-right text-xs sm:text-[13px]">
                    <thead class="border-b border-gray-100 bg-gray-50/80 text-[11px] font-semibold text-gray-500">
                        <tr>
                            <th class="whitespace-nowrap px-5 py-3.5">الجهاز</th>
                            <th class="whitespace-nowrap px-5 py-3.5">الكاشير</th>
                            <th class="whitespace-nowrap px-5 py-3.5">وقت الفتح</th>
                            <th class="whitespace-nowrap px-5 py-3.5">النقد المتوقع بالدرج</th>
                            <th class="whitespace-nowrap px-5 py-3.5 text-left">الإجراء</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($openShifts as $sh)
                        @php
                            $cashierInitial = mb_substr(trim((string) ($sh->user?->name ?? 'ك')), 0, 1);
                        @endphp
                        <tr class="transition hover:bg-gray-50/70">
                            <td class="whitespace-nowrap px-5 py-3.5">
                                <div class="flex items-center gap-2.5">
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-xs font-bold text-emerald-700">
                                        {{ $sh->terminal?->code ?? 'T' }}
                                    </span>
                                    <div>
                                        <div class="font-semibold text-gray-900">{{ $sh->terminal?->name ?? 'جهاز كاشير' }}</div>
                                        <div class="text-[11px] text-gray-400">وردية #{{ $sh->id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-gray-100 text-[11px] font-bold text-gray-700">
                                        {{ $cashierInitial }}
                                    </span>
                                    <span class="font-medium text-gray-800">{{ $sh->user?->name ?? '—' }}</span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 text-gray-600 tabular-nums">
                                <span dir="ltr" class="inline-block">{{ $sh->opened_at?->format('Y-m-d H:i') }}</span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 tabular-nums">
                                <span class="inline-flex items-baseline gap-1 rounded-lg bg-emerald-50 px-2.5 py-1 font-bold text-emerald-700">
                                    {{ format_money($sh->expected_cash, $dp) }}
                                    <span class="text-[11px] font-medium">{{ $sym }}</span>
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 text-left">
                                <a href="{{ route('shifts.show', $sh->id) }}"
                                   class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700">
                                    <span>تقرير الصندوق</span>
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                                    </svg>
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

</div>
@endsection