@extends('layouts.app')
@section('title', 'الجرد والتسويات المخزنية')
@section('header', 'جرد المخزون السريع وتسجيل التوالف')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

    <!-- Stocktake & Adjustment Form Card -->
    <div class="lg:col-span-4 rounded-2xl border border-gray-200 bg-white shadow-xs overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-emerald-50 border border-emerald-200/70 text-emerald-700 flex items-center justify-center shrink-0">
                <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2" />
                    <rect x="9" y="3" width="6" height="4" rx="1" />
                    <path d="m9 14 2 2 4-4" />
                </svg>
            </div>
            <div class="min-w-0">
                <h2 class="text-sm font-bold text-slate-900">تسجيل جرد أو تسوية مخزنية</h2>
                <p class="text-[11px] text-gray-500 mt-0.5">مطابقة الرصيد الفعلي أو إخراج التوالف</p>
            </div>
        </div>

        <form method="POST" action="{{ route('stocktake.store') }}" class="p-5 space-y-4 text-xs">
            @csrf

            <div class="space-y-1.5">
                <label class="block font-semibold text-slate-700">
                    الصنف <span class="text-rose-600">*</span>
                </label>
                <select name="product_id"
                        required
                        class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                    @foreach ($products as $p)
                        <option value="{{ $p->id }}">{{ $p->name }} (الحالي: {{ format_qty($p->quantity) }})</option>
                    @endforeach
                </select>
            </div>

            <div class="space-y-1.5">
                <label class="block font-semibold text-slate-700">نوع العملية</label>
                <select name="mode"
                        class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                    <option value="stocktake">مطابقة الجرد الفعلي (الكمية الفعلية على الرف)</option>
                    <option value="adjustment_out">إخراج تالف / هالك (-)</option>
                    <option value="adjustment_in">إضافة تسوية مخزنية (+)</option>
                </select>
            </div>

            <div class="space-y-1.5">
                <label class="block font-semibold text-slate-700">
                    الكمية <span class="text-rose-600">*</span>
                </label>
                <input type="number"
                       step="any"
                       name="quantity"
                       required
                       placeholder="0"
                       class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs font-semibold text-slate-900 tabular-nums placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
            </div>

            <div class="space-y-1.5">
                <label class="block font-semibold text-slate-700">سبب التسوية / ملاحظة</label>
                <input type="text"
                       name="note"
                       placeholder="جرد دوري / تالف"
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
                    <span>حفظ قيد المخزون</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Recent Stock Movements Table Card -->
    <div class="lg:col-span-8 rounded-2xl border border-gray-200 bg-white shadow-xs overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-slate-900 text-white flex items-center justify-center shrink-0">
                    <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 3v18h18" />
                        <path d="m19 9-5 5-4-4-3 3" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-slate-900 truncate">آخر 30 حركة في دفتر المخزون</h2>
                    <p class="text-[11px] text-gray-500 mt-0.5">اضغط على اسم الصنف لعرض بطاقة الصنف الكاملة</p>
                </div>
            </div>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-gray-100 text-slate-700 shrink-0 tabular-nums">
                {{ $recentMovements->count() }} حركة
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-right">
                <thead class="bg-[#F9FAFB] border-b border-gray-200 text-[11px] font-semibold text-gray-500">
                    <tr>
                        <th class="px-4 py-3">الصنف</th>
                        <th class="px-4 py-3">النوع</th>
                        <th class="px-4 py-3">التغير</th>
                        <th class="px-4 py-3">الرصيد بعد</th>
                        <th class="px-4 py-3">الموظف</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($recentMovements as $m)
                        <tr class="hover:bg-gray-50/80 transition-colors">
                            <td class="px-4 py-3">
                                <a href="{{ route('products.stock-card', $m->product_id) }}"
                                   class="inline-flex items-center gap-1.5 font-semibold text-slate-900 hover:text-emerald-700 transition-colors group">
                                    <span>{{ $m->product?->name }}</span>
                                    <svg class="w-3.5 h-3.5 text-gray-400 group-hover:text-emerald-600 transition-colors shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M7 17 17 7" />
                                        <path d="M7 7h10v10" />
                                    </svg>
                                </a>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-slate-700 border border-gray-200/70">
                                    {{ $m->type }}
                                </span>
                            </td>
                            <td class="px-4 py-3 tabular-nums">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold {{ (float)$m->quantity_delta < 0 ? 'bg-rose-50 text-rose-700 border border-rose-200/70' : 'bg-emerald-50 text-emerald-700 border border-emerald-200/70' }}">
                                    {{ format_qty($m->quantity_delta) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-bold text-slate-900 tabular-nums">
                                {{ format_qty($m->balance_after) }}
                            </td>
                            <td class="px-4 py-3 text-gray-500">
                                {{ $m->user?->name }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center">
                                <div class="w-11 h-11 rounded-2xl bg-gray-100 text-gray-400 mx-auto mb-3 flex items-center justify-center">
                                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M3 3v18h18" />
                                        <path d="m19 9-5 5-4-4-3 3" />
                                    </svg>
                                </div>
                                <p class="text-xs font-medium text-gray-500">لا توجد حركات مخزنية مسجلة حتى الآن.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection