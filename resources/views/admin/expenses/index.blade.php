@extends('layouts.app')
@section('title', 'المصروفات التشغيلية')
@section('header', 'المصروفات اليومية والتشغيلية')

@section('content')
@php $sym = $store->currency_symbol; @endphp

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

    <!-- Add Expense Form Card -->
    <div class="lg:col-span-4 rounded-2xl border border-gray-200 bg-white shadow-xs overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-emerald-50 border border-emerald-200/70 text-emerald-700 flex items-center justify-center shrink-0">
                <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                </svg>
            </div>
            <div class="min-w-0">
                <h2 class="text-sm font-bold text-slate-900">تسجيل مصروف جديد</h2>
                <p class="text-[11px] text-gray-500 mt-0.5">إدخال المصروفات التشغيلية وتحديد مصدر الصرف</p>
            </div>
        </div>

        <form method="POST" action="{{ route('expenses.store') }}" class="p-5 space-y-4 text-xs">
            @csrf

            <div class="space-y-1.5">
                <label class="block font-semibold text-slate-700">البند</label>
                <select name="category"
                        class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                    <option value="كهرباء وطاقة">كهرباء وطاقة</option>
                    <option value="إيجار المحل">إيجار المحل</option>
                    <option value="ماء ونظافة">ماء ونظافة</option>
                    <option value="أكياس وتغليف">أكياس وتغليف</option>
                    <option value="صيانة">صيانة</option>
                    <option value="نثريات">نثريات عامة</option>
                </select>
            </div>

            <div class="space-y-1.5">
                <label class="block font-semibold text-slate-700">
                    المبلغ <span class="text-rose-600">*</span>
                </label>
                <input type="number"
                       step="any"
                       name="amount"
                       required
                       placeholder="0"
                       class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs font-bold text-slate-900 tabular-nums placeholder:font-normal placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
            </div>

            <div class="space-y-1.5">
                <label class="block font-semibold text-slate-700">مصدر الصرف</label>
                <select name="payment_source"
                        class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                    <option value="owner_pocket">جيب المالك / الإدارة</option>
                    <option value="cash_drawer">درج الكاشير المفتوح</option>
                    <option value="bank">البنك</option>
                </select>
            </div>

            <div class="space-y-1.5">
                <label class="block font-semibold text-slate-700">الوردية (عند الصرف من الدرج)</label>
                <select name="shift_id"
                        class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                    <option value="">— بدون وردية —</option>
                    @foreach ($openShifts as $sh)
                        <option value="{{ $sh->id }}">{{ $sh->terminal?->name }} (#{{ $sh->id }})</option>
                    @endforeach
                </select>
            </div>

            <div class="space-y-1.5">
                <label class="block font-semibold text-slate-700">بيان / ملاحظة</label>
                <input type="text"
                       name="notes"
                       placeholder="بيان / ملاحظة"
                       class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs text-slate-900 placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
            </div>

            <div class="pt-1">
                <button type="submit"
                        class="w-full inline-flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                        <polyline points="17 21 17 13 7 13 7 21" />
                        <polyline points="7 3 7 8 15 8" />
                    </svg>
                    <span>حفظ المصروف</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Expenses Ledger Table Card -->
    <div class="lg:col-span-8 rounded-2xl border border-gray-200 bg-white shadow-xs overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-slate-900 text-white flex items-center justify-center shrink-0">
                    <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z" />
                        <path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8" />
                        <path d="M12 17.5v-11" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-slate-900 truncate">سجل المصروفات</h2>
                    <p class="text-[11px] text-gray-500 mt-0.5">متابعة المصروفات اليومية والتشغيلية للمتجر</p>
                </div>
            </div>

            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-rose-50 border border-rose-200/80 text-xs shrink-0">
                <span class="text-rose-700 font-medium">إجمالي هذا الشهر:</span>
                <strong class="font-extrabold text-rose-700 tabular-nums">
                    {{ format_money($monthTotal, $store->decimal_places) }} {{ $sym }}
                </strong>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-right">
                <thead class="bg-[#F9FAFB] border-b border-gray-200 text-[11px] font-semibold text-gray-500">
                    <tr>
                        <th class="px-4 py-3">البند</th>
                        <th class="px-4 py-3">المبلغ</th>
                        <th class="px-4 py-3">المصدر</th>
                        <th class="px-4 py-3">التاريخ</th>
                        <th class="px-4 py-3">ملاحظة</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($expenses as $ex)
                        <tr class="hover:bg-gray-50/80 transition-colors">
                            <td class="px-4 py-3.5 font-bold text-slate-900 whitespace-nowrap">
                                {{ $ex->category }}
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap tabular-nums">
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200/70">
                                    <span>{{ format_money($ex->amount, $store->decimal_places) }}</span>
                                    <span class="font-medium opacity-80">{{ $sym }}</span>
                                </span>
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-slate-700 border border-gray-200/70">
                                    {{ $ex->payment_source }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 text-gray-600 font-mono tabular-nums whitespace-nowrap">
                                {{ $ex->spent_at?->format('Y-m-d H:i') }}
                            </td>
                            <td class="px-4 py-3.5 text-gray-500">
                                {{ $ex->notes ?: '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-14 text-center">
                                <div class="w-11 h-11 rounded-2xl bg-gray-100 text-gray-400 mx-auto mb-3 flex items-center justify-center">
                                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z" />
                                        <path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8" />
                                    </svg>
                                </div>
                                <p class="text-xs font-medium text-gray-500">لا توجد مصروفات مسجلة.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection