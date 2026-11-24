@extends('layouts.app')
@section('title', 'لوحة متابعة المالك السريعة')
@section('header', 'لوحة المالك — الموقف اللحظي والرقابة')

@section('content')
@php
    $sym = $store->currency_symbol;
    $dp = $store->decimal_places;
@endphp

<div class="space-y-5 text-xs sm:text-sm">

    <!-- 1. Today's Sales Breakdown (Cash / Wallets / Credit) -->
    <div class="bg-slate-900 text-white rounded-2xl p-4 sm:p-5 shadow-sm">
        <div class="flex items-center justify-between gap-2 mb-3">
            <div>
                <div class="text-xs text-slate-400">مبيعات اليوم ({{ $todayOrdersCount }} فاتورة)</div>
                <div class="text-2xl sm:text-3xl font-extrabold text-emerald-400 mt-0.5">
                    {{ number_format((float) $todaySales, $dp) }} {{ $sym }}
                </div>
            </div>
            <span class="px-3 py-1 rounded-full bg-emerald-500/20 text-emerald-300 text-xs font-bold border border-emerald-500/30">
                تحديث لحظي
            </span>
        </div>

        <div class="grid grid-cols-3 gap-2 sm:gap-3 pt-3 border-t border-slate-800 text-xs">
            <div class="bg-slate-800/80 rounded-xl p-2.5">
                <div class="text-slate-400">نقدي</div>
                <div class="font-bold text-emerald-300 text-sm mt-0.5">{{ number_format((float) $todayCashSales, $dp) }} {{ $sym }}</div>
            </div>
            <div class="bg-slate-800/80 rounded-xl p-2.5">
                <div class="text-slate-400">محافظ وبنوك</div>
                <div class="font-bold text-sky-300 text-sm mt-0.5">{{ number_format((float) $todayWalletSales, $dp) }} {{ $sym }}</div>
            </div>
            <div class="bg-slate-800/80 rounded-xl p-2.5">
                <div class="text-slate-400">آجل (ذمم)</div>
                <div class="font-bold text-amber-300 text-sm mt-0.5">{{ number_format((float) $todayCreditSales, $dp) }} {{ $sym }}</div>
            </div>
        </div>
    </div>

    <!-- 2. Open Drawers & Shifts -->
    <div class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5">
        <div class="flex items-center justify-between mb-3">
            <h2 class="font-bold text-sm text-slate-900">النقد المتوقع في الأدراج المفتوحة</h2>
            <span class="font-extrabold text-emerald-700">
                الإجمالي: {{ number_format((float) $metrics['cash_in_drawers'], $dp) }} {{ $sym }}
            </span>
        </div>

        @if($openShifts->isEmpty())
            <p class="text-xs text-slate-400">لا توجد ورديات مفتوحة حالياً.</p>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
                @foreach($openShifts as $sh)
                <a href="{{ route('shifts.show', $sh->id) }}" class="p-3 rounded-xl border border-slate-200 hover:border-emerald-500 bg-slate-50/60 flex items-center justify-between">
                    <div>
                        <div class="font-bold text-slate-900">{{ $sh->terminal?->name ?? 'جهاز' }} ({{ $sh->terminal?->code }})</div>
                        <div class="text-slate-500">الكاشير: {{ $sh->user?->name }} • {{ $sh->opened_at?->format('H:i') }}</div>
                    </div>
                    <div class="text-left font-extrabold text-emerald-700 text-sm">
                        {{ number_format((float) $sh->expected_cash, $dp) }} {{ $sym }}
                    </div>
                </a>
                @endforeach
            </div>
        @endif
    </div>

    <!-- 3. Financial Position Summary -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
        <div class="bg-white rounded-2xl border border-emerald-200 p-4">
            <div class="text-emerald-700 font-semibold">لنا عند العملاء</div>
            <div class="text-lg font-extrabold text-emerald-800 mt-1">{{ number_format((float) $metrics['customer_receivables'], $dp) }} {{ $sym }}</div>
        </div>
        <div class="bg-white rounded-2xl border border-rose-200 p-4">
            <div class="text-rose-700 font-semibold">علينا للموردين</div>
            <div class="text-lg font-extrabold text-rose-800 mt-1">{{ number_format((float) $metrics['supplier_payables'], $dp) }} {{ $sym }}</div>
        </div>
        <div class="bg-white rounded-2xl border border-amber-200 p-4">
            <div class="text-amber-700 font-semibold">سلف العمال هذا الشهر</div>
            <div class="text-lg font-extrabold text-amber-800 mt-1">{{ number_format((float) $monthEmployeeAdvances, $dp) }} {{ $sym }}</div>
        </div>
        <div class="bg-indigo-600 text-white rounded-2xl p-4">
            <div class="text-indigo-100 font-semibold">صافي الربح التراكمي</div>
            <div class="text-lg font-extrabold mt-1">{{ number_format((float) $metrics['net_profit'], $dp) }} {{ $sym }}</div>
        </div>
    </div>

    <!-- 4. Operational & Security Alerts -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 text-xs">
        <!-- Low Stock -->
        <div class="bg-white rounded-2xl border border-slate-200 p-4">
            <h3 class="font-bold text-sm text-amber-800 mb-2.5">نواقص المخزون ({{ $lowStockProducts->count() }})</h3>
            <div class="divide-y divide-slate-100 max-h-64 overflow-y-auto">
                @forelse($lowStockProducts as $lp)
                <div class="py-2 flex justify-between gap-2">
                    <span class="font-semibold text-slate-800 truncate">{{ $lp->name }}</span>
                    <span class="text-amber-700 font-bold shrink-0">{{ $lp->quantity }} / {{ $lp->min_stock_alert }}</span>
                </div>
                @empty
                <p class="text-slate-400 py-3">جميع الأصناف متوفرة بكمية آمنة.</p>
                @endforelse
            </div>
        </div>

        <!-- Negative Stock Conflict Alerts -->
        <div class="bg-white rounded-2xl border border-slate-200 p-4">
            <h3 class="font-bold text-sm text-rose-700 mb-2.5">تنبيهات المخزون السالب ({{ $negativeAlerts->count() }})</h3>
            <div class="divide-y divide-slate-100 max-h-64 overflow-y-auto">
                @forelse($negativeAlerts as $na)
                <div class="py-2 flex justify-between gap-2">
                    <div class="min-w-0">
                        <div class="font-semibold text-slate-800 truncate">{{ $na->product?->name }}</div>
                        <div class="text-[11px] text-slate-400">{{ $na->user?->name }}</div>
                    </div>
                    <span class="text-rose-600 font-bold shrink-0">{{ $na->balance_after }}</span>
                </div>
                @empty
                <p class="text-slate-400 py-3">لا توجد تنبيهات مخزون سالب.</p>
                @endforelse
            </div>
        </div>

        <!-- Audit Log Alerts: Below-Cost Overrides & Cart Item Removals -->
        <div class="bg-white rounded-2xl border border-slate-200 p-4">
            <h3 class="font-bold text-sm text-slate-900 mb-2.5">الرقابة: تجاوز الخصم وحذف السلة</h3>
            <div class="divide-y divide-slate-100 max-h-64 overflow-y-auto">
                @forelse($auditAlerts as $act)
                <div class="py-2 space-y-0.5">
                    <div class="flex justify-between gap-2">
                        <span class="px-1.5 py-0.5 rounded font-bold {{ $act->event === 'discount_override' ? 'bg-amber-50 text-amber-800' : 'bg-rose-50 text-rose-700' }}">
                            {{ $act->event === 'discount_override' ? 'تجاوز حد التكلفة' : 'حذف صنف من السلة' }}
                        </span>
                        <span class="text-[11px] text-slate-400">{{ $act->created_at?->format('m-d H:i') }}</span>
                    </div>
                    <div class="text-slate-700">{{ $act->description }}</div>
                    <div class="text-[11px] text-slate-400">بواسطة: {{ $act->causer?->name ?? 'نظام' }}</div>
                </div>
                @empty
                <p class="text-slate-400 py-3">لا توجد تنبيهات رقابية مسجلة.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
