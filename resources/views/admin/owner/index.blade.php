@extends('layouts.app')
@section('title', 'لوحة متابعة المالك السريعة')
@section('header', 'لوحة المالك السريعة')
@section('breadcrumb', 'الرئيسية / الموقف اللحظي والرقابة')

@section('content')
@php
    $sym = $store->currency_symbol;
    $dp  = $store->decimal_places;

    $salesNum  = max(0, (float) $todaySales);
    $cashPct   = $salesNum > 0 ? round(((float) $todayCashSales / $salesNum) * 100) : 0;
    $walletPct = $salesNum > 0 ? round(((float) $todayWalletSales / $salesNum) * 100) : 0;
    $creditPct = $salesNum > 0 ? max(0, 100 - $cashPct - $walletPct) : 0;

    $totalAlerts = $lowStockProducts->count() + $negativeAlerts->count() + $auditAlerts->count();
@endphp

<div class="space-y-6">

    {{-- Top Status & Quick Links --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500">
            <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 font-semibold text-emerald-700">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                مراقبة لحظية
            </span>
            <span>{{ now()->format('Y-m-d • H:i') }}</span>
            @if($totalAlerts > 0)
                <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-1 font-semibold text-rose-700 ring-1 ring-inset ring-rose-600/15">
                    {{ $totalAlerts }} تنبيه يحتاج مراجعة
                </span>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('reports.index') }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-xs transition hover:bg-gray-50">
                <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                <span>التقارير وصافي الربح</span>
            </a>
            <button type="button" onclick="window.location.reload()"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-slate-800">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <span>تحديث الآن</span>
            </button>
        </div>
    </div>

    {{-- 1. Today's Sales Hero & Payment Breakdown --}}
    <section class="relative overflow-hidden rounded-2xl bg-slate-900 p-5 text-white shadow-sm sm:p-6">
        <div class="pointer-events-none absolute -top-12 -left-12 h-44 w-44 rounded-full bg-emerald-500/10"></div>
        <div class="pointer-events-none absolute -bottom-16 left-28 h-36 w-36 rounded-full bg-sky-500/10"></div>

        <div class="relative flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs font-medium text-slate-400">
                    <span>إجمالي مبيعات اليوم</span>
                    <span class="rounded-full bg-white/10 px-2 py-0.5 text-[11px] font-semibold text-slate-200">
                        {{ $todayOrdersCount }} فاتورة
                    </span>
                </div>
                <div class="mt-2 flex items-baseline gap-2 tabular-nums">
                    <span class="text-3xl font-extrabold tracking-tight text-emerald-400 sm:text-4xl">
                        {{ format_money($todaySales, $dp) }}
                    </span>
                    <span class="text-sm font-semibold text-slate-400">{{ $sym }}</span>
                </div>
            </div>

            <a href="{{ route('orders.index') }}"
               class="inline-flex items-center gap-1.5 rounded-xl border border-white/15 bg-white/10 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-white/15">
                <span>سجل الفواتير</span>
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
        </div>

        {{-- Distribution Bar --}}
        @if($salesNum > 0)
        <div class="relative mt-5">
            <div class="flex h-2 w-full overflow-hidden rounded-full bg-slate-800">
                @if($cashPct > 0)
                    <div class="bg-emerald-500 transition-all" style="width: {{ $cashPct }}%" title="نقدي {{ $cashPct }}%"></div>
                @endif
                @if($walletPct > 0)
                    <div class="bg-sky-400 transition-all" style="width: {{ $walletPct }}%" title="محافظ وبنوك {{ $walletPct }}%"></div>
                @endif
                @if($creditPct > 0)
                    <div class="bg-amber-400 transition-all" style="width: {{ $creditPct }}%" title="آجل {{ $creditPct }}%"></div>
                @endif
            </div>
        </div>
        @endif

        {{-- 3 Breakdown Tiles --}}
        <div class="relative mt-5 grid grid-cols-1 gap-3 border-t border-white/10 pt-5 sm:grid-cols-3">
            <div class="rounded-xl border border-white/10 bg-white/5 p-3.5">
                <div class="flex items-center justify-between text-xs">
                    <span class="flex items-center gap-2 text-slate-300">
                        <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                        مبيعات نقدية
                    </span>
                    <span class="font-semibold text-emerald-300 tabular-nums">{{ $cashPct }}%</span>
                </div>
                <div class="mt-2 text-lg font-extrabold text-white tabular-nums">
                    {{ format_money($todayCashSales, $dp) }}
                    <span class="text-xs font-medium text-slate-400">{{ $sym }}</span>
                </div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-3.5">
                <div class="flex items-center justify-between text-xs">
                    <span class="flex items-center gap-2 text-slate-300">
                        <span class="h-2 w-2 rounded-full bg-sky-400"></span>
                        محافظ وبنوك
                    </span>
                    <span class="font-semibold text-sky-300 tabular-nums">{{ $walletPct }}%</span>
                </div>
                <div class="mt-2 text-lg font-extrabold text-white tabular-nums">
                    {{ format_money($todayWalletSales, $dp) }}
                    <span class="text-xs font-medium text-slate-400">{{ $sym }}</span>
                </div>
            </div>

            <div class="rounded-xl border border-white/10 bg-white/5 p-3.5">
                <div class="flex items-center justify-between text-xs">
                    <span class="flex items-center gap-2 text-slate-300">
                        <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                        آجل (ذمم عملاء)
                    </span>
                    <span class="font-semibold text-amber-300 tabular-nums">{{ $creditPct }}%</span>
                </div>
                <div class="mt-2 text-lg font-extrabold text-white tabular-nums">
                    {{ format_money($todayCreditSales, $dp) }}
                    <span class="text-xs font-medium text-slate-400">{{ $sym }}</span>
                </div>
            </div>
        </div>
    </section>

    {{-- 2. Financial Position KPI Grid --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 sm:gap-5">
        {{-- Receivables --}}
        <a href="{{ route('customers.index') }}"
           class="group rounded-2xl border border-gray-200 bg-white p-5 shadow-xs transition hover:border-emerald-300 hover:shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700">ذمم مدينة</span>
            </div>
            <div class="mt-4">
                <div class="text-xs font-medium text-gray-500">لنا عند العملاء</div>
                <div class="mt-1 flex items-baseline gap-1.5 tabular-nums">
                    <span class="text-2xl font-extrabold text-emerald-700">{{ format_money($metrics['customer_receivables'], $dp) }}</span>
                    <span class="text-xs font-semibold text-gray-500">{{ $sym }}</span>
                </div>
            </div>
        </a>

        {{-- Payables --}}
        <a href="{{ auth()->user()?->can('suppliers.manage') ? route('suppliers.index') : '#' }}"
           class="group rounded-2xl border border-gray-200 bg-white p-5 shadow-xs transition hover:border-rose-300 hover:shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                    </svg>
                </div>
                <span class="rounded-full bg-rose-50 px-2.5 py-0.5 text-[11px] font-semibold text-rose-700">ذمم دائنة</span>
            </div>
            <div class="mt-4">
                <div class="text-xs font-medium text-gray-500">علينا للموردين</div>
                <div class="mt-1 flex items-baseline gap-1.5 tabular-nums">
                    <span class="text-2xl font-extrabold text-rose-700">{{ format_money($metrics['supplier_payables'], $dp) }}</span>
                    <span class="text-xs font-semibold text-gray-500">{{ $sym }}</span>
                </div>
            </div>
        </a>

        {{-- Month Employee Advances --}}
        <a href="{{ auth()->user()?->can('employees.manage') ? route('employees.index') : '#' }}"
           class="group rounded-2xl border border-gray-200 bg-white p-5 shadow-xs transition hover:border-amber-300 hover:shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0"/>
                    </svg>
                </div>
                <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-[11px] font-semibold text-amber-700">هذا الشهر</span>
            </div>
            <div class="mt-4">
                <div class="text-xs font-medium text-gray-500">سلف العمال هذا الشهر</div>
                <div class="mt-1 flex items-baseline gap-1.5 tabular-nums">
                    <span class="text-2xl font-extrabold text-amber-700">{{ format_money($monthEmployeeAdvances, $dp) }}</span>
                    <span class="text-xs font-semibold text-gray-500">{{ $sym }}</span>
                </div>
            </div>
        </a>

        {{-- Cumulative Net Profit --}}
        <a href="{{ route('reports.index') }}"
           class="group rounded-2xl bg-gradient-to-bl from-emerald-600 to-emerald-700 p-5 text-white shadow-sm transition hover:shadow-md">
            <div class="flex items-start justify-between gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/15 text-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                </div>
                <span class="rounded-full bg-white/15 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-50">تراكمي</span>
            </div>
            <div class="mt-4">
                <div class="text-xs font-medium text-emerald-100">صافي الربح التراكمي</div>
                <div class="mt-1 flex items-baseline gap-1.5 tabular-nums">
                    <span class="text-2xl font-extrabold">{{ format_money($metrics['net_profit'], $dp) }}</span>
                    <span class="text-xs font-semibold text-emerald-100">{{ $sym }}</span>
                </div>
            </div>
        </a>
    </div>

    {{-- 3. Open Cash Drawers --}}
    <section class="rounded-2xl border border-gray-200 bg-white shadow-xs">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 sm:px-6">
            <div>
                <h2 class="text-sm font-bold text-gray-900 sm:text-base">النقد المتوقع في الأدراج المفتوحة</h2>
                <p class="mt-0.5 text-xs text-gray-500">متابعة الصناديق النشطة حالياً عبر جميع أجهزة الكاشير</p>
            </div>
            <div class="inline-flex items-baseline gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-2 tabular-nums">
                <span class="text-xs font-medium text-emerald-700">الإجمالي:</span>
                <span class="text-base font-extrabold text-emerald-800">{{ format_money($metrics['cash_in_drawers'], $dp) }}</span>
                <span class="text-xs font-semibold text-emerald-700">{{ $sym }}</span>
            </div>
        </div>

        <div class="p-5 sm:p-6">
            @if($openShifts->isEmpty())
                <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-200 bg-gray-50/50 py-8 text-center">
                    <svg class="h-8 w-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="mt-2 text-xs font-medium text-gray-500">لا توجد ورديات مفتوحة حالياً.</p>
                </div>
            @else
                <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($openShifts as $sh)
                    <a href="{{ route('shifts.show', $sh->id) }}"
                       class="group flex items-center justify-between gap-3 rounded-xl border border-gray-200 bg-gray-50/60 p-4 transition hover:border-emerald-400 hover:bg-emerald-50/30">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-xs font-extrabold text-emerald-700 ring-1 ring-gray-200 group-hover:ring-emerald-300">
                                {{ $sh->terminal?->code ?? 'T' }}
                            </div>
                            <div class="min-w-0">
                                <div class="truncate text-sm font-bold text-gray-900">
                                    {{ $sh->terminal?->name ?? 'جهاز كاشير' }}
                                </div>
                                <div class="mt-0.5 truncate text-xs text-gray-500">
                                    {{ $sh->user?->name ?? '—' }}
                                    <span class="mx-1 text-gray-300">•</span>
                                    <span dir="ltr">{{ $sh->opened_at?->format('H:i') }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="shrink-0 text-left tabular-nums">
                            <div class="text-sm font-extrabold text-emerald-700">
                                {{ format_money($sh->expected_cash, $dp) }}
                            </div>
                            <div class="text-[11px] font-medium text-gray-400">{{ $sym }}</div>
                        </div>
                    </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- 4. Operational & Security Alerts (3 Columns) --}}
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">

        {{-- Alert Card 1: Low Stock --}}
        <section class="flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs">
            <div class="flex items-center justify-between gap-2 border-b border-gray-100 px-5 py-4">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                    </span>
                    <h3 class="text-sm font-bold text-gray-900">نواقص المخزون</h3>
                </div>
                <span class="rounded-full px-2.5 py-0.5 text-xs font-bold tabular-nums {{ $lowStockProducts->count() > 0 ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-500' }}">
                    {{ $lowStockProducts->count() }}
                </span>
            </div>

            <div class="max-h-72 flex-1 divide-y divide-gray-100 overflow-y-auto px-5">
                @forelse($lowStockProducts as $lp)
                <div class="flex items-center justify-between gap-3 py-3 text-xs">
                    <div class="min-w-0">
                        <div class="truncate font-semibold text-gray-800">{{ $lp->name }}</div>
                        <div class="mt-0.5 text-[11px] text-gray-400">حد التنبيه: {{ format_qty($lp->min_stock_alert) }}</div>
                    </div>
                    <span class="shrink-0 rounded-lg bg-amber-50 px-2.5 py-1 font-bold text-amber-700 tabular-nums">
                        {{ format_qty($lp->quantity) }}
                    </span>
                </div>
                @empty
                <div class="flex flex-col items-center justify-center py-10 text-center">
                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </span>
                    <p class="mt-2.5 text-xs font-medium text-gray-500">جميع الأصناف متوفرة بكمية آمنة.</p>
                </div>
                @endforelse
            </div>

            @if($lowStockProducts->count() > 0 && auth()->user()?->can('products.manage'))
            <div class="border-t border-gray-100 bg-gray-50/60 px-5 py-2.5 text-left">
                <a href="{{ route('products.index', ['filter' => 'low_stock']) }}" class="text-xs font-semibold text-amber-700 hover:text-amber-800">
                    عرض قائمة النواقص كاملة ←
                </a>
            </div>
            @endif
        </section>

        {{-- Alert Card 2: Negative Stock Conflicts --}}
        <section class="flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs">
            <div class="flex items-center justify-between gap-2 border-b border-gray-100 px-5 py-4">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </span>
                    <h3 class="text-sm font-bold text-gray-900">تنبيهات المخزون السالب</h3>
                </div>
                <span class="rounded-full px-2.5 py-0.5 text-xs font-bold tabular-nums {{ $negativeAlerts->count() > 0 ? 'bg-rose-50 text-rose-700' : 'bg-gray-100 text-gray-500' }}">
                    {{ $negativeAlerts->count() }}
                </span>
            </div>

            <div class="max-h-72 flex-1 divide-y divide-gray-100 overflow-y-auto px-5">
                @forelse($negativeAlerts as $na)
                <div class="flex items-center justify-between gap-3 py-3 text-xs">
                    <div class="min-w-0">
                        <div class="truncate font-semibold text-gray-800">{{ $na->product?->name ?? 'صنف' }}</div>
                        <div class="mt-0.5 text-[11px] text-gray-400">
                            بواسطة: {{ $na->user?->name ?? 'نظام' }}
                        </div>
                    </div>
                    <span class="shrink-0 rounded-lg bg-rose-50 px-2.5 py-1 font-bold text-rose-700 tabular-nums" dir="ltr">
                        {{ format_qty($na->balance_after) }}
                    </span>
                </div>
                @empty
                <div class="flex flex-col items-center justify-center py-10 text-center">
                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </span>
                    <p class="mt-2.5 text-xs font-medium text-gray-500">لا توجد تنبيهات مخزون سالب.</p>
                </div>
                @endforelse
            </div>

            @if($negativeAlerts->count() > 0 && auth()->user()?->can('products.manage'))
            <div class="border-t border-gray-100 bg-gray-50/60 px-5 py-2.5 text-left">
                <a href="{{ route('products.index', ['filter' => 'negative']) }}" class="text-xs font-semibold text-rose-700 hover:text-rose-800">
                    مراجعة الأصناف السالبة ←
                </a>
            </div>
            @endif
        </section>

        {{-- Alert Card 3: Audit & Security Events --}}
        <section class="flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs">
            <div class="flex items-center justify-between gap-2 border-b border-gray-100 px-5 py-4">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </span>
                    <h3 class="text-sm font-bold text-gray-900">الرقابة وتجاوزات الكاشير</h3>
                </div>
                <span class="rounded-full px-2.5 py-0.5 text-xs font-bold tabular-nums {{ $auditAlerts->count() > 0 ? 'bg-slate-900 text-white' : 'bg-gray-100 text-gray-500' }}">
                    {{ $auditAlerts->count() }}
                </span>
            </div>

            <div class="max-h-72 flex-1 divide-y divide-gray-100 overflow-y-auto px-5">
                @forelse($auditAlerts as $act)
                <div class="space-y-1.5 py-3 text-xs">
                    <div class="flex items-center justify-between gap-2">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-bold {{ $act->event === 'discount_override' ? 'bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-600/20' : 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20' }}">
                            {{ $act->event === 'discount_override' ? 'بيع بأقل من التكلفة' : 'حذف صنف من السلة' }}
                        </span>
                        <span class="text-[11px] text-gray-400 tabular-nums" dir="ltr">
                            {{ $act->created_at?->format('m-d H:i') }}
                        </span>
                    </div>
                    <p class="leading-relaxed text-gray-700">{{ $act->description }}</p>
                    <div class="text-[11px] text-gray-400">
                        بواسطة: <span class="font-medium text-gray-600">{{ $act->causer?->name ?? 'نظام' }}</span>
                    </div>
                </div>
                @empty
                <div class="flex flex-col items-center justify-center py-10 text-center">
                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </span>
                    <p class="mt-2.5 text-xs font-medium text-gray-500">لا توجد تنبيهات رقابية مسجلة.</p>
                </div>
                @endforelse
            </div>
        </section>

    </div>

</div>
@endsection