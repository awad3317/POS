@extends('layouts.app')
@section('title', 'كرت الصنف')
@section('header', 'كرت حركة الصنف: ' . $product->name)

@section('content')
<div class="space-y-6">

    <!-- Product Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="rounded-2xl border border-gray-200 bg-white shadow-xs p-5 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 5v14M7 5v14M10 5v14M14 5v14M17 5v14M21 5v14" />
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-[11px] font-medium text-gray-500">الباركود</p>
                <p class="text-sm font-bold text-slate-900 font-mono tabular-nums truncate mt-0.5">
                    {{ $product->barcode ?? '—' }}
                </p>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-xs p-5 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m7.5 4.27 9 5.15" />
                    <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z" />
                    <path d="m3.3 7 8.7 5 8.7-5" />
                    <path d="M12 22V12" />
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-[11px] font-medium text-gray-500">نوع الصنف</p>
                <p class="text-sm font-bold text-slate-900 truncate mt-0.5">
                    {{ product_type_label($product->type) }}
                </p>
            </div>
        </div>

        <div class="rounded-2xl border border-emerald-200/80 bg-white shadow-xs p-5 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-emerald-50 border border-emerald-200/70 text-emerald-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2" />
                    <rect x="9" y="3" width="6" height="4" rx="1" />
                    <path d="m9 14 2 2 4-4" />
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-[11px] font-medium text-gray-500">الرصيد الحالي المعتمد</p>
                <p class="text-lg font-extrabold text-emerald-700 tabular-nums mt-0.5">
                    {{ format_qty($product->quantity) }}
                </p>
            </div>
        </div>
    </div>

    <!-- Movements Ledger Table -->
    <div class="rounded-2xl border border-gray-200 bg-white shadow-xs overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-slate-900 text-white flex items-center justify-center shrink-0">
                    <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 8v4l3 3" />
                        <circle cx="12" cy="12" r="9" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-slate-900 truncate">سجل حركات الصنف</h2>
                    <p class="text-[11px] text-gray-500 mt-0.5 truncate">{{ $product->name }}</p>
                </div>
            </div>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-gray-100 text-slate-700 shrink-0 tabular-nums">
                {{ $movements->count() }} حركة في الصفحة
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-right">
                <thead class="bg-[#F9FAFB] border-b border-gray-200 text-[11px] font-semibold text-gray-500">
                    <tr>
                        <th class="px-4 py-3">التاريخ</th>
                        <th class="px-4 py-3">نوع الحركة</th>
                        <th class="px-4 py-3">قبل</th>
                        <th class="px-4 py-3">التغير</th>
                        <th class="px-4 py-3">بعد</th>
                        <th class="px-4 py-3">ملاحظة</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($movements as $mv)
                        <tr class="hover:bg-gray-50/80 transition-colors">
                            <td class="px-4 py-3 text-gray-600 font-mono tabular-nums whitespace-nowrap">
                                {{ $mv->created_at?->format('Y-m-d H:i') }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-slate-700 border border-gray-200/70">
                                        {{ $mv->type }}
                                    </span>
                                    @if ($mv->is_negative_alert)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200/80">
                                            <svg class="w-3 h-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
                                                <path d="M12 9v4" />
                                                <path d="M12 17h.01" />
                                            </svg>
                                            <span>تنبيه سالب</span>
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600 tabular-nums whitespace-nowrap">
                                {{ format_qty($mv->balance_before) }}
                            </td>
                            <td class="px-4 py-3 tabular-nums whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold {{ (float)$mv->quantity_delta < 0 ? 'bg-rose-50 text-rose-700 border border-rose-200/70' : 'bg-emerald-50 text-emerald-700 border border-emerald-200/70' }}">
                                    {{ format_qty($mv->quantity_delta) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-bold text-slate-900 tabular-nums whitespace-nowrap">
                                {{ format_qty($mv->balance_after) }}
                            </td>
                            <td class="px-4 py-3 text-gray-500">
                                {{ $mv->note ?: '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center">
                                <div class="w-11 h-11 rounded-2xl bg-gray-100 text-gray-400 mx-auto mb-3 flex items-center justify-center">
                                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 8v4l3 3" />
                                        <circle cx="12" cy="12" r="9" />
                                    </svg>
                                </div>
                                <p class="text-xs font-medium text-gray-500">لا توجد حركات مسجلة لهذا الصنف حتى الآن.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($movements->hasPages())
            <div class="px-5 py-3.5 border-t border-gray-100 bg-[#F9FAFB]">
                {{ $movements->links() }}
            </div>
        @endif
    </div>

</div>
@endsection