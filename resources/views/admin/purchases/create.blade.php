@extends('layouts.app')
@section('title', 'إدخال فاتورة مشتريات')
@section('header', 'فاتورة مشتريات جديدة (تغذية المخزن وتحديث متوسط التكلفة)')

@section('content')
<div class="max-w-5xl"
     x-data="{
        items: [{ product_id: '{{ $products->first()?->id }}', quantity: 1, unit_cost: 0 }],
        paidAmount: 0,
        discountAmount: 0,
        get subtotal() {
            return this.items.reduce((sum, i) => sum + ((parseFloat(i.quantity) || 0) * (parseFloat(i.unit_cost) || 0)), 0);
        },
        get total() { return Math.max(0, this.subtotal - (parseFloat(this.discountAmount) || 0)); },
        get credit() {
            const val = Math.max(0, this.total - (parseFloat(this.paidAmount) || 0));
            return Number.isInteger(val) ? val : parseFloat(val.toFixed(4));
        }
     }">

    <form method="POST" action="{{ route('purchases.store') }}" class="space-y-6 text-xs">
        @csrf

        <!-- Invoice Header Details Card -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-xs overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-slate-900 text-white flex items-center justify-center shrink-0">
                    <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                        <polyline points="14 2 14 8 20 8" />
                        <line x1="16" y1="13" x2="8" y2="13" />
                        <line x1="16" y1="17" x2="8" y2="17" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-slate-900">بيانات الفاتورة والمورد</h2>
                    <p class="text-[11px] text-gray-500 mt-0.5">حدد المورد والمرجع ومصدر سداد المبلغ المدفوع</p>
                </div>
            </div>

            <div class="p-5 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">المورد</label>
                    <select name="supplier_id"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                        <option value="">— شراء نقدي بدون مورد —</option>
                        @foreach ($suppliers as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">رقم فاتورة المورد</label>
                    <input type="text"
                           name="reference_number"
                           placeholder="اختياري"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs text-slate-900 placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                </div>

                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">مصدر الدفع</label>
                    <select name="payment_source"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                        <option value="owner_pocket">جيب المالك / الإدارة</option>
                        <option value="cash_drawer">درج الكاشير</option>
                        <option value="bank">البنك / حوالة</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Purchased Items Card -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-xs overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 border border-emerald-200/70 text-emerald-700 flex items-center justify-center shrink-0">
                        <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m7.5 4.27 9 5.15" />
                            <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z" />
                            <path d="m3.3 7 8.7 5 8.7-5" />
                            <path d="M12 22V12" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-sm font-bold text-slate-900">الأصناف المشتراة</h2>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-slate-700 tabular-nums"
                                  x-text="`${items.length} سطر`"></span>
                        </div>
                        <p class="text-[11px] text-gray-500 mt-0.5">أدخل الكمية وسعر تكلفة الوحدة لكل صنف</p>
                    </div>
                </div>

                <button type="button"
                        @click="items.push({ product_id: '{{ $products->first()?->id }}', quantity: 1, unit_cost: 0 })"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold transition-colors cursor-pointer">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 5v14M5 12h14" />
                    </svg>
                    <span>سطر جديد</span>
                </button>
            </div>

            <div class="p-5 space-y-3">
                <!-- Desktop Column Labels -->
                <div class="hidden sm:grid sm:grid-cols-12 gap-3 px-3 text-[11px] font-semibold text-gray-500">
                    <div class="sm:col-span-6">الصنف</div>
                    <div class="sm:col-span-2">الكمية</div>
                    <div class="sm:col-span-3">سعر تكلفة الوحدة</div>
                    <div class="sm:col-span-1 text-center">إجراء</div>
                </div>

                <template x-for="(row, idx) in items" :key="idx">
                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center rounded-xl border border-gray-200/80 bg-[#F9FAFB] p-3">
                        <div class="sm:col-span-6">
                            <label class="block sm:hidden text-[11px] font-semibold text-gray-500 mb-1">الصنف</label>
                            <select :name="`items[${idx}][product_id]`"
                                    x-model="row.product_id"
                                    class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs text-slate-900 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                                @foreach ($products as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->barcode }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block sm:hidden text-[11px] font-semibold text-gray-500 mb-1">الكمية</label>
                            <input type="number"
                                   step="any"
                                   :name="`items[${idx}][quantity]`"
                                   x-model="row.quantity"
                                   placeholder="الكمية"
                                   class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-slate-900 tabular-nums focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                        </div>

                        <div class="sm:col-span-3">
                            <label class="block sm:hidden text-[11px] font-semibold text-gray-500 mb-1">سعر تكلفة الوحدة</label>
                            <input type="number"
                                   step="any"
                                   :name="`items[${idx}][unit_cost]`"
                                   x-model="row.unit_cost"
                                   placeholder="سعر تكلفة الوحدة"
                                   class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-slate-900 tabular-nums focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                        </div>

                        <div class="sm:col-span-1 flex justify-end sm:justify-center">
                            <button type="button"
                                    @click="if (items.length > 1) items.splice(idx, 1)"
                                    :disabled="items.length <= 1"
                                    title="حذف السطر"
                                    class="p-2 rounded-lg text-gray-400 hover:text-rose-600 hover:bg-rose-50 disabled:opacity-30 disabled:cursor-not-allowed transition-colors cursor-pointer">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 6h18" />
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6" />
                                    <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Totals & Payment Summary Card -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-xs overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-sm font-bold text-slate-900">الحساب والسداد</h2>
                <div class="flex flex-wrap items-center gap-3 text-xs tabular-nums">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-gray-100 text-gray-600">
                        <span>المجموع الفرعي:</span>
                        <strong class="text-slate-900" x-text="subtotal"></strong>
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-emerald-50 border border-emerald-200/70 text-emerald-700">
                        <span>صافي الفاتورة:</span>
                        <strong class="text-emerald-800" x-text="total"></strong>
                    </span>
                </div>
            </div>

            <div class="p-5 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">خصم الفاتورة</label>
                    <input type="number"
                           step="any"
                           name="discount_amount"
                           x-model="discountAmount"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs font-semibold text-slate-900 tabular-nums focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                </div>

                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">المدفوع الآن</label>
                    <input type="number"
                           step="any"
                           name="paid_amount"
                           x-model="paidAmount"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs font-semibold text-emerald-700 tabular-nums focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                </div>

                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">المتبقي آجل للمورد</label>
                    <input type="number"
                           step="any"
                           name="credit_amount"
                           :value="credit"
                           readonly
                           class="w-full rounded-xl border border-rose-200/80 bg-rose-50/60 px-3.5 py-2.5 text-xs font-bold text-rose-700 tabular-nums focus:outline-none">
                </div>
            </div>

            <div class="px-5 py-4 border-t border-gray-100 bg-[#F9FAFB] flex items-center justify-end gap-2.5">
                <a href="{{ route('purchases.index') }}"
                   class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-slate-700 text-xs font-semibold transition-colors">
                    إلغاء
                </a>
                <button type="submit"
                        class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 6 9 17l-5-5" />
                    </svg>
                    <span>اعتماد فاتورة الشراء</span>
                </button>
            </div>
        </div>
    </form>

</div>
@endsection