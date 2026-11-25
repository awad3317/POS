@extends('layouts.app')
@section('title', 'فواتير المشتريات')
@section('header', 'فواتير المشتريات ومرتجعات الشراء')

@section('content')
@php $sym = $store->currency_symbol; @endphp

<div class="space-y-6">

    <!-- Purchases Table Card -->
    <div class="rounded-2xl border border-gray-200 bg-white shadow-xs overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-slate-900 text-white flex items-center justify-center shrink-0">
                    <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z" />
                        <path d="M3 6h18" />
                        <path d="M16 10a4 4 0 0 1-8 0" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm font-bold text-slate-900 truncate">سجل فواتير المشتريات</h2>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-slate-700 shrink-0 tabular-nums">
                            {{ $purchases->count() }} فاتورة
                        </span>
                    </div>
                    <p class="text-[11px] text-gray-500 mt-0.5">متابعة المشتريات والمرتجعات والمدفوعات والآجل للموردين</p>
                </div>
            </div>

            <a href="{{ route('purchases.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-xs transition-colors shrink-0">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 5v14M5 12h14" />
                </svg>
                <span>فاتورة مشتريات جديدة</span>
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-right">
                <thead class="bg-[#F9FAFB] border-b border-gray-200 text-[11px] font-semibold text-gray-500">
                    <tr>
                        <th class="px-4 py-3">المرجع</th>
                        <th class="px-4 py-3">النوع</th>
                        <th class="px-4 py-3">المورد</th>
                        <th class="px-4 py-3">الإجمالي</th>
                        <th class="px-4 py-3">المدفوع</th>
                        <th class="px-4 py-3">الآجل</th>
                        <th class="px-4 py-3">التاريخ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($purchases as $p)
                        <tr class="hover:bg-gray-50/80 transition-colors">
                            <td class="px-4 py-3.5 font-mono font-bold text-slate-900 tabular-nums whitespace-nowrap">
                                {{ $p->reference_number }}
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap">
                                @if ($p->type === 'purchase')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/70">
                                        فاتورة شراء
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200/80">
                                        مرتجع شراء
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 font-medium text-slate-700">
                                {{ $p->supplier?->name ?? 'نقدي مباشر' }}
                            </td>
                            <td class="px-4 py-3.5 font-bold text-slate-900 tabular-nums whitespace-nowrap">
                                {{ format_money($p->total_amount, $store->decimal_places) }}
                                <span class="text-[11px] font-medium text-gray-400">{{ $sym }}</span>
                            </td>
                            <td class="px-4 py-3.5 font-semibold text-emerald-700 tabular-nums whitespace-nowrap">
                                {{ format_money($p->paid_amount, $store->decimal_places) }}
                                <span class="text-[11px] font-medium text-emerald-600/70">{{ $sym }}</span>
                            </td>
                            <td class="px-4 py-3.5 font-semibold tabular-nums whitespace-nowrap {{ (float) $p->credit_amount > 0 ? 'text-rose-600' : 'text-gray-400' }}">
                                {{ format_money($p->credit_amount, $store->decimal_places) }}
                                <span class="text-[11px] font-medium opacity-75">{{ $sym }}</span>
                            </td>
                            <td class="px-4 py-3.5 text-gray-500 font-mono tabular-nums whitespace-nowrap">
                                {{ $p->purchased_at?->format('Y-m-d H:i') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-14 text-center">
                                <div class="w-12 h-12 rounded-2xl bg-gray-100 text-gray-400 mx-auto mb-3 flex items-center justify-center">
                                    <svg class="w-5.5 h-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z" />
                                        <path d="M3 6h18" />
                                        <path d="M16 10a4 4 0 0 1-8 0" />
                                    </svg>
                                </div>
                                <p class="text-xs font-semibold text-slate-700">لا توجد فواتير مشتريات بعد.</p>
                                <p class="text-[11px] text-gray-400 mt-1">ابدأ بإضافة أول فاتورة مشتريات لتغذية المخزن.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($purchases->hasPages())
            <div class="px-5 py-3.5 border-t border-gray-100 bg-[#F9FAFB]">
                {{ $purchases->links() }}
            </div>
        @endif
    </div>

</div>
@endsection