@extends('layouts.app')
@section('title', 'تقرير الوردية Z-Report')
@section('header', 'تقرير جرد الوردية #' . $shift->id)
@section('breadcrumb', 'الرئيسية / الورديات والصندوق / وردية #' . $shift->id)

@section('content')
@php $sym = $store->currency_symbol; @endphp

<div class="space-y-5">

    <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
        <a href="{{ route('shifts.index') }}"
           class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 transition hover:bg-gray-50">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
            <span>العودة لسجل الورديات</span>
        </a>

        <button type="button" onclick="window.print()"
                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 transition hover:bg-gray-50">
            <span>طباعة التقرير</span>
        </button>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs lg:col-span-2">
            <div class="grid grid-cols-1 gap-4 border-b border-gray-100 bg-gray-50/60 px-5 py-4 text-xs sm:grid-cols-3 sm:px-6">
                <div>
                    <div class="text-[11px] text-gray-400">الكاشير</div>
                    <div class="mt-0.5 text-sm font-bold text-gray-900">{{ $shift->user?->name }}</div>
                </div>
                <div>
                    <div class="text-[11px] text-gray-400">الجهاز</div>
                    <div class="mt-0.5 text-sm font-bold text-gray-900">{{ $shift->terminal?->name }}</div>
                </div>
                <div>
                    <div class="text-[11px] text-gray-400">الحالة</div>
                    <div class="mt-1">
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $shift->status === 'open' ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20' : 'bg-gray-200/70 text-gray-700' }}">
                            {{ $shift->status === 'open' ? 'مفتوحة' : 'مغلقة' }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="divide-y divide-gray-100 px-5 py-2 text-sm sm:px-6">
                <div class="flex items-center justify-between py-3.5">
                    <span class="text-gray-600">عهدة البداية (Opening Cash)</span>
                    <strong class="font-bold text-gray-900 tabular-nums">{{ format_money($shift->opening_cash, $store->decimal_places) }} {{ $sym }}</strong>
                </div>
                <div class="flex items-center justify-between py-3.5 text-emerald-700">
                    <span class="font-medium">+ صافي المبيعات النقدية</span>
                    <strong class="font-bold tabular-nums">{{ format_money($shift->cash_sales_total, $store->decimal_places) }} {{ $sym }}</strong>
                </div>
                <div class="flex items-center justify-between py-3.5 text-emerald-700">
                    <span class="font-medium">+ مقبوضات ديون العملاء نقداً</span>
                    <strong class="font-bold tabular-nums">{{ format_money($shift->cash_in_total, $store->decimal_places) }} {{ $sym }}</strong>
                </div>
                <div class="flex items-center justify-between py-3.5 text-rose-600">
                    <span class="font-medium">- مرتجعات المبيعات النقدية</span>
                    <strong class="font-bold tabular-nums">{{ format_money($shift->cash_returns_total, $store->decimal_places) }} {{ $sym }}</strong>
                </div>
                <div class="flex items-center justify-between py-3.5 text-rose-600">
                    <span class="font-medium">- مسحوبات الدرج (مصروفات + موردين + سلف عمال)</span>
                    <strong class="font-bold tabular-nums">{{ format_money($shift->cash_out_total, $store->decimal_places) }} {{ $sym }}</strong>
                </div>
            </div>

            <div class="space-y-3 border-t border-gray-200 bg-gray-50/70 px-5 py-4 sm:px-6">
                <div class="flex items-center justify-between text-base font-extrabold text-gray-900">
                    <span>= النقد المتوقع في الدرج</span>
                    <span class="tabular-nums">{{ format_money($shift->expected_cash, $store->decimal_places) }} {{ $sym }}</span>
                </div>

                @if ($shift->status === 'closed')
                    <div class="flex items-center justify-between border-t border-gray-200/80 pt-3 text-sm text-gray-700">
                        <span>النقد الفعلي المعدود</span>
                        <strong class="font-bold text-gray-900 tabular-nums">{{ format_money($shift->actual_cash, $store->decimal_places) }} {{ $sym }}</strong>
                    </div>

                    <div class="flex items-center justify-between rounded-xl px-4 py-3 text-sm font-extrabold {{ $shift->difference !== null && \App\Support\MoneyMath::isNegative($shift->difference) ? 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200' : 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200' }}">
                        <span>الفارق (العجز / الزيادة)</span>
                        <span class="tabular-nums">{{ format_money($shift->difference, $store->decimal_places) }} {{ $sym }}</span>
                    </div>
                @endif
            </div>
        </section>

        <div class="space-y-4 print:hidden">
            @if ($shift->status === 'open')
                <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs">
                    <div class="border-b border-gray-100 px-5 py-4">
                        <h3 class="text-sm font-bold text-gray-900">إغلاق الوردية وجرد النقد</h3>
                    </div>

                    <form method="POST" action="{{ route('shifts.close', $shift->id) }}" class="space-y-4 p-5 text-xs">
                        @csrf
                        <div>
                            <label class="mb-1.5 block font-semibold text-gray-700">النقد الفعلي الموجود بالدرج *</label>
                            <input type="number" step="any" name="actual_cash" required
                                   class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3.5 text-sm font-bold text-gray-900 tabular-nums focus:border-slate-900 focus:outline-none">
                        </div>

                        <div>
                            <label class="mb-1.5 block font-semibold text-gray-700">ملاحظات الإغلاق</label>
                            <input type="text" name="notes" placeholder="ملاحظات الإغلاق"
                                   class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3.5 text-xs text-gray-900 focus:border-slate-900 focus:outline-none">
                        </div>

                        <button type="submit"
                                class="h-10 w-full rounded-lg bg-slate-900 text-xs font-bold text-white shadow-xs transition hover:bg-slate-800">
                            إغلاق الوردية واعتماد الجرد
                        </button>
                    </form>
                </section>
            @elseif ($shift->difference !== null && \App\Support\MoneyMath::isNegative($shift->difference))
                @can('employees.manage')
                <section class="overflow-hidden rounded-2xl border border-rose-200 bg-white shadow-xs">
                    <div class="border-b border-rose-100 bg-rose-50/70 px-5 py-4">
                        <h3 class="text-sm font-bold text-rose-900">تسوية عجز الصندوق</h3>
                    </div>

                    <div class="space-y-4 p-5 text-xs">
                        <div class="rounded-xl border border-rose-200 bg-rose-50 p-3.5 text-rose-800">
                            <div class="font-bold">يوجد عجز نقدي بمقدار {{ format_money($shift->difference, $store->decimal_places) }} {{ $sym }}</div>
                        </div>

                        <form method="POST" action="{{ route('shifts.transfer-shortage', $shift->id) }}">
                            @csrf
                            <button type="submit"
                                    class="h-10 w-full rounded-lg bg-rose-600 text-xs font-bold text-white shadow-xs transition hover:bg-rose-700">
                                ترحيل العجز لحساب الكاشير
                            </button>
                        </form>
                    </div>
                </section>
                @endcan
            @endif
        </div>

    </div>
</div>
@endsection