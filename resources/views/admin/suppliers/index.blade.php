@extends('layouts.app')
@section('title', 'حسابات الموردين')
@section('header', 'حسابات الموردين وسندات الصرف')

@section('content')
@php $sym = $store->currency_symbol; @endphp

<div class="space-y-6" x-data="{ addModal: false, paySupplier: null }">

    <!-- Top Summary & Actions Row -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-stretch">
        <div class="lg:col-span-5 rounded-2xl border border-rose-200/80 bg-white shadow-xs p-5 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3.5 min-w-0">
                <div class="w-11 h-11 rounded-xl bg-rose-50 border border-rose-200/70 text-rose-600 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="5" width="20" height="14" rx="2" />
                        <line x1="2" y1="10" x2="22" y2="10" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] font-medium text-gray-500">إجمالي المستحق للموردين على المحل</p>
                    <p class="text-lg font-extrabold text-rose-600 tabular-nums mt-0.5 truncate">
                        {{ format_money($totalPayables, $store->decimal_places) }}
                        <span class="text-xs font-semibold text-rose-500/80">{{ $sym }}</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="lg:col-span-7 rounded-2xl border border-gray-200 bg-white shadow-xs p-5 flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <h2 class="text-sm font-bold text-slate-900">إجراءات سريعة</h2>
                <p class="text-[11px] text-gray-500 mt-0.5">أضف مورداً جديداً أو أدخل فاتورة مشتريات لتحديث الأرصدة</p>
            </div>
            <div class="flex flex-wrap items-center gap-2.5">
                <a href="{{ route('purchases.create') }}"
                   class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold transition-colors">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z" />
                        <path d="M3 6h18" />
                        <path d="M16 10a4 4 0 0 1-8 0" />
                    </svg>
                    <span>فاتورة مشتريات</span>
                </a>
                <button type="button"
                        @click="addModal = true"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 5v14M5 12h14" />
                    </svg>
                    <span>مورد جديد</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Suppliers Table Card -->
    <div class="rounded-2xl border border-gray-200 bg-white shadow-xs overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-slate-900 text-white flex items-center justify-center shrink-0">
                    <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 21h18" />
                        <path d="M5 21V7l8-4v18" />
                        <path d="M19 21V11l-6-4" />
                        <path d="M9 9v.01M9 12v.01M9 15v.01M9 18v.01" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-slate-900 truncate">قائمة الموردين</h2>
                    <p class="text-[11px] text-gray-500 mt-0.5">متابعة المشتريات والمسدد والأرصدة المستحقة وسندات الصرف</p>
                </div>
            </div>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-gray-100 text-slate-700 shrink-0 tabular-nums">
                {{ $suppliers->count() }} مورد
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-right">
                <thead class="bg-[#F9FAFB] border-b border-gray-200 text-[11px] font-semibold text-gray-500">
                    <tr>
                        <th class="px-4 py-3">المورد / الشركة</th>
                        <th class="px-4 py-3">الهاتف</th>
                        <th class="px-4 py-3">إجمالي المشتريات</th>
                        <th class="px-4 py-3">إجمالي المسدد</th>
                        <th class="px-4 py-3">الرصيد المتبقي له</th>
                        <th class="px-4 py-3">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($suppliers as $s)
                        <tr class="hover:bg-gray-50/80 transition-colors">
                            <td class="px-4 py-3.5">
                                <div class="font-bold text-slate-900">{{ $s->name }}</div>
                                @if ($s->company_name)
                                    <div class="text-[11px] text-gray-500 mt-0.5">{{ $s->company_name }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 font-mono text-gray-600 tabular-nums whitespace-nowrap">
                                {{ $s->phone ?? '—' }}
                            </td>
                            <td class="px-4 py-3.5 font-semibold text-slate-800 tabular-nums whitespace-nowrap">
                                {{ format_money($s->total_purchases ?? 0, $store->decimal_places) }}
                                <span class="text-[11px] font-medium text-gray-400">{{ $sym }}</span>
                            </td>
                            <td class="px-4 py-3.5 font-semibold text-emerald-700 tabular-nums whitespace-nowrap">
                                {{ format_money($s->total_payments ?? 0, $store->decimal_places) }}
                                <span class="text-[11px] font-medium text-emerald-600/70">{{ $sym }}</span>
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap tabular-nums">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold {{ (float) $s->current_balance > 0 ? 'bg-rose-50 text-rose-700 border border-rose-200/70' : 'bg-gray-100 text-gray-600 border border-gray-200/70' }}">
                                    <span>{{ format_money($s->current_balance, $store->decimal_places) }}</span>
                                    <span class="font-medium opacity-80">{{ $sym }}</span>
                                </span>
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap">
                                <div class="flex items-center gap-1.5">
                                    <button type="button"
                                            @click="paySupplier = { id: {{ $s->id }}, name: '{{ addslashes($s->name) }}' }"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-600 text-emerald-700 hover:text-white border border-emerald-200/80 hover:border-emerald-600 text-[11px] font-semibold transition-colors cursor-pointer">
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="2" y="6" width="20" height="12" rx="2" />
                                            <circle cx="12" cy="12" r="2" />
                                            <path d="M6 12h.01M18 12h.01" />
                                        </svg>
                                        <span>سند صرف</span>
                                    </button>

                                    <a href="{{ route('suppliers.statement', $s->id) }}"
                                       class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 text-slate-700 text-[11px] font-semibold transition-colors">
                                        <svg class="w-3.5 h-3.5 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                            <polyline points="14 2 14 8 20 8" />
                                            <line x1="16" y1="13" x2="8" y2="13" />
                                            <line x1="16" y1="17" x2="8" y2="17" />
                                        </svg>
                                        <span>كشف حساب</span>
                                    </a>

                                    <a href="{{ \App\Support\WhatsAppLink::forSupplierPayment($s, $store) }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-semibold transition-colors">
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z" />
                                        </svg>
                                        <span>واتساب</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-14 text-center">
                                <div class="w-12 h-12 rounded-2xl bg-gray-100 text-gray-400 mx-auto mb-3 flex items-center justify-center">
                                    <svg class="w-5.5 h-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M3 21h18" />
                                        <path d="M5 21V7l8-4v18" />
                                        <path d="M19 21V11l-6-4" />
                                    </svg>
                                </div>
                                <p class="text-xs font-semibold text-slate-700">لا يوجد موردون مسجلون.</p>
                                <p class="text-[11px] text-gray-400 mt-1">أضف أول مورد لمتابعة فواتير الشراء وسندات الصرف.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Supplier Modal -->
    <div x-show="addModal"
         style="display:none;"
         class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-[1px] flex items-center justify-center p-4">
        <form method="POST"
              action="{{ route('suppliers.store') }}"
              @click.outside="addModal = false"
              class="rounded-2xl border border-gray-200 bg-white shadow-xl max-w-md w-full overflow-hidden text-xs">
            @csrf

            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-emerald-50 border border-emerald-200/70 text-emerald-700 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 5v14M5 12h14" />
                        </svg>
                    </div>
                    <h3 class="font-bold text-sm text-slate-900">إضافة مورد جديد</h3>
                </div>
                <button type="button"
                        @click="addModal = false"
                        class="p-1.5 rounded-lg text-gray-400 hover:text-slate-700 hover:bg-gray-100 transition-colors cursor-pointer">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-5 space-y-3.5">
                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">
                        اسم المورد <span class="text-rose-600">*</span>
                    </label>
                    <input type="text"
                           name="name"
                           required
                           placeholder="اسم المورد *"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs text-slate-900 placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                </div>

                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">اسم الشركة / الوكالة</label>
                    <input type="text"
                           name="company_name"
                           placeholder="اسم الشركة / الوكالة"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs text-slate-900 placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                </div>

                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">رقم الهاتف / واتساب</label>
                    <input type="text"
                           name="phone"
                           placeholder="رقم الهاتف / واتساب"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs font-mono text-slate-900 tabular-nums placeholder:font-sans placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                </div>

                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">رصيد افتتاحي مستحق للمورد</label>
                    <input type="number"
                           step="any"
                           name="opening_balance"
                           value="0"
                           placeholder="رصيد افتتاحي مستحق للمورد"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs font-semibold text-slate-900 tabular-nums placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                </div>
            </div>

            <div class="px-5 py-3.5 border-t border-gray-100 bg-[#F9FAFB] flex justify-end gap-2">
                <button type="button"
                        @click="addModal = false"
                        class="px-4 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-slate-700 font-semibold transition-colors cursor-pointer">
                    إلغاء
                </button>
                <button type="submit"
                        class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold shadow-xs transition-colors cursor-pointer">
                    حفظ
                </button>
            </div>
        </form>
    </div>

    <!-- Pay Supplier Modal -->
    <div x-show="paySupplier"
         style="display:none;"
         class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-[1px] flex items-center justify-center p-4">
        <form method="POST"
              :action="`/suppliers/${paySupplier?.id}/payments`"
              @click.outside="paySupplier = null"
              class="rounded-2xl border border-gray-200 bg-white shadow-xl max-w-md w-full overflow-hidden text-xs">
            @csrf

            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-8 h-8 rounded-xl bg-slate-900 text-white flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="6" width="20" height="12" rx="2" />
                            <circle cx="12" cy="12" r="2" />
                        </svg>
                    </div>
                    <h3 class="font-bold text-sm text-slate-900 truncate">
                        سند صرف للمورد: <span class="text-emerald-700" x-text="paySupplier?.name"></span>
                    </h3>
                </div>
                <button type="button"
                        @click="paySupplier = null"
                        class="p-1.5 rounded-lg text-gray-400 hover:text-slate-700 hover:bg-gray-100 transition-colors cursor-pointer shrink-0">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-5 space-y-3.5">
                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">
                        المبلغ المسدد <span class="text-rose-600">*</span>
                    </label>
                    <input type="number"
                           step="any"
                           name="amount"
                           required
                           placeholder="المبلغ المسدد *"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs font-bold text-slate-900 tabular-nums placeholder:font-normal placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label class="block font-semibold text-slate-700">طريقة السداد</label>
                        <select name="method"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3 py-2.5 text-xs text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                            <option value="cash">نقدي</option>
                            <option value="kuraimi">الكريمي</option>
                            <option value="jawali">جوالي</option>
                            <option value="floosak">فلوسك</option>
                            <option value="onecash">ون كاش</option>
                            <option value="bank_transfer">تحويل بنكي</option>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-semibold text-slate-700">مصدر الصرف</label>
                        <select name="payment_source"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3 py-2.5 text-xs text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                            <option value="owner_pocket">من حساب المالك / الإدارة</option>
                            <option value="cash_drawer">من درج الكاشير المفتوح</option>
                            <option value="bank">من البنك</option>
                        </select>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">الوردية (عند الصرف من الدرج)</label>
                    <select name="shift_id"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                        <option value="">— الوردية (مطلوب فقط عند الصرف من الدرج) —</option>
                        @foreach ($openShifts as $sh)
                            <option value="{{ $sh->id }}">{{ $sh->terminal?->name }} (#{{ $sh->id }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">رقم الحوالة / الإشعار</label>
                    <input type="text"
                           name="reference_number"
                           placeholder="رقم الحوالة / الإشعار (اختياري)"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs text-slate-900 placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                </div>
            </div>

            <div class="px-5 py-3.5 border-t border-gray-100 bg-[#F9FAFB] flex justify-end gap-2">
                <button type="button"
                        @click="paySupplier = null"
                        class="px-4 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-slate-700 font-semibold transition-colors cursor-pointer">
                    إلغاء
                </button>
                <button type="submit"
                        class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold shadow-xs transition-colors cursor-pointer">
                    اعتماد الصرف
                </button>
            </div>
        </form>
    </div>

</div>
@endsection