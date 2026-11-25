@extends('layouts.app')
@section('title', 'العمال والرواتب والسلف')
@section('header', 'حسابات العمال والموظفين والرواتب والسلف')

@section('content')
@php $sym = $store->currency_symbol; @endphp

<div class="space-y-6" x-data="{ addEmp: false, txEmp: null }">

    <!-- Top Actions & Salary Accrual Bar -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-stretch">
        <div class="lg:col-span-8 rounded-2xl border border-gray-200 bg-white shadow-xs p-5 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-xl bg-slate-900 text-white flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                        <line x1="16" y1="2" x2="16" y2="6" />
                        <line x1="8" y1="2" x2="8" y2="6" />
                        <line x1="3" y1="10" x2="21" y2="10" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-slate-900">اعتماد رواتب الفترة</h2>
                    <p class="text-[11px] text-gray-500 mt-0.5">توليد استحقاق الرواتب الدورية للعمال المسجلين</p>
                </div>
            </div>

            <form method="POST" action="{{ route('employees.accrue-salaries') }}" class="flex flex-wrap items-center gap-2 text-xs">
                @csrf
                <input type="text"
                       name="period_label"
                       value="{{ now()->format('Y-m') }}"
                       required
                       class="w-32 text-center rounded-xl border border-gray-200 bg-gray-50/70 px-3 py-2 text-xs font-mono font-bold text-slate-900 tabular-nums focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 6 9 17l-5-5" />
                    </svg>
                    <span>توليد استحقاق الرواتب</span>
                </button>
            </form>
        </div>

        <div class="lg:col-span-4 rounded-2xl border border-gray-200 bg-white shadow-xs p-5 flex items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs font-bold text-slate-900">فريق العمل</p>
                <p class="text-[11px] text-gray-500 mt-0.5 tabular-nums">{{ $employees->count() }} عامل / موظف</p>
            </div>
            <button type="button"
                    @click="addEmp = true"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-xs transition-colors shrink-0 cursor-pointer">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 5v14M5 12h14" />
                </svg>
                <span>إضافة عامل / موظف</span>
            </button>
        </div>
    </div>

    <!-- Employees Table Card -->
    <div class="rounded-2xl border border-gray-200 bg-white shadow-xs overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-slate-900 text-white flex items-center justify-center shrink-0">
                    <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-slate-900 truncate">كشف العمال والموظفين</h2>
                    <p class="text-[11px] text-gray-500 mt-0.5">متابعة الرواتب والسلف الشهرية والأرصدة المستحقة</p>
                </div>
            </div>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-gray-100 text-slate-700 shrink-0 tabular-nums">
                {{ $employees->count() }} عامل
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-right">
                <thead class="bg-[#F9FAFB] border-b border-gray-200 text-[11px] font-semibold text-gray-500">
                    <tr>
                        <th class="px-4 py-3">العامل</th>
                        <th class="px-4 py-3">المسمى / الراتب</th>
                        <th class="px-4 py-3">سلف هذا الشهر</th>
                        <th class="px-4 py-3">صافي الرصيد الحالي</th>
                        <th class="px-4 py-3">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($employees as $emp)
                        @php $isNeg = \App\Support\MoneyMath::isNegative($emp->current_balance); @endphp
                        <tr class="hover:bg-gray-50/80 transition-colors">
                            <td class="px-4 py-3.5">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="font-bold text-slate-900">{{ $emp->name }}</span>
                                    @if (!$emp->user_id)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-gray-100 text-gray-500 border border-gray-200/70">
                                            بدون دخول نظام
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="font-semibold text-slate-800">{{ $emp->job_title ?? 'عامل' }}</div>
                                <div class="text-[11px] text-gray-500 tabular-nums mt-0.5">
                                    {{ format_money($emp->base_salary, $store->decimal_places) }} {{ $sym }}
                                    <span class="text-gray-300">/</span>
                                    <span>{{ $emp->salary_type }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap tabular-nums">
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200/70">
                                    <span>{{ format_money($emp->month_advances ?? 0, $store->decimal_places) }}</span>
                                    <span class="font-medium opacity-80">{{ $sym }}</span>
                                </span>
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap tabular-nums">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold {{ $isNeg ? 'bg-rose-50 text-rose-700 border border-rose-200/70' : 'bg-emerald-50 text-emerald-700 border border-emerald-200/70' }}">
                                    <span>{{ format_money($emp->current_balance, $store->decimal_places) }}</span>
                                    <span class="font-medium opacity-80">{{ $sym }}</span>
                                </span>
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap">
                                <div class="flex items-center gap-1.5">
                                    <button type="button"
                                            @click="txEmp = { id: {{ $emp->id }}, name: '{{ addslashes($emp->name) }}' }"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-amber-50 hover:bg-amber-600 text-amber-800 hover:text-white border border-amber-200/80 hover:border-amber-600 text-[11px] font-semibold transition-colors cursor-pointer">
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="2" y="6" width="20" height="12" rx="2" />
                                            <circle cx="12" cy="12" r="2" />
                                            <path d="M6 12h.01M18 12h.01" />
                                        </svg>
                                        <span>سلفة / حركة</span>
                                    </button>

                                    <a href="{{ route('employees.statement', $emp->id) }}"
                                       class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 text-slate-700 text-[11px] font-semibold transition-colors">
                                        <svg class="w-3.5 h-3.5 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                            <polyline points="14 2 14 8 20 8" />
                                            <line x1="16" y1="13" x2="8" y2="13" />
                                            <line x1="16" y1="17" x2="8" y2="17" />
                                        </svg>
                                        <span>كشف حساب</span>
                                    </a>

                                    <a href="{{ \App\Support\WhatsAppLink::forEmployeeStatement($emp, $store) }}"
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
                                <p class="text-xs font-semibold text-slate-700">لا يوجد عمال مسجلون.</p>
                                <p class="text-[11px] text-gray-400 mt-1">أضف أول عامل أو موظف لمتابعة الرواتب والسلف.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Employee Modal -->
    <div x-show="addEmp"
         style="display:none;"
         class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-[1px] flex items-center justify-center p-4">
        <form method="POST"
              action="{{ route('employees.store') }}"
              @click.outside="addEmp = false"
              class="rounded-2xl border border-gray-200 bg-white shadow-xl max-w-md w-full overflow-hidden text-xs">
            @csrf

            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-emerald-50 border border-emerald-200/70 text-emerald-700 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 5v14M5 12h14" />
                        </svg>
                    </div>
                    <h3 class="font-bold text-sm text-slate-900">إضافة عامل جديد</h3>
                </div>
                <button type="button"
                        @click="addEmp = false"
                        class="p-1.5 rounded-lg text-gray-400 hover:text-slate-700 hover:bg-gray-100 transition-colors cursor-pointer">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-5 space-y-3.5">
                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">
                        اسم العامل <span class="text-rose-600">*</span>
                    </label>
                    <input type="text"
                           name="name"
                           required
                           placeholder="اسم العامل *"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs text-slate-900 placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                </div>

                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">المسمى الوظيفي</label>
                    <input type="text"
                           name="job_title"
                           placeholder="المسمى الوظيفي (عامل مخزن / كاشير / توصيل)"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs text-slate-900 placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                </div>

                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">رقم الجوال / واتساب</label>
                    <input type="text"
                           name="phone"
                           placeholder="رقم الجوال / واتساب"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs font-mono text-slate-900 tabular-nums placeholder:font-sans placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                </div>

                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">ربط بحساب دخول للنظام</label>
                    <select name="user_id"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                        <option value="">— بدون حساب دخول للنظام —</option>
                        @foreach ($storeUsers as $su)
                            <option value="{{ $su->id }}">{{ $su->name }} ({{ $su->email }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label class="block font-semibold text-slate-700">نوع الراتب</label>
                        <select name="salary_type"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3 py-2.5 text-xs text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                            <option value="monthly">راتب شهري</option>
                            <option value="weekly">راتب أسبوعي</option>
                            <option value="daily">أجر يومي</option>
                            <option value="commission_only">عمولة فقط</option>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-semibold text-slate-700">
                            الراتب الأساسي <span class="text-rose-600">*</span>
                        </label>
                        <input type="number"
                               step="any"
                               name="base_salary"
                               value="0"
                               required
                               placeholder="الراتب الأساسي"
                               class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs font-semibold text-slate-900 tabular-nums placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                    </div>
                </div>
            </div>

            <div class="px-5 py-3.5 border-t border-gray-100 bg-[#F9FAFB] flex justify-end gap-2">
                <button type="button"
                        @click="addEmp = false"
                        class="px-4 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-slate-700 font-semibold transition-colors cursor-pointer">
                    إلغاء
                </button>
                <button type="submit"
                        class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold shadow-xs transition-colors cursor-pointer">
                    حفظ العامل
                </button>
            </div>
        </form>
    </div>

    <!-- Quick Advance / Transaction Modal -->
    <div x-show="txEmp"
         style="display:none;"
         class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-[1px] flex items-center justify-center p-4">
        <form method="POST"
              :action="`/employees/${txEmp?.id}/transactions`"
              @click.outside="txEmp = null"
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
                        حركة مالية للعامل: <span class="text-emerald-700" x-text="txEmp?.name"></span>
                    </h3>
                </div>
                <button type="button"
                        @click="txEmp = null"
                        class="p-1.5 rounded-lg text-gray-400 hover:text-slate-700 hover:bg-gray-100 transition-colors cursor-pointer shrink-0">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-5 space-y-3.5">
                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">نوع الحركة</label>
                    <select name="type"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                        <option value="advance">سلفة / مصروف يومي (-)</option>
                        <option value="salary_payout">صرف صافي راتب (-)</option>
                        <option value="bonus">حافز / مكافأة (+)</option>
                        <option value="deduction">خصم غياب / إتلاف (-)</option>
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
                           placeholder="المبلغ *"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs font-bold text-slate-900 tabular-nums placeholder:font-normal placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                </div>

                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">مصدر الصرف</label>
                    <select name="payment_source"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                        <option value="owner_pocket">من جيب المالك / الإدارة</option>
                        <option value="cash_drawer">من درج الكاشير المفتوح</option>
                        <option value="kuraimi">حوالة الكريمي</option>
                        <option value="jawali">جوالي</option>
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">الوردية (عند السحب من الدرج)</label>
                    <select name="shift_id"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                        <option value="">— اختر الوردية (عند السحب من الدرج) —</option>
                        @foreach ($openShifts as $sh)
                            <option value="{{ $sh->id }}">{{ $sh->terminal?->name }} (#{{ $sh->id }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">ملاحظة / سبب السلفة</label>
                    <input type="text"
                           name="notes"
                           placeholder="ملاحظة / سبب السلفة"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs text-slate-900 placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                </div>
            </div>

            <div class="px-5 py-3.5 border-t border-gray-100 bg-[#F9FAFB] flex justify-end gap-2">
                <button type="button"
                        @click="txEmp = null"
                        class="px-4 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-slate-700 font-semibold transition-colors cursor-pointer">
                    إلغاء
                </button>
                <button type="submit"
                        class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold shadow-xs transition-colors cursor-pointer">
                    اعتماد الحركة
                </button>
            </div>
        </form>
    </div>

</div>
@endsection