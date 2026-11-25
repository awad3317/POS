@extends('layouts.app')
@section('title', 'كشف حساب مورد')
@section('header', 'كشف حساب المورد: ' . $supplier->name)

@section('content')
@php $sym = $store->currency_symbol; @endphp

<div class="space-y-6">

    <!-- Statement Summary & Actions -->
    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-stretch">
        <div class="md:col-span-4 rounded-2xl border border-gray-200 bg-white shadow-xs p-5 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-[11px] font-medium text-gray-500">الرصيد الافتتاحي</p>
                <p class="text-base font-extrabold text-slate-900 tabular-nums mt-0.5 truncate">
                    {{ format_money($statement['opening_balance'], $store->decimal_places) }}
                    <span class="text-xs font-medium text-gray-400">{{ $sym }}</span>
                </p>
            </div>
        </div>

        <div class="md:col-span-4 rounded-2xl border border-rose-200/80 bg-white shadow-xs p-5 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl bg-rose-50 border border-rose-200/70 text-rose-600 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="5" width="20" height="14" rx="2" />
                    <line x1="2" y1="10" x2="22" y2="10" />
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-[11px] font-medium text-gray-500">الرصيد الختامي المستحق</p>
                <p class="text-base font-extrabold text-rose-600 tabular-nums mt-0.5 truncate">
                    {{ format_money($statement['closing_balance'], $store->decimal_places) }}
                    <span class="text-xs font-medium text-rose-500/80">{{ $sym }}</span>
                </p>
            </div>
        </div>

        <div class="md:col-span-4 rounded-2xl border border-gray-200 bg-white shadow-xs p-5 flex flex-wrap items-center justify-end gap-2.5">
            <a href="{{ $whatsappUrl }}"
               target="_blank"
               rel="noopener"
               class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-xs transition-colors">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z" />
                </svg>
                <span>إرسال إشعار واتساب</span>
            </a>

            <button type="button"
                    onclick="window.print()"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-slate-700 text-xs font-semibold transition-colors cursor-pointer">
                <svg class="w-4 h-4 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 9V2h12v7" />
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                    <rect x="6" y="14" width="12" height="8" />
                </svg>
                <span>طباعة</span>
            </button>
        </div>
    </div>

    <!-- Statement Ledger Table Card -->
    <div class="rounded-2xl border border-gray-200 bg-white shadow-xs overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-slate-900 text-white flex items-center justify-center shrink-0">
                    <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                        <polyline points="14 2 14 8 20 8" />
                        <line x1="16" y1="13" x2="8" y2="13" />
                        <line x1="16" y1="17" x2="8" y2="17" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-slate-900 truncate">حركات الحساب</h2>
                    <p class="text-[11px] text-gray-500 mt-0.5 truncate">{{ $supplier->name }}</p>
                </div>
            </div>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-gray-100 text-slate-700 shrink-0 tabular-nums">
                {{ count($statement['entries']) }} حركة
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-right">
                <thead class="bg-[#F9FAFB] border-b border-gray-200 text-[11px] font-semibold text-gray-500">
                    <tr>
                        <th class="px-4 py-3">التاريخ</th>
                        <th class="px-4 py-3">العملية</th>
                        <th class="px-4 py-3">المرجع</th>
                        <th class="px-4 py-3">القيمة</th>
                        <th class="px-4 py-3">الرصيد التراكمي</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($statement['entries'] as $row)
                        <tr class="hover:bg-gray-50/80 transition-colors">
                            <td class="px-4 py-3.5 text-gray-600 font-mono tabular-nums whitespace-nowrap">
                                {{ $row['date'] }}
                            </td>
                            <td class="px-4 py-3.5">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-slate-700 border border-gray-200/70">
                                    {{ $row['type'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 font-mono text-gray-600 tabular-nums whitespace-nowrap">
                                {{ $row['reference'] ?: '—' }}
                            </td>
                            <td class="px-4 py-3.5 font-semibold tabular-nums whitespace-nowrap {{ (float) $row['signed_amount'] < 0 ? 'text-emerald-700' : 'text-rose-600' }}">
                                {{ format_money($row['signed_amount'], $store->decimal_places) }}
                                <span class="text-[11px] font-medium opacity-75">{{ $sym }}</span>
                            </td>
                            <td class="px-4 py-3.5 font-bold text-slate-900 tabular-nums whitespace-nowrap">
                                {{ format_money($row['running_balance'], $store->decimal_places) }}
                                <span class="text-[11px] font-medium text-gray-400">{{ $sym }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-14 text-center">
                                <div class="w-11 h-11 rounded-2xl bg-gray-100 text-gray-400 mx-auto mb-3 flex items-center justify-center">
                                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                        <polyline points="14 2 14 8 20 8" />
                                    </svg>
                                </div>
                                <p class="text-xs font-medium text-gray-500">لا توجد حركات مسجلة في هذه الفترة.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection