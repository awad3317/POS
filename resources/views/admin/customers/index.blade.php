@extends('layouts.app')
@section('title', 'العملاء ودفتر الديون')
@section('header', 'العملاء ودفتر الديون وسندات القبض')

@section('content')
@php $sym = $store->currency_symbol; @endphp

<div class="space-y-6" x-data="{ addCust: false, settleCust: null }">

    <!-- Top Summary & Search / Actions Row -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-stretch">
        <div class="lg:col-span-5 rounded-2xl border border-emerald-200/80 bg-white shadow-xs p-5 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3.5 min-w-0">
                <div class="w-11 h-11 rounded-xl bg-emerald-50 border border-emerald-200/70 text-emerald-700 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] font-medium text-gray-500">إجمالي الديون المستحقة عند العملاء</p>
                    <p class="text-lg font-extrabold text-emerald-700 tabular-nums mt-0.5 truncate">
                        {{ format_money($totalReceivables, $store->decimal_places) }}
                        <span class="text-xs font-semibold text-emerald-600/80">{{ $sym }}</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="lg:col-span-7 rounded-2xl border border-gray-200 bg-white shadow-xs p-5 flex flex-wrap items-center justify-between gap-3">
            <form method="GET" action="{{ route('customers.index') }}" class="flex items-center gap-2 flex-1 min-w-[220px]">
                <div class="relative flex-1">
                    <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8" />
                            <path d="m21 21-4.3-4.3" />
                        </svg>
                    </span>
                    <input type="text"
                           name="q"
                           value="{{ $search }}"
                           placeholder="بحث بالاسم أو الهاتف..."
                           class="w-full rounded-xl border border-gray-200 bg-gray-50/70 pr-9 pl-3 py-2.5 text-xs text-slate-900 placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                </div>
                <button type="submit"
                        class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold shrink-0 transition-colors cursor-pointer">
                    بحث
                </button>
            </form>

            <button type="button"
                    @click="addCust = true"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-xs transition-colors shrink-0 cursor-pointer">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 5v14M5 12h14" />
                </svg>
                <span>عميل جديد</span>
            </button>
        </div>
    </div>

    <!-- Customers Table Card -->
    <div class="rounded-2xl border border-gray-200 bg-white shadow-xs overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-slate-900 text-white flex items-center justify-center shrink-0">
                    <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-slate-900 truncate">دفتر العملاء والديون</h2>
                    <p class="text-[11px] text-gray-500 mt-0.5">متابعة السقوف الائتمانية والأرصدة المستحقة وسندات القبض</p>
                </div>
            </div>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-gray-100 text-slate-700 shrink-0 tabular-nums">
                {{ $customers->count() }} عميل
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-right">
                <thead class="bg-[#F9FAFB] border-b border-gray-200 text-[11px] font-semibold text-gray-500">
                    <tr>
                        <th class="px-4 py-3">العميل</th>
                        <th class="px-4 py-3">الهاتف</th>
                        <th class="px-4 py-3">السقف الائتماني</th>
                        <th class="px-4 py-3">الرصيد المستحق عليه</th>
                        <th class="px-4 py-3">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($customers as $c)
                        @php $hasDebt = \App\Support\MoneyMath::isPositive($c->current_balance); @endphp
                        <tr class="hover:bg-gray-50/80 transition-colors">
                            <td class="px-4 py-3.5 font-bold text-slate-900">
                                {{ $c->name }}
                            </td>
                            <td class="px-4 py-3.5 font-mono text-gray-600 tabular-nums whitespace-nowrap">
                                {{ $c->phone ?? '—' }}
                            </td>
                            <td class="px-4 py-3.5 font-semibold text-slate-700 tabular-nums whitespace-nowrap">
                                {{ format_money($c->credit_limit, $store->decimal_places) }}
                                <span class="text-[11px] font-medium text-gray-400">{{ $sym }}</span>
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap tabular-nums">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold {{ $hasDebt ? 'bg-rose-50 text-rose-700 border border-rose-200/70' : 'bg-emerald-50 text-emerald-700 border border-emerald-200/70' }}">
                                    <span>{{ format_money($c->current_balance, $store->decimal_places) }}</span>
                                    <span class="font-medium opacity-80">{{ $sym }}</span>
                                </span>
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap">
                                <div class="flex items-center gap-1.5">
                                    <button type="button"
                                            @click="settleCust = { id: {{ $c->id }}, name: '{{ addslashes($c->name) }}' }"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-600 text-emerald-700 hover:text-white border border-emerald-200/80 hover:border-emerald-600 text-[11px] font-semibold transition-colors cursor-pointer">
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="2" y="6" width="20" height="12" rx="2" />
                                            <circle cx="12" cy="12" r="2" />
                                            <path d="M6 12h.01M18 12h.01" />
                                        </svg>
                                        <span>سند قبض</span>
                                    </button>

                                    <a href="{{ route('customers.statement', $c->id) }}"
                                       class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 text-slate-700 text-[11px] font-semibold transition-colors">
                                        <svg class="w-3.5 h-3.5 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                            <polyline points="14 2 14 8 20 8" />
                                            <line x1="16" y1="13" x2="8" y2="13" />
                                            <line x1="16" y1="17" x2="8" y2="17" />
                                        </svg>
                                        <span>كشف حساب</span>
                                    </a>

                                    <a href="{{ \App\Support\WhatsAppLink::forCustomerDebt($c, $store) }}"
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
                            <td colspan="5" class="px-4 py-14 text-center">
                                <div class="w-12 h-12 rounded-2xl bg-gray-100 text-gray-400 mx-auto mb-3 flex items-center justify-center">
                                    <svg class="w-5.5 h-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                                        <circle cx="9" cy="7" r="4" />
                                    </svg>
                                </div>
                                <p class="text-xs font-semibold text-slate-700">لا يوجد عملاء مسجلون.</p>
                                <p class="text-[11px] text-gray-400 mt-1">أضف عميلاً جديداً لمتابعة مبيعات الآجل وسندات القبض.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($customers->hasPages())
            <div class="px-5 py-3.5 border-t border-gray-100 bg-[#F9FAFB]">
                {{ $customers->links() }}
            </div>
        @endif
    </div>

    <!-- Add Customer Modal -->
    <div x-show="addCust"
         style="display:none;"
         class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-[1px] flex items-center justify-center p-4">
        <form method="POST"
              action="{{ route('customers.store') }}"
              @click.outside="addCust = false"
              class="rounded-2xl border border-gray-200 bg-white shadow-xl max-w-md w-full overflow-hidden text-xs">
            @csrf

            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-emerald-50 border border-emerald-200/70 text-emerald-700 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 5v14M5 12h14" />
                        </svg>
                    </div>
                    <h3 class="font-bold text-sm text-slate-900">إضافة عميل جديد</h3>
                </div>
                <button type="button"
                        @click="addCust = false"
                        class="p-1.5 rounded-lg text-gray-400 hover:text-slate-700 hover:bg-gray-100 transition-colors cursor-pointer">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-5 space-y-3.5">
                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">
                        اسم العميل <span class="text-rose-600">*</span>
                    </label>
                    <input type="text"
                           name="name"
                           required
                           placeholder="اسم العميل *"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs text-slate-900 placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                </div>

                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">رقم الهاتف / واتساب</label>
                    <input type="text"
                           name="phone"
                           placeholder="رقم الهاتف / واتساب"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs font-mono text-slate-900 tabular-nums placeholder:font-sans placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label class="block font-semibold text-slate-700">السقف الائتماني المسموح</label>
                        <input type="number"
                               step="any"
                               name="credit_limit"
                               value="50000"
                               placeholder="السقف الائتماني المسموح"
                               class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs font-semibold text-slate-900 tabular-nums placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-semibold text-slate-700">رصيد دين افتتاحي سابق</label>
                        <input type="number"
                               step="any"
                               name="opening_balance"
                               value="0"
                               placeholder="رصيد دين افتتاحي سابق"
                               class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs font-semibold text-slate-900 tabular-nums placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                    </div>
                </div>
            </div>

            <div class="px-5 py-3.5 border-t border-gray-100 bg-[#F9FAFB] flex justify-end gap-2">
                <button type="button"
                        @click="addCust = false"
                        class="px-4 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-slate-700 font-semibold transition-colors cursor-pointer">
                    إلغاء
                </button>
                <button type="submit"
                        class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold shadow-xs transition-colors cursor-pointer">
                    حفظ العميل
                </button>
            </div>
        </form>
    </div>

    <!-- Quick Credit Settlement Modal -->
    <div x-show="settleCust"
         style="display:none;"
         class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-[1px] flex items-center justify-center p-4">
        <form method="POST"
              :action="`/customers/${settleCust?.id}/settlements`"
              @click.outside="settleCust = null"
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
                        سند قبض تسديد دين: <span class="text-emerald-700" x-text="settleCust?.name"></span>
                    </h3>
                </div>
                <button type="button"
                        @click="settleCust = null"
                        class="p-1.5 rounded-lg text-gray-400 hover:text-slate-700 hover:bg-gray-100 transition-colors cursor-pointer shrink-0">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-5 space-y-3.5">
                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">
                        المبلغ المستلم <span class="text-rose-600">*</span>
                    </label>
                    <input type="number"
                           step="any"
                           name="amount"
                           required
                           placeholder="المبلغ المستلم *"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs font-bold text-slate-900 tabular-nums placeholder:font-normal placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                </div>

                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">طريقة الاستلام</label>
                    <select name="method"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                        <option value="cash">نقدي (يدخل درج الوردية)</option>
                        <option value="kuraimi">الكريمي</option>
                        <option value="jawali">جوالي</option>
                        <option value="floosak">فلوسك</option>
                        <option value="onecash">ون كاش</option>
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">الوردية الحالية (للنقدي)</label>
                    <select name="shift_id"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                        <option value="">— الوردية الحالية (للنقدي) —</option>
                        @foreach ($openShifts as $sh)
                            <option value="{{ $sh->id }}">{{ $sh->terminal?->name }} (#{{ $sh->id }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">رقم الإشعار / الحوالة</label>
                    <input type="text"
                           name="reference_number"
                           placeholder="رقم الإشعار / الحوالة"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs text-slate-900 placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                </div>
            </div>

            <div class="px-5 py-3.5 border-t border-gray-100 bg-[#F9FAFB] flex justify-end gap-2">
                <button type="button"
                        @click="settleCust = null"
                        class="px-4 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-slate-700 font-semibold transition-colors cursor-pointer">
                    إلغاء
                </button>
                <button type="submit"
                        class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold shadow-xs transition-colors cursor-pointer">
                    تأكيد القبض
                </button>
            </div>
        </form>
    </div>

</div>
@endsection